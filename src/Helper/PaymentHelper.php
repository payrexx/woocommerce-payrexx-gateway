<?php

namespace PayrexxPaymentGateway\Helper;

use WC_Payrexx_Gateway;
use PayrexxPaymentGateway\Service\OrderService;

class PaymentHelper
{
    /**
     * @return void
     */
    public static function handleError() {
        if (!isset($_GET['order_id']) || !isset($_GET['order_hash'])) {
            return;
        }
        $order_id = $_GET['order_id'];
        $requestHash = $_GET['order_hash'];

        $order = new \WC_Order($order_id);

        // Check if request valid
        if (self::getOrderTimeHash($order) !== $requestHash) {
            return;
        }

        $orderService = WC_Payrexx_Gateway::getOrderService();

        // Set order status to cancelled
        if ( $orderService->transition_allowed( OrderService::WC_STATUS_CANCELLED, $order ) ) {
            $orderService->transitionOrder( $order, OrderService::WC_STATUS_CANCELLED );
        }

        $payrexxApiService = WC_Payrexx_Gateway::getPayrexxApiService();

        // Delete old Gateway using order metadata
        $gatewayId = intval($order->get_meta('payrexx_gateway_id', true));
        $payrexxApiService->deleteGatewayById($gatewayId);
    }

    /**
     * @param $order
     * @return string
     */
    public static function getCancelUrl($order) {
        $checkoutUrl = wc_get_checkout_url();

        $getParam = strpos($checkoutUrl, '?');
        return $checkoutUrl . ($getParam ? '&' : '?') . 'payrexx_error=1&order_hash=' . self::getOrderTimeHash($order) .'&order_id=' . $order->get_id();
    }

    /**
     * @param $order
     * @return string
     */
    private static function getOrderTimeHash($order) {
        return hash('sha256', AUTH_SALT . $order->get_date_created()->__toString());
    }
}