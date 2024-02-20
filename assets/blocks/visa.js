const payrexx_visa_settings = window.wc.wcSettings.getSetting( 'payrexx_visa_data', {} );
const payrexx_visa_label = window.wp.htmlEntities.decodeEntities( payrexx_visa_settings.title ) || window.wp.i18n.__( 'Visa (Payrexx)', 'wc-payrexx-gateway' );
const PayrexxVisaContent = () => {
	return window.wp.htmlEntities.decodeEntities( payrexx_visa_settings.description || '' );
};
const Payrexx_Visa_Block_Gateway = {
	name: 'payrexx_visa',
	label: payrexx_visa_label,
	content: Object( window.wp.element.createElement )( PayrexxVisaContent, null ),
	edit: Object( window.wp.element.createElement )( PayrexxVisaContent, null ),
	canMakePayment: () => true,
	ariaLabel: payrexx_visa_label,
	supports: {
		features: payrexx_visa_settings.supports,
	},
};
window.wc.wcBlocksRegistry.registerPaymentMethod( Payrexx_Visa_Block_Gateway );
