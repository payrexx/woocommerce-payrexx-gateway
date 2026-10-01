<?php

declare(strict_types=1);

/**
 * PP-20828 — Amount reconciliation in StatusUtil::determineNewOrderStatus().
 *
 * A confirmed payment whose amount differs from the order total by one cent
 * (per-unit rounding drift, e.g. 58.11 paid vs 58.10 order total) or that
 * overpays it used to match none of the strict float `===` branches, so the
 * order never became paid and stayed on hold. The comparison now runs in
 * integer cents; overpayment counts as paid, any underpayment stays on hold.
 *
 * Standalone like OrderServiceStatusMappingTest.php (the plugin has no PHPUnit
 * infrastructure). The REAL classes are invoked.
 *
 * Run:
 *   php tests/StatusUtilReconciliationTest.php
 */

use Payrexx\Models\Response\Transaction;
use PayrexxPaymentGateway\Util\AmountUtil;
use PayrexxPaymentGateway\Util\StatusUtil;

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/Util/AmountUtil.php';
require __DIR__ . '/../src/Util/StatusUtil.php';

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

function status(float $orderTotal, float $confirmed, float $refunded = 0.0): string
{
    return StatusUtil::determineNewOrderStatus($orderTotal, $confirmed, $refunded);
}

/* ------------------------------------------------------------------- Test cases */

echo "PP-20828 StatusUtil amount reconciliation\n";

// Bug case (onyxlashes tx 72015148): 58.11 paid on a 58.10 order stayed on hold.
test('1 cent overpaid => CONFIRMED (bugfix)', function () {
    assertSame(Transaction::CONFIRMED, status(58.10, 58.11));
});
test('1 cent underpaid => WAITING (no tolerance)', function () {
    assertSame(Transaction::WAITING, status(58.11, 58.10));
});
test('exact amount => CONFIRMED (as before)', function () {
    assertSame(Transaction::CONFIRMED, status(58.10, 58.10));
});
test('float drift (0.1 + 0.2 vs 0.3) => CONFIRMED', function () {
    assertSame(Transaction::CONFIRMED, status(0.3, 0.1 + 0.2));
});
test('underpaid by 1 cent on 100.00 => WAITING', function () {
    assertSame(Transaction::WAITING, status(100.00, 99.99));
});
test('underpaid by 2 cents => WAITING', function () {
    assertSame(Transaction::WAITING, status(100.00, 99.98));
});
test('large overpayment (double payment) => CONFIRMED', function () {
    assertSame(Transaction::CONFIRMED, status(58.10, 116.20));
});

// Partial payment (bank transfer) is legitimate and must stay on hold.
test('half paid => WAITING (as before)', function () {
    assertSame(Transaction::WAITING, status(100.00, 50.00));
});
test('nothing paid => WAITING (as before)', function () {
    assertSame(Transaction::WAITING, status(100.00, 0.00));
});

// Security: a tolerance must not let a small transaction pay a big order.
test('1.00 paid on 100.00 order => WAITING', function () {
    assertSame(Transaction::WAITING, status(100.00, 1.00));
});

// Refunds: behaviour unchanged, refund transactions carry a negative amount.
test('full refund => REFUNDED (as before)', function () {
    assertSame(Transaction::REFUNDED, status(58.10, 58.10, -58.10));
});
test('partial refund => PARTIALLY_REFUNDED (as before)', function () {
    assertSame(Transaction::PARTIALLY_REFUNDED, status(58.10, 58.10, -10.00));
});
test('tiny partial refund (3 cents) => PARTIALLY_REFUNDED', function () {
    assertSame(Transaction::PARTIALLY_REFUNDED, status(58.10, 58.10, -0.03));
});
test('overpaid then excess refunded => CONFIRMED (as before)', function () {
    assertSame(Transaction::CONFIRMED, status(58.10, 68.10, -10.00));
});
test('float drift on refund sum => REFUNDED', function () {
    assertSame(Transaction::REFUNDED, status(0.3, 0.3, -(0.1 + 0.2)));
});

// Cent conversion used by gateway amount, charge and refund.
test('toCents rounds instead of truncating (4.35 => 435, 0.29 => 29)', function () {
    assertSame(435, AmountUtil::toCents(4.35));
    assertSame(29, AmountUtil::toCents(0.29));
    assertSame(434, (int) (4.35 * 100), 'sanity: plain cast truncates');
});
test('toCents handles string and integer input', function () {
    assertSame(1005, AmountUtil::toCents('10.05'));
    assertSame(1000, AmountUtil::toCents(10));
});

/* --------------------------------------------------------------------- Output */

echo "\n$passed passed, $failed failed\n";
exit($failed === 0 ? 0 : 1);
