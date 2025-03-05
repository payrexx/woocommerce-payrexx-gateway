const payrexx_paypal_settings = window.wc.wcSettings.getSetting( 'payrexx_paypal_data', {} );
const payrexx_paypal_label = window.wp.htmlEntities.decodeEntities( payrexx_paypal_settings.title ) || window.wp.i18n.__( 'Paypal (Payrexx)', 'wc-payrexx-gateway' );
const PayrexxPaypalContent = () => {
	return window.wp.element.createElement(
		window.wp.element.RawHTML,
		null,
		payrexx_paypal_settings.description || ''
	);
};
const Payrexx_Paypal_Block_Gateway = {
	name: 'payrexx_paypal',
	label: window.wp.element.createElement(
		'span',
		{ style: { display: 'inline-flex', alignItems: 'center', gap: '8px' } },
		payrexx_paypal_label,
		window.wp.element.createElement(
			window.wp.element.RawHTML,
			null,
			payrexx_paypal_settings.icon || ''
		),
	),
	content: Object( window.wp.element.createElement )( PayrexxPaypalContent, null ),
	edit: Object( window.wp.element.createElement )( PayrexxPaypalContent, null ),
	canMakePayment: () => true,
	ariaLabel: payrexx_paypal_label,
	supports: {
		features: payrexx_paypal_settings.supports,
	},
};
window.wc.wcBlocksRegistry.registerPaymentMethod( Payrexx_Paypal_Block_Gateway );
