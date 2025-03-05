const payrexx_maestro_settings = window.wc.wcSettings.getSetting( 'payrexx_maestro_data', {} );
const payrexx_maestro_label = window.wp.htmlEntities.decodeEntities( payrexx_maestro_settings.title ) || window.wp.i18n.__( 'Maestro (Payrexx)', 'wc-payrexx-gateway' );
const PayrexxMaestroContent = () => {
	return window.wp.element.createElement(
		window.wp.element.RawHTML,
		null,
		payrexx_maestro_settings.description || ''
	);
};
const Payrexx_Maestro_Block_Gateway = {
	name: 'payrexx_maestro',
	label: window.wp.element.createElement(
		'span',
		{ style: { display: 'inline-flex', alignItems: 'center', gap: '8px' } },
		payrexx_maestro_label,
		window.wp.element.createElement(
			window.wp.element.RawHTML,
			null,
			payrexx_maestro_settings.icon || ''
		),
	),
	content: Object( window.wp.element.createElement )( PayrexxMaestroContent, null ),
	edit: Object( window.wp.element.createElement )( PayrexxMaestroContent, null ),
	canMakePayment: () => true,
	ariaLabel: payrexx_maestro_label,
	supports: {
		features: payrexx_maestro_settings.supports,
	},
};
window.wc.wcBlocksRegistry.registerPaymentMethod( Payrexx_Maestro_Block_Gateway );
