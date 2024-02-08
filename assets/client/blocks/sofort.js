const payrexx_sofort_settings = window.wc.wcSettings.getSetting( 'payrexx_sofort_data', {} );
const payrexx_sofort_label = window.wp.htmlEntities.decodeEntities( payrexx_sofort_settings.title );
const PayrexxSofortContent = () => {
    return window.wp.htmlEntities.decodeEntities( payrexx_sofort_settings.description || '' );
};
const Payrexx_Sofort_Block_Gateway = {
    name: 'payrexx_sofort',
    label: payrexx_sofort_label,
    content: Object( window.wp.element.createElement )( PayrexxSofortContent, null ),
    edit: Object( window.wp.element.createElement )( PayrexxSofortContent, null ),
    canMakePayment: () => true,
    ariaLabel: payrexx_sofort_label,
    supports: {
        features: payrexx_sofort_settings.supports,
    },
};
window.wc.wcBlocksRegistry.registerPaymentMethod( Payrexx_Sofort_Block_Gateway );
