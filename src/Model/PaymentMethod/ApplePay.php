<?php

class WC_Payrexx_Gateway_ApplePay extends WC_Payrexx_Gateway_Base
{

    public function __construct()
    {
        $this->id = PAYREXX_PM_PREFIX . 'apple-pay';
        $this->method_title = __('Apple Pay (Payrexx)', 'woo-payrexx-gateway');

        parent::__construct();
    }
}
