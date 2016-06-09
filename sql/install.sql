---
--- See the xml schema files for more information.
---
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE `korder` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `invoice_from_id` int(10) unsigned DEFAULT NULL COMMENT 'Invoice From Contact ID',
  `nid` int(10) unsigned NOT NULL DEFAULT '0',
  `vid` int(10) unsigned NOT NULL DEFAULT '0',
  `node_reference` int(10) unsigned NOT NULL DEFAULT '0',
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
  KEY `node_reference` (`node_reference`),
  KEY `FK_korder_invoice_from_id` (`invoice_from_id`),
  CONSTRAINT `FK_korder_invoice_from_id` FOREIGN KEY (`invoice_from_id`) REFERENCES `civicrm_contact` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE `korder_line` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` int(10) unsigned NOT NULL,
  `title` varchar(255) COLLATE utf8_unicode_ci DEFAULT '',
  `hours_billed` float NOT NULL DEFAULT '0',
  `cost` float NOT NULL DEFAULT '0',
  `unit` varchar(15) COLLATE utf8_unicode_ci DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`),
  CONSTRAINT `FK_korder_line_order_id` FOREIGN KEY (`order_id`) REFERENCES `korder` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE `kprojectreports_schedules` (
  `krid` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` char(255) DEFAULT NULL,
  `frequency` char(16) DEFAULT NULL,
  `report` char(255) DEFAULT NULL,
  `mail` char(255) DEFAULT NULL,
  `format` char(16) DEFAULT NULL,
  `lastrun` int(11) DEFAULT NULL,
  `options` text,
  `intro` text,
  PRIMARY KEY (`krid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE `kpunch` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `ktask_id` int(10) unsigned DEFAULT NULL,
  `contact_id` int(10) unsigned NOT NULL,
  `begin` int(11) NOT NULL,
  `duration` int(11) DEFAULT NULL,
  `comment` varchar(255) DEFAULT NULL,
  `billable_intern` int(11) NOT NULL DEFAULT '1',
  `billable_client` int(11) NOT NULL DEFAULT '1',
  `rate` decimal(11,0) DEFAULT NULL,
  `order_reference` int(10) unsigned DEFAULT NULL,
  `korder_id` int(10) unsigned DEFAULT NULL,
  `korder_line_id` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `contact_id` (`contact_id`),
  KEY `order_reference` (`order_reference`),
  KEY `FK_kpunch_korder_line_id` (`korder_line_id`),
  KEY `FK_kpunch_korder_id` (`korder_id`),
  KEY `ktask_id` (`ktask_id`),
  CONSTRAINT `FK_kpunch_contact_id` FOREIGN KEY (`contact_id`) REFERENCES `civicrm_contact` (`id`) ON DELETE SET NULL,
  CONSTRAINT `FK_kpunch_korder_id` FOREIGN KEY (`korder_id`) REFERENCES `korder` (`id`) ON DELETE SET NULL,
  CONSTRAINT `FK_kpunch_korder_line_id` FOREIGN KEY (`korder_line_id`) REFERENCES `korder_line` (`id`) ON DELETE SET NULL,
  CONSTRAINT `FK_ktask_id` FOREIGN KEY (`ktask_id`) REFERENCES `ktask` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE `ktask` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `case_id` int(10) unsigned NOT NULL DEFAULT '0',
  `activity_id` int(10) unsigned NOT NULL DEFAULT '0',
  `parent` int(10) unsigned NOT NULL DEFAULT '0',
  `state` tinyint(4) NOT NULL DEFAULT '0',
  `estimate` int(11) DEFAULT NULL,
  `begin` int(11) DEFAULT NULL,
  `end` int(11) DEFAULT NULL,
  `lead` int(11) DEFAULT NULL,
  `title` varchar(255) DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `state` (`state`),
  KEY `parent` (`parent`),
  KEY `case_id` (`case_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
