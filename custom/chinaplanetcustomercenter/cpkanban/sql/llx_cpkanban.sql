-- ============================================================
-- China Planet Kanban (cpkanban) - Database schema
-- For Dolibarr 23+
-- Tables:
--   llx_cpkanban_column  : the Kanban columns / statuses (admin-managed)
--   llx_cpkanban_card    : project/task cards, linked to a customer (fk_soc)
--   llx_cpkanban_log     : activity history (column moves, etc.)
-- ============================================================

-- ---------- COLUMNS / STATUSES ----------
CREATE TABLE llx_cpkanban_column (
    rowid           integer AUTO_INCREMENT PRIMARY KEY,
    entity          integer DEFAULT 1 NOT NULL,
    code            varchar(32) NOT NULL,          -- machine code e.g. 'new','prog','ship'
    label           varchar(128) NOT NULL,         -- display label (Arabic)
    label_en        varchar(128),                  -- optional English label
    color           varchar(9) DEFAULT '#2980b9',  -- hex color of the column swatch
    position        integer DEFAULT 0 NOT NULL,    -- ordering on the board
    cust_visible    tinyint DEFAULT 1 NOT NULL,    -- 1 = visible to customer portal
    auto_progress   integer DEFAULT NULL,          -- % progress applied when a card lands here
    active          tinyint DEFAULT 1 NOT NULL,    -- 1 = active, 0 = hidden (keeps history)
    tms             timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=innodb;

ALTER TABLE llx_cpkanban_column ADD UNIQUE INDEX uk_cpkanban_column_code (code, entity);
ALTER TABLE llx_cpkanban_column ADD INDEX idx_cpkanban_column_pos (position);

-- ---------- CARDS (projects / tasks) ----------
CREATE TABLE llx_cpkanban_card (
    rowid            integer AUTO_INCREMENT PRIMARY KEY,
    ref              varchar(40) NOT NULL,
    entity           integer DEFAULT 1 NOT NULL,
    fk_soc           integer NOT NULL,             -- owner customer (third party) - drives data isolation
    fk_column        integer NOT NULL,             -- current column (llx_cpkanban_column.rowid)
    title            varchar(255) NOT NULL,
    description      text,
    priority         varchar(10) DEFAULT 'med',    -- 'high' | 'med' | 'low'
    progress         integer DEFAULT 0,            -- 0-100
    date_deadline    date,
    fk_user_assign   integer,                      -- internal staff assigned
    fk_user_creat    integer NOT NULL,
    cust_visible     tinyint DEFAULT 1 NOT NULL,   -- 1 = customer can see this card
    fk_shipment      integer DEFAULT NULL,         -- link to llx_chinaplanetlogistics.rowid (Phase 2F)
    fk_project       integer DEFAULT NULL,         -- optional link to a Dolibarr project (future)
    position         integer DEFAULT 0,            -- order within its column
    note_public      text,                         -- customer-visible note
    note_private     text,                         -- internal-only note
    status           integer DEFAULT 1 NOT NULL,   -- 0=draft/cancelled, 1=active, 9=archived
    date_creation    datetime,
    tms              timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=innodb;

ALTER TABLE llx_cpkanban_card ADD INDEX idx_cpkanban_card_fk_soc (fk_soc);
ALTER TABLE llx_cpkanban_card ADD INDEX idx_cpkanban_card_fk_column (fk_column);
ALTER TABLE llx_cpkanban_card ADD INDEX idx_cpkanban_card_fk_shipment (fk_shipment);
ALTER TABLE llx_cpkanban_card ADD UNIQUE INDEX uk_cpkanban_card_ref (ref, entity);

-- ---------- ACTIVITY LOG ----------
CREATE TABLE llx_cpkanban_log (
    rowid          integer AUTO_INCREMENT PRIMARY KEY,
    entity         integer DEFAULT 1 NOT NULL,
    fk_card        integer NOT NULL,
    fk_column_old  integer,
    fk_column_new  integer,
    action_type    varchar(32) DEFAULT 'move',     -- 'move' | 'create' | 'update' | 'file' | 'note'
    note           varchar(255),
    fk_user        integer NOT NULL,
    visible_cust   tinyint DEFAULT 1 NOT NULL,      -- whether this log line shows to customer
    date_action    datetime
) ENGINE=innodb;

ALTER TABLE llx_cpkanban_log ADD INDEX idx_cpkanban_log_fk_card (fk_card);
