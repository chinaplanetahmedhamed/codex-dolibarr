CREATE TABLE llx_cpo_task_details (
 rowid bigint AUTO_INCREMENT PRIMARY KEY,
 entity integer NOT NULL,
 fk_task bigint NOT NULL,
 payload mediumtext NOT NULL,
 create_hash varchar(64) NULL,
 version integer NOT NULL DEFAULT 1,
 fk_user_creat integer NOT NULL,
 fk_user_modif integer NOT NULL,
 date_creation datetime NOT NULL,
 tms timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uk_cpo_task_details (entity, fk_task)
) ENGINE=innodb;
