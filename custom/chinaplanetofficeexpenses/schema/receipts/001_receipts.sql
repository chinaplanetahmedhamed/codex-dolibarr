-- Inactive receipt metadata; originals/previews remain outside the web root.
CREATE TABLE llx_cpo_oe_receipt (
  rowid integer AUTO_INCREMENT PRIMARY KEY,
  entity integer NOT NULL,
  fk_user_creat integer NOT NULL,
  office varchar(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  draft_ref varchar(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  receipt_ref varchar(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  submission_key varchar(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  source_hash char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  intent_hash char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  metadata_hash char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  document_json longtext NOT NULL,
  status varchar(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  date_creation datetime NOT NULL
) ENGINE=innodb;
CREATE UNIQUE INDEX uk_cpo_oe_receipt_ref ON llx_cpo_oe_receipt (entity,receipt_ref);
CREATE UNIQUE INDEX uk_cpo_oe_receipt_retry ON llx_cpo_oe_receipt (entity,fk_user_creat,draft_ref,submission_key);
