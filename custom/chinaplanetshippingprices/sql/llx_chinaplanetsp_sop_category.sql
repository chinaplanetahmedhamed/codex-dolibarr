CREATE TABLE llx_chinaplanetsp_sop_category (
  rowid bigint AUTO_INCREMENT PRIMARY KEY,
  entity integer NOT NULL DEFAULT 1,
  code varchar(64) NOT NULL,
  label varchar(160) NOT NULL,
  label_ar varchar(160) NULL,
  description text NULL,
  position integer NOT NULL DEFAULT 100,
  status smallint NOT NULL DEFAULT 1,
  fk_user_creat integer NOT NULL,
  fk_user_modif integer NULL,
  date_creation datetime NOT NULL,
  tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_chinaplanetsp_sopcat_code (entity, code),
  KEY idx_chinaplanetsp_sopcat_status (entity, status, position)
) ENGINE=innodb;
