CREATE TABLE llx_cpo_operation_data (
  rowid bigint AUTO_INCREMENT PRIMARY KEY,
  entity integer NOT NULL DEFAULT 1,
  fk_operation bigint NOT NULL,
  section_code varchar(48) NOT NULL,
  data_json longtext NULL,
  fk_user_modif integer NULL,
  tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_cpo_operation_data (entity, fk_operation, section_code),
  KEY idx_cpo_operation_data_operation (entity, fk_operation)
) ENGINE=innodb;
