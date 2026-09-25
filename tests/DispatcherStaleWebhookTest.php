<?php

declare(strict_types=1);

/**
 * PP-20982 — WooCommerce reuses a pending order across checkouts, so a transaction of an
 * earlier attempt can arrive after the order was re-paid another way. Such a stale webhook
 * must not cancel/fail the order, but a stale webhook in which money moved must still be
 * processed exactly as before.
 *
 * Drives the REAL Dispatcher + OrderService with WP/Payrexx stubs (no PHPUnit in this
 * plugin). send_response() ends with die, so every scenario runs in its own child process
 * and reports the outcome from a shutdown function.
 *
 * Run:
 *   php tests/DispatcherStaleWebhookTest.php
 */

use Payrexx\Models\Response\Transaction;
use PayrexxPaymentGateway\Service\OrderService;
use PayrexxPaymentGateway\Webhook\Dispatcher;

require __DIR__ . '/../vendor/autoload.php';

const CURRENT_GATEWAY_ID = 200;
const CURRENT_TRANSACTION_ID = 2;
const STALE_TRANSACTION_ID = 1;

$scenarios = [
    'stale confirmed, order pending' => [
        'transaction' => ['status' => Transaction::CONFIRMED],
        'expect' => ['message' => 'Success: Processed webhook response', 'paid' => true],
    ],
    'stale confirmed, order re-paid via BACS (on-hold)' => [
        'order' => ['status' => 'on-hold', 'payment_method' => 'bacs'],
        'transaction' => ['status' => Transaction::CONFIRMED],
        'expect' => ['message' => 'Success: Processed webhook response', 'paid' => true],
    ],
    'stale paid bank-transfer invoice (cancelled -> confirmed)' => [
        'transaction' => [
            'status' => Transaction::CANCELLED,
            'psp' => 'Native_PSP',
            'payment' => ['brand' => 'bank-transfer', 'invoicePaymentStatus' => 'paid'],
        ],
        'expect' => ['message' => 'Success: Processed webhook response', 'paid' => true],
    ],
    'stale refunded, order paid' => [
        'order' => ['status' => 'processing', 'transaction_id' => 'uuid-b'],
        'transaction' => ['status' => Transaction::REFUNDED],
        'expect' => ['message' => 'Success: Processed webhook response', 'status' => 'refunded'],
    ],
    'stale partially-refunded, order paid' => [
        'order' => ['status' => 'processing', 'transaction_id' => 'uuid-b'],
        'transaction' => ['status' => Transaction::PARTIALLY_REFUNDED, 'amount' => 900],
        'expect' => ['message' => 'Success: Processed webhook response'],
    ],
    'stale waiting, order pending' => [
        'transaction' => ['status' => Transaction::WAITING],
        'expect' => ['message' => 'Success: Processed webhook response', 'status' => 'on-hold'],
    ],
    'stale cancelled, order pending' => [
        'transaction' => ['status' => Transaction::CANCELLED],
        'expect' => ['message' => 'Transaction belongs to a previous gateway of this order, nothing to process', 'status' => null],
    ],
    'stale expired, order pending' => [
        'transaction' => ['status' => Transaction::EXPIRED],
        'expect' => ['message' => 'Transaction belongs to a previous gateway of this order, nothing to process', 'status' => null],
    ],
    'stale declined, order pending' => [
        'transaction' => ['status' => Transaction::DECLINED],
        'expect' => ['message' => 'Transaction belongs to a previous gateway of this order, nothing to process', 'status' => null],
    ],
    'stale error, order pending' => [
        'transaction' => ['status' => Transaction::ERROR],
        'expect' => ['message' => 'Transaction belongs to a previous gateway of this order, nothing to process', 'status' => null],
    ],
    'stale expired, order re-paid via BACS (on-hold)' => [
        'order' => ['status' => 'on-hold', 'payment_method' => 'bacs'],
        'transaction' => ['status' => Transaction::EXPIRED],
        'expect' => ['message' => 'Order is no longer paid via Payrexx, nothing to process', 'status' => null],
    ],
    'current gateway cancelled, order pending' => [
        'transaction' => ['status' => Transaction::CANCELLED, 'id' => CURRENT_TRANSACTION_ID],
        'expect' => ['message' => 'Success: Processed webhook response', 'status' => 'cancelled'],
    ],
    'no gateway stored on the order, expired' => [
        'order' => ['gateway_id' => 0],
        'transaction' => ['status' => Transaction::EXPIRED],
        'expect' => ['message' => 'Success: Processed webhook response', 'status' => 'cancelled'],
    ],
    'stored gateway not fetchable, expired' => [
        'gateway_fetch_fails' => true,
        'transaction' => ['status' => Transaction::EXPIRED],
        'expect' => ['message' => 'Success: Processed webhook response', 'status' => 'cancelled'],
    ],
    'subscription renewal charged via pre-authorization, confirmed' => [
        'subscription' => true,
        'transaction' => ['status' => Transaction::CONFIRMED],
        'expect' => ['message' => 'Success: Processed webhook response', 'paid' => true],
    ],
    'subscription renewal charged via pre-authorization, expired' => [
        'subscription' => true,
        'transaction' => ['status' => Transaction::EXPIRED],
        'expect' => ['message' => 'Success: Processed webhook response', 'status' => 'failed'],
    ],
];

/* ------------------------------------------------------------- Parent runner */

if (!isset($argv[1])) {
    $passed = 0;
    $failed = 0;
    foreach (array_keys($scenarios) as $name) {
        $output = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' ' . escapeshellarg($name) . ' 2>&1');
        $lines = array_filter(explode("\n", trim((string) $output)));
        $result = json_decode((string) end($lines), true);

        $errors = [];
        if (!is_array($result)) {
            $errors[] = 'no result, output: ' . trim((string) $output);
        } else {
            foreach ($scenarios[$name]['expect'] as $key => $expected) {
                if (($result[$key] ?? null) !== $expected) {
                    $errors[] = $key . ': expected ' . var_export($expected, true) . ', got ' . var_export($result[$key] ?? null, true);
                }
            }
        }

        if ($errors) {
            $failed++;
            echo "  FAIL  $name\n        -> " . implode("\n        -> ", $errors) . "\n";
        } else {
            $passed++;
            echo "  PASS  $name\n";
        }
    }
    echo "\n$passed passed, $failed failed\n";
    exit($failed > 0 ? 1 : 0);
}

/* ------------------------------------------------------ Child: one scenario */

$scenario = $scenarios[$argv[1]];

function __($text, $domain = 'default')
{
    return $text;
}

function apply_filters($tag, $value)
{
    return $value;
}

function wp_clear_scheduled_hook($hook, $args = [])
{
}

function wp_json_encode($data)
{
    $GLOBALS['px_test_response'] = $data['message'] ?? null;
    return '';
}

function wc_get_order($orderId)
{
    return $GLOBALS['px_test_order'];
}

function wc_get_logger()
{
    return new class {
        public function warning($message, $context = [])
        {
        }
    };
}

function WC()
{
    return (object) ['cart' => new class {
        public function empty_cart()
        {
        }
    }];
}

if (!empty($scenario['subscription'])) {
    // Renewals are charged against the pre-authorization, so their transaction is on none of the order's gateways.
    class WC_Subscription
    {
        public function get_last_order($return = 'ids', $type = 'any')
        {
            return 42;
        }

        public function update_meta_data($key, $value)
        {
        }

        public function save()
        {
        }
    }

    function wcs_get_subscriptions_for_order($order, $args = [])
    {
        return [new WC_Subscription()];
    }

    function wcs_order_contains_subscription($order, $orderType = 'parent')
    {
        return true;
    }
}

require __DIR__ . '/../src/Service/OrderService.php';
require __DIR__ . '/../src/Util/StatusUtil.php';
require __DIR__ . '/../src/Webhook/Dispatcher.php';

if (!class_exists('WC_Order')) {
    class WC_Order
    {
    }
}

class FakeOrder extends WC_Order
{
    public ?string $updated_status = null;
    public bool $paid = false;

    public function __construct(
        private string $status,
        private string $payment_method,
        private int $gateway_id,
        private string $transaction_id
    ) {
    }

    public function get_id()
    {
        return 42;
    }

    public function get_type()
    {
        return 'shop_order';
    }

    public function get_status()
    {
        return $this->status;
    }

    public function get_payment_method()
    {
        return $this->payment_method;
    }

    public function get_meta($key, $single = true)
    {
        return $key === 'payrexx_gateway_id' ? $this->gateway_id : '';
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
    }

    public function update_status($status, $note = '')
    {
        $this->updated_status = $status;
    }

    public function payment_complete($transactionId = '')
    {
        $this->paid = true;
    }
}

class FakeApiService
{
    public function __construct(private Transaction $transaction, private bool $gatewayFetchFails)
    {
    }

    public function getPayrexxTransaction($id)
    {
        return $this->transaction;
    }

    public function getPayrexxGateway($gatewayId)
    {
        if ($this->gatewayFetchFails) {
            throw new Exception('No gateway found by ID: ' . $gatewayId);
        }

        $gateway = new \Payrexx\Models\Response\Gateway();
        $gateway->setInvoices([
            ['transactions' => [['id' => CURRENT_TRANSACTION_ID, 'status' => Transaction::WAITING]]],
        ]);
        return $gateway;
    }
}

$orderData = ($scenario['order'] ?? []) + [
    'status' => 'pending',
    'payment_method' => 'payrexx_twint',
    'gateway_id' => CURRENT_GATEWAY_ID,
    'transaction_id' => '',
];
$GLOBALS['px_test_order'] = new FakeOrder(
    $orderData['status'],
    $orderData['payment_method'],
    $orderData['gateway_id'],
    $orderData['transaction_id']
);

$transactionData = $scenario['transaction'] + [
    'id' => STALE_TRANSACTION_ID,
    'amount' => 1800,
    'psp' => 'Twint',
    'payment' => [],
];
$transaction = new Transaction();
$transaction->setId($transactionData['id']);
$transaction->setUuid('uuid-' . $transactionData['id']);
$transaction->setStatus($transactionData['status']);
$transaction->setReferenceId('42');
$transaction->setInvoice(['referenceId' => '42']);
$transaction->setAmount($transactionData['amount']);
$transaction->setPsp($transactionData['psp']);
$transaction->setPayment($transactionData['payment']);

$_REQUEST = ['transaction' => ['id' => $transactionData['id'], 'status' => $transactionData['status']]];
if (!empty($scenario['subscription'])) {
    $_REQUEST['transaction']['preAuthorizationId'] = 99;
}

register_shutdown_function(function () {
    $order = $GLOBALS['px_test_order'];
    echo "\n" . json_encode([
        'message' => $GLOBALS['px_test_response'] ?? null,
        'status' => $order->updated_status,
        'paid' => $order->paid,
    ]) . "\n";
});

$dispatcher = new Dispatcher(
    new FakeApiService($transaction, $scenario['gateway_fetch_fails'] ?? false),
    new OrderService(),
    ''
);
$dispatcher->check_webhook_response();
