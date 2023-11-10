<?php

class WC_Payrexx_Gateway_Twint extends WC_Payrexx_Gateway_SubscriptionBase
{

    public function __construct()
    {
        $this->id = PAYREXX_PM_PREFIX . 'twint';
        $this->method_title = __('Twint (Payrexx)', 'wc-payrexx-gateway');

        parent::__construct();
    }
}
