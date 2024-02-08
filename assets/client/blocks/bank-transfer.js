const payrexx_bank_transfer_settings = window.wc.wcSettings.getSetting( 'payrexx_bank-transfer_data', {} );
const payrexx_bank_transfer_label = window.wp.htmlEntities.decodeEntities( payrexx_bank-transfer_settings.title );
const PayrexxBankTransferContent = () => {
    return window.wp.htmlEntities.decodeEntities( payrexx_bank-transfer_settings.description || '' );
};
const Payrexx_BankTransfer_Block_Gateway = {
    name: 'payrexx_bank-transfer',
    label: payrexx_bank_transfer_label,
    content: Object( window.wp.element.createElement )( PayrexxBankTransferContent, null ),
    edit: Object( window.wp.element.createElement )( PayrexxBankTransferContent, null ),
    canMakePayment: () => true,
    ariaLabel: payrexx_bank_transfer_label,
    supports: {
        features: payrexx_bank_transfer_settings.supports,
    },
};
window.wc.wcBlocksRegistry.registerPaymentMethod( Payrexx_BankTransfer_Block_Gateway );
