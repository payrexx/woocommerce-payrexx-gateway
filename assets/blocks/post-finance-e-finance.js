const payrexx_post_finance_e_finance_settings = window.wc.wcSettings.getSetting( 'payrexx_post-finance-e-finance_data', {} );
const payrexx_post_finance_e_finance_label = window.wp.htmlEntities.decodeEntities( payrexx_post_finance_e_finance_settings.title ) || window.wp.i18n.__( 'Post Finance E-Finance (Payrexx)', 'wc-payrexx-gateway' );
const PayrexxPostFinanceEFinanceContent = () => {
	return window.wp.htmlEntities.decodeEntities( payrexx_post_finance_e_finance_settings.description || '' );
};
const Payrexx_PostFinanceEFinance_Block_Gateway = {
	name: 'payrexx_post-finance-e-finance',
	label: payrexx_post_finance_e_finance_label,
	content: Object( window.wp.element.createElement )( PayrexxPostFinanceEFinanceContent, null ),
	edit: Object( window.wp.element.createElement )( PayrexxPostFinanceEFinanceContent, null ),
	canMakePayment: () => true,
	ariaLabel: payrexx_post_finance_e_finance_label,
	supports: {
		features: payrexx_post_finance_e_finance_settings.supports,
	},
};
window.wc.wcBlocksRegistry.registerPaymentMethod( Payrexx_PostFinanceEFinance_Block_Gateway );
