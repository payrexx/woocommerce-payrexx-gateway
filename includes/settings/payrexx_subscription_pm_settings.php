<?php
$settings = array();
if ( class_exists( '\WC_Subscriptions' ) ) {
	$settings = array(
		'subscriptions_enabled'   => array(
			'title'   => __( 'Enable/Disable Subscriptions', 'wc-payrexx-gateway' ),
			'type'    => 'checkbox',
			'label'   => __( 'Enable payment method for automatic subscription payments', 'wc-payrexx-gateway' ),
			'default' => 'yes',
		),
		'subscriptions_user_desc' => array(
			'title'       => __( 'Description Checkbox', 'wc-payrexx-gateway' ),
			'type'        => 'textarea',
			'css'         => 'width:400px;',
			'description' => __( 'This controls the description which the user sees besides the checkbox to activate recurring payments for a subscription. Checkbox shows only if the feature is active and a subscription is purchased.', 'woocommerce' ),
			'default'     => __( 'I accept that recurring payments will be charged to my credit card', 'wc-payrexx-gateway' ),
			'desc_tip'    => true,
		),
	);

	if ( 'payrexx' === $this->id ) {
		$settings['subscription_logos'] = array(
			'title'       => __( 'Recurring payment logo', 'wc-payrexx-gateway' ),
			'type'        => 'multiselect',
			'css'         => 'height: 100px;width:400px;',
			'description' => __( 'This controls the payment method logos for recurring payments.', 'wc-payrexx-gateway' ),
			'desc_tip'    => true,
			'options'     => array(
				'mastercard'           => 'Mastercard',
				'visa'                 => 'Visa',
				'american_express'     => 'American Express',
				'twint'                => 'TWINT',
				'post-finance-pay'     => 'Post Finance Pay',
				'post-finance-card'    =>  'PostFinance Card',
				'post-finance-e-finance' => 'PostFinance E-Finance',
			),
		);
	}
}
return apply_filters( 'wc_offline_form_fields', $settings );
