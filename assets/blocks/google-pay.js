const payrexx_google_pay_settings = window.wc.wcSettings.getSetting( 'payrexx_google-pay_data', {} );
const payrexx_google_pay_label = window.wp.htmlEntities.decodeEntities( payrexx_google_pay_settings.title ) || window.wp.i18n.__( 'Google Pay (Payrexx)', 'wc-payrexx-gateway' );
const PayrexxGooglePayContent = () => {
	return window.wp.htmlEntities.decodeEntities( payrexx_google_pay_settings.description || '' );
};
const Payrexx_GooglePay_Block_Gateway = {
	name: 'payrexx_google-pay',
	label: payrexx_google_pay_label,
	content: Object( window.wp.element.createElement )( PayrexxGooglePayContent, null ),
	edit: Object( window.wp.element.createElement )( PayrexxGooglePayContent, null ),
	canMakePayment: () => true,
	ariaLabel: payrexx_google_pay_label,
	supports: {
		features: payrexx_google_pay_settings.supports,
	},
};
window.wc.wcBlocksRegistry.registerPaymentMethod( Payrexx_GooglePay_Block_Gateway );
