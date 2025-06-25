<?php

class WC_Payrexx_Gateway_CembraPay extends WC_Payrexx_Gateway_Base
{

	public function __construct()
	{
		$this->id = PAYREXX_PM_PREFIX . 'cembrapay';
		$this->method_title = __('CembraPay (Payrexx)', 'woo-payrexx-gateway');

		parent::__construct();
	}
}
