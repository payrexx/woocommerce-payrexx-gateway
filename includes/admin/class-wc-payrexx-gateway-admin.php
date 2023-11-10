<?php

class WC_Payrexx_Gateway_Admin
{
    /**
     * @var
     */
    private $label;

    /**
     * The single instance of the class.
     *
     * @var WC_Payrexx_Gateway_Admin
     */
    protected static $_instance = null;

    /**
     * Main WooCommerce Payrexx Admin Instance.
     *
     * @return WC_Payrexx_Gateway_Admin - Main instance.
     */
    public static function instance()
    {
        if (null === self::$_instance) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * WC Payrexx Admin Constructor.
     */
    public function __construct()
    {
        $this->label = __('Payrexx', 'wc-payrexx-gateway');

        $this->register_hooks();
    }

    /**
     * @return void
     */
    public function migrate_data()
    {
        if (get_option(PAYREXX_CONFIGS_PREFIX . 'instance') && get_option(PAYREXX_CONFIGS_PREFIX . 'api_key')) {
            return;
        }

        $settings = $this->get_settings();

        $paymentMethod = WC()->payment_gateways->payment_gateways()['payrexx'];

        $data[PAYREXX_CONFIGS_PREFIX . 'platform'] = $paymentMethod->get_option('platform');
        $data[PAYREXX_CONFIGS_PREFIX . 'instance'] = $paymentMethod->get_option('instance');
        $data[PAYREXX_CONFIGS_PREFIX . 'api_key'] = $paymentMethod->get_option('apiKey');
        $data[PAYREXX_CONFIGS_PREFIX . 'prefix'] = $paymentMethod->get_option('prefix');
        $data[PAYREXX_CONFIGS_PREFIX . 'look_and_feel_id'] = $paymentMethod->get_option('lookAndFeel');

        \WC_Admin_Settings::save_fields($settings, $data);
    }

    /**
     * @return void
     */
    private function register_hooks()
    {
        add_filter(
            'plugin_action_links_' . PAYREXX_MAIN_NAME,
            [
                $this,
                'plugin_action_links',
            ]
        );

        add_filter(
            'woocommerce_settings_tabs_array',
            [
                $this,
                'add_settings_tab',
            ],
            21
        );

        add_action(
            'woocommerce_settings_' . PAYREXX_ADMIN_SETTINGS_ID,
            [
                $this,
                'settings_content',
            ]
        );

        add_action(
            'woocommerce_update_options_' . PAYREXX_ADMIN_SETTINGS_ID,
            [
                $this,
                'settings_save',
            ]
        );


    }

    /**
     * Add Settings Tab
     *
     * @param mixed $settings_tabs settings_tabs.
     * @return mixed $settings_tabs
     */
    public function add_settings_tab($settings_tabs)
    {
        $settings_tabs[PAYREXX_ADMIN_SETTINGS_ID] = $this->label;
        return $settings_tabs;
    }

    /**
     *
     * @return void
     */
    public function settings_content()
    {
        woocommerce_admin_fields($this->get_settings());
    }

    /**
     *
     * @return void
     */
    public function settings_save()
    {
        $settings = $this->get_settings();

        woocommerce_update_options($settings);
    }

    /**
     * Show action links on the plugin screen
     *
     * @param mixed $links Plugin Action links.
     * @return array
     */
    public function plugin_action_links($links)
    {
        $action_links = [
            'settings' => '<a href="' . admin_url('admin.php?page=wc-settings&tab=' . PAYREXX_ADMIN_SETTINGS_ID) . '">' . __('Settings', 'wc-payrexx-gateway') . '</a>',
        ];

        return array_merge($action_links, $links);
    }

    /**
     * @return mixed
     */
    private function get_settings()
    {
        return include(PAYREXX_PLUGIN_DIR . '/includes/settings/payrexx_settings.php');
    }
}
