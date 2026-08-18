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

    /** @var string Bounded structural reason for the latest readiness result. */
    private $readinessReason = 'NOT_CHECKED';

    public function __construct($db)
    {
        $this->db = $db;
        $this->prefix = defined('MAIN_DB_PREFIX') ? MAIN_DB_PREFIX : 'llx_';
    }

    public function migrate()
    {
        $lock = 'cpo_m059_stage_migration';
        $res = $this->db->query("SELECT GET_LOCK('".$this->db->escape($lock)."',10) acquired");
        $row = $res ? $this->db->fetch_object($res) : null;
        if (!$row || (int)$row->acquired !== 1) throw new RuntimeException('Internal task migration is already running.');
        try {
            foreach (array(500523,500524) as $rightId) {
                if ($this->rightCollision($rightId)) throw new RuntimeException('Internal task right definition collision.');
            }
            return $this->migrateUnlocked();
        } finally {
            if (!$this->db->query("SELECT RELEASE_LOCK('".$this->db->escape($lock)."')")) throw new RuntimeException('Unable to release internal task migration lock.');
        }
    }

    private function migrateUnlocked()
    {
        if (!$this->tableExists($this->prefix.'cpo_task')) {
            throw new RuntimeException('Internal task base table is missing.');
        }

        $columns = array(
            'fk_usergroup' => 'integer NULL AFTER fk_user_assign',
            'fk_stage' => 'bigint NULL AFTER fk_usergroup',
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

        $this->mustQuery(
            'CREATE TABLE IF NOT EXISTS '.$this->prefix."cpo_task_stage (
                rowid bigint AUTO_INCREMENT PRIMARY KEY,
                entity integer NOT NULL DEFAULT 1,
                code varchar(64) NOT NULL,
                label_en varchar(255) NOT NULL,
                label_ar varchar(255) NOT NULL,
                label_zh varchar(255) NOT NULL,
                color varchar(9) NOT NULL DEFAULT '#2980b9',
                position integer NOT NULL DEFAULT 0,
                behavior varchar(16) NOT NULL,
                is_system tinyint NOT NULL DEFAULT 0,
                active tinyint NOT NULL DEFAULT 1,
                version integer NOT NULL DEFAULT 1,
                fk_user_creat integer NOT NULL,
                fk_user_modif integer NULL,
                date_creation datetime NOT NULL,
                tms timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uk_cpo_task_stage_code (entity,code),
                KEY idx_cpo_task_stage_board (entity,active,position,rowid)
            ) ENGINE=innodb"
        );

        $this->createCollaborationTables();
        $this->enforceTimestampNullability();

        $indexes = array(
            'idx_cpo_task_own_board' => '(entity,fk_user_assign,date_archived,status,position)',
            'idx_cpo_task_team_board' => '(entity,fk_usergroup,date_archived,status,position)',
            'idx_cpo_task_stage_tasks' => '(entity,fk_stage,date_archived,status,position)',
        );
        foreach ($indexes as $name => $definition) {
            if (!$this->indexExists($this->prefix.'cpo_task', $name)) {
                $this->mustQuery('ALTER TABLE '.$this->prefix.'cpo_task ADD KEY '.$name.' '.$definition);
            }
        }

        $this->seedAndBackfillStages();

        if (!$this->isReady(false)) {
            throw new RuntimeException('Internal task schema readiness check failed: '.$this->readinessReason().'.');
        }
        return true;
    }

    public function isReady($requireRights = false)
    {
        $this->readinessReason = 'UNKNOWN';
        if (!function_exists('mb_check_encoding') || !function_exists('mb_strlen')) return $this->notReady('MBSTRING_REQUIRED');
        $taskColumns = array(
            'rowid'=>array('bigint','NO',null,null,'auto_increment'),
            'fk_usergroup'=>array('int','YES',null,null),
            'fk_stage'=>array('bigint','YES',null,null),
            'waiting_reason'=>array('text','YES',null,null),
            'waiting_on'=>array('varchar','YES',null,32),
            'date_followup'=>array('datetime','YES',null,null),
            'fk_user_modif'=>array('int','YES',null,null),
            'date_archived'=>array('datetime','YES',null,null),
            'version'=>array('int','NO','1',null),
            'tms'=>array('timestamp','NO','CURRENT_TIMESTAMP',null,'on update current_timestamp'),
        );
        if (!$this->columnsMatch($this->prefix.'cpo_task', $taskColumns)) return false;
        if (!$this->tableExists($this->prefix.'cpo_task_team_manager')) return $this->notReady('CPO_TASK_TEAM_MANAGER_MISSING');
        if (!$this->tableExists($this->prefix.'cpo_task_asset')) return $this->notReady('CPO_TASK_ASSET_MISSING');
        if (!$this->tableExists($this->prefix.'cpo_task_stage')) return $this->notReady('CPO_TASK_STAGE_MISSING');
        foreach (self::collaborationTables() as $table) if (!$this->tableExists($this->prefix.$table)) return $this->notReady(strtoupper($table).'_MISSING');
        if (!$this->tableExists($this->prefix.'cpo_activity')) return $this->notReady('CPO_ACTIVITY_MISSING');
        foreach (array_merge(array('cpo_task','cpo_task_team_manager','cpo_task_asset','cpo_task_stage','cpo_activity'), self::collaborationTables()) as $table) if (!$this->tableEngineIsInnoDb($this->prefix.$table)) return $this->notReady(strtoupper($table).'_ENGINE');
        if (!$this->columnsMatch($this->prefix.'cpo_task_team_manager', array(
            'rowid'=>array('bigint','NO',null,null,'auto_increment'),
            'entity'=>array('int','NO','1',null),'fk_usergroup'=>array('int','NO',null,null),'fk_user'=>array('int','NO',null,null),
            'status'=>array('tinyint','NO','1',null),'fk_user_creat'=>array('int','NO',null,null),'fk_user_modif'=>array('int','YES',null,null),'date_creation'=>array('datetime','NO',null,null),'tms'=>array('timestamp','NO','CURRENT_TIMESTAMP',null,'on update current_timestamp'),
        ))) return $this->notReady('CPO_TASK_TEAM_MANAGER_COLUMNS');
        if (!$this->columnsMatch($this->prefix.'cpo_task_asset', array(
            'rowid'=>array('bigint','NO',null,null,'auto_increment'),
            'entity'=>array('int','NO','1',null),'fk_task'=>array('bigint','NO',null,null),'asset_ref'=>array('varchar','NO',null,64),'parent_asset_id'=>array('bigint','YES',null,null),
            'asset_type'=>array('varchar','NO',null,16),'original_name'=>array('varchar','NO',null,255),'stored_name'=>array('varchar','NO',null,128),'extension'=>array('varchar','NO',null,16),
            'mime_type'=>array('varchar','NO',null,128),'file_size'=>array('bigint','NO',null,null),'sha256'=>array('char','NO',null,64),'version_no'=>array('int','NO','1',null),
            'is_cover'=>array('tinyint','NO','0',null),'status'=>array('varchar','NO','active',16),'fk_user_creat'=>array('int','NO',null,null),'fk_user_modif'=>array('int','YES',null,null),'date_creation'=>array('datetime','NO',null,null),'tms'=>array('timestamp','NO','CURRENT_TIMESTAMP',null,'on update current_timestamp'),
        ))) return $this->notReady('CPO_TASK_ASSET_COLUMNS');
        if (!$this->columnsMatch($this->prefix.'cpo_task_stage', array(
            'rowid'=>array('bigint','NO',null,null,'auto_increment'),'entity'=>array('int','NO','1',null),'code'=>array('varchar','NO',null,64),
            'label_en'=>array('varchar','NO',null,255),'label_ar'=>array('varchar','NO',null,255),'label_zh'=>array('varchar','NO',null,255),'color'=>array('varchar','NO','#2980b9',9),
            'position'=>array('int','NO','0',null),'behavior'=>array('varchar','NO',null,16),'is_system'=>array('tinyint','NO','0',null),'active'=>array('tinyint','NO','1',null),
            'version'=>array('int','NO','1',null),'fk_user_creat'=>array('int','NO',null,null),'fk_user_modif'=>array('int','YES',null,null),'date_creation'=>array('datetime','NO',null,null),'tms'=>array('timestamp','NO','CURRENT_TIMESTAMP',null,'on update current_timestamp'),
        ))) return $this->notReady('CPO_TASK_STAGE_COLUMNS');
        if (!$this->columnsMatch($this->prefix.'cpo_activity', array(
            'rowid'=>array('bigint','NO',null,null,'auto_increment'),
            'entity'=>array('int','NO','1',null),'fk_operation'=>array('bigint','YES',null,null),'fk_task'=>array('bigint','YES',null,null),
            'action_code'=>array('varchar','NO',null,48),'message'=>array('text','YES',null,null),'visibility'=>array('varchar','NO','internal',16),
            'fk_user'=>array('int','NO',null,null),'date_action'=>array('datetime','NO',null,null),
        ))) return $this->notReady('CPO_ACTIVITY_COLUMNS');
        if (!$this->collaborationSchemaReady()) return $this->notReady('COLLABORATION_SCHEMA');
        $indexes = array(
            $this->prefix.'cpo_task'=>array('PRIMARY'=>array('rowid',0),'idx_cpo_task_own_board'=>array('entity,fk_user_assign,date_archived,status,position',1),'idx_cpo_task_team_board'=>array('entity,fk_usergroup,date_archived,status,position',1),'idx_cpo_task_stage_tasks'=>array('entity,fk_stage,date_archived,status,position',1)),
            $this->prefix.'cpo_task_team_manager'=>array('PRIMARY'=>array('rowid',0),'uk_cpo_task_team_manager'=>array('entity,fk_usergroup,fk_user',0),'idx_cpo_task_manager_user'=>array('entity,fk_user,status',1),'idx_cpo_task_manager_team'=>array('entity,fk_usergroup,status',1)),
            $this->prefix.'cpo_task_asset'=>array('PRIMARY'=>array('rowid',0),'uk_cpo_task_asset_stored'=>array('entity,stored_name',0),'uk_cpo_task_asset_version'=>array('entity,fk_task,asset_ref,version_no',0),'idx_cpo_task_asset_current'=>array('entity,fk_task,status,asset_ref,version_no',1),'idx_cpo_task_asset_cover'=>array('entity,fk_task,status,is_cover',1)),
            $this->prefix.'cpo_task_stage'=>array('PRIMARY'=>array('rowid',0),'uk_cpo_task_stage_code'=>array('entity,code',0),'idx_cpo_task_stage_board'=>array('entity,active,position,rowid',1)),
            $this->prefix.'cpo_activity'=>array('PRIMARY'=>array('rowid',0),'idx_cpo_activity_task'=>array('entity,fk_task,date_action',1)),
        );
        foreach ($indexes as $table=>$definitions) foreach ($definitions as $name=>$definition) if (!$this->indexMatches($table,$name,$definition[0],$definition[1])) return $this->notReady('INDEX_'.strtoupper($name));
        if (!$this->stageDataReady()) return $this->notReady('STAGE_DATA');
        if (!$this->collaborationDataReady()) return $this->notReady('COLLABORATION_DATA');
        foreach (array_keys(self::rightCodes()) as $rightId) {
            if ($this->rightCollision($rightId)) return $this->notReady('RIGHT_COLLISION_'.$rightId);
        }
        if ($requireRights && !$this->rightsReady()) return $this->notReady('RIGHTS_NOT_READY');
        $this->readinessReason = 'READY';
        return true;
    }

    public function readinessReason()
    {
        return $this->readinessReason;
    }

    private function notReady($reason)
    {
        $this->readinessReason = preg_replace('/[^A-Z0-9_]/', '_', strtoupper((string) $reason));
        return false;
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
        $sql = 'SELECT module,perms,bydefault FROM '.$this->prefix.'rights_def WHERE id='.(int) $rightId;
        $res = $this->db->query($sql);
        if (!$res) throw new RuntimeException('Unable to inspect right definitions.');
        $expected = self::rightCodes();
        while ($row = $this->db->fetch_object($res)) {
            if ((string) $row->module !== 'chinaplanetoperations' || !isset($expected[(int) $rightId]) || (string) $row->perms !== $expected[(int) $rightId] || (int) $row->bydefault !== 0) return true;
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
            500523 => 'task_comment',
            500524 => 'task_checklist',
        );
    }

    public static function collaborationTables()
    {
        return array('cpo_task_comment','cpo_task_comment_mention','cpo_task_notification','cpo_task_checklist','cpo_task_checklist_item','cpo_task_label','cpo_task_label_link');
    }

    private function createCollaborationTables()
    {
        $definitions = array(
            "cpo_task_comment (rowid bigint AUTO_INCREMENT PRIMARY KEY,entity integer NOT NULL DEFAULT 1,fk_task bigint NOT NULL,body text NOT NULL,request_key char(36) NOT NULL,request_hash char(64) NOT NULL,visibility varchar(16) NOT NULL DEFAULT 'visible',redaction_reason varchar(500) NULL,fk_user_redact integer NULL,date_redacted datetime NULL,version integer NOT NULL DEFAULT 1,fk_user_creat integer NOT NULL,date_creation datetime NOT NULL,tms timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,UNIQUE KEY uk_cpo_task_comment_request (entity,fk_user_creat,request_key),KEY idx_cpo_task_comment_task (entity,fk_task,date_creation,rowid),KEY idx_cpo_task_comment_author (entity,fk_user_creat,date_creation,rowid)) ENGINE=innodb",
            "cpo_task_comment_mention (rowid bigint AUTO_INCREMENT PRIMARY KEY,entity integer NOT NULL DEFAULT 1,fk_comment bigint NOT NULL,fk_user integer NOT NULL,date_creation datetime NOT NULL,UNIQUE KEY uk_cpo_task_comment_mention (entity,fk_comment,fk_user),KEY idx_cpo_task_mention_recipient (entity,fk_user,fk_comment)) ENGINE=innodb",
            "cpo_task_notification (rowid bigint AUTO_INCREMENT PRIMARY KEY,entity integer NOT NULL DEFAULT 1,fk_user integer NOT NULL,fk_task bigint NOT NULL,fk_comment bigint NOT NULL,notification_type varchar(32) NOT NULL DEFAULT 'mention',date_seen datetime NULL,date_creation datetime NOT NULL,UNIQUE KEY uk_cpo_task_notification_event (entity,notification_type,fk_user,fk_comment),KEY idx_cpo_task_notification_inbox (entity,fk_user,date_seen,date_creation,rowid)) ENGINE=innodb",
            "cpo_task_checklist (rowid bigint AUTO_INCREMENT PRIMARY KEY,entity integer NOT NULL DEFAULT 1,fk_task bigint NOT NULL,title varchar(255) NOT NULL,position integer NOT NULL DEFAULT 0,active tinyint NOT NULL DEFAULT 1,version integer NOT NULL DEFAULT 1,fk_user_creat integer NOT NULL,fk_user_modif integer NULL,date_creation datetime NOT NULL,tms timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,KEY idx_cpo_task_checklist_list (entity,fk_task,active,position,rowid)) ENGINE=innodb",
            "cpo_task_checklist_item (rowid bigint AUTO_INCREMENT PRIMARY KEY,entity integer NOT NULL DEFAULT 1,fk_checklist bigint NOT NULL,label varchar(255) NOT NULL,position integer NOT NULL DEFAULT 0,completed tinyint NOT NULL DEFAULT 0,fk_user_completed integer NULL,date_completed datetime NULL,active tinyint NOT NULL DEFAULT 1,version integer NOT NULL DEFAULT 1,fk_user_creat integer NOT NULL,fk_user_modif integer NULL,date_creation datetime NOT NULL,tms timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,KEY idx_cpo_task_checkitem_list (entity,fk_checklist,active,position,rowid)) ENGINE=innodb",
            "cpo_task_label (rowid bigint AUTO_INCREMENT PRIMARY KEY,entity integer NOT NULL DEFAULT 1,code varchar(64) NOT NULL,label_en varchar(255) NOT NULL,label_ar varchar(255) NOT NULL,label_zh varchar(255) NOT NULL,color char(7) NOT NULL DEFAULT '#2980B9',active tinyint NOT NULL DEFAULT 1,version integer NOT NULL DEFAULT 1,fk_user_creat integer NOT NULL,fk_user_modif integer NULL,date_creation datetime NOT NULL,tms timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,UNIQUE KEY uk_cpo_task_label_code (entity,code),KEY idx_cpo_task_label_list (entity,active,code,rowid)) ENGINE=innodb",
            "cpo_task_label_link (rowid bigint AUTO_INCREMENT PRIMARY KEY,entity integer NOT NULL DEFAULT 1,fk_task bigint NOT NULL,fk_label bigint NOT NULL,fk_user_creat integer NOT NULL,date_creation datetime NOT NULL,UNIQUE KEY uk_cpo_task_label_link (entity,fk_task,fk_label),KEY idx_cpo_task_label_filter (entity,fk_label,fk_task)) ENGINE=innodb",
        );
        foreach ($definitions as $definition) $this->mustQuery('CREATE TABLE IF NOT EXISTS '.$this->prefix.$definition);
    }

    private function collaborationSchemaReady()
    {
        $specs = array(
            'cpo_task_comment'=>array('rowid'=>array('bigint','NO',null,null,'auto_increment'),'entity'=>array('int','NO','1',null),'fk_task'=>array('bigint','NO',null,null),'body'=>array('text','NO',null,null),'request_key'=>array('char','NO',null,36),'request_hash'=>array('char','NO',null,64),'visibility'=>array('varchar','NO','visible',16),'redaction_reason'=>array('varchar','YES',null,500),'fk_user_redact'=>array('int','YES',null,null),'date_redacted'=>array('datetime','YES',null,null),'version'=>array('int','NO','1',null),'fk_user_creat'=>array('int','NO',null,null),'date_creation'=>array('datetime','NO',null,null),'tms'=>array('timestamp','NO','CURRENT_TIMESTAMP',null,'on update current_timestamp')),
            'cpo_task_comment_mention'=>array('rowid'=>array('bigint','NO',null,null,'auto_increment'),'entity'=>array('int','NO','1',null),'fk_comment'=>array('bigint','NO',null,null),'fk_user'=>array('int','NO',null,null),'date_creation'=>array('datetime','NO',null,null)),
            'cpo_task_notification'=>array('rowid'=>array('bigint','NO',null,null,'auto_increment'),'entity'=>array('int','NO','1',null),'fk_user'=>array('int','NO',null,null),'fk_task'=>array('bigint','NO',null,null),'fk_comment'=>array('bigint','NO',null,null),'notification_type'=>array('varchar','NO','mention',32),'date_seen'=>array('datetime','YES',null,null),'date_creation'=>array('datetime','NO',null,null)),
            'cpo_task_checklist'=>array('rowid'=>array('bigint','NO',null,null,'auto_increment'),'entity'=>array('int','NO','1',null),'fk_task'=>array('bigint','NO',null,null),'title'=>array('varchar','NO',null,255),'position'=>array('int','NO','0',null),'active'=>array('tinyint','NO','1',null),'version'=>array('int','NO','1',null),'fk_user_creat'=>array('int','NO',null,null),'fk_user_modif'=>array('int','YES',null,null),'date_creation'=>array('datetime','NO',null,null),'tms'=>array('timestamp','NO','CURRENT_TIMESTAMP',null,'on update current_timestamp')),
            'cpo_task_checklist_item'=>array('rowid'=>array('bigint','NO',null,null,'auto_increment'),'entity'=>array('int','NO','1',null),'fk_checklist'=>array('bigint','NO',null,null),'label'=>array('varchar','NO',null,255),'position'=>array('int','NO','0',null),'completed'=>array('tinyint','NO','0',null),'fk_user_completed'=>array('int','YES',null,null),'date_completed'=>array('datetime','YES',null,null),'active'=>array('tinyint','NO','1',null),'version'=>array('int','NO','1',null),'fk_user_creat'=>array('int','NO',null,null),'fk_user_modif'=>array('int','YES',null,null),'date_creation'=>array('datetime','NO',null,null),'tms'=>array('timestamp','NO','CURRENT_TIMESTAMP',null,'on update current_timestamp')),
            'cpo_task_label'=>array('rowid'=>array('bigint','NO',null,null,'auto_increment'),'entity'=>array('int','NO','1',null),'code'=>array('varchar','NO',null,64),'label_en'=>array('varchar','NO',null,255),'label_ar'=>array('varchar','NO',null,255),'label_zh'=>array('varchar','NO',null,255),'color'=>array('char','NO','#2980B9',7),'active'=>array('tinyint','NO','1',null),'version'=>array('int','NO','1',null),'fk_user_creat'=>array('int','NO',null,null),'fk_user_modif'=>array('int','YES',null,null),'date_creation'=>array('datetime','NO',null,null),'tms'=>array('timestamp','NO','CURRENT_TIMESTAMP',null,'on update current_timestamp')),
            'cpo_task_label_link'=>array('rowid'=>array('bigint','NO',null,null,'auto_increment'),'entity'=>array('int','NO','1',null),'fk_task'=>array('bigint','NO',null,null),'fk_label'=>array('bigint','NO',null,null),'fk_user_creat'=>array('int','NO',null,null),'date_creation'=>array('datetime','NO',null,null)),
        );
        foreach ($specs as $table=>$spec) if (!$this->columnsMatch($this->prefix.$table,$spec)) return false;
        $indexes = array(
            'cpo_task_comment'=>array('PRIMARY'=>array('rowid',0),'uk_cpo_task_comment_request'=>array('entity,fk_user_creat,request_key',0),'idx_cpo_task_comment_task'=>array('entity,fk_task,date_creation,rowid',1),'idx_cpo_task_comment_author'=>array('entity,fk_user_creat,date_creation,rowid',1)),
            'cpo_task_comment_mention'=>array('PRIMARY'=>array('rowid',0),'uk_cpo_task_comment_mention'=>array('entity,fk_comment,fk_user',0),'idx_cpo_task_mention_recipient'=>array('entity,fk_user,fk_comment',1)),
            'cpo_task_notification'=>array('PRIMARY'=>array('rowid',0),'uk_cpo_task_notification_event'=>array('entity,notification_type,fk_user,fk_comment',0),'idx_cpo_task_notification_inbox'=>array('entity,fk_user,date_seen,date_creation,rowid',1)),
            'cpo_task_checklist'=>array('PRIMARY'=>array('rowid',0),'idx_cpo_task_checklist_list'=>array('entity,fk_task,active,position,rowid',1)),
            'cpo_task_checklist_item'=>array('PRIMARY'=>array('rowid',0),'idx_cpo_task_checkitem_list'=>array('entity,fk_checklist,active,position,rowid',1)),
            'cpo_task_label'=>array('PRIMARY'=>array('rowid',0),'uk_cpo_task_label_code'=>array('entity,code',0),'idx_cpo_task_label_list'=>array('entity,active,code,rowid',1)),
            'cpo_task_label_link'=>array('PRIMARY'=>array('rowid',0),'uk_cpo_task_label_link'=>array('entity,fk_task,fk_label',0),'idx_cpo_task_label_filter'=>array('entity,fk_label,fk_task',1)),
        );
        foreach ($indexes as $table=>$defs) foreach ($defs as $name=>$def) if (!$this->indexMatches($this->prefix.$table,$name,$def[0],$def[1])) return false;
        return true;
    }

    private function collaborationDataReady()
    {
        $p=$this->prefix;
        $checks=array(
            "SELECT COUNT(*) count_rows FROM {$p}cpo_task_comment c LEFT JOIN {$p}cpo_task t ON t.rowid=c.fk_task AND t.entity=c.entity WHERE t.rowid IS NULL OR c.visibility NOT IN ('visible','redacted') OR (c.visibility='visible' AND (c.redaction_reason IS NOT NULL OR c.fk_user_redact IS NOT NULL OR c.date_redacted IS NOT NULL)) OR (c.visibility='redacted' AND (c.redaction_reason IS NULL OR c.fk_user_redact IS NULL OR c.date_redacted IS NULL))",
            "SELECT COUNT(*) count_rows FROM {$p}cpo_task_comment_mention m LEFT JOIN {$p}cpo_task_comment c ON c.rowid=m.fk_comment AND c.entity=m.entity WHERE c.rowid IS NULL",
            "SELECT COUNT(*) count_rows FROM {$p}cpo_task_notification n LEFT JOIN {$p}cpo_task t ON t.rowid=n.fk_task AND t.entity=n.entity LEFT JOIN {$p}cpo_task_comment c ON c.rowid=n.fk_comment AND c.entity=n.entity AND c.fk_task=n.fk_task WHERE t.rowid IS NULL OR c.rowid IS NULL OR n.notification_type<>'mention'",
            "SELECT COUNT(*) count_rows FROM {$p}cpo_task_checklist c LEFT JOIN {$p}cpo_task t ON t.rowid=c.fk_task AND t.entity=c.entity WHERE t.rowid IS NULL OR c.active NOT IN (0,1)",
            "SELECT COUNT(*) count_rows FROM {$p}cpo_task_checklist_item i LEFT JOIN {$p}cpo_task_checklist c ON c.rowid=i.fk_checklist AND c.entity=i.entity WHERE c.rowid IS NULL OR i.active NOT IN (0,1) OR i.completed NOT IN (0,1) OR (i.completed=0 AND (i.fk_user_completed IS NOT NULL OR i.date_completed IS NOT NULL)) OR (i.completed=1 AND (i.fk_user_completed IS NULL OR i.date_completed IS NULL))",
            "SELECT COUNT(*) count_rows FROM {$p}cpo_task_label l WHERE l.active NOT IN (0,1) OR l.code NOT REGEXP '^[a-z0-9_]{1,64}$' OR l.color NOT REGEXP '^#[0-9A-Fa-f]{6}$'",
            "SELECT COUNT(*) count_rows FROM {$p}cpo_task_label_link x LEFT JOIN {$p}cpo_task t ON t.rowid=x.fk_task AND t.entity=x.entity LEFT JOIN {$p}cpo_task_label l ON l.rowid=x.fk_label AND l.entity=x.entity WHERE t.rowid IS NULL OR l.rowid IS NULL",
        );
        foreach($checks as $sql) if($this->count($sql)!==0)return false;
        return true;
    }

    private function enforceTimestampNullability()
    {
        foreach (array('cpo_task', 'cpo_task_team_manager', 'cpo_task_asset') as $table) {
            $qualified = $this->prefix.$table;
            if ($this->columnsMatch($qualified, array('tms'=>array('timestamp','NO','CURRENT_TIMESTAMP',null,'on update current_timestamp')))) continue;
            if ($this->count('SELECT COUNT(*) count_rows FROM '.$qualified.' WHERE tms IS NULL') !== 0) {
                throw new RuntimeException('Internal task timestamp contains null values.');
            }
            $this->mustQuery('ALTER TABLE '.$qualified.' MODIFY COLUMN tms timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');
        }
    }

    private function seedAndBackfillStages()
    {
        $entities = array();
        $res = $this->db->query('SELECT DISTINCT entity FROM '.$this->prefix.'cpo_task UNION SELECT DISTINCT entity FROM '.$this->prefix."const WHERE name='MAIN_MODULE_CHINAPLANETOPERATIONS' AND value='1'");
        if (!$res) throw new RuntimeException('Unable to discover internal task entities.');
        while ($row = $this->db->fetch_object($res)) if ((int)$row->entity > 0) $entities[] = (int)$row->entity;
        foreach (array_values(array_unique($entities)) as $entity) {
            $actor = 0;
            $userRes = $this->db->query('SELECT rowid FROM '.$this->prefix.'user WHERE entity='.$entity.' AND statut=1 AND COALESCE(fk_soc,0)=0 ORDER BY admin DESC,rowid LIMIT 1');
            $userRow = $userRes ? $this->db->fetch_object($userRes) : null;
            if ($userRow) $actor = (int)$userRow->rowid;
            $definitions = array(
                array('todo','To Do','للعمل','待办','#64748B',10,'todo',1,1),
                array('in_progress','In Progress','قيد التنفيذ','进行中','#2563EB',100,'work',1,1),
                array('waiting','Waiting','بانتظار','等待中','#C47A08',9000,'waiting',1,1),
                array('review','Review','مراجعة','审核中','#7C3AED',9010,'review',1,1),
                array('done','Done','مكتمل','已完成','#16845B',9020,'done',1,1),
                array('legacy_unknown','Previous stage not recorded','المرحلة السابقة غير مسجلة','未记录之前阶段','#6B7280',10000,'legacy',1,0),
            );
            foreach ($definitions as $d) {
                $sql = 'INSERT IGNORE INTO '.$this->prefix.'cpo_task_stage(entity,code,label_en,label_ar,label_zh,color,position,behavior,is_system,active,version,fk_user_creat,fk_user_modif,date_creation) VALUES(';
                $sql .= $entity.",'".$this->db->escape($d[0])."','".$this->db->escape($d[1])."','".$this->db->escape($d[2])."','".$this->db->escape($d[3])."','".$this->db->escape($d[4])."',".(int)$d[5].",'".$this->db->escape($d[6])."',".(int)$d[7].','.(int)$d[8].',1,'.$actor.','.$actor.",'".date('Y-m-d H:i:s')."')";
                $this->mustQuery($sql);
            }
            $map = array('todo'=>'todo','in_progress'=>'in_progress','waiting'=>'waiting','review'=>'review','done'=>'done','cancelled'=>'legacy_unknown','deleted'=>'legacy_unknown');
            foreach ($map as $status=>$code) {
                $sql = 'UPDATE '.$this->prefix.'cpo_task t INNER JOIN '.$this->prefix.'cpo_task_stage s ON s.entity=t.entity AND s.code=\''.$this->db->escape($code).'\' SET t.fk_stage=s.rowid WHERE t.entity='.$entity.' AND t.fk_stage IS NULL AND t.status=\''.$this->db->escape($status).'\'';
                $this->mustQuery($sql);
            }
        }
    }

    private function stageDataReady()
    {
        $res = $this->db->query('SELECT DISTINCT entity FROM '.$this->prefix.'cpo_task_stage UNION SELECT DISTINCT entity FROM '.$this->prefix.'cpo_task');
        if (!$res) return false;
        while ($entityRow = $this->db->fetch_object($res)) {
            $entity = (int)$entityRow->entity;
            if ($entity <= 0) return false;
            $required = array('todo'=>array('todo',1),'in_progress'=>array('work',1),'waiting'=>array('waiting',1),'review'=>array('review',1),'done'=>array('done',1),'legacy_unknown'=>array('legacy',0));
            foreach ($required as $code=>$spec) {
                $sql = 'SELECT COUNT(*) count_rows FROM '.$this->prefix.'cpo_task_stage WHERE entity='.$entity." AND code='".$this->db->escape($code)."' AND behavior='".$this->db->escape($spec[0])."' AND is_system=1 AND active=".(int)$spec[1];
                if ($this->count($sql) !== 1) return false;
            }
            $badCustom = 'SELECT COUNT(*) count_rows FROM '.$this->prefix."cpo_task_stage WHERE entity=".$entity." AND is_system=0 AND behavior<>'work'";
            if ($this->count($badCustom) !== 0) return false;
            $badTasks = 'SELECT COUNT(*) count_rows FROM '.$this->prefix.'cpo_task t LEFT JOIN '.$this->prefix.'cpo_task_stage s ON s.rowid=t.fk_stage AND s.entity=t.entity WHERE t.entity='.$entity.' AND (s.rowid IS NULL OR (t.status=\'todo\' AND s.behavior<>\'todo\') OR (t.status=\'in_progress\' AND s.behavior<>\'work\') OR (t.status=\'waiting\' AND s.behavior<>\'waiting\') OR (t.status=\'review\' AND s.behavior<>\'review\') OR (t.status=\'done\' AND s.behavior<>\'done\'))';
            if ($this->count($badTasks) !== 0) return false;
        }
        return true;
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
            $columnReason = 'COLUMN_'.preg_replace('/[^A-Z0-9_]/', '_', strtoupper($table.'_'.$column));
            if (!$row) return $this->notReady($columnReason.'_MISSING');
            if (strtolower((string)$row->data_type)!==$specification[0]) return $this->notReady($columnReason.'_TYPE');
            if ((string)$row->is_nullable!==$specification[1]) return $this->notReady($columnReason.'_NULLABILITY');
            $actualDefault = $row->column_default === null ? null : (string)$row->column_default;
            if (!$this->columnDefaultMatches($actualDefault, $specification[2])) return $this->notReady($columnReason.'_DEFAULT');
            if ($specification[3] !== null && (int)$row->character_maximum_length !== (int)$specification[3]) return $this->notReady($columnReason.'_LENGTH');
            if (isset($specification[4]) && $specification[4] !== null && !$this->columnExtraMatches((string)$row->extra, (string)$specification[4])) return $this->notReady($columnReason.'_EXTRA');
        }
        return true;
    }

    /**
     * MariaDB 10.2.7+ exposes literal defaults quoted in
     * information_schema while older MySQL-compatible runtimes return the
     * same literal without quotes.  Normalize only those two documented
     * representations and the optional CURRENT_TIMESTAMP parentheses; all
     * other expressions stay fail-closed.
     */
    private function columnDefaultMatches($actual, $expected)
    {
        // MariaDB can expose an implicit SQL NULL default as the unquoted
        // token NULL. A quoted 'NULL' remains a real string default.
        if ($expected === null) return $actual === null || (is_string($actual) && strcasecmp($actual, 'NULL') === 0);
        if ($actual === null) return false;

        $actual = (string) $actual;
        $expected = (string) $expected;
        if ($expected === 'CURRENT_TIMESTAMP') {
            return preg_match('/^CURRENT_TIMESTAMP(?:\(\))?$/iD', $actual) === 1;
        }

        if (strlen($actual) >= 2 && $actual[0] === "'" && substr($actual, -1) === "'") {
            $actual = substr($actual, 1, -1);
            $actual = str_replace("''", "'", $actual);
        }
        return $actual === $expected;
    }

    private function columnExtraMatches($actual, $expected)
    {
        if (strtolower($expected) === 'on update current_timestamp') {
            return preg_match('/^on update CURRENT_TIMESTAMP(?:\(\))?$/iD', $actual) === 1;
        }
        return strtolower($actual) === strtolower($expected);
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
