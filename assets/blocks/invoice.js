const payrexx_invoice_settings = window.wc.wcSettings.getSetting( 'payrexx_invoice_data', {} );
const payrexx_invoice_label = window.wp.htmlEntities.decodeEntities( payrexx_invoice_settings.title ) || window.wp.i18n.__( 'Invoice (Payrexx)', 'wc-payrexx-gateway' );
const PayrexxInvoiceContent = () => {
	return window.wp.htmlEntities.decodeEntities( payrexx_invoice_settings.description || '' );
};
const Payrexx_Invoice_Block_Gateway = {
	name: 'payrexx_invoice',
	label: payrexx_invoice_label,
	content: Object( window.wp.element.createElement )( PayrexxInvoiceContent, null ),
	edit: Object( window.wp.element.createElement )( PayrexxInvoiceContent, null ),
	canMakePayment: () => true,
	ariaLabel: payrexx_invoice_label,
	supports: {
		features: payrexx_invoice_settings.supports,
	},
};
window.wc.wcBlocksRegistry.registerPaymentMethod( Payrexx_Invoice_Block_Gateway );
