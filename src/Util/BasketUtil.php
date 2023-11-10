<?php

namespace PayrexxPaymentGateway\Util;

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

            $basket[] = [
                'name' => $item['data']->get_title(),
                'description' => strip_tags($item['data']->get_short_description()),
                'quantity' => $item['quantity'],
                'amount' => round($amount * 100),
                'sku' => $item['data']->get_sku(),
            ];
        }

        $shipping = $cart->get_shipping_total();
        $shippingTax = $cart->get_shipping_tax();
        if ($shipping || $shippingTax) {
            $shippingAmount = round($shipping + $shippingTax, 2);
            $basket[] = [
                'name' => 'Shipping',
                'quantity' => 1,
                'amount' => round($shippingAmount * 100),
            ];
        }

        $discount = $cart->get_discount_total();
        $discountTax = $cart->get_discount_tax();
        if ($discount) {
            $discountAmount = $discount;
            $discountAmount += !$productPriceIncludesTax ? 0 : $discountTax;
            $basket[] = [
                'name' => 'Discount',
                'quantity' => 1,
                'amount' => round($discountAmount * -100),
            ];
        }

        $fee = $cart->get_fee_total();
        $feeTax = $cart->get_fee_tax();
        if ($fee) {
            $feeAmount = $fee;
            $feeAmount += !$productPriceIncludesTax ? 0 : $feeTax;
            $basket[] = [
                'name' => 'Fee',
                'quantity' => 1,
                'amount' => round($feeAmount * 100),
            ];
        }

        $taxAmount = $cart->get_cart_contents_tax();
        if ($taxAmount && !$productPriceIncludesTax) {
            $basket[] = [
                'name' => 'Tax',
                'quantity' => 1,
                'amount' => round($taxAmount * 100),
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