<?php

declare(strict_types=1);

/**
 * PP-20477 — An aborted payment attempt must not cancel an order that another
 * attempt on the same gateway has paid or is about to pay.
 *
 * The cancel/error redirect fires once per attempt. PaymentHelper::handleError()
 * used to cancel the WooCommerce order + all waiting Payrexx transactions
 * optimistically on that redirect, so an aborted attempt killed a sibling attempt
 * (e.g. Apple Pay) whose confirming webhook had not arrived yet.
 *
 * The fix gates the cleanup on the real Payrexx state
 * (PayrexxApiService::gatewayHasLiveTransaction()): only cancel when no attempt is
 * confirmed / authorized / waiting-for-webhook; otherwise leave the order to the
 * webhook. This test drives the REAL PaymentHelper + OrderService with WP/Payrexx
 * stubs (the plugin has no PHPUnit infrastructure - same style as
 * OrderServiceStatusMappingTest.php).
 *
 * Run:
 *   php tests/PaymentHelperCancelGuardTest.php
 */

use Payrexx\Models\Response\Transaction;
use PayrexxPaymentGateway\Service\PayrexxApiService;

/* ------------------------------------------------------------ WP/WCS stubs */

if (!defined('AUTH_SALT')) {
    define('AUTH_SALT', 'unit-test-salt');
}
if (!defined('PAYREXX_CONFIGS_PREFIX')) {
    define('PAYREXX_CONFIGS_PREFIX', 'payrexx_');
}

$GLOBALS['px_test_order_has_subscription'] = false;

function __($text, $domain = 'default')
{
    return $text;
}

function apply_filters($tag, $value)
{
    return $value;
}

function wcs_order_contains_subscription($order, $order_type = 'parent')
{
    return $GLOBALS['px_test_order_has_subscription'];
}

// new_checkout_after_cancel off, so handleError() never reaches header()/exit().
function get_option($name, $default = false)
{
    return '';
}

function wc_get_checkout_url()
{
    return 'https://example.test/checkout';
}

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/Service/OrderService.php';
require __DIR__ . '/../src/Service/PayrexxApiService.php';
require __DIR__ . '/../src/Helper/PaymentHelper.php';

/* ------------------------------------------------------------- Fake WC_Order */

class PxFakeDate
{
    public function __construct(private string $value)
    {
    }

    public function __toString(): string
    {
        return $this->value;
    }
}

class WC_Order
{
    public ?string $updated_status = null;
    public array $notes = [];
    private string $status;
    private string $transaction_id;
    private int $gateway_id;
    private string $date;

    public function __construct($order_id = 0)
    {
        $cfg = $GLOBALS['px_wc_order_cfg'] ?? [];
        $this->status = $cfg['status'] ?? 'pending';
        $this->transaction_id = $cfg['transaction_id'] ?? '';
        $this->gateway_id = $cfg['gateway_id'] ?? 111;
        $this->date = $cfg['date'] ?? '2026-08-25 10:00:00';
        // handleError() builds its own WC_Order; expose the instance for assertions.
        $GLOBALS['px_last_order'] = $this;
    }

    public function get_id()
    {
        return 42;
    }

    public function get_status()
    {
        return $this->status;
    }

    public function get_transaction_id()
    {
        return $this->transaction_id;
    }

    public function get_meta($key, $single = true)
    {
        return $key === 'payrexx_gateway_id' ? $this->gateway_id : '';
    }

    public function get_date_created()
    {
        return new PxFakeDate($this->date);
    }

    public function update_status($status, $note = '')
    {
        $this->updated_status = $status;
        $this->status = $status;
    }

    public function add_order_note($note)
    {
        $this->notes[] = $note;
    }
}

/* --------------------------------------------------- Fake Payrexx API service */

class FakePayrexxApiService
{
    public array $calls = [];

    public function __construct(private bool $live, private bool $cancelResult = true)
    {
    }

    public function gatewayHasLiveTransaction($gatewayId): bool
    {
        $this->calls[] = 'live';
        return $this->live;
    }

    public function cancelWaitingTransactions($gatewayId): bool
    {
        $this->calls[] = 'cancel';
        return $this->cancelResult;
    }

    public function deleteGatewayById($gatewayId): bool
    {
        $this->calls[] = 'delete';
        return true;
    }
}

class WC_Payrexx_Gateway
{
    public static function getOrderService(): \PayrexxPaymentGateway\Service\OrderService
    {
        return new \PayrexxPaymentGateway\Service\OrderService();
    }

    public static function getPayrexxApiService()
    {
        return $GLOBALS['px_fake_api'];
    }
}

/* ----------------------------------- Subclass to unit-test the decision logic */

class TestablePayrexxApiService extends PayrexxApiService
{
    private $fakeGateway;
    private bool $throw;

    public function __construct($fakeGateway, bool $throw = false)
    {
        parent::__construct('instance', 'key', 'platform', null);
        $this->fakeGateway = $fakeGateway;
        $this->throw = $throw;
    }

    public function getPayrexxGateway($gatewayId)
    {
        if ($this->throw) {
            throw new \Exception('gateway unreadable');
        }
        return $this->fakeGateway;
    }
}

class FakeGateway
{
    public function __construct(private array $invoices)
    {
    }

    public function getInvoices()
    {
        return $this->invoices;
    }
}

function invoicesWith(array ...$statuses): array
{
    $transactions = array_map(static fn ($s) => ['id' => 1, 'status' => $s], array_merge(...$statuses));
    return [['transactions' => $transactions]];
}

/* -------------------------------------------------------------- Test harness */

function runHandleError(array $orderCfg, bool $live, bool $cancelResult = true): array
{
    $GLOBALS['px_wc_order_cfg'] = $orderCfg;
    $GLOBALS['px_test_order_has_subscription'] = $orderCfg['subscription'] ?? false;
    $GLOBALS['px_fake_api'] = new FakePayrexxApiService($live, $cancelResult);
    $GLOBALS['px_last_order'] = null;

    $date = $orderCfg['date'] ?? '2026-08-25 10:00:00';
    $_GET['order_id'] = 42;
    $_GET['order_hash'] = ($orderCfg['hash'] ?? null) ?? hash('sha256', AUTH_SALT . $date);

    \PayrexxPaymentGateway\Helper\PaymentHelper::handleError();

    return [
        'order' => $GLOBALS['px_last_order'],
        'calls' => $GLOBALS['px_fake_api']->calls,
    ];
}

$passed = 0;
$failed = 0;

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
        throw new \RuntimeException(
            ($msg ? $msg . ' — ' : '') .
            'expected ' . var_export($expected, true) . ', got ' . var_export($actual, true)
        );
    }
}

function assertTrue($cond, string $msg = ''): void
{
    assertSame(true, $cond, $msg);
}

/* ============================================================================
 * Part 1 — PaymentHelper::handleError() gating (the ticket behaviour)
 * ========================================================================== */

echo "PP-20477 handleError() cancel guard\n";

// THE BUG: a sibling attempt is live (confirmed/waiting) while an aborted attempt
// hits the cancel redirect. The order must survive untouched.
test('live sibling transaction => order NOT cancelled, no Payrexx cleanup', function () {
    $r = runHandleError(['status' => 'pending', 'transaction_id' => ''], live: true);
    assertSame(null, $r['order']->updated_status, 'order must stay pending');
    assertSame(['live'], $r['calls'], 'must not cancel transactions or delete the gateway');
});

// Genuine abort with nothing else in flight: cleanup still works.
test('no live transaction => order cancelled + transactions cancelled + gateway deleted', function () {
    $r = runHandleError(['status' => 'pending', 'transaction_id' => ''], live: false);
    assertSame('cancelled', $r['order']->updated_status);
    assertSame(['live', 'cancel', 'delete'], $r['calls']);
});

// Subscription order: retryable failure keeps the WCS subscription on-hold.
test('subscription order, no live transaction => failed (not cancelled)', function () {
    $r = runHandleError(
        ['status' => 'pending', 'transaction_id' => '', 'subscription' => true],
        live: false
    );
    assertSame('failed', $r['order']->updated_status);
    assertSame(['live', 'cancel', 'delete'], $r['calls']);
});

// Already paid order (has transaction_id): transition_allowed() blocks the cancel,
// so even with no live flag nothing is torn down.
test('already paid order => no transition, no cleanup', function () {
    $r = runHandleError(['status' => 'processing', 'transaction_id' => 'uuid-1'], live: false);
    assertSame(null, $r['order']->updated_status);
    assertSame(['live'], $r['calls']);
});

// A failed cancel-cleanup leaves a diagnostic order note.
test('cancel cleanup failure => order note added', function () {
    $r = runHandleError(['status' => 'pending', 'transaction_id' => ''], live: false, cancelResult: false);
    assertSame('cancelled', $r['order']->updated_status);
    assertSame(['live', 'cancel', 'delete'], $r['calls']);
    assertSame(1, count($r['order']->notes), 'a diagnostic note must be recorded');
});

// Replay protection: a wrong hash is ignored before anything happens.
test('invalid order_hash => early return, no transition, no Payrexx call at all', function () {
    $r = runHandleError(['status' => 'pending', 'transaction_id' => '', 'hash' => 'wrong'], live: false);
    // handleError builds a WC_Order for the hash check, but must bail before touching it.
    assertSame(null, $r['order']->updated_status, 'order must not be transitioned');
    assertSame([], $r['calls'], 'no Payrexx API call may run');
});

/* ============================================================================
 * Part 2 — PayrexxApiService::gatewayHasLiveTransaction() decision logic
 * ========================================================================== */

echo "\nPP-20477 gatewayHasLiveTransaction() status logic\n";

function hasLive($fakeGatewayOrThrow, int $gatewayId = 111): bool
{
    if ($fakeGatewayOrThrow === 'throw') {
        return (new TestablePayrexxApiService(null, true))->gatewayHasLiveTransaction($gatewayId);
    }
    return (new TestablePayrexxApiService($fakeGatewayOrThrow))->gatewayHasLiveTransaction($gatewayId);
}

test('gatewayId 0 => false (nothing to inspect)', function () {
    assertSame(false, hasLive(new FakeGateway([]), 0));
});

test('only dead transactions (cancelled/declined/expired/error) => false', function () {
    $g = new FakeGateway(invoicesWith([
        Transaction::CANCELLED,
        Transaction::DECLINED,
        Transaction::EXPIRED,
        Transaction::ERROR,
    ]));
    assertSame(false, hasLive($g));
});

test('a waiting sibling among dead ones => true (the Apple-Pay race)', function () {
    $g = new FakeGateway(invoicesWith([Transaction::DECLINED, Transaction::WAITING]));
    assertTrue(hasLive($g));
});

test('a confirmed transaction => true', function () {
    $g = new FakeGateway(invoicesWith([Transaction::CANCELLED, Transaction::CONFIRMED]));
    assertTrue(hasLive($g));
});

test('an authorized (pre-auth) transaction => true', function () {
    $g = new FakeGateway(invoicesWith([Transaction::AUTHORIZED]));
    assertTrue(hasLive($g));
});

test('a refunded transaction => true (a payment happened)', function () {
    $g = new FakeGateway(invoicesWith([Transaction::REFUNDED]));
    assertTrue(hasLive($g));
});

test('no invoices => false', function () {
    assertSame(false, hasLive(new FakeGateway([])));
});

test('unreadable gateway => true (fail safe, leave order for webhook)', function () {
    assertTrue(hasLive('throw'));
});

/* --------------------------------------------------------------------- Output */

echo "\n$passed passed, $failed failed\n";
exit($failed === 0 ? 0 : 1);
