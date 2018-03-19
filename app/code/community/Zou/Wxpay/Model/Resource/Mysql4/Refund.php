<?php
class Zou_Wxpay_Model_Resource_Mysql4_Refund extends Mage_Core_Model_Mysql4_Abstract
{
	protected function _construct()
	{
		$this->_init('wxpay/refund','id');
	}
}

?>
