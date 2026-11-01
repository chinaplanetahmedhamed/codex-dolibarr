<?php
declare(strict_types=1);
require_once __DIR__.'/OfficeExpenseWorkspaceService.php';

final class OfficeExpenseWorkspaceController
{
    public const VIEWS=['expenses'=>'expense','advances'=>'advance','treasury'=>'deposit','receipts'=>'receipt','categories'=>'category','employees'=>'employee','payroll'=>'payroll','settings'=>'settings','reports'=>'reports'];
    private OfficeExpenseWorkspaceService $service;private string $token;
    public function __construct(OfficeExpenseWorkspaceService $service,string $token){$this->service=$service;$this->token=$token;}
    public function handle(string $method,array $query,array $post,array $files=[]): array
    {
        $state=['status'=>200,'error'=>null,'redirect'=>null,'csv'=>null,'view'=>'categories','kind'=>'category','office'=>'','language'=>'ar','offices'=>[],'rows'=>[],'legacy'=>[],'staff'=>[],'categories'=>[],'advances'=>[],'selected'=>null,'history'=>[],'print'=>false,'report'=>null,'permissions'=>[],'cash'=>null,'accounts'=>[],'settings'=>null,'form'=>null,'submission_key'=>bin2hex(random_bytes(16))];
        try{
            if(!in_array($method,['GET','POST'],true))return array_replace($state,['status'=>405,'error'=>'method']);
            foreach(['view','office','lang','edit','print','from','to','kind','status','export','saved']as$field)if(isset($query[$field])&&!is_string($query[$field]))throw new InvalidArgumentException('Invalid query.');
            $view=$post['view']??$query['view']??'categories';if(!is_string($view)||!isset(self::VIEWS[$view]))throw new InvalidArgumentException('Unknown section.');
            $lang=$post['lang']??$query['lang']??'ar';if(!in_array($lang,['ar','en'],true))throw new InvalidArgumentException('Unknown language.');
            $offices=$this->service->offices($view);$office=$post['office']??$query['office']??array_key_first($offices);
            if(!is_string($office)||!isset($offices[$office]))throw new DomainException('Branch unavailable.');
            $kind=self::VIEWS[$view];if($view==='advances'&&($post['kind']??$query['kind']??'advance')==='advance_return')$kind='advance_return';
            $state=array_replace($state,['view'=>$view,'kind'=>$kind,'office'=>$office,'language'=>$lang,'offices'=>$offices]);
            if($method==='POST'){
                if($this->token===''||!is_string($post['token']??null)||!hash_equals($this->token,$post['token']))return array_replace($state,['status'=>403,'error'=>'csrf']);
                if(array_diff(array_keys($post),['view','kind','office','lang','token','action','id','revision','submission_key','payload']))throw new InvalidArgumentException('Unexpected fields.');
                $action=$post['action']??null;if(!in_array($action,['save','archive','restore'],true)||$view==='reports')throw new InvalidArgumentException('Unsupported action.');
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
                return array_replace($state,['status'=>303,'redirect'=>'workspace.php?'.http_build_query(['lang'=>$lang,'office'=>$office,'view'=>$view,'kind'=>$kind,'saved'=>'1','edit'=>$saved['id']])]);
            }
            foreach(['read','create','update','delete']as$action)$state['permissions'][$action]=$this->service->allowed($office,$kind,$action);
            if($view==='reports'){
                $state['report']=$this->service->report($office,$query['from']??date('Y-m-01'),$query['to']??date('Y-m-t'),$query['kind']??'all',$query['status']??'all');
                if(isset($query['export'])){if(!in_array($query['export'],['csv','cash_csv'],true))throw new InvalidArgumentException('Invalid export.');$state['csv']=$query['export']==='cash_csv'?OfficeExpenseWorkspaceService::cashCsv($state['report']):OfficeExpenseWorkspaceService::csv($state['report']);$state['csv_name']=$query['export']==='cash_csv'?'office-cash-period.csv':'office-requests.csv';}
                return $state;
            }
            $state['rows']=$this->service->list($office,$kind);
            if($view==='advances')$state['rows']=array_merge($state['rows'],$this->service->list($office,'advance_return'));
            if(in_array($view,['expenses','advances'],true))$state['legacy']=$this->service->legacy($office,$view);
            if(in_array($view,['employees','payroll'],true))$state['staff']=$this->service->staff($office,$view);
            if($view==='expenses'&&$this->service->allowed($office,'category','read'))$state['categories']=array_values(array_filter($this->service->list($office,'category'),fn($r)=>$r['status']==='active'&&$r['content']['active']));
            if($view==='advances')$state['advances']=array_values(array_filter($this->service->list($office,'advance'),fn($r)=>$r['status']==='draft'));
            if($view==='settings'&&$state['rows'])$state['selected']=$state['rows'][0];
            if($view==='treasury')$state['cash']=$this->service->cash($office);
            if($view==='settings')$state['accounts']=$this->service->accounts($office);
            if(isset($query['edit'])){
                // Return requests carry their own kind in links, and cannot be accessed by substituting a different route kind.
                $state['selected']=$this->service->get($office,$kind,$query['edit']);
                $state['history']=$this->service->history($office,$kind,$query['edit']);
            }
            if(isset($query['print'])){
                if($query['print']!=='1'||!$state['selected']||!in_array($kind,['receipt','deposit','payroll','expense','advance','advance_return'],true))throw new InvalidArgumentException('Invalid printable document.');$state['print']=true;
            }
            if(in_array($view,['receipts','payroll'],true)&&$this->service->allowed($office,'settings','read'))$state['settings']=$this->service->list($office,'settings')[0]['content']??null;
            return $state;
        }catch(InvalidArgumentException $e){return array_replace($state,['status'=>422,'error'=>'invalid']);}
        catch(DomainException $e){return array_replace($state,['status'=>403,'error'=>'forbidden','rows'=>[],'legacy'=>[],'staff'=>[],'selected'=>null,'history'=>[],'report'=>null,'cash'=>null,'accounts'=>[]]);}
        catch(Throwable $e){return array_replace($state,['status'=>503,'error'=>'unavailable','rows'=>[],'legacy'=>[],'staff'=>[],'selected'=>null,'history'=>[],'report'=>null,'cash'=>null,'accounts'=>[]]);}
    }
}
