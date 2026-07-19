CREATE TABLE IF NOT EXISTS llx_cpcc_mobile_public_id (
  rowid bigint AUTO_INCREMENT PRIMARY KEY,
  public_id varchar(96) NOT NULL,
  resource_type varchar(40) NOT NULL,
  entity integer NOT NULL,
  fk_soc integer NOT NULL,
  object_id bigint NOT NULL,
  date_creation datetime NOT NULL,
  UNIQUE INDEX uk_cpcc_mobile_public_id (public_id),
  UNIQUE INDEX uk_cpcc_mobile_public_object (resource_type, entity, fk_soc, object_id),
  INDEX idx_cpcc_mobile_public_owner (entity, fk_soc, resource_type)
) ENGINE=innodb;
