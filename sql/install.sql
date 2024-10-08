CREATE TABLE `kcontract` (
  `kcid` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `parent` int(10) unsigned NOT NULL DEFAULT '0',
  `alias` char(16) DEFAULT NULL,
  `state` tinyint(4) NOT NULL DEFAULT '0',
  `estimate` int(11) NOT NULL DEFAULT '0',
  `begin` int(11) DEFAULT NULL,
  `end` int(11) DEFAULT NULL,
  `lead` int(11) DEFAULT NULL,
  `case_id` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`kcid`),
  UNIQUE KEY `alias` (`alias`),
  KEY `state` (`state`),
  KEY `lead` (`lead`),
  KEY `parent` (`parent`),
  KEY `FK_case_id` (`case_id`),
  CONSTRAINT `FK_case_id` FOREIGN KEY (`case_id`) REFERENCES `civicrm_case` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

CREATE TABLE `korder` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `invoice_from_id` int(10) unsigned DEFAULT NULL COMMENT 'Invoice From Contact ID',
  `nid` int(10) unsigned NOT NULL DEFAULT '0',
  `vid` int(10) unsigned NOT NULL DEFAULT '0',
  `state` tinyint(4) NOT NULL DEFAULT '0',
  `ledger_order_id` int(11) DEFAULT '0',
  `ledger_bill_id` int(11) NOT NULL DEFAULT '0',
  `hours_billed` float NOT NULL DEFAULT '0',
  `paid` int(11) DEFAULT NULL COMMENT 'When the bill has been paid (timestamp)',
  `title` varchar(255) DEFAULT '',
  `created_date` timestamp NULL DEFAULT NULL,
  `modified_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `case_id` int(10) unsigned DEFAULT NULL,
  `deposit_date` timestamp NULL DEFAULT NULL COMMENT 'Date of the deposit, payment received for an invoice.',
  `deposit_reference` varchar(255) DEFAULT '' COMMENT 'Reference for the deposit, usually the cheque or wire transfer reference.',
  `details_public` text COMMENT 'Additional information regarding the invoice, to be shown on the invoice.',
  `details_private` text COMMENT 'Additional information regarding the invoice, not shown on the invoice.',
  PRIMARY KEY (`id`),
  KEY `state` (`state`),
  KEY `FK_korder_invoice_from_id` (`invoice_from_id`),
  CONSTRAINT `FK_korder_invoice_from_id` FOREIGN KEY (`invoice_from_id`) REFERENCES `civicrm_contact` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

CREATE TABLE `civicrm_timetracktask` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `case_id` int(10) unsigned NOT NULL DEFAULT '0',
  `state` tinyint(4) NOT NULL DEFAULT '0',
  `estimate` int(11) DEFAULT NULL,
  `begin` timestamp DEFAULT NULL,
  `end` timestamp DEFAULT NULL,
  `lead` int(11) DEFAULT NULL,
  `title` varchar(255) DEFAULT '',
  `description` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `state` (`state`),
  KEY `case_id` (`case_id`)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

CREATE TABLE `korder_line` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` int(10) unsigned NOT NULL,
  `ktask_id` int(10) unsigned DEFAULT NULL,
  `title` varchar(255) COLLATE utf8_unicode_ci DEFAULT '',
  `hours_billed` float NOT NULL DEFAULT '0',
  `cost` float NOT NULL DEFAULT '0',
  `unit` varchar(15) COLLATE utf8_unicode_ci DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`),
  CONSTRAINT `FK_korder_line_order_id` FOREIGN KEY (`order_id`) REFERENCES `korder` (`id`) ON DELETE CASCADE,
  CONSTRAINT `FK_korder_line_ktask_id` FOREIGN KEY (`ktask_id`) REFERENCES `civicrm_timetracktask` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
