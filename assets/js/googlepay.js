(function ($) {
    'use strict';

    $(document).ready(function() {
        setTimeout(function() {
            checkGooglePaySupport();
        }, 100);
    });

    $(document).on("DOMNodeInserted", '.woocommerce-checkout-payment, #payment-method', function(e) {
        checkGooglePaySupport();
    });

    /**
     * Check the deive to support google pay.
     */
    function checkGooglePaySupport() {
        $('label[for$=payrexx_google-pay], [class$=payrexx_google-pay], [id$=payrexx_google-pay]').hide();
        try {
            const baseRequest = {
                apiVersion: 2,
                apiVersionMinor: 0
            };
            const allowedCardNetworks = ['MASTERCARD', 'VISA'];
            const allowedCardAuthMethods = ['CRYPTOGRAM_3DS'];
            const baseCardPaymentMethod = {
                type: 'CARD',
                parameters: {
                    allowedAuthMethods: allowedCardAuthMethods,
                    allowedCardNetworks: allowedCardNetworks
                }
            };

            const isReadyToPayRequest = Object.assign({}, baseRequest);
            isReadyToPayRequest.allowedPaymentMethods = [
                baseCardPaymentMethod
            ];
            const paymentsClient = new google.payments.api.PaymentsClient(
                {
                    environment: 'TEST'
                }
            );
            paymentsClient.isReadyToPay(isReadyToPayRequest).then(function(response) {
                if (response.result) {
                    $('label[for$=payrexx_google-pay], [class$=payrexx_google-pay], [id$=payrexx_google-pay]').show();
                }
            }).catch(function(err) {
                console.log(err);
            });
        } catch (err) {
            console.log(err);
        }
    }
}(jQuery));
