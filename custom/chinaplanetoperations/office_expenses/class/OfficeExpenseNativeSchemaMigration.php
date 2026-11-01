<?php
declare(strict_types=1);
require_once __DIR__.'/OfficeExpenseNativeConnection.php';

/** Trusted server-only port. No request fields, credentials, connection creation or automatic installation. */
interface OfficeExpenseSchemaBackend
{
    public function prefix(): string;
    public function describe(string $table): ?array;
    public function executeDDL(string $sql): void;
    public function withExclusiveLock(callable $action): array;
    public function marker(string $version='m119_office_expenses_drafts_v1'): ?array;
    public function writeMarker(string $version,string $hash,string $status): void;
}

/** Explicit fresh-install migration. Existing partial/unmarked/drifted schema requires manual reconciliation. */
final class OfficeExpenseNativeSchemaMigration
{
    public const VERSION='m119_office_expenses_drafts_v1';
    public const RECEIPT_VERSION='m119_office_expenses_receipts_v1';
    private $backend;private $directory;private $receiptStage;private $version;
    public function __construct(OfficeExpenseSchemaBackend $backend,?string $directory=null,bool $receiptStage=false)
    {
        if(!preg_match('/^[A-Za-z0-9_]{1,40}$/D',$backend->prefix()))throw new InvalidArgumentException('Invalid native schema prefix.');
        $this->backend=$backend;$this->receiptStage=$receiptStage;$this->version=$receiptStage?self::RECEIPT_VERSION:self::VERSION;
        $this->directory=$directory??dirname(__DIR__).'/schema'.($receiptStage?'/receipts':'');
    }
    public static function forReceipts(OfficeExpenseSchemaBackend $backend,?string $directory=null): self {return new self($backend,$directory,true);}
    public function plan(): array
    {
        $artifact=$this->artifact();if($this->receiptStage)(new self($this->backend))->assertReady();$tables=array();$present=0;$matches=true;
        foreach($artifact['catalog'] as $table=>$expected){
            $actual=$this->backend->describe($table);$same=$actual!==null&&$this->same($actual,$expected);
            if($actual!==null)$present++;if(!$same)$matches=false;$tables[$table]=$actual===null?'missing':($same?'matching':'drift');
        }
        $registry=$this->backend->prefix().'cpo_oe_schema';$marker=($this->receiptStage||$tables[$registry]==='matching')?$this->backend->marker($this->version):null;
        if($present===0&&$marker===null)$state='empty';
        elseif(!$this->receiptStage&&$tables[$registry]==='missing')$state='unmarked';
        elseif($marker!==null&&($marker['schema_key']??null)===$this->version&&($marker['manifest_hash']??null)===$artifact['manifest_hash']&&($marker['status']??null)==='installing')$state='partial';
        elseif(!$matches)$state='drift';
        elseif($marker===null)$state='unmarked';
        elseif(($marker['schema_key']??null)!==$this->version||($marker['manifest_hash']??null)!==$artifact['manifest_hash']||($marker['status']??null)!=='ready')$state='drift';
        else $state='installed';
        return array('version'=>$this->version,'manifest_hash'=>$artifact['manifest_hash'],'state'=>$state,'tables'=>$tables,'schema_matches'=>$matches,'statements'=>$artifact['statements']);
    }
    public function assertReady(): void {if($this->plan()['state']!=='installed')throw new DomainException('Native operational schema is not verified ready.');}
    public function install(string $reviewedHash): array
    {
        $artifact=$this->artifact();
        if(!preg_match('/^[a-f0-9]{64}$/D',$reviewedHash)||!hash_equals($artifact['manifest_hash'],$reviewedHash))throw new DomainException('Migration artifact differs from the reviewed manifest.');
        return $this->backend->withExclusiveLock(function()use($artifact){
            $plan=$this->plan();if($plan['state']==='installed')return $plan;
            if($plan['state']!=='empty')throw new DomainException('Existing native schema needs manual reconciliation; no automatic overwrite or resume.');
            $offset=$this->receiptStage?0:2;
            foreach(array_slice($artifact['statements'],0,$offset) as $sql)$this->backend->executeDDL($sql);
            $this->backend->writeMarker($this->version,$artifact['manifest_hash'],'installing');
            foreach(array_slice($artifact['statements'],$offset) as $sql)$this->backend->executeDDL($sql);
            if(!$this->plan()['schema_matches'])throw new RuntimeException('Created native schema differs from the artifact.');
            $this->backend->writeMarker($this->version,$artifact['manifest_hash'],'ready');
            $done=$this->plan();if($done['state']!=='installed')throw new RuntimeException('Native migration completion was not verified.');return $done;
        });
    }
    public function reversalPlan(): array
    {
        return array('preserve_data'=>true,'destructive_sql'=>array(),'actions'=>array('Disable entrypoint factories and writes.','Retain source, audit, schema registry and receipt metadata/blobs.','Reconcile partial or uncertain outcomes using a fresh physical connection.','Restore only through an approved consistent source/audit/blob backup and verified recovery plan.'));
    }
    private function artifact(): array
    {
        $raw=@file_get_contents($this->directory.'/manifest.json');if(!is_string($raw))throw new RuntimeException('Native migration manifest is unavailable.');
        try{$manifest=json_decode($raw,true,512,JSON_THROW_ON_ERROR);}catch(Throwable $error){throw new RuntimeException('Invalid native migration manifest.',0,$error);}
        $names=$this->receiptStage?array('001_receipts.sql','002_receipt_audit.sql'):array('003_schema_state.sql','001_drafts.sql','002_draft_audit.sql');
        if(!is_array($manifest)||($manifest['version']??null)!==$this->version||!is_array($manifest['files']??null)||array_keys($manifest['files'])!==$names)throw new RuntimeException('Unexpected native migration artifact.');
        if($this->receiptStage&&($manifest['requires'][self::VERSION]??null)!==hash_file('sha256',dirname(__DIR__).'/schema/manifest.json'))throw new RuntimeException('Receipt migration dependency differs.');
        $statements=array();$catalog=array();$allowed=$this->receiptStage?array('llx_cpo_oe_receipt','llx_cpo_oe_receipt_audit'):array('llx_cpo_oe_schema','llx_cpo_oe_draft','llx_cpo_oe_draft_audit');
        foreach($names as $name){
            $bytes=@file_get_contents($this->directory.'/'.$name);$hash=$manifest['files'][$name];
            if(!is_string($bytes)||!is_string($hash)||!preg_match('/^[a-f0-9]{64}$/D',$hash)||!hash_equals($hash,hash('sha256',$bytes)))throw new RuntimeException('Native migration SQL checksum mismatch.');
            foreach(explode(';',preg_replace('/--[^\n]*/','',$bytes)) as $sql){
                $sql=trim($sql);if($sql==='')continue;
                if(preg_match('/^CREATE TABLE (llx_cpo_oe_[a-z_]+) \((.*)\) ENGINE=innodb$/sD',$sql,$m)){
                    if(!in_array($m[1],$allowed,true)||isset($catalog[$m[1]]))throw new RuntimeException('Unexpected native migration table.');
                    $columns=array();foreach(explode(',',$m[2]) as $definition){
                        if(!preg_match('/^\s*([a-z_]+) (integer|(?:var)?char\(\d+\)|longtext|datetime)(.*)$/D',trim($definition),$column))throw new RuntimeException('Unsupported native migration column.');
                        $columns[$column[1]]=array('type'=>$column[2],'nullable'=>strpos($column[3],'NOT NULL')===false&&strpos($column[3],'PRIMARY KEY')===false,'auto'=>strpos($column[3],'AUTO_INCREMENT')!==false,'collation'=>strpos($column[3],'COLLATE ascii_bin')!==false?'ascii_bin':null,'default'=>null);
                    }
                    $catalog[$m[1]]=array('engine'=>'innodb','columns'=>$columns,'indexes'=>array('PRIMARY'=>array('unique'=>true,'columns'=>array('rowid'),'prefixes'=>array(null),'kind'=>'BTREE')));
                }elseif(preg_match('/^CREATE UNIQUE INDEX ([a-z_]+) ON (llx_cpo_oe_[a-z_]+) \(([a-z_,]+)\)$/D',$sql,$m)){
                    if(!isset($catalog[$m[2]])||isset($catalog[$m[2]]['indexes'][$m[1]]))throw new RuntimeException('Unexpected native migration index.');
                    $cols=explode(',',$m[3]);foreach($cols as $column)if(!isset($catalog[$m[2]]['columns'][$column]))throw new RuntimeException('Unknown native index column.');
                    $catalog[$m[2]]['indexes'][$m[1]]=array('unique'=>true,'columns'=>$cols,'prefixes'=>array_fill(0,count($cols),null),'kind'=>'BTREE');
                }else throw new RuntimeException('Only reviewed owned CREATE statements are supported.');
                $statements[]=str_replace('llx_',$this->backend->prefix(),$sql);
            }
        }
        if(array_keys($catalog)!==$allowed||count($statements)!==($this->receiptStage?5:7))throw new RuntimeException('Incomplete native migration artifact.');
        $prefixed=array();foreach($catalog as $name=>$description)$prefixed[str_replace('llx_',$this->backend->prefix(),$name)]=$description;
        return array('manifest_hash'=>hash('sha256',$raw),'statements'=>$statements,'catalog'=>$prefixed);
    }
    private function same(array $actual,array $expected): bool
    {
        if(($actual['engine']??null)!==$expected['engine']||array_keys($actual['columns']??array())!==array_keys($expected['columns']))return false;
        foreach($expected['columns'] as $name=>$column)foreach($column as $field=>$value)if(($field!=='collation'||$value!==null)&&(!array_key_exists($field,$actual['columns'][$name])||$actual['columns'][$name][$field]!==$value))return false;
        $a=$actual['indexes']??array();$e=$expected['indexes'];ksort($a);ksort($e);return $a===$e;
    }
}

/** Existing Dolibarr mysqli handle only; metadata/locking and explicitly invoked owned DDL. */
final class OfficeExpenseMysqliSchemaBackend implements OfficeExpenseSchemaBackend
{
    private $db;private $connection;
    public function __construct($db)
    {
        if(!is_object($db)||($db->type??null)!=='mysqli'||!defined('MAIN_DB_PREFIX')||!preg_match('/^[A-Za-z0-9_]{1,40}$/D',MAIN_DB_PREFIX)||!is_string($db->database_name??null)||$db->database_name==='')throw new InvalidArgumentException('Existing native mysqli context is required.');
        $this->db=$db;$this->connection=new OfficeExpenseNativeConnection($db);
    }
    public function prefix(): string {return MAIN_DB_PREFIX;}
    private function assertContext(): void
    {
        $this->connection->outer();$selected=$this->connection->one('SELECT DATABASE() AS selected_database');
        if(($selected['selected_database']??null)!==$this->db->database_name)throw new DomainException('Selected native database differs from the declared context.');
    }
    private function table(string $table): string
    {
        if(!in_array($table,array(MAIN_DB_PREFIX.'cpo_oe_schema',MAIN_DB_PREFIX.'cpo_oe_draft',MAIN_DB_PREFIX.'cpo_oe_draft_audit',MAIN_DB_PREFIX.'cpo_oe_receipt',MAIN_DB_PREFIX.'cpo_oe_receipt_audit'),true))throw new InvalidArgumentException('Unknown owned migration table.');return $table;
    }
    public function describe(string $table): ?array
    {
        $this->assertContext();
        $name=$this->connection->quote($this->table($table));$where='TABLE_SCHEMA=DATABASE() AND TABLE_NAME='.$name;
        $row=$this->connection->one('SELECT ENGINE AS engine FROM information_schema.TABLES WHERE '.$where);if($row===null)return null;
        $columns=array();foreach($this->connection->rows('SELECT COLUMN_NAME AS name,COLUMN_TYPE AS type,IS_NULLABLE AS nullable,COLUMN_DEFAULT AS value_default,EXTRA AS extra,COLLATION_NAME AS collation FROM information_schema.COLUMNS WHERE '.$where.' ORDER BY ORDINAL_POSITION') as $c){
            $type=strtolower($c['type']);if(preg_match('/^(?:int|integer)(?:\(\d+\))?$/D',$type))$type='integer';
            $columns[$c['name']]=array('type'=>$type,'nullable'=>$c['nullable']==='YES','auto'=>$c['extra']==='auto_increment','collation'=>$c['collation'],'default'=>$c['value_default']);
        }
        $indexes=array();foreach($this->connection->rows('SELECT INDEX_NAME AS name,NON_UNIQUE AS non_unique,COLUMN_NAME AS column_name,SUB_PART AS sub_part,INDEX_TYPE AS kind FROM information_schema.STATISTICS WHERE '.$where.' ORDER BY INDEX_NAME,SEQ_IN_INDEX') as $i){
            if(!isset($indexes[$i['name']]))$indexes[$i['name']]=array('unique'=>(int)$i['non_unique']===0,'columns'=>array(),'prefixes'=>array(),'kind'=>$i['kind']);
            $indexes[$i['name']]['columns'][]=$i['column_name'];$indexes[$i['name']]['prefixes'][]=$i['sub_part']===null?null:(int)$i['sub_part'];
        }
        return array('engine'=>strtolower((string)$row['engine']),'columns'=>$columns,'indexes'=>$indexes);
    }
    public function withExclusiveLock(callable $action): array
    {
        $this->assertContext();$name='cpo_oe_'.substr(hash('sha256',$this->db->database_name.'|'.MAIN_DB_PREFIX),0,48);$q=$this->connection->quote($name);
        $owner=$this->connection->one('SELECT IS_USED_LOCK('.$q.') AS owner,CONNECTION_ID() AS connection_id');
        if($owner!==null&&$owner['owner']!==null&&(string)$owner['owner']===(string)$owner['connection_id'])throw new DomainException('Do not inherit or recursively acquire an existing migration lock.');
        try{$lock=$this->connection->one('SELECT GET_LOCK('.$q.',0) AS acquired');}
        catch(Throwable $error){$this->connection->retire();$this->releaseKnownLock($q,$error);throw $error;}
        if(($lock['acquired']??null)===null){$this->connection->retire();$error=new RuntimeException('Migration lock acquisition outcome requires fresh verification.');$this->releaseKnownLock($q,$error);throw $error;}
        if((int)$lock['acquired']!==1)throw new DomainException('Native migration lock is unavailable.');
        $failure=null;$result=null;
        try{$result=$action();}catch(Throwable $error){$failure=$error;}
        $this->releaseKnownLock($q,$failure);
        if($failure!==null)throw $failure;return $result;
    }
    private function releaseKnownLock(string $quotedName,?Throwable $failure): void
    {
        // A retired handle must not issue business queries. This single bounded cleanup query
        // releases only the advisory lock acquired here; quarantine is never cleared.
        try{
            $result=$this->db->query('SELECT RELEASE_LOCK('.$quotedName.') AS released');
            $row=$result?$this->db->fetch_object($result):null;
            if(!$row||(int)($row->released??0)!==1)throw new RuntimeException('Advisory-lock release was not confirmed.');
        }catch(Throwable $cleanupError){
            $this->connection->retire();throw new RuntimeException('Migration lock release is unconfirmed; discard the physical connection and verify server lock ownership before retry.',0,$failure??$cleanupError);
        }
    }
    public function executeDDL(string $sql): void
    {
        $this->assertContext();
        try{$this->connection->query($sql);if($this->db->transaction_opened!==0)throw new RuntimeException('Unexpected transaction after native DDL.');}
        catch(Throwable $error){$this->connection->retire();throw new RuntimeException('Native DDL outcome requires a fresh connection and schema inspection; no destructive recovery.',0,$error);}
    }
    public function marker(string $version='m119_office_expenses_drafts_v1'): ?array {return $this->connection->one('SELECT schema_key,manifest_hash,status,date_creation,date_modification FROM '.MAIN_DB_PREFIX.'cpo_oe_schema WHERE schema_key='.$this->connection->quote($version));}
    public function writeMarker(string $version,string $hash,string $status): void
    {
        if(!in_array($version,array(OfficeExpenseNativeSchemaMigration::VERSION,OfficeExpenseNativeSchemaMigration::RECEIPT_VERSION),true)||!preg_match('/^[a-f0-9]{64}$/D',$hash)||!in_array($status,array('installing','ready'),true))throw new InvalidArgumentException('Invalid migration marker.');
        $this->assertContext();
        $this->connection->beginOwned();
        try{
            $old=$this->marker($version);$q=function($s){return $this->connection->quote($s);};$date=gmdate('Y-m-d H:i:s');$table=MAIN_DB_PREFIX.'cpo_oe_schema';
            if($old===null){
                if($status!=='installing')throw new DomainException('Migration start marker is missing.');
                $this->connection->query('INSERT INTO '.$table.'(schema_key,manifest_hash,status,date_creation,date_modification) VALUES('.$q($version).','.$q($hash).','.$q($status).','.$q($date).','.$q($date).')');$created=$date;
            }else{
                if($old['schema_key']!==$version||$old['manifest_hash']!==$hash||$old['status']!=='installing'||$status!=='ready')throw new DomainException('Migration marker conflicts.');
                $created=$old['date_creation'];$this->connection->query('UPDATE '.$table.' SET status='.$q($status).',date_modification='.$q($date).' WHERE schema_key='.$q($version).' AND manifest_hash='.$q($hash)." AND status='installing'");
            }
            $expected=array('schema_key'=>$version,'manifest_hash'=>$hash,'status'=>$status,'date_creation'=>$created,'date_modification'=>$date);
            if($this->marker($version)!==$expected)throw new RuntimeException('Migration marker readback differs.');$this->connection->commitOwned();
        }catch(Throwable $error){$this->connection->rollbackOwnedOnFailure($error);throw new RuntimeException('Migration marker was not confirmed.',0,$error);}
    }
}
