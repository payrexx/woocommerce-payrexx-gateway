<?php

class WC_Payrexx_Gateway_SamsungPay extends WC_Payrexx_Gateway_Base
{

    public function __construct()
    {
        $this->id = PAYREXX_PM_PREFIX . 'samsung-pay';
        $this->method_title = __('Samsung Pay (Payrexx)', 'wc-payrexx-gateway');

        parent::__construct();
    }
}
