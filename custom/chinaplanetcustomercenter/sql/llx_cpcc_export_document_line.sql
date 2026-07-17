CREATE TABLE IF NOT EXISTS llx_cpcc_export_document_line (
  rowid integer AUTO_INCREMENT PRIMARY KEY,
  fk_document integer NOT NULL,
  position integer NOT NULL DEFAULT 0,
  description varchar(255) NOT NULL,
  hs_code varchar(32) NULL,
  quantity decimal(24,8) NOT NULL DEFAULT 0,
  unit varchar(16) NULL,
  unit_price decimal(24,8) NOT NULL DEFAULT 0,
  total_amount decimal(24,8) NOT NULL DEFAULT 0,
  cartons decimal(24,8) NULL,
  net_weight decimal(24,8) NULL,
  gross_weight decimal(24,8) NULL,
  country_origin varchar(64) NULL,
  date_creation datetime NOT NULL,
  tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_cpcc_export_line_document (fk_document, position)
) ENGINE=innodb;
