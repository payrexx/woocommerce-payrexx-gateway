const payrexx_amex_settings = window.wc.wcSettings.getSetting( 'payrexx_american-express_data', {} );
const payrexx_amex_label = window.wp.htmlEntities.decodeEntities( payrexx_amex_settings.title ) || window.wp.i18n.__( 'Amex (Payrexx)', 'wc-payrexx-gateway' )
const PayrexxAmexContent = () => {
	return window.wp.htmlEntities.decodeEntities( payrexx_amex_settings.description || '' );
};
const Payrexx_Amex_Block_Gateway = {
	name: 'payrexx_american-express',
	label: payrexx_amex_label,
	content: Object( window.wp.element.createElement )( PayrexxAmexContent, null ),
	edit: Object( window.wp.element.createElement )( PayrexxAmexContent, null ),
	canMakePayment: () => true,
	ariaLabel: payrexx_amex_label,
	supports: {
		features: payrexx_amex_settings.supports,
	},
};
window.wc.wcBlocksRegistry.registerPaymentMethod( Payrexx_Amex_Block_Gateway );
