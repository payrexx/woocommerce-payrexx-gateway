<?php

declare(strict_types=1);

/**
 * PP-19798 — Status mapping in OrderService::handleTransactionStatus().
 *
 * DECLINED/EXPIRED are retryable failures. For orders that belong to a
 * subscription they must map to WC 'failed' (WCS: subscription on-hold +
 * retry) instead of 'cancelled' (WCS: permanent cancellation). Non-subscription
 * orders keep the previous behaviour (cancelled → WC core releases stock again;
 * 'failed' has no increase-stock hook).
 *
 * Standalone like DispatcherBankTransferStatusTest.php (the plugin has no
 * PHPUnit infrastructure). Unlike that test, the REAL class is invoked here —
 * the WP/WCS coupling is covered via function stubs.
 *
 * Run:
 *   php tests/OrderServiceStatusMappingTest.php
 */

use Payrexx\Models\Response\Transaction;
use PayrexxPaymentGateway\Service\OrderService;

/* ------------------------------------------------------------ WP/WCS stubs */

$GLOBALS['px_test_order_has_subscription'] = false;

function wp_clear_scheduled_hook($hook, $args = [])
{
}

function apply_filters($tag, $value)
{
    return $value;
}

function __($text, $domain = 'default')
{
    return $text;
}

function wcs_order_contains_subscription($order, $order_type = 'parent')
{
    return $GLOBALS['px_test_order_has_subscription'];
}

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/Service/OrderService.php';

/* -------------------------------------------------------------- Fake order */

class FakeOrder
{
    public ?string $updated_status = null;
    public array $notes = [];
    private string $status;
    private string $transaction_id;

    public function __construct(string $status = 'pending', string $transaction_id = '')
    {
        $this->status = $status;
        $this->transaction_id = $transaction_id;
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

    public function get_total($context = 'view')
    {
        return '18.00';
    }

    public function add_order_note($note)
    {
        $this->notes[] = $note;
    }

    public function update_status($status, $note = '')
    {
        $this->updated_status = $status;
        $this->status = $status;
    }
}

function runStatus(string $payrexx_status, bool $hasSubscription, string $orderStatus = 'pending'): FakeOrder
{
    $GLOBALS['px_test_order_has_subscription'] = $hasSubscription;
    $order = new FakeOrder($orderStatus);
    (new OrderService())->handleTransactionStatus($order, [], $payrexx_status, 'test-uuid');

    return $order;
}

/* ---------------------------------------------------------------- Mini runner */

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

/* ------------------------------------------------------------------- Test cases */

echo "PP-19798 OrderService status mapping (subscription-scoped)\n";

// Bug case: DECLINED/EXPIRED + subscription -> failed (WCS: on-hold + retry)
test('DECLINED + subscription order => FAILED (bugfix)', function () {
    $order = runStatus(Transaction::DECLINED, true);
    assertSame(OrderService::WC_STATUS_FAILED, $order->updated_status);
});
test('EXPIRED + subscription order => FAILED (bugfix)', function () {
    $order = runStatus(Transaction::EXPIRED, true);
    assertSame(OrderService::WC_STATUS_FAILED, $order->updated_status);
});

// Deliberate abort stays cancelled — even for a subscription
test('CANCELLED + subscription order => stays CANCELLED', function () {
    $order = runStatus(Transaction::CANCELLED, true);
    assertSame(OrderService::WC_STATUS_CANCELLED, $order->updated_status);
});

// Non-subscription orders: current behaviour unchanged (stock release via cancelled)
test('DECLINED + normal order => stays CANCELLED (no behaviour change)', function () {
    $order = runStatus(Transaction::DECLINED, false);
    assertSame(OrderService::WC_STATUS_CANCELLED, $order->updated_status);
});
test('EXPIRED + normal order => stays CANCELLED (no behaviour change)', function () {
    $order = runStatus(Transaction::EXPIRED, false);
    assertSame(OrderService::WC_STATUS_CANCELLED, $order->updated_status);
});
test('CANCELLED + normal order => stays CANCELLED', function () {
    $order = runStatus(Transaction::CANCELLED, false);
    assertSame(OrderService::WC_STATUS_CANCELLED, $order->updated_status);
});

// Regression: ERROR mapping unchanged
test('ERROR + normal order => FAILED (as before)', function () {
    $order = runStatus(Transaction::ERROR, false);
    assertSame(OrderService::WC_STATUS_FAILED, $order->updated_status);
});
test('ERROR + subscription order => FAILED (as before)', function () {
    $order = runStatus(Transaction::ERROR, true);
    assertSame(OrderService::WC_STATUS_FAILED, $order->updated_status);
});

// Regression: transition_allowed gate still applies (failed only from pending/on-hold)
test('DECLINED + subscription + order already processing => no status change', function () {
    $order = runStatus(Transaction::DECLINED, true, 'processing');
    assertSame(null, $order->updated_status);
});
test('DECLINED + subscription + order on-hold => FAILED (allowed transition)', function () {
    $order = runStatus(Transaction::DECLINED, true, 'on-hold');
    assertSame(OrderService::WC_STATUS_FAILED, $order->updated_status);
});

// Regression: WAITING unchanged -> on-hold
test('WAITING + subscription order => ON-HOLD (as before)', function () {
    $order = runStatus(Transaction::WAITING, true);
    assertSame(OrderService::WC_STATUS_ONHOLD, $order->updated_status);
});

/* --------------------------------------------------------------------- Output */

echo "\n$passed passed, $failed failed\n";
exit($failed === 0 ? 0 : 1);
