CREATE TABLE IF NOT EXISTS llx_cpcc_mobile_rate_limit (
  rowid bigint AUTO_INCREMENT PRIMARY KEY,
  route_key varchar(32) NOT NULL,
  bucket_hash char(64) NOT NULL,
  window_start datetime NOT NULL,
  attempt_count integer NOT NULL DEFAULT 0,
  blocked_until datetime NULL,
  date_creation datetime NOT NULL,
  tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE INDEX uk_cpcc_mobile_rate_window (route_key, bucket_hash, window_start),
  INDEX idx_cpcc_mobile_rate_cleanup (window_start, blocked_until)
) ENGINE=innodb;
