function registerPayrexxPaymentMethod(paymentMethodKey, defaultLabel) {
    var payrexx_payment_settings = window.wc.wcSettings.getSetting( paymentMethodKey, {} );
    var payrexx_payment_label = window.wp.htmlEntities.decodeEntities( payrexx_payment_settings.title ) || window.wp.i18n.__( defaultLabel, 'wc-payrexx-gateway' );
    var payrexx_description = () => {
        return window.wp.element.createElement(
            window.wp.element.RawHTML,
            null,
            payrexx_payment_settings.description || ''
        );
    };
    var Payrexx_Block_Gateway = {
        name: 'payrexx_mastercard',
        label: window.wp.element.createElement(
            'span',
            { style: { display: 'inline-flex', alignItems: 'center', gap: '8px' } },
            payrexx_payment_label,
            window.wp.element.createElement(
                window.wp.element.RawHTML,
                null,
                payrexx_payment_settings.icon || ''
            ),
        ),
        content: Object( window.wp.element.createElement )( payrexx_description, null ),
        edit: Object( window.wp.element.createElement )( payrexx_description, null ),
        canMakePayment: () => true,
        ariaLabel: payrexx_payment_label,
        supports: {
            features: payrexx_payment_settings.supports,
        },
        
    };
    window.wc.wcBlocksRegistry.registerPaymentMethod( Payrexx_Block_Gateway );
}
