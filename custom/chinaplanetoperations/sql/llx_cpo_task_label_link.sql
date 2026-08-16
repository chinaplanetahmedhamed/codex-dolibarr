CREATE TABLE llx_cpo_task_label_link (
  rowid bigint AUTO_INCREMENT PRIMARY KEY,
  entity integer NOT NULL DEFAULT 1,
  fk_task bigint NOT NULL,
  fk_label bigint NOT NULL,
  fk_user_creat integer NOT NULL,
  date_creation datetime NOT NULL,
  UNIQUE KEY uk_cpo_task_label_link (entity,fk_task,fk_label),
  KEY idx_cpo_task_label_filter (entity,fk_label,fk_task)
) ENGINE=innodb;
