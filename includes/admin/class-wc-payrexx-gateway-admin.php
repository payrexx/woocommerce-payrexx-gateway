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

    protected $plugin_file;

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
    public function __construct($plugin_file)
    {
        $this->label = __('Payrexx', 'wc-payrexx-gateway');
        $this->plugin_file = $plugin_file;

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

        add_action(
            'woocommerce_admin_field_verify_button',
            [
                $this,
                'render_verify_button'
            ]
        );

        add_action(
                'admin_enqueue_scripts',
                [
                        $this, 'enqueue_admin_settings_script'
                ]
        );

        add_action(
                'wp_ajax_payrexx_validate_api',
                [
                        $this, 'payrexx_validate_api'
                ]
        );
    }

    /**
     *
     * @param string $hook
     */
    function enqueue_admin_settings_script($hook)
    {
        if ($hook !== 'woocommerce_page_wc-settings') return;

        wp_enqueue_script(
            'wc-payrexx-gateway-admin',
            plugins_url('assets/js/settingsValidation.js', $this->plugin_file),
            ['jquery'],
            '1.0',
            true
        );

        wp_localize_script('wc-payrexx-gateway-admin', 'payrexxAjax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('wc_payrexx_gateway_verify_nonce'),
        ]);
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

    /**
     * Content of custom settings field
     *
     * @param array $value contains settings field properties
     */
    function render_verify_button($value) {
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc"><?php echo esc_html($value['title']); ?></th>
            <td class="forminp">
                <button id="validateApiCredentials" class="button button-secondary"><?php echo esc_html($value['button_label']); ?></button>
                <span id="validationSpinner" class="spinner" style="display: none; float: none; margin: 3px 10px; vertical-align: middle;"></span>
                <span id="validationResult" style="margin-left:10px; line-height: 2.15384615"></span>
            </td>
        </tr>
        <?php
    }

    /**
     * Ajax request handle to validate API Credentials
     *
     */
    function payrexx_validate_api()
    {
        $instance = isset($_POST['instance']) ? sanitize_text_field($_POST['instance']) : '';
        $apiKey = isset($_POST['api_key']) ? sanitize_text_field($_POST['api_key']) : '';
        $platform = isset($_POST['platform']) ? sanitize_text_field($_POST['platform']) : '';

        $payrexxApiService = WC_Payrexx_Gateway::getPayrexxApiService();

        if ($payrexxApiService->validate_api_credentials($instance, $apiKey, $platform)){
            wp_send_json_success(['message' => __('Signature validated successfully. Your credentials are correct.', 'wc-payrexx-gateway')]);
        }

        wp_send_json_error(['message' => __('Signature validation failed. Pleae check your credentials.', 'wc-payrexx-gateway')]);
    }
}
