<?php

class WC_Payrexx_Gateway_VerdCash extends WC_Payrexx_Gateway_Base
{

    public function __construct()
    {
        $this->id = PAYREXX_PM_PREFIX . 'verd-cash';
        $this->method_title = __('VERD.cash (Payrexx)', 'woo-payrexx-gateway');

        parent::__construct();
    }
}
