const payrexx_reka_settings = window.wc.wcSettings.getSetting( 'payrexx_reka_data', {} );
const payrexx_reka_label = window.wp.htmlEntities.decodeEntities( payrexx_reka_settings.title );
const PayrexxRekaContent = () => {
    return window.wp.htmlEntities.decodeEntities( payrexx_reka_settings.description || '' );
};
const Payrexx_Reka_Block_Gateway = {
    name: 'payrexx_reka',
    label: payrexx_reka_label,
    content: Object( window.wp.element.createElement )( PayrexxRekaContent, null ),
    edit: Object( window.wp.element.createElement )( PayrexxRekaContent, null ),
    canMakePayment: () => true,
    ariaLabel: payrexx_reka_label,
    supports: {
        features: payrexx_reka_settings.supports,
    },
};
window.wc.wcBlocksRegistry.registerPaymentMethod( Payrexx_Reka_Block_Gateway );
