-- UX-014 / UX-023: canonical card media and document metadata.
-- Physical files remain outside the web root; customer access must use scoped endpoints.
CREATE TABLE llx_cpkanban_asset (
    rowid              integer AUTO_INCREMENT PRIMARY KEY,
    entity             integer DEFAULT 1 NOT NULL,
    fk_soc             integer NOT NULL,
    fk_card            integer NOT NULL,
    fk_shipment        integer DEFAULT NULL,
    parent_asset_id    integer DEFAULT NULL,
    asset_type         varchar(32) NOT NULL,
    document_type      varchar(64) DEFAULT NULL,
    original_name      varchar(255) NOT NULL,
    stored_name        varchar(255) NOT NULL,
    mime_type          varchar(128) NOT NULL,
    file_size          bigint DEFAULT 0 NOT NULL,
    caption            varchar(255) DEFAULT NULL,
    milestone          varchar(64) DEFAULT NULL,
    version_no         integer DEFAULT 1 NOT NULL,
    review_status      varchar(24) DEFAULT 'under_review' NOT NULL,
    customer_visible   tinyint DEFAULT 0 NOT NULL,
    is_cover           tinyint DEFAULT 0 NOT NULL,
    status             tinyint DEFAULT 1 NOT NULL,
    fk_user_creat      integer NOT NULL,
    fk_user_modif      integer DEFAULT NULL,
    date_creation      datetime NOT NULL,
    tms                timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=innodb;

ALTER TABLE llx_cpkanban_asset ADD INDEX idx_cpkasset_owner (entity, fk_soc, fk_card, status);
ALTER TABLE llx_cpkanban_asset ADD INDEX idx_cpkasset_shipment (entity, fk_soc, fk_shipment);
ALTER TABLE llx_cpkanban_asset ADD INDEX idx_cpkasset_public (entity, fk_soc, fk_card, customer_visible, review_status, status);
ALTER TABLE llx_cpkanban_asset ADD INDEX idx_cpkasset_cover (entity, fk_soc, fk_card, is_cover, status);
ALTER TABLE llx_cpkanban_asset ADD INDEX idx_cpkasset_parent (parent_asset_id);
