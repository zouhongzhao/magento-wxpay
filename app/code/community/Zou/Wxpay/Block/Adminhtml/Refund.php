<?php
class Zou_Wxpay_Block_Adminhtml_Refund extends Mage_Adminhtml_Block_Widget_Grid_Container
{
  public function __construct()
  {
    $this->_controller = 'adminhtml_refund';
    $this->_blockGroup = 'wxpay';
    $this->_headerText = Mage::helper('wxpay')->__('Refund Manager');
//     $this->_addButtonLabel = Mage::helper('supplier')->__('Add Supplier');
    parent::__construct();
    $this->_removeButton('add');
  }
}