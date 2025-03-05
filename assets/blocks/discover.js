const payrexx_discover_settings = window.wc.wcSettings.getSetting( 'payrexx_discover_data', {} );
const payrexx_discover_label = window.wp.htmlEntities.decodeEntities( payrexx_discover_settings.title ) || window.wp.i18n.__( 'Discover (Payrexx)', 'wc-payrexx-gateway' );
const PayrexxDiscoverContent = () => {
	return window.wp.element.createElement(
		window.wp.element.RawHTML,
		null,
		payrexx_discover_settings.description || ''
	);
};
const Payrexx_Discover_Block_Gateway = {
	name: 'payrexx_discover',
	label: window.wp.element.createElement(
		'span',
		{ style: { display: 'inline-flex', alignItems: 'center', gap: '8px' } },
		payrexx_discover_label,
		window.wp.element.createElement(
			window.wp.element.RawHTML,
			null,
			payrexx_discover_settings.icon || ''
		),
	),
	content: Object( window.wp.element.createElement )( PayrexxDiscoverContent, null ),
	edit: Object( window.wp.element.createElement )( PayrexxDiscoverContent, null ),
	canMakePayment: () => true,
	ariaLabel: payrexx_discover_label,
	supports: {
		features: payrexx_discover_settings.supports,
	},
};
window.wc.wcBlocksRegistry.registerPaymentMethod( Payrexx_Discover_Block_Gateway );
