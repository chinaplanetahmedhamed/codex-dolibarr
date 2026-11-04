CREATE TABLE llx_cpo_customer_workspace_appearance (
 rowid BIGINT AUTO_INCREMENT PRIMARY KEY,
 entity INT NOT NULL,
 fk_soc BIGINT NOT NULL,
 background VARCHAR(7) NOT NULL DEFAULT '#182b4e',
 mime_type VARCHAR(16) NULL,
 image_hash VARCHAR(64) NULL,
 image_data MEDIUMTEXT NULL,
 version INT NOT NULL DEFAULT 1,
 fk_user_modif INT NOT NULL,
 date_modified DATETIME NOT NULL,
 UNIQUE KEY uk_cpo_customer_workspace_appearance (entity,fk_soc)
) ENGINE=innodb;
