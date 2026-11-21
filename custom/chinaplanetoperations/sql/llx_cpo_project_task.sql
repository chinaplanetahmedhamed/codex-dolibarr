CREATE TABLE llx_cpo_project_task (
 rowid BIGINT AUTO_INCREMENT PRIMARY KEY,
 entity INT NOT NULL,
 fk_task BIGINT NOT NULL,
 fk_project BIGINT NOT NULL,
 fk_native_task BIGINT NOT NULL,
 fk_user_creat INT NOT NULL,
 date_creation DATETIME NOT NULL,
 UNIQUE KEY uk_cpo_project_task_internal (entity,fk_task),
 UNIQUE KEY uk_cpo_project_task_native (entity,fk_native_task),
 KEY idx_cpo_project_task_project (entity,fk_project)
) ENGINE=innodb;
