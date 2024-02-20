const payrexx_centi_settings = window.wc.wcSettings.getSetting( 'payrexx_centi_data', {} );
const payrexx_centi_label = window.wp.htmlEntities.decodeEntities( payrexx_centi_settings.title ) || window.wp.i18n.__( 'Centi (Payrexx)', 'wc-payrexx-gateway' );
const PayrexxCentiContent = () => {
	return window.wp.htmlEntities.decodeEntities( payrexx_centi_settings.description || '' );
};
const Payrexx_Centi_Block_Gateway = {
	name: 'payrexx_centi',
	label: payrexx_centi_label,
	content: Object( window.wp.element.createElement )( PayrexxCentiContent, null ),
	edit: Object( window.wp.element.createElement )( PayrexxCentiContent, null ),
	canMakePayment: () => true,
	ariaLabel: payrexx_centi_label,
	supports: {
		features: payrexx_centi_settings.supports,
	},
};
window.wc.wcBlocksRegistry.registerPaymentMethod( Payrexx_Centi_Block_Gateway );
