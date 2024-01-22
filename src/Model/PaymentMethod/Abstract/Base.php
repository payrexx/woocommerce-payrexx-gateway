<?php

use PayrexxPaymentGateway\Helper\PaymentHelper;
use PayrexxPaymentGateway\Helper\SubscriptionHelper;
use \PayrexxPaymentGateway\Service\PayrexxApiService;
use PayrexxPaymentGateway\Service\OrderService;

abstract class WC_Payrexx_Gateway_Base extends WC_Payment_Gateway
{
	/**
	 * @var PayrexxApiService
	 */
	protected $payrexxApiService;

	/**
	 * @var string
	 */
	protected $pm;

	public function __construct()
	{
		$this->init_form_fields();
		$this->init_settings();
		$this->register_hooks();

		$pm = str_replace(PAYREXX_PM_PREFIX, '', $this->id);
		$this->pm = ($pm == 'payrexx' ? '' : $pm);
		$this->method_description = '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=' . PAYREXX_ADMIN_SETTINGS_ID ) . '">' . __('General Payrexx Settings', 'wc-payrexx-gateway') . '</a>';

		if ($this->pm) {
			$this->icon = WC_HTTPS::force_https_url(plugins_url('/includes/cardicons/card_' . $this->pm . '.svg', PAYREXX_MAIN_FILE));
		}

		$this->payrexxApiService = WC_Payrexx_Gateway::getPayrexxApiService();
	}

	/**
	 * Initialize Gateway Settings Form Fields
	 *
	 * @return void
	 */
	public function init_form_fields()
	{
		$this->form_fields = include(PAYREXX_PLUGIN_DIR . '/includes/settings/payrexx_pm_settings.php');
	}

	/**
	 * @return void
	 */
	public function init_settings() {
		parent::init_settings();

		$support_refunds = array(
			'refunds',
		);
		$this->supports = array_merge( $this->supports, $support_refunds );
		$this->enabled = $this->get_option('enabled');
		$this->title = $this->get_option('title');
		$this->description = $this->get_option('description');
	}

	/**
	 * @return void
	 */
	public function register_hooks()
	{
		add_action(
			'woocommerce_update_options_payment_gateways_' . $this->id,
			[
				$this,
				'process_admin_options'
			]
		);
	}

	/**
	 * @param $orderId
	 * @return array
	 */
	public function process_payment($orderId)
	{
		$cart = WC()->cart;
		$order = new WC_Order($orderId);

		if (!$totalAmount = floatval($order->get_total('edit'))) {
			$order->payment_complete();
			$cart->empty_cart();
			return [
				'result' => 'success',
				'redirect' => $this->get_return_url($order)
			];
		}

		$reference = (get_option(PAYREXX_CONFIGS_PREFIX . 'prefix') ? get_option(PAYREXX_CONFIGS_PREFIX . 'prefix') . '_' :  '') . $orderId;

		$successRedirectUrl = $this->get_return_url($order);
		$cancelRedirectUrl = PaymentHelper::getCancelUrl($order);

		$gateway = $this->payrexxApiService->createPayrexxGateway($order, $cart, $totalAmount, $this->pm, $reference, $successRedirectUrl, $cancelRedirectUrl, false, false);
		if ( ! $gateway ) {
			return array(
				'result' => 'failure',
			);
		}
		return $this->process_redirect($gateway, $order);
	}

	/**
	 * @param $gateway
	 * @param $order
	 * @return array
	 */
	public function process_redirect($gateway, $order) {
		$order->update_meta_data('payrexx_gateway_id', $gateway->getId());
		$order->save();

		$language = substr(get_locale(), 0, 2);
		!in_array($language, LANG) ? $language = LANG[0] : null;
		$redirect = str_replace('?', $language . '/?', $gateway->getLink());

		// Return redirect
		return [
			'result' => 'success',
			'redirect' => $redirect
		];
	}

	/**
	 * @param $order
	 * @return void
	 */
	protected function getCancelUrl($order) {
	}

	/**
	 * Get payment icons
	 *
	 * @return string
	 */
	public function get_icon() {
		if ( empty( $this->pm ) ) {
			$subscription_logos = $this->get_option( 'subscription_logos' ) ?? array();
			$logos              = $this->get_option( 'logos' ) ?? array();
			if ( empty( $logos ) && empty( $subscription_logos ) ) {
				return '';
			}
			// Check if cart contains subscriptions.
			$logos = SubscriptionHelper::isSubscription( WC()->cart ) ? $subscription_logos : $logos;
			$icon  = '';
			foreach ( $logos as $logo ) {
				$src   = WC_HTTPS::force_https_url( plugins_url( 'includes/cardicons/card_' . $logo . '.svg', PAYREXX_MAIN_FILE ) );
				$icon .= '<img src="' . $src . '" alt="' . $logo . '" id="' . $logo . '"/>';
			}
		} else {
			$src  = WC_HTTPS::force_https_url( plugins_url( '/includes/cardicons/card_' . $this->pm . '.svg', PAYREXX_MAIN_FILE ) );
			$icon = '<img src="' . $src . '" alt="' . $this->pm . '" id="' . $this->pm . '"/>';
		}
		// Add a wrapper around the images to allow styling.
		return apply_filters( 'woocommerce_gateway_icon', '<span class="icon-wrapper">' . $icon . '</span>', $this->id );
	}

	/**
	 * Processing Refund
	 *
	 * @param int $order_id
	 * @param int $amount
	 * @param string $reason
	 * @return bool
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ): bool
	{
		$order = new WC_Order( $order_id );
		$gatewayId = intval( $order->get_meta( 'payrexx_gateway_id', true ) );
		$transaction_uuid = $order->get_transaction_id();
    	$refund = $this->payrexxApiService->refund_transaction( $gatewayId, $transaction_uuid, $amount );
		$message_status = OrderService::WC_STATUS_PARTIALLY_REFUNDED;
		if ( ( $amount * 100 ) ==  (int) ( $order->get_total( 'edit' ) * 100 ) ) {
			$message_status = OrderService::WC_STATUS_REFUNDED;
		}
		if ( $refund ) {
			$order->update_status(
				OrderService::WC_STATUS_REFUNDED,
				OrderService::STATUS_MESSAGES[$message_status] . ' transaction id: ' . $refund->getUuid(),
				'wc-payrexx-gateway'
			);
			$order->add_order_note( $reason );
			return true;
		}
		return false;
	}
}
