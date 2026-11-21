CREATE TABLE llx_cpo_oe_workspace_audit (
 rowid integer AUTO_INCREMENT PRIMARY KEY,
 entity integer NOT NULL,
 office varchar(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 record_ref varchar(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 revision integer NOT NULL,
 fk_actor integer NOT NULL,
 action varchar(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 before_json longtext NOT NULL,
 after_json longtext NOT NULL,
 date_creation datetime NOT NULL
) ENGINE=innodb;
CREATE UNIQUE INDEX uk_cpo_oe_workspace_audit ON llx_cpo_oe_workspace_audit (entity,record_ref,revision);
