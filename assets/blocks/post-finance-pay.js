const payrexx_post_finance_pay_settings = window.wc.wcSettings.getSetting( 'payrexx_post-finance-pay', {} );
const payrexx_post_finance_pay = window.wp.htmlEntities.decodeEntities( payrexx_post_finance_pay_settings.title ) || window.wp.i18n.__( 'Post Finance Pay (Payrexx)', 'wc-payrexx-gateway' );
const PayrexxPostFinancePayContent = () => {
	return window.wp.htmlEntities.decodeEntities( payrexx_post_finance_pay_settings.description || '' );
};
const Payrexx_PostFinancePay_Block_Gateway = {
	name: 'payrexx_post-finance-pay',
	label: payrexx_post_finance_pay,
	content: Object( window.wp.element.createElement )( PayrexxPostFinancePayContent, null ),
	edit: Object( window.wp.element.createElement )( PayrexxPostFinancePayContent, null ),
	canMakePayment: () => true,
	ariaLabel: payrexx_post_finance_pay,
	supports: {
		features: payrexx_post_finance_pay_settings.supports,
	},
};
window.wc.wcBlocksRegistry.registerPaymentMethod( Payrexx_PostFinancePay_Block_Gateway );
