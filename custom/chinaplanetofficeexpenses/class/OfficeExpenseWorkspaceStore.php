<?php
declare(strict_types=1);
require_once __DIR__.'/OfficeExpenseNativeConnection.php';
require_once __DIR__.'/OfficeExpenseMoney.php';

/** Versioned operational documents and audit, using the existing native connection. */
final class OfficeExpenseWorkspaceStore
{
    private OfficeExpenseNativeConnection $c;
    public function __construct($db){if(!defined('MAIN_DB_PREFIX')||!preg_match('/^[A-Za-z0-9_]{1,40}$/D',MAIN_DB_PREFIX))throw new InvalidArgumentException('Invalid native prefix.');$this->c=new OfficeExpenseNativeConnection($db);}
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
        if($from!==null||$to!==null){OfficeExpenseMoney::date($from);OfficeExpenseMoney::date($to);if($from>$to)throw new InvalidArgumentException('Invalid date range.');$date="JSON_UNQUOTE(JSON_EXTRACT(content_json,'$.date'))";$where.=' AND '.$date.'>='.$q($from).' AND '.$date.'<='.$q($to);}
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
            $subject=(int)($payload['user_id']??0);$status=in_array($kind,['settings','category','employee'],true)?'active':'draft';$now=gmdate('Y-m-d H:i:s');
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
            if(in_array($old['status'],['approved','posted'],true))throw new DomainException('Financially approved documents are immutable.');
            if($action==='update'&&($payload===null||$old['status']==='archived'))throw new DomainException('Archived document must be restored before editing.');
            if($action==='archive'&&$old['status']==='archived')throw new DomainException('Document already archived.');
            if($action==='restore'&&$old['status']!=='archived')throw new DomainException('Only archived documents may be restored.');
            $content=$action==='update'?$payload:$old['content'];
            if((int)($content['user_id']??0)!==$old['subject']||($old['kind']==='payroll'&&$content['month']!==$old['content']['month']))throw new DomainException('Document subject and payroll period cannot change.');
            $status=$action==='archive'?'archived':($action==='restore'?(in_array($old['kind'],['settings','category','employee'],true)?'active':'draft'):$old['status']);
            $q=$c->quote(...);$c->query('UPDATE '.MAIN_DB_PREFIX.'cpo_oe_workspace SET content_json='.$q(self::json($content)).',status='.$q($status).',revision='.($expected+1).',date_modification='.$q(gmdate('Y-m-d H:i:s')).' WHERE entity='.$entity.' AND record_ref='.$q($ref).' AND revision='.$expected);
            $saved=$this->find($entity,$ref);
            if(!$saved||$saved['revision']!==$expected+1||$saved['status']!==$status||$saved['content']!==$content)throw new RuntimeException('Document update did not verify.');
            $this->audit($actor,$action,$old,$saved);$c->commitOwned();return $saved;
        }catch(Throwable$e){$c->rollbackOwnedOnFailure($e);throw $e;}
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
