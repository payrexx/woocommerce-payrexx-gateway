<?php

class WC_Payrexx_Gateway_Centi extends WC_Payrexx_Gateway_Base
{

    public function __construct()
    {
        $this->id = PAYREXX_PM_PREFIX . 'centi';
        $this->method_title = __('Centi (Payrexx)', 'wc-payrexx-gateway');

        parent::__construct();
    }
}
