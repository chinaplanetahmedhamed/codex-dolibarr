CREATE TABLE llx_cpo_oe_receipt_audit (
  rowid integer AUTO_INCREMENT PRIMARY KEY,
  entity integer NOT NULL,
  fk_user_creat integer NOT NULL,
  office varchar(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  draft_ref varchar(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  receipt_ref varchar(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  event_key varchar(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  event varchar(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  original_hash char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  date_creation datetime NOT NULL
) ENGINE=innodb;
CREATE UNIQUE INDEX uk_cpo_oe_receipt_event ON llx_cpo_oe_receipt_audit (entity,event_key);
