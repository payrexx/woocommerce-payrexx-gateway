<?php

class WC_Payrexx_Gateway_DinersClub extends WC_Payrexx_Gateway_Base
{

    public function __construct()
    {
        $this->id = PAYREXX_PM_PREFIX . 'diners-club';
        $this->method_title = __('Diners Club (Payrexx)', 'woo-payrexx-gateway');

        parent::__construct();
    }
}
