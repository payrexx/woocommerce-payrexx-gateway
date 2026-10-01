<?php

namespace PayrexxPaymentGateway\Util;

class AmountUtil
{
    /**
     * Decimal amount to integer cents. round() is mandatory: (int) (4.35 * 100) is 434.
     *
     * @param float|string|int $amount
     * @return int
     */
    public static function toCents($amount): int
    {
        return (int) round(floatval($amount) * 100);
    }
}
