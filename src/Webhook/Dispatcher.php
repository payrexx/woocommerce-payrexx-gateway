<?php

namespace PayrexxPaymentGateway\Webhook;

use Payrexx\Models\Response\Transaction;

use PayrexxPaymentGateway\Service\OrderService;
use PayrexxPaymentGateway\Service\PayrexxApiService;
use PayrexxPaymentGateway\Util\StatusUtil;

class Dispatcher
{
    /**
     * @var PayrexxApiService
     */
    private $payrexx_api_service;

    /**
     * @var OrderService
     */
    private $order_service;

    /**
     * Settings prefix
     *
     * @var string
     */
    private $prefix;

    /**
     * @param $payrexx_api_service
     * @param $order_service
     * @param $prefix
     */
    public function __construct($payrexx_api_service, $order_service, $prefix)
    {
        $this->payrexx_api_service = $payrexx_api_service;
        $this->order_service = $order_service;
        $this->prefix = $prefix;
    }

    /**
     * @return void
     * @throws \Exception
     */
    public function check_webhook_response()
    {
        try {
            $resp = $_REQUEST;
			$order_id = $resp['transaction']['invoice']['referenceId'] ?? '';
			$gateway_id = $resp['transaction']['invoice']['paymentRequestId'] ?? '';

			if ( empty( $order_id ) ) {
				$this->send_response( 'Webhook data incomplete' );
			}

            if (!empty($this->prefix) && strpos($order_id, $this->prefix) === false) {
                $this->send_response('Prefix mismatch');
            }

            $arr = explode('_', $order_id);
            $order_id = end($arr);

            if (!isset($resp['transaction']['status'])) {
                throw new \Exception('Missing transaction status');
            }

            /**
             * @var \Payrexx\Models\Response\Transaction
             */
            $transaction = $this->payrexx_api_service->getPayrexxTransaction($resp['transaction']['id']);

            if ($transaction->getStatus() !== $resp['transaction']['status']) {
                throw new \Exception('Fraudulent transaction status');
            }

            // Check if subscription to handle accordingly
            $subscriptions = [];
            $preAuthId = null;
            if (!empty($resp['transaction']['preAuthorizationId'])) {
                $subscriptions = wcs_get_subscriptions_for_order($order_id, array('order_type' => 'any'));

                // $order_id is the subscription id in case of payment method change. In this case $subscriptions will be empty
                if (!$subscriptions) {
                    $subscriptions[] = new \WC_Subscription($order_id);
                }

                // Identify the correct order_id
                // Automatic subscription payment > order_id is already valid and matches the referenceId. It must not be overwritten
                // Payment method change > $order_id is a subscriptionId and must be overwritten
                // Subscription renewal > $order_id is from an old order and must be overwritten
                $firstSubscription = reset($subscriptions);
                $preAuthId = $resp['transaction']['preAuthorizationId'];
                // Check if the subscription is associated with a switch order (upgrade/downgrade)
                if ($firstSubscription->has_status( 'active' ) && $firstSubscription->get_last_order() > 0) {
                    $order_id = $firstSubscription->get_last_order();
                } else {
                    // If it's a switch order, get the switch order ID
                    $order_id = $firstSubscription->get_meta( '_subscription_switch_order_id' );

                    // Fallback: Use the last order ID associated with the subscription if the switch order ID is not found
                    if (empty($order_id)) {
                        $order_id = $firstSubscription->get_last_order();
                    }
                }
            }

            $order = new \WC_Order($order_id);

            if (!$order_id || !$order) {
                throw new \Exception('Fraudulent request');
            }

            $orderTotal = round(floatval($order->get_total('edit')), 2);
            $newTransactionStatus = $transaction->getStatus();

            // A confirmed transaction can also be a partial payment (with bank transfer).
            // Therefore the new correct status must be determined
            if (in_array($newTransactionStatus, [Transaction::CONFIRMED, Transaction::REFUNDED, Transaction::PARTIALLY_REFUNDED]) && !$preAuthId) {
                $gateway = $this->payrexx_api_service->getPayrexxGateway($gateway_id);
                $confirmedAmount = StatusUtil::getAmountByStatusAndGateway($gateway, [Transaction::CONFIRMED]);
                $refundedAmount = StatusUtil::getAmountByStatusAndGateway($gateway, [Transaction::PARTIALLY_REFUNDED, Transaction::REFUNDED]);

                $newTransactionStatus = StatusUtil::determineNewOrderStatus($orderTotal, $confirmedAmount, $refundedAmount);
            }

            $transactionUuid = $transaction->getUuid();
            $this->order_service->handleTransactionStatus($order, $subscriptions, $newTransactionStatus, $transactionUuid, $preAuthId);
            $this->send_response('Success: Processed webhook response');

        } catch (\Exception $e) {
            throw new \Exception('Error: ' . $e->getMessage());
        }
    }

    /**
     * Returns webhook response.
     *
     * @param string $message success or error message.
     * @param array $data response data.
     * @param string|int $response_code response code.
     */
    private function send_response($message, $data = [], $response_code = 200)
    {
        $response['message'] = $message;
        if (!empty($data)) {
            $response['data'] = $data;
        }
        echo wp_json_encode($response);
        http_response_code($response_code);
        die;
    }
}
