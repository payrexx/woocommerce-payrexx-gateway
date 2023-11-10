<?php

class WC_Payrexx_Gateway_PostFinanceEFinance extends WC_Payrexx_Gateway_SubscriptionBase
{

    public function __construct()
    {
        $this->id = PAYREXX_PM_PREFIX . 'post-finance-e-finance';
        $this->method_title = __('PostFinance E-Finance (Payrexx)', 'wc-payrexx-gateway');

        parent::__construct();
    }
}
