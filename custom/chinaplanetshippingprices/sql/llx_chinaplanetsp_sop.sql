CREATE TABLE llx_chinaplanetsp_sop (
  rowid bigint AUTO_INCREMENT PRIMARY KEY,
  entity integer NOT NULL DEFAULT 1,
  ref varchar(64) NOT NULL,
  status smallint NOT NULL DEFAULT 0,
  latest_revision integer NOT NULL DEFAULT 1,
  published_revision integer NULL,
  fk_user_creat integer NOT NULL,
  fk_user_modif integer NULL,
  date_creation datetime NOT NULL,
  tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_chinaplanetsp_sop_ref (entity, ref),
  KEY idx_chinaplanetsp_sop_status (entity, status, tms)
) ENGINE=innodb;
