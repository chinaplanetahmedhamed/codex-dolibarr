CREATE TABLE IF NOT EXISTS llx_cpcc_mobile_refresh_token (
  rowid bigint AUTO_INCREMENT PRIMARY KEY,
  fk_family bigint NOT NULL,
  token_hash char(64) NOT NULL,
  generation integer NOT NULL,
  date_creation datetime NOT NULL,
  expires_at datetime NOT NULL,
  used_at datetime NULL,
  revoked_at datetime NULL,
  UNIQUE INDEX uk_cpcc_mobile_refresh_hash (token_hash),
  UNIQUE INDEX uk_cpcc_mobile_refresh_generation (fk_family, generation),
  INDEX idx_cpcc_mobile_refresh_family (fk_family, revoked_at, expires_at)
) ENGINE=innodb;
