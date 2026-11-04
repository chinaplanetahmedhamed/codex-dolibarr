CREATE TABLE llx_cpo_board_completion (
 rowid BIGINT AUTO_INCREMENT PRIMARY KEY,
 entity INT NOT NULL,
 fk_board BIGINT NOT NULL,
 completed TINYINT NOT NULL DEFAULT 0,
 fk_user_modif INT NOT NULL,
 date_modified DATETIME NOT NULL,
 UNIQUE KEY uk_cpo_board_completion (entity,fk_board)
) ENGINE=innodb;
