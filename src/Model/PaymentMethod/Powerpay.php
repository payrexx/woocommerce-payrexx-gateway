<?php

class WC_Payrexx_Gateway_Powerpay extends WC_Payrexx_Gateway_Base
{

    public function __construct()
    {
        $this->id = PAYREXX_PM_PREFIX . 'powerpay';
        $this->method_title = __('Powerpay (Payrexx)', 'wc-payrexx-gateway');

        parent::__construct();
    }
}