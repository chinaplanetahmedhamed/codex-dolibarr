CREATE TABLE llx_cpo_task_notification (
  rowid bigint AUTO_INCREMENT PRIMARY KEY,
  entity integer NOT NULL DEFAULT 1,
  fk_user integer NOT NULL,
  fk_task bigint NOT NULL,
  fk_comment bigint NOT NULL,
  notification_type varchar(32) NOT NULL DEFAULT 'mention',
  date_seen datetime NULL,
  date_creation datetime NOT NULL,
  UNIQUE KEY uk_cpo_task_notification_event (entity,notification_type,fk_user,fk_comment),
  KEY idx_cpo_task_notification_inbox (entity,fk_user,date_seen,date_creation,rowid)
) ENGINE=innodb;
