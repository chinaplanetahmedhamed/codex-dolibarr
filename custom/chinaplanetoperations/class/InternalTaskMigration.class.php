<?php

/**
 * Idempotent M058 schema migration and readiness guard.
 *
 * The migration is intentionally additive. It never classifies a legacy task,
 * grants a right, removes a row, or performs DDL from a normal page request.
 */
class InternalTaskMigration
{
    /** @var DoliDB */
    private $db;

    /** @var string */
    private $prefix;

    public function __construct($db)
    {
        $this->db = $db;
        $this->prefix = defined('MAIN_DB_PREFIX') ? MAIN_DB_PREFIX : 'llx_';
    }

    public function migrate()
    {
        if (!$this->tableExists($this->prefix.'cpo_task')) {
            throw new RuntimeException('Internal task base table is missing.');
        }

        $columns = array(
            'fk_usergroup' => 'integer NULL AFTER fk_user_assign',
            'waiting_reason' => 'text NULL AFTER progress',
            'waiting_on' => 'varchar(32) NULL AFTER waiting_reason',
            'date_followup' => 'datetime NULL AFTER waiting_on',
            'fk_user_modif' => 'integer NULL AFTER fk_user_creat',
            'date_archived' => 'datetime NULL AFTER date_completed',
            'version' => 'integer NOT NULL DEFAULT 1 AFTER position',
        );
        foreach ($columns as $name => $definition) {
            if (!$this->columnExists($this->prefix.'cpo_task', $name)) {
                $this->mustQuery('ALTER TABLE '.$this->prefix.'cpo_task ADD COLUMN '.$name.' '.$definition);
            }
        }

        $this->mustQuery(
            'CREATE TABLE IF NOT EXISTS '.$this->prefix."cpo_task_team_manager (
                rowid bigint AUTO_INCREMENT PRIMARY KEY,
                entity integer NOT NULL DEFAULT 1,
                fk_usergroup integer NOT NULL,
                fk_user integer NOT NULL,
                status tinyint NOT NULL DEFAULT 1,
                fk_user_creat integer NOT NULL,
                fk_user_modif integer NULL,
                date_creation datetime NOT NULL,
                tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uk_cpo_task_team_manager (entity,fk_usergroup,fk_user),
                KEY idx_cpo_task_manager_user (entity,fk_user,status),
                KEY idx_cpo_task_manager_team (entity,fk_usergroup,status)
            ) ENGINE=innodb"
        );

        $this->mustQuery(
            'CREATE TABLE IF NOT EXISTS '.$this->prefix."cpo_task_asset (
                rowid bigint AUTO_INCREMENT PRIMARY KEY,
                entity integer NOT NULL DEFAULT 1,
                fk_task bigint NOT NULL,
                asset_ref varchar(64) NOT NULL,
                parent_asset_id bigint NULL,
                asset_type varchar(16) NOT NULL,
                original_name varchar(255) NOT NULL,
                stored_name varchar(128) NOT NULL,
                extension varchar(16) NOT NULL,
                mime_type varchar(128) NOT NULL,
                file_size bigint NOT NULL,
                sha256 char(64) NOT NULL,
                version_no integer NOT NULL DEFAULT 1,
                is_cover tinyint NOT NULL DEFAULT 0,
                status varchar(16) NOT NULL DEFAULT 'active',
                fk_user_creat integer NOT NULL,
                fk_user_modif integer NULL,
                date_creation datetime NOT NULL,
                tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uk_cpo_task_asset_stored (entity,stored_name),
                UNIQUE KEY uk_cpo_task_asset_version (entity,fk_task,asset_ref,version_no),
                KEY idx_cpo_task_asset_current (entity,fk_task,status,asset_ref,version_no),
                KEY idx_cpo_task_asset_cover (entity,fk_task,status,is_cover)
            ) ENGINE=innodb"
        );

        $indexes = array(
            'idx_cpo_task_own_board' => '(entity,fk_user_assign,date_archived,status,position)',
            'idx_cpo_task_team_board' => '(entity,fk_usergroup,date_archived,status,position)',
        );
        foreach ($indexes as $name => $definition) {
            if (!$this->indexExists($this->prefix.'cpo_task', $name)) {
                $this->mustQuery('ALTER TABLE '.$this->prefix.'cpo_task ADD KEY '.$name.' '.$definition);
            }
        }

        if (!$this->isReady(false)) {
            throw new RuntimeException('Internal task schema readiness check failed.');
        }
        return true;
    }

    public function isReady($requireRights = false)
    {
        $taskColumns = array(
            'rowid'=>array('bigint','NO',null,null,'auto_increment'),
            'fk_usergroup'=>array('int','YES',null,null),
            'waiting_reason'=>array('text','YES',null,null),
            'waiting_on'=>array('varchar','YES',null,32),
            'date_followup'=>array('datetime','YES',null,null),
            'fk_user_modif'=>array('int','YES',null,null),
            'date_archived'=>array('datetime','YES',null,null),
            'version'=>array('int','NO','1',null),
            'tms'=>array('timestamp','NO','CURRENT_TIMESTAMP',null,null),
        );
        if (!$this->columnsMatch($this->prefix.'cpo_task', $taskColumns)) return false;
        if (!$this->tableExists($this->prefix.'cpo_task_team_manager')) return false;
        if (!$this->tableExists($this->prefix.'cpo_task_asset')) return false;
        if (!$this->tableExists($this->prefix.'cpo_activity')) return false;
        foreach (array('cpo_task','cpo_task_team_manager','cpo_task_asset','cpo_activity') as $table) if (!$this->tableEngineIsInnoDb($this->prefix.$table)) return false;
        if (!$this->columnsMatch($this->prefix.'cpo_task_team_manager', array(
            'rowid'=>array('bigint','NO',null,null,'auto_increment'),
            'entity'=>array('int','NO','1',null),'fk_usergroup'=>array('int','NO',null,null),'fk_user'=>array('int','NO',null,null),
            'status'=>array('tinyint','NO','1',null),'fk_user_creat'=>array('int','NO',null,null),'fk_user_modif'=>array('int','YES',null,null),'date_creation'=>array('datetime','NO',null,null),'tms'=>array('timestamp','NO','CURRENT_TIMESTAMP',null,null),
        ))) return false;
        if (!$this->columnsMatch($this->prefix.'cpo_task_asset', array(
            'rowid'=>array('bigint','NO',null,null,'auto_increment'),
            'entity'=>array('int','NO','1',null),'fk_task'=>array('bigint','NO',null,null),'asset_ref'=>array('varchar','NO',null,64),'parent_asset_id'=>array('bigint','YES',null,null),
            'asset_type'=>array('varchar','NO',null,16),'original_name'=>array('varchar','NO',null,255),'stored_name'=>array('varchar','NO',null,128),'extension'=>array('varchar','NO',null,16),
            'mime_type'=>array('varchar','NO',null,128),'file_size'=>array('bigint','NO',null,null),'sha256'=>array('char','NO',null,64),'version_no'=>array('int','NO','1',null),
            'is_cover'=>array('tinyint','NO','0',null),'status'=>array('varchar','NO','active',16),'fk_user_creat'=>array('int','NO',null,null),'fk_user_modif'=>array('int','YES',null,null),'date_creation'=>array('datetime','NO',null,null),'tms'=>array('timestamp','NO','CURRENT_TIMESTAMP',null,null),
        ))) return false;
        if (!$this->columnsMatch($this->prefix.'cpo_activity', array(
            'rowid'=>array('bigint','NO',null,null,'auto_increment'),
            'entity'=>array('int','NO','1',null),'fk_operation'=>array('bigint','YES',null,null),'fk_task'=>array('bigint','YES',null,null),
            'action_code'=>array('varchar','NO',null,48),'message'=>array('text','YES',null,null),'visibility'=>array('varchar','NO','internal',16),
            'fk_user'=>array('int','NO',null,null),'date_action'=>array('datetime','NO',null,null),
        ))) return false;
        $indexes = array(
            $this->prefix.'cpo_task'=>array('PRIMARY'=>array('rowid',0),'idx_cpo_task_own_board'=>array('entity,fk_user_assign,date_archived,status,position',1),'idx_cpo_task_team_board'=>array('entity,fk_usergroup,date_archived,status,position',1)),
            $this->prefix.'cpo_task_team_manager'=>array('PRIMARY'=>array('rowid',0),'uk_cpo_task_team_manager'=>array('entity,fk_usergroup,fk_user',0),'idx_cpo_task_manager_user'=>array('entity,fk_user,status',1),'idx_cpo_task_manager_team'=>array('entity,fk_usergroup,status',1)),
            $this->prefix.'cpo_task_asset'=>array('PRIMARY'=>array('rowid',0),'uk_cpo_task_asset_stored'=>array('entity,stored_name',0),'uk_cpo_task_asset_version'=>array('entity,fk_task,asset_ref,version_no',0),'idx_cpo_task_asset_current'=>array('entity,fk_task,status,asset_ref,version_no',1),'idx_cpo_task_asset_cover'=>array('entity,fk_task,status,is_cover',1)),
            $this->prefix.'cpo_activity'=>array('PRIMARY'=>array('rowid',0),'idx_cpo_activity_task'=>array('entity,fk_task,date_action',1)),
        );
        foreach ($indexes as $table=>$definitions) foreach ($definitions as $name=>$definition) if (!$this->indexMatches($table,$name,$definition[0],$definition[1])) return false;
        foreach (array(500516,500517,500518,500519,500520,500521,500522) as $rightId) {
            if ($this->rightCollision($rightId)) return false;
        }
        if ($requireRights && !$this->rightsReady()) return false;
        return true;
    }

    public function rightsReady()
    {
        if (!$this->tableExists($this->prefix.'rights_def')) return false;
        foreach (self::rightCodes() as $rightId => $code) {
            $res=$this->db->query('SELECT module,perms,bydefault FROM '.$this->prefix.'rights_def WHERE id='.(int)$rightId);
            if(!$res)return false;$valid=false;
            while($row=$this->db->fetch_object($res))if((string)$row->module==='chinaplanetoperations'&&(string)$row->perms===$code&&(int)$row->bydefault===0)$valid=true;else return false;
            if(!$valid)return false;
        }
        return true;
    }

    /**
     * Before module rights are installed, an existing definition with the same
     * ID and another module/code is a hard collision. Missing definitions are
     * expected during a pre-init migration.
     */
    public function rightCollision($rightId)
    {
        if (!$this->tableExists($this->prefix.'rights_def')) return false;
        $sql = 'SELECT module,perms FROM '.$this->prefix.'rights_def WHERE id='.(int) $rightId;
        $res = $this->db->query($sql);
        if (!$res) throw new RuntimeException('Unable to inspect right definitions.');
        $expected = self::rightCodes();
        while ($row = $this->db->fetch_object($res)) {
            if ((string) $row->module !== 'chinaplanetoperations' || !isset($expected[(int) $rightId]) || (string) $row->perms !== $expected[(int) $rightId]) return true;
        }
        return false;
    }

    public static function rightCodes()
    {
        return array(
            500516 => 'task_read_own',
            500517 => 'task_update_own',
            500518 => 'task_upload_own',
            500519 => 'task_manage_team',
            500520 => 'task_archive',
            500521 => 'task_delete',
            500522 => 'task_admin',
        );
    }

    private function tableExists($table)
    {
        $sql = "SELECT COUNT(*) count_rows FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='".$this->db->escape($table)."'";
        return $this->count($sql) === 1;
    }

    private function columnExists($table, $column)
    {
        $sql = "SELECT COUNT(*) count_rows FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='".$this->db->escape($table)."' AND column_name='".$this->db->escape($column)."'";
        return $this->count($sql) === 1;
    }

    private function indexExists($table, $index)
    {
        $sql = "SELECT COUNT(*) count_rows FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='".$this->db->escape($table)."' AND index_name='".$this->db->escape($index)."'";
        return $this->count($sql) > 0;
    }

    private function columnsMatch($table, array $specifications)
    {
        foreach ($specifications as $column=>$specification) {
            $sql = "SELECT data_type,is_nullable,column_default,character_maximum_length,extra FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='".$this->db->escape($table)."' AND column_name='".$this->db->escape($column)."' LIMIT 1";
            $res = $this->db->query($sql);
            $row = $res ? $this->db->fetch_object($res) : null;
            if (!$row || strtolower((string)$row->data_type)!==$specification[0] || (string)$row->is_nullable!==$specification[1]) return false;
            $actualDefault = $row->column_default === null ? null : (string)$row->column_default;
            if ($actualDefault !== $specification[2]) return false;
            if ($specification[3] !== null && (int)$row->character_maximum_length !== (int)$specification[3]) return false;
            if (isset($specification[4]) && $specification[4] !== null && strtolower((string)$row->extra)!==strtolower((string)$specification[4])) return false;
        }
        return true;
    }

    private function indexMatches($table,$index,$columns,$nonUnique)
    {
        $sql = "SELECT GROUP_CONCAT(column_name ORDER BY seq_in_index SEPARATOR ',') index_columns,MIN(non_unique) non_unique FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='".$this->db->escape($table)."' AND index_name='".$this->db->escape($index)."' GROUP BY index_name";
        $res = $this->db->query($sql);
        $row = $res ? $this->db->fetch_object($res) : null;
        return $row && (string)$row->index_columns === (string)$columns && (int)$row->non_unique === (int)$nonUnique;
    }

    private function tableEngineIsInnoDb($table)
    {
        $sql="SELECT engine FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='".$this->db->escape($table)."' LIMIT 1";$res=$this->db->query($sql);$row=$res?$this->db->fetch_object($res):null;return $row&&strtolower((string)$row->engine)==='innodb';
    }

    private function count($sql)
    {
        $res = $this->db->query($sql);
        if (!$res) throw new RuntimeException('Unable to inspect internal task schema.');
        $row = $this->db->fetch_object($res);
        return $row ? (int) $row->count_rows : 0;
    }

    private function mustQuery($sql)
    {
        if (!$this->db->query($sql)) throw new RuntimeException('Internal task migration step failed.');
    }
}
