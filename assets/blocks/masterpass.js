const payrexx_masterpass_settings = window.wc.wcSettings.getSetting( 'payrexx_masterpass_data', {} );
const payrexx_masterpass_label = window.wp.htmlEntities.decodeEntities( payrexx_masterpass_settings.title ) || window.wp.i18n.__( 'Masterpass (Payrexx)', 'wc-payrexx-gateway' );
const PayrexxMasterpassContent = () => {
	return window.wp.element.createElement(
		window.wp.element.RawHTML,
		null,
		payrexx_masterpass_settings.description || ''
	);
};
const Payrexx_Masterpass_Block_Gateway = {
	name: 'payrexx_masterpass',
	label: window.wp.element.createElement(
		'span',
		{ style: { display: 'inline-flex', alignItems: 'center', gap: '8px' } },
		payrexx_masterpass_label,
		window.wp.element.createElement(
			window.wp.element.RawHTML,
			null,
			payrexx_masterpass_settings.icon || ''
		),
	),
	content: Object( window.wp.element.createElement )( PayrexxMasterpassContent, null ),
	edit: Object( window.wp.element.createElement )( PayrexxMasterpassContent, null ),
	canMakePayment: () => true,
	ariaLabel: payrexx_masterpass_label,
	supports: {
		features: payrexx_masterpass_settings.supports,
	},
};
window.wc.wcBlocksRegistry.registerPaymentMethod( Payrexx_Masterpass_Block_Gateway );
