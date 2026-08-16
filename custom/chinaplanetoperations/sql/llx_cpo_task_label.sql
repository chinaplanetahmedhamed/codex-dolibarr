CREATE TABLE llx_cpo_task_label (
  rowid bigint AUTO_INCREMENT PRIMARY KEY,
  entity integer NOT NULL DEFAULT 1,
  code varchar(64) NOT NULL,
  label_en varchar(255) NOT NULL,
  label_ar varchar(255) NOT NULL,
  label_zh varchar(255) NOT NULL,
  color char(7) NOT NULL DEFAULT '#2980B9',
  active tinyint NOT NULL DEFAULT 1,
  version integer NOT NULL DEFAULT 1,
  fk_user_creat integer NOT NULL,
  fk_user_modif integer NULL,
  date_creation datetime NOT NULL,
  tms timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_cpo_task_label_code (entity,code),
  KEY idx_cpo_task_label_list (entity,active,code,rowid)
) ENGINE=innodb;
