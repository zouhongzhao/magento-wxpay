<?php
$installer = $this;
$installer->startSetup();
$installer->run("

		-- DROP TABLE if exists {$this->getTable('wxpay_refunds')};
		CREATE TABLE {$this->getTable('wxpay_refunds')} (
		 `id` int(11) NOT NULL AUTO_INCREMENT,
		  `order_no` varchar(255) DEFAULT NULL,
		  `customer_id` int(10) DEFAULT NULL,
		  `customer_email` varchar(255) DEFAULT NULL,
		  `refund_id` varchar(255) DEFAULT NULL,
		  `transaction_id` varchar(255) DEFAULT NULL,
		  `out_refund_no` varchar(255) DEFAULT NULL,
		  `currency` varchar(20) DEFAULT NULL,
		  `amount` DECIMAL(12,4) DEFAULT NULL,
		  `refund_time` timestamp NULL DEFAULT NULL,
		  `result_code` varchar(255) DEFAULT NULL,
		  `result_msg` varchar(255) DEFAULT NULL,
		  `message` text DEFAULT NULL,
		  `status` SMALLINT(5) DEFAULT NULL,
		  PRIMARY KEY (`id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8;

");
$installer->endSetup(); 