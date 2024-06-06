(function ($) {
    'use strict';

    $(document).ready(function() {
        console.log('google pay');
        checkApplePaySupport();
    });

    /**
     * Check the deive to support apple pay.
     */
    function checkApplePaySupport() {
        if ((window.ApplePaySession && ApplePaySession.canMakePayments()) !== true) {
            $('label[for$=payrexx_apple-pay]').hide();
        }
    }
}(jQuery));
