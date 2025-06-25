<?php

class WC_Payrexx_Gateway_Mastercard extends WC_Payrexx_Gateway_SubscriptionBase
{
    public function __construct()
    {
        $this->id = PAYREXX_PM_PREFIX . 'mastercard';
        $this->method_title = __('Mastercard (Payrexx)', 'woo-payrexx-gateway');

        parent::__construct();
    }
}
