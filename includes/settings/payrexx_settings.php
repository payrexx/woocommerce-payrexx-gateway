<?php

return apply_filters('wc_payrexx_gateway_settings', [
        [
            'title' => __('API Settings', 'wc-payrexx-gateway'),
            'id' => PAYREXX_CONFIGS_PREFIX . 'api_settings',
            'type' => 'title',
            'desc' => __('Submit your Payrexx API credentials to connect WooCommerce with your Payrexx account'),
        ],
        [
            'title' => __('Choose Your Platform', 'wc-payrexx-gateway'),
            'id' => PAYREXX_CONFIGS_PREFIX . 'platform',
            'type' => 'select',
            'default' => 'payrexx.com',
            'desc' => '',
            'css' => '',
            'options' => [
                'payrexx.com' => 'Payrexx',
                'zahls.ch' => 'Zahls',
                'spenden-grunliberale.ch' => 'Grünliberale Schweiz',
                'deinshop.online' => 'Deinshop.online',
                'swissbrain-pay.ch' => 'Swissbrain',
                'loop-pay.com' => 'Loop Pay',
                'shop-and-pay.com' => 'Shop and Pay',
                'loxopay.ch' => 'Loxopay',
                'ideal-pay.ch' => 'Ideal Pay',
                'comvation.shop' => 'Comvation',
                'payzzter.com' => 'Payzzter',
                'go2flow.finance' => 'Go 2 Flow Finance',
                '4else.de' => '4else',
                'bkpos-pay.com' => 'Hiopos',
                'paymentmasta.com' => 'PaymentMasta',
                'pay.boukii.com' => 'Boukii (Neuron-e)',
                'payrexx.aboon.ch' => 'Aboon',
                '1pay.ch' => '1pay.ch',
            ],
        ],
        [
            'title' => __('Instance Name', 'wc-payrexx-gateway'),
            'id' => PAYREXX_CONFIGS_PREFIX . 'instance',
            'type' => 'text',
            'desc' => __('The instance name is your Payrexx account name. You find it in the URL when logged in INSTANCENAME.payrexx.com.', 'wc-payrexx-gateway'),
            'custom_attributes' => ['required' => 'required'],
        ],
        [
            'title' => __('API Key', 'wc-payrexx-gateway'),
            'id' => PAYREXX_CONFIGS_PREFIX . 'api_key',
            'type' => 'text',
            'desc' => __('Paste the API key from the integrations page of your Payrexx merchant backend here', 'wc-payrexx-gateway'),
            'default' => '',
            'custom_attributes' => ['required' => 'required'],
        ],
        [
            'type' => 'sectionend',
            'id' => PAYREXX_CONFIGS_PREFIX . 'api_settings',
        ],
        [
            'title' => __('Additional Settings', 'wc-payrexx-gateway'),
            'id' => PAYREXX_CONFIGS_PREFIX . 'additional_settings',
            'type' => 'title',
        ],
        [
            'title' => __('Look&Feel Profile ID', 'wc-payrexx-gateway'),
            'id' => PAYREXX_CONFIGS_PREFIX . 'look_and_feel_id',
            'type' => 'text',
            'description' => __('Enter a look and feel profile ID if you wish to use a specific design in the checkout', 'wc-payrexx-gateway'),
        ],
        [
            'title' => __('Prefix ', 'wc-payrexx-gateway'),
            'id' => PAYREXX_CONFIGS_PREFIX . 'prefix',
            'type' => 'text',
            'custom_attributes' => [],
            'description' => __('The Prefix is only necessary for merchants outside the Payrexx platform', 'wc-payrexx-gateway'),
            'desc_tip' => true,
        ],
        [
            'type' => 'sectionend',
            'id'   => PAYREXX_CONFIGS_PREFIX . 'additional_settings',
        ],
    ]
);
?>
