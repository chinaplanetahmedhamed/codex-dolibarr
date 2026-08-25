CREATE TABLE llx_chinaplanetsp_port_alias (
  rowid bigint AUTO_INCREMENT PRIMARY KEY,
  entity integer NOT NULL DEFAULT 1,
  fk_port bigint NOT NULL,
  alias_name varchar(160) NOT NULL,
  normalized_alias varchar(160) NOT NULL,
  alias_source varchar(16) NOT NULL DEFAULT 'manual',
  status smallint NOT NULL DEFAULT 1,
  fk_user_creat integer NOT NULL,
  fk_user_modif integer NULL,
  date_creation datetime NOT NULL,
  tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_chinaplanetsp_port_alias (entity, normalized_alias),
  KEY idx_chinaplanetsp_port_alias_port (entity, fk_port, status)
) ENGINE=innodb;
