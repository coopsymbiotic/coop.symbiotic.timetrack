-- Rename the table primary key fields to just "id". More DAO-friendly.
-- TODO: do the same for ktask, kcontract.
ALTER TABLE kpunch change pid id int(10) unsigned not null auto_increment;
ALTER TABLE korder change koid id int(10) unsigned not null auto_increment;

-- order_reference was a varchar(512), but should always reference a number.
-- nb: we trash/move the contents of this column to 'korder_id' later on.
alter table kpunch change order_reference order_reference int(10) unsigned null;

-- Move node information into the korder
alter table korder add column title varchar(255) default '';
alter table korder add column created_date timestamp NULL DEFAULT NULL;
alter table korder add column modified_date timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP;
update korder left join node on (node.nid = korder.nid) set korder.title = node.title, korder.created_date = from_unixtime(node.created), korder.modified_date = from_unixtime(node.changed);

-- Until we drop the nid/vid completely, remove the keys on those fields:
ALTER TABLE korder DROP key nid_vid;
ALTER TABLE korder DROP key vid;

-- Make sure that all tables are InnoDB. Important for foreign keys.
ALTER TABLE kpunch ENGINE=InnoDB;
ALTER TABLE korder ENGINE=InnoDB;
ALTER TABLE ktask ENGINE=InnoDB;
ALTER TABLE kcontract ENGINE=InnoDB;

-- Add line reference in kpunch
ALTER TABLE kpunch add korder_id int(10) unsigned DEFAULT NULL;
ALTER TABLE kpunch add korder_line_id int(10) unsigned DEFAULT NULL;
ALTER TABLE kpunch add CONSTRAINT `FK_kpunch_korder_id` FOREIGN KEY (`korder_id`) REFERENCES `korder` (`id`) ON DELETE SET NULL;
ALTER TABLE kpunch add CONSTRAINT `FK_kpunch_korder_line_id` FOREIGN KEY (`korder_line_id`) REFERENCES `korder_line` (`id`) ON DELETE SET NULL;

-- The kpunch rows were referencing the nid of korders.
-- This updates the new korder_id field, which refers to the korder.id instead.
UPDATE kpunch, node, korder SET kpunch.korder_id = korder.id WHERE node.nid = kpunch.order_reference and korder.nid = node.nid;

-- Add reference to civicrm_case.id in korder
-- (previously, it would refer to the nid of the contract)
ALTER TABLE korder add case_id int(10) unsigned DEFAULT NULL;

UPDATE korder, node, civicrm_value_infos_base_contrats_1
   SET korder.case_id = civicrm_value_infos_base_contrats_1.entity_id
 WHERE korder.node_reference = node.nid
   AND node.nid = civicrm_value_infos_base_contrats_1.kproject_node_2;

-- Make the order ID optional, since not really used.
-- Invoice ID remains mandatory, for now.. (more of a workflow issue).
ALTER TABLE korder change ledger_order_id ledger_order_id int(11) null default 0;

-- new korder_line table
CREATE TABLE `korder_line` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` int(10) unsigned NOT NULL,
  `title` varchar(255) DEFAULT '',
  `hours_billed` float NOT NULL DEFAULT '0',
  `cost` float NOT NULL DEFAULT '0',
  `unit` varchar(15) COLLATE utf8_unicode_ci DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`),
  CONSTRAINT `FK_korder_line_order_id` FOREIGN KEY (`order_id`) REFERENCES `korder` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
