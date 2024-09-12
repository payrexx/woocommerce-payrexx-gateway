const payrexx_diners_club_settings = window.wc.wcSettings.getSetting( 'payrexx_diners-club_data', {} );
const payrexx_diners_club_label = window.wp.htmlEntities.decodeEntities( payrexx_diners_club_settings.title ) || window.wp.i18n.__( 'Diners Club (Payrexx)', 'wc-payrexx-gateway' );
const PayrexxDinersClubContent = () => {
	return window.wp.htmlEntities.decodeEntities( payrexx_diners_club_settings.description || '' );
};
const Payrexx_DinersClub_Block_Gateway = {
	name: 'payrexx_diners-club',
	label: payrexx_diners_club_label,
	content: Object( window.wp.element.createElement )( PayrexxDinersClubContent, null ),
	edit: Object( window.wp.element.createElement )( PayrexxDinersClubContent, null ),
	canMakePayment: () => true,
	ariaLabel: payrexx_diners_club_label,
	supports: {
		features: payrexx_diners_club_settings.supports,
	},
};
window.wc.wcBlocksRegistry.registerPaymentMethod( Payrexx_DinersClub_Block_Gateway );
