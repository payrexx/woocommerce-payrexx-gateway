<?php

class WC_Payrexx_Gateway_Klarna extends WC_Payrexx_Gateway_Base
{

	public function __construct()
	{
		$this->id = PAYREXX_PM_PREFIX . 'klarna';
		$this->method_title = __('Klarna (Payrexx)', 'woo-payrexx-gateway');

		parent::__construct();
	}
}