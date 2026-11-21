CREATE TABLE llx_cpo_customer_workspace_meta (
 rowid BIGINT AUTO_INCREMENT PRIMARY KEY,
 entity INT NOT NULL,
 fk_soc BIGINT NOT NULL,
 priority VARCHAR(16) NOT NULL DEFAULT 'normal',
 version INT NOT NULL DEFAULT 1,
 fk_user_modif INT NOT NULL,
 date_creation DATETIME NOT NULL,
 tms TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uk_cpo_customer_workspace_meta (entity,fk_soc)
) ENGINE=innodb;
