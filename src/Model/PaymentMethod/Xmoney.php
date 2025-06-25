<?php

class WC_Payrexx_Gateway_Xmoney extends WC_Payrexx_Gateway_Base
{

    public function __construct()
    {
        $this->id = PAYREXX_PM_PREFIX . 'x-money';
        $this->method_title = __('xMoney (Payrexx)', 'woo-payrexx-gateway');

        parent::__construct();
    }
}
