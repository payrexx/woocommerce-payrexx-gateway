<?php

namespace PayrexxPaymentGateway\Service;

use Exception;
use Payrexx\Models\Response\Transaction;
use PayrexxPaymentGateway\Util\BasketUtil;

class PayrexxApiService
{
	private $instance;
	private $apiKey;
	private $platform;
	private $lookAndFeelId;

	/**
	 * Constructor
	 *
	 * @param EntityRepository $customerRepository
	 * @param LoggerInterface $logger
	 */
	public function __construct($instance, $apiKey, $platform, $lookAndFeelId)
	{
		$this->instance = $instance;
		$this->apiKey = $apiKey;
		$this->platform = $platform;
		$this->lookAndFeelId = $lookAndFeelId;
	}

	public function createPayrexxGateway($order, $cart, $totalAmount, $pm, $reference, $successRedirectUrl, $cancelRedirectUrl, $preAuthorization, $chargeOnAuth) {
		$payrexx = $this->getInterface();
		try {
			$plugin_data = get_plugin_data(
				WP_PLUGIN_DIR . '/woo-payrexx-gateway/woo-payrexx-gateway.php'
			);
			$payrexx->setHttpHeaders([
				'X-Shop-Version'   => get_bloginfo('version'),
				'X-Plugin-Version' => $plugin_data['Version'],
			]);
		} catch(Exception $e) {}

		$gateway = new \Payrexx\Models\Request\Gateway();

		$gateway->setValidity(15);
		$gateway->setPsp([]);
		$gateway->setSkipResultPage(true);

		$totalAmount = round($totalAmount, 2);
		if ($totalAmount) {
			$gateway->setAmount($totalAmount * 100);
		} else {
			// The amount is artificially elevated because the Gateway creation always needs an amount
			$gateway->setAmount(0.50 * 100);
		}

		if (!$totalAmount && $preAuthorization) {
			$gateway->setButtonText([
				1 => 'Autorisieren',
				2 => 'Authorize',
				3 => 'Autoriser',
				4 => 'Autorizzare',
				7 => 'Autoriseer',
			]);
		}

		$gateway->setCurrency(get_woocommerce_currency() ?: 'USD');

		$gateway->setPm([$pm]);
		$gateway->setPreAuthorization($preAuthorization);
		$gateway->setChargeOnAuthorization($chargeOnAuth);

		$basket = BasketUtil::createBasketByCart($cart);
		$basketAmount = round(BasketUtil::getBasketAmount($basket), 2);
		if ($totalAmount && $totalAmount === $basketAmount) {
			$gateway->setBasket($basket);
		} else {
			$gateway->setPurpose([BasketUtil::createPurposeByBasket($basket)]);
		}

		$gateway->setReferenceId($reference);

		$gateway->setLookAndFeelProfile($this->lookAndFeelId ?: null);

		$gateway->setSuccessRedirectUrl($successRedirectUrl);
		$gateway->setCancelRedirectUrl($cancelRedirectUrl);
		$gateway->setFailedRedirectUrl($cancelRedirectUrl);

		$billingAddress = $order->get_billing_address_1() . ' ' . $order->get_billing_address_2();
		$gateway->addField('title', '');
		$gateway->addField('forename', $order->get_billing_first_name());
		$gateway->addField('surname', $order->get_billing_last_name());
		$gateway->addField('company', $order->get_billing_company());
		$gateway->addField('street', $billingAddress);
		$gateway->addField('postcode', $order->get_billing_postcode());
		$gateway->addField('place', $order->get_billing_city());
		$gateway->addField('country', $order->get_billing_country());
		$gateway->addField('phone', $order->get_billing_phone());
		$gateway->addField('email', $order->get_billing_email());
		$gateway->addField('custom_field_1', $order->get_id(), 'WooCommerce Order ID');

		try {
			$response = $payrexx->create($gateway);
			return $response;
		} catch (\Payrexx\PayrexxException $e) {
			return null;
		}
	}

	public function deleteGatewayById($gatewayId):bool {
		$payrexx = $this->getInterface();

		$gateway = new \Payrexx\Models\Request\Gateway();
		$gateway->setId($gatewayId);

		try {
			$payrexx->delete($gateway);
		} catch (\Payrexx\PayrexxException $e) {
			return false;
		}
		return true;
	}

	public function getPayrexxTransaction(int $payrexxTransactionId): ?\Payrexx\Models\Response\Transaction
	{
		$payrexx = $this->getInterface();

		$payrexxTransaction = new \Payrexx\Models\Request\Transaction();
		$payrexxTransaction->setId($payrexxTransactionId);

		try {
			$response = $payrexx->getOne($payrexxTransaction);
			return $response;
		} catch (\Payrexx\PayrexxException $e) {
			return null;
		}
	}

	public function chargeTransaction($transactionId, $amount) {
		$payrexx = $this->getInterface();
		$transaction = new \Payrexx\Models\Request\Transaction();
		$transaction->setId($transactionId);
		$transaction->setAmount(floatval($amount) * 100);
		try {
			$payrexx->charge($transaction);
			return true;
		} catch (\Payrexx\PayrexxException $e) {
		}
		return false;
	}

	/**
	 * @param $gatewayId
	 * @return \Payrexx\Models\Request\Gateway
	 */
	public function getPayrexxGateway($gatewayId) {
		$payrexx = $this->getInterface();
		$gateway = new \Payrexx\Models\Request\Gateway();
		$gateway->setId($gatewayId);
		try {
			$payrexxGateway = $payrexx->getOne($gateway);
			return $payrexxGateway;
		} catch (\Payrexx\PayrexxException $e) {
			throw new \Exception('No gateway found by ID: '. $gatewayId);
		}
	}

	/**
	 * Refund transaction
	 *
	 * @param string $gateway_id        payrexx gateway id.
	 * @param string $transaction_uuid transaction uuid.
	 * @param float  $amount           refund amount.
	 */
	public function refund_transaction( $gateway_id, $transaction_uuid, $amount ) {
		try {
			$payrexx_gateway = $this->getPayrexxGateway( $gateway_id );
			$invoices        = $payrexx_gateway->getInvoices();

			if ( ! $invoices || ! $invoice = end( $invoices ) ) {
				return false;
			}

			$transactions = $invoice['transactions'];
			if ( ! $transactions ) {
				return false;
			}
			$transaction_id = '';
			foreach ( $transactions as $transaction ) {
				if ( $transaction['uuid'] === $transaction_uuid ) {
					$transaction_id = $transaction['id'];
					break;
				}

				// fix: if uuid not exists.
				if ( Transaction::CONFIRMED === $transaction['status'] ) {
					$transaction_id = $transaction['id'];
					break;
				}
			}

			$refund_transaction = $this->getPayrexxTransaction( $transaction_id );
			if ( $refund_transaction->getStatus() === Transaction::CONFIRMED ) {
				$payrexx     = $this->getInterface();
				$transaction = new \Payrexx\Models\Request\Transaction();
				$transaction->setId( $refund_transaction->getId() );
				$transaction->setAmount( (int) ( $amount * 100 ) );
				$refund = $payrexx->refund( $transaction );
				$refund_success_status = [
					Transaction::CONFIRMED,
					Transaction::REFUNDED,
					Transaction::PARTIALLY_REFUNDED,
				];
				if ( in_array( $refund->getStatus(), $refund_success_status ) ) {
					return true;
				}
			}
			return false;
		} catch ( \Payrexx\PayrexxException $e ) {
			return false;
		}
	}

	public function validate_api_credentials($instance, $apiKey, $platform)
	{
		$payrexx = new \Payrexx\Payrexx($instance, $apiKey, '', $platform);
		$signatureCheck = new \Payrexx\Models\Request\SignatureCheck();

		try {
			$response = $payrexx->getOne($signatureCheck);
		} catch(\Payrexx\PayrexxException $e) {
			return false;
		}

		return true;
	}

	/**
	 * @return \Payrexx\Payrexx
	 */
	private function getInterface(): \Payrexx\Payrexx
	{
		$platform = !empty($this->platform) ? $this->platform : \Payrexx\Communicator::API_URL_BASE_DOMAIN;
		return new \Payrexx\Payrexx($this->instance, $this->apiKey, '', $platform);
	}
}
