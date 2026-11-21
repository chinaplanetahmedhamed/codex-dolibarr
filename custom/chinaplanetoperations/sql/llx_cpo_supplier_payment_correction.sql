CREATE TABLE IF NOT EXISTS llx_cpo_supplier_payment_correction (
  rowid BIGINT AUTO_INCREMENT PRIMARY KEY,
  entity INT NOT NULL,
  fk_order INT NOT NULL,
  fk_invoice INT NOT NULL,
  fk_payment INT NOT NULL,
  fk_replacement INT NULL,
  fk_user INT NOT NULL,
  action VARCHAR(16) NOT NULL,
  reason VARCHAR(500) NOT NULL,
  detail_json LONGTEXT NOT NULL,
  request_hash CHAR(64) NOT NULL,
  date_creation DATETIME NOT NULL,
  UNIQUE KEY uk_cpo_supplier_correction_payment (entity,fk_payment),
  KEY idx_cpo_supplier_correction_order (entity,fk_order,rowid)
) ENGINE=InnoDB;
