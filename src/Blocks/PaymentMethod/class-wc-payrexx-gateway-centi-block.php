<?php
/**
 * Woocommerce payrexx payment gateway
 */

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * WC_Payrexx_Gateway_Centi_Block
 */
class WC_Payrexx_Gateway_Centi_Block extends WC_Payrexx_Gateway_Block_Base {
	/**
	 * Payment method name
	 *
	 * @var string
	 */
	protected $name = PAYREXX_PM_PREFIX . 'centi';
}