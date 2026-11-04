-- Candidate operational staging schema only. Not in module auto-install SQL; no activation/migration.
CREATE TABLE llx_cpo_oe_draft (
  rowid integer AUTO_INCREMENT PRIMARY KEY,
  entity integer NOT NULL,
  fk_user_creat integer NOT NULL,
  office varchar(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  draft_ref varchar(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  submission_key varchar(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  payload_hash char(64) NOT NULL,
  document_json longtext NOT NULL,
  status varchar(16) NOT NULL,
  date_creation datetime NOT NULL
) ENGINE=innodb;
CREATE UNIQUE INDEX uk_cpo_oe_draft_source ON llx_cpo_oe_draft (entity,fk_user_creat,submission_key);
CREATE UNIQUE INDEX uk_cpo_oe_draft_ref ON llx_cpo_oe_draft (entity,draft_ref);
