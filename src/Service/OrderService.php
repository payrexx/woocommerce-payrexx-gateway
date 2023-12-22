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
        ];

    /**
     * @param $order
     * @param array $subscriptions
     * @param $payrexxStatus
     * @param $transactionUuid
     * @param $preAuthId
     * @return void
     */
    public function handleTransactionStatus($order, array $subscriptions, $payrexxStatus, $transactionUuid, $preAuthId = '') {
        $newTransactionStatus = '';

        switch ($payrexxStatus) {
            case Transaction::WAITING:
                $newTransactionStatus = self::WC_STATUS_ONHOLD;
                break;
            case Transaction::CONFIRMED:
                $this->setOrderPaid($order, $transactionUuid);
                return;
            case Transaction::AUTHORIZED:
                foreach ($subscriptions as $subscription) {
                    $subscription->update_meta_data('payrexx_auth_transaction_id', $preAuthId);
                    $subscription->save();
                }

                // An order with amount 0 is considered as paid if the authorization is successful
                if (floatval($order->get_total('edit')) === 0.0) {
                    $this->setOrderPaid($order, $transactionUuid);
                }
                break;
            case Transaction::REFUNDED:
                $newTransactionStatus = self::WC_STATUS_REFUNDED;
                break;
//            case Transaction::PARTIALLY_REFUNDED:
//                if ($order->get_status() === 'refunded') {
//                    break;
//                }
//                $order->update_status('refunded', __('Payment was partially refunded', 'wc-payrexx-gateway'));
//                break;
            case Transaction::CANCELLED:
            case Transaction::EXPIRED:
            case Transaction::DECLINED:
                $newTransactionStatus = self::WC_STATUS_CANCELLED;
                break;
            case Transaction::ERROR:
                $newTransactionStatus = self::WC_STATUS_FAILED;
        }

		if ( ! $newTransactionStatus || ! $this->transition_allowed( $newTransactionStatus, $order->get_status() ) ) {
			return;
		}

        $this->transitionOrder($order, $newTransactionStatus);
    }

	/**
	 * Check order transition allowed
	 *
	 * @param string $new_status new order status.
	 * @param string $old_status old order status.
	 * @return bool
	 */
	public function transition_allowed( string $new_status, string $old_status ): bool {
		if ( $new_status === $old_status ) {
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
     * @param $order
     * @param $newStatus
     * @return void
     */
    public function transitionOrder($order, $newStatus) {
        $customStatus = apply_filters('woo_payrexx_custom_transaction_status_' . $newStatus, $newStatus);
        $order->update_status($customStatus,  __(self::STATUS_MESSAGES[$newStatus], 'wc-payrexx-gateway'));
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