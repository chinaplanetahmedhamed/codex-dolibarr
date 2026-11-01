<?php
declare(strict_types=1);
require_once __DIR__.'/OfficeExpenseDraftService.php';
require_once __DIR__.'/OfficeExpenseNativeConnection.php';

/** Operational review drafts through an injected native DB handle; no schema install or financial objects. */
final class OfficeExpenseNativeDraftStore implements OfficeExpenseDraftStore
{
    private $db;private $connection;
    public function __construct($db)
    {
        if (!defined('MAIN_DB_PREFIX')||!preg_match('/^[A-Za-z0-9_]+$/D',MAIN_DB_PREFIX)
            || !is_object($db)||!method_exists($db,'query')||!method_exists($db,'escape')||!isset($db->transaction_opened)) {
            throw new InvalidArgumentException('An existing native database handle is required.');
        }
        $this->db=$db;$this->connection=new OfficeExpenseNativeConnection($db);
    }

    public function save(int $entity,int $owner,string $key,array $draft): array
    {
        $this->scope($entity,$owner);
        if (!preg_match('/^[A-Za-z0-9_-]{8,80}$/D',$key)||($draft['entity']??null)!==$entity||($draft['owner_id']??null)!==$owner
            ||($draft['status']??null)!=='draft'||!array_key_exists('native_record',$draft)||$draft['native_record']!==null
            ||!is_string($draft['office']??null)||!preg_match('/^[a-z][a-z0-9_-]{0,39}$/D',$draft['office'])
            ||isset($draft['id'])||isset($draft['created_at'])) throw new InvalidArgumentException('Invalid operational source context.');
        if($this->db->transaction_opened!==0)throw new LogicException('Draft storage must own its outer transaction.');
        $hash=hash('sha256',$this->json($draft));
        $existing=$this->existing($entity,$owner,$key,$hash);if($existing!==null)return $existing;
        $document=$draft;$document['id']=bin2hex(random_bytes(16));$document['created_at']=gmdate('Y-m-d\TH:i:s\Z');
        $date=str_replace(array('T','Z'),array(' ',''),$document['created_at']);
        $this->connection->beginOwned();
        try{
            $this->query('INSERT INTO '.MAIN_DB_PREFIX.'cpo_oe_draft(entity,fk_user_creat,office,draft_ref,submission_key,payload_hash,document_json,status,date_creation) VALUES('
                .$entity.','.$owner.','.$this->q($draft['office']).','.$this->q($document['id']).','.$this->q($key).','.$this->q($hash).','.$this->q($this->json($document)).",'draft',".$this->q($date).')');
            $this->query('INSERT INTO '.MAIN_DB_PREFIX.'cpo_oe_draft_audit(entity,fk_user_creat,draft_ref,event,payload_hash,date_creation) VALUES('
                .$entity.','.$owner.','.$this->q($document['id']).",'draft_created',".$this->q($hash).','.$this->q($date).')');
            if($this->existing($entity,$owner,$key,$hash)!==$document)throw new RuntimeException('Operational source/audit was not persisted exactly.');
            $this->connection->commitOwned();
            return $document;
        }catch(Throwable $error){
            // Native rollback can clear its counter even when the SQL rollback fails.
            // Never read back uncommitted rows as successful recovery, or loop on that handle.
            $this->connection->rollbackOwnedOnFailure($error);
            // Only a confirmed rollback permits this connection to recover a competing committed intent.
            // Lost commit acknowledgement is reconciled by a fresh connection on the next scoped retry.
            $existing=$this->existing($entity,$owner,$key,$hash);if($existing!==null)return $existing;
            throw new RuntimeException('Operational draft persistence failed.',0,$error);
        }
    }

    public function listOwn(int $entity,int $owner): array
    {
        $this->scope($entity,$owner);$result=$this->query('SELECT * FROM '.MAIN_DB_PREFIX.'cpo_oe_draft WHERE entity='.$entity.' AND fk_user_creat='.$owner.' ORDER BY rowid');
        $rows=array();while($row=$this->db->fetch_object($result))$rows[]=$row;
        return array_map(function($row)use($entity,$owner){return $this->decode($row,$entity,$owner);},$rows);
    }

    public function findOwn(int $entity,int $owner,string $id): ?array
    {
        $this->scope($entity,$owner);if(!preg_match('/^[a-f0-9]{32}$/D',$id))return null;
        $row=$this->one('SELECT * FROM '.MAIN_DB_PREFIX.'cpo_oe_draft WHERE entity='.$entity.' AND fk_user_creat='.$owner.' AND draft_ref='.$this->q($id));
        return $row?$this->decode($row,$entity,$owner):null;
    }

    public function auditOwn(int $entity,int $owner,string $id): array
    {
        if($this->findOwn($entity,$owner,$id)===null)return array();
        $result=$this->query('SELECT event,date_creation FROM '.MAIN_DB_PREFIX.'cpo_oe_draft_audit WHERE entity='.$entity.' AND fk_user_creat='.$owner.' AND draft_ref='.$this->q($id).' ORDER BY rowid');
        $rows=array();while($row=$this->db->fetch_object($result))$rows[]=array('draft_id'=>$id,'event'=>$row->event,'created_at'=>$row->date_creation);return $rows;
    }
    public function usesConnection($db): bool {return $this->db===$db;}

    private function existing(int $entity,int $owner,string $key,string $hash): ?array
    {
        $row=$this->one('SELECT * FROM '.MAIN_DB_PREFIX.'cpo_oe_draft WHERE entity='.$entity.' AND fk_user_creat='.$owner.' AND submission_key='.$this->q($key));
        if(!$row)return null;
        $document=$this->decode($row,$entity,$owner);
        if(!hash_equals($row->payload_hash,$hash))throw new DomainException('Submission key already has a different intent.');return $document;
    }

    private function decode($row,int $entity,int $owner): array
    {
        try{$document=json_decode($row->document_json,true,512,JSON_THROW_ON_ERROR);}catch(Throwable $e){throw new RuntimeException('Operational source integrity failed.',0,$e);}
        if(!is_array($document))throw new RuntimeException('Operational source integrity failed.');
        $intent=$document;unset($intent['id'],$intent['created_at']);
        if(!is_array($document)||($document['entity']??null)!==$entity||($document['owner_id']??null)!==$owner
            ||(int)$row->entity!==$entity||(int)$row->fk_user_creat!==$owner||$row->status!=='draft'||($document['status']??null)!=='draft'
            ||!array_key_exists('native_record',$document)||$document['native_record']!==null||($document['office']??null)!==$row->office
            ||!preg_match('/^[a-f0-9]{32}$/D',$row->draft_ref)||($document['id']??null)!==$row->draft_ref
            ||!is_string($document['created_at']??null)||!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/D',$document['created_at'])
            ||str_replace(array('T','Z'),array(' ',''),$document['created_at'])!==$row->date_creation
            ||!hash_equals($row->payload_hash,hash('sha256',$this->json($intent))))throw new RuntimeException('Operational source integrity failed.');
        $audit=$this->one('SELECT fk_user_creat,payload_hash,date_creation FROM '.MAIN_DB_PREFIX.'cpo_oe_draft_audit WHERE entity='.$entity.' AND draft_ref='.$this->q($row->draft_ref)." AND event='draft_created'");
        if(!$audit||(int)$audit->fk_user_creat!==$owner||!hash_equals($audit->payload_hash,$row->payload_hash)||$audit->date_creation!==$row->date_creation)throw new RuntimeException('Operational creation audit is missing or inconsistent.');
        return $document;
    }
    private function scope(int $entity,int $owner): void {
        $this->connection->assertUsable();
        if($entity<=0||$owner<=0)throw new InvalidArgumentException('Invalid native source scope.');
    }
    private function json(array $value): string {return json_encode($value,JSON_THROW_ON_ERROR);}
    private function q(string $value): string {return "'".$this->db->escape($value)."'";}
    private function query(string $sql){try{$r=$this->db->query($sql);if(!$r)throw new RuntimeException('Native query rejected.');return $r;}catch(Throwable $e){throw new RuntimeException('Operational storage unavailable.',0,$e);}}
    private function one(string $sql){return $this->db->fetch_object($this->query($sql));}
}
