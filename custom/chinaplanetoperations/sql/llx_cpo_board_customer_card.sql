CREATE TABLE llx_cpo_board_customer_card (
 entity INTEGER NOT NULL,
 fk_task BIGINT NOT NULL,
 fk_board BIGINT NOT NULL,
 fk_project INTEGER NOT NULL,
 fk_soc INTEGER NOT NULL,
 public_title VARCHAR(180) NOT NULL,
 version INTEGER NOT NULL DEFAULT 1,
 fk_user_modif INTEGER NOT NULL,
 date_modified DATETIME NOT NULL,
 PRIMARY KEY (entity, fk_task)
) ENGINE=InnoDB;
