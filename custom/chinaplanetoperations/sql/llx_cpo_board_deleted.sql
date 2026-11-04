CREATE TABLE llx_cpo_board_deleted (
 rowid BIGINT AUTO_INCREMENT PRIMARY KEY,
 entity INT NOT NULL,
 fk_board BIGINT NOT NULL,
 fk_user_delete INT NOT NULL,
 date_deleted DATETIME NOT NULL,
 UNIQUE KEY uk_cpo_board_deleted (entity,fk_board)
) ENGINE=innodb;
