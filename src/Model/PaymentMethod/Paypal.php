<?php

class WC_Payrexx_Gateway_Paypal extends WC_Payrexx_Gateway_Base
{

    public function __construct()
    {
        $this->id = PAYREXX_PM_PREFIX . 'paypal';
        $this->method_title = __('Paypal (Payrexx)', 'wc-payrexx-gateway');

        parent::__construct();
    }
}
