const payrexx_post_finance_card_settings = window.wc.wcSettings.getSetting( 'payrexx_post-finance-card_data', {} );
const payrexx_post_finance_card_label = window.wp.htmlEntities.decodeEntities( payrexx_post_finance_card_settings.title ) || window.wp.i18n.__( 'Post Finance Card (Payrexx)', 'wc-payrexx-gateway' );
const PayrexxPostFinancecardContent = () => {
	return window.wp.element.createElement(
		window.wp.element.RawHTML,
		null,
		payrexx_post_finance_card_settings.description || ''
	);
};
const Payrexx_PostFinancecard_Block_Gateway = {
	name: 'payrexx_post-finance-card',
	label: window.wp.element.createElement(
		'span',
		{ style: { display: 'inline-flex', alignItems: 'center', gap: '8px' } },
		payrexx_post_finance_card_label,
		window.wp.element.createElement(
			window.wp.element.RawHTML,
			null,
			payrexx_post_finance_card_settings.icon || ''
		),
	),
	content: Object( window.wp.element.createElement )( PayrexxPostFinancecardContent, null ),
	edit: Object( window.wp.element.createElement )( PayrexxPostFinancecardContent, null ),
	canMakePayment: () => true,
	ariaLabel: payrexx_post_finance_card_label,
	supports: {
		features: payrexx_post_finance_card_settings.supports,
	},
};
window.wc.wcBlocksRegistry.registerPaymentMethod( Payrexx_PostFinancecard_Block_Gateway );
