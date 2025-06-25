<?php

class WC_Payrexx_Gateway_Reka extends WC_Payrexx_Gateway_Base
{

    public function __construct()
    {
        $this->id = PAYREXX_PM_PREFIX . 'reka';
        $this->method_title = __('Reka (Payrexx)', 'woo-payrexx-gateway');

        parent::__construct();
    }
}
