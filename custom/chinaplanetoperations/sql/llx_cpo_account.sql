CREATE TABLE llx_cpo_account_entry (
 rowid BIGINT AUTO_INCREMENT PRIMARY KEY,
 entity INT NOT NULL,
 fk_soc INT NOT NULL,
 payload LONGTEXT NOT NULL,
 status VARCHAR(12) NOT NULL,
 version INT NOT NULL DEFAULT 1,
 allocation_version INT NOT NULL DEFAULT 1,
 reversal_of BIGINT NULL,
 fk_user_creat INT NOT NULL,
 date_creation DATETIME NOT NULL,
 UNIQUE KEY uk_cpo_account_reversal(entity,reversal_of),
 KEY idx_cpo_account_customer(entity,fk_soc,rowid)
) ENGINE=InnoDB;
CREATE TABLE llx_cpo_account_allocation (
 rowid BIGINT AUTO_INCREMENT PRIMARY KEY,
 entity INT NOT NULL,
 fk_entry BIGINT NOT NULL,
 batch INT NOT NULL,
 position INT NOT NULL,
 fk_project INT NULL,
 fk_task BIGINT NULL,
 original_minor DECIMAL(24,0) NOT NULL,
 base_minor DECIMAL(24,0) NOT NULL,
 rounding_minor DECIMAL(24,0) NOT NULL,
 UNIQUE KEY uk_cpo_account_line(entity,fk_entry,batch,position),
 KEY idx_cpo_account_project(entity,fk_project,fk_task)
) ENGINE=InnoDB;
CREATE TABLE llx_cpo_account_command (
 rowid BIGINT AUTO_INCREMENT PRIMARY KEY,
 entity INT NOT NULL,
 fk_user INT NOT NULL,
 request_key VARCHAR(64) NOT NULL,
 request_hash CHAR(64) NOT NULL,
 result_json LONGTEXT NOT NULL,
 UNIQUE KEY uk_cpo_account_command(entity,fk_user,request_key)
) ENGINE=InnoDB;
CREATE TABLE llx_cpo_account_audit (
 rowid BIGINT AUTO_INCREMENT PRIMARY KEY,
 entity INT NOT NULL,
 fk_soc INT NOT NULL,
 fk_entry BIGINT NULL,
 fk_user INT NOT NULL,
 action VARCHAR(32) NOT NULL,
 reason VARCHAR(500) NOT NULL,
 detail_json LONGTEXT NOT NULL,
 date_creation DATETIME NOT NULL,
 KEY idx_cpo_account_audit(entity,fk_soc,rowid)
) ENGINE=InnoDB;
CREATE TABLE llx_cpo_account_column (
 rowid BIGINT AUTO_INCREMENT PRIMARY KEY,
 entity INT NOT NULL,
 fk_soc INT NOT NULL,
 label VARCHAR(60) NOT NULL,
 UNIQUE KEY uk_cpo_account_column(entity,fk_soc,label)
) ENGINE=InnoDB;
