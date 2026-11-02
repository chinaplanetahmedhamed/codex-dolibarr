<?php
declare(strict_types=1);
require_once __DIR__.'/OfficeExpenseNativeFinance.php';
require_once __DIR__.'/OfficeExpenseWorkspaceService.php';
require_once __DIR__.'/OfficeExpenseWorkspaceEvidence.php';

/** Owns the explicit approval/payment journey, on the native source connection. */
final class OfficeExpenseFinancialService
{
    private $db;private $conf;private OfficeExpenseStaffAccess $access;private OfficeExpenseWorkspaceService $source;private OfficeExpenseWorkspaceStore $store;private OfficeExpenseFinanceSettings $settings;private OfficeExpenseNativeFinance $native;private ?OfficeExpenseWorkspaceEvidence $evidence;
    public function __construct($db,$user,$conf,OfficeExpenseStaffAccess $access,OfficeExpenseWorkspaceService $source,?OfficeExpenseWorkspaceEvidence $evidence=null)
    {
        if(!$source->usesNativeConnection($db,(int)$conf->entity,(int)$user->id))throw new DomainException('Financial source context differs.');
        $this->db=$db;$this->conf=$conf;$this->access=$access;$this->source=$source;$this->evidence=$evidence;$this->store=new OfficeExpenseWorkspaceStore($db);$this->settings=new OfficeExpenseFinanceSettings($db,$conf,$access);$this->native=new OfficeExpenseNativeFinance($db,$user,$conf,$access);
    }
    public function settings(): OfficeExpenseFinanceSettings {return $this->settings;}
    /** Posted money only, by posting date; a later reversal does not erase prior-period history. */
    public function report(string $office,string $from,string $to): array
    {
        OfficeExpenseMoney::date($from);OfficeExpenseMoney::date($to);if($from>$to)throw new InvalidArgumentException('Invalid financial report period.');
        $scope=$this->access->recordScope($office,'reports');$rows=[];$totals=[];
        foreach(OfficeExpenseFinancialPlan::KINDS as$kind){
            if(!$this->source->allowed($office,$kind,'read'))continue;
            foreach($this->source->list($office,$kind)as$row){
                if($scope==='own'&&($kind==='payroll'?$row['subject']!==$this->source->actorId():$row['owner']!==$this->source->actorId()))continue;
                if(!in_array($row['status'],['posted','reversed'],true))continue;
                foreach(['posting','reversal']as$phase){$p=$row['content']['_finance'][$phase]??null;if(!$p||$p['plan']['date']<$from||$p['plan']['date']>$to)continue;$this->native->verifyPosted($p);$plan=$p['plan'];$c=$plan['currency'];
                    if(!isset($totals[$c]))$totals[$c]=['cash_in'=>'0.00','cash_out'=>'0.00','net'=>'0.00'];
                    $cash=$plan['cash_amount'];if(bccomp($cash,'0',2)>=0)$totals[$c]['cash_in']=bcadd($totals[$c]['cash_in'],$cash,2);else $totals[$c]['cash_out']=bcsub($totals[$c]['cash_out'],$cash,2);
                    $totals[$c]['net']=bcadd($totals[$c]['net'],$cash,2);$rows[]=['id'=>$row['id'],'kind'=>$kind,'phase'=>$phase,'date'=>$plan['date'],'currency'=>$c,'cash'=>$cash,'base_currency'=>$plan['base_currency'],'cash_base'=>$plan['cash_base'],'bank_id'=>$p['bank_id']??0,'journals'=>$p['journals']];
                }
            }
        }
        usort($rows,static fn($a,$b)=>[$b['date'],$b['id'],$b['phase']]<=>[$a['date'],$a['id'],$a['phase']]);return ['from'=>$from,'to'=>$to,'rows'=>$rows,'totals'=>$totals];
    }
    public function state(string $office,?array $row=null): array
    {
        $config=$this->settings->internal($office);$v=$config['content'];$out=['enabled'=>$v['enabled']&&$this->settings->coreEnabled(),'base'=>$this->settings->base(),'revision'=>$config['revision'],'administrator'=>$this->access->isFinanceAdministrator(),'evidence_available'=>$this->evidence!==null];
        if(!$row)return $out;
        $this->source->get($office,$row['kind'],$row['id']);$out['confirmation']=$this->confirmation($row);$out['permissions']=[];
        foreach(['approve','post','reverse']as$action)$out['permissions'][$action]=$this->permitted($office,$row['kind'],$action);
        $out['submit']=($row['owner']===$this->access->financeActorId()&&$this->source->allowed($office,$row['kind'],'create'))||$this->source->allowed($office,$row['kind'],'update');
        $out['staff']=$this->source->staff($office,OfficeExpenseWorkspaceDocument::SECTIONS[$row['kind']]);$out['customers']=[];$out['invoices']=[];$out['advances']=[];
        if($row['kind']==='receipt'&&$this->settings->coreEnabled()){
            $c=new OfficeExpenseNativeConnection($this->db);$where=$this->access->isFinanceAdministrator()||$v['customer_mode']==='all_company_customers'?'':' AND rowid IN ('.implode(',',array_merge([0],$v['customer_ids'])).')';
            $out['customers']=$c->rows('SELECT rowid,nom FROM '.MAIN_DB_PREFIX.'societe WHERE entity='.(int)$this->conf->entity.' AND client IN (1,2,3)'.$where.' ORDER BY nom LIMIT 1001');
            $invoiceScope=$this->access->isFinanceAdministrator()?'':' AND rowid IN ('.implode(',',array_merge([0],$v['invoice_ids'])).')';$customerIds=array_map('intval',array_column($out['customers'],'rowid'));
            $out['invoices']=$c->rows('SELECT rowid,ref,fk_soc,multicurrency_code FROM '.MAIN_DB_PREFIX.'facture WHERE entity='.(int)$this->conf->entity.' AND fk_statut IN (1,2) AND type NOT IN (2,4) AND fk_soc IN ('.implode(',',array_merge([0],$customerIds)).')'.$invoiceScope.' ORDER BY rowid DESC LIMIT 1001');
        }
        if($row['kind']==='payroll'&&$this->source->allowed($office,'advance','read'))foreach($this->source->list($office,'advance')as$loan)if($loan['status']==='posted'&&($loan['content']['_finance']['posting']['plan']['staff_id']??0)===$row['subject']&&$loan['content']['currency']===$row['content']['currency']){$balance=$this->advance($row,$loan['id'],$row['subject'],false);if(bccomp($balance['remaining_original'],'0',2)>0)$out['advances'][]=['id'=>$loan['id'],'purpose'=>$loan['content']['purpose'],'amount'=>$balance['remaining_original'],'currency'=>$loan['content']['currency']];}
        $out['evidence']=$this->evidence?$this->evidence->list($office,$row['kind'],$row['id']):[];return $out;
    }
    public function transition(string $office,string $kind,string $id,int $revision,string $action,string $key,array $input): array
    {
        $this->source->get($office,$kind,$id);
        if(!in_array($kind,OfficeExpenseFinancialPlan::KINDS,true))throw new DomainException('Financial document required.');
        if(!in_array($action,['submit','approve','reject','post','reverse'],true))throw new InvalidArgumentException('Invalid financial action.');
        return OfficeExpenseFinancialLock::run($this->db,(int)$this->conf->entity,function()use($office,$kind,$id,$revision,$action,$key,$input){
            $config=$this->settings->internal($office);
            return $this->store->financial((int)$this->conf->entity,$this->access->financeActorId(),$id,$revision,$action,$key,$input,
                function($row)use($office,$kind,$action,$config){
                    if($row['office']!==$office||$row['kind']!==$kind)throw new DomainException('Financial source differs.');
                    $this->source->get($office,$kind,$row['id']);
                    if($action==='submit'){
                        if(!(($row['owner']===$this->access->financeActorId()&&$this->source->allowed($office,$kind,'create'))||$this->source->allowed($office,$kind,'update')))throw new DomainException('Document submission unavailable.');
                    }else $this->access->assertFinancial($office,OfficeExpenseWorkspaceDocument::SECTIONS[$kind],$action==='reject'?'approve':$action,$config['content']['roles']);
                },
                function($row)use($action,$input,$config){
                    $content=$row['content'];$f=$content['_finance']??[];$actor=$this->access->financeActorId();
                    if($action==='submit'){
                        $intent=OfficeExpenseFinancialPlan::intent($row,$input,$this->settings->base(),$this->native->today());
                        if($intent['setup_revision']!==$config['revision'])throw new DomainException('Financial settings changed; refresh the preview.');
                        $this->settings->assertReady($row['office'],$config['content']);
                        $proof=$this->proof($row,$config['content']['require_evidence']);$plan=$this->plan($row,$intent,$config);
                        $f=['operations'=>$f['operations']??[],'intent'=>$intent,'submitted_by'=>$actor,'submitted_at'=>gmdate('Y-m-d H:i:s'),'proof'=>$proof,'source_hash'=>self::sourceHash($row),'plan'=>$plan];
                    }elseif($action==='reject'){
                        self::fields($input,['reason']);$reason=$input['reason']??'';if(!is_string($reason))throw new InvalidArgumentException('Rejection reason required.');OfficeExpenseMoney::text(trim($reason),1000);
                        $f['rejected_by']=$actor;$f['rejected_at']=gmdate('Y-m-d H:i:s');$f['rejection_reason']=trim($reason);unset($f['approved_hash']);
                    }elseif($action==='approve'||$action==='post'){
                        self::fields($input,['confirmation']);$this->settings->assertReady($row['office'],$config['content']);$this->unchanged($row,$f,$config);
                        $plan=$this->plan($row,$f['intent'],$config);$hash=self::hash([$plan,$f['proof'],$f['source_hash']]);
                        if(!is_string($input['confirmation']??null)||!hash_equals(self::hash([$f['plan'],$f['proof'],$f['source_hash']]),$input['confirmation'])||$plan!==$f['plan'])throw new DomainException('The reviewed financial preview changed; reject and resubmit.');
                        if($action==='approve'){$f['approved_by']=$actor;$f['approved_at']=gmdate('Y-m-d H:i:s');$f['approved_hash']=$hash;}
                        else{
                            if(!hash_equals($f['approved_hash']??'',$hash))throw new DomainException('Exact financial approval required.');
                            $posting=$this->native->post($row,$plan,$config['content']);$posting['configuration']=$config['content'];$f['posting']=$posting;
                        }
                    }else{
                        self::fields($input,['date','reason']);$reason=$input['reason']??'';if(!is_string($reason))throw new InvalidArgumentException('Reversal reason required.');OfficeExpenseMoney::text(trim($reason),1000);
                        $posting=$f['posting']??null;if(!$posting)throw new DomainException('Original native posting evidence missing.');$this->native->verifyPosted($posting);$this->noDependents($row);
                        $plan=OfficeExpenseFinancialPlan::reverse($posting['plan'],$input['date']??'',$this->native->today());
                        $f['reversal']=$this->native->post($row,$plan,$posting['configuration'],true);$f['reversal']['reason']=trim($reason);
                    }
                    $content['_finance']=$f;return $content;
                });
        });
    }
    public function confirmation(array $row): string
    {
        $this->source->get($row['office'],$row['kind'],$row['id']);$f=$row['content']['_finance']??[];
        return isset($f['plan'],$f['proof'],$f['source_hash'])?self::hash([$f['plan'],$f['proof'],$f['source_hash']]):'';
    }
    public function permitted(string $office,string $kind,string $action): bool
    {
        try{$config=$this->settings->internal($office);$this->access->assertFinancial($office,OfficeExpenseWorkspaceDocument::SECTIONS[$kind],$action,$config['content']['roles']);return true;}catch(DomainException $e){return false;}
    }
    private function unchanged(array $row,array $f,array $config): void
    {
        if(($f['intent']['setup_revision']??null)!==$config['revision']||!hash_equals($f['source_hash']??'',self::sourceHash($row))||($f['proof']??null)!==$this->proof($row,$config['content']['require_evidence']))throw new DomainException('Source, evidence or financial setup changed; reject and resubmit.');
    }
    private function proof(array $row,bool $required): array
    {
        if(!$this->evidence){if($required)throw new DomainException('Private evidence storage must be available before submission.');return [];}
        $proof=[];foreach($this->evidence->list($row['office'],$row['kind'],$row['id'],true)as$file)$proof[]=['id'=>$file['id'],'sha256'=>$file['sha256'],'size'=>$file['size']];
        if($required&&!$proof)throw new DomainException('Attach supporting evidence before submission.');return $proof;
    }
    private function plan(array $row,array $intent,array $config): array
    {
        $staff=$row['kind']==='payroll'?$row['content']['user_id']:$intent['staff_id'];
        if(in_array($row['kind'],['expense','advance','advance_return','advance_settlement','payroll'],true)){
            if(!in_array($staff,array_column($this->source->staff($row['office'],OfficeExpenseWorkspaceDocument::SECTIONS[$row['kind']]),'id'),true))throw new DomainException('Select an active native employee assigned to this branch.');
        }
        $allocation=null;$ref=$row['content']['settles_ref']??$intent['loan_ref'];
        if(in_array($row['kind'],['advance_return','advance_settlement'],true)||($row['kind']==='payroll'&&bccomp($row['content']['loan_repayment'],'0',2)>0))$allocation=$this->advance($row,$ref,$staff);
        if($row['kind']==='receipt')$allocation=$this->native->invoiceValuation($row,$intent,$config['content']);
        return OfficeExpenseFinancialPlan::build($row,$intent,$allocation);
    }
    private function advance(array $row,string $ref,int $staff,bool $lock=true): array
    {
        $parent=$lock?$this->store->locked((int)$this->conf->entity,$ref):$this->store->find((int)$this->conf->entity,$ref);if(!$parent||$parent['office']!==$row['office']||$parent['kind']!=='advance'||$parent['status']!=='posted'||$parent['content']['currency']!==$row['content']['currency'])throw new DomainException('Outstanding posted advance required in this branch/currency.');
        $p=$parent['content']['_finance']['posting']??null;if(!$p||$p['plan']['staff_id']!==$staff)throw new DomainException('Advance belongs to a different employee.');$this->native->verifyPosted($p);
        $original=$parent['content']['amount'];$base=$p['plan']['valuation']['base_amount'];
        foreach(['advance_return','advance_settlement','payroll']as$kind)foreach($this->store->list((int)$this->conf->entity,$row['office'],$kind)as$child){
            if($child['status']!=='posted')continue;$posting=$child['content']['_finance']['posting']??null;if(!$posting||$posting['plan']['allocation_ref']!==$ref)continue;$this->native->verifyPosted($posting);
            $original=bcsub($original,$posting['plan']['allocation_original'],2);$base=bcsub($base,$posting['plan']['allocation_base'],2);
        }
        return ['currency'=>$row['content']['currency'],'remaining_original'=>$original,'remaining_base'=>$base];
    }
    private function noDependents(array $row): void
    {
        if($row['kind']!=='advance')return;
        foreach(['advance_return','advance_settlement','payroll']as$kind)foreach($this->store->list((int)$this->conf->entity,$row['office'],$kind)as$child)if($child['status']==='posted'&&($child['content']['_finance']['posting']['plan']['allocation_ref']??null)===$row['id'])throw new DomainException('Reverse linked settlements before reversing their original advance.');
    }
    private static function sourceHash(array $row): string {$v=$row['content'];unset($v['_finance']);return self::hash([$row['entity'],$row['office'],$row['kind'],$row['owner'],$row['subject'],$v]);}
    private static function hash(array $v): string {return hash('sha256',json_encode($v,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));}
    private static function fields(array $input,array $allowed): void {if(array_diff(array_keys($input),$allowed)||array_diff($allowed,array_keys($input)))throw new InvalidArgumentException('Unexpected or missing financial fields.');}
}
