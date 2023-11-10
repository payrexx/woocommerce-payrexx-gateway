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

        if (!$newTransactionStatus || !$this->transitionAllowed($newTransactionStatus, $order->get_status())) {
            return;
        }

        $this->transitionOrder($order, $newTransactionStatus);
    }

    /**
     * @param string $newStatus
     * @param string $oldStatus
     * @return bool
     */
    public function transitionAllowed(string $newStatus, string $oldStatus)
    {
        switch($newStatus) {
            case self::WC_STATUS_CANCELLED:
                return !in_array($oldStatus, [self::WC_STATUS_CANCELLED, self::WC_STATUS_PROCESSING, self::WC_STATUS_COMPLETED]);
            case self::WC_STATUS_FAILED:
                return !in_array($oldStatus, [self::WC_STATUS_FAILED, self::WC_STATUS_PROCESSING, self::WC_STATUS_COMPLETED]);
            case self::WC_STATUS_PROCESSING:
                return !in_array($oldStatus, [self::WC_STATUS_PROCESSING, self::WC_STATUS_COMPLETED]);
            case self::WC_STATUS_REFUNDED:
            case self::WC_STATUS_ONHOLD:
            default:
                return $oldStatus != $newStatus;
        }
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
        if (!$this->transitionAllowed(self::WC_STATUS_PROCESSING, $order->get_status())) {
            return;
        }

        $order->payment_complete($transactionUuid);
        // Remove cart
        WC()->cart->empty_cart();
    }
}