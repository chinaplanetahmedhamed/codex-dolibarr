<?php
declare(strict_types=1);
require_once __DIR__.'/../../../custom/chinaplanetoperations/office_expenses/class/OfficeExpenseReceiptService.php';

/** Owned synthetic workbench ONLY; private blobs and SQLite metadata/audit. Never a runtime factory. */
final class LocalReceiptStore implements OfficeExpenseReceiptStore
{
    private $pdo;private $root;private $maxCount;private $maxBytes;
    public function __construct(PDO $pdo,string $root,string $webRoot,int $maxCount=10,int $maxBytes=26214400)
    {
        $real=realpath($root);$web=realpath($webRoot);
        if($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)!=='sqlite'||$pdo->inTransaction()||!$real||!$web||is_link($root)
            ||!is_dir($real)||!is_file($real.'/.m119-receipts-synthetic')||is_link($real.'/.m119-receipts-synthetic')||(fileperms($real)&0077)!==0
            ||$real===$web||strpos($real,$web.'/')===0||strpos($web,$real.'/')===0||$maxCount<=0||$maxBytes<=0)throw new InvalidArgumentException('Receipt storage requires an owned private synthetic root outside the web root.');
        $databases=$pdo->query('PRAGMA database_list')->fetchAll(PDO::FETCH_ASSOC);$database='';
        foreach($databases as $row)if($row['name']==='main')$database=$row['file'];
        if($database===''||dirname((string)realpath($database))!==$real||is_link($database))throw new InvalidArgumentException('Receipt metadata must be in the owned private root.');
        $this->pdo=$pdo;$this->root=$real;$this->maxCount=$maxCount;$this->maxBytes=$maxBytes;$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
        $pdo->exec("CREATE TABLE IF NOT EXISTS oe_receipt(id TEXT PRIMARY KEY,entity INTEGER NOT NULL,owner_id INTEGER NOT NULL,office TEXT NOT NULL,draft_id TEXT NOT NULL,submission_key TEXT NOT NULL,draft_hash TEXT NOT NULL,intent_hash TEXT NOT NULL,meta_hash TEXT NOT NULL,document_json TEXT NOT NULL,status TEXT NOT NULL,UNIQUE(entity,owner_id,draft_id,submission_key))");
        $pdo->exec('CREATE TABLE IF NOT EXISTS oe_receipt_audit(id INTEGER PRIMARY KEY,entity INTEGER NOT NULL,owner_id INTEGER NOT NULL,draft_id TEXT NOT NULL,receipt_id TEXT NOT NULL,event TEXT NOT NULL,sha256 TEXT NOT NULL,created_at TEXT NOT NULL)');
        $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS oe_receipt_added ON oe_receipt_audit(receipt_id) WHERE event='receipt_added'");
    }
    public function add(array $draft,string $key,array $file,string $original,string $preview): array
    {
        $this->directory($draft,false);$this->outer();
        $intent=hash('sha256',$this->json(array('name'=>$file['original_name'],'mime'=>$file['mime'],'size'=>$file['size'],'sha256'=>$file['sha256'])));
        if(hash('sha256',$original)!==$file['sha256']||strlen($original)!==$file['size']||hash('sha256',$preview)!==$file['preview_sha256']||strlen($preview)!==$file['preview_size'])throw new InvalidArgumentException('Receipt byte evidence differs.');
        $created=array();$commitAttempted=false;$this->begin();
        try{
            $row=$this->one('SELECT * FROM oe_receipt WHERE entity=? AND owner_id=? AND draft_id=? AND submission_key=?',array($draft['entity'],$draft['owner_id'],$draft['id'],$key));
            if($row){
                $meta=$this->metadata($row,$draft);
                if(!hash_equals($row['intent_hash'],$intent))throw new OfficeExpenseReceiptConflict('Receipt key already has a different intent.');
                $this->contents($draft,$meta,'original');$this->contents($draft,$meta,'preview');$this->pdo->rollBack();return $meta;
            }
            $usage=$this->usage($draft);
            if($usage['logical_count']+1>$this->maxCount||$usage['bytes']+strlen($original)+strlen($preview)>$this->maxBytes)throw new OfficeExpenseReceiptQuota('Receipt quota exceeded; unresolved files also consume storage.');
            $meta=array_merge($file,array('id'=>bin2hex(random_bytes(16)),'entity'=>$draft['entity'],'owner_id'=>$draft['owner_id'],'office'=>$draft['office'],'draft_id'=>$draft['id'],'created_at'=>gmdate('Y-m-d\TH:i:s\Z')));
            $dir=$this->directory($draft,true);
            foreach(array('original'=>$original,'preview'=>$preview) as $variant=>$bytes){
                $path=$dir.'/'.$meta['id'].'.'.$variant;$handle=@fopen($path,'xb');if(!$handle)throw new RuntimeException('Cannot create private receipt.');$created[]=$path;
                try{if(!chmod($path,0600))throw new RuntimeException('Cannot protect private receipt.');$offset=0;while($offset<strlen($bytes)){$written=fwrite($handle,substr($bytes,$offset));if(!$written)throw new RuntimeException('Receipt write failed.');$offset+=$written;}if(!fflush($handle))throw new RuntimeException('Receipt flush failed.');}
                finally{fclose($handle);}
            }
            $q=$this->pdo->prepare("INSERT INTO oe_receipt(id,entity,owner_id,office,draft_id,submission_key,draft_hash,intent_hash,meta_hash,document_json,status) VALUES(?,?,?,?,?,?,?,?,?,?,'ready')");
            $q->execute(array($meta['id'],$draft['entity'],$draft['owner_id'],$draft['office'],$draft['id'],$key,hash('sha256',$this->json($draft)),$intent,hash('sha256',$this->json($meta)),$this->json($meta)));
            $this->audit($draft,$meta,'receipt_added');$this->find($draft,$meta['id']);
            $commitAttempted=true;if(!$this->pdo->commit()||$this->pdo->inTransaction())throw new RuntimeException('Receipt commit was not confirmed.');return $meta;
        }catch(Throwable $error){
            if($this->pdo->inTransaction()){
                try{$rolledBack=$this->pdo->rollBack();}catch(Throwable $rollbackError){throw new RuntimeException('Receipt rollback is uncertain; private files retained.',0,$rollbackError);}
                if(!$rolledBack||$this->pdo->inTransaction())throw new RuntimeException('Receipt rollback is uncertain; private files retained.',0,$error);
            }
            // A failed commit response may follow a successful commit: never delete possibly committed evidence.
            if(!$commitAttempted)foreach($created as $path)if(is_file($path)&&!is_link($path)&&!@unlink($path))throw new RuntimeException('Receipt cleanup needs reconciliation.',0,$error);
            if($error instanceof DomainException)throw $error;
            throw new RuntimeException($commitAttempted?'Receipt commit needs verified retry/reconciliation.':'Private receipt persistence failed.',0,$error);
        }
    }
    public function listForDraft(array $draft): array
    {
        $this->directory($draft,false);$q=$this->pdo->prepare('SELECT * FROM oe_receipt WHERE entity=? AND owner_id=? AND draft_id=? ORDER BY rowid');
        $q->execute(array($draft['entity'],$draft['owner_id'],$draft['id']));$rows=$q->fetchAll(PDO::FETCH_ASSOC);
        return array_map(function($row)use($draft){return $this->metadata($row,$draft);},$rows);
    }
    public function readForDraft(array $draft,string $id,string $variant): array
    {
        $this->directory($draft,false);$this->outer();if(!in_array($variant,array('original','preview'),true))throw new InvalidArgumentException('Unknown receipt variant.');
        $this->begin();
        try{
            $meta=$this->find($draft,$id);$bytes=$this->contents($draft,$meta,$variant);$this->audit($draft,$meta,'receipt_access_started');
            if(!$this->pdo->commit()||$this->pdo->inTransaction())throw new RuntimeException('Receipt access audit commit was not confirmed.');
            return array('bytes'=>$bytes,'filename'=>$variant==='original'?$meta['original_name']:'preview-'.$meta['id'].($meta['preview_mime']==='image/png'?'.png':'.jpg'),
                'mime'=>'application/octet-stream','source_mime'=>$variant==='original'?$meta['mime']:$meta['preview_mime'],'disposition'=>'attachment');
        }catch(Throwable $error){if($this->pdo->inTransaction())$this->pdo->rollBack();if($error instanceof DomainException)throw $error;throw new RuntimeException('Private receipt access failed.',0,$error);}
    }
    public function auditForDraft(array $draft): array
    {
        $this->directory($draft,false);$q=$this->pdo->prepare('SELECT receipt_id,event,created_at FROM oe_receipt_audit WHERE entity=? AND owner_id=? AND draft_id=? ORDER BY id');
        $q->execute(array($draft['entity'],$draft['owner_id'],$draft['id']));return $q->fetchAll(PDO::FETCH_ASSOC);
    }
    public function inspectForDraft(array $draft): array {$this->listForDraft($draft);$usage=$this->usage($draft);unset($usage['logical_count']);return $usage;}
    private function find(array $draft,string $id): array
    {
        if(!preg_match('/^[a-f0-9]{32}$/D',$id))throw new DomainException('Receipt is unavailable.');
        $row=$this->one('SELECT * FROM oe_receipt WHERE entity=? AND owner_id=? AND draft_id=? AND id=?',array($draft['entity'],$draft['owner_id'],$draft['id'],$id));
        if(!$row)throw new DomainException('Receipt is unavailable.');return $this->metadata($row,$draft);
    }
    private function metadata(array $row,array $draft): array
    {
        try{$meta=json_decode($row['document_json'],true,512,JSON_THROW_ON_ERROR);}catch(Throwable $e){throw new RuntimeException('Receipt metadata integrity failed.',0,$e);}
        if(!is_array($meta)||!hash_equals($row['meta_hash'],hash('sha256',$this->json($meta)))||$row['status']!=='ready'
            ||(int)$row['entity']!==$draft['entity']||(int)$row['owner_id']!==$draft['owner_id']||$row['draft_id']!==$draft['id']||$row['office']!==$draft['office']
            ||($meta['id']??null)!==$row['id']||!preg_match('/^[a-f0-9]{32}$/D',$row['id'])||($meta['draft_id']??null)!==$draft['id']
            ||($meta['entity']??null)!==$draft['entity']||($meta['owner_id']??null)!==$draft['owner_id']||($meta['office']??null)!==$draft['office']
            ||!hash_equals($row['draft_hash'],hash('sha256',$this->json($draft))))throw new RuntimeException('Receipt source association is inconsistent.');
        foreach(array('size','preview_size') as $field)if(!is_int($meta[$field]??null)||$meta[$field]<=0||$meta[$field]>16777216)throw new RuntimeException('Receipt size evidence is invalid.');
        foreach(array('sha256','preview_sha256') as $field)if(!is_string($meta[$field]??null)||!preg_match('/^[a-f0-9]{64}$/D',$meta[$field]))throw new RuntimeException('Receipt digest evidence is invalid.');
        OfficeExpenseReceiptService::validateName($meta['original_name']??'');
        $audit=$this->one("SELECT sha256 FROM oe_receipt_audit WHERE entity=? AND owner_id=? AND draft_id=? AND receipt_id=? AND event='receipt_added'",array($draft['entity'],$draft['owner_id'],$draft['id'],$meta['id']));
        if(!$audit||!hash_equals($audit['sha256'],$meta['sha256']))throw new RuntimeException('Receipt creation audit is missing.');return $meta;
    }
    private function contents(array $draft,array $meta,string $variant): string
    {
        $dir=$this->directory($draft,false);$path=$dir.'/'.$meta['id'].'.'.$variant;$size=$meta[$variant==='original'?'size':'preview_size'];
        clearstatcache(true,$path);
        if(is_link($path)||!is_file($path)||realpath(dirname($path))!==$dir||filesize($path)!==$size)throw new RuntimeException('Private receipt file is missing or changed.');
        $bytes=@file_get_contents($path,false,null,0,$size+1);
        if(!is_string($bytes)||strlen($bytes)!==$size||!hash_equals($meta[$variant==='original'?'sha256':'preview_sha256'],hash('sha256',$bytes)))throw new RuntimeException('Private receipt checksum failed.');return $bytes;
    }
    private function directory(array $draft,bool $create): string
    {
        clearstatcache(true,$this->root);
        if(is_link($this->root)||realpath($this->root)!==$this->root||!is_dir($this->root)||(fileperms($this->root)&0077)!==0
            ||!is_file($this->root.'/.m119-receipts-synthetic')||is_link($this->root.'/.m119-receipts-synthetic'))throw new RuntimeException('Private receipt root changed.');
        if(!is_int($draft['entity']??null)||$draft['entity']<=0||!is_int($draft['owner_id']??null)||$draft['owner_id']<=0
            ||!is_string($draft['id']??null)||!preg_match('/^[a-f0-9]{32}$/D',$draft['id']))throw new InvalidArgumentException('Invalid trusted receipt scope.');
        $path=$this->root;
        foreach(array($draft['entity'],$draft['owner_id'],$draft['id']) as $part){
            $path.='/'.$part;
            clearstatcache(true,$path);
            if(is_link($path))throw new RuntimeException('Receipt directory is unsafe.');
            if(!file_exists($path)&&$create&&!mkdir($path,0700))throw new RuntimeException('Cannot create private receipt directory.');
            if(file_exists($path)&&(!is_dir($path)||(fileperms($path)&0077)!==0||strpos((string)realpath($path),$this->root.'/')!==0))throw new RuntimeException('Receipt directory is unsafe.');
        }
        return $path;
    }
    private function usage(array $draft): array
    {
        $dir=$this->directory($draft,false);$known=array();foreach($this->listForDraft($draft) as $meta)$known[$meta['id']]=true;
        $logical=$known;$files=0;$bytes=0;$orphans=0;
        if(is_dir($dir))foreach(scandir($dir) as $name){
            if($name==='.'||$name==='..')continue;
            if(!preg_match('/^([a-f0-9]{32})\.(original|preview)$/D',$name,$match)||is_link($dir.'/'.$name)||!is_file($dir.'/'.$name))throw new RuntimeException('Unrecognized private receipt file; reconciliation required.');
            $files++;$bytes+=filesize($dir.'/'.$name);$logical[$match[1]]=true;if(!isset($known[$match[1]]))$orphans++;
        }
        return array('receipt_count'=>count($known),'logical_count'=>count($logical),'files'=>$files,'bytes'=>$bytes,'orphan_files'=>$orphans);
    }
    private function audit(array $draft,array $meta,string $event): void
    {
        $date=gmdate('Y-m-d\TH:i:s\Z');
        $q=$this->pdo->prepare('INSERT INTO oe_receipt_audit(entity,owner_id,draft_id,receipt_id,event,sha256,created_at) VALUES(?,?,?,?,?,?,?)');
        $q->execute(array($draft['entity'],$draft['owner_id'],$draft['id'],$meta['id'],$event,$meta['sha256'],$date));
        if($q->rowCount()!==1)throw new RuntimeException('Receipt audit was not persisted.');
        $row=$this->one('SELECT * FROM oe_receipt_audit WHERE id=?',array($this->pdo->lastInsertId()));
        if(!$row||(int)$row['entity']!==$draft['entity']||(int)$row['owner_id']!==$draft['owner_id']||$row['draft_id']!==$draft['id']
            ||$row['receipt_id']!==$meta['id']||$row['event']!==$event||!hash_equals($row['sha256'],$meta['sha256'])||$row['created_at']!==$date)throw new RuntimeException('Receipt audit readback differs.');
    }
    private function outer(): void {if($this->pdo->inTransaction())throw new LogicException('Receipt store owns its transaction.');}
    private function begin(): void
    {
        // Keep PDO's transaction bookkeeping, then acquire SQLite's writer lock before quota reads.
        // A manually executed BEGIN IMMEDIATE is not tracked by PDO8.3 commit()/inTransaction().
        $this->pdo->beginTransaction();
        try{$this->pdo->exec("UPDATE oe_receipt SET status=status WHERE id=''");}
        catch(Throwable $error){if($this->pdo->inTransaction())$this->pdo->rollBack();throw new RuntimeException('Receipt storage is busy/unavailable.',0,$error);}
    }
    private function json(array $value): string {return json_encode($value,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);}
    private function one(string $sql,array $params): ?array {$q=$this->pdo->prepare($sql);$q->execute($params);$row=$q->fetch(PDO::FETCH_ASSOC);return $row?:null;}
}
