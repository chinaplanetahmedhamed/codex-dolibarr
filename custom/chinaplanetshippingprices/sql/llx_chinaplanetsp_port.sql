CREATE TABLE llx_chinaplanetsp_port (
  rowid bigint AUTO_INCREMENT PRIMARY KEY,
  entity integer NOT NULL DEFAULT 1,
  port_name varchar(160) NOT NULL,
  port_code varchar(16) NULL,
  country_code char(2) NOT NULL,
  aliases text NULL,
  status smallint NOT NULL DEFAULT 1,
  fk_user_creat integer NOT NULL,
  fk_user_modif integer NULL,
  date_creation datetime NOT NULL,
  tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_chinaplanetsp_port (entity, port_name),
  KEY idx_chinaplanetsp_port_country (entity, country_code, status)
) ENGINE=innodb;
