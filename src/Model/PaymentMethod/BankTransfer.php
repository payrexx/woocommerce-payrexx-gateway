<?php

class WC_Payrexx_Gateway_BankTransfer extends WC_Payrexx_Gateway_Base
{

    public function __construct()
    {
        $this->id = PAYREXX_PM_PREFIX . 'bank-transfer';
        $this->method_title = __('Purchase on invoice', 'wc-payrexx-gateway');

        parent::__construct();
    }
}
