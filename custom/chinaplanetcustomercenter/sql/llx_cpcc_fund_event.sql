CREATE TABLE IF NOT EXISTS llx_cpcc_fund_event (
  rowid bigint AUTO_INCREMENT PRIMARY KEY,
  entity integer NOT NULL DEFAULT 1,
  fk_soc integer NOT NULL,
  object_type varchar(16) NOT NULL,
  object_id bigint NOT NULL,
  action_code varchar(32) NOT NULL,
  status_from varchar(16) NULL,
  status_to varchar(16) NULL,
  message text NULL,
  fk_user integer NOT NULL,
  date_action datetime NOT NULL,
  KEY idx_cpcc_fund_event_object (entity, object_type, object_id, date_action),
  KEY idx_cpcc_fund_event_customer (entity, fk_soc, date_action)
) ENGINE=innodb;
