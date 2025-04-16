const payrexx_powerpay_settings = window.wc.wcSettings.getSetting( 'payrexx_powerpay_data', {} );
const payrexx_powerpay_label = window.wp.htmlEntities.decodeEntities( payrexx_powerpay_settings.title ) || window.wp.i18n.__( 'Powerpay (Payrexx)', 'wc-payrexx-gateway' );
const PayrexxPowerpayContent = () => {
	return window.wp.htmlEntities.decodeEntities( payrexx_powerpay_settings.description || '' );
};
const Payrexx_Powerpay_Block_Gateway = {
	name: 'payrexx_powerpay',
	label: payrexx_powerpay_label,
	content: Object( window.wp.element.createElement )( PayrexxPowerpayContent, null ),
	edit: Object( window.wp.element.createElement )( PayrexxPowerpayContent, null ),
	canMakePayment: () => true,
	ariaLabel: payrexx_powerpay_label,
	supports: {
		features: payrexx_powerpay_settings.supports,
	},
};
window.wc.wcBlocksRegistry.registerPaymentMethod( Payrexx_Powerpay_Block_Gateway );
