<?php
/**
 * Woocommerce payrexx payment gateway
 */

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use PayrexxPaymentGateway\Helper\SubscriptionHelper;

/**
 * WC_Payrexx_Block_Base
 */
class WC_Payrexx_Gateway_Block_Base extends AbstractPaymentMethodType {

	/**
	 * Initializes the payment method type.
	 */
	public function initialize() {
		$this->settings = get_option( 'woocommerce_' . $this->name . '_settings', [] );
	}

	/**
	 * Returns if this payment method should be active. If false, the scripts will not be enqueued.
	 *
	 * @return boolean
	 */
	public function is_active() {
		return filter_var( $this->get_setting( 'enabled', false ), FILTER_VALIDATE_BOOLEAN );
	}

	/**
	 * Returns an array of scripts/handles to be registered for this payment method.
	 *
	 * @return array
	 */
	public function get_payment_method_script_handles() {
		$pm     = str_replace( PAYREXX_PM_PREFIX, '', $this->name );
		$handle = 'payrexx-blocks-' . $pm . '-integration';
		$deps   = [
			'wc-blocks-registry',
			'wc-settings',
			'wp-element',
			'wp-html-entities',
			'wp-i18n',
		];
		wp_register_script(
			$handle,
			plugins_url( 'assets/blocks/' . $pm . '.js', PAYREXX_MAIN_FILE ),
			$deps,
			true,
			true
		);
		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( $handle );
		}
		return [ $handle ];
	}

	/**
	 * Returns an array of key=>value pairs of data made available to the payment methods script.
	 *
	 * @return array
	 */
	public function get_payment_method_data() {
		$description = wp_kses_post( $this->settings['description'] );
		if ( 'yes' === $this->settings['subscriptions_enabled'] ) {
			$supports = array_merge(
				$this->get_supported_features(),
				SubscriptionHelper::get_supported_features()
			);

			// Check if the cart contains a subscription
			if ( SubscriptionHelper::isSubscription( WC()->cart ) ) {
				$name = esc_attr( 'payrexx-allow-recurring-' . $this->name );
				$label_text = wp_kses_post( $this->settings['subscriptions_user_desc'] );

				$description .= sprintf(
					'<br/>
					<div class="wc-block-components-checkbox">
						<label for="%s">
							<input type="checkbox" class="wc-block-components-checkbox__input" checked name="%s" id="%s" value="1" />
							%s
						</label>
					</div>',
					$name, $name, $name, $label_text
				);
			}
		}

		return [
			'title'       => $this->get_setting( 'title' ),
			'description' => $description,
			'supports'    => $supports,
		];
	}
}
