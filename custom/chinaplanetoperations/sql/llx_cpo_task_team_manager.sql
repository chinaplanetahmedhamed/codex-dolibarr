CREATE TABLE llx_cpo_task_team_manager (
  rowid bigint AUTO_INCREMENT PRIMARY KEY,
  entity integer NOT NULL DEFAULT 1,
  fk_usergroup integer NOT NULL,
  fk_user integer NOT NULL,
  status tinyint NOT NULL DEFAULT 1,
  fk_user_creat integer NOT NULL,
  fk_user_modif integer NULL,
  date_creation datetime NOT NULL,
  tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_cpo_task_team_manager (entity, fk_usergroup, fk_user),
  KEY idx_cpo_task_manager_user (entity, fk_user, status),
  KEY idx_cpo_task_manager_team (entity, fk_usergroup, status)
) ENGINE=innodb;
