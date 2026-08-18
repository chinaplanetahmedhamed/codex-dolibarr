CREATE TABLE llx_cpo_task_comment_mention (
  rowid bigint AUTO_INCREMENT PRIMARY KEY,
  entity integer NOT NULL DEFAULT 1,
  fk_comment bigint NOT NULL,
  fk_user integer NOT NULL,
  date_creation datetime NOT NULL,
  UNIQUE KEY uk_cpo_task_comment_mention (entity,fk_comment,fk_user),
  KEY idx_cpo_task_mention_recipient (entity,fk_user,fk_comment)
) ENGINE=innodb;
