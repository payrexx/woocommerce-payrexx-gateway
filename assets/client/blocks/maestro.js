const payrexx_maestro_settings = window.wc.wcSettings.getSetting( 'payrexx_maestro_data', {} );
const payrexx_maestro_label = window.wp.htmlEntities.decodeEntities( payrexx_maestro_settings.title );
const PayrexxMaestroContent = () => {
    return window.wp.htmlEntities.decodeEntities( payrexx_maestro_settings.description || '' );
};
const Payrexx_Maestro_Block_Gateway = {
    name: 'payrexx_maestro',
    label: payrexx_maestro_label,
    content: Object( window.wp.element.createElement )( PayrexxMaestroContent, null ),
    edit: Object( window.wp.element.createElement )( PayrexxMaestroContent, null ),
    canMakePayment: () => true,
    ariaLabel: payrexx_maestro_label,
    supports: {
        features: payrexx_maestro_settings.supports,
    },
};
window.wc.wcBlocksRegistry.registerPaymentMethod( Payrexx_Maestro_Block_Gateway );
