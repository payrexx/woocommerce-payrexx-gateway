const payrexx_wirpay_settings = window.wc.wcSettings.getSetting( 'payrexx_wirpay_data', {} );
const payrexx_wirpay_label = window.wp.htmlEntities.decodeEntities( payrexx_wirpay_settings.title );
const PayrexxWirpayContent = () => {
    return window.wp.htmlEntities.decodeEntities( payrexx_wirpay_settings.description || '' );
};
const Payrexx_Wirpay_Block_Gateway = {
    name: 'payrexx_wirpay',
    label: payrexx_wirpay_label,
    content: Object( window.wp.element.createElement )( PayrexxWirpayContent, null ),
    edit: Object( window.wp.element.createElement )( PayrexxWirpayContent, null ),
    canMakePayment: () => true,
    ariaLabel: payrexx_wirpay_label,
    supports: {
        features: payrexx_wirpay_settings.supports,
    },
};
window.wc.wcBlocksRegistry.registerPaymentMethod( Payrexx_Wirpay_Block_Gateway );
