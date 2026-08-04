<?php

declare(strict_types=1);

/**
 * PP-20206 — The webhook must bind the order to the FETCHED transaction.
 *
 * Dispatcher::check_webhook_response() takes the order reference and the gateway
 * id straight from $_REQUEST. The transaction itself is fetched from the API and
 * its status is compared, which looks like a validation but is not one: a real,
 * confirmed transaction of order A combined with the referenceId of a pending
 * order B passes that check and marks B as paid without any payment.
 *
 * Part A pins the SDK behaviour the fix relies on. Part B characterises the
 * CURRENT behaviour of every legitimate flow — those cases must stay green
 * across the fix; they are the proof that nothing else breaks. Part C is the
 * attack from the report and is expected to FAIL until the fix lands.
 *
 * Every success path in the dispatcher ends in send_response(), which calls die.
 * Each case therefore runs in its own subprocess and the stubs echo MARK lines
 * as they happen, so state survives the die.
 *
 * Standalone like OrderServiceStatusMappingTest.php (the plugin has no PHPUnit
 * infrastructure). The REAL Dispatcher, OrderService and StatusUtil are invoked;
 * the Payrexx API and the WP/WooCommerce coupling are covered via a fake service
 * and function stubs.
 *
 * Run:
 *   php tests/WebhookTransactionBindingTest.php
 */

/* ------------------------------------------------------------- Child process */

use Payrexx\Models\Response\Transaction;
use PayrexxPaymentGateway\Service\OrderService;
use PayrexxPaymentGateway\Webhook\Dispatcher;

$caseArg = null;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--case=')) {
        $caseArg = substr($arg, 7);
    }
}

if ($caseArg !== null) {
    runCase($caseArg);
    exit(0);
}

/* ------------------------------------------------------------- Parent runner */

require __DIR__ . '/../vendor/autoload.php';

$passed = 0;
$failed = 0;

/** @param callable():void $fn */
function test(string $name, callable $fn): void
{
    global $passed, $failed;
    try {
        $fn();
        $passed++;
        echo "  PASS  $name\n";
    } catch (\Throwable $e) {
        $failed++;
        echo "  FAIL  $name\n        -> {$e->getMessage()}\n";
    }
}

function assertSame($expected, $actual, string $msg = ''): void
{
    if ($expected !== $actual) {
        $e = var_export($expected, true);
        $a = var_export($actual, true);
        throw new \RuntimeException(($msg ? $msg . ' — ' : '') . "expected $e, got $a");
    }
}

function assertMarked(string $needle, string $output, string $msg = ''): void
{
    if (!str_contains($output, 'MARK ' . $needle)) {
        throw new \RuntimeException(
            ($msg ? $msg . ' — ' : '') . "expected marker '$needle'\n        -- output --\n" . indent($output)
        );
    }
}

function assertNotMarked(string $needle, string $output, string $msg = ''): void
{
    if (str_contains($output, 'MARK ' . $needle)) {
        throw new \RuntimeException(
            ($msg ? $msg . ' — ' : '') . "unexpected marker '$needle'\n        -- output --\n" . indent($output)
        );
    }
}

/** Assert that no mutation marker refers to the given order. */
function assertOrderUntouched(string $orderId, string $output, string $msg = ''): void
{
    foreach (explode("\n", $output) as $line) {
        $isMutation = str_starts_with($line, 'MARK paid id=') || str_starts_with($line, 'MARK status=');
        if ($isMutation && str_contains($line, 'id=' . $orderId)) {
            throw new \RuntimeException(
                ($msg ? $msg . ' — ' : '') . "order $orderId was mutated: " . trim($line)
                . "\n        -- output --\n" . indent($output)
            );
        }
    }
}

/**
 * Echoing markers means output has started before the dispatcher calls
 * http_response_code(), which warns under CLI. Drop that harness artefact so
 * failures stay readable.
 */
function indent(string $text): string
{
    $lines = array_filter(
        explode("\n", trim($text)),
        static fn (string $line): bool => !str_contains($line, 'http_response_code()')
    );

    return '        ' . implode("\n        ", array_map('trim', $lines));
}

/** Run one case in its own process; the dispatcher's die() ends only the child. */
function dispatch(string $case): string
{
    $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' --case=' . escapeshellarg($case) . ' 2>&1';

    return (string) shell_exec($cmd);
}

/* ------------------------------------- Part A: the SDK field the fix relies on */

echo "PP-20206 SDK exposes the top-level referenceId\n";

// The merchant API strips referenceId from the invoice object but keeps it at top
// level, so this getter — inherited from Request\Transaction — is the only
// trustworthy order reference available on a fetched transaction.
test('fromArray() hydrates the top-level referenceId', function () {
    $transaction = (new Transaction())->fromArray([
        'id' => 39189529,
        'uuid' => 'uuid-39189529',
        'referenceId' => '2176',
        'status' => 'confirmed',
    ]);

    assertSame('2176', $transaction->getReferenceId());
    assertSame('confirmed', $transaction->getStatus());
});

test('a transaction without a referenceId stays empty rather than fatal', function () {
    $transaction = (new Transaction())->fromArray([
        'id' => 1,
        'uuid' => 'uuid-1',
        'status' => 'confirmed',
    ]);

    assertSame(null, $transaction->getReferenceId());
});

/* ------------------------------- Part B: characterisation of legitimate flows */

echo "\nPP-20206 legitimate flows (must stay identical across the fix)\n";

test('normal checkout => order is paid', function () {
    $out = dispatch('legit');

    assertMarked('paid id=2177', $out);
    assertMarked('cart-emptied', $out);
});

test('reference prefix is resolved to the bare order id', function () {
    $out = dispatch('legit_prefix');

    assertMarked('paid id=2177', $out, 'prefix "shop_2177" must resolve to 2177');
});

test('partial bank transfer => on-hold, not paid', function () {
    $out = dispatch('partial');

    assertMarked('status=on-hold id=2177', $out);
    assertNotMarked('paid id=', $out);
});

test('full refund => order refunded', function () {
    $out = dispatch('refund');

    assertMarked('status=refunded id=2178', $out);
});

// Cash/invoice/prepayment: the offline transaction sits WAITING (order on-hold)
// until the merchant registers the payment in the Payrexx backend. That updates
// the same transaction row, so the invoice reference survives untouched.
test('merchant marks an offline transaction as paid => on-hold order is paid', function () {
    $out = dispatch('manual_confirm');

    assertMarked('paid id=2179', $out);
});

// The Bill "mark as paid" button cancels the waiting transaction instead of
// confirming it; the bank-transfer branch translates that back to paid.
test('merchant marks a bank transfer bill as paid => order is paid despite cancelled', function () {
    $out = dispatch('manual_bill_paid');

    assertMarked('paid id=2180', $out);
});

test('subscription preAuth => auth id stored on the subscription, order not paid', function () {
    $out = dispatch('subscription');

    assertMarked('submeta payrexx_auth_transaction_id=39189700', $out, 'the stored token must be the fetched transaction id, never the request value');
    assertNotMarked('paid id=', $out, 'an authorization is not a payment');
});

test('unknown reference is rejected without touching an order', function () {
    $out = dispatch('no_reference');

    assertNotMarked('paid id=', $out);
    assertNotMarked('status=', $out);
});

// A transaction that cannot be fetched must raise, so Payrexx retries the
// webhook instead of the shop swallowing a transient API failure with 200.
test('an unfetchable transaction raises and touches no order', function () {
    $out = dispatch('unknown_transaction');

    assertOrderUntouched('2177', $out);
    assertMarked('exception Error: Unknown transaction', $out);
});

/* ------------------------------------------------- Part C: the reported attack */

echo "\nPP-20206 forged webhook (expected RED until the fix lands)\n";

// Report: real confirmed transaction of order 2176 replayed with the referenceId
// of the aborted, equally priced order 2177.
test('forged referenceId must not touch the pending order', function () {
    $out = dispatch('attack');

    assertOrderUntouched('2177', $out, 'order 2177 was never paid');
});

// Vacuous while the attack succeeds (2177 is hit instead); it becomes the real
// idempotency guarantee once the order is derived from the transaction.
test('replaying a transaction does not pay its own order twice', function () {
    $out = dispatch('attack');

    assertOrderUntouched('2176', $out, 'order 2176 is already paid');
});

/* ------------------------- Part D: the fetched transaction is the only source */

echo "\nPP-20206 hardening: request fields must not reach the money path\n";

// The request names a gateway that holds the full order total; the transaction's
// own gateway holds a tenth of it. Deriving the gateway from the request let the
// attacker's choice prove the payment.
test('forged paymentRequestId cannot complete an underpaid order', function () {
    $out = dispatch('forged_gateway');

    assertNotMarked('paid id=2190', $out, 'only 10.00 of a 100.00 order was confirmed');
    assertMarked('status=on-hold id=2190', $out);
});

// Last cycle's genuine 18.00 transaction, replayed with an invented
// preAuthorizationId, used to complete the unpaid 100.00 renewal for free.
test('forged preAuthorizationId does not buy a free renewal', function () {
    $out = dispatch('forged_preauth');

    assertNotMarked('paid id=3101', $out, 'renewal 3101 was never charged');
});

/* --------------------------------------------------------------------- Output */

echo "\n$passed passed, $failed failed\n";
exit($failed === 0 ? 0 : 1);

/* ============================================================================ */
/*                              Child-side world                                */
/* ============================================================================ */

function mark(string $line): void
{
    echo "MARK $line\n";
}

/* ---------------------------------------------------------------- WP/WC stubs */

function wp_json_encode($data)
{
    return json_encode($data);
}

function apply_filters($tag, $value)
{
    return $value;
}

function __($text, $domain = 'default')
{
    return $text;
}

function wp_clear_scheduled_hook($hook, $args = [])
{
}

function wcs_order_contains_subscription($order, $order_type = 'parent')
{
    return $GLOBALS['px_has_subscription'] ?? false;
}

function wcs_get_subscriptions_for_order($order_id, $args = [])
{
    return $GLOBALS['px_subscriptions'] ?? [];
}

/**
 * The HPOS-safe factory the dispatcher now uses. Unlike `new WC_Order($id)` it
 * returns false for an id that cannot be loaded instead of throwing, which is
 * what makes the dispatcher's own `!$order` guard reachable.
 */
function wc_get_order($order_id)
{
    if (!$order_id || !isset($GLOBALS['px_orders'][(int) $order_id])) {
        mark("unknown-order id=" . (int) $order_id);

        return false;
    }

    return new WC_Order($order_id);
}

function WC()
{
    static $wc = null;
    if ($wc === null) {
        $wc = new \stdClass();
        $wc->cart = new class {
            public function empty_cart(): void
            {
                mark('cart-emptied');
            }
        };
    }

    return $wc;
}

/**
 * Orders are looked up by id from a registry, because the dispatcher builds the
 * order itself from the reference it derived. An id that is not in the registry
 * is reported, so a wrong derivation shows up instead of silently passing.
 */
class WC_Order
{
    private int $id;
    private string $status;
    private string $transaction_id;
    private string $total;

    public function __construct($id = 0)
    {
        $this->id = (int) $id;
        $order = $GLOBALS['px_orders'][$this->id] ?? null;

        if ($order === null) {
            mark("unknown-order id={$this->id}");
            $order = ['status' => 'pending', 'transaction_id' => '', 'total' => '18.00'];
        }

        $this->status = $order['status'];
        $this->transaction_id = $order['transaction_id'];
        $this->total = $order['total'];
    }

    public function get_id()
    {
        return $this->id;
    }

    public function get_status()
    {
        return $this->status;
    }

    public function get_transaction_id()
    {
        return $this->transaction_id;
    }

    public function get_total($context = 'view')
    {
        return $this->total;
    }

    public function get_meta($key, $single = true)
    {
        return $GLOBALS['px_order_meta'][$key] ?? '';
    }

    public function add_order_note($note)
    {
        mark("note id={$this->id}");
    }

    public function update_status($status, $note = '')
    {
        $this->status = $status;
        mark("status=$status id={$this->id}");
    }

    public function payment_complete($transaction_uuid = '')
    {
        mark("paid id={$this->id} uuid=$transaction_uuid");
    }
}

class WC_Subscription
{
    private int $id;

    public function __construct($id = 0)
    {
        $this->id = (int) $id;
    }

    public function get_id()
    {
        return $this->id;
    }

    public function get_last_order($return = 'ids', $order_type = 'any')
    {
        return $GLOBALS['px_subscription_last_order'] ?? 0;
    }

    public function update_meta_data($key, $value)
    {
        mark("submeta $key=$value");
    }

    public function save()
    {
    }
}

/**
 * Stands in for PayrexxApiService. The dispatcher does not type-hint the service,
 * so only the two methods it actually calls are needed.
 */
class FakeApiService
{
    private array $transactions;
    private array $gateways;

    public function __construct(array $transactions, array $gateways = [])
    {
        $this->transactions = $transactions;
        $this->gateways = $gateways;
    }

    public function getPayrexxTransaction(int $id): ?Transaction
    {
        return $this->transactions[$id] ?? null;
    }

    public function getPayrexxGateway($gatewayId)
    {
        if (!isset($this->gateways[(int) $gatewayId])) {
            throw new \Exception('No gateway found by ID: ' . $gatewayId);
        }

        return $this->gateways[(int) $gatewayId];
    }
}

/**
 * Build a response transaction through the SDK's real hydration path.
 *
 * $paymentRequestId populates the transaction's own invoice. The dispatcher now
 * reads the gateway from there rather than from the request, so every fixture
 * that reaches the amount reconciliation has to carry it.
 */
function txnFixture(int $id, string $status, ?string $referenceId, $paymentRequestId = null, $amount = null): Transaction
{
    $data = [
        'id' => $id,
        'uuid' => 'uuid-' . $id,
        'status' => $status,
    ];
    if ($referenceId !== null) {
        $data['referenceId'] = $referenceId;
    }
    if ($paymentRequestId !== null) {
        $data['invoice'] = ['paymentRequestId' => $paymentRequestId];
    }
    if ($amount !== null) {
        $data['amount'] = $amount;
    }

    return (new Transaction())->fromArray($data);
}

/** Gateway invoices in the shape StatusUtil expects; amounts are in cents. */
function gatewayFixture(array $transactions): \Payrexx\Models\Response\Gateway
{
    $gateway = new \Payrexx\Models\Response\Gateway();
    $gateway->setInvoices([['transactions' => $transactions]]);

    return $gateway;
}

function cents(string $status, int $amount): array
{
    return ['status' => $status, 'amount' => $amount];
}

/* -------------------------------------------------------------- Case fixtures */

function runCase(string $case): void
{
    require __DIR__ . '/../vendor/autoload.php';
    require __DIR__ . '/../src/Util/StatusUtil.php';
    require __DIR__ . '/../src/Service/OrderService.php';
    require __DIR__ . '/../src/Webhook/Dispatcher.php';

    $GLOBALS['px_orders'] = [];
    $GLOBALS['px_order_meta'] = [];
    $GLOBALS['px_subscriptions'] = [];
    $GLOBALS['px_has_subscription'] = false;
    $prefix = '';

    switch ($case) {
        // The report: transaction 39189529 truly belongs to the paid order 2176.
        case 'attack':
            $GLOBALS['px_orders'] = [
                2176 => ['status' => 'processing', 'transaction_id' => 'uuid-39189529', 'total' => '18.00'],
                2177 => ['status' => 'pending', 'transaction_id' => '', 'total' => '18.00'],
            ];
            $_REQUEST = ['transaction' => [
                'id' => '39189529',
                'status' => 'confirmed',
                'invoice' => ['referenceId' => '2177', 'paymentRequestId' => '35522847'],
            ]];
            $api = new FakeApiService(
                [39189529 => txnFixture(39189529, 'confirmed', '2176', '35522847', 1800)],
                [35522847 => gatewayFixture([cents('confirmed', 1800)])]
            );
            break;

        case 'legit':
            $GLOBALS['px_orders'] = [
                2177 => ['status' => 'pending', 'transaction_id' => '', 'total' => '18.00'],
            ];
            $_REQUEST = ['transaction' => [
                'id' => '39189600',
                'status' => 'confirmed',
                'invoice' => ['referenceId' => '2177', 'paymentRequestId' => '35522900'],
            ]];
            $api = new FakeApiService(
                [39189600 => txnFixture(39189600, 'confirmed', '2177', '35522900', 1800)],
                [35522900 => gatewayFixture([cents('confirmed', 1800)])]
            );
            break;

        case 'legit_prefix':
            $prefix = 'shop';
            $GLOBALS['px_orders'] = [
                2177 => ['status' => 'pending', 'transaction_id' => '', 'total' => '18.00'],
            ];
            $_REQUEST = ['transaction' => [
                'id' => '39189601',
                'status' => 'confirmed',
                'invoice' => ['referenceId' => 'shop_2177', 'paymentRequestId' => '35522901'],
            ]];
            $api = new FakeApiService(
                [39189601 => txnFixture(39189601, 'confirmed', 'shop_2177', '35522901', 1800)],
                [35522901 => gatewayFixture([cents('confirmed', 1800)])]
            );
            break;

        // Bank transfer: only half the amount arrived, so the order stays on-hold.
        case 'partial':
            $GLOBALS['px_orders'] = [
                2177 => ['status' => 'pending', 'transaction_id' => '', 'total' => '18.00'],
            ];
            $_REQUEST = ['transaction' => [
                'id' => '39189602',
                'status' => 'confirmed',
                'invoice' => ['referenceId' => '2177', 'paymentRequestId' => '35522902'],
            ]];
            $api = new FakeApiService(
                [39189602 => txnFixture(39189602, 'confirmed', '2177', '35522902', 900)],
                [35522902 => gatewayFixture([cents('confirmed', 900)])]
            );
            break;

        case 'refund':
            $GLOBALS['px_orders'] = [
                2178 => ['status' => 'processing', 'transaction_id' => 'uuid-39189603', 'total' => '18.00'],
            ];
            $_REQUEST = ['transaction' => [
                'id' => '39189603',
                'status' => 'refunded',
                'invoice' => ['referenceId' => '2178', 'paymentRequestId' => '35522903'],
            ]];
            $api = new FakeApiService(
                [39189603 => txnFixture(39189603, 'refunded', '2178', '35522903', 1800)],
                [35522903 => gatewayFixture([cents('confirmed', 1800), cents('refunded', -1800)])]
            );
            break;

        // Merchant registers a cash/invoice payment: same transaction row flips to confirmed.
        case 'manual_confirm':
            $GLOBALS['px_orders'] = [
                2179 => ['status' => 'on-hold', 'transaction_id' => '', 'total' => '18.00'],
            ];
            $_REQUEST = ['transaction' => [
                'id' => '39189604',
                'status' => 'confirmed',
                'invoice' => ['referenceId' => '2179', 'paymentRequestId' => '35522904'],
            ]];
            $api = new FakeApiService(
                [39189604 => txnFixture(39189604, 'confirmed', '2179', '35522904', 1800)],
                [35522904 => gatewayFixture([cents('confirmed', 1800)])]
            );
            break;

        // Bill marked as paid in the backend: the waiting transaction is cancelled.
        case 'manual_bill_paid':
            $GLOBALS['px_orders'] = [
                2180 => ['status' => 'on-hold', 'transaction_id' => '', 'total' => '18.00'],
            ];
            $_REQUEST = ['transaction' => [
                'id' => '39189605',
                'status' => 'cancelled',
                'invoice' => ['referenceId' => '2180', 'paymentRequestId' => '35522905'],
            ]];
            $transaction = txnFixture(39189605, 'cancelled', '2180');
            $transaction->setPsp('Native_PSP');
            $transaction->setPayment(['brand' => 'bank-transfer', 'invoicePaymentStatus' => 'paid']);
            $api = new FakeApiService([39189605 => $transaction]);
            break;

        // preAuthorization: the order id is replaced by the subscription's last order.
        case 'subscription':
            $GLOBALS['px_orders'] = [
                3001 => ['status' => 'pending', 'transaction_id' => '', 'total' => '18.00'],
            ];
            $GLOBALS['px_subscriptions'] = [new WC_Subscription(500)];
            $GLOBALS['px_subscription_last_order'] = 3001;
            $GLOBALS['px_has_subscription'] = true;
            $_REQUEST = ['transaction' => [
                'id' => '39189700',
                'status' => 'authorized',
                'preAuthorizationId' => 'pa-123',
                'invoice' => ['referenceId' => '3000', 'paymentRequestId' => '35523000'],
            ]];
            $api = new FakeApiService(
                [39189700 => txnFixture(39189700, 'authorized', '3000')]
            );
            break;

        // API down or transaction id unknown to the instance.
        case 'unknown_transaction':
            $GLOBALS['px_orders'] = [
                2177 => ['status' => 'pending', 'transaction_id' => '', 'total' => '18.00'],
            ];
            $_REQUEST = ['transaction' => [
                'id' => '99999999',
                'status' => 'confirmed',
                'invoice' => ['referenceId' => '2177', 'paymentRequestId' => '35522900'],
            ]];
            $api = new FakeApiService([]);
            break;

        // Transaction of another system on the same instance: no order reference.
        case 'no_reference':
            $_REQUEST = ['transaction' => [
                'id' => '39189800',
                'status' => 'confirmed',
                'invoice' => ['paymentRequestId' => '35523100'],
            ]];
            $api = new FakeApiService(
                [39189800 => txnFixture(39189800, 'confirmed', null)]
            );
            break;

        // Hardening: the request names gateway 35530002 (holds the full 100.00),
        // while the transaction's own gateway 35530001 holds only 10.00.
        case 'forged_gateway':
            $GLOBALS['px_orders'] = [
                2190 => ['status' => 'pending', 'transaction_id' => '', 'total' => '100.00'],
            ];
            $_REQUEST = ['transaction' => [
                'id' => '39190001',
                'status' => 'confirmed',
                'invoice' => ['referenceId' => '2190', 'paymentRequestId' => '35530002'],
            ]];
            $api = new FakeApiService(
                [39190001 => txnFixture(39190001, 'confirmed', '2190', '35530001', 1000)],
                [
                    35530001 => gatewayFixture([cents('confirmed', 1000)]),
                    35530002 => gatewayFixture([cents('confirmed', 10000)]),
                ]
            );
            break;

        // Hardening: an invented preAuthorizationId re-points the notification at the
        // subscription's unpaid 100.00 renewal, carrying only an 18.00 transaction.
        case 'forged_preauth':
            $GLOBALS['px_orders'] = [
                3100 => ['status' => 'processing', 'transaction_id' => 'uuid-39190100', 'total' => '18.00'],
                3101 => ['status' => 'pending', 'transaction_id' => '', 'total' => '100.00'],
            ];
            $GLOBALS['px_subscriptions'] = [new WC_Subscription(600)];
            $GLOBALS['px_subscription_last_order'] = 3101;
            $GLOBALS['px_has_subscription'] = true;
            $_REQUEST = ['transaction' => [
                'id' => '39190100',
                'status' => 'confirmed',
                'preAuthorizationId' => 'forged-anything',
                'invoice' => ['referenceId' => '3100', 'paymentRequestId' => '35530100'],
            ]];
            $api = new FakeApiService(
                [39190100 => txnFixture(39190100, 'confirmed', '3100', '35530100', 1800)],
                [35530100 => gatewayFixture([cents('confirmed', 1800)])]
            );
            break;

        default:
            fwrite(STDERR, "unknown case: $case\n");
            exit(2);
    }

    $dispatcher = new Dispatcher(
        $api,
        new OrderService(),
        $prefix
    );

    try {
        $dispatcher->check_webhook_response();
    } catch (\Throwable $e) {
        mark('exception ' . $e->getMessage());
    }
}
