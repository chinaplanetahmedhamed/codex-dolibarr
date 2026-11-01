<?php
declare(strict_types=1);
/** CLI-only query shim: genuine owned SQLite DDL/DML plus simulated mysqli metadata/advisory locks. */
final class M119MigrationRows {public $rows;public function __construct(array $rows){$this->rows=$rows;}}
final class M119MigrationBackend implements OfficeExpenseSchemaBackend
{
    public $type='mysqli';public $database_name;public $transaction_opened;public $selectedDatabase;
    public $ddlCount=0;public $lockCount=0;public $locked=false;public $onLock=null;public $failAt=0;public $loseReadyResponse=false;public $loseAcquireResponse=false;public $failRelease=false;
    public $receiptCommitMode='';public $receiptTrace=array();public $beforeReceiptInsert=null;
    private $native;private $backend;private $readyWrite=false;private static $serverLocks=array();
    public function __construct(M119NativeSqlite $native)
    {
        if(PHP_SAPI!=='cli'||!defined('M119_SYNTHETIC_NATIVE')||!M119_SYNTHETIC_NATIVE||strpos($native->database_name,'m119_office_')!==0)throw new LogicException('Owned synthetic CLI database only.');
        $this->native=$native;$this->database_name=$native->database_name;$this->selectedDatabase=$native->database_name;$this->transaction_opened=&$native->transaction_opened;
        $this->backend=new OfficeExpenseMysqliSchemaBackend($this);
    }
    public function prefix(): string {return $this->backend->prefix();}
    public function describe(string $table): ?array {return $this->backend->describe($table);}
    public function executeDDL(string $sql): void {$this->backend->executeDDL($sql);}
    public function withExclusiveLock(callable $action): array {return $this->backend->withExclusiveLock($action);}
    public function marker(string $version='m119_office_expenses_drafts_v1'): ?array {return $this->backend->marker($version);}
    public function writeMarker(string $version,string $hash,string $status): void {$this->backend->writeMarker($version,$hash,$status);}
    public function escape($value){return $this->native->escape($value);}
    public function fetch_object($result){return $result instanceof M119MigrationRows?($result->rows?(object)array_shift($result->rows):false):$this->native->fetch_object($result);}
    public function begin(){return $this->native->begin();}
    public function rollback(){if($this->receiptCommitMode==='rollback_failed'){$this->transaction_opened=0;return 0;}return $this->native->rollback();}
    public function commit()
    {
        if(in_array($this->receiptCommitMode,array('false','rollback_failed'),true))return 0;
        $result=$this->native->commit();if(($this->loseReadyResponse&&$this->readyWrite)||$this->receiptCommitMode==='lost')throw new RuntimeException('SYNTHETIC lost durable commit response.');return $result;
    }
    private function sqliteRows(string $sql): array
    {
        $result=$this->native->db->query($sql);$rows=array();while($row=$result->fetchArray(SQLITE3_ASSOC))$rows[]=$row;$result->finalize();return $rows;
    }
    public function releaseOwnedForTest(): void {foreach(self::$serverLocks as $key=>$lock)if($lock['owner']===(string)spl_object_id($this))unset(self::$serverLocks[$key]);}
    public function query($sql)
    {
        if(strpos($sql,'UPDATE llx_cpo_oe_draft SET status=status')===0)$this->receiptTrace[]='source_lock';
        if(strpos($sql,'SELECT * FROM llx_cpo_oe_receipt WHERE')===0)$this->receiptTrace[]='receipt_metadata_read';
        if(strpos($sql,'INSERT INTO llx_cpo_oe_receipt(')===0){$this->receiptTrace[]='receipt_insert';if($this->beforeReceiptInsert!==null){$callback=$this->beforeReceiptInsert;$this->beforeReceiptInsert=null;$callback();}}
        if($sql==='SELECT DATABASE() AS selected_database')return new M119MigrationRows(array(array('selected_database'=>$this->selectedDatabase)));
        if(strpos($sql,'SELECT IS_USED_LOCK(')===0){preg_match("/IS_USED_LOCK\('([^']+)'\)/",$sql,$m);return new M119MigrationRows(array(array('owner'=>self::$serverLocks[$m[1]]['owner']??($this->locked?'foreign':null),'connection_id'=>(string)spl_object_id($this))));}
        if(strpos($sql,'SELECT GET_LOCK(')===0){
            preg_match("/GET_LOCK\('([^']+)'/",$sql,$m);$name=$m[1];$owner=(string)spl_object_id($this);$this->lockCount++;
            if($this->locked||(isset(self::$serverLocks[$name])&&self::$serverLocks[$name]['owner']!==$owner))return new M119MigrationRows(array(array('acquired'=>0)));
            $count=(self::$serverLocks[$name]['count']??0)+1;self::$serverLocks[$name]=array('owner'=>$owner,'count'=>$count);
            if($this->loseAcquireResponse)throw new RuntimeException('SYNTHETIC lost lock-acquisition response.');
            if($this->onLock!==null){$callback=$this->onLock;$this->onLock=null;$callback();}return new M119MigrationRows(array(array('acquired'=>1)));
        }
        if(strpos($sql,'SELECT RELEASE_LOCK(')===0){
            if($this->failRelease)return false;preg_match("/RELEASE_LOCK\('([^']+)'/",$sql,$m);$name=$m[1];
            if(!isset(self::$serverLocks[$name]))return new M119MigrationRows(array(array('released'=>null)));
            if(self::$serverLocks[$name]['owner']!==(string)spl_object_id($this))return new M119MigrationRows(array(array('released'=>0)));
            if(--self::$serverLocks[$name]['count']===0)unset(self::$serverLocks[$name]);return new M119MigrationRows(array(array('released'=>1)));
        }
        if(strpos($sql,'information_schema.')!==false){
            if(!preg_match("/TABLE_NAME='([A-Za-z0-9_]+)'/",$sql,$match))throw new RuntimeException('Invalid synthetic metadata scope.');$table=$match[1];
            $found=$this->sqliteRows("SELECT sql FROM sqlite_master WHERE type='table' AND name='".$table."'");
            if(strpos($sql,'information_schema.TABLES')!==false)return new M119MigrationRows($found?array(array('engine'=>'InnoDB')):array());
            $columns=$this->sqliteRows('PRAGMA table_info('.$table.')');
            if(strpos($sql,'information_schema.COLUMNS')!==false){
                $rows=array();foreach($columns as $column){
                    // SQLite BINARY substitutes for reviewed ASCII identity; actual charset/engine proof remains pending.
                    $binary=$found&&preg_match('/\b'.preg_quote($column['name'],'/').'\s+[^,\n]+COLLATE BINARY/i',$found[0]['sql']);
                    $rows[]=array('name'=>$column['name'],'type'=>strtolower($column['type']),'nullable'=>($column['notnull']||$column['pk'])?'NO':'YES',
                        'value_default'=>$column['dflt_value'],'extra'=>$column['pk']?'auto_increment':'','collation'=>$binary?'ascii_bin':null);
                }return new M119MigrationRows($rows);
            }
            if(strpos($sql,'information_schema.STATISTICS')!==false){
                $rows=array();foreach($columns as $column)if($column['pk'])$rows[]=array('name'=>'PRIMARY','non_unique'=>0,'column_name'=>$column['name'],'sub_part'=>null,'kind'=>'BTREE');
                foreach($this->sqliteRows('PRAGMA index_list('.$table.')') as $index){
                    if(strpos($index['name'],'sqlite_autoindex_')===0)continue;
                    foreach($this->sqliteRows('PRAGMA index_info('.$index['name'].')') as $column)$rows[]=array('name'=>$index['name'],'non_unique'=>$index['unique']?0:1,'column_name'=>$column['name'],'sub_part'=>null,'kind'=>'BTREE');
                }return new M119MigrationRows($rows);
            }
            throw new RuntimeException('Unexpected synthetic metadata query.');
        }
        if(strpos($sql,'CREATE ')===0){
            $this->ddlCount++;if($this->failAt===$this->ddlCount)throw new RuntimeException('SYNTHETIC interrupted DDL.');
            // Avoid native SQLite's datetime->nullable timestamp rewrite: retain the artifact's constraints.
            $sql=str_replace(array('CHARACTER SET ascii COLLATE ascii_bin','integer AUTO_INCREMENT PRIMARY KEY',' ENGINE=innodb'),array('COLLATE BINARY','INTEGER PRIMARY KEY AUTOINCREMENT',''),$sql);
            return $this->native->db->query($sql);
        }
        if(strpos($sql," SET status='ready'")!==false)$this->readyWrite=true;
        return $this->native->query($sql);
    }
}
