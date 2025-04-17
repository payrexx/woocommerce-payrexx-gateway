registerPayrexxPaymentMethod = (id, defaultLabel ) => {
	const paymentMethodSettings = window.wc.wcSettings.getSetting( `${id}_data`, {} );
	if (!paymentMethodSettings || !paymentMethodSettings.title) {
		return;
	}
	const paymentMethodLabelText = window.wp.htmlEntities.decodeEntities( paymentMethodSettings.title ) || window.wp.i18n.__( defaultLabel, 'wc-payrexx-gateway' );

	const { useEffect } = window.wp.element;
	const ContentComponent = ( props ) => {
		const { id, eventRegistration, emitResponse } = props;
		const { onPaymentProcessing } = eventRegistration;
		useEffect( () => {
			const unsubscribe = onPaymentProcessing( async () => {
				const checkbox = document.getElementById('payrexx-allow-recurring-' + id);
				const payrexx_allow_recurring_block = checkbox && checkbox.checked ? 'payrexx-allow-recurring-' + id : 'no';
				return {
					type: emitResponse.responseTypes.SUCCESS,
					meta: {
						paymentMethodData: {
							payrexx_allow_recurring_block,
						},
					},
				};
			} );
			return () => {
				unsubscribe();
			};
		}, [
			emitResponse.responseTypes.ERROR,
			emitResponse.responseTypes.SUCCESS,
			onPaymentProcessing,
		] );
		return window.wp.element.createElement(
			window.wp.element.RawHTML,
			null,
			paymentMethodSettings.description || ''
		);
	}

	const paymentMethod = {
		name: id,
		label: window.wp.element.createElement(
			'span',
			{ style: { display: 'inline-flex', alignItems: 'center', gap: '8px' } },
			paymentMethodLabelText,
			window.wp.element.createElement(
				window.wp.element.RawHTML,
				null,
				paymentMethodSettings.icon || ''
			),
		),
		content: Object( window.wp.element.createElement )( ContentComponent, { id } ),
		edit: Object( window.wp.element.createElement )( ContentComponent, { id } ),
		canMakePayment: () => true,
		ariaLabel: paymentMethodLabelText,
		supports: {
			features: paymentMethodSettings.supports,
		},
	};

	window.wc.wcBlocksRegistry.registerPaymentMethod( paymentMethod );
}

registerPayrexxPaymentMethod( 'payrexx_american-express', 'Amex (Payrexx)' );
registerPayrexxPaymentMethod( 'payrexx_apple-pay', 'Apple Pay (Payrexx)' );
registerPayrexxPaymentMethod( 'payrexx_bank-transfer', 'Purchase on invoice' );
registerPayrexxPaymentMethod( 'payrexx_boncard', 'Boncard (Payrexx)' );
registerPayrexxPaymentMethod( 'payrexx_centi', 'Centi (Payrexx)' );
registerPayrexxPaymentMethod( 'payrexx_diners-club', 'Diners Club (Payrexx)' );
registerPayrexxPaymentMethod( 'payrexx_discover', 'Discover (Payrexx)' );
registerPayrexxPaymentMethod( 'payrexx_google-pay', 'Google Pay (Payrexx)' );
registerPayrexxPaymentMethod( 'payrexx_heidipay', 'Heidipay (Payrexx)' );
registerPayrexxPaymentMethod( 'payrexx_invoice', 'Bill (manual)' );
registerPayrexxPaymentMethod( 'payrexx_klarna', 'Klarna (Payrexx)' );
registerPayrexxPaymentMethod( 'payrexx_maestro', 'Maestro (Payrexx)' );
registerPayrexxPaymentMethod( 'payrexx_mastercard', 'Mastercard (Payrexx)' );
registerPayrexxPaymentMethod( 'payrexx_masterpass', 'Masterpass (Payrexx)' );
registerPayrexxPaymentMethod( 'payrexx_pay-by-bank', 'Pay by Bank (Payrexx)' );
registerPayrexxPaymentMethod( 'payrexx_paypal', 'Paypal (Payrexx)' );
registerPayrexxPaymentMethod( 'payrexx', 'Payrexx' );
registerPayrexxPaymentMethod( 'payrexx_post-finance-card', 'Post Finance Card (Payrexx)' );
registerPayrexxPaymentMethod( 'payrexx_post-finance-e-finance', 'Post Finance E-Finance (Payrexx)' );
registerPayrexxPaymentMethod( 'payrexx_post-finance-pay', 'Post Finance Pay (Payrexx)' );
registerPayrexxPaymentMethod( 'payrexx_reka', 'Reka (Payrexx)' );
registerPayrexxPaymentMethod( 'payrexx_samsung-pay', 'Samsung Pay (Payrexx)' );
registerPayrexxPaymentMethod( 'payrexx_twint', 'Twint (Payrexx)' );
registerPayrexxPaymentMethod( 'payrexx_visa', 'Visa (Payrexx)' );
registerPayrexxPaymentMethod( 'payrexx_wirpay', 'Wirpay (Payrexx)' );
registerPayrexxPaymentMethod( 'payrexx_x-money', 'xMoney (Payrexx)' );
registerPayrexxPaymentMethod( 'payrexx_powerpay', 'Powerpay (Payrexx)' );
