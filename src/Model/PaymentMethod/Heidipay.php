<?php

class WC_Payrexx_Gateway_Heidipay extends WC_Payrexx_Gateway_Base
{

    public function __construct()
    {
        $this->id = PAYREXX_PM_PREFIX . 'heidipay';
        $this->method_title = __('Heidipay (Payrexx)', 'wc-payrexx-gateway');

        parent::__construct();
    }
}
