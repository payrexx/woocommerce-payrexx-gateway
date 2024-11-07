<?php

namespace PayrexxPaymentGateway\Service;

use Payrexx\Models\Response\Transaction;

class OrderService
{
    const WC_STATUS_CANCELLED = 'cancelled';
    const WC_STATUS_FAILED = 'failed';
    const WC_STATUS_REFUNDED = 'refunded';
    const WC_STATUS_PROCESSING = 'processing';
    const WC_STATUS_COMPLETED = 'completed';
    const WC_STATUS_ONHOLD = 'on-hold';
	const WC_STATUS_PENDING = 'pending';

    const STATUS_MESSAGES = [
        self::WC_STATUS_CANCELLED => 'Payment was cancelled by the customer',
        self::WC_STATUS_FAILED => 'An error occured while processing this payment',
        self::WC_STATUS_REFUNDED => 'Payment was fully refunded',
        self::WC_STATUS_ONHOLD => 'Awaiting payment',
		Transaction::PARTIALLY_REFUNDED => 'Payment was partially refunded',
	];

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
					self::STATUS_MESSAGES[ $payrexx_status ] . ' ( ' . $transaction_uuid . ' )'
				);
				return;
			case Transaction::CANCELLED:
			case Transaction::EXPIRED:
			case Transaction::DECLINED:
				$order_status = self::WC_STATUS_CANCELLED;
				break;
			case Transaction::ERROR:
				$order_status = self::WC_STATUS_FAILED;
		}

		if ( ! $order_status || ! $this->transition_allowed( $order_status, $order ) ) {
			return;
		}

		$this->transitionOrder( $order, $order_status, $transaction_uuid );
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

		if ( $new_status === $old_status) {
			return false;
		}

		if ( $order->get_transaction_id() &&
			$new_status !== self::WC_STATUS_REFUNDED
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
		return true;
	}

	/**
	 * Transtition the order
	 *
	 * @param order  $order            order.
	 * @param string $order_status     order status.
	 * @param string $transaction_uuid payrexx transaction uuid.
	 * @return void
	 */
	public function transitionOrder( $order, string $order_status, string $transaction_uuid = '' ) {
		$custom_status = apply_filters( 'woo_payrexx_custom_transaction_status_' . $order_status, $order_status );
		if ( $transaction_uuid ) {
			$transaction_uuid = ' ( ' . $transaction_uuid . ' )';
		}
		$order->update_status(
			$custom_status,
			__( self::STATUS_MESSAGES[$order_status] . $transaction_uuid, 'wc-payrexx-gateway' )
		);
	}

    /**
     * @param $order
     * @param $transactionUuid
     * @return void
     */
    private function setOrderPaid($order, $transactionUuid) {
		if ( ! $this->transition_allowed( self::WC_STATUS_PROCESSING, $order->get_status() ) ) {
			return;
		}

        $order->payment_complete($transactionUuid);
        // Remove cart
        WC()->cart->empty_cart();
    }
}