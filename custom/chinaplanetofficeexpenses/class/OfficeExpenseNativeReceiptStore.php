<?php
declare(strict_types=1);
require_once __DIR__.'/OfficeExpenseReceiptService.php';
require_once __DIR__.'/OfficeExpenseNativeDraftStore.php';
require_once __DIR__.'/OfficeExpenseNativeSchemaMigration.php';

/** Private operational receipts on the existing native DB. No route, installer, credentials or financial API. */
final class OfficeExpenseNativeReceiptStore implements OfficeExpenseReceiptStore
{
    private $connection;private $sources;private $root;private $maxCount;private $maxBytes;private $database;
    public function __construct($db,OfficeExpenseNativeDraftStore $sources,string $root,string $webRoot,int $maxCount=10,int $maxBytes=26214400)
    {
        if(!$sources->usesConnection($db))throw new InvalidArgumentException('Receipt/source native connections differ.');
        $real=realpath($root);$web=realpath($webRoot);
        if(!$real||!$web||is_link($root)||!is_dir($real)||!is_dir($web)||(fileperms($real)&0077)!==0
            ||$real===$web||strpos($real,$web.'/')===0||strpos($web,$real.'/')===0||$maxCount<1||$maxCount>50||$maxBytes<1||$maxBytes>268435456)throw new InvalidArgumentException('A provisioned private receipt root outside the web root is required.');
        $this->root=$real;$this->rootCheck();
        $schema=OfficeExpenseNativeSchemaMigration::forReceipts(new OfficeExpenseMysqliSchemaBackend($db));$schema->assertReady();
        $this->connection=new OfficeExpenseNativeConnection($db);$this->sources=$sources;$this->database=$db->database_name;$this->maxCount=$maxCount;$this->maxBytes=$maxBytes;
    }
    public function add(array $draft,string $key,array $file,string $original,string $preview): array
    {
        $this->source($draft);$this->connection->outer();$this->directory($draft,false);
        if(!preg_match('/^[A-Za-z0-9_-]{8,80}$/D',$key))throw new InvalidArgumentException('Invalid receipt retry key.');
        OfficeExpenseReceiptService::validateName($file['original_name']??'');
        foreach(array('original'=>array($original,'size','sha256'),'preview'=>array($preview,'preview_size','preview_sha256')) as $evidence){
            list($bytes,$size,$digest)=$evidence;
            if(!is_int($file[$size]??null)||$file[$size]<=0||$file[$size]>16777216||strlen($bytes)!==$file[$size]||!is_string($file[$digest]??null)||!hash_equals($file[$digest],hash('sha256',$bytes)))throw new InvalidArgumentException('Receipt byte evidence differs.');
        }
        $intent=$this->intent($file);$created=array();$commitAttempted=false;$this->connection->beginOwned();
        try{
            $this->lockSource($draft);$row=$this->connection->one('SELECT * FROM '.MAIN_DB_PREFIX.'cpo_oe_receipt WHERE '.$this->where($draft).' AND submission_key='.$this->q($key));
            if($row!==null){
                $meta=$this->metadata($row,$draft);if(!hash_equals($row['intent_hash'],$intent))throw new OfficeExpenseReceiptConflict('Receipt retry has a different intent.');
                $this->contents($draft,$meta,'original');$this->contents($draft,$meta,'preview');
            }else{
                $usage=$this->usage($draft);
                if($usage['logical_count']+1>$this->maxCount||$usage['bytes']+strlen($original)+strlen($preview)>$this->maxBytes)throw new OfficeExpenseReceiptQuota('Receipt quota includes unresolved private files.');
                $meta=array_merge($file,array('id'=>bin2hex(random_bytes(16)),'entity'=>$draft['entity'],'owner_id'=>$draft['owner_id'],'office'=>$draft['office'],'draft_id'=>$draft['id'],'created_at'=>gmdate('Y-m-d\TH:i:s\Z')));
                $dir=$this->directory($draft,true);
                foreach(array('original'=>$original,'preview'=>$preview) as $variant=>$bytes){
                    $path=$dir.'/'.$meta['id'].'.'.$variant;$handle=@fopen($path,'xb');if(!$handle)throw new RuntimeException('Cannot create private receipt.');$created[]=$path;
                    try{if(!chmod($path,0600))throw new RuntimeException('Cannot protect private receipt.');$offset=0;while($offset<strlen($bytes)){$written=fwrite($handle,substr($bytes,$offset));if(!$written)throw new RuntimeException('Private receipt write failed.');$offset+=$written;}if(!fflush($handle))throw new RuntimeException('Private receipt flush failed.');}
                    finally{fclose($handle);}
                }
                $this->connection->query('INSERT INTO '.MAIN_DB_PREFIX.'cpo_oe_receipt(entity,fk_user_creat,office,draft_ref,receipt_ref,submission_key,source_hash,intent_hash,metadata_hash,document_json,status,date_creation) VALUES('
                    .$draft['entity'].','.$draft['owner_id'].','.$this->q($draft['office']).','.$this->q($draft['id']).','.$this->q($meta['id']).','.$this->q($key).','.$this->q($this->hash($draft)).','.$this->q($intent).','.$this->q($this->hash($meta)).','.$this->q($this->json($meta)).",'ready',".$this->q($this->sqlDate($meta['created_at'])).')');
                $this->audit($draft,$meta,'receipt_added');if($this->find($draft,$meta['id'])!==$meta)throw new RuntimeException('Receipt metadata readback differs.');
            }
            $commitAttempted=true;$this->connection->commitOwned();return $meta;
        }catch(Throwable $error){
            $this->connection->rollbackOwnedOnFailure($error);
            // A lost commit acknowledgement may follow durable SQL. Never remove possibly committed evidence.
            if(!$commitAttempted){$this->directory($draft,false);foreach($created as $path)if(is_file($path)&&!is_link($path)&&!@unlink($path))throw new RuntimeException('Private receipt cleanup needs reconciliation.',0,$error);}
            if($error instanceof DomainException||$error instanceof InvalidArgumentException)throw $error;
            throw new RuntimeException('Native receipt persistence requires verified retry/reconciliation.',0,$error);
        }
    }
    public function listForDraft(array $draft): array {$this->source($draft);$this->connection->outer();return $this->listRows($draft);}
    public function readForDraft(array $draft,string $id,string $variant): array
    {
        $this->source($draft);$this->connection->outer();if(!in_array($variant,array('original','preview'),true))throw new InvalidArgumentException('Unknown receipt variant.');
        $this->connection->beginOwned();
        try{
            $this->lockSource($draft);$meta=$this->find($draft,$id);$bytes=$this->contents($draft,$meta,$variant);$this->audit($draft,$meta,'receipt_access_started');$this->connection->commitOwned();
            return array('bytes'=>$bytes,'filename'=>$variant==='original'?$meta['original_name']:'preview-'.$meta['id'].($meta['preview_mime']==='image/png'?'.png':'.jpg'),
                'mime'=>'application/octet-stream','source_mime'=>$variant==='original'?$meta['mime']:$meta['preview_mime'],'disposition'=>'attachment');
        }catch(Throwable $error){$this->connection->rollbackOwnedOnFailure($error);if($error instanceof DomainException||$error instanceof InvalidArgumentException)throw $error;throw new RuntimeException('Native receipt access failed; no delivery confirmed.',0,$error);}
    }
    public function auditForDraft(array $draft): array
    {
        $this->source($draft);$this->connection->outer();$this->listRows($draft);
        $rows=$this->connection->rows('SELECT * FROM '.MAIN_DB_PREFIX.'cpo_oe_receipt_audit WHERE '.$this->where($draft).' ORDER BY rowid');$out=array();
        foreach($rows as $row){$meta=$this->find($draft,$row['receipt_ref']);$this->auditEvidence($row,$draft,$meta,$row['event_key'],$row['event'],$row['date_creation']);$out[]=array('receipt_id'=>$row['receipt_ref'],'event'=>$row['event'],'created_at'=>str_replace(' ','T',$row['date_creation']).'Z');}return $out;
    }
    public function inspectForDraft(array $draft): array {$this->source($draft);$this->connection->outer();$usage=$this->usage($draft);unset($usage['logical_count']);return $usage;}
    private function source(array $draft): void
    {
        $this->connection->assertUsable();$selected=$this->connection->one('SELECT DATABASE() AS selected_database');
        if(($selected['selected_database']??null)!==$this->database)throw new DomainException('Receipt native database context differs.');
        if(!is_int($draft['entity']??null)||$draft['entity']<1||!is_int($draft['owner_id']??null)||$draft['owner_id']<1||!is_string($draft['id']??null)||!preg_match('/^[a-f0-9]{32}$/D',$draft['id'])
            ||$this->sources->findOwn($draft['entity'],$draft['owner_id'],$draft['id'])!==$draft)throw new DomainException('Receipt source is not the persisted owned draft.');
        $this->directory($draft,false);
    }
    private function lockSource(array $draft): void
    {
        $this->connection->query('UPDATE '.MAIN_DB_PREFIX.'cpo_oe_draft SET status=status WHERE entity='.$draft['entity'].' AND fk_user_creat='.$draft['owner_id'].' AND draft_ref='.$this->q($draft['id']));$this->source($draft);
    }
    private function where(array $draft): string {return 'entity='.$draft['entity'].' AND fk_user_creat='.$draft['owner_id'].' AND draft_ref='.$this->q($draft['id']);}
    private function listRows(array $draft): array
    {
        $rows=$this->connection->rows('SELECT * FROM '.MAIN_DB_PREFIX.'cpo_oe_receipt WHERE '.$this->where($draft).' ORDER BY rowid');return array_map(function($r)use($draft){return $this->metadata($r,$draft);},$rows);
    }
    private function find(array $draft,string $id): array
    {
        if(!preg_match('/^[a-f0-9]{32}$/D',$id))throw new DomainException('Receipt unavailable.');$row=$this->connection->one('SELECT * FROM '.MAIN_DB_PREFIX.'cpo_oe_receipt WHERE '.$this->where($draft).' AND receipt_ref='.$this->q($id));
        if($row===null)throw new DomainException('Receipt unavailable.');return $this->metadata($row,$draft);
    }
    private function metadata(array $row,array $draft): array
    {
        try{$meta=json_decode($row['document_json'],true,512,JSON_THROW_ON_ERROR);}catch(Throwable $error){throw new RuntimeException('Receipt metadata is invalid.',0,$error);}
        if(!is_array($meta)||!hash_equals($row['metadata_hash'],$this->hash($meta))||$row['status']!=='ready'||(int)$row['entity']!==$draft['entity']||(int)$row['fk_user_creat']!==$draft['owner_id']||$row['office']!==$draft['office']||$row['draft_ref']!==$draft['id']
            ||($meta['entity']??null)!==$draft['entity']||($meta['owner_id']??null)!==$draft['owner_id']||($meta['office']??null)!==$draft['office']||($meta['draft_id']??null)!==$draft['id']||($meta['id']??null)!==$row['receipt_ref']||!preg_match('/^[a-f0-9]{32}$/D',$row['receipt_ref'])
            ||!is_string($meta['created_at']??null)||!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/D',$meta['created_at'])||$row['date_creation']!==$this->sqlDate($meta['created_at'])||!hash_equals($row['source_hash'],$this->hash($draft)))throw new RuntimeException('Receipt source/metadata evidence differs.');
        OfficeExpenseReceiptService::validateName($meta['original_name']??'');
        foreach(array('mime','preview_mime') as $field)if(!in_array($meta[$field]??null,array('image/png','image/jpeg'),true))throw new RuntimeException('Receipt format evidence differs.');
        foreach(array('size','preview_size') as $field)if(!is_int($meta[$field]??null)||$meta[$field]<1||$meta[$field]>16777216)throw new RuntimeException('Receipt size evidence differs.');
        foreach(array('sha256','preview_sha256') as $field)if(!is_string($meta[$field]??null)||!preg_match('/^[a-f0-9]{64}$/D',$meta[$field]))throw new RuntimeException('Receipt digest evidence differs.');
        if(!hash_equals($row['intent_hash'],$this->intent($meta)))throw new RuntimeException('Receipt retry evidence differs.');
        $key='created-'.$meta['id'];$audit=$this->connection->one('SELECT * FROM '.MAIN_DB_PREFIX.'cpo_oe_receipt_audit WHERE entity='.$draft['entity'].' AND event_key='.$this->q($key));
        if($audit===null)throw new RuntimeException('Receipt creation audit is missing.');$this->auditEvidence($audit,$draft,$meta,$key,'receipt_added',$row['date_creation']);return $meta;
    }
    private function audit(array $draft,array $meta,string $event): void
    {
        $key=$event==='receipt_added'?'created-'.$meta['id']:'access-'.bin2hex(random_bytes(16));$date=$event==='receipt_added'?$this->sqlDate($meta['created_at']):gmdate('Y-m-d H:i:s');
        $this->connection->query('INSERT INTO '.MAIN_DB_PREFIX.'cpo_oe_receipt_audit(entity,fk_user_creat,office,draft_ref,receipt_ref,event_key,event,original_hash,date_creation) VALUES('.$draft['entity'].','.$draft['owner_id'].','.$this->q($draft['office']).','.$this->q($draft['id']).','.$this->q($meta['id']).','.$this->q($key).','.$this->q($event).','.$this->q($meta['sha256']).','.$this->q($date).')');
        $row=$this->connection->one('SELECT * FROM '.MAIN_DB_PREFIX.'cpo_oe_receipt_audit WHERE entity='.$draft['entity'].' AND event_key='.$this->q($key));
        if($row===null)throw new RuntimeException('Receipt audit was not persisted.');$this->auditEvidence($row,$draft,$meta,$key,$event,$date);
    }
    private function auditEvidence(array $row,array $draft,array $meta,string $key,string $event,string $date): void
    {
        if(!in_array($event,array('receipt_added','receipt_access_started'),true)||!preg_match('/^(created|access)-[a-f0-9]{32}$/D',$key)||(int)$row['entity']!==$draft['entity']||(int)$row['fk_user_creat']!==$draft['owner_id']||$row['office']!==$draft['office']||$row['draft_ref']!==$draft['id']||$row['receipt_ref']!==$meta['id']||$row['event_key']!==$key||$row['event']!==$event||$row['original_hash']!==$meta['sha256']||$row['date_creation']!==$date||!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D',$date))throw new RuntimeException('Receipt audit readback differs.');
    }
    private function contents(array $draft,array $meta,string $variant): string
    {
        $dir=$this->directory($draft,false);$path=$dir.'/'.$meta['id'].'.'.$variant;$size=$meta[$variant==='original'?'size':'preview_size'];clearstatcache(true,$path);
        if(is_link($path)||!is_file($path)||realpath(dirname($path))!==$dir||filesize($path)!==$size||(fileperms($path)&0077)!==0)throw new RuntimeException('Private receipt is missing or unsafe.');
        $bytes=@file_get_contents($path,false,null,0,$size+1);if(!is_string($bytes)||strlen($bytes)!==$size||!hash_equals($meta[$variant==='original'?'sha256':'preview_sha256'],hash('sha256',$bytes)))throw new RuntimeException('Private receipt checksum differs.');return $bytes;
    }
    private function rootCheck(): void
    {
        clearstatcache(true,$this->root);$marker=$this->root.'/.cpo-office-expenses-private';
        if(is_link($this->root)||realpath($this->root)!==$this->root||!is_dir($this->root)||(fileperms($this->root)&0077)!==0||is_link($marker)||!is_file($marker)||(fileperms($marker)&0077)!==0||file_get_contents($marker)!=='cpo-office-expenses-private-v1')throw new InvalidArgumentException('Private receipt root is not provisioned or changed.');
    }
    private function directory(array $draft,bool $create): string
    {
        try{$this->rootCheck();}catch(InvalidArgumentException $error){throw new RuntimeException('Private receipt root changed.',0,$error);}
        if(!is_int($draft['entity']??null)||$draft['entity']<1||!is_int($draft['owner_id']??null)||$draft['owner_id']<1||!is_string($draft['id']??null)||!preg_match('/^[a-f0-9]{32}$/D',$draft['id']))throw new InvalidArgumentException('Invalid trusted receipt scope.');
        $path=$this->root;foreach(array($draft['entity'],$draft['owner_id'],$draft['id']) as $part){$path.='/'.$part;clearstatcache(true,$path);if(is_link($path))throw new RuntimeException('Unsafe private receipt directory.');
            if(!file_exists($path)&&$create&&!mkdir($path,0700))throw new RuntimeException('Cannot create private receipt directory.');
            if(file_exists($path)&&(!is_dir($path)||(fileperms($path)&0077)!==0||strpos((string)realpath($path),$this->root.'/')!==0))throw new RuntimeException('Unsafe private receipt directory.');}return $path;
    }
    private function usage(array $draft): array
    {
        $dir=$this->directory($draft,false);$known=array();foreach($this->listRows($draft) as $meta)$known[$meta['id']]=true;$logical=$known;$files=0;$bytes=0;$orphans=0;
        if(is_dir($dir))foreach(scandir($dir) as $name){if($name==='.'||$name==='..')continue;if(!preg_match('/^([a-f0-9]{32})\.(original|preview)$/D',$name,$m)||is_link($dir.'/'.$name)||!is_file($dir.'/'.$name)||(fileperms($dir.'/'.$name)&0077)!==0)throw new RuntimeException('Unrecognized private file needs reconciliation.');$files++;$bytes+=filesize($dir.'/'.$name);$logical[$m[1]]=true;if(!isset($known[$m[1]]))$orphans++;}
        return array('receipt_count'=>count($known),'logical_count'=>count($logical),'files'=>$files,'bytes'=>$bytes,'orphan_files'=>$orphans);
    }
    private function json(array $v): string {return json_encode($v,JSON_THROW_ON_ERROR);}
    private function hash(array $v): string {return hash('sha256',$this->json($v));}
    private function intent(array $f): string {return $this->hash(array('name'=>$f['original_name'],'mime'=>$f['mime'],'size'=>$f['size'],'sha256'=>$f['sha256']));}
    private function q(string $v): string {return $this->connection->quote($v);}
    private function sqlDate(string $date): string {return str_replace(array('T','Z'),array(' ',''),$date);}
}
