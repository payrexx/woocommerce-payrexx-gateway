<?php

namespace PayrexxPaymentGateway\Helper;

class SubscriptionHelper
{
	/**
	 * @param $cart
	 * @return bool
	 */
	public static function isSubscription($cart):bool {
		if (empty($cart->cart_contents)) return false;
		if (self::isPaymentMethodChange()) return true;

		// Check if cart contains subscriptions
		foreach ($cart->cart_contents as $cart_item) {
			if (class_exists('WC_Subscriptions_Product') && \WC_Subscriptions_Product::is_subscription($cart_item['data'])) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @return bool
	 */
	public static function isPaymentMethodChange():bool	{
		$changePaymentMethod = !empty($_GET['change_payment_method']) ? $_GET['change_payment_method'] : null;
		if (!$changePaymentMethod) return false;
		return true;
	}

	/**
	 * Subscription supported features
	 *
	 * @return array
	 */
	public static function get_supported_features(): array {
		return [
			'subscriptions',
			'subscription_cancellation',
			'subscription_suspension',
			'subscription_reactivation',
			'subscription_amount_changes',
			'subscription_date_changes',
			'multiple_subscriptions',
			'subscription_payment_method_change',
			'subscription_payment_method_change_customer',
		];
	}
}
