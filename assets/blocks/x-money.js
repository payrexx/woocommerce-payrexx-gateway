const payrexx_xmoney_settings = window.wc.wcSettings.getSetting( 'payrexx_x-money_data', {} );
const payrexx_xmoney_label = window.wp.htmlEntities.decodeEntities( payrexx_xmoney_settings.title ) || window.wp.i18n.__( 'xMoney (Payrexx)', 'wc-payrexx-gateway' );
const PayrexxXmoneyContent = () => {
	return window.wp.htmlEntities.decodeEntities( payrexx_xmoney_settings.description || '' );
};
const Payrexx_Xmoney_Block_Gateway = {
	name: 'payrexx_x-money',
	label: payrexx_xmoney_label,
	content: Object( window.wp.element.createElement )( PayrexxXmoneyContent, null ),
	edit: Object( window.wp.element.createElement )( PayrexxXmoneyContent, null ),
	canMakePayment: () => true,
	ariaLabel: payrexx_xmoney_label,
	supports: {
		features: payrexx_xmoney_settings.supports,
	},
};
window.wc.wcBlocksRegistry.registerPaymentMethod( Payrexx_Xmoney_Block_Gateway );
