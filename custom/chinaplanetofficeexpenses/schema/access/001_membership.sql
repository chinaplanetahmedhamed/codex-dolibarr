-- Explicit inactive additive migration; never invoked by module init/remove.
CREATE TABLE llx_cpo_oe_membership (
  rowid integer AUTO_INCREMENT PRIMARY KEY,
  entity integer NOT NULL,
  fk_user integer NOT NULL,
  office varchar(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  active integer NOT NULL,
  grants_json longtext NOT NULL,
  record_scope varchar(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  revision integer NOT NULL,
  date_modification datetime NOT NULL
) ENGINE=innodb;
CREATE UNIQUE INDEX uk_cpo_oe_membership ON llx_cpo_oe_membership (entity,fk_user,office);
