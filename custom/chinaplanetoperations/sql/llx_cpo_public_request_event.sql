CREATE TABLE llx_cpo_public_request_event (
  rowid bigint AUTO_INCREMENT PRIMARY KEY,
  entity integer NOT NULL DEFAULT 1,
  fk_request bigint NOT NULL,
  event_type varchar(64) NOT NULL,
  state_from varchar(32) NULL,
  state_to varchar(32) NULL,
  note_public text NULL,
  note_private text NULL,
  metadata_json text NULL,
  customer_visible tinyint NOT NULL DEFAULT 0,
  fk_user_creat integer NOT NULL,
  date_creation datetime NOT NULL,
  tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_cpo_request_event_request (entity, fk_request, date_creation),
  KEY idx_cpo_request_event_portal (entity, fk_request, customer_visible, date_creation)
) ENGINE=innodb;
