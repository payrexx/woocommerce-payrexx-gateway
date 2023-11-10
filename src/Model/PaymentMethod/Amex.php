<?php

class WC_Payrexx_Gateway_Amex extends WC_Payrexx_Gateway_SubscriptionBase
{
    public function __construct()
    {
        $this->id = PAYREXX_PM_PREFIX . 'american-express';
        $this->method_title = __('Amex (Payrexx)', 'wc-payrexx-gateway');

        parent::__construct();
    }
}
