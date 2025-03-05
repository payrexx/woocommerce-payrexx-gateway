const payrexx_mastercard_settings = window.wc.wcSettings.getSetting( 'payrexx_mastercard_data', {} );
const payrexx_mastercard_label = window.wp.htmlEntities.decodeEntities( payrexx_mastercard_settings.title ) || window.wp.i18n.__( 'Mastercard (Payrexx)', 'wc-payrexx-gateway' );
const PayrexxMastercardContent = () => {
	return window.wp.element.createElement(
		window.wp.element.RawHTML,
		null,
		payrexx_mastercard_settings.description || ''
	);
};
const Payrexx_Mastercard_Block_Gateway = {
	name: 'payrexx_mastercard',
	label: window.wp.element.createElement(
		'span',
		{ style: { display: 'inline-flex', alignItems: 'center', gap: '8px' } },
		payrexx_mastercard_label,
		window.wp.element.createElement(
			window.wp.element.RawHTML,
			null,
			payrexx_mastercard_settings.icon || ''
		),
	),
	content: Object( window.wp.element.createElement )( PayrexxMastercardContent, null ),
	edit: Object( window.wp.element.createElement )( PayrexxMastercardContent, null ),
	canMakePayment: () => true,
	ariaLabel: payrexx_mastercard_label,
	supports: {
		features: payrexx_mastercard_settings.supports,
	},
};
window.wc.wcBlocksRegistry.registerPaymentMethod( Payrexx_Mastercard_Block_Gateway );
