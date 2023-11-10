<?php

class WC_Payrexx_Gateway_Masterpass extends WC_Payrexx_Gateway_Base
{

    public function __construct()
    {
        $this->id = PAYREXX_PM_PREFIX . 'masterpass';
        $this->method_title = __('Masterpass (Payrexx)', 'wc-payrexx-gateway');

        parent::__construct();
    }
}
