CREATE TABLE llx_cpo_task_comment (
  rowid bigint AUTO_INCREMENT PRIMARY KEY,
  entity integer NOT NULL DEFAULT 1,
  fk_task bigint NOT NULL,
  body text NOT NULL,
  request_key char(36) NOT NULL,
  request_hash char(64) NOT NULL,
  visibility varchar(16) NOT NULL DEFAULT 'visible',
  redaction_reason varchar(500) NULL,
  fk_user_redact integer NULL,
  date_redacted datetime NULL,
  version integer NOT NULL DEFAULT 1,
  fk_user_creat integer NOT NULL,
  date_creation datetime NOT NULL,
  tms timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_cpo_task_comment_request (entity,fk_user_creat,request_key),
  KEY idx_cpo_task_comment_task (entity,fk_task,date_creation,rowid),
  KEY idx_cpo_task_comment_author (entity,fk_user_creat,date_creation,rowid)
) ENGINE=innodb;
