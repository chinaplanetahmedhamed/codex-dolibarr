CREATE TABLE llx_cpo_oe_access_audit (
  rowid integer AUTO_INCREMENT PRIMARY KEY,
  entity integer NOT NULL,
  fk_actor integer NOT NULL,
  fk_user integer NOT NULL,
  office varchar(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  revision integer NOT NULL,
  before_json longtext NOT NULL,
  after_json longtext NOT NULL,
  date_creation datetime NOT NULL
) ENGINE=innodb;
CREATE UNIQUE INDEX uk_cpo_oe_access_revision ON llx_cpo_oe_access_audit (entity,fk_user,office,revision);
