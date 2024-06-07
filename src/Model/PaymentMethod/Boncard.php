<?php

class WC_Payrexx_Gateway_Boncard extends WC_Payrexx_Gateway_Base
{

	public function __construct()
	{
		$this->id = PAYREXX_PM_PREFIX . 'boncard';
		$this->method_title = __( 'Boncard (Payrexx)', 'wc-payrexx-gateway' );

		parent::__construct();
	}
}
