const payrexx_samsung_pay_settings = window.wc.wcSettings.getSetting( 'payrexx_samsung-pay_data', {} );
const payrexx_samsung_pay_label = window.wp.htmlEntities.decodeEntities( payrexx_samsung_pay_settings.title ) || window.wp.i18n.__( 'Samsung Pay (Payrexx)', 'wc-payrexx-gateway' );
const PayrexxSamsungPayContent = () => {
	return window.wp.element.createElement(
		window.wp.element.RawHTML,
		null,
		payrexx_samsung_pay_settings.description || ''
	);
};
const Payrexx_SamsungPay_Block_Gateway = {
	name: 'payrexx_samsung-pay',
	label: payrexx_samsung_pay_label,
	content: Object( window.wp.element.createElement )( PayrexxSamsungPayContent, null ),
	edit: Object( window.wp.element.createElement )( PayrexxSamsungPayContent, null ),
	canMakePayment: () => true,
	ariaLabel: payrexx_samsung_pay_label,
	supports: {
		features: payrexx_samsung_pay_settings.supports,
	},
};
window.wc.wcBlocksRegistry.registerPaymentMethod( Payrexx_SamsungPay_Block_Gateway );
