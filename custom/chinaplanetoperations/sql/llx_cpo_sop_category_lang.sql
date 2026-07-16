CREATE TABLE llx_cpo_sop_category_lang (
  rowid bigint AUTO_INCREMENT PRIMARY KEY,
  entity integer NOT NULL DEFAULT 1,
  fk_category bigint NOT NULL,
  lang_code varchar(8) NOT NULL,
  label varchar(160) NOT NULL,
  description text NULL,
  UNIQUE KEY uk_cpo_sopcatlang (entity, fk_category, lang_code),
  KEY idx_cpo_sopcatlang_lookup (entity, lang_code, label)
) ENGINE=innodb;
