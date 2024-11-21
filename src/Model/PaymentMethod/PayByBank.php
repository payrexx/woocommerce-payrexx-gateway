<?php

class WC_Payrexx_Gateway_PayByBank extends WC_Payrexx_Gateway_Base
{

	public function __construct()
	{
		$this->id = PAYREXX_PM_PREFIX . 'pay-by-bank';
		$this->method_title = __('Pay by Bank (Payrexx)', 'wc-payrexx-gateway');

		parent::__construct();
	}
}
