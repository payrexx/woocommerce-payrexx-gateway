const payrexx_settings = window.wc.wcSettings.getSetting( 'payrexx_data', {} );
const payrexx_label = window.wp.htmlEntities.decodeEntities( payrexx_settings.title );
const Content = () => {
    return window.wp.htmlEntities.decodeEntities( payrexx_settings.description || '' );
};
const Payrexx_Block_Gateway = {
    name: 'payrexx',
    label: payrexx_label,
    content: Object( window.wp.element.createElement )( Content, null ),
    edit: Object( window.wp.element.createElement )( Content, null ),
    canMakePayment: () => true,
    ariaLabel: payrexx_label,
    supports: {
        features: payrexx_settings.supports,
    },
};
window.wc.wcBlocksRegistry.registerPaymentMethod( Payrexx_Block_Gateway );
