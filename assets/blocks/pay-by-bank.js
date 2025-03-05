const payrexx_pay_by_bank_settings = window.wc.wcSettings.getSetting( 'payrexx_pay-by-bank_data', {} );
const payrexx_pay_by_bank_label = window.wp.htmlEntities.decodeEntities( payrexx_pay_by_bank_settings.title ) || window.wp.i18n.__( 'Pay by Bank (Payrexx)', 'wc-payrexx-gateway' );
const PayrexxPayByBankContent = () => {
	return window.wp.element.createElement(
		window.wp.element.RawHTML,
		null,
		payrexx_pay_by_bank_settings.description || ''
	);
};
const Payrexx_PayByBank_Block_Gateway = {
	name: 'payrexx_pay-by-bank',
	label: window.wp.element.createElement(
		'span',
		{ style: { display: 'inline-flex', alignItems: 'center', gap: '8px' } },
		payrexx_pay_by_bank_label,
		window.wp.element.createElement(
			window.wp.element.RawHTML,
			null,
			payrexx_pay_by_bank_settings.icon || ''
		),
	),
	content: Object( window.wp.element.createElement )( PayrexxPayByBankContent, null ),
	edit: Object( window.wp.element.createElement )( PayrexxPayByBankContent, null ),
	canMakePayment: () => true,
	ariaLabel: payrexx_pay_by_bank_label,
	supports: {
		features: payrexx_pay_by_bank_settings.supports,
	},
};
window.wc.wcBlocksRegistry.registerPaymentMethod( Payrexx_PayByBank_Block_Gateway );
