const payrexx_klarna_settings = window.wc.wcSettings.getSetting( 'payrexx_klarna_data', {} );
const payrexx_klarna_label = window.wp.htmlEntities.decodeEntities( payrexx_klarna_settings.title ) || window.wp.i18n.__( 'Klarna (Payrexx)', 'wc-payrexx-gateway' );
const PayrexxKlarnaContent = () => {
	return window.wp.element.createElement(
		window.wp.element.RawHTML,
		null,
		payrexx_klarna_settings.description || ''
	);
};
const Payrexx_Klarna_Block_Gateway = {
	name: 'payrexx_klarna',
	label: window.wp.element.createElement(
		'span',
		{ style: { display: 'inline-flex', alignItems: 'center', gap: '8px' } },
		payrexx_klarna_label,
		window.wp.element.createElement(
			window.wp.element.RawHTML,
			null,
			payrexx_klarna_settings.icon || ''
		),
	),
	content: Object( window.wp.element.createElement )( PayrexxKlarnaContent, null ),
	edit: Object( window.wp.element.createElement )( PayrexxKlarnaContent, null ),
	canMakePayment: () => true,
	ariaLabel: payrexx_klarna_label,
	supports: {
		features: payrexx_klarna_settings.supports,
	},
};
window.wc.wcBlocksRegistry.registerPaymentMethod( Payrexx_Klarna_Block_Gateway );
