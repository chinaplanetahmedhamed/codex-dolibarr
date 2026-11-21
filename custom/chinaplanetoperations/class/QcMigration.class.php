<?php

/** Additive M074 schema/readiness checks. DDL is loaded by the module SQL loader. */
class QcMigration
{
    private $db;
    private $prefix;

    public function __construct($db)
    {
        $this->db = $db;
        $this->prefix = MAIN_DB_PREFIX;
    }

    public static function rightCodes()
    {
        return array(
            500528 => 'qc_plan',
            500529 => 'qc_execute',
            500530 => 'qc_review',
            500531 => 'qc_publish',
        );
    }

    public static function tables()
    {
        return array('cpo_qc_checkpoint', 'cpo_qc_evidence', 'cpo_qc_publication');
    }

    /** Complete schema contract used by migration, deploy and runtime readiness checks. */
    public static function schemaContract()
    {
        return array(
            'cpo_qc_checkpoint' => array(
                'columns' => array(
                    'rowid'=>array('bigint','NO',null,'auto_increment'), 'entity'=>array('int','NO','1',''),
                    'fk_operation'=>array('bigint','NO',null,''), 'client_uuid'=>array('char(36)','NO',null,''),
                    'record_kind'=>array('varchar(16)','NO','requirement',''), 'source_type'=>array('varchar(16)','NO','manual',''),
                    'source_ref'=>array('varchar(255)','YES',null,''), 'source_page'=>array('int','YES',null,''),
                    'source_confidence'=>array('decimal(6,5)','YES',null,''), 'scope_type'=>array('varchar(16)','NO','inspection',''),
                    'snapshot_line_key'=>array('varchar(64)','YES',null,''), 'section_code'=>array('varchar(64)','NO','general',''),
                    'section_label'=>array('varchar(255)','NO',null,''), 'requirement_text'=>array('text','NO',null,''),
                    'expected_value'=>array('text','YES',null,''), 'tolerance_text'=>array('text','YES',null,''),
                    'observation'=>array('text','YES',null,''), 'result_code'=>array('varchar(24)','NO','pending',''),
                    'severity'=>array('varchar(16)','NO','none',''), 'mandatory'=>array('tinyint','NO','1',''),
                    'min_evidence'=>array('int','NO','0',''), 'sample_size'=>array('int','YES',null,''),
                    'defects_critical'=>array('int','YES',null,''), 'defects_major'=>array('int','YES',null,''),
                    'defects_minor'=>array('int','YES',null,''), 'public_note'=>array('text','YES',null,''),
                    'internal_note'=>array('text','YES',null,''), 'position'=>array('int','NO','0',''),
                    'revision'=>array('int','NO','1',''), 'review_status'=>array('varchar(24)','NO','pending',''),
                    'review_note'=>array('text','YES',null,''), 'customer_visible'=>array('tinyint','NO','0',''),
                    'mutation_receipts_json'=>array('longtext','YES',null,''), 'fk_user_creat'=>array('int','NO',null,''),
                    'fk_user_modif'=>array('int','YES',null,''), 'fk_user_review'=>array('int','YES',null,''),
                    'date_review'=>array('datetime','YES',null,''), 'date_creation'=>array('datetime','NO',null,''),
                    'tms'=>array('timestamp','NO','current_timestamp','on update current_timestamp'),
                ),
                'indexes' => array(
                    'PRIMARY'=>array(0,array('rowid'),'BTREE',array(null)), 'uk_cpo_qc_checkpoint_client'=>array(0,array('entity','fk_operation','client_uuid'),'BTREE',array(null,null,null)),
                    'idx_cpo_qc_checkpoint_list'=>array(1,array('entity','fk_operation','position','rowid'),'BTREE',array(null,null,null,null)),
                    'idx_cpo_qc_checkpoint_review'=>array(1,array('entity','fk_operation','review_status','result_code'),'BTREE',array(null,null,null,null)),
                    'idx_cpo_qc_checkpoint_scope'=>array(1,array('entity','fk_operation','scope_type','snapshot_line_key'),'BTREE',array(null,null,null,null)),
                ),
            ),
            'cpo_qc_evidence' => array(
                'columns' => array(
                    'rowid'=>array('bigint','NO',null,'auto_increment'), 'entity'=>array('int','NO','1',''),
                    'fk_operation'=>array('bigint','NO',null,''), 'fk_checkpoint'=>array('bigint','NO',null,''),
                    'client_uuid'=>array('char(36)','NO',null,''), 'evidence_type'=>array('varchar(16)','NO',null,''),
                    'caption'=>array('varchar(500)','YES',null,''), 'upload_status'=>array('varchar(24)','NO','pending',''),
                    'review_status'=>array('varchar(24)','NO','pending',''), 'customer_visible'=>array('tinyint','NO','0',''),
                    'upload_token_hash'=>array('char(64)','YES',null,''), 'upload_owner'=>array('int','NO',null,''),
                    'upload_expires_at'=>array('datetime','YES',null,''), 'expected_size'=>array('bigint','YES',null,''),
                    'chunk_size'=>array('int','YES',null,''), 'chunk_count'=>array('int','YES',null,''),
                    'chunk_manifest_json'=>array('longtext','YES',null,''), 'original_name'=>array('varchar(255)','NO',null,''),
                    'original_stored_name'=>array('varchar(255)','YES',null,''), 'original_mime'=>array('varchar(128)','YES',null,''),
                    'original_size'=>array('bigint','YES',null,''), 'original_sha256'=>array('char(64)','YES',null,''),
                    'safe_stored_name'=>array('varchar(255)','YES',null,''), 'safe_mime'=>array('varchar(128)','YES',null,''),
                    'safe_size'=>array('bigint','YES',null,''), 'safe_sha256'=>array('char(64)','YES',null,''),
                    'revision'=>array('int','NO','1',''), 'mutation_receipts_json'=>array('longtext','YES',null,''),
                    'fk_user_creat'=>array('int','NO',null,''), 'fk_user_review'=>array('int','YES',null,''),
                    'review_note'=>array('text','YES',null,''), 'date_review'=>array('datetime','YES',null,''),
                    'date_finalized'=>array('datetime','YES',null,''), 'date_creation'=>array('datetime','NO',null,''),
                    'tms'=>array('timestamp','NO','current_timestamp','on update current_timestamp'),
                ),
                'indexes' => array(
                    'PRIMARY'=>array(0,array('rowid'),'BTREE',array(null)), 'uk_cpo_qc_evidence_client'=>array(0,array('entity','fk_operation','client_uuid'),'BTREE',array(null,null,null)),
                    'uk_cpo_qc_evidence_token'=>array(0,array('entity','upload_token_hash'),'BTREE',array(null,null)),
                    'idx_cpo_qc_evidence_checkpoint'=>array(1,array('entity','fk_operation','fk_checkpoint','upload_status'),'BTREE',array(null,null,null,null)),
                    'idx_cpo_qc_evidence_review'=>array(1,array('entity','fk_operation','review_status','customer_visible'),'BTREE',array(null,null,null,null)),
                    'idx_cpo_qc_evidence_expiry'=>array(1,array('entity','upload_status','upload_expires_at'),'BTREE',array(null,null,null)),
                ),
            ),
            'cpo_qc_publication' => array(
                'columns' => array(
                    'rowid'=>array('bigint','NO',null,'auto_increment'), 'entity'=>array('int','NO','1',''),
                    'fk_operation'=>array('bigint','NO',null,''), 'fk_soc'=>array('int','NO',null,''),
                    'public_uuid'=>array('char(36)','NO',null,''), 'version_no'=>array('int','NO',null,''),
                    'is_current'=>array('tinyint','NO','1',''), 'overall_result'=>array('varchar(40)','NO',null,''),
                    'supervisor_override_reason'=>array('text','YES',null,''), 'inspection_at'=>array('datetime','YES',null,''),
                    'publication_json'=>array('longtext','NO',null,''), 'publication_sha256'=>array('char(64)','NO',null,''),
                    'pdf_stored_name'=>array('varchar(255)','YES',null,''), 'pdf_sha256'=>array('char(64)','YES',null,''),
                    'pdf_size'=>array('bigint','YES',null,''), 'fk_user_review'=>array('int','NO',null,''),
                    'fk_user_publish'=>array('int','NO',null,''), 'date_review'=>array('datetime','NO',null,''),
                    'date_publication'=>array('datetime','NO',null,''), 'date_creation'=>array('datetime','NO',null,''),
                    'tms'=>array('timestamp','NO','current_timestamp','on update current_timestamp'),
                ),
                'indexes' => array(
                    'PRIMARY'=>array(0,array('rowid'),'BTREE',array(null)), 'uk_cpo_qc_publication_version'=>array(0,array('entity','fk_operation','version_no'),'BTREE',array(null,null,null)),
                    'uk_cpo_qc_publication_public'=>array(0,array('entity','public_uuid'),'BTREE',array(null,null)),
                    'idx_cpo_qc_publication_current'=>array(1,array('entity','fk_operation','is_current','version_no'),'BTREE',array(null,null,null,null)),
                    'idx_cpo_qc_publication_customer'=>array(1,array('entity','fk_soc','is_current','date_publication'),'BTREE',array(null,null,null,null)),
                ),
            ),
        );
    }

    /** Idempotent additive installer for modules that were already enabled. */
    public function migrate()
    {
        $lock='cpo_m074_qc_migration';$res=$this->db->query("SELECT GET_LOCK('".$this->db->escape($lock)."',10) acquired");$row=$res?$this->db->fetch_object($res):null;if(!$row||(int)$row->acquired!==1)throw new RuntimeException('QC migration is already running.');
        try{
            foreach(self::rightCodes()as$id=>$code)if($this->rightDefinitionCollision($id,$code))throw new RuntimeException('QC right definition collision.');
            $statuses=array();foreach(self::tables()as$table){$statuses[$table]=$this->tableContractStatus($table);if($statuses[$table]==='collision')throw new RuntimeException('QC schema collision detected before migration.');}
            $root=dirname(__DIR__).'/sql';foreach(self::tables()as$table){if($statuses[$table]!=='missing')continue;$path=$root.'/llx_'.$table.'.sql';if(!is_file($path)||is_link($path))throw new RuntimeException('QC schema source is missing.');$sql=file_get_contents($path);if($sql===false)throw new RuntimeException('Unable to read QC schema source.');$sql=preg_replace('/^CREATE TABLE\s+llx_/i','CREATE TABLE IF NOT EXISTS '.$this->prefix,trim($sql));if(!$this->db->query($sql))throw new RuntimeException('Unable to install QC table.');}
            if(!$this->schemaReady())throw new RuntimeException('QC schema readiness check failed after migration.');return true;
        }finally{$this->db->query("SELECT RELEASE_LOCK('".$this->db->escape($lock)."')");}
    }

    public function rightCollision($rightId)
    {
        $expected = self::rightCodes();
        if (!isset($expected[(int) $rightId])) return true;
        return $this->rightDefinitionCollision((int) $rightId, $expected[(int) $rightId]);
    }

    public function rightDefinitionCollision($rightId, $rightCode)
    {
        if (!$this->tableExists($this->prefix.'rights_def')) return false;
        $res = $this->db->query('SELECT id,entity,module,perms,bydefault FROM '.$this->prefix.'rights_def WHERE id='.(int) $rightId." OR (module='chinaplanetoperations' AND perms='".$this->db->escape($rightCode)."')");
        if (!$res) throw new RuntimeException('Unable to inspect QC right definitions.');
        while ($row = $this->db->fetch_object($res)) {
            if ((int) $row->id !== (int) $rightId || (string) $row->module !== 'chinaplanetoperations' || (string) $row->perms !== (string) $rightCode || (int) $row->bydefault !== 0) return true;
        }
        return false;
    }

    public function rightDefinitionExists($rightId, $rightCode, $entity)
    {
        $entity = (int) $entity;
        if ($entity <= 0) throw new RuntimeException('QC permission entity is invalid.');
        if (!$this->tableExists($this->prefix.'rights_def')) return false;
        $res = $this->db->query('SELECT COUNT(*) count_rows FROM '.$this->prefix.'rights_def WHERE id='.(int) $rightId.' AND entity='.$entity." AND module='chinaplanetoperations' AND perms='".$this->db->escape($rightCode)."' AND bydefault=0");
        if (!$res) throw new RuntimeException('Unable to inspect QC permission definition.');
        $row = $this->db->fetch_object($res);
        return $row && (int) $row->count_rows === 1;
    }

    public function rightsReady($entity)
    {
        $entity = (int) $entity;
        if ($entity <= 0) return false;
        if (!$this->tableExists($this->prefix.'rights_def')) return false;
        foreach (self::rightCodes() as $rightId => $rightCode) {
            if ($this->rightDefinitionCollision($rightId, $rightCode)) return false;
            if (!$this->rightDefinitionExists($rightId, $rightCode, $entity)) return false;
        }
        return true;
    }

    /** Install only the four reviewed QC definitions, atomically and disabled by default. */
    public function installRights($module, $entity)
    {
        $entity = (int) $entity;
        if ($entity <= 0) throw new RuntimeException('QC permission entity is invalid.');

        $expected = self::rightCodes();
        $definitions = array();
        foreach ((array) $module->rights as $right) {
            $id = isset($right[0]) ? (int) $right[0] : 0;
            if (!isset($expected[$id])) continue;
            $perms = isset($right[4]) ? (string) $right[4] : '';
            $byDefault = isset($right[3]) ? (int) $right[3] : 0;
            if ($perms !== $expected[$id] || $byDefault !== 0 || isset($definitions[$id])) {
                throw new RuntimeException('QC permission definition is invalid.');
            }
            $definitions[$id] = array(
                'label' => isset($right[1]) ? (string) $right[1] : '',
                'perms' => $perms,
            );
        }
        if (count($definitions) !== count($expected)) throw new RuntimeException('QC permission definitions are incomplete.');

        $moduleName = !empty($module->rights_class) ? (string) $module->rights_class : strtolower((string) $module->name);
        $modulePosition = method_exists($module, 'getModulePosition') ? (int) $module->getModulePosition() : (int) $module->module_position;
        $family = (string) $module->family;
        if ($moduleName !== 'chinaplanetoperations' || $family !== 'projects') throw new RuntimeException('QC permission module identity is invalid.');

        if ($this->db->begin() <= 0) throw new RuntimeException('Unable to begin QC permission transaction.');
        try {
            foreach ($definitions as $id => $definition) {
                if ($this->rightDefinitionCollision($id, $definition['perms'])) throw new RuntimeException('QC right definition collision.');
                if ($this->rightDefinitionExists($id, $definition['perms'], $entity)) continue;

                $sql = 'INSERT INTO '.$this->prefix.'rights_def (id,entity,libelle,module,module_origin,module_position,family,family_position,type,bydefault,perms,subperms,enabled) VALUES ('
                    .(int) $id.','.$entity.", '".$this->db->escape($definition['label'])."', 'chinaplanetoperations', '', ".$modulePosition.", '".$this->db->escape($family)."', 0, 'w', 0, '".$this->db->escape($definition['perms'])."', '', '1')";
                if (!$this->db->query($sql)) throw new RuntimeException('Unable to install QC permission definition.');
            }
            if (!$this->rightsReady($entity)) throw new RuntimeException('QC permission readiness check failed after installation.');
            if ($this->db->commit() <= 0) throw new RuntimeException('Unable to commit QC permission transaction.');
        } catch (Throwable $exception) {
            if ($this->db->rollback() <= 0) {
                throw new RuntimeException('Unable to roll back QC permission transaction.', 0, $exception);
            }
            throw $exception;
        }
        return true;
    }

    public function schemaReady()
    {
        foreach (self::tables() as $table) if ($this->tableContractStatus($table) !== 'exact') return false;
        return true;
    }

    public function tableContractStatus($table)
    {
        $contracts = self::schemaContract();
        if (!isset($contracts[$table])) return 'collision';
        $physical = $this->prefix.$table;
        if (!$this->tableExists($physical)) return 'missing';
        $contract = $contracts[$table];
        $status = $this->db->query("SELECT ENGINE FROM information_schema.tables WHERE BINARY table_schema=BINARY DATABASE() AND BINARY table_name=BINARY '".$this->db->escape($physical)."'");
        $statusRow = $status ? $this->db->fetch_object($status) : null;
        $engine = $statusRow ? ($statusRow->ENGINE ?? $statusRow->Engine ?? '') : '';
        if (!$statusRow || strtoupper((string) $engine) !== 'INNODB') return 'collision';
        try {
            $res = $this->db->query('SHOW COLUMNS FROM '.$physical);
            if (!$res) return 'collision';
            $actualColumns = array();
            while ($row = $this->db->fetch_object($res)) {
                $default = $row->Default;
                if (is_string($default) && preg_match('/^current_timestamp(?:\(\))?$/i', $default)) $default = 'current_timestamp';
                $extra = strtolower(trim((string) $row->Extra));
                $extra = str_replace('current_timestamp()', 'current_timestamp', $extra);
                $actualColumns[(string) $row->Field] = array($this->normalizeType($row->Type), strtoupper((string) $row->Null), $default === null ? null : (string) $default, $extra);
            }
            if ($actualColumns !== $contract['columns']) return 'collision';

            $res = $this->db->query('SHOW INDEX FROM '.$physical);
            if (!$res) return 'collision';
            $actualIndexes = array();
            while ($row = $this->db->fetch_object($res)) {
                $name = (string) $row->Key_name;
                if (!isset($actualIndexes[$name])) $actualIndexes[$name] = array((int) $row->Non_unique, array(), strtoupper((string) $row->Index_type), array());
                if ($actualIndexes[$name][2] !== strtoupper((string) $row->Index_type)) return 'collision';
                $actualIndexes[$name][1][(int) $row->Seq_in_index] = (string) $row->Column_name;
                $actualIndexes[$name][3][(int) $row->Seq_in_index] = $row->Sub_part === null ? null : (int) $row->Sub_part;
            }
            foreach ($actualIndexes as &$index) { ksort($index[1]); $index[1] = array_values($index[1]); ksort($index[3]); $index[3] = array_values($index[3]); }
            unset($index);
            $expectedIndexes = $contract['indexes'];
            ksort($actualIndexes);
            ksort($expectedIndexes);
            return $actualIndexes === $expectedIndexes ? 'exact' : 'collision';
        } catch (Throwable $exception) {
            return 'collision';
        }
    }

    private function normalizeType($type)
    {
        $type = strtolower(trim((string) $type));
        return preg_replace('/^(bigint|int|tinyint)\([0-9]+\)/', '$1', $type);
    }

    private function tableExists($table)
    {
        $res = $this->db->query("SELECT COUNT(*) count_rows FROM information_schema.tables WHERE BINARY table_schema=BINARY DATABASE() AND BINARY table_name=BINARY '".$this->db->escape($table)."'");
        $row = $res ? $this->db->fetch_object($res) : null;
        return $row && (int) $row->count_rows === 1;
    }
}
