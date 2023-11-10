<?php

class WC_Payrexx_Gateway_Visa extends WC_Payrexx_Gateway_SubscriptionBase
{

    public function __construct()
    {
        $this->id = PAYREXX_PM_PREFIX . 'visa';
        $this->method_title = __('Visa (Payrexx)', 'wc-payrexx-gateway');

        parent::__construct();
    }
}
