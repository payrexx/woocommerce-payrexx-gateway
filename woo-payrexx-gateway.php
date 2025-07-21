<?php
/**
 * Plugin Name: WooCommerce Payrexx Gateway
 * Description: Accept many different payment methods on your store using Payrexx
 * Author: Payrexx
 * Author URI: https://payrexx.com
 * Version: 3.1.0
 * Requires at least: 5.6
 * Tested up to: 6.8
 * Requires PHP: 8.0
 * Requires Plugins: woocommerce
 * WC requires at least: 5.0
 * WC tested up to: 10.0.1
 * Text Domain: woo-payrexx-gateway
 * Domain Path: /languages
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

// Check if woocommerce is installed
$active_plugins = get_option( 'active_plugins', [] );
if ( function_exists( 'is_multisite' ) && is_multisite() ) {
	$active_plugins = array_merge(
		$active_plugins,
		get_site_option( 'active_sitewide_plugins', [] )
	);
}

$is_woocommerce_active = in_array( 'woocommerce/woocommerce.php', $active_plugins ) ||
	array_key_exists( 'woocommerce/woocommerce.php', $active_plugins );

if ( ! $is_woocommerce_active ) {
	return;
}

use PayrexxPaymentGateway\Service\PayrexxApiService;
use PayrexxPaymentGateway\Service\OrderService;
use PayrexxPaymentGateway\Webhook\Dispatcher;
use PayrexxPaymentGateway\Helper\PaymentHelper;

if (! class_exists( 'WC_Payrexx_Gateway' ))
{
	class WC_Payrexx_Gateway
	{

		/**
		 * @var PayrexxApiService
		 */
		protected $payrexxApiService;

		/**
		 * @var OrderService
		 */
		protected $orderService;

		/**
		 * @var Dispatcher
		 */
		protected $webhookDispatcher;

		private $paymentMethodList = [];

		protected static $_instance = null;

		public static function instance() {
			if ( null === self::$_instance ) {
				self::$_instance = new self();
			}
			return self::$_instance;
		}

		public function __construct()
		{
			$this->define_constants();
			$this->include();
			$this->init();
			$this->register_hooks();
			foreach (scandir(PAYREXX_PM_DIR) as $paymentMethod) {
				if (!strpos($paymentMethod, '.php')) continue;
				$this->paymentMethodList[] = str_replace('.php', '', $paymentMethod);
			}
		}

		protected function define_constants() {
			define('PAYREXX_PLUGIN_DIR', plugin_dir_path( __FILE__ ));
			define('PAYREXX_PM_DIR', PAYREXX_PLUGIN_DIR . 'src/Model/PaymentMethod/');
			define('PAYREXX_PM_BLOCK_DIR', PAYREXX_PLUGIN_DIR . 'src/Blocks/PaymentMethod/');
			define('PAYREXX_MAIN_FILE',  __FILE__ );
			define('PAYREXX_MAIN_NAME',  plugin_basename( __FILE__ ) );

			define('PAYREXX_CONFIGS_PREFIX', 'payrexx_configs_');
			define('PAYREXX_PM_PREFIX', 'payrexx_');

			define('PAYREXX_ADMIN_SETTINGS_ID' , 'payrexx');

			define('LANG', ['en', 'de', 'it', 'fr', 'nl', 'pt', 'tr']);
		}

		protected function include() {
			require_once PAYREXX_PLUGIN_DIR . '/vendor/autoload.php';
			require_once PAYREXX_PLUGIN_DIR . '/src/Service/PayrexxApiService.php';
			require_once PAYREXX_PLUGIN_DIR . '/src/Service/OrderService.php';
			require_once PAYREXX_PLUGIN_DIR . '/src/Helper/SubscriptionHelper.php';
			require_once PAYREXX_PLUGIN_DIR . '/src/Helper/PaymentHelper.php';
			require_once PAYREXX_PLUGIN_DIR . '/src/Util/BasketUtil.php';
			require_once PAYREXX_PLUGIN_DIR . '/src/Util/StatusUtil.php';
			require_once PAYREXX_PLUGIN_DIR . '/src/Webhook/Dispatcher.php';

			if (is_admin()) {
				require_once PAYREXX_PLUGIN_DIR . '/includes/admin/class-wc-payrexx-gateway-admin.php';
			}
		}

		protected function init() {
			if (is_admin()) {
				new WC_Payrexx_Gateway_Admin(__FILE__);
			}

			$this->payrexxApiService = self::getPayrexxApiService();
			$this->orderService = self::getOrderService();
			$this->webhookDispatcher = new Dispatcher($this->payrexxApiService, $this->orderService, get_option(PAYREXX_CONFIGS_PREFIX . 'prefix'));

			load_plugin_textdomain('woo-payrexx-gateway', false, basename(dirname(PAYREXX_MAIN_FILE)) . '/languages');
		}

		protected function register_hooks() {
			add_action(
				'plugins_loaded',
				[
					$this,
					'loaded',
				]
			);

			add_action(
				'woocommerce_api_wc_payrexx_gateway',
				[
					$this->webhookDispatcher,
					'check_webhook_response'
				]
			);

			add_action(
				'wp_enqueue_scripts',
				[
					$this,
					'payment_scripts'
				]
			);
			add_action( 'before_woocommerce_init', function() {
				if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
					\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
					\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_checkout_blocks', __FILE__, true );
				}
			});
		}

		/**
		 * Custom function to register a payment method type
		 */
		public function register_block_payment_methods() {
			// Check if the required class exists.
			if ( ! class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
				return;
			}

			add_action(
				'woocommerce_blocks_payment_method_type_registration',
				function( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
					foreach ( $this->paymentMethodList as $payment_method ) {
						$block_gateway = 'WC_Payrexx_Gateway_' . $payment_method . '_Block';
						$payment_method_registry->register( new $block_gateway() );
					}
				}
			);

		}

		public function loaded()
		{
			require_once PAYREXX_PM_DIR . 'Abstract/Base.php';
			require_once PAYREXX_PM_DIR . 'Abstract/SubscriptionBase.php';
			require_once PAYREXX_PM_BLOCK_DIR . 'Base/class-wc-payrexx-gateway-block-base.php';

			foreach ($this->paymentMethodList as $paymentMethod) {
				require_once PAYREXX_PM_DIR . $paymentMethod . '.php';
				require_once PAYREXX_PM_BLOCK_DIR . 'class-wc-payrexx-gateway-' . strtolower( $paymentMethod ) . '-block.php';
			}

			// Add payment gateways
			add_filter(
				'woocommerce_payment_gateways',
				function ($gateways)
				{
					foreach ($this->paymentMethodList as $paymentMethod) {
						$gateways[] = 'WC_Payrexx_Gateway_' . $paymentMethod;
					}

					return $gateways;
				}
			);

			add_action(
				'woocommerce_blocks_loaded',
				[
					$this,
					'register_block_payment_methods'
				]
			);

			add_filter(
				'woocommerce_valid_order_statuses_for_payment',
				[
					$this,
					'allow_order_payment_on_cancelled'
				],
				10,
				2
			);
		}

		public function payment_scripts()
		{
			if ( ! is_cart() && ! is_checkout() && ! isset( $_GET['pay_for_order'] ) && ! is_add_payment_method_page() ) {
				return;
			}

			wp_register_style('payrexx_styles', plugins_url('assets/css/payrexx-styles.css', PAYREXX_MAIN_FILE));
			wp_enqueue_style('payrexx_styles');

			if (isset($_GET['payrexx_error'])) {
				wc_add_notice(__('Payment failed. Please choose another method.', 'woo-payrexx-gateway'), 'error');
				PaymentHelper::handleError();
			}

			$googlePayPaymentMethod = new WC_Payrexx_Gateway_GooglePay();
			if ( 'yes' == $googlePayPaymentMethod->enabled ) {
				wp_register_script( 'googlepay_js', 'https://pay.google.com/gp/p/js/pay.js', array( 'jquery' ) );
				wp_enqueue_script( 'googlepay_js' );
				wp_register_script( 'payrexx_googlepay_check', plugins_url( 'assets/js/googlepay.js', PAYREXX_MAIN_FILE ), array(), '1.0.1', true );
				wp_enqueue_script( 'payrexx_googlepay_check' );
			}

			$applePayPaymentMethod = new WC_Payrexx_Gateway_ApplePay();
			if ( 'yes' == $applePayPaymentMethod->enabled ) {
				wp_register_script( 'payrexx_applepay_check', plugins_url( 'assets/js/applepay.js', PAYREXX_MAIN_FILE ), array(), '1.0.1', true );
				wp_enqueue_script( 'payrexx_applepay_check' );
			}
		}

		public static function getPayrexxApiService() {
			return new PayrexxApiService(
				get_option(PAYREXX_CONFIGS_PREFIX . 'instance'),
				get_option(PAYREXX_CONFIGS_PREFIX . 'api_key'),
				get_option(PAYREXX_CONFIGS_PREFIX . 'platform'),
				get_option(PAYREXX_CONFIGS_PREFIX . 'look_and_feel_id')
			);
		}

		public static function getOrderService() {
			return new OrderService();
		}

		/**
		 * Allow payment retry for cancelled orders when using Payrexx payment methods.
		 *
		 * @param array    $statuses Valid order statuses for payment.
		 * @param WC_Order $order    The WooCommerce order object.
		 * @return array
		 */
		public function allow_order_payment_on_cancelled(array $statuses, WC_Order $order): array {
			if (
				strpos($order->get_payment_method(), 'payrexx') === 0 &&
				$order->has_status(OrderService::WC_STATUS_CANCELLED)
			) {
				$statuses[] = OrderService::WC_STATUS_CANCELLED;
			}

			return $statuses;
		}
	}
}

WC_Payrexx_Gateway::instance();
