<?php
declare(strict_types=1);
require_once __DIR__.'/OfficeExpenseWorkspaceService.php';

final class OfficeExpenseWorkspaceController
{
    public const VIEWS=['overview'=>'overview','expenses'=>'expense','advances'=>'advance','treasury'=>'deposit','receipts'=>'receipt','categories'=>'category','employees'=>'employee','payroll'=>'payroll','settings'=>'settings','reports'=>'reports'];
    private OfficeExpenseWorkspaceService $service;private string $token;
    public function __construct(OfficeExpenseWorkspaceService $service,string $token){$this->service=$service;$this->token=$token;}
    public static function receiptBaseUrl(string $configuredRoot): string
    {
        // The staging installation retains its old alias in native configuration.
        // Correct only that exact trusted alias; never derive QR targets from Host headers.
        return (string)preg_replace('~^https://staging-erp\\.visiontrading\\.biz(?=/|$)~','https://backup-erp.visiontrading.biz',rtrim($configuredRoot,'/'));
    }
    public function handle(string $method,array $query,array $post,array $files=[]): array
    {
        $state=['status'=>200,'error'=>null,'redirect'=>null,'csv'=>null,'view'=>'categories','kind'=>'category','office'=>'','language'=>'ar','offices'=>[],'rows'=>[],'legacy'=>[],'staff'=>[],'categories'=>[],'advances'=>[],'selected'=>null,'history'=>[],'print'=>false,'report'=>null,'permissions'=>[],'cash'=>null,'accounts'=>[],'settings'=>null,'form'=>null,'submission_key'=>bin2hex(random_bytes(16)),'receipt_filter'=>null,'receivers'=>[],'actor_name'=>'','receipt_link'=>'','can_edit_receipt_settings'=>false,'report_currency'=>'','month'=>''];
        try{
            if(!in_array($method,['GET','POST'],true))return array_replace($state,['status'=>405,'error'=>'method']);
            foreach(['view','office','lang','edit','print','from','to','kind','status','export','saved','search','currency','month']as$field)if(isset($query[$field])&&!is_string($query[$field]))throw new InvalidArgumentException('Invalid query.');
            $view=$post['view']??$query['view']??'categories';if(!is_string($view)||!isset(self::VIEWS[$view]))throw new InvalidArgumentException('Unknown section.');
            $lang=$post['lang']??$query['lang']??'ar';if(!in_array($lang,['ar','en'],true))throw new InvalidArgumentException('Unknown language.');
            $offices=$this->service->offices($view==='overview'?'expenses':$view);$office=$post['office']??$query['office']??array_key_first($offices);
            if(!is_string($office)||!isset($offices[$office]))throw new DomainException('Branch unavailable.');
            $kind=self::VIEWS[$view];if($view==='advances'&&($post['kind']??$query['kind']??'advance')==='advance_return')$kind='advance_return';
            $state=array_replace($state,['view'=>$view,'kind'=>$kind,'office'=>$office,'language'=>$lang,'offices'=>$offices]);
            if($method==='POST'){
                if($this->token===''||!is_string($post['token']??null)||!hash_equals($this->token,$post['token']))return array_replace($state,['status'=>403,'error'=>'csrf']);
                if(array_diff(array_keys($post),['view','kind','office','lang','token','action','id','revision','submission_key','payload','print_after']))throw new InvalidArgumentException('Unexpected fields.');
                $action=$post['action']??null;if(!in_array($action,['save','archive','restore'],true)||in_array($view,['reports','overview'],true))throw new InvalidArgumentException('Unsupported action.');
                if(isset($post['print_after'])&&($view!=='receipts'||$post['print_after']!=='1'||$action!=='save'))throw new InvalidArgumentException('Invalid print request.');
                $id=$post['id']??'';if(!is_string($id)||($id!==''&&!preg_match('/^[a-f0-9]{32}$/D',$id)))throw new InvalidArgumentException('Invalid reference.');
                $revision=OfficeExpenseWorkspaceDocument::number($post['revision']??'0',true);
                if($files){
                    if($view!=='settings'||$action!=='save'||array_keys($files)!==['logo']||!is_array($files['logo']))throw new InvalidArgumentException('Unsupported upload.');
                    if(!$this->service->allowed($office,'settings','update'))throw new DomainException('Branch branding update unavailable.');
                    if(($files['logo']['error']??null)!==UPLOAD_ERR_NO_FILE){if(!is_array($post['payload']??null))throw new InvalidArgumentException('Missing logo settings.');$post['payload']['logo']=OfficeExpenseWorkspaceLogo::upload($files['logo']);}
                }
                if($action==='save'){
                    if(!is_array($post['payload']??null)||!is_string($post['submission_key']??null))throw new InvalidArgumentException('Invalid document fields.');
                    $state['form']=$post['payload'];$state['submission_key']=$post['submission_key'];
                    $saved=$this->service->save($office,$kind,$post['payload'],$post['submission_key'],$id===''?null:$id,$revision);
                }else{if($id===''||isset($post['payload'])||isset($post['submission_key']))throw new InvalidArgumentException('Invalid transition.');$saved=$this->service->change($office,$kind,$id,$revision,$action);}
                return array_replace($state,['status'=>303,'redirect'=>'workspace.php?'.http_build_query(['lang'=>$lang,'office'=>$office,'view'=>$view,'kind'=>$kind,'saved'=>'1','edit'=>$saved['id']]+(isset($post['print_after'])?['print'=>'1']:[]))]);
            }
            foreach(['read','create','update','delete']as$action)$state['permissions'][$action]=$this->service->allowed($office,$kind,$action);
            if($view==='overview'){$state['report']=$this->service->overview($office);$state['actor_name']=$this->service->actorName();foreach(['expense','receipt','deposit']as$action)$state['overview_actions'][$action]=$this->service->allowed($office,$action,'create');return $state;}
            if($view==='reports'){
                $month=$query['month']??date('Y-m');OfficeExpenseMoney::date($month.'-01');if(!preg_match('/^[0-9]{4}-[0-9]{2}$/D',$month))throw new InvalidArgumentException('Invalid reporting month.');
                $state['month']=$month;$currency=$query['currency']??OfficeExpenseStaffAccess::OFFICES[$office][0];if(!in_array($currency,OfficeExpenseStaffAccess::OFFICES[$office],true))throw new InvalidArgumentException('Invalid branch currency.');$state['report_currency']=$currency;
                $state['report']=$this->service->report($office,$query['from']??$month.'-01',$query['to']??date('Y-m-t',strtotime($month.'-01')),$query['kind']??'all',$query['status']??'all');
                if(isset($query['export'])){if(!in_array($query['export'],['csv','cash_csv'],true))throw new InvalidArgumentException('Invalid export.');$state['csv']=$query['export']==='cash_csv'?OfficeExpenseWorkspaceService::cashCsv($state['report']):OfficeExpenseWorkspaceService::csv($state['report']);$state['csv_name']=$query['export']==='cash_csv'?'office-cash-period.csv':'office-requests.csv';}
                return $state;
            }
            if($view==='receipts'){
                $from=$query['from']??date('Y-m-01');$to=$query['to']??date('Y-m-t');OfficeExpenseMoney::date($from);OfficeExpenseMoney::date($to);
                $search=trim($query['search']??'');$status=$query['status']??'draft';
                if($from>$to||strlen($search)>160||preg_match('/[\x00-\x1f]/',$search)||!in_array($status,['all','draft','archived'],true))throw new InvalidArgumentException('Invalid receipt filters.');
                $state['receipt_filter']=['from'=>$from,'to'=>$to,'search'=>$search,'status'=>$status];
                $state['rows']=array_values(array_filter($this->service->list($office,$kind,$from,$to),static function($r)use($search,$status){$v=$r['content'];return ($status==='all'||$r['status']===$status)&&($search===''||stripos('REC-'.$r['id'].' '.$v['payer'].' '.$v['phone'].' '.$v['purpose'],$search)!==false);}));
                $state['actor_name']=$this->service->actorName();$state['can_edit_receipt_settings']=$this->service->allowed($office,'settings','update');
            }else $state['rows']=$this->service->list($office,$kind);
            if($view==='payroll'){$month=$query['month']??date('Y-m');OfficeExpenseMoney::date($month.'-01');if(!preg_match('/^[0-9]{4}-[0-9]{2}$/D',$month))throw new InvalidArgumentException('Invalid payroll month.');$state['month']=$month;$state['rows']=array_values(array_filter($state['rows'],static fn($r)=>$r['content']['month']===$month));}
            if($view==='advances')$state['rows']=array_merge($state['rows'],$this->service->list($office,'advance_return'));
            if(in_array($view,['expenses','advances'],true))$state['legacy']=$this->service->legacy($office,$view);
            if(in_array($view,['employees','payroll'],true))$state['staff']=$this->service->staff($office,$view);
            if($view==='expenses'&&$this->service->allowed($office,'category','read'))$state['categories']=array_values(array_filter($this->service->list($office,'category'),fn($r)=>$r['status']==='active'&&$r['content']['active']));
            if($view==='advances')$state['advances']=array_values(array_filter($this->service->list($office,'advance'),fn($r)=>$r['status']==='draft'));
            if($view==='settings'&&$state['rows'])$state['selected']=$state['rows'][0];
            if($view==='treasury')$state['cash']=$this->service->cash($office,$query['from']??date('Y-m-01'),$query['to']??date('Y-m-t'));
            if($view==='settings')$state['accounts']=$this->service->accounts($office);
            if(isset($query['edit'])){
                // Return requests carry their own kind in links, and cannot be accessed by substituting a different route kind.
                $state['selected']=$this->service->get($office,$kind,$query['edit']);
                $state['history']=$this->service->history($office,$kind,$query['edit']);
            }
            if($view==='receipts'){
                foreach(array_merge($state['rows'],$state['selected']?[$state['selected']]:[])as$r)if(!isset($state['receivers'][$r['owner']]))$state['receivers'][$r['owner']]=$this->service->receiptOwnerName($office,$r['id']);
            }
            if(isset($query['print'])){
                if($query['print']!=='1'||!$state['selected']||!in_array($kind,['receipt','deposit','payroll','expense','advance','advance_return'],true))throw new InvalidArgumentException('Invalid printable document.');$state['print']=true;
            }
            if($view==='receipts')$state['settings']=$this->service->receiptBrand($office);
            if($view==='payroll'&&$this->service->allowed($office,'settings','read'))$state['settings']=$this->service->list($office,'settings')[0]['content']??null;
            return $state;
        }catch(InvalidArgumentException $e){return array_replace($state,['status'=>422,'error'=>'invalid']);}
        catch(DomainException $e){return array_replace($state,['status'=>403,'error'=>'forbidden','rows'=>[],'legacy'=>[],'staff'=>[],'selected'=>null,'history'=>[],'report'=>null,'cash'=>null,'accounts'=>[]]);}
        catch(Throwable $e){return array_replace($state,['status'=>503,'error'=>'unavailable','rows'=>[],'legacy'=>[],'staff'=>[],'selected'=>null,'history'=>[],'report'=>null,'cash'=>null,'accounts'=>[]]);}
    }
}
