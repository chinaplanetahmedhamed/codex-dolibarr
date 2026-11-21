CREATE TABLE IF NOT EXISTS llx_cpo_supplier_workspace (
  entity INT NOT NULL,
  fk_supplier INT NOT NULL,
  PRIMARY KEY (entity,fk_supplier)
) ENGINE=InnoDB;
