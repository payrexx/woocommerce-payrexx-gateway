<?php

class WC_Payrexx_Gateway_Wirpay extends WC_Payrexx_Gateway_Base
{

    public function __construct()
    {
        $this->id = PAYREXX_PM_PREFIX . 'wirpay';
        $this->method_title = __('Wirpay (Payrexx)', 'wc-payrexx-gateway');

        parent::__construct();
    }
}
