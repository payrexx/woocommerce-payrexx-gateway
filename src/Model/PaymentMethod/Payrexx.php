<?php

class WC_Payrexx_Gateway_Payrexx extends WC_Payrexx_Gateway_SubscriptionBase
{

    public function __construct()
    {
        $this->id = 'payrexx';
        $this->method_title = __('Payrexx', 'wc-payrexx-gateway');

        parent::__construct();
    }
}
