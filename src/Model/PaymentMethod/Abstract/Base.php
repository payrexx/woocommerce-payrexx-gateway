<?php

use PayrexxPaymentGateway\Helper\PaymentHelper;
use PayrexxPaymentGateway\Helper\SubscriptionHelper;
use \PayrexxPaymentGateway\Service\PayrexxApiService;

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
		$this->method_description = '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=' . PAYREXX_ADMIN_SETTINGS_ID ) . '">' . __('General Payrexx Settings', 'woo-payrexx-gateway') . '</a>';

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

		$this->supports = array_merge( $this->supports, ['refunds'] );
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
	 * @param int $order_id
	 * @return array
	 */
	public function process_payment( $order_id ): array {
		$cart  = WC()->cart;
		$order = new WC_Order( $order_id );

		if (!$totalAmount = floatval($order->get_total('edit'))) {
			$order->payment_complete();
			$cart->empty_cart();
			return [
				'result' => 'success',
				'redirect' => $this->get_return_url($order)
			];
		}

		$prefix                       = get_option( PAYREXX_CONFIGS_PREFIX . 'prefix' );
		$data['reference']            = $prefix ? $prefix . '_' . $order_id : $order_id;
		$data['success_redirect_url'] = $this->get_return_url( $order );
		$data['cancel_redirect_url']  = PaymentHelper::getCancelUrl( $order );
		$data['language']             = $this->get_gateway_lang();

		$gateway = $this->payrexxApiService->createPayrexxGateway( $order, $cart, $totalAmount, $this->pm, $data, false, false );
		if ( ! $gateway ) {
			return array(
				'result' => 'failure',
			);
		}
		return $this->process_redirect( $gateway, $order );
	}

	public function process_redirect( $gateway, $order ): array {
		$order->update_meta_data( 'payrexx_gateway_id', $gateway->getId() );
		$order->save();

		// Return redirect
		return array(
			'result' => 'success',
			'redirect' => $gateway->getLink(),
		);
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
			$icon = '<img src="' . $src . '" alt="' . $this->pm . '" id="' . $this->id . '"/>';
		}
		// Add a wrapper around the images to allow styling.
		return apply_filters( 'woocommerce_gateway_icon', '<span class="icon-wrapper">' . $icon . '</span>', $this->id );
	}

	/**
	 * Processing Refund
	 *
	 * @param int    $order_id order id.
	 * @param int    $amount   refund amount.
	 * @param string $reason   refund reason.
	 * @return bool
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ): bool
	{
		$order            = new WC_Order( $order_id );
		$gateway_id       = intval( $order->get_meta( 'payrexx_gateway_id', true ) );
		$transaction_uuid = $order->get_transaction_id();
		return $this->payrexxApiService->refund_transaction(
			$gateway_id,
			$transaction_uuid,
			$amount
		);
	}

	public function get_gateway_lang(): string {
		if ( isset( $_COOKIE['pll_language'] ) ) {
			$language = sanitize_text_field( $_COOKIE['pll_language'] );
		}

		$language = substr( $language ?? get_locale(), 0, 2 );
		return in_array( $language, LANG, true ) ? $language : LANG[0];
	}
}
