(function ($) {
    'use strict';

    $(document).ready(function() {
        setTimeout(function() {
            checkApplePaySupport();
        }, 100);
    });

    $(document).on("DOMNodeInserted", '.woocommerce-checkout-payment, #payment-method', function(e) {
        checkApplePaySupport();
    });

    /**
     * Check the deive to support apple pay.
     */
    function checkApplePaySupport() {
        if ((window.ApplePaySession && ApplePaySession.canMakePayments()) !== true) {
            $('label[for$=payrexx_apple-pay], [class$=payrexx_apple-pay], [id$=payrexx_apple-pay]').hide();
        }
    }
}(jQuery));
