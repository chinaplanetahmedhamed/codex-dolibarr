-- Explicit mapping between native product (type 0) and supplier (type 1) categories.
-- No product-supplier pairs or financial fields are materialized here.
CREATE TABLE llx_cpo_supplier_category_map (
  rowid integer AUTO_INCREMENT PRIMARY KEY,
  entity integer NOT NULL,
  fk_product_category integer NOT NULL,
  fk_supplier_category integer NOT NULL,
  include_descendants integer NOT NULL DEFAULT 0,
  status integer NOT NULL DEFAULT 1,
  version integer NOT NULL DEFAULT 1,
  fk_user_creat integer NOT NULL,
  date_creation datetime NOT NULL,
  fk_user_modif integer NOT NULL,
  date_modification datetime NOT NULL,
  UNIQUE KEY uk_cpo_supplier_category (entity,fk_product_category,fk_supplier_category),
  KEY idx_cpo_category_supplier (entity,status)
) ENGINE=innodb;
