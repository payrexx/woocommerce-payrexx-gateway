const payrexx_apple_pay_settings = window.wc.wcSettings.getSetting( 'payrexx_apple-pay_data', {} );
const payrexx_apple_pay_label = window.wp.htmlEntities.decodeEntities( payrexx_apple_pay_settings.title ) || window.wp.i18n.__( 'Apple Pay (Payrexx)', 'wc-payrexx-gateway' );
const PayrexxApplePayContent = () => {
	return window.wp.element.createElement(
		window.wp.element.RawHTML,
		null,
		payrexx_apple_pay_settings.description || ''
	);
};
const Payrexx_ApplePay_Block_Gateway = {
	name: 'payrexx_apple-pay',
	label: payrexx_apple_pay_label,
	content: Object( window.wp.element.createElement )( PayrexxApplePayContent, null ),
	edit: Object( window.wp.element.createElement )( PayrexxApplePayContent, null ),
	canMakePayment: () => true,
	ariaLabel: payrexx_apple_pay_label,
	supports: {
		features: payrexx_apple_pay_settings.supports,
	},
};
window.wc.wcBlocksRegistry.registerPaymentMethod( Payrexx_ApplePay_Block_Gateway );
