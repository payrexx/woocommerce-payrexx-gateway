<?php

class WC_Payrexx_Gateway_Discover extends WC_Payrexx_Gateway_Base
{

    public function __construct()
    {
        $this->id = PAYREXX_PM_PREFIX . 'discover';
        $this->method_title = __('Discover (Payrexx)', 'wc-payrexx-gateway');

        parent::__construct();
    }
}
