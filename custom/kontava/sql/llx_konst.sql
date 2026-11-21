create table if not exists llx_konst
(
  rowid                     integer AUTO_INCREMENT PRIMARY KEY,
  nom                      varchar(128) NOT NULL,
  valeur                     varchar(128) DEFAULT NULL,
  type                      varchar(32)  NOT NULL DEFAULT 'chaine',
  note                      varchar(128) DEFAULT NULL,
  tms TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_llx_konst_nom (nom)
)ENGINE=innodb;
