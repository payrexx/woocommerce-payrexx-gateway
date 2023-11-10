<?php

class WC_Payrexx_Gateway_GooglePay extends WC_Payrexx_Gateway_Base
{

    public function __construct()
    {
        $this->id = PAYREXX_PM_PREFIX . 'google-pay';
        $this->method_title = __('Google Pay (Payrexx)', 'wc-payrexx-gateway');

        parent::__construct();
    }
}
