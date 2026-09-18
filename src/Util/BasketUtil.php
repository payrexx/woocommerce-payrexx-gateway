<?php

namespace PayrexxPaymentGateway\Util;

use Exception;
use WC_Tax;

class BasketUtil
{

    public static function createBasketByCart($cart): array
    {
        $productPriceIncludesTax = ('yes' === get_option( 'woocommerce_prices_include_tax'));

        $cartItems = $cart->get_cart();
        $basket = [];

        foreach ($cartItems as $item) {
            $quantity = (int) $item['quantity'];

            // Actual charged line amount (post-coupon) so the basket reconciles (PP-20637).
            $lineTotal = (float) ( $item['line_total'] ?? 0 );
            $lineTax   = (float) ( $item['line_tax'] ?? 0 );
            $unitAmount = $quantity > 0 ? ( $lineTotal + $lineTax ) / $quantity : 0.0;

            // Get VAT rate based on product tax class
            $tax_class = $item['data']->get_tax_class();
            $tax_rates = WC_Tax::get_rates( $tax_class );
            $tax_rate = !empty( $tax_rates ) ? reset( $tax_rates )['rate'] : 0;

            $basket[] = [
                'name' => wp_strip_all_tags( $item['data']->get_name() ),
                'description' => wp_strip_all_tags( self::get_product_description( $item ) ),
                'quantity' => $quantity,
                'amount' => (int) round( $unitAmount * 100 ),
                'sku' => $item['data']->get_sku(),
                'vatRate' => $tax_rate,
            ];
        }

        // Shipping
        $shipping = $cart->get_shipping_total();
        $shippingTax = $cart->get_shipping_tax();
        if ( $shipping || $shippingTax ) {
            $shippingTaxPercentage = 0;
            if ( !empty( $cart->get_shipping_taxes() ) ) {
                $shippingTaxPercentage = WC_Tax::get_rate_percent_value(
                    array_key_first( $cart->get_shipping_taxes() )
                );
            }
            $shippingAmount = round( $shipping + $shippingTax, 2 );
            $basket[] = [
                'name' =>  [
                    1 => 'Versand',
                    2 => 'Shipping',
                ],
                'quantity' => 1,
                'amount' => round( $shippingAmount * 100 ),
                'vatRate' => $shippingTaxPercentage,
            ];
        }

        // Fee
        $fee = $cart->get_fee_total();
        $feeTax = $cart->get_fee_tax();
        if ($fee) {
            $feeAmount = $fee;
            $feeAmount += $productPriceIncludesTax ? 0 : $feeTax;
            $basket[] = [
                'name' =>  [
                    1 => 'Gebühr',
                    2 => 'Fee',
                ],
                'quantity' => 1,
                'amount' => round($feeAmount * 100),
            ];
        }
        return $basket;
    }

    /**
     * @param array $basket
     * @return float
     */
    public static function getBasketAmount(array $basket): float
    {
        $basketAmount = 0;

        foreach ($basket as $product) {
            $amount = $product['amount'] / 100;
            $basketAmount += $product['quantity'] * $amount;
        }
        return floatval($basketAmount);
    }

    /** Append an adjustment line so the basket sums exactly to the order total (PP-20637). */
    public static function appendRoundingCorrection(array $basket, int $totalInCents): array
    {
        $basketInCents = (int) round(self::getBasketAmount($basket) * 100);
        $delta = $totalInCents - $basketInCents;
        if ($delta !== 0) {
            $basket[] = [
                'name' => [
                    1 => 'Anpassung',
                    2 => 'Adjustment',
                    3 => 'Ajustement',
                    4 => 'Adeguamento',
                ],
                'quantity' => 1,
                'amount' => $delta,
                'vatRate' => 0,
            ];
        }
        return $basket;
    }

    /**
     * @param array $basket
     * @return string
     */
    public static function createPurposeByBasket(array $basket): string
    {
        $desc = [];
        foreach ($basket as $product) {
            $desc[] = implode(' ', [
                is_array( $product['name'] ) ? $product['name'][2] : $product['name'],
                $product['quantity'],
                'x',
                number_format($product['amount'] / 100, 2, '.'),
            ]);
        }
        return implode('; ', $desc);
    }

    private static function get_product_description(array $item): string
    {
        $product = $item['data'];
        $description = strip_tags($item['data']->get_short_description());

        try {
            if ( $product->is_type( 'variation' ) ) {
                $item_meta = wc_get_formatted_cart_item_data( $item, true );
                if ( $item_meta ) {
                    $item_meta = trim(preg_replace('/\s+/', ' ', strip_tags($item_meta)));
                    return $description . " ( " . $item_meta . " )";
                }
            }
        } catch ( Exception $e ) {
        }

        return $description;
    }
}
