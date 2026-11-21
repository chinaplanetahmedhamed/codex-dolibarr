<?php
declare(strict_types=1);
require_once __DIR__.'/OfficeExpenseNativeConnection.php';
require_once __DIR__.'/OfficeExpenseMoney.php';

/** Versioned operational documents and audit, using the existing native connection. */
final class OfficeExpenseWorkspaceStore
{
    private OfficeExpenseNativeConnection $c;private $db;
    public function __construct($db){if(!defined('MAIN_DB_PREFIX')||!preg_match('/^[A-Za-z0-9_]{1,40}$/D',MAIN_DB_PREFIX))throw new InvalidArgumentException('Invalid native prefix.');$this->db=$db;$this->c=new OfficeExpenseNativeConnection($db);}
    public function find(int $entity,string $ref): ?array
    {
        self::ref($ref);if($entity<1)throw new InvalidArgumentException('Invalid entity.');
        $row=$this->c->one('SELECT entity,office,kind,record_ref,fk_user_creat,fk_subject,object_key,request_key,request_hash,content_json,status,revision,date_creation,date_modification FROM '.MAIN_DB_PREFIX.'cpo_oe_workspace WHERE entity='.$entity.' AND record_ref='.$this->c->quote($ref));
        return $row===null?null:$this->decode($row);
    }
    public function list(int $entity,string $office,string $kind,?int $owner=null,?string $from=null,?string $to=null): array
    {
        self::scope($entity,$office,$kind);if($owner!==null&&$owner<1)throw new InvalidArgumentException('Invalid owner.');
        $q=$this->c->quote(...);$where='entity='.$entity.' AND office='.$q($office).' AND kind='.$q($kind);
        if ($from !== null || $to !== null) {
            OfficeExpenseMoney::date($from);
            OfficeExpenseMoney::date($to);
            if ($from > $to) throw new InvalidArgumentException('Invalid date range.');
            // Compare dates instead of text from the JSON and connection collations.
            $date = "DATE(JSON_UNQUOTE(JSON_EXTRACT(content_json,'$.date')))";
            $where .= ' AND '.$date.'>=DATE('.$q($from).') AND '.$date.'<=DATE('.$q($to).')';
        }
        if($owner!==null)$where.=' AND (fk_user_creat='.$owner.' OR fk_subject='.$owner.')';
        $rows=$this->c->rows('SELECT entity,office,kind,record_ref,fk_user_creat,fk_subject,object_key,request_key,request_hash,content_json,status,revision,date_creation,date_modification FROM '.MAIN_DB_PREFIX.'cpo_oe_workspace WHERE '.$where.' ORDER BY date_creation DESC,record_ref LIMIT 10001');
        if(count($rows)>10000)throw new DomainException('Document list exceeds the supported workspace size; narrow the reporting period or add pagination before export.');
        return array_map($this->decode(...),$rows);
    }
    public function create(int $entity,int $actor,string $office,string $kind,string $key,string $ref,string $objectKey,array $payload,callable $authorize): array
    {
        self::scope($entity,$office,$kind);self::ref($ref);if($actor<1||!preg_match('/^[A-Za-z0-9_-]{8,80}$/D',$key)||!preg_match('/^[A-Za-z0-9:_-]{1,100}$/D',$objectKey))throw new InvalidArgumentException('Invalid request key.');
        $c=$this->c;$q=$c->quote(...);$json=self::json($payload);$hash=hash('sha256',self::json([$office,$kind,$payload]));$c->beginOwned();
        try{
            $authorize();$old=$c->one('SELECT record_ref,request_hash FROM '.MAIN_DB_PREFIX.'cpo_oe_workspace WHERE entity='.$entity.' AND fk_user_creat='.$actor.' AND request_key='.$q($key));
            if($old){if(!hash_equals($old['request_hash'],$hash))throw new DomainException('Submission key has different content.');$saved=$this->find($entity,$old['record_ref']);$c->commitOwned();return $saved;}
            $subject=(int)($payload['user_id']??0);$status=in_array($kind,['settings','finance_settings','category','employee'],true)?'active':'draft';$now=gmdate('Y-m-d H:i:s');
            $c->query('INSERT INTO '.MAIN_DB_PREFIX.'cpo_oe_workspace(entity,office,kind,record_ref,fk_user_creat,fk_subject,object_key,request_key,request_hash,content_json,status,revision,date_creation,date_modification) VALUES('.$entity.','.$q($office).','.$q($kind).','.$q($ref).','.$actor.','.$subject.','.$q($objectKey).','.$q($key).','.$q($hash).','.$q($json).','.$q($status).',1,'.$q($now).','.$q($now).')');
            $saved=$this->find($entity,$ref);if(!$saved||$saved['content']!==$payload||$saved['revision']!==1)throw new RuntimeException('Created document did not verify.');
            $this->audit($actor,'create',null,$saved);$c->commitOwned();return $saved;
        }catch(Throwable$e){$c->rollbackOwnedOnFailure($e);throw $e;}
    }
    public function change(int $entity,int $actor,string $ref,int $expected,string $action,?array $payload,callable $authorize): array
    {
        if($actor<1||$expected<1||!in_array($action,['update','archive','restore'],true))throw new InvalidArgumentException('Invalid document change.');$c=$this->c;$c->beginOwned();
        try{
            $old=$this->find($entity,$ref);if(!$old)throw new DomainException('Document unavailable.');$authorize($old);
            if($old['revision']!==$expected)throw new DomainException('Document changed; refresh before retrying.');
            if(!in_array($old['status'],['draft','active','archived','rejected'],true))throw new DomainException('Financially approved documents are immutable.');
            if($action==='update'&&($payload===null||$old['status']==='archived'))throw new DomainException('Archived document must be restored before editing.');
            if($action==='archive'&&$old['status']==='archived')throw new DomainException('Document already archived.');
            if($action==='restore'&&$old['status']!=='archived')throw new DomainException('Only archived documents may be restored.');
            $content=$action==='update'?$payload:$old['content'];
            // Rejected edits retain the complete operation history, while invalidating prior approval.
            if($action==='update'&&isset($old['content']['_finance']))$content['_finance']=['operations'=>$old['content']['_finance']['operations']??[]];
            if((!in_array($old['kind'],['expense','advance','advance_return','advance_settlement'],true)&&(int)($content['user_id']??0)!==$old['subject'])||($old['kind']==='payroll'&&$content['month']!==$old['content']['month']))throw new DomainException('Document subject and payroll period cannot change.');
            $status=$action==='archive'?'archived':($action==='restore'?(in_array($old['kind'],['settings','finance_settings','category','employee'],true)?'active':'draft'):$old['status']);
            $q=$c->quote(...);$c->query('UPDATE '.MAIN_DB_PREFIX.'cpo_oe_workspace SET content_json='.$q(self::json($content)).',status='.$q($status).',revision='.($expected+1).',date_modification='.$q(gmdate('Y-m-d H:i:s')).' WHERE entity='.$entity.' AND record_ref='.$q($ref).' AND revision='.$expected);
            $saved=$this->find($entity,$ref);
            if(!$saved||$saved['revision']!==$expected+1||$saved['status']!==$status||$saved['content']!==$content)throw new RuntimeException('Document update did not verify.');
            $this->audit($actor,$action,$old,$saved);$c->commitOwned();return $saved;
        }catch(Throwable$e){$c->rollbackOwnedOnFailure($e);throw $e;}
    }
    public function evidenceFor(int $entity,string $office,string $parent): array
    {
        self::scope($entity,$office,'evidence');self::ref($parent);$q=$this->c->quote(...);
        $rows=$this->c->rows('SELECT entity,office,kind,record_ref,fk_user_creat,fk_subject,object_key,request_key,request_hash,content_json,status,revision,date_creation,date_modification FROM '.MAIN_DB_PREFIX.'cpo_oe_workspace WHERE entity='.$entity.' AND office='.$q($office)." AND kind='evidence' AND object_key LIKE ".$q('evidence:'.$parent.':%').' ORDER BY date_creation,record_ref LIMIT 51');
        if(count($rows)>50)throw new DomainException('Evidence volume requires review.');return array_map($this->decode(...),$rows);
    }
    public function addEvidence(int $entity,int $actor,string $parent,string $key,array $metadata,callable $authorize,callable $persist,callable $cleanup): array
    {
        self::ref($parent);if(!preg_match('/^[A-Za-z0-9_-]{8,80}$/D',$key))throw new InvalidArgumentException('Invalid evidence retry key.');
        $c=$this->c;$hash=hash('sha256',self::json(['workspace_evidence',$parent,$metadata]));$created=null;$commitAttempted=false;$c->beginOwned();
        try{
            $source=$this->locked($entity,$parent);if(!$source)throw new DomainException('Evidence parent unavailable.');$authorize($source);$q=$c->quote(...);
            $retry=$c->one('SELECT record_ref,request_hash FROM '.MAIN_DB_PREFIX.'cpo_oe_workspace WHERE entity='.$entity.' AND fk_user_creat='.$actor.' AND request_key='.$q($key));
            if($retry){if(!hash_equals($retry['request_hash'],$hash))throw new DomainException('Evidence retry content differs.');$saved=$this->find($entity,$retry['record_ref']);if(!$saved||$saved['kind']!=='evidence'||$saved['content']['parent_ref']!==$parent)throw new RuntimeException('Evidence retry source differs.');$commitAttempted=true;$c->commitOwned();return $saved;}
            $ref=bin2hex(random_bytes(16));$metadata['parent_ref']=$parent;$metadata['parent_kind']=$source['kind'];$metadata['parent_owner']=$source['owner'];$metadata['source_revision']=$source['revision'];
            $created=$ref;$persist($ref,$source);$now=gmdate('Y-m-d H:i:s');
            $c->query('INSERT INTO '.MAIN_DB_PREFIX.'cpo_oe_workspace(entity,office,kind,record_ref,fk_user_creat,fk_subject,object_key,request_key,request_hash,content_json,status,revision,date_creation,date_modification) VALUES('.$entity.','.$q($source['office']).",'evidence',".$q($ref).','.$actor.','.$source['owner'].','.$q('evidence:'.$parent.':'.$ref).','.$q($key).','.$q($hash).','.$q(self::json($metadata)).",'active',1,".$q($now).','.$q($now).')');
            $saved=$this->find($entity,$ref);if(!$saved||$saved['content']!==$metadata)throw new RuntimeException('Evidence metadata readback differs.');$this->audit($actor,'attach',null,$saved);$authorize($source);$commitAttempted=true;$c->commitOwned();return $saved;
        }catch(Throwable$error){$c->rollbackOwnedOnFailure($error);if(!$commitAttempted&&$created!==null)$cleanup($created);throw $error;}
    }
    public function evidenceRead(int $entity,int $actor,string $ref,callable $read): array
    {
        $c=$this->c;$c->beginOwned();try{
            $old=$this->locked($entity,$ref);if(!$old||$old['kind']!=='evidence')throw new DomainException('Evidence unavailable.');$download=$read($old);$q=$c->quote(...);
            $c->query('UPDATE '.MAIN_DB_PREFIX.'cpo_oe_workspace SET revision='.($old['revision']+1).',date_modification='.$q(gmdate('Y-m-d H:i:s')).' WHERE entity='.$entity.' AND record_ref='.$q($ref).' AND revision='.$old['revision']);
            $saved=$this->find($entity,$ref);if(!$saved||$saved['revision']!==$old['revision']+1)throw new RuntimeException('Evidence access audit revision differs.');$this->audit($actor,'download',$old,$saved);$c->commitOwned();return $download;
        }catch(Throwable$error){$c->rollbackOwnedOnFailure($error);throw $error;}
    }
    /** Financial mutations share the document transaction and immutable revision audit.
     * The callback may call native APIs; it must not commit the outer transaction.
     */
    public function financial(int $entity,int $actor,string $ref,int $expected,string $action,string $key,array $intent,callable $authorize,callable $apply): array
    {
        self::ref($ref);
        if($actor<1||$expected<1||!in_array($action,['submit','approve','reject','post','reverse'],true)||!preg_match('/^[A-Za-z0-9_-]{8,80}$/D',$key))throw new InvalidArgumentException('Invalid financial operation.');
        $c=$this->c;$hash=hash('sha256',self::json([$expected,$action,$intent]));$c->beginOwned();
        try{
            $old=$this->locked($entity,$ref);if(!$old)throw new DomainException('Document unavailable.');$authorize($old);
            // A durable acknowledgement can be recovered without repeating native writes.
            foreach($old['content']['_finance']['operations']??[]as$operation)if($operation['key']===$key){
                if($operation['actor']!==$actor||!hash_equals($operation['hash'],$hash))throw new DomainException('Financial retry intent differs.');
                $c->commitOwned();return $old;
            }
            if($old['revision']!==$expected)throw new DomainException('Document changed; refresh before retrying.');
            $next=['submit'=>[['draft','rejected'],'submitted'],'approve'=>[['submitted'],'approved'],'reject'=>[['submitted','approved'],'rejected'],'post'=>[['approved'],'posted'],'reverse'=>[['posted'],'reversed']][$action];
            if(!in_array($old['status'],$next[0],true))throw new DomainException('Financial transition unavailable.');
            $content=$apply($old);if(!is_array($content)||!isset($content['_finance'])||!is_array($content['_finance']))throw new RuntimeException('Financial evidence missing.');
            // Domain fields are immutable during a financial transition.
            $before=$old['content'];unset($before['_finance']);$after=$content;unset($after['_finance']);if($before!==$after)throw new RuntimeException('Financial transition changed its source.');
            $subject=$old['subject'];if($action==='submit'&&in_array($old['kind'],['expense','advance','advance_return','advance_settlement'],true)&&isset($content['_finance']['intent']['staff_id'])){$assigned=$content['_finance']['intent']['staff_id'];if(!is_int($assigned)||$assigned<1||($subject!==0&&$subject!==$assigned))throw new DomainException('A submitted financial staff subject cannot be reassigned.');$subject=$assigned;}
            $operations=$old['content']['_finance']['operations']??[];
            $operations[]=['key'=>$key,'hash'=>$hash,'actor'=>$actor,'action'=>$action,'at'=>gmdate('Y-m-d H:i:s')];$content['_finance']['operations']=$operations;
            $q=$c->quote(...);$c->query('UPDATE '.MAIN_DB_PREFIX.'cpo_oe_workspace SET fk_subject='.$subject.',content_json='.$q(self::json($content)).',status='.$q($next[1]).',revision='.($expected+1).',date_modification='.$q(gmdate('Y-m-d H:i:s')).' WHERE entity='.$entity.' AND record_ref='.$q($ref).' AND revision='.$expected);
            $saved=$this->find($entity,$ref);if(!$saved||$saved['revision']!==$expected+1||$saved['status']!==$next[1]||$saved['subject']!==$subject||$saved['content']!==$content)throw new RuntimeException('Financial state readback differs.');
            $this->audit($actor,$action,$old,$saved);$c->commitOwned();return $saved;
        }catch(Throwable $error){$c->rollbackOwnedOnFailure($error);throw $error;}
    }
    /** Locks are held by the owning outer transaction until source/native/audit commit. */
    public function locked(int $entity,string $ref): ?array
    {
        self::ref($ref);if($entity<1)throw new InvalidArgumentException('Invalid entity.');
        if($this->db->transaction_opened!==1)throw new LogicException('Financial lock requires the owned transaction.');
        if(in_array($this->db->type??'', ['mysqli','mysql','pgsql'],true))$lock=' FOR UPDATE';
        elseif(defined('M119_SYNTHETIC_NATIVE')&&M119_SYNTHETIC_NATIVE&&$this->db instanceof M119NativeSqlite)$lock='';
        else throw new RuntimeException('Verified native row locking is required.');
        $row=$this->c->one('SELECT entity,office,kind,record_ref,fk_user_creat,fk_subject,object_key,request_key,request_hash,content_json,status,revision,date_creation,date_modification FROM '.MAIN_DB_PREFIX.'cpo_oe_workspace WHERE entity='.$entity.' AND record_ref='.$this->c->quote($ref).$lock);
        return $row===null?null:$this->decode($row);
    }
    private function audit(int $actor,string $action,?array $before,array $after): void
    {
        $c=$this->c;$q=$c->quote(...);$old=self::json($before);$next=self::json($after);
        $c->query('INSERT INTO '.MAIN_DB_PREFIX.'cpo_oe_workspace_audit(entity,office,record_ref,revision,fk_actor,action,before_json,after_json,date_creation) VALUES('.$after['entity'].','.$q($after['office']).','.$q($after['id']).','.$after['revision'].','.$actor.','.$q($action).','.$q($old).','.$q($next).','.$q(gmdate('Y-m-d H:i:s')).')');
        $row=$c->one('SELECT fk_actor,action,before_json,after_json FROM '.MAIN_DB_PREFIX.'cpo_oe_workspace_audit WHERE entity='.$after['entity'].' AND record_ref='.$q($after['id']).' AND revision='.$after['revision']);
        if(!$row||(int)$row['fk_actor']!==$actor||$row['action']!==$action||$row['before_json']!==$old||$row['after_json']!==$next)throw new RuntimeException('Document audit did not verify.');
    }
    private function decode(array $r): array
    {
        $content=json_decode($r['content_json'],true,32,JSON_THROW_ON_ERROR);if(!is_array($content))throw new RuntimeException('Document content corrupt.');
        return ['entity'=>(int)$r['entity'],'office'=>$r['office'],'kind'=>$r['kind'],'id'=>$r['record_ref'],'owner'=>(int)$r['fk_user_creat'],'subject'=>(int)$r['fk_subject'],'object_key'=>$r['object_key'],'request_key'=>$r['request_key'],'request_hash'=>$r['request_hash'],'content'=>$content,'status'=>$r['status'],'revision'=>(int)$r['revision'],'created_at'=>$r['date_creation'],'updated_at'=>$r['date_modification']];
    }
    private static function json($v): string{return json_encode($v,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);}
    private static function ref(string $ref): void{if(!preg_match('/^[a-f0-9]{32}$/D',$ref))throw new InvalidArgumentException('Invalid document reference.');}
    private static function scope(int $entity,string $office,string $kind): void{if($entity<1||!in_array($office,['egypt','china'],true)||!preg_match('/^[a-z_]{3,24}$/D',$kind))throw new InvalidArgumentException('Invalid document scope.');}
}
