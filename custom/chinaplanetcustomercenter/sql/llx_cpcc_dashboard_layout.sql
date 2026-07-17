CREATE TABLE IF NOT EXISTS llx_cpcc_dashboard_layout (
  rowid integer AUTO_INCREMENT PRIMARY KEY,
  entity integer NOT NULL DEFAULT 1,
  fk_user integer NOT NULL,
  dashboard_key varchar(64) NOT NULL DEFAULT 'admin_operations',
  widget_key varchar(64) NOT NULL,
  position integer NOT NULL DEFAULT 0,
  widget_size varchar(16) NOT NULL DEFAULT 'medium',
  visible tinyint NOT NULL DEFAULT 1,
  date_creation datetime NOT NULL,
  tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE INDEX uk_cpcc_dashboard_widget (entity, fk_user, dashboard_key, widget_key),
  INDEX idx_cpcc_dashboard_user (entity, fk_user, dashboard_key, position)
) ENGINE=innodb;
