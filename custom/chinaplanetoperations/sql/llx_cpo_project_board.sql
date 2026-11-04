CREATE TABLE llx_cpo_project_board (
  rowid BIGINT AUTO_INCREMENT PRIMARY KEY,
  entity INT NOT NULL,
  fk_project INT NOT NULL,
  fk_board BIGINT NOT NULL,
  UNIQUE KEY uk_cpo_project_board_project (entity,fk_project),
  UNIQUE KEY uk_cpo_project_board_board (entity,fk_board)
) ENGINE=InnoDB;
