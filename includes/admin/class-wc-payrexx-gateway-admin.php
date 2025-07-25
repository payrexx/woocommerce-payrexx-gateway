<?php

class WC_Payrexx_Gateway_Admin {
    /**
     * @var string Settings label
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
    public static function instance() {
        if ( null === self::$_instance ) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    /**
     * WC Payrexx Admin Constructor.
     */
    public function __construct( $plugin_file ) {
        $this->label       = __( 'Payrexx', 'woo-payrexx-gateway' );
        $this->plugin_file = $plugin_file;

        $this->register_hooks();
    }

    /**
     * @return void
     */
    public function migrate_data() {
        if ( get_option( PAYREXX_CONFIGS_PREFIX . 'instance' ) && get_option( PAYREXX_CONFIGS_PREFIX . 'api_key' ) ) {
            return;
        }

        $settings = $this->get_settings();

        $payment_method = WC()->payment_gateways->payment_gateways()['payrexx'];

        $data[ PAYREXX_CONFIGS_PREFIX . 'platform' ]         = $payment_method->get_option( 'platform' );
        $data[ PAYREXX_CONFIGS_PREFIX . 'instance' ]         = $payment_method->get_option( 'instance' );
        $data[ PAYREXX_CONFIGS_PREFIX . 'api_key' ]          = $payment_method->get_option( 'apiKey' );
        $data[ PAYREXX_CONFIGS_PREFIX . 'prefix' ]           = $payment_method->get_option( 'prefix' );
        $data[ PAYREXX_CONFIGS_PREFIX . 'look_and_feel_id' ] = $payment_method->get_option( 'lookAndFeel' );

        \WC_Admin_Settings::save_fields( $settings, $data );
    }

    /**
     * @return void
     */
    private function register_hooks() {
        add_filter(
                'plugin_action_links_' . PAYREXX_MAIN_NAME,
                array( $this, 'plugin_action_links' )
        );

        add_filter(
                'woocommerce_settings_tabs_array',
                array( $this, 'add_settings_tab' ),
                21
        );

        add_action(
                'woocommerce_settings_' . PAYREXX_ADMIN_SETTINGS_ID,
                array( $this, 'settings_content' )
        );

        add_action(
                'woocommerce_update_options_' . PAYREXX_ADMIN_SETTINGS_ID,
                array( $this, 'settings_save' )
        );

        add_action(
                'woocommerce_admin_field_verify_button',
                array( $this, 'render_verify_button' )
        );

        add_action(
                'admin_enqueue_scripts',
                array( $this, 'enqueue_admin_settings_script' )
        );

        add_action(
                'wp_ajax_payrexx_validate_api',
                array( $this, 'payrexx_validate_api' )
        );

        add_action(
                'woocommerce_admin_field_connect_payrexx_button',
                array( $this, 'render_connect_payrexx_button' )
        );

        add_action(
                'wp_ajax_payrexx_store_connect_settings',
                array( $this, 'payrexx_store_connect_settings' )
        );
    }

    /**
     *
     * @param string $hook
     */
    function enqueue_admin_settings_script( $hook ) {
        if ( $hook !== 'woocommerce_page_wc-settings' ) {
            return;
        }

        wp_enqueue_script(
                'wc-payrexx-gateway-admin-connect-button',
                plugins_url( 'assets/js/connectPayrexx.js', $this->plugin_file ),
                array( 'jquery' ),
                '1.0',
                true
        );

        wp_enqueue_script(
                'wc-payrexx-gateway-admin-verify-button',
                plugins_url( 'assets/js/settingsValidation.js', $this->plugin_file ),
                array( 'jquery' ),
                '1.0',
                true
        );

        wp_localize_script( 'wc-payrexx-gateway-admin-verify-button', 'payrexxAjax',
                array(
                        'ajax_url' => admin_url( 'admin-ajax.php' ),
                        'nonce'    => wp_create_nonce( 'wc_payrexx_gateway_verify_nonce' ),
                )
        );
    }

    /**
     * Add Settings Tab
     *
     * @param mixed $settings_tabs settings_tabs.
     *
     * @return mixed $settings_tabs
     */
    public function add_settings_tab( $settings_tabs ) {
        $settings_tabs[ PAYREXX_ADMIN_SETTINGS_ID ] = $this->label;

        return $settings_tabs;
    }

    /**
     *
     * @return void
     */
    public function settings_content() {
        woocommerce_admin_fields( $this->get_settings() );
    }

    /**
     *
     * @return void
     */
    public function settings_save() {
        $settings = $this->get_settings();

        woocommerce_update_options( $settings );
    }

    /**
     * Show action links on the plugin screen
     *
     * @param mixed $links Plugin Action links.
     *
     * @return array
     */
    public function plugin_action_links( $links ) {
        $action_links = array(
                'settings' => '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=' . PAYREXX_ADMIN_SETTINGS_ID ) . '">' . __( 'Settings', 'woo-payrexx-gateway' ) . '</a>',
        );

        return array_merge( $action_links, $links );
    }

    /**
     * @return mixed
     */
    private function get_settings() {
        return include PAYREXX_PLUGIN_DIR . '/includes/settings/payrexx_settings.php';
    }

    /**
     * Content of custom settings field
     *
     * @param array $value contains settings field properties
     */
    public function render_verify_button( $value ) {
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc"><?php echo esc_html( $value['title'] ); ?></th>
            <td class="forminp">
                <button id="validateApiCredentials"
                        class="button button-secondary"><?php echo esc_html( $value['button_label'] ); ?></button>
                <span id="validationSpinner" class="spinner"
                      style="display: none; float: none; margin: 3px 10px; vertical-align: middle;"></span>
                <span id="validationResult" style="margin-left:10px; line-height: 2.15384615"></span>
            </td>
        </tr>
        <?php
    }

    /**
     * Ajax request handle to validate API Credentials
     *
     */
    public function payrexx_validate_api() {
        $instance = isset( $_POST['instance'] ) ? sanitize_text_field( $_POST['instance'] ) : '';
        $api_key  = isset( $_POST['api_key'] ) ? sanitize_text_field( $_POST['api_key'] ) : '';
        $platform = isset( $_POST['platform'] ) ? sanitize_text_field( $_POST['platform'] ) : '';

        $payrexx_api_service = WC_Payrexx_Gateway::getPayrexxApiService();

        if ( $payrexx_api_service->validate_api_credentials( $instance, $api_key, $platform ) ) {
            wp_send_json_success( array( 'message' => __( 'Signature validated successfully. Your credentials are correct.', 'woo-payrexx-gateway' ) ) );
        }

        wp_send_json_error( array( 'message' => __( 'Signature validation failed. Please check your credentials.', 'woo-payrexx-gateway' ) ) );
    }

    /**
     * Render connect button
     *
     * @param array $value contains settings field properties.
     */
    public function render_connect_payrexx_button( $value ) {
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label>
                    <?php echo esc_html( $value['title'] ); ?>
                    <span class="woocommerce-help-tip" data-tip="<?php echo esc_attr( $value['tooltip'] ); ?>"></span>
                </label>
            </th>
            <td class="forminp">
                <button id="payrexx-connect-button"
                        type="button"
                        class="button button-secondary"><?php echo esc_html( $value['button_label'] ); ?></button>
                <span id="connectSpinner" class="spinner"
                      style="display: none; float: none; margin: 3px 10px; vertical-align: middle;"></span>
                <span id="connectResult" style="margin-left:10px; line-height: 2.15384615"></span>
                <p class="description"><?php echo esc_html( $value['desc'] ); ?></p>
            </td>
        </tr>
        <?php
    }

    /**
     * Save payrexx credentials received in frontend.
     *
     * @return void
     */
    public function payrexx_store_connect_settings() {
        $instance         = isset( $_POST['instance'] ) ? sanitize_text_field( $_POST['instance'] ) : '';
        $api_key          = isset( $_POST['api_key'] ) ? sanitize_text_field( $_POST['api_key'] ) : '';
        $success_instance = update_option( PAYREXX_CONFIGS_PREFIX . 'instance', $instance );
        $success_key      = update_option( PAYREXX_CONFIGS_PREFIX . 'api_key', $api_key );

        if ( $success_instance && $success_key ) {
            wp_send_json_success( array( 'message' => __( 'Integration successfully created and credentials imported.', 'woo-payrexx-gateway' ) ) );
        }

        wp_send_json_error( array( 'message' => __( 'Integration created but error while saving the credentials. Please open the payrexx backend and copy the credentials manually.', 'woo-payrexx-gateway' ) ) );
    }
}
