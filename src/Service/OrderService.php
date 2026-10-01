<?php

namespace PayrexxPaymentGateway\Service;

use Payrexx\Models\Response\Transaction;
use WC_Order;

class OrderService
{
    const WC_STATUS_CANCELLED = 'cancelled';
    const WC_STATUS_FAILED = 'failed';
    const WC_STATUS_REFUNDED = 'refunded';
    const WC_STATUS_PROCESSING = 'processing';
    const WC_STATUS_COMPLETED = 'completed';
    const WC_STATUS_ONHOLD = 'on-hold';
	const WC_STATUS_PENDING = 'pending';

	/**
	 * Handle transaction status
	 *
	 * @param Order  $order            Order.
	 * @param array  $subscriptions    subscriptions.
	 * @param string $payrexx_status   payrexx transaction status.
	 * @param string $transaction_uuid payrexx transaction uuid.
	 * @param string $pre_auth_id      preauth id.
	 * @return void
	 */
	public function handleTransactionStatus(
		$order,
		array $subscriptions,
		$payrexx_status,
		$transaction_uuid,
		$pre_auth_id = ''
	) {
		$order_status = '';
		$message_key = '';

		$this->clear_payrexx_unpaid_order_timeout_event( $order->get_id() );
		switch ( $payrexx_status ) {
			case Transaction::WAITING:
				$order_status = self::WC_STATUS_ONHOLD;
				break;
			case Transaction::CONFIRMED:
				$this->setOrderPaid( $order, $transaction_uuid );
				return;
			case Transaction::AUTHORIZED:
				foreach ( $subscriptions as $subscription ) {
					$subscription->update_meta_data( 'payrexx_auth_transaction_id', $pre_auth_id );
					$subscription->save();
				}

				// An order with amount 0 is considered as paid if the authorization is successful.
				if ( floatval( $order->get_total( 'edit' ) ) === 0.0 ) {
					$this->setOrderPaid( $order, $transaction_uuid );
				}
				break;
			case Transaction::REFUNDED:
				$order_status = self::WC_STATUS_REFUNDED;
				break;
			case Transaction::PARTIALLY_REFUNDED:
				if ( $order->get_status() === self::WC_STATUS_REFUNDED ) {
					break;
				}
				$order->add_order_note(
					$this->getStatusMessage( $payrexx_status ) . ' ( ' . $transaction_uuid . ' )'
				);
				return;
			case Transaction::CANCELLED:
				$order_status = self::WC_STATUS_CANCELLED;
				break;
			case Transaction::EXPIRED:
			case Transaction::DECLINED:
				// Retryable failure: 'failed' keeps a WCS subscription on-hold (retry), 'cancelled' would end it permanently.
				$order_status = $this->orderContainsSubscription( $order )
					? self::WC_STATUS_FAILED
					: self::WC_STATUS_CANCELLED;
				$message_key = $payrexx_status;
				break;
			case Transaction::ERROR:
				$order_status = self::WC_STATUS_FAILED;
		}

		if ( ! $order_status || ! $this->transition_allowed( $order_status, $order ) ) {
			return;
		}

		$this->transitionOrder( $order, $order_status, $transaction_uuid, $message_key );
	}

	/**
	 * The generic gateway is 'payrexx', the single methods 'payrexx_<pm>'.
	 *
	 * @param WC_Order $order woocommerce order.
	 * @return bool
	 */
	public function isPayrexxOrder( WC_Order $order ): bool {
		return str_starts_with( (string) $order->get_payment_method(), 'payrexx' );
	}

	/**
	 * Check whether the order belongs to a WooCommerce subscription
	 *
	 * @param WC_Order $order woocommerce order.
	 * @return bool
	 */
	public function orderContainsSubscription( $order ): bool {
		return function_exists( 'wcs_order_contains_subscription' )
			&& wcs_order_contains_subscription( $order, 'any' );
	}

	/**
	 * Cancel an order that stayed unpaid past the gateway timeout (PP-17648: frees a
	 * blocked WooCommerce Bookings slot when the customer just closes the tab).
	 *
	 * Deliberately does not cancel Payrexx transactions: 'pending' only means no webhook
	 * arrived, not that the customer abandoned the checkout. A waiting transaction here
	 * belongs to an invoice the customer already received and may still pay.
	 *
	 * @param WC_Order $order woocommerce order.
	 * @return void
	 */
	public function autoCancelUnpaidOrder(WC_Order $order ): void
    {
		if ( $order->is_paid() || self::WC_STATUS_PENDING !== $order->get_status() ) {
			return;
		}

		if ( $this->orderContainsSubscription( $order ) ) {
			// 'failed' keeps the WCS subscription on-hold instead of cancelling it permanently.
			$order->update_status(
				self::WC_STATUS_FAILED,
				__( 'Payment not received within 15 minutes (Payrexx).' )
			);
			return;
		}

		$order->update_status(
			self::WC_STATUS_CANCELLED,
			__( 'Automatically cancelled – payment not received within 15 minutes (Payrexx).' )
		);
	}

	/**
	 * Check order transition allowed
	 *
	 * @param string $new_status new order status.
	 * @param WC_Order $order woocommerce order.
	 * @return bool
	 */
	public function transition_allowed( string $new_status, $order ): bool {
		$old_status = $order->get_status();

		if ( $new_status === $old_status ||
			(
				$order->get_transaction_id() && // Check paid
				$new_status !== self::WC_STATUS_REFUNDED // Refund allowed
			)
		) {
			return false;
		}

		switch ( $new_status ) {
			case self::WC_STATUS_CANCELLED:
			case self::WC_STATUS_FAILED:
				return in_array( $old_status, [ self::WC_STATUS_PENDING, self::WC_STATUS_ONHOLD ] );
			case self::WC_STATUS_PROCESSING:
				return ! in_array( $old_status, [ self::WC_STATUS_COMPLETED, self::WC_STATUS_REFUNDED ] );
			case self::WC_STATUS_REFUNDED:
				return in_array( $old_status, [ self::WC_STATUS_PROCESSING, self::WC_STATUS_COMPLETED ] );
			case self::WC_STATUS_ONHOLD:
				return self::WC_STATUS_PENDING === $old_status;
		}
		return false;
	}

	/**
	 * Transtition the order
	 *
	 * @param WC_Order $order         order.
	 * @param string $order_status     order status.
	 * @param string $transaction_uuid payrexx transaction uuid.
	 * @param string $message_key      order note key, defaults to the order status.
	 * @return void
	 */
	public function transitionOrder(
		WC_Order $order,
		string $order_status,
		string $transaction_uuid = '',
		string $message_key = ''
	): void {
		$custom_status = apply_filters( 'woo_payrexx_custom_transaction_status_' . $order_status, $order_status );
		if ( ! $message_key ) {
			$message_key = $order_status;
		}
		// Translate before appending the uuid, otherwise the string never matches a .po entry.
		$note = $this->getStatusMessage( $message_key );
		if ( $transaction_uuid ) {
			$note .= ' ( ' . $transaction_uuid . ' )';
		}
		$order->update_status( $custom_status, $note );
	}

	/**
	 * Literal strings so gettext extraction picks them up.
	 *
	 * @param string $key order status or payrexx transaction status.
	 * @return string
	 */
	private function getStatusMessage( string $key ): string {
		return match ( $key ) {
			self::WC_STATUS_CANCELLED => __( 'Payment was cancelled by the customer', 'woo-payrexx-gateway' ),
			self::WC_STATUS_FAILED => __( 'An error occured while processing this payment', 'woo-payrexx-gateway' ),
			self::WC_STATUS_REFUNDED => __( 'Payment was fully refunded', 'woo-payrexx-gateway' ),
			self::WC_STATUS_ONHOLD => __( 'Awaiting payment', 'woo-payrexx-gateway' ),
			Transaction::PARTIALLY_REFUNDED => __( 'Payment was partially refunded', 'woo-payrexx-gateway' ),
			Transaction::EXPIRED => __( 'Payment expired', 'woo-payrexx-gateway' ),
			Transaction::DECLINED => __( 'Payment was declined', 'woo-payrexx-gateway' ),
			default => '',
		};
	}

    /**
     * @param $order
     * @param $transactionUuid
     * @return void
     */
    private function setOrderPaid($order, $transactionUuid) {
		if ( ! $this->transition_allowed( self::WC_STATUS_PROCESSING, $order ) ) {
			return;
		}

        $order->payment_complete($transactionUuid);
        // Remove cart
        WC()->cart->empty_cart();
    }

	public function clear_payrexx_unpaid_order_timeout_event( $order_id ) {
		wp_clear_scheduled_hook(
			'payrexx_unpaid_order_timeout_event',
			[ $order_id ]
    	);
	}
}
