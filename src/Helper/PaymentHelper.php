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

		// Subscription orders: 'failed' keeps the WCS subscription on-hold instead of cancelling it permanently.
		$order_status = $orderService->orderContainsSubscription( $order )
			? OrderService::WC_STATUS_FAILED
			: OrderService::WC_STATUS_CANCELLED;
		// The cancel url is a plain GET whose hash never changes, so it can be replayed at
		// any time. transition_allowed() refuses for paid or already cancelled orders -
		// their transactions are none of this request's business, so the Payrexx side is
		// only cleaned up when this request really does cancel the order.
		if ( $orderService->transition_allowed( $order_status, $order ) ) {
			$orderService->transitionOrder( $order, $order_status );

			$payrexxApiService = WC_Payrexx_Gateway::getPayrexxApiService();

			// Cancel first: a deleted gateway no longer exposes its waiting transactions.
			$gatewayId = intval( $order->get_meta( 'payrexx_gateway_id', true ) );
			if ( ! $payrexxApiService->cancelWaitingTransactions( $gatewayId ) ) {
				// Silence here would recreate the exact bug this guards against, with
				// nothing left to diagnose it from.
				$order->add_order_note(
					__(
						'Payrexx: an open transaction of this order could not be cancelled automatically. Please check the payment in the Payrexx backend.',
						'woo-payrexx-gateway'
					)
				);
			}
			$payrexxApiService->deleteGatewayById( $gatewayId );
		}

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
