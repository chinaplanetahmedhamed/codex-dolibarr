-- Inactive native migration registry. No module auto-install or destructive down migration.
CREATE TABLE llx_cpo_oe_schema (
  rowid integer AUTO_INCREMENT PRIMARY KEY,
  schema_key varchar(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  manifest_hash char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  status varchar(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  date_creation datetime NOT NULL,
  date_modification datetime NOT NULL
) ENGINE=innodb;
CREATE UNIQUE INDEX uk_cpo_oe_schema_key ON llx_cpo_oe_schema (schema_key);
