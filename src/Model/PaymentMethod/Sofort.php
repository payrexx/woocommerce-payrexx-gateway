<?php

class WC_Payrexx_Gateway_Sofort extends WC_Payrexx_Gateway_Base
{

    public function __construct()
    {
        $this->id = PAYREXX_PM_PREFIX . 'sofort';
        $this->method_title = __('Sofort (Payrexx)', 'wc-payrexx-gateway');

        parent::__construct();
    }
}
