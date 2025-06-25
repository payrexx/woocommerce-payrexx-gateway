<?php

class WC_Payrexx_Gateway_Maestro extends WC_Payrexx_Gateway_Base
{

    public function __construct()
    {
        $this->id = PAYREXX_PM_PREFIX . 'maestro';
        $this->method_title = __('Maestro (Payrexx)', 'woo-payrexx-gateway');

        parent::__construct();
    }
}
