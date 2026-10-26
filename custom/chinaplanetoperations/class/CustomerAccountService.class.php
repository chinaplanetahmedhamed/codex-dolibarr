<?php
require_once __DIR__.'/CustomerAccountAmount.class.php';
require_once __DIR__.'/CustomerAccountPolicy.class.php';
/** Manual operational subledger. Never writes native payments, banks or invoices. */
class CustomerAccountService
{
    private $db; private $policy; private $entity; private $currencies;
    public function __construct($db, CustomerAccountPolicy $policy, $entity, array $currencies=['USD','CNY','EGP'])
    { $this->db=$db; $this->policy=$policy; $this->entity=(int)$entity; $this->currencies=$currencies; if(!extension_loaded('bcmath'))throw new RuntimeException('BCMath is required.'); }
    private function q($s) { return "'".$this->db->escape((string)$s)."'"; }
    private function table($s) { return MAIN_DB_PREFIX.'cpo_account_'.$s; }
    private function query($s) { $r=$this->db->query($s); if(!$r)throw new RuntimeException('Account storage failed. No changes were saved.'); return $r; }
    private function rows($s) { $r=$this->query($s);$a=[];while($v=$this->db->fetch_object($r))$a[]=(array)$v;return $a; }
    private function json($s) { return json_encode($s,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR); }
    private function text($v,$length,$required=true)
    { if(!is_string($v)||!mb_check_encoding($v,'UTF-8')||mb_strlen(trim($v))>$length||($required&&trim($v)==='')||preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f]/u',$v))throw new InvalidArgumentException('Invalid text field.');return trim($v); }
    private function date($v)
    { if(!is_string($v)||!preg_match('/^([0-9]{4})-([0-9]{2})-([0-9]{2})$/D',$v,$m)||!checkdate((int)$m[2],(int)$m[3],(int)$m[1]))throw new InvalidArgumentException('Use a valid YYYY-MM-DD date.');return $v; }
    public function permissions() { $r=[];foreach(['read','edit','post','reallocate','export'] as $a)$r[$a]=$this->policy->allowed($a);return $r; }
    public function columns($customer)
    { $this->policy->customer($customer);return $this->rows('SELECT rowid,label FROM '.$this->table('column').' WHERE entity='.$this->entity.' AND fk_soc='.(int)$customer.' ORDER BY rowid'); }
    private function payload($customer,array $d)
    {
        $currency=$d['currency']??'';if(!in_array($currency,$this->currencies,true))throw new InvalidArgumentException('Choose a configured currency.');
        $amount=CustomerAccountAmount::money(CustomerAccountAmount::minor($d['amount']??''));$rate=CustomerAccountAmount::rate($d['rate']??'');
        if($currency==='USD'&&bccomp($rate,'1',12)!==0)throw new InvalidArgumentException('USD uses a rate of 1.');
        if(!in_array($d['direction']??'', ['received','spent'],true))throw new InvalidArgumentException('Choose received or spent.');
        $custom=$d['custom']??[];if(!is_array($custom))throw new InvalidArgumentException('Invalid custom fields.');
        $ids=array_map('strval',array_column($this->columns($customer),'rowid'));
        foreach($custom as $id=>&$value){if(!in_array((string)$id,$ids,true))throw new InvalidArgumentException('Unknown custom column.');$value=$this->text($value,500,false);}unset($value);
        return ['date'=>$this->date($d['date']??''),'direction'=>$d['direction'],'amount'=>$amount,'currency'=>$currency,'rate'=>$rate,'rate_date'=>$this->date($d['rate_date']??''),'rate_source'=>$this->text($d['rate_source']??'',120),'description'=>$this->text($d['description']??'',255),'reference'=>$this->text($d['reference']??'',120,false),'custom'=>$custom,'base_minor'=>CustomerAccountAmount::base($amount,$rate),'source'=>'manual'];
    }
    private function lines($entry)
    { return $this->rows('SELECT fk_project project,fk_task task,original_minor,base_minor,rounding_minor FROM '.$this->table('allocation').' WHERE entity='.$this->entity.' AND fk_entry='.(int)$entry['rowid'].' AND batch='.(int)$entry['allocation_version'].' ORDER BY position'); }
    private function allocations($customer,$payload,$input)
    {
        if(!is_array($input))throw new InvalidArgumentException('Allocation lines required.');
        $out=CustomerAccountAmount::allocate($payload['amount'],$payload['rate'],$input);
        foreach($out as $l)$this->policy->scope($customer,$l['project'],$l['task']);return $out;
    }
    private function writeLines($id,$batch,array $lines)
    {
        foreach($lines as $i=>$l)$this->query('INSERT INTO '.$this->table('allocation').'(entity,fk_entry,batch,position,fk_project,fk_task,original_minor,base_minor,rounding_minor) VALUES('.$this->entity.','.(int)$id.','.(int)$batch.','.$i.','.((int)$l['project']?:'NULL').','.((int)$l['task']?:'NULL').','.$this->q($l['original_minor']).','.$this->q($l['base_minor']).','.$this->q($l['rounding_minor']).')');
    }
    private function insertEntry($customer,$payload,$status,$reversal=0)
    {
        $this->query('INSERT INTO '.$this->table('entry').'(entity,fk_soc,payload,status,version,allocation_version,reversal_of,fk_user_creat,date_creation) VALUES('.$this->entity.','.(int)$customer.','.$this->q($this->json($payload)).','.$this->q($status).',1,1,'.($reversal?(int)$reversal:'NULL').','.$this->policy->actorId().','.$this->q(date('Y-m-d H:i:s')).')');return (int)$this->db->last_insert_id($this->table('entry'));
    }
    private function audit($customer,$id,$action,$reason,$detail)
    { $this->query('INSERT INTO '.$this->table('audit').'(entity,fk_soc,fk_entry,fk_user,action,reason,detail_json,date_creation) VALUES('.$this->entity.','.(int)$customer.','.($id?(int)$id:'NULL').','.$this->policy->actorId().','.$this->q($action).','.$this->q($reason).','.$this->q($this->json($detail)).','.$this->q(date('Y-m-d H:i:s')).')'); }
    /** Own one transaction, lock customer then entries. Commands replay only identical payloads. */
    public function mutate($action,$customer,array $d,$request)
    {
        $right=['save'=>'edit','column'=>'edit','post'=>'post','reverse'=>'post','reallocate'=>'reallocate'][$action]??null;
        if(!$right)throw new InvalidArgumentException('Unknown account action.');$this->policy->requireRight($right);$this->policy->customer($customer);
        if(!is_string($request)||!preg_match('/^[a-zA-Z0-9-]{16,64}$/D',$request))throw new InvalidArgumentException('A request identity is required.');
        if(!empty($this->db->transaction_opened))throw new RuntimeException('Account commands require their own transaction.');
        $hash=hash('sha256',$this->json([$action,(int)$customer,$d]));if(!$this->db->begin())throw new RuntimeException('Account transaction unavailable. No changes were saved.');
        try {
            $this->rows('SELECT rowid FROM '.MAIN_DB_PREFIX.'societe WHERE entity='.$this->entity.' AND rowid='.(int)$customer.' FOR UPDATE');$this->policy->customer($customer);
            $prior=$this->rows('SELECT request_hash,result_json FROM '.$this->table('command').' WHERE entity='.$this->entity.' AND fk_user='.$this->policy->actorId().' AND request_key='.$this->q($request));
            if($prior){if(!hash_equals($prior[0]['request_hash'],$hash))throw new RuntimeException('Request identity was used for different data.',409);$result=json_decode($prior[0]['result_json'],true,512,JSON_THROW_ON_ERROR);$this->db->commit();return $result;}
            $id=(int)($d['id']??0);$old=null;$oldLines=[];$reason='';
            if($id){$r=$this->rows('SELECT * FROM '.$this->table('entry').' WHERE entity='.$this->entity.' AND fk_soc='.(int)$customer.' AND rowid='.$id.' FOR UPDATE');if(!$r)throw new RuntimeException('Record unavailable.',403);$old=$r[0];if((int)($d['version']??0)!==(int)$old['version'])throw new RuntimeException('Record changed. Reload before retrying.',409);$oldLines=$this->lines($old);foreach($oldLines as $l)$this->policy->scope($customer,$l['project'],$l['task'],true);}
            if($action==='column') {
                $label=$this->text($d['label']??'',60);$cols=$this->columns($customer);if(count($cols)>=12)throw new InvalidArgumentException('Maximum 12 custom text columns.');foreach($cols as $c)if(mb_strtolower($c['label'])===mb_strtolower($label))throw new InvalidArgumentException('Column name already exists.');
                $this->query('INSERT INTO '.$this->table('column').'(entity,fk_soc,label) VALUES('.$this->entity.','.(int)$customer.','.$this->q($label).')');$result=['column'=>(int)$this->db->last_insert_id($this->table('column'))];
            } elseif($action==='save') {
                if($old&&$old['status']!=='draft')throw new RuntimeException('Posted records are immutable. Reverse and create a correction.',409);
                $payload=$this->payload($customer,$d);$lines=$this->allocations($customer,$payload,$d['allocations']??null);
                if($old){$batch=(int)$old['allocation_version']+1;$this->query('UPDATE '.$this->table('entry').' SET payload='.$this->q($this->json($payload)).',version=version+1,allocation_version='.$batch.' WHERE rowid='.$id.' AND entity='.$this->entity);}
                else{$id=$this->insertEntry($customer,$payload,'draft');$batch=1;}
                $this->writeLines($id,$batch,$lines);$result=['id'=>$id,'version'=>$old?(int)$old['version']+1:1];
            } else {
                if(!$old)throw new InvalidArgumentException('Choose a record.');$payload=json_decode($old['payload'],true,512,JSON_THROW_ON_ERROR);
                $reversed=$this->rows('SELECT rowid FROM '.$this->table('entry').' WHERE entity='.$this->entity.' AND reversal_of='.$id);
                if($reversed||$old['reversal_of'])throw new RuntimeException('A reversed record cannot be changed.',409);
                if($action==='post') {
                    if($old['status']!=='draft')throw new RuntimeException('Record already posted.',409);
                    foreach($oldLines as $l)$this->policy->scope($customer,$l['project'],$l['task']);
                    $this->query('UPDATE '.$this->table('entry')." SET status='posted',version=version+1 WHERE entity=".$this->entity.' AND rowid='.$id);$result=['id'=>$id,'version'=>(int)$old['version']+1];
                } else {
                    if($old['status']!=='posted')throw new RuntimeException('Post the record first.',409);$reason=$this->text($d['reason']??'',500);
                    if($action==='reverse') {
                        $payload['direction']=$payload['direction']==='received'?'spent':'received';$payload['description']='Reversal: '.$payload['description'];$payload['date']=$this->date($d['date']??date('Y-m-d'));
                        $newId=$this->insertEntry($customer,$payload,'posted',$id);$this->writeLines($newId,1,$oldLines);
                        $this->query('UPDATE '.$this->table('entry').' SET version=version+1 WHERE entity='.$this->entity.' AND rowid='.$id);$result=['id'=>$newId,'version'=>1,'reversal_of'=>$id];
                    } else {
                        $lines=$this->allocations($customer,$payload,$d['allocations']??null);$batch=(int)$old['allocation_version']+1;
                        $this->writeLines($id,$batch,$lines);$this->query('UPDATE '.$this->table('entry').' SET allocation_version='.$batch.',version=version+1 WHERE entity='.$this->entity.' AND rowid='.$id);$result=['id'=>$id,'version'=>(int)$old['version']+1];
                    }
                }
            }
            $this->audit($customer,$id,$action,$reason,['before'=>$old,'before_allocations'=>$oldLines,'input'=>$d,'result'=>$result]);
            $this->query('INSERT INTO '.$this->table('command').'(entity,fk_user,request_key,request_hash,result_json) VALUES('.$this->entity.','.$this->policy->actorId().','.$this->q($request).','.$this->q($hash).','.$this->q($this->json($result)).')');
            if(!$this->db->commit())throw new RuntimeException('Commit failed.');return $result;
        } catch(Throwable $e){$this->db->rollback();throw $e;}
    }
    public function statement($customer,$project=0,$task=0)
    {
        $name=$this->policy->customer($customer);$this->policy->scope($customer,$project,$task,true);
        $entries=$this->rows('SELECT * FROM '.$this->table('entry').' WHERE entity='.$this->entity.' AND fk_soc='.(int)$customer.' ORDER BY rowid');
        $out=['customer'=>$name,'project'=>(int)$project,'task'=>(int)$task,'base_currency'=>'USD','balance_minor'=>'0','received_minor'=>'0','spent_minor'=>'0','unallocated_minor'=>'0','original_balances'=>[],'rows'=>[],'drafts'=>[],'columns'=>$this->columns($customer),'permissions'=>$this->permissions(),'currencies'=>$this->currencies,'obligations'=>'Not included: use native invoices and commitments separately.'];
        $reversed=[];foreach($entries as $e)if($e['reversal_of'])$reversed[(int)$e['reversal_of']]=true;
        foreach($entries as $e) {
            $lines=$this->lines($e);$selected=[];
            foreach($lines as $l) {
                if($project&&(int)$l['project']!==(int)$project)continue;if($task&&(int)$l['task']!==(int)$task)continue;
                $this->policy->scope($customer,(int)$l['project'],(int)$l['task'],true);$selected[]=$l;
            }
            if(!$selected)continue;$p=json_decode($e['payload'],true,512,JSON_THROW_ON_ERROR);$base='0';$original='0';$rounding='0';$unallocated='0';
            foreach($selected as $l){$base=bcadd($base,$l['base_minor'],0);$original=bcadd($original,$l['original_minor'],0);$rounding=bcadd($rounding,$l['rounding_minor'],0);if(!$l['project'])$unallocated=bcadd($unallocated,$l['base_minor'],0);}
            $partial=count($selected)!==count($lines);if($partial){$p['amount']=CustomerAccountAmount::money($original);$p['base_minor']=$base;}
            $row=['id'=>(int)$e['rowid'],'version'=>(int)$e['version'],'status'=>$e['status'],'reversal_of'=>(int)$e['reversal_of'],'reversed'=>isset($reversed[(int)$e['rowid']]),'payload'=>$p,'allocations'=>$selected,'partial'=>count($selected)!==count($lines),'scoped_original_minor'=>$original,'scoped_base_minor'=>$base,'rounding_minor'=>$rounding];
            if($e['status']==='draft'){$out['drafts'][]=$row;continue;}
            $sgn=$p['direction']==='received'?'1':'-1';$out['balance_minor']=bcadd($out['balance_minor'],bcmul($base,$sgn,0),0);$out[$p['direction']==='received'?'received_minor':'spent_minor']=bcadd($out[$p['direction']==='received'?'received_minor':'spent_minor'],$base,0);
            $out['unallocated_minor']=bcadd($out['unallocated_minor'],bcmul($unallocated,$sgn,0),0);$currency=$p['currency'];$out['original_balances'][$currency]=bcadd($out['original_balances'][$currency]??'0',bcmul($original,$sgn,0),0);$row['running_minor']=$out['balance_minor'];$out['rows'][]=$row;
        }
        usort($out['rows'],function($a,$b){return [$a['payload']['date'],$a['id']]<=>[$b['payload']['date'],$b['id']];});$running='0';foreach($out['rows'] as &$r){$running=bcadd($running,bcmul($r['scoped_base_minor'],$r['payload']['direction']==='received'?'1':'-1',0),0);$r['running_minor']=$running;}unset($r);
        return $out;
    }
    public function auditTrail($customer)
    {
        $this->policy->requireRight('read');$this->statement($customer);
        $events=$this->rows('SELECT rowid,fk_entry,fk_user,action,reason,detail_json,date_creation FROM '.$this->table('audit').' WHERE entity='.$this->entity.' AND fk_soc='.(int)$customer.' ORDER BY rowid DESC LIMIT 200');
        // Current allocations do not authorize earlier snapshots. Fail the whole
        // audit view before serialization if any returned historical scope is hidden.
        foreach($events as $event){
            $detail=json_decode($event['detail_json'],true,512,JSON_THROW_ON_ERROR);
            $scopes=array_merge($detail['before_allocations']??[], $detail['input']['allocations']??[]);
            foreach($scopes as $line)$this->policy->scope($customer,(int)($line['project']??0),(int)($line['task']??0),true);
        }
        return $events;
    }
    public function options($customer)
    {
        $this->policy->customer($customer);$projectSql=$this->policy->projectSql();
        $projects=$this->rows('SELECT p.rowid,p.ref,p.title FROM '.MAIN_DB_PREFIX.'projet p WHERE p.fk_soc='.(int)$customer.' AND '.$projectSql.' ORDER BY p.rowid LIMIT 500');$tasks=[];
        foreach($projects as $p){$records=$this->rows('SELECT t.rowid,t.ref,t.title FROM '.MAIN_DB_PREFIX.'cpo_task t INNER JOIN '.MAIN_DB_PREFIX.'cpo_board_card bc ON bc.entity=t.entity AND bc.fk_task=t.rowid INNER JOIN '.MAIN_DB_PREFIX.'cpo_project_board pb ON pb.entity=bc.entity AND pb.fk_board=bc.fk_board WHERE t.entity='.$this->entity.' AND pb.fk_project='.(int)$p['rowid']." AND t.status<>'deleted' ORDER BY t.rowid LIMIT 500");foreach($records as $t){try{$this->policy->scope($customer,$p['rowid'],$t['rowid']);$tasks[]=['rowid'=>(int)$t['rowid'],'project'=>(int)$p['rowid'],'label'=>$t['ref'].' '.$t['title']];}catch(Throwable $e){}}}
        return ['projects'=>$projects,'tasks'=>$tasks];
    }

}
