<?php

namespace PayrexxPaymentGateway\Service;

use Exception;
use Payrexx\Communicator;
use Payrexx\Models\Response\Transaction;
use Payrexx\Payrexx;
use Payrexx\PayrexxException;
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

	public function createPayrexxGateway($order, $cart, $totalAmount, $pm, $data, $preAuthorization, $chargeOnAuth) {
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

        $formattedTotalAmount = wc_format_decimal( $totalAmount, wc_get_price_decimals() );
        $totalInCents = (int) round( $formattedTotalAmount * 100 );
        $totalAmount = round( $totalAmount, 2 );
		if ( $totalAmount ) {
			$gateway->setAmount( $totalInCents );
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
		$basketInCents = (int) round(BasketUtil::getBasketAmount($basket) * 100);

		// Each line amount is rounded to whole cents per unit, so the basket sum can drift
		// a few cents from the order total (PP-20204). Tolerate that instead of collapsing
		// every line item into one purpose string, which would also drop the VAT breakdown.
		$roundingTolerance = 1;
		foreach ($basket as $basketItem) {
			$roundingTolerance += (int) $basketItem['quantity'];
		}

		if ($totalAmount && abs($totalInCents - $basketInCents) <= $roundingTolerance) {
			$gateway->setBasket($basket);
		} else {
			$gateway->setPurpose([BasketUtil::createPurposeByBasket($basket)]);
		}

		$gateway->setReferenceId( $data['reference'] );
		$gateway->setLookAndFeelProfile( $this->lookAndFeelId ?: null );
		$gateway->setSuccessRedirectUrl( $data['success_redirect_url'] );
		$gateway->setCancelRedirectUrl( $data['cancel_redirect_url'] );
		$gateway->setFailedRedirectUrl( $data['cancel_redirect_url'] );

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
		$gateway->setLanguage( $data['language'] ?? LANG['0'] );

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

    /**
     * Cancel every waiting transaction belonging to a gateway.
     *
     * Deleting the gateway does not touch its transactions, so this has to run first.
     * A gateway that can no longer be read is reported as a failure: its transactions
     * are then unreachable and would stay open without anyone noticing.
     *
     * @param int $gatewayId payrexx gateway id.
     * @return bool false as soon as one transaction could not be cancelled.
     * @throws PayrexxException
     */
	public function cancelWaitingTransactions( int $gatewayId ): bool {
		// No gateway was ever stored on the order - there is nothing to cancel.
		if ( ! $gatewayId ) {
			return true;
		}

		try {
			$gateway = $this->getPayrexxGateway( $gatewayId );
		} catch ( Exception $e ) {
			return false;
		}

		$cancelled = true;
		foreach ( $gateway->getInvoices() ?? [] as $invoice ) {
			foreach ( $invoice['transactions'] ?? [] as $transaction ) {
				if ( Transaction::WAITING !== ( $transaction['status'] ?? '' ) ) {
					continue;
				}
				// The call comes first so it runs for every waiting transaction.
				$cancelled = $this->cancelTransaction( (int) ( $transaction['id'] ?? 0 ) ) && $cancelled;
			}
		}

		return $cancelled;
	}

    /**
     * Cancel a single waiting transaction.
     *
     * Raw request on purpose: no SDK method emits act "cancel", and $payrexx->delete()
     * maps to DELETE /Transaction/{id}, which returns 404 for a waiting transaction.
     *
     * @param int $transactionId payrexx transaction id.
     * @return bool
     * @throws PayrexxException
     */
	protected function cancelTransaction( int $transactionId ): bool {
		$version = $this->getInterface()->getVersion();
		if ( ! $transactionId || ! $version ) {
			return false;
		}

		$url = sprintf(
			'https://api.%s/v%s/Transaction/%d/cancel?instance=%s',
			$this->getApiBaseDomain(),
			$version,
			$transactionId,
			rawurlencode( (string) $this->instance )
		);

		// Runs while the customer waits for the redirect, so fail fast rather than hang.
		$response = wp_remote_request(
			$url,
			[
				'method'  => 'PATCH',
				'timeout' => 10,
				'headers' => [
					'x-api-key'    => $this->apiKey,
					'Content-Type' => 'application/x-www-form-urlencoded',
				],
				'body'    => '',
			]
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		// Below API 1.15 errors arrive as HTTP 200 with status "error", so judge the envelope.
		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		return is_array( $body ) && 'success' === ( $body['status'] ?? '' );
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

	/**
	 * @return bool|null true = charged, false = declined/failed (safe to retry),
	 *                   null = request timed out (outcome unknown, must NOT be retried).
	 */
	public function chargeTransaction($transactionId, $amount) {
		$payrexx = $this->getInterface();
		$transaction = new \Payrexx\Models\Request\Transaction();
		$transaction->setId($transactionId);
		$transaction->setAmount( (int) round( floatval( $amount ) * 100 ) );
		try {
			$payrexx->charge($transaction);
			return true;
		} catch (\Payrexx\PayrexxException $e) {
			// A cURL timeout (no HTTP response) means the request was sent but we never
			// learned the outcome - the charge may well have gone through. Signal "unknown"
			// so the caller does not retry and risk a double charge. Any other error is a
			// real failure and can be retried safely.
			if ($e->getCode() === 0 && (int) $e->getMessage() === CURLE_OPERATION_TIMEDOUT) {
				return null;
			}
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
	 * @return Payrexx
     * @throws PayrexxException
     */
	private function getInterface(): Payrexx
	{
		return new Payrexx($this->instance, $this->apiKey, '', $this->getApiBaseDomain());
	}

	/**
	 * Api host the SDK and the raw cancel request must agree on.
	 *
	 * @return string
	 */
	private function getApiBaseDomain(): string
	{
		if (!empty($this->platform)) {
			return $this->platform;
		}
		return Communicator::API_URL_BASE_DOMAIN;
	}
}
