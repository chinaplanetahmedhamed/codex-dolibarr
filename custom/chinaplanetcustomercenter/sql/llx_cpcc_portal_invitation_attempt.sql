CREATE TABLE llx_cpcc_portal_invitation_attempt (
  rowid bigint AUTO_INCREMENT PRIMARY KEY,
  entity integer NOT NULL DEFAULT 1,
  token_hash char(64) NOT NULL,
  ip_hmac char(64) NOT NULL,
  window_start datetime NOT NULL,
  attempt_count integer NOT NULL DEFAULT 0,
  last_attempt_at datetime NOT NULL,
  tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_cpcc_invitation_attempt_scope (entity, token_hash, ip_hmac, last_attempt_at),
  KEY idx_cpcc_invitation_attempt_window (entity, last_attempt_at)
) ENGINE=innodb;
