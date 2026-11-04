-- Operational workspace documents only; no statutory ledger or automatic posting.
CREATE TABLE llx_cpo_oe_workspace (
 rowid integer AUTO_INCREMENT PRIMARY KEY,
 entity integer NOT NULL,
 office varchar(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 kind varchar(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 record_ref varchar(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 fk_user_creat integer NOT NULL,
 fk_subject integer NOT NULL,
 object_key varchar(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 request_key varchar(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 request_hash char(64) NOT NULL,
 content_json longtext NOT NULL,
 status varchar(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 revision integer NOT NULL,
 date_creation datetime NOT NULL,
 date_modification datetime NOT NULL
) ENGINE=innodb;
CREATE UNIQUE INDEX uk_cpo_oe_workspace_ref ON llx_cpo_oe_workspace (entity,record_ref);
CREATE UNIQUE INDEX uk_cpo_oe_workspace_request ON llx_cpo_oe_workspace (entity,fk_user_creat,request_key);
CREATE UNIQUE INDEX uk_cpo_oe_workspace_object ON llx_cpo_oe_workspace (entity,office,kind,object_key);
CREATE UNIQUE INDEX uk_cpo_oe_workspace_branch ON llx_cpo_oe_workspace (entity,office,kind,record_ref);
