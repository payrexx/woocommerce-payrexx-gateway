<?php

declare(strict_types=1);

/**
 * PP-20206 — the webhook must stay well-behaved on a shop WITHOUT WooCommerce
 * Subscriptions.
 *
 * Dispatcher::check_webhook_response() branches on a request-supplied
 * preAuthorizationId and then calls the global wcs_get_subscriptions_for_order()
 * and new \WC_Subscription(). On a shop that does not run WooCommerce
 * Subscriptions those symbols do not exist, so PHP raises an \Error — which is
 * not an \Exception and therefore escaped the dispatcher's catch as a raw fatal
 * on a public, unauthenticated endpoint.
 *
 * This lives in its own file precisely because it must run in a process where
 * those symbols are NOT defined; the stubs in WebhookTransactionBindingTest.php
 * would make function_exists() true and hide the bug.
 *
 * Run:
 *   php tests/WebhookWithoutSubscriptionsTest.php
 */

use PayrexxPaymentGateway\Webhook\Dispatcher;
use Payrexx\Models\Response\Transaction;

$isChild = in_array('--child', $argv, true);

if (!$isChild) {
    require __DIR__ . '/../vendor/autoload.php';

    echo "PP-20206 webhook on a shop without WooCommerce Subscriptions\n";

    $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' --child 2>&1';
    $out = (string) shell_exec($cmd);

    $failed = 0;

    // No \Error may leave the dispatcher. The child catches \Throwable so the run
    // stays readable, but in production nothing above WC_API::handle_api_requests()
    // catches one - it is a raw HTTP 500 on a public endpoint.
    if (str_contains($out, 'THREW Error') || str_contains($out, 'Fatal error') || str_contains($out, 'Uncaught')) {
        echo "  FAIL  no \\Error escapes the dispatcher\n";
        echo "        -- output --\n        " . trim($out) . "\n";
        $failed++;
    } else {
        echo "  PASS  no \\Error escapes the dispatcher\n";
    }

    if (!str_contains($out, 'Subscriptions not supported on this shop')) {
        echo "  FAIL  the request is answered with an explicit message\n";
        echo "        -- output --\n        " . trim($out) . "\n";
        $failed++;
    } else {
        echo "  PASS  the request is answered with an explicit message\n";
    }

    // Nothing may be written to any order on the way out.
    if (str_contains($out, 'MARK ')) {
        echo "  FAIL  no order is touched\n";
        echo "        -- output --\n        " . trim($out) . "\n";
        $failed++;
    } else {
        echo "  PASS  no order is touched\n";
    }

    echo "\n" . (3 - $failed) . " passed, $failed failed\n";
    exit($failed === 0 ? 0 : 1);
}

/* ---------------------------------------------------------------- Child world */

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/Util/StatusUtil.php';
require __DIR__ . '/../src/Webhook/Dispatcher.php';

// Deliberately NOT defined here: wcs_get_subscriptions_for_order(), WC_Subscription.

function wp_json_encode($data)
{
    return json_encode($data);
}

class FakeApiService
{
    private array $transactions;

    public function __construct(array $transactions)
    {
        $this->transactions = $transactions;
    }

    public function getPayrexxTransaction(int $id): ?Transaction
    {
        return $this->transactions[$id] ?? null;
    }

    public function getPayrexxGateway($gatewayId)
    {
        throw new \Exception('No gateway found by ID: ' . $gatewayId);
    }
}

$transaction = (new Transaction())->fromArray([
    'id' => 39190200,
    'uuid' => 'uuid-39190200',
    'referenceId' => '4001',
    'status' => 'confirmed',
    'amount' => 1800,
]);

$_REQUEST = ['transaction' => [
    'id' => '39190200',
    'status' => 'confirmed',
    // The field an attacker adds to reach the subscription branch.
    'preAuthorizationId' => 'forged-anything',
    'invoice' => ['referenceId' => '4001', 'paymentRequestId' => '35530200'],
]];

$dispatcher = new Dispatcher(
    new FakeApiService([39190200 => $transaction]),
    new class {
        public function handleTransactionStatus($order, array $subscriptions, $status, $uuid, $preAuthId = '')
        {
            echo "MARK handled id=" . $order->get_id() . "\n";
        }
    },
    ''
);

try {
    $dispatcher->check_webhook_response();
} catch (\Throwable $e) {
    echo 'THREW ' . get_class($e) . ': ' . $e->getMessage() . "\n";
}
