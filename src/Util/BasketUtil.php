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

            // Get VAT rate based on product tax class
            $tax_class = $item['data']->get_tax_class();
            $tax_rates = WC_Tax::get_rates( $tax_class );
            $tax_rate = !empty( $tax_rates ) ? reset( $tax_rates )['rate'] : 0;

            $basket[] = [
                'name' => self::get_product_name( $item ),
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
                'name' =>  [
                    1 => 'Versand',
                    2 => 'Shipping',
                ],
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
			// Calculate the VAT Rate based on discount amount and tax.
			$vatRate = $discountTax ? round( ( $discountTax / $discount ) * 100 ) : 0;

            $basket[] = [
                'name' => [
                    1 => 'Rabatt',
                    2 => 'Discount',
                ],
                'quantity' => 1,
                'amount' => round($discountAmount * -100),
				'vatRate' => $vatRate,
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

    private static function get_product_name(array $item): string
    {
        $product = $item['data'];
        $product_name = $product->get_name();

        try {
            if ( $product->is_type( 'variation' ) ) {
                $item_meta = wc_get_formatted_cart_item_data( $item, true );
                if ( $item_meta ) {
                    return $product_name . " ( " . $item_meta . " )";
                }
            }
        } catch ( Exception $e ) {
        }

        return $product_name;
    }
}
