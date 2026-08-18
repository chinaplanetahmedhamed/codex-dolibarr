CREATE TABLE llx_cpo_task_checklist_item (
  rowid bigint AUTO_INCREMENT PRIMARY KEY,
  entity integer NOT NULL DEFAULT 1,
  fk_checklist bigint NOT NULL,
  label varchar(255) NOT NULL,
  position integer NOT NULL DEFAULT 0,
  completed tinyint NOT NULL DEFAULT 0,
  fk_user_completed integer NULL,
  date_completed datetime NULL,
  active tinyint NOT NULL DEFAULT 1,
  version integer NOT NULL DEFAULT 1,
  fk_user_creat integer NOT NULL,
  fk_user_modif integer NULL,
  date_creation datetime NOT NULL,
  tms timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_cpo_task_checkitem_list (entity,fk_checklist,active,position,rowid)
) ENGINE=innodb;
