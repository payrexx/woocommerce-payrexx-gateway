<?php

namespace PayrexxPaymentGateway\Webhook;

use Exception;
use Payrexx\Models\Response\Transaction;

use PayrexxPaymentGateway\Service\OrderService;
use PayrexxPaymentGateway\Service\PayrexxApiService;
use PayrexxPaymentGateway\Util\StatusUtil;
use Throwable;

class Dispatcher
{
    const BRAND_BANK_TRANSFER = 'bank-transfer';
    const PSP_NATIVE = 'Native_PSP';

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
     * @throws Exception
     */
    public function check_webhook_response(): void
    {
        try {
            $resp = $_REQUEST;

            if (empty($resp['transaction']['id'])) {
                throw new Exception('Missing transaction id');
            }

            if (!isset($resp['transaction']['status'])) {
                throw new Exception('Missing transaction status');
            }

            // This endpoint is public and unauthenticated by design - Payrexx calls it
            // directly and the notification carries no signature. Every value that decides
            // WHICH order is touched and BY HOW MUCH must therefore be read back from the
            // transaction we fetch with our own API credentials, never from $_REQUEST.
            // Taken from the request they only prove that some confirmed transaction
            // exists somewhere, which lets a replay bind a real payment to a foreign order.
            $transaction = $this->payrexx_api_service->getPayrexxTransaction((int) $resp['transaction']['id']);

            if (!$transaction) {
                throw new Exception('Fraudulent request: transaction not found');
            }

            if ($transaction->getStatus() !== $resp['transaction']['status']) {
                throw new Exception('Fraudulent transaction status');
            }

            $invoice = $transaction->getInvoice();
            $invoice = is_array($invoice) ? $invoice : [];

            // referenceId is written by this shop itself when the gateway is created
            // (PayrexxApiService::createPayrexxGateway()), so read back from the fetched
            // transaction it cannot be forged by a third party. Both positions are
            // consulted because the API exposes it at the top level while the invoice
            // object carries it as well, and which one is populated differs per
            // transaction type.
            $order_id = $transaction->getReferenceId() ?? '';
            if ($order_id === '') {
                $order_id = (string) ($invoice['referenceId'] ?? '');
            }

            $gateway_id = (string) ($invoice['paymentRequestId'] ?? '');

            if (empty($order_id)) {
                $this->send_response('Webhook data incomplete');
            }

            if (!empty($this->prefix) && strpos($order_id, $this->prefix) === false) {
                $this->send_response('Prefix mismatch');
            }

            $arr = explode('_', $order_id);
            $order_id = end($arr);

            // Check if subscription to handle accordingly.
            // The WooCommerce Subscriptions helpers are guarded the same way as in
            // SubscriptionBase::register_hooks() - without the plugin they raise an
            // \Error, which is not an \Exception and would escape as a bare HTTP 500.
            $subscriptions = [];
            $preAuthId = null;
            if (!empty($resp['transaction']['preAuthorizationId'])
                && function_exists('wcs_get_subscriptions_for_order')
                && class_exists('WC_Subscription')
            ) {
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
                $order_id = $firstSubscription->get_last_order( 'ids', 'any' );

                // The request's preAuthorizationId must not be persisted: Response\Transaction
                // has no such field, so it cannot be corroborated against Payrexx, and
                // OrderService stores it as 'payrexx_auth_transaction_id' - the id that
                // SubscriptionBase::scheduled_subscription_payment() later hands to
                // PayrexxApiService::chargeTransaction(), which charges it with our own API
                // key. The authorized transaction we just fetched is that pre-authorization,
                // so its own id is the only verifiable token.
                $preAuthId = $transaction->getId();
            }

            // wc_get_order() is the HPOS-compatible factory - the plugin declares
            // custom_order_tables compatibility in woo-payrexx-gateway.php. new \WC_Order()
            // throws on an unloadable id instead of returning false, which made the
            // !$order guard below dead code and turned legitimate webhooks into HTTP 500.
            $order = wc_get_order($order_id);

            if (!$order_id || !$order) {
                throw new Exception('Fraudulent request');
            }

            $orderTotal = round(floatval($order->get_total('edit')), 2);
            $newTransactionStatus = $transaction->getStatus();

            // A confirmed transaction can also be a partial payment (with bank transfer).
            // Therefore the new correct status must be determined.
            if (in_array($newTransactionStatus, [Transaction::CONFIRMED, Transaction::REFUNDED, Transaction::PARTIALLY_REFUNDED])) {
                if ($gateway_id !== '') {
                    $gateway = $this->payrexx_api_service->getPayrexxGateway($gateway_id);
                    $confirmedAmount = StatusUtil::getAmountByStatusAndGateway($gateway, [Transaction::CONFIRMED]);
                    $refundedAmount = StatusUtil::getAmountByStatusAndGateway($gateway, [Transaction::PARTIALLY_REFUNDED, Transaction::REFUNDED]);

                    $newTransactionStatus = StatusUtil::determineNewOrderStatus($orderTotal, $confirmedAmount, $refundedAmount);
                } elseif ($newTransactionStatus === Transaction::CONFIRMED) {
                    // A transaction charged directly against a pre-authorization carries no
                    // gateway, so getPayrexxGateway('') would throw and the renewal would never
                    // settle. The reconciliation must not be skipped either - that was the
                    // second half of the bypass - so the transaction's own amount is used.
                    // getAmount() is in cents, mirroring createPayrexxGateway()'s setAmount().
                    $transactionAmount = round(($transaction->getAmount()) / 100, 2);

                    if (abs($transactionAmount - $orderTotal) >= 0.005) {
                        $newTransactionStatus = Transaction::WAITING;
                    }
                }
            }

            // Marking a Payrexx Pay bank transfer invoice as paid in the merchant backend cancels the open
            // waiting transaction, which would otherwise cancel an already paid order. Treat it as confirmed.
            $payment = $transaction->getPayment();
            if ($newTransactionStatus === Transaction::CANCELLED
                && is_array($payment)
                && ($payment['brand'] ?? '') === self::BRAND_BANK_TRANSFER
                && $transaction->getPsp() === self::PSP_NATIVE
                && in_array(($payment['invoicePaymentStatus'] ?? null), ['paid', 'overpaid'], true)
            ) {
                $newTransactionStatus = Transaction::CONFIRMED;
            }

            $transactionUuid = $transaction->getUuid();
            $this->order_service->handleTransactionStatus($order, $subscriptions, $newTransactionStatus, $transactionUuid, $preAuthId);
            $this->send_response('Success: Processed webhook response');

        } catch (Throwable $e) {
            // \Throwable, not \Exception: the SDK models declare typed properties, so an
            // API response missing uuid or id raises an \Error rather than an \Exception.
            throw new Exception('Error: ' . $e->getMessage());
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
