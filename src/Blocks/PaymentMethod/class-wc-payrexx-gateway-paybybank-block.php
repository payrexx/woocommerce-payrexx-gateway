<?php
/**
 * Payrexx payment gateway for Woocommerce
 */

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * WC_Payrexx_Gateway_PayByBank_Block
 */
class WC_Payrexx_Gateway_PayByBank_Block extends WC_Payrexx_Gateway_Block_Base {
	/**
	 * Payment method name
	 *
	 * @var string
	 */
	protected $name = PAYREXX_PM_PREFIX . 'pay-by-bank';
}
