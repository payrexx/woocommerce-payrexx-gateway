<?php

namespace PayrexxPaymentGateway\Util;

use Payrexx\Models\Response\Gateway;
use Payrexx\Models\Response\Transaction;

class StatusUtil
{
    /** Underpayment of one cent still counts as paid: rounding is never off by more (PP-20828). */
    const AMOUNT_TOLERANCE_CENTS = 1;

    /**
     * @param Gateway $gateway
     * @param array $status
     * @return float
     */
    public static function getAmountByStatusAndGateway(Gateway $gateway, array $status): float
    {
        $amount = 0;
        foreach ($gateway->getInvoices() as $invoice) {
            foreach($invoice['transactions'] as $transaction) {
                if (!in_array($transaction['status'], $status)) continue;
                $amount += $transaction['amount'];
            }
        }
        return ($amount / 100);
    }

    /**
     * Compares in integer cents: float equality on summed amounts is unreliable.
     *
     * @param float $orderTotal
     * @param float $confirmedAmount
     * @param float $refundedAmount refund transactions carry a negative amount.
     * @return string
     */
    public static function determineNewOrderStatus($orderTotal, $confirmedAmount, $refundedAmount): string
    {
        $totalCents = AmountUtil::toCents($orderTotal);
        $confirmedCents = AmountUtil::toCents($confirmedAmount);
        $refundedCents = abs(AmountUtil::toCents($refundedAmount));
        $paidCents = $confirmedCents - $refundedCents;

        if ($paidCents === $totalCents) return Transaction::CONFIRMED;
        // Overpayment or a few cents short: the order is covered, it must not stay on hold.
        if ($refundedCents === 0 && $paidCents >= $totalCents - self::AMOUNT_TOLERANCE_CENTS) return Transaction::CONFIRMED;
        if ($confirmedCents === $refundedCents && $confirmedCents > 0) return Transaction::REFUNDED;
        if ($confirmedCents > $refundedCents && $refundedCents > 0) return Transaction::PARTIALLY_REFUNDED;
        return Transaction::WAITING; // Partially paid
    }
}
