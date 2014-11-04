-- order_reference was a varchar(512), but should always reference a number.
alter table kpunch change order_reference order_reference int(10) unsigned null;

-- move node information into the korder
alter table korder add column title varchar(255) default '';
alter table korder add column created_date timestamp NULL DEFAULT NULL;
alter table korder add column modified_date timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP;
update korder left join node on (node.nid = korder.nid) set korder.title = node.title, korder.created_date = from_unixtime(node.created), korder.modified_date = from_unixtime(node.changed);

-- until we drop the nid/vid completely:
alter table korder drop key nid_vid;
alter table korder drop key vid;

-- set korder as innodb so that we can reference it with foreign keys
ALTER TABLE korder ENGINE=InnoDB;

-- add line reference in kpunch
ALTER TABLE kpunch ENGINE=InnoDB;
ALTER TABLE kpunch add korder_id int(10) unsigned DEFAULT NULL;
ALTER TABLE kpunch add korder_line_id int(10) unsigned DEFAULT NULL;
ALTER TABLE kpunch add CONSTRAINT `FK_kpunch_korder_id` FOREIGN KEY (`korder_id`) REFERENCES `korder` (`koid`) ON DELETE SET NULL;
ALTER TABLE kpunch add CONSTRAINT `FK_kpunch_korder_line_id` FOREIGN KEY (`korder_line_id`) REFERENCES `korder_line` (`id`) ON DELETE SET NULL;

-- The kpunch rows were referencing the nid of korders.
-- This updates the new korder_id field, which refers to the korder.koid instead.
UPDATE kpunch, node, korder SET kpunch.korder_id = korder.koid WHERE node.nid = kpunch.order_reference and korder.nid = node.nid;

-- new korder_line table
CREATE TABLE `korder_line` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` int(10) unsigned NOT NULL,
  `title` varchar(255) DEFAULT '',
  `hours_billed` float NOT NULL DEFAULT '0',
  `rate` float NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`),
  CONSTRAINT `FK_korder_line_order_id` FOREIGN KEY (`order_id`) REFERENCES `korder` (`koid`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
