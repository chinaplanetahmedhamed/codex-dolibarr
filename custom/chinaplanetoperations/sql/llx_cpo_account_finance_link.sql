CREATE TABLE IF NOT EXISTS llx_cpo_account_finance_link (
 entity INT NOT NULL,
 fk_soc INT NOT NULL,
 fk_entry BIGINT NOT NULL,
 entry_version INT NOT NULL,
 source_key VARCHAR(160) NOT NULL,
 active_key VARCHAR(160) NULL,
 fk_user INT NOT NULL,
 date_creation DATETIME NOT NULL,
 PRIMARY KEY (entity, fk_entry),
 UNIQUE KEY uk_cpo_finance_active_source (entity, active_key),
 KEY idx_cpo_finance_customer (entity, fk_soc)
) ENGINE=InnoDB;
