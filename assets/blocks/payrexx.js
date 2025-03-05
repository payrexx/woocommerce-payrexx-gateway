const payrexx_settings = window.wc.wcSettings.getSetting( 'payrexx_data', {} );
const payrexx_label = window.wp.htmlEntities.decodeEntities( payrexx_settings.title ) || window.wp.i18n.__( 'Payrexx', 'wc-payrexx-gateway' );
const Content = () => {
	return window.wp.element.createElement(
		window.wp.element.RawHTML,
		null,
		payrexx_settings.description || ''
	);
};
const Payrexx_Block_Gateway = {
	name: 'payrexx',
	label: window.wp.element.createElement(
		'span',
		{ style: { display: 'inline-flex', alignItems: 'center', gap: '8px' } },
		payrexx_label,
		window.wp.element.createElement(
			window.wp.element.RawHTML,
			null,
			payrexx_settings.icon || ''
		),
	),
	content: Object( window.wp.element.createElement )( Content, null ),
	edit: Object( window.wp.element.createElement )( Content, null ),
	canMakePayment: () => true,
	ariaLabel: payrexx_label,
	supports: {
		features: payrexx_settings.supports,
	},
};
window.wc.wcBlocksRegistry.registerPaymentMethod( Payrexx_Block_Gateway );
