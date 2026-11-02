<?php
declare(strict_types=1);
require_once __DIR__.'/OfficeExpenseWorkspaceDocument.php';
require_once __DIR__.'/OfficeExpenseWorkspaceStore.php';
require_once __DIR__.'/OfficeExpenseStaffAccess.php';
require_once __DIR__.'/OfficeExpenseNativeDraftStore.php';
require_once __DIR__.'/OfficeExpenseNativeTreasury.php';

/** Operational workflow only. Native bank/payroll payments are never inferred from a draft. */
final class OfficeExpenseWorkspaceService
{
    private $db;private $user;private int $entity;private int $actor;
    private OfficeExpenseStaffAccess $access;private OfficeExpenseWorkspaceStore $store;private OfficeExpenseWorkspaceDocument $documents;
    public function __construct($db,$user,int $entity,OfficeExpenseStaffAccess $access)
    {
        if(!$access->usesContext($user,$entity))throw new DomainException('Native context differs.');
        $this->db=$db;$this->user=$user;$this->actor=(int)$user->id;$this->entity=$entity;$this->access=$access;
        $this->store=new OfficeExpenseWorkspaceStore($db);$this->documents=new OfficeExpenseWorkspaceDocument(new OfficeExpenseMoney());
    }
    public function usesNativeConnection($db,int $entity,int $actor): bool {return $db===$this->db&&$entity===$this->entity&&$actor===$this->actor&&$this->access->usesContext($this->user,$entity);}
    public function navigationViews(string $office): array {$this->context();return $this->access->navigationViews($office);}
    public function actorId(): int {$this->context();return $this->access->financeActorId();}
    private function context(): void {if(!$this->access->usesContext($this->user,$this->entity))throw new DomainException('Native context differs.');}
    public function offices(string $section): array
    {
        $this->context();$out=[];foreach($this->access->readScopes()as$office=>$sections)if(isset($sections[$section]))$out[$office]=OfficeExpenseStaffAccess::OFFICES[$office];return $out;
    }
    public function allowed(string $office,string $kind,string $action): bool
    {
        $this->context();$section=OfficeExpenseWorkspaceDocument::SECTIONS[$kind]??$kind;
        $allowed=$this->access->allowed($office,$section,$action);
        if($allowed&&$action!=='read'&&in_array($kind,['category','settings'],true)&&$this->access->recordScope($office,$section)!=='branch')return false;
        return $allowed;
    }
    public function receiptBrand(string $office): array
    {
        $this->context();$this->access->assertAction($office,'receipts','read');$rows=$this->store->list($this->entity,$office,'settings');$brand=$rows[0]['content']??['name'=>'China Planet','phone'=>'','logo'=>''];
        return array_intersect_key($brand,array_flip(['name','phone','logo']));
    }
    public function actorName(): string {$this->context();return trim((string)$this->user->firstname.' '.(string)$this->user->lastname)?:((string)$this->user->login);}
    public function receiptOwnerName(string $office,string $id): string
    {
        $row=$this->get($office,'receipt',$id);$c=new OfficeExpenseNativeConnection($this->db);
        $native=$c->one('SELECT firstname,lastname,login FROM '.MAIN_DB_PREFIX.'user WHERE rowid='.$row['owner'].' AND entity IN (0,'.$this->entity.') AND (fk_soc IS NULL OR fk_soc=0)');
        return $native?(trim((string)$native['firstname'].' '.(string)$native['lastname'])?:$native['login']):'#'.$row['owner'];
    }
    public function staff(string $office,string $section): array {$this->context();return $this->access->branchStaff($office,$section);}
    public function cash(string $office,?string $from=null,?string $to=null): array {$this->context();return (new OfficeExpenseNativeTreasury($this->db,$this->user,$this->entity,$this->access))->read($office,$from,$to);}
    public function accounts(string $office): array {$this->context();return (new OfficeExpenseNativeTreasury($this->db,$this->user,$this->entity,$this->access))->accounts($office);}
    public function list(string $office,string $kind,?string $from=null,?string $to=null): array
    {
        $this->context();$section=OfficeExpenseWorkspaceDocument::SECTIONS[$kind]??null;if(!$section)throw new InvalidArgumentException('Invalid document kind.');
        $scope=$this->access->recordScope($office,$section);
        $rows=$this->store->list($this->entity,$office,$kind,$scope==='own'&&!in_array($kind,['category','settings'],true)?$this->actor:null,$from,$to);
        // Payroll/profile visibility follows the subject even if an own-scope employee authored another record previously.
        if($scope==='own'&&in_array($kind,['payroll','employee'],true))$rows=array_values(array_filter($rows,fn($r)=>$r['subject']===$this->actor));
        return $rows;
    }
    public function get(string $office,string $kind,string $id): array
    {
        $this->context();$row=$this->store->find($this->entity,$id);
        if(!$row||$row['office']!==$office||$row['kind']!==$kind)throw new DomainException('Document unavailable.');
        $this->authorize($row,'read');return $row;
    }
    private function authorize(array $row,string $action): void
    {
        $this->context();$section=OfficeExpenseWorkspaceDocument::SECTIONS[$row['kind']];
        $this->access->assertAction($row['office'],$section,$action);$scope=$this->access->recordScope($row['office'],$section);
        if($scope==='own'&&!in_array($row['kind'],['category','settings'],true)&&(in_array($row['kind'],['employee','payroll'],true)?$row['subject']!==$this->actor:$row['owner']!==$this->actor))throw new DomainException('Document unavailable.');
        if($scope==='own'&&in_array($row['kind'],['settings','category'],true)&&$action!=='read')throw new DomainException('Branch configuration requires branch visibility.');
    }
    public function save(string $office,string $kind,array $payload,string $key,?string $id=null,int $revision=0): array
    {
        if($kind==='opening'&&!$this->access->isFinanceAdministrator())throw new DomainException('Only the finance administrator can reconcile native openings.');
        $v=$this->documents->validate($kind,$office,$payload);$ref=$id??bin2hex(random_bytes(16));
        $row=['office'=>$office,'kind'=>$kind,'owner'=>$this->actor,'subject'=>(int)($v['user_id']??0)];
        $action=$id===null?($kind==='settings'?'update':'create'):'update';
        $check=function()use($row,$v,$action){$this->authorize($row,$action);$this->references($row['office'],$row['kind'],$v);};
        $check();
        if($id===null)return $this->store->create($this->entity,$this->actor,$office,$kind,$key,$ref,$this->documents->objectKey($kind,$v,$ref),$v,$check);
        return $this->store->change($this->entity,$this->actor,$id,$revision,'update',$v,function($old)use($office,$kind,$v){if($old['office']!==$office||$old['kind']!==$kind)throw new DomainException('Document unavailable.');$this->authorize($old,'update');$this->references($office,$kind,$v);});
    }
    public function change(string $office,string $kind,string $id,int $revision,string $action): array
    {
        if($kind==='opening'&&!$this->access->isFinanceAdministrator())throw new DomainException('Opening reconciliation requires administrator authority.');
        if(!in_array($action,['archive','restore'],true)||$kind==='settings')throw new InvalidArgumentException('Unsupported document action.');
        return $this->store->change($this->entity,$this->actor,$id,$revision,$action,null,function($old)use($office,$kind,$action){if($old['office']!==$office||$old['kind']!==$kind)throw new DomainException('Document unavailable.');$this->authorize($old,'delete');if($action==='restore')$this->references($office,$kind,$old['content']);});
    }
    private function references(string $office,string $kind,array $v): void
    {
        if($kind==='opening'){
            if(!$this->access->isFinanceAdministrator())throw new DomainException('Opening reconciliation requires administrator authority.');
            $account=null;foreach($this->accounts($office)as$a)if($a['currency']===$v['currency'])$account=$a;if(!$account)throw new DomainException('Native branch account unavailable.');
            $c=new OfficeExpenseNativeConnection($this->db);$line=$c->one('SELECT dateo,CAST(CAST(amount AS DECIMAL(30,8)) AS CHAR) AS amount FROM '.MAIN_DB_PREFIX.'bank WHERE rowid='.$v['bank_id'].' AND fk_account='.(int)$account['id']." AND fk_type='SOLD'");
            if(!$line||substr($line['dateo'],0,10)!==$v['date']||bccomp($line['amount'],$v['amount'],8)!==0)throw new DomainException('Opening source differs from the existing native cash movement.');
        }
        if(isset($v['user_id'])){
            $staff=$this->staff($office,OfficeExpenseWorkspaceDocument::SECTIONS[$kind]);
            if(!in_array($v['user_id'],array_column($staff,'id'),true))throw new DomainException('Employee is not active in this branch.');
        }
        if($kind==='settings'&&$v['account_id']!==0){
            // Branding metadata cannot redirect the authoritative per-currency native mapping.
            (new OfficeExpenseNativeTreasury($this->db,$this->user,$this->entity,$this->access))->assertPrimaryAccount($office,$v['account_id']);
        }
        if(in_array($kind,['expense','advance_settlement'],true)&&$v['category_ref']!==''){
            $category=$this->get($office,'category',$v['category_ref']);if($category['status']!=='active'||!$category['content']['active'])throw new DomainException('Category is inactive.');
        }
        if(in_array($kind,['advance_return','advance_settlement'],true)){
            $advance=$this->get($office,'advance',$v['settles_ref']);
            if(!in_array($advance['status'],['draft','posted'],true)||$advance['content']['currency']!==$v['currency']||bccomp($v['amount'],$advance['content']['amount'],2)>0)throw new DomainException('Return does not match an available advance.');
            // These are requests, not a settled balance. Never present multiple pending returns as posted settlement.
        }
    }
    public function validateOpening(array $row): void {$this->get($row['office'],'opening',$row['id']);$this->references($row['office'],'opening',$row['content']);}
    public function history(string $office,string $kind,string $id): array
    {
        $this->get($office,$kind,$id);$c=new OfficeExpenseNativeConnection($this->db);
        return $c->rows('SELECT revision,action,fk_actor,date_creation FROM '.MAIN_DB_PREFIX.'cpo_oe_workspace_audit WHERE entity='.$this->entity.' AND office='.$c->quote($office).' AND record_ref='.$c->quote($id).' ORDER BY revision');
    }
    /** Old immutable drafts are still shown and reported, with their own revision/attachment workflow. */
    public function legacy(string $office,string $section): array
    {
        if(!in_array($section,['expenses','advances'],true))return [];
        $scope=$this->access->recordScope($office,$section);
        return (new OfficeExpenseNativeDraftStore($this->db))->listBranch($this->entity,$office,$section,$scope==='own'?$this->actor:null);
    }
    public function report(string $office,string $from,string $to,string $filter='all',string $status='all',bool $withCharts=true): array
    {
        $reportScope=$this->access->recordScope($office,'reports');OfficeExpenseMoney::date($from);OfficeExpenseMoney::date($to);
        if($from>$to||!in_array($filter,['all','expense','advance','advance_return','advance_settlement','deposit','receipt','payroll'],true)||!in_array($status,['all','draft','submitted','approved','posted','rejected','reversed','archived'],true))throw new InvalidArgumentException('Invalid reporting filters.');
        $rows=[];$totals=[];$money=new OfficeExpenseMoney();
        foreach(['expense','advance','advance_return','advance_settlement','deposit','receipt','payroll']as$kind){
            if($filter!=='all'&&$filter!==$kind)continue;
            if(!$this->allowed($office,$kind,'read'))continue;
            foreach($this->list($office,$kind,$from,$to)as$row){$v=$row['content'];if($v['date']<$from||$v['date']>$to||($status!=='all'&&$row['status']!==$status))continue;if($reportScope==='own'&&($kind==='payroll'?$row['subject']!==$this->actor:$row['owner']!==$this->actor))continue;$rows[]=$row;}
        }
        foreach(['expenses'=>'expense','advances'=>null]as$section=>$mapped){
            if(!$this->allowed($office,$section,'read')||$status==='archived')continue;
            foreach($this->legacy($office,$section)as$d){$kind=$mapped??$d['kind'];if(($filter!=='all'&&$filter!==$kind)||$d['occurred_on']<$from||$d['occurred_on']>$to||($reportScope==='own'&&$d['owner_id']!==$this->actor))continue;
                $rows[]=['id'=>$d['id'],'office'=>$office,'kind'=>$kind,'owner'=>$d['owner_id'],'subject'=>0,'status'=>'draft','legacy'=>true,'content'=>['date'=>$d['occurred_on'],'amount'=>$d['amount'],'currency'=>$d['currency'],'purpose'=>$d['purpose']]];
            }
        }
        foreach($rows as$row){if($row['status']==='archived')continue;$v=$row['content'];$currency=$v['currency'];$kind=$row['kind'];$totals[$currency][$kind]=bcadd($totals[$currency][$kind]??'0',$v[$kind==='payroll'?'net':'amount'],2);}
        usort($rows,fn($a,$b)=>[$b['content']['date'],$b['id']]<=>[$a['content']['date'],$a['id']]);
        $cash=null;if($reportScope==='branch'&&$this->allowed($office,'treasury','read')&&$this->access->recordScope($office,'treasury')==='branch')$cash=$this->cash($office,$from,$to);
        $result=['rows'=>$rows,'totals'=>$totals,'from'=>$from,'to'=>$to,'filter'=>$filter,'status'=>$status,'scope'=>$reportScope,'cash'=>$cash];
        if($withCharts){
            $end=new DateTimeImmutable(substr($to,0,7).'-01');$start=$end->modify('-5 months')->format('Y-m-d');
            $history=$this->report($office,$start,$to,$filter,$status,false);$labels=[];
            if($this->allowed($office,'category','read'))foreach($this->list($office,'category')as$category)$labels[$category['id']]=$category['content']['name'];
            $series=[];foreach(OfficeExpenseStaffAccess::OFFICES[$office]as$currency){
                $months=[];for($i=0;$i<6;$i++){$month=(new DateTimeImmutable($start))->modify('+'.$i.' months')->format('Y-m');$months[$month]=['month'=>$month,'deposit'=>'0.00','expense'=>'0.00','payroll'=>'0.00'];}
                foreach($history['rows']as$row){$v=$row['content'];$month=substr($v['date'],0,7);if($row['status']==='archived'||$v['currency']!==$currency||!isset($months[$month][$row['kind']]))continue;$kind=$row['kind'];$months[$month][$kind]=bcadd($months[$month][$kind],$v[$kind==='payroll'?'net':'amount'],2);}
                $categories=[];foreach($rows as$row){$v=$row['content'];if($row['status']==='archived'||$row['kind']!=='expense'||$v['currency']!==$currency)continue;$key=$v['category_ref']??'';$name=$labels[$key]??null;$bucket=$name===null?'uncategorized':$key;$categories[$bucket]=['name'=>$name,'amount'=>bcadd($categories[$bucket]['amount']??'0.00',$v['amount'],2)];}
                foreach($months as&$month)$month['net']=bcsub(bcsub($month['deposit'],$month['expense'],2),$month['payroll'],2);unset($month);
                $series[$currency]=['months'=>array_values($months),'categories'=>array_values($categories)];
            }
            $result['charts']=$series;
        }
        return $result;
    }
    public function overview(string $office): array
    {
        if($this->allowed($office,'reports','read'))return $this->report($office,date('Y-m-01'),date('Y-m-t'));
        $rows=$this->list($office,'expense',date('Y-m-01'),date('Y-m-t'));$totals=[];
        foreach($rows as$row)if($row['status']!=='archived'){$v=$row['content'];$totals[$v['currency']]['expense']=bcadd($totals[$v['currency']]['expense']??'0.00',$v['amount'],2);}
        return ['rows'=>$rows,'totals'=>$totals,'from'=>date('Y-m-01'),'to'=>date('Y-m-t'),'scope'=>'own','cash'=>null,'charts'=>[]];
    }
    public static function cashCsv(array $report): string
    {
        if(empty($report['cash'])||$report['cash']['restricted'])throw new DomainException('Branch cash report unavailable.');
        $stream=fopen('php://temp','w+');fwrite($stream,"\xEF\xBB\xBF");fputcsv($stream,['From','To','Account reference','Currency','Opening before period','Initial balances during period','Cash in excluding initial balances','Cash out','Recorded closing'],';', '"','');
        foreach($report['cash']['accounts']as$account)fputcsv($stream,[$report['from'],$report['to'],$account['reference'],$account['currency'],$account['period_opening'],$account['period_initial'],$account['period_in'],$account['period_out'],$account['period_closing']],';','"','');
        rewind($stream);$csv=stream_get_contents($stream);fclose($stream);return $csv;
    }
    public static function csv(array $report): string
    {
        $stream=fopen('php://temp','w+');fwrite($stream,"\xEF\xBB\xBF");fputcsv($stream,['Reference','Type','Date','Status','Original currency','Amount / net salary','Gross salary','Loan repayment','Description'],';', '"','');
        foreach($report['rows']as$row){$v=$row['content'];$cells=[$row['id'],$row['kind'],$v['date'],$row['status'],$v['currency'],$v[$row['kind']==='payroll'?'net':'amount'],$v['gross']??'',$v['loan_repayment']??'',$v['purpose']??$v['notes']??''];$cells=array_map(fn($s)=>preg_match('/^[\s]*[=+\-@]/u',(string)$s)?"'".$s:(string)$s,$cells);fputcsv($stream,$cells,';','"','');}
        rewind($stream);$csv=stream_get_contents($stream);fclose($stream);return $csv;
    }
}
