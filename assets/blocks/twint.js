const payrexx_twint_settings = window.wc.wcSettings.getSetting( 'payrexx_twint_data', {} );
const payrexx_twint_label = window.wp.htmlEntities.decodeEntities( payrexx_twint_settings.title ) || window.wp.i18n.__( 'Twint (Payrexx)', 'wc-payrexx-gateway' );
const PayrexxTwintContent = () => {
	return window.wp.htmlEntities.decodeEntities( payrexx_twint_settings.description || '' );
};
const Payrexx_Twint_Block_Gateway = {
	name: 'payrexx_twint',
	label: payrexx_twint_label,
	content: Object( window.wp.element.createElement )( PayrexxTwintContent, null ),
	edit: Object( window.wp.element.createElement )( PayrexxTwintContent, null ),
	canMakePayment: () => true,
	ariaLabel: payrexx_twint_label,
	supports: {
		features: payrexx_twint_settings.supports,
	},
};
window.wc.wcBlocksRegistry.registerPaymentMethod( Payrexx_Twint_Block_Gateway );
