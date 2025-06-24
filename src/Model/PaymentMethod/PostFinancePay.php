<?php

class WC_Payrexx_Gateway_PostFinancePay extends WC_Payrexx_Gateway_SubscriptionBase
{

    public function __construct()
    {
        $this->id = PAYREXX_PM_PREFIX . 'post-finance-pay';
        $this->method_title = __('Post Finance Pay (Payrexx)', 'woo-payrexx-gateway');

        parent::__construct();
    }
}
