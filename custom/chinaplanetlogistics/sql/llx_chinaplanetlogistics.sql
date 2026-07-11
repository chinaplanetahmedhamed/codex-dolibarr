CREATE TABLE llx_chinaplanetlogistics (
    rowid integer AUTO_INCREMENT PRIMARY KEY,
    ref varchar(30) NOT NULL,
    entity integer DEFAULT 1 NOT NULL,
    fk_soc integer NOT NULL,
    fk_commande integer NOT NULL,
    fk_user_creat integer NOT NULL,
    fk_user_assign integer,
    container_no varchar(50),
    container_type varchar(20),
    nb_containers integer DEFAULT 1,
    booking_no varchar(50),
    bl_no varchar(50),
    shipping_line varchar(100),
    vessel_name varchar(100),
    voyage_no varchar(50),
    port_loading varchar(100),
    port_destination varchar(100),
    date_etd date,
    date_eta date,
    date_ata date,
    freetime_start date,
    freetime_end date,
    fk_statut integer DEFAULT 0,
    notes text,
    tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    date_creation datetime
) ENGINE=innodb;

ALTER TABLE llx_chinaplanetlogistics ADD INDEX idx_chinaplanetlogistics_fk_soc (fk_soc);
ALTER TABLE llx_chinaplanetlogistics ADD INDEX idx_chinaplanetlogistics_fk_commande (fk_commande);
ALTER TABLE llx_chinaplanetlogistics ADD UNIQUE INDEX uk_chinaplanetlogistics_ref (ref, entity);
