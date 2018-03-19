<?php
/* @var $installer Mage_Core_Model_Resource_Setup */
$installer = $this;
$installer->startSetup();
$installer->getConnection()->addColumn($installer->getTable('sales/order'), 'wxpay_order_no', array(
    'type' => Varien_Db_Ddl_Table::TYPE_TEXT,
    'length' => 255,
    'nullable' => true,
    'default' => null,
    'comment' => 'WX Order No'
));
$installer->getConnection()->addColumn($installer->getTable('sales/quote'), 'wxpay_order_no', array(
    'type' => Varien_Db_Ddl_Table::TYPE_TEXT,
    'length' => 255,
    'nullable' => true,
    'default' => null,
    'comment' => 'WX Order No'
));
$installer->endSetup();

