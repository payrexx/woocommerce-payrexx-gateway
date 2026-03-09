<?php

namespace PayrexxPaymentGateway\Helper;

use WC_Payrexx_Gateway;
use PayrexxPaymentGateway\Service\OrderService;
use WC_Order;

class PaymentHelper
{
	public static function handleError(): void {
		if (!isset($_GET['order_id']) || !isset($_GET['order_hash'])) {
			return;
		}
		$order_id = $_GET['order_id'];
		$requestHash = $_GET['order_hash'];

		$order = new WC_Order( $order_id );

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
		$gatewayId = intval( $order->get_meta( 'payrexx_gateway_id', true ) );
		$payrexxApiService->deleteGatewayById( $gatewayId );

		if (get_option( PAYREXX_CONFIGS_PREFIX . 'new_checkout_after_cancel' ) === 'yes') {
			header( "Location:" . wc_get_checkout_url() );
			exit();
		}
	}

	public static function getCancelUrl( WC_Order $order ): string  {
		return add_query_arg([
			'payrexx_error' => '1',
			'order_id'      => $order->get_id(),
			'order_hash'    => self::getOrderTimeHash( $order )
		], $order->get_checkout_payment_url() );
	}

	private static function getOrderTimeHash( WC_Order $order ): string {
		return hash( 'sha256', AUTH_SALT . $order->get_date_created()->__toString() );
	}
}
