--
-- General
--

-- Rename the table primary key fields to just "id". More DAO-friendly.
-- TODO: do the same for kcontract.
ALTER TABLE ktask change ktid id int(10) unsigned NOT NULL auto_increment;
ALTER TABLE kpunch change pid id int(10) unsigned NOT NULL auto_increment;
ALTER TABLE korder change koid id int(10) unsigned NOT NULL auto_increment;

-- Make sure that all tables are InnoDB. Important for foreign keys.
ALTER TABLE kpunch ENGINE=InnoDB;
ALTER TABLE korder ENGINE=InnoDB;
ALTER TABLE ktask ENGINE=InnoDB;
ALTER TABLE kcontract ENGINE=InnoDB;

--
-- kcontract
--

--
-- TODO: Create a 'case' for each 'contract'.
-- NB: keep the nid/case_id association somewhere (currently in a custom field).
-- Try to respect the project status, open/close dates.
--

ALTER TABLE kcontract DROP KEY `nid_vid`;
ALTER TABLE kcontract DROP KEY `vid`;

ALTER TABLE kcontract ADD `case_id` int(10) unsigned default NULL;
ALTER TABLE kcontract ADD constraint FK_case_id foreign key (`case_id`) references `civicrm_case` (`id`) on delete cascade;

-- UPDATE kcontract, civicrm_value_infos_base_contrats_1 set kcontract.case_id = civicrm_value_infos_base_contrats_1.entity_id where civicrm_value_infos_base_contrats_1.kproject_node_2 = kcontract.nid and kcontract.case_id is null;


--
-- ktask
--

ALTER TABLE ktask add case_id int(10) unsigned NOT NULL default 0 after id;
ALTER TABLE ktask add key `case_id` (`case_id`);

ALTER TABLE ktask add activity_id int(10) unsigned NOT NULL default 0 after case_id;
ALTER TABLE ktask add key `activity_id` (`activity_id`);

ALTER TABLE ktask drop key nid_vid, drop key vid;
ALTER TABLE ktask drop key drop key vid;

ALTER table ktask ADD column title varchar(255) default '';
ALTER table ktask CHANGE estimate estimate int(11) default null;

UPDATE ktask, node, civicrm_value_infos_base_contrats_1
   SET ktask.case_id = civicrm_value_infos_base_contrats_1.entity_id,
       ktask.title = node.title
 WHERE ktask.nid = node.nid
   AND ktask.parent = civicrm_value_infos_base_contrats_1.kproject_node_2
   AND (ktask.case_id = 0 OR ktask.case_id IS NULL);

--
-- kpunch
--

-- order_reference was a varchar(512), but should always reference a number.
-- nb: we trash/move the contents of this column to 'korder_id' later on.
ALTER table kpunch CHANGE order_reference order_reference int(10) unsigned NULL;

-- Convert the kpunch.nid to kpunch.ktask_id
-- NB: when a task is deleted, we do not delete the punch.
-- The punch will become orphan if its task is deleted, but too risky otherwise.
ALTER table kpunch ADD `ktask_id` int(10) unsigned default NULL after id;
ALTER table kpunch ADD key `ktask_id` (`ktask_id`);
ALTER table kpunch ADD constraint FK_ktask_id foreign key (`ktask_id`) references `ktask` (`id`) On delete set null;

UPDATE kpunch, ktask SET kpunch.ktask_id = ktask.id WHERE kpunch.nid = ktask.nid AND kpunch.ktask_id IS NULL;

-- TODO: convert uid to contact_id.

--
-- korder
--

-- Move node information into the korder
ALTER table korder ADD column title varchar(255) default '';
ALTER table korder ADD column created_date timestamp NULL DEFAULT NULL;
ALTER table korder ADD column modified_date timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP;
ALTER table korder ADD column deposit_date timestamp NULL DEFAULT NULL COMMENT 'Date of the deposit, payment received for an invoice.';
ALTER table korder ADD column deposit_reference varchar(255) DEFAULT '' COMMENT 'Reference for the deposit, usually the cheque or wire transfer reference.';
ALTER table korder ADD column details_public text COMMENT 'Additional information regarding the invoice, to be shown on the invoice.';
ALTER table korder ADD column details_private text COMMENT 'Additional information regarding the invoice, not shown on the invoice.';

UPDATE korder
  LEFT JOIN node on (node.nid = korder.nid)
  SET korder.title = node.title,
      korder.created_date = from_unixtime(node.created),
      korder.modified_date = from_unixtime(node.changed)
  WHERE korder.title IS NULL;

-- Until we drop the nid/vid completely, remove the keys on those fields:
ALTER TABLE korder DROP key nid_vid;
ALTER TABLE korder DROP key vid;

-- Make the order ID optional, since not really used.
-- Invoice ID remains mandatory, for now.. (more of a workflow issue).
ALTER TABLE korder change ledger_order_id ledger_order_id int(11) null default 0;

-- Add reference to civicrm_case.id in korder
-- (previously, it would refer to the nid of the contract)
ALTER TABLE korder add case_id int(10) unsigned DEFAULT NULL;

UPDATE korder, node, civicrm_value_infos_base_contrats_1
   SET korder.case_id = civicrm_value_infos_base_contrats_1.entity_id
 WHERE korder.node_reference = node.nid
   AND node.nid = civicrm_value_infos_base_contrats_1.kproject_node_2
   AND (korder.case_id = 0 OR korder.case_id IS NULL);

--
-- korder_line
--

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

--
-- korder_line and kpunch links
--

-- Add line reference in kpunch
ALTER TABLE kpunch add korder_id int(10) unsigned DEFAULT NULL;
ALTER TABLE kpunch add korder_line_id int(10) unsigned DEFAULT NULL;
ALTER TABLE kpunch add CONSTRAINT `FK_kpunch_korder_id` FOREIGN KEY (`korder_id`) REFERENCES `korder` (`id`) ON DELETE SET NULL;
ALTER TABLE kpunch add CONSTRAINT `FK_kpunch_korder_line_id` FOREIGN KEY (`korder_line_id`) REFERENCES `korder_line` (`id`) ON DELETE SET NULL;

-- The kpunch rows were referencing the nid of korders.
-- This updates the new korder_id field, which refers to the korder.id instead.
UPDATE kpunch, node, korder SET kpunch.korder_id = korder.id WHERE node.nid = kpunch.order_reference and korder.nid = node.nid and kpunch.korder_id is NULL;

--
-- TODO: cleanup
--
-- ALTER TABLE kpunch DROP nid, DROP uid;
-- ALTER TABLE ktask DROP nid, DROP vid, DROP parent;
-- etc.
