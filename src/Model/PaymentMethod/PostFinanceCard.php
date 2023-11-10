<?php

class WC_Payrexx_Gateway_PostFinanceCard extends WC_Payrexx_Gateway_SubscriptionBase
{

    public function __construct()
    {
        $this->id = PAYREXX_PM_PREFIX . 'post-finance-card';
        $this->method_title = __('Post Finance Card (Payrexx)', 'wc-payrexx-gateway');

        parent::__construct();
    }
}
