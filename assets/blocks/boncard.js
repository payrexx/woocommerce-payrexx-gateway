const payrexx_boncard_settings = window.wc.wcSettings.getSetting( 'payrexx_boncard_data', {} );
const payrexx_boncard_label = window.wp.htmlEntities.decodeEntities( payrexx_boncard_settings.title ) || window.wp.i18n.__( 'Boncard (Payrexx)', 'wc-payrexx-gateway' )
const PayrexxBorncardContent = () => {
	return window.wp.htmlEntities.decodeEntities( payrexx_boncard_settings.description || '' );
};
const Payrexx_Boncard_Block_Gateway = {
	name: 'payrexx_boncard',
	label: payrexx_boncard_label,
	content: Object( window.wp.element.createElement )( PayrexxBorncardContent, null ),
	edit: Object( window.wp.element.createElement )( PayrexxBorncardContent, null ),
	canMakePayment: () => true,
	ariaLabel: payrexx_boncard_label,
	supports: {
		features: payrexx_boncard_settings.supports,
	},
};
window.wc.wcBlocksRegistry.registerPaymentMethod( Payrexx_Boncard_Block_Gateway );
