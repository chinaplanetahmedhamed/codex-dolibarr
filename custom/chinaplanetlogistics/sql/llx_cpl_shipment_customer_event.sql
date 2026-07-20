CREATE TABLE IF NOT EXISTS llx_cpl_shipment_customer_event (
    rowid bigint AUTO_INCREMENT PRIMARY KEY,
    entity integer NOT NULL DEFAULT 1,
    fk_shipment integer NOT NULL,
    fk_soc integer NOT NULL,
    fk_public_document bigint NULL,
    event_type varchar(48) NOT NULL,
    status_code varchar(48) NULL,
    note_public text NULL,
    actor_type varchar(16) NOT NULL,
    fk_user integer NOT NULL,
    customer_visible tinyint NOT NULL DEFAULT 1,
    event_at datetime NOT NULL,
    date_creation datetime NOT NULL,
    tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_cpl_customer_event_owner (entity, fk_soc, fk_shipment, customer_visible, event_at),
    INDEX idx_cpl_customer_event_document (entity, fk_public_document, event_at),
    INDEX idx_cpl_customer_event_type (entity, fk_shipment, event_type, status_code)
) ENGINE=innodb;
