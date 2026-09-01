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
            500525 => 'qc_plan',
            500526 => 'qc_execute',
            500527 => 'qc_review',
            500528 => 'qc_publish',
        );
    }

    public static function tables()
    {
        return array('cpo_qc_checkpoint', 'cpo_qc_evidence', 'cpo_qc_publication');
    }

    /** Idempotent additive installer for modules that were already enabled. */
    public function migrate()
    {
        $lock='cpo_m074_qc_migration';$res=$this->db->query("SELECT GET_LOCK('".$this->db->escape($lock)."',10) acquired");$row=$res?$this->db->fetch_object($res):null;if(!$row||(int)$row->acquired!==1)throw new RuntimeException('QC migration is already running.');
        try{
            foreach(self::rightCodes()as$id=>$code)if($this->rightCollision($id))throw new RuntimeException('QC right definition collision.');
            $root=dirname(__DIR__).'/sql';foreach(self::tables()as$table){$path=$root.'/llx_'.$table.'.sql';if(!is_file($path)||is_link($path))throw new RuntimeException('QC schema source is missing.');$sql=file_get_contents($path);if($sql===false)throw new RuntimeException('Unable to read QC schema source.');$sql=preg_replace('/^CREATE TABLE\s+llx_/i','CREATE TABLE IF NOT EXISTS '.$this->prefix,trim($sql));if(!$this->db->query($sql))throw new RuntimeException('Unable to install '.$table.': '.$this->db->lasterror());}
            if(!$this->schemaReady())throw new RuntimeException('QC schema readiness check failed after migration.');return true;
        }finally{$this->db->query("SELECT RELEASE_LOCK('".$this->db->escape($lock)."')");}
    }

    public function rightCollision($rightId)
    {
        if (!$this->tableExists($this->prefix.'rights_def')) return false;
        $expected = self::rightCodes();
        $res = $this->db->query('SELECT module,perms,bydefault FROM '.$this->prefix.'rights_def WHERE id='.(int) $rightId);
        if (!$res) throw new RuntimeException('Unable to inspect QC right definitions.');
        while ($row = $this->db->fetch_object($res)) {
            if ((string) $row->module !== 'chinaplanetoperations' || !isset($expected[(int) $rightId]) || (string) $row->perms !== $expected[(int) $rightId] || (int) $row->bydefault !== 0) return true;
        }
        return false;
    }

    public function schemaReady()
    {
        foreach (self::tables() as $table) if (!$this->tableExists($this->prefix.$table)) return false;
        $required=array('cpo_qc_checkpoint'=>array('client_uuid','revision','review_status'),'cpo_qc_evidence'=>array('upload_status','upload_expires_at','safe_sha256','revision'),'cpo_qc_publication'=>array('public_uuid','publication_sha256','is_current'));
        foreach($required as$table=>$columns)foreach($columns as$column)if(!$this->columnExists($this->prefix.$table,$column))return false;
        return true;
    }

    private function columnExists($table,$column){$res=$this->db->query("SHOW COLUMNS FROM ".$table." LIKE '".$this->db->escape($column)."'");return$res&&$this->db->num_rows($res)===1;}

    private function tableExists($table)
    {
        $res = $this->db->query("SHOW TABLES LIKE '".$this->db->escape($table)."'");
        return $res && $this->db->num_rows($res) === 1;
    }
}
