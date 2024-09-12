const payrexx_discover_settings = window.wc.wcSettings.getSetting( 'payrexx_discover_data', {} );
const payrexx_discover_label = window.wp.htmlEntities.decodeEntities( payrexx_discover_settings.title ) || window.wp.i18n.__( 'Discover (Payrexx)', 'wc-payrexx-gateway' );
const PayrexxDiscoverContent = () => {
	return window.wp.htmlEntities.decodeEntities( payrexx_discover_settings.description || '' );
};
const Payrexx_Discover_Block_Gateway = {
	name: 'payrexx_discover',
	label: payrexx_discover_label,
	content: Object( window.wp.element.createElement )( PayrexxDiscoverContent, null ),
	edit: Object( window.wp.element.createElement )( PayrexxDiscoverContent, null ),
	canMakePayment: () => true,
	ariaLabel: payrexx_discover_label,
	supports: {
		features: payrexx_discover_settings.supports,
	},
};
window.wc.wcBlocksRegistry.registerPaymentMethod( Payrexx_Discover_Block_Gateway );
