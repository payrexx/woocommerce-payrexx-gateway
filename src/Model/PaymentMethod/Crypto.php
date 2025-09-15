<?php

class WC_Payrexx_Gateway_Crypto extends WC_Payrexx_Gateway_Base
{

    public function __construct()
    {
        $this->id = PAYREXX_PM_PREFIX . 'crypto';
        $this->method_title = __('Crypto (Payrexx)', 'woo-payrexx-gateway');

        parent::__construct();
    }
}
