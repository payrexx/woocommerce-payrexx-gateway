<?php

class WC_Payrexx_Gateway_BobInvoice extends WC_Payrexx_Gateway_Base
{

    public function __construct()
    {
        $this->id = PAYREXX_PM_PREFIX . 'bob-invoice';
        $this->method_title = __( 'Bob Invoice (Payrexx)', 'wc-payrexx-gateway' );

        parent::__construct();
    }
}
