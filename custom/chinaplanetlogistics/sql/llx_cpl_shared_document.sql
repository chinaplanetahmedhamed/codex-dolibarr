CREATE TABLE IF NOT EXISTS llx_cpl_shared_document (
 rowid bigint AUTO_INCREMENT PRIMARY KEY,
 entity integer NOT NULL,
 fk_shipment integer NOT NULL,
 display_name varchar(255) NOT NULL,
 current_version_id bigint NULL,
 revision integer NOT NULL DEFAULT 1,
 status varchar(16) NOT NULL DEFAULT 'active',
 fk_user_creat integer NOT NULL,
 fk_user_modif integer NOT NULL,
 date_creation datetime NOT NULL,
 tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 KEY idx_cpl_shared_ship (entity,fk_shipment,status)
) ENGINE=innodb;
CREATE TABLE IF NOT EXISTS llx_cpl_shared_document_version (
 rowid bigint AUTO_INCREMENT PRIMARY KEY,
 entity integer NOT NULL,
 fk_document bigint NOT NULL,
 version_no integer NOT NULL,
 storage_key varchar(512) NOT NULL,
 original_name varchar(255) NOT NULL,
 mime_type varchar(150) NOT NULL,
 file_size bigint NOT NULL,
 file_sha256 char(64) NOT NULL,
 item_kind varchar(16) NOT NULL,
 source_origin varchar(16) NOT NULL DEFAULT 'staff',
 fk_user_creat integer NOT NULL,
 date_creation datetime NOT NULL,
 UNIQUE KEY uk_cpl_shared_version (entity,fk_document,version_no),
 UNIQUE KEY uk_cpl_shared_storage (entity,storage_key)
) ENGINE=innodb;
CREATE TABLE IF NOT EXISTS llx_cpl_shared_document_event (
 rowid bigint AUTO_INCREMENT PRIMARY KEY,
 entity integer NOT NULL,
 fk_document bigint NOT NULL,
 fk_version bigint NULL,
 action_code varchar(32) NOT NULL,
 fk_user integer NOT NULL,
 date_creation datetime NOT NULL,
 KEY idx_cpl_shared_event (entity,fk_document,rowid)
) ENGINE=innodb;
CREATE TABLE IF NOT EXISTS llx_cpo_task_context (
 rowid bigint AUTO_INCREMENT PRIMARY KEY,
 entity integer NOT NULL,
 fk_task bigint NOT NULL,
 fk_soc integer NULL,
 fk_project integer NULL,
 fk_shipment integer NULL,
 fk_cover_document bigint NULL,
 version integer NOT NULL DEFAULT 1,
 fk_user_modif integer NOT NULL,
 tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uk_cpo_task_context (entity,fk_task),
 KEY idx_cpo_context_ship (entity,fk_shipment)
) ENGINE=innodb;
