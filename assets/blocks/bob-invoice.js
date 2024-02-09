const payrexx_bob_invoice_settings = window.wc.wcSettings.getSetting( 'payrexx_bob-invoice_data', {} );
const payrexx_bob_invoice_label = window.wp.htmlEntities.decodeEntities( payrexx_bob_invoice_settings.title ) || window.wp.i18n.__( 'Bob Invoice (Payrexx)', 'wc-payrexx-gateway' );
const PayrexxBobInvoiceContent = () => {
	return window.wp.htmlEntities.decodeEntities( payrexx_bob_invoice_settings.description || '' );
};
const Payrexx_BobInvoice_Block_Gateway = {
	name: 'payrexx_bob-invoice',
	label: payrexx_bob_invoice_label,
	content: Object( window.wp.element.createElement )( PayrexxBobInvoiceContent, null ),
	edit: Object( window.wp.element.createElement )( PayrexxBobInvoiceContent, null ),
	canMakePayment: () => true,
	ariaLabel: payrexx_bob_invoice_label,
	supports: {
		features: payrexx_bob_invoice_settings.supports,
	},
};
window.wc.wcBlocksRegistry.registerPaymentMethod( Payrexx_BobInvoice_Block_Gateway );
