CREATE TABLE IF NOT EXISTS llx_cpo_supplier_board (
  rowid BIGINT AUTO_INCREMENT PRIMARY KEY,
  entity INT NOT NULL,
  fk_supplier_order INT NOT NULL,
  fk_board BIGINT NOT NULL,
  fk_supplier INT NOT NULL,
  fk_project INT NOT NULL,
  UNIQUE KEY uk_cpo_supplier_board_order (entity,fk_supplier_order),
  UNIQUE KEY uk_cpo_supplier_board_board (entity,fk_board)
) ENGINE=InnoDB;
