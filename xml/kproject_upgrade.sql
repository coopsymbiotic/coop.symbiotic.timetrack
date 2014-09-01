-- order_reference was a varchar(512), but should always reference a number.
alter table kpunch change order_reference order_reference int(10) unsigned null;
