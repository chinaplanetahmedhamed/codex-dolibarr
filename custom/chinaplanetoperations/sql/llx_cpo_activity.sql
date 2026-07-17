CREATE TABLE llx_cpo_activity (
  rowid bigint AUTO_INCREMENT PRIMARY KEY,
  entity integer NOT NULL DEFAULT 1,
  fk_operation bigint NULL,
  fk_task bigint NULL,
  action_code varchar(48) NOT NULL,
  message text NULL,
  visibility varchar(16) NOT NULL DEFAULT 'internal',
  fk_user integer NOT NULL,
  date_action datetime NOT NULL,
  KEY idx_cpo_activity_operation (entity, fk_operation, date_action),
  KEY idx_cpo_activity_task (entity, fk_task, date_action)
) ENGINE=innodb;
