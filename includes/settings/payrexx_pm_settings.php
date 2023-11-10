<?php

$settings = [
    'enabled' => [
        'title' => __('Enable/Disable', 'wc-payrexx-gateway'),
        'type' => 'checkbox',
        'label' => __('Enable Payment Method', 'wc-payrexx-gateway'),
        'default' => 'no',
    ],
    'title' => [
        'title' => __('Title', 'wc-payrexx-gateway'),
        'type' => 'text',
        'description' => __('The title of the payment method which shows in the checkout.', 'wc-payrexx-gateway'),
        'default' => __($this->method_title),
        'desc_tip' => true,
        'custom_attributes' => ['required' => 'required'],
    ],
    'description' => [
        'title' => __('Description', 'wc-payrexx-gateway'),
        'type' => 'textarea',
        'css' => 'width:400px;',
        'description' => __('The description of the paymment method which is visible in the checkout once selected.', 'wc-payrexx-gateway'),
        'desc_tip' => true,
    ],
];

if ($this->id === 'payrexx') {
    $settings['logos'] = [
        'title' => __('Select Logo', 'wc-payrexx-gateway'),
        'type' => 'multiselect',
        'css' => 'height: 400px;width:400px;',
        'description' => __('This controls the payment method logos the customer sees during checkout.', 'wc-payrexx-gateway'),
        'default' => __(get_option("woocommerce_payrexx_logos"), 'wc-payrexx-gateway'),
        'desc_tip' => true,
        'options' => [
            'masterpass' => 'Masterpass',
            'mastercard' => 'Mastercard',
            'visa' => 'Visa',
            'apple_pay' => 'Apple Pay',
            'maestro' => 'Maestro',
            'jcb' => 'JCB',
            'american_express' => 'American Express',
            'wirpay' => 'WIRpay',
            'paypal' => 'PayPal',
            'bitcoin' => 'Bitcoin',
            'sofortueberweisung_de' => 'Sofort Überweisung',
            'airplus' => 'Airplus',
            'billpay' => 'Billpay',
            'bonuscard' => 'Bonus card',
            'cashu' => 'CashU',
            'cb' => 'Carte Bleue',
            'diners_club' => 'Diners Club',
            'direct_debit' => 'Direct Debit',
            'discover' => 'Discover',
            'elv' => 'ELV',
            'ideal' => 'iDEAL',
            'invoice' => 'Invoice',
            'myone' => 'My One',
            'paysafecard' => 'Paysafe Card',
            'postfinance_card' => 'PostFinance Card',
            'postfinance_efinance' => 'PostFinance E-Finance',
            'swissbilling' => 'SwissBilling',
            'twint' => 'TWINT',
            'barzahlen' => 'Barzahlen/Viacash',
            'bancontact' => 'Bancontact',
            'giropay' => 'GiroPay',
            'eps' => 'EPS',
            'google_pay' => 'Google Pay',
            'antepay' => 'AntePay',
            'paysafecash' => 'Paysafes Cash',
            'bob-invoice' => 'Kauf auf Rechnung (Bob)',
            'samsung_pay' => 'Samsung Pay',
            'klarna_paynow' => 'Klarna Pay nowe',
            'klarna_paylater' => 'Klarna Pay Later',
            'oney' => 'Oney',
            'gecko-card' => 'Gecko Card',
            'reka' => 'Reka',
        ]
    ];
}

return apply_filters('wc_offline_form_fields', $settings);
