const payrexx_heidipay_settings = window.wc.wcSettings.getSetting( 'payrexx_heidipay_data', {} );
const payrexx_heidipay_label = window.wp.htmlEntities.decodeEntities( payrexx_heidipay_settings.title ) || window.wp.i18n.__( 'Heidipay (Payrexx)', 'wc-payrexx-gateway' );
const PayrexxHeidipayContent = () => {
	return window.wp.element.createElement(
		window.wp.element.RawHTML,
		null,
		payrexx_heidipay_settings.description || ''
	);
};
const Payrexx_Heidipay_Block_Gateway = {
	name: 'payrexx_heidipay',
	label: window.wp.element.createElement(
		'span',
		{ style: { display: 'inline-flex', alignItems: 'center', gap: '8px' } },
		payrexx_heidipay_label,
		window.wp.element.createElement(
			window.wp.element.RawHTML,
			null,
			payrexx_heidipay_settings.icon || ''
		),
	),
	content: Object( window.wp.element.createElement )( PayrexxHeidipayContent, null ),
	edit: Object( window.wp.element.createElement )( PayrexxHeidipayContent, null ),
	canMakePayment: () => true,
	ariaLabel: payrexx_heidipay_label,
	supports: {
		features: payrexx_heidipay_settings.supports,
	},
};
window.wc.wcBlocksRegistry.registerPaymentMethod( Payrexx_Heidipay_Block_Gateway );
