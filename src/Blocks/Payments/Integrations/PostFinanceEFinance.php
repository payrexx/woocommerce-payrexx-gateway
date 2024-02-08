<?php

class WC_Payrexx_Gateway_PostFinanceEFinance extends WC_Payrexx_Gateway_SubscriptionBase
{

    public function __construct()
    {
        $this->id = PAYREXX_PM_PREFIX . 'post-finance-e-finance';
        $this->method_title = __('PostFinance E-Finance (Payrexx)', 'wc-payrexx-gateway');

        parent::__construct();
    }
}

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class WC_Payrexx_Gateway_PostFinanceEFinance_Block extends AbstractPaymentMethodType
{
    /**
     * Payment method name
     *
     * @var string
     */
    protected $name = PAYREXX_PM_PREFIX . 'post-finance-e-finance';

    /**
     * Initializes the payment method type.
     */
    public function initialize()
    {
        $this->settings = get_option('woocommerce_payrexx_post-finance-e-finance_settings', []);
    }

    /**
     * Returns if this payment method should be active. If false, the scripts will not be enqueued.
     *
     * @return boolean
     */
    public function is_active()
    {
        return filter_var($this->get_setting('enabled', false), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Returns an array of scripts/handles to be registered for this payment method.
     *
     * @return array
     */
    public function get_payment_method_script_handles()
    {
        wp_register_script(
            'payrexx-post-finance-e-finance-blocks-integration',
            plugins_url('assets/client/blocks/post-finance-e-finance.js', PAYREXX_MAIN_FILE),
            [
                'wc-blocks-registry',
                'wc-settings',
                'wp-element',
                'wp-html-entities',
                'wp-i18n',
            ],
            null,
            true
        );
        if (function_exists('wp_set_script_translations')) {
            wp_set_script_translations('payrexx-post-finance-e-finance-blocks-integration');
        }
        return ['payrexx-post-finance-e-finance-blocks-integration'];
    }

    /**
     * Returns an array of key=>value pairs of data made available to the payment methods script.
     *
     * @return array
     */
    public function get_payment_method_data()
    {
        return [
            'title' => $this->get_setting('title'),
            'description' => $this->get_setting('description'),
            'supports' => $this->get_supported_features(),
        ];
    }
}
