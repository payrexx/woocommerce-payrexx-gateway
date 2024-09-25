<?php

namespace PayrexxPaymentGateway\Util;

use WC_Tax;

class BasketUtil
{

    /**
     * @param $cart
     * @return array
     */
    public static function createBasketByCart($cart): array
    {
        $productPriceIncludesTax = ('yes' === get_option( 'woocommerce_prices_include_tax'));

        $cartItems = $cart->get_cart();
        $basket = [];

        foreach ($cartItems as $item) {
            // Product
            $productId = $item['data']->get_id();
            $amount = $item['data']->get_sale_price() ?: $item['data']->get_price();

            // In case of subscription the sign up fee maybe should be added
            if (class_exists('\WC_Subscriptions') && \WC_Subscriptions_Product::is_subscription($productId)) {
                $amount += \WC_Subscriptions_Product::get_sign_up_fee($productId);

                // With a trial period the original price is not immediately charged
                if (\WC_Subscriptions_Product::get_trial_length($productId)) {
                    $amount -= ($item['data']->get_sale_price() ?: $item['data']->get_price());
                }
            }

            if ( ! $amount ) {
                $amount = 0;
            }

			$taxPerProduct = ( ( $item['line_subtotal_tax'] * 100 ) / $item['quantity'] ) / 100;
			if ( ! $productPriceIncludesTax ) {
				$amount += $taxPerProduct;
			}

            $amount += $productPriceIncludesTax ? 0 : $item['line_subtotal_tax'];
            // Get VAT rate based on product tax class
            $tax_class = $item['data']->get_tax_class();
            $tax_rates = WC_Tax::get_rates( $tax_class );
            $tax_rate = !empty( $tax_rates ) ? reset( $tax_rates )['rate'] : 0;

            $basket[] = [
                'name' => $item['data']->get_title(),
                'description' => strip_tags($item['data']->get_short_description()),
                'quantity' => $item['quantity'],
                'amount' => round($amount * 100),
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
                'name' => 'Shipping',
                'quantity' => 1,
                'amount' => round( $shippingAmount * 100 ),
                'vatRate' => $shippingTaxPercentage,
            ];
        }

        // Discount
        $discount = $cart->get_discount_total();
        $discountTax = $cart->get_discount_tax();
        if ($discount) {
            $discountAmount = $discount;
            $discountAmount += $productPriceIncludesTax ? 0 : $discountTax;
            $basket[] = [
                'name' => 'Discount',
                'quantity' => 1,
                'amount' => round($discountAmount * -100),
            ];
        }

        // Fee
        $fee = $cart->get_fee_total();
        $feeTax = $cart->get_fee_tax();
        if ($fee) {
            $feeAmount = $fee;
            $feeAmount += $productPriceIncludesTax ? 0 : $feeTax;
            $basket[] = [
                'name' => 'Fee',
                'quantity' => 1,
                'amount' => round($feeAmount * 100),
            ];
        }

        return $basket;
    }

    /**
     * @param $basket
     * @return float
     */
    public static function getBasketAmount($basket): float
    {
        $basketAmount = 0;

        foreach ($basket as $product) {
            $amount = $product['amount'] / 100;
            $basketAmount += $product['quantity'] * $amount;
        }
        return floatval($basketAmount);
    }

    /**
     * @param $basket
     * @return string
     */
    public static function createPurposeByBasket($basket): string
    {
        $desc = [];
        foreach ($basket as $product) {
            $desc[] = implode(' ', [
                $product['name'],
                $product['quantity'],
                'x',
                number_format($product['amount'] / 100, 2, '.'),
            ]);
        }
        return implode('; ', $desc);
    }
}