-- Product-kind sourcing relationships. Unknown prices never enter purchasing prices.
CREATE TABLE IF NOT EXISTS llx_cpo_product_supplier_candidate (
    rowid integer AUTO_INCREMENT PRIMARY KEY,
    entity integer NOT NULL,
    fk_product integer NOT NULL,
    fk_supplier integer NOT NULL,
    product_kind varchar(32) NOT NULL,
    evidence_url varchar(512) NOT NULL,
    batch_key varchar(64) NOT NULL,
    fk_user_creat integer NOT NULL,
    date_creation datetime NOT NULL,
    status integer NOT NULL DEFAULT 1,
    UNIQUE KEY uk_cpo_product_supplier_candidate (entity, fk_product, fk_supplier),
    KEY idx_cpo_supplier_candidate (entity, fk_supplier, status)
) ENGINE=innodb;
