CREATE TABLE llx_cpo_customer_workspace_pref (
 rowid BIGINT AUTO_INCREMENT PRIMARY KEY,
 entity INT NOT NULL,
 fk_soc BIGINT NOT NULL,
 fk_user INT NOT NULL,
 starred TINYINT NOT NULL DEFAULT 0,
 position INT NOT NULL DEFAULT 2147483647,
 UNIQUE KEY uk_cpo_customer_workspace_pref (entity,fk_soc,fk_user),
 KEY idx_cpo_customer_workspace_pref_user (entity,fk_user,position)
) ENGINE=innodb;
