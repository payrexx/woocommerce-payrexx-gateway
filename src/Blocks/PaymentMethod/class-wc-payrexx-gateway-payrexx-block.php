<?php
/**
 * Payrexx payment gateway for Woocommerce
 */

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * WC_Payrexx_Gateway_Payrexx_Block
 */
class WC_Payrexx_Gateway_Payrexx_Block extends WC_Payrexx_Gateway_Block_Base {
	/**
	 * Payment method name
	 *
	 * @var string
	 */
	protected $name = 'payrexx';
}
