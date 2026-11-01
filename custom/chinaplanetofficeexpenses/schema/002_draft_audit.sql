CREATE TABLE llx_cpo_oe_draft_audit (
  rowid integer AUTO_INCREMENT PRIMARY KEY,
  entity integer NOT NULL,
  fk_user_creat integer NOT NULL,
  draft_ref varchar(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  event varchar(32) NOT NULL,
  payload_hash char(64) NOT NULL,
  date_creation datetime NOT NULL
) ENGINE=innodb;
CREATE UNIQUE INDEX uk_cpo_oe_draft_created ON llx_cpo_oe_draft_audit (entity,draft_ref,event);
