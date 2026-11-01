<?php
declare(strict_types=1);
require_once __DIR__.'/OfficeExpenseDraftService.php';
require_once __DIR__.'/OfficeExpenseReceiptHttp.php';

/** Authenticated draft-only HTTP behavior; native session/bootstrap supplies the context. */
final class OfficeExpenseEmployeeController
{
    private $service; private $requestToken; private $formToken; private $language;private $receipts;private $uploads;
    private const FIELDS=array('action','token','submission_key','kind','office','amount','currency','occurred_on','source_ref','purpose','quote_rate','quote_date','quote_source','settles_ref','lang');
    public function __construct(OfficeExpenseDraftService $service,string $requestToken,string $formToken,string $language='en',?OfficeExpenseReceiptService $receipts=null,?OfficeExpenseReceiptUploadSource $uploads=null)
    {
        if($receipts!==null&&!$receipts->usesDraftService($service))throw new InvalidArgumentException('Receipt service must use the identical authorized draft service.');
        $this->service=$service;$this->requestToken=$requestToken;$this->formToken=$formToken;$this->language=$language==='ar'?'ar':'en';
        $this->receipts=$receipts;$this->uploads=$uploads??new OfficeExpenseHttpUploadSource();
    }

    public function handle(string $method,array $query,array $post,array $files=array()): array
    {
        $requested=$query['lang'] ?? $post['lang'] ?? $this->language;
        $lang=in_array($requested,array('ar','en'),true)?$requested:$this->language;
        $state=array('status'=>200,'language'=>$lang,'error'=>null,'drafts'=>array(),'filtered_drafts'=>array(),'offices'=>array(),'base_currency'=>'','values'=>array(),'saved'=>false,'redirect'=>null,'view'=>'expenses','filters'=>array('search'=>'','from'=>'','to'=>'','kind'=>''));
        $state+=array('receipt_enabled'=>$this->receipts!==null,'receipt_policy'=>array(),'receipt_upload_limit'=>0,'selected_draft'=>null,'receipts'=>array(),
            'receipt_error'=>null,'receipt_saved'=>false,'receipt_values'=>array('draft_id'=>'','receipt_key'=>''),'download'=>null,'download_id'=>null);
        try {
            $state['drafts']=$this->service->listOwn();$state['offices']=$this->service->officeOptions();$state['base_currency']=$this->service->baseCurrency();
            if (!$state['offices']) throw new DomainException('No assigned office.');
        } catch (DomainException $e) { return $this->error($state,403,'forbidden'); }
        catch (Throwable $e) { return $this->error($state,503,'unavailable'); }
        $first=array_key_first($state['offices']);
        $state['values']=array('submission_key'=>bin2hex(random_bytes(16)),'kind'=>'expense_claim','office'=>$first,
            'amount'=>'','currency'=>$state['offices'][$first][0],'occurred_on'=>date('Y-m-d'),'source_ref'=>'','purpose'=>'','quote_rate'=>'','quote_date'=>'','quote_source'=>'','settles_ref'=>'');
        $state['receipt_values']['receipt_key']=bin2hex(random_bytes(16));
        if($this->receipts!==null){$state['receipt_policy']=$this->receipts->uploadPolicy();$state['receipt_upload_limit']=$this->uploads->effectiveLimit($state['receipt_policy']['max_bytes']);}
        if ($this->formToken==='') return $this->error($state,503,'unavailable');
        if (isset($query['view']) && (!is_string($query['view']) || !in_array($query['view'],array('overview','expenses','advances'),true))) return $this->error($state,400,'invalid');
        $state['view']=$query['view']??'expenses';
        $state['values']['kind']=$state['view']==='advances'?'advance':'expense_claim';
        foreach ($state['filters'] as $field=>$default) {
            if (!isset($query[$field])) continue;
            if (!is_string($query[$field]) || strlen($query[$field])>160) return $this->error($state,400,'invalid');
            $state['filters'][$field]=trim($query[$field]);
        }
        try {foreach(array('from','to')as$field) if($state['filters'][$field]!=='') OfficeExpenseMoney::date($state['filters'][$field]);}
        catch(InvalidArgumentException $e){return $this->error($state,400,'invalid');}
        if (($state['filters']['kind']!=='' && !in_array($state['filters']['kind'],array('expense_claim','advance','advance_return'),true))
            || ($state['filters']['from']!=='' && $state['filters']['to']!=='' && $state['filters']['from']>$state['filters']['to'])) return $this->error($state,400,'invalid');
        $state['filtered_drafts']=$this->filtered($state);
        if ($method==='GET') {
            foreach (array('draft','saved') as $field) {
                if (isset($query[$field])) {
                    try { if (!is_string($query[$field])) throw new DomainException('Invalid ID.');$selected=$this->service->getOwn($query[$field]);if($field==='draft'||$state['selected_draft']===null)$state['selected_draft']=$selected; }
                    catch (DomainException $e) { return $this->error($state,404,'not_found'); }
                }
            }
            $state['saved']=isset($query['saved']);
            if($this->receipts!==null&&$state['selected_draft']!==null){
                $state['receipt_values']['draft_id']=$state['selected_draft']['id'];
                try{$state['receipts']=$this->receipts->listOwn($state['selected_draft']['id']);}
                catch(DomainException $e){return $this->error($state,404,'not_found');}
                catch(Throwable $e){return $this->receiptError($state,503,'receipt_unavailable');}
                if(isset($query['receipt_saved'])){
                    if(!is_string($query['receipt_saved'])||!in_array($query['receipt_saved'],array_column($state['receipts'],'id'),true))return $this->error($state,404,'not_found');
                    $state['receipt_saved']=true;
                }
            }
            return $state;
        }
        if ($method!=='POST') return $this->error($state,405,'method');
        if (($query['errorcode']??null)==='InvalidToken') return $this->error($state,403,'csrf');
        foreach ($state['values'] as $field=>$default) if (isset($post[$field]) && is_string($post[$field])) $state['values'][$field]=$post[$field];
        if ($this->requestToken==='' || !isset($post['token']) || !is_string($post['token']) || !hash_equals($this->requestToken,$post['token'])) return $this->error($state,403,'csrf');
        if(in_array($post['action']??null,array('attach_receipt','download_receipt'),true))return $this->receiptAction($state,$post,$files);
        if (($post['action'] ?? null)!=='save') return $this->error($state,403,'forbidden_action');
        if($files)return $this->error($state,415,'receipt_save_first');
        if (array_diff(array_keys($post),self::FIELDS)) return $this->error($state,422,'invalid');
        if (!is_string($post['office'] ?? null) || !array_key_exists($post['office'],$state['offices'])) return $this->error($state,403,'forbidden');
        if (!in_array($post['kind'] ?? null,array('expense_claim','advance','advance_return'),true)) return $this->error($state,422,'invalid');
        $payload=array_intersect_key($post,array_flip(array('kind','office','amount','currency','occurred_on','source_ref','purpose')));
        if (($post['kind'] ?? '')==='advance_return' || ($post['settles_ref'] ?? '')!=='') $payload['settles_ref']=$post['settles_ref'] ?? '';
        $quote=array('rate'=>$post['quote_rate'] ?? '', 'date'=>$post['quote_date'] ?? '', 'source'=>$post['quote_source'] ?? '');
        if ($quote!==array('rate'=>'','date'=>'','source'=>'')) $payload['quote']=$quote;
        try {
            $draft=$this->service->submit($payload,$post['submission_key'] ?? null);
            $state['status']=303;$state['redirect']='index.php?lang='.$lang.'&view='.($draft['kind']==='expense_claim'?'expenses':'advances').'&saved='.$draft['id'];return $state;
        } catch (InvalidArgumentException $e) { return $this->error($state,422,'invalid'); }
        catch (DomainException $e) { return $this->error($state,409,'conflict'); }
        catch (Throwable $e) { return $this->error($state,503,'unavailable'); }
    }

    private function error(array $state,int $status,string $message): array
    {
        $state['status']=$status;$state['error']=$message;
        $state['download']=null;$state['download_id']=null;
        if (in_array($status,array(403,404),true)) { $state['drafts']=array();$state['filtered_drafts']=array();$state['offices']=array();$state['selected_draft']=null;$state['receipts']=array();$state['receipt_enabled']=false; }
        return $state;
    }
    private function filtered(array $state): array
    {
        return array_values(array_filter($state['drafts'],static function($draft)use($state){
            if($state['view']==='expenses'&&$draft['kind']!=='expense_claim')return false;
            if($state['view']==='advances'&&!in_array($draft['kind'],array('advance','advance_return'),true))return false;
            $f=$state['filters'];
            return ($f['kind']===''||$draft['kind']===$f['kind'])&&($f['from']===''||$draft['occurred_on']>=$f['from'])
                &&($f['to']===''||$draft['occurred_on']<=$f['to'])&&($f['search']===''||stripos($draft['purpose'].' '.$draft['source_ref'],$f['search'])!==false);
        }));
    }
    private function receiptError(array $state,int $status,string $message): array
    {
        $state['status']=$status;$state['receipt_error']=$message;$state['download']=null;
        if($state['selected_draft']===null)$state['error']=$message;return $state;
    }
    private function receiptAction(array $state,array $post,array $files): array
    {
        if($this->receipts===null)return $this->receiptError($state,503,'receipt_unavailable');
        $attach=$post['action']==='attach_receipt';
        $allowed=$attach?array('action','token','lang','draft_id','receipt_key'):array('action','token','lang','draft_id','receipt_id','variant');
        if(array_diff(array_keys($post),$allowed))return $this->receiptError($state,422,'receipt_invalid');
        if(!is_string($post['draft_id']??null))return $this->error($state,404,'not_found');
        try{$draft=$this->service->getOwn($post['draft_id']);}
        catch(DomainException $e){return $this->error($state,404,'not_found');}
        catch(Throwable $e){return $this->receiptError($state,503,'receipt_unavailable');}
        $state['selected_draft']=$draft;$state['view']=$draft['kind']==='expense_claim'?'expenses':'advances';$state['filtered_drafts']=$this->filtered($state);$state['receipt_values']['draft_id']=$draft['id'];
        if(is_string($post['receipt_key']??null)&&preg_match('/^[A-Za-z0-9_-]{8,80}$/D',$post['receipt_key']))$state['receipt_values']['receipt_key']=$post['receipt_key'];
        try{
            $state['receipts']=$this->receipts->listOwn($draft['id']);
            if($attach){
                if(!is_string($post['receipt_key']??null)||!preg_match('/^[A-Za-z0-9_-]{8,80}$/D',$post['receipt_key'])
                    ||array_keys($files)!==array('receipt')||!is_array($files['receipt']))return $this->receiptError($state,422,'receipt_invalid');
                if($state['receipt_upload_limit']<=0)return $this->receiptError($state,503,'receipt_unavailable');
                $file=$this->uploads->read($files['receipt'],$state['receipt_upload_limit']);
                $meta=$this->receipts->attach($draft['id'],$post['receipt_key'],$file['name'],$file['bytes']);
                if(!is_string($meta['id']??null)||!preg_match('/^[a-f0-9]{32}$/D',$meta['id']))throw new RuntimeException('Invalid receipt result.');
                $state['status']=303;$state['redirect']='index.php?lang='.$state['language'].'&view='.$state['view'].'&draft='.$draft['id'].'&receipt_saved='.$meta['id'].'#receipts';return $state;
            }
            if($files)return $this->receiptError($state,415,'receipt_invalid');
            if(!is_string($post['receipt_id']??null)||!preg_match('/^[a-f0-9]{32}$/D',$post['receipt_id']))return $this->error($state,404,'not_found');
            $variant=$post['variant']??'original';if(!is_string($variant)||!in_array($variant,array('original','preview'),true))return $this->receiptError($state,422,'receipt_invalid');
            $download=$this->receipts->read($draft['id'],$post['receipt_id'],$variant);OfficeExpenseReceiptHttpResponse::headers($download,$post['receipt_id']);
            $state['download']=$download;$state['download_id']=$post['receipt_id'];return $state;
        }catch(OfficeExpenseUploadTooLarge $e){return $this->receiptError($state,413,'receipt_too_large');}
        catch(OfficeExpenseReceiptQuota $e){return $this->receiptError($state,409,'receipt_quota');}
        catch(OfficeExpenseReceiptConflict $e){return $this->receiptError($state,409,'receipt_conflict');}
        catch(InvalidArgumentException $e){return $this->receiptError($state,422,'receipt_invalid');}
        catch(DomainException $e){return $this->error($state,404,'not_found');}
        catch(Throwable $e){return $this->receiptError($state,503,'receipt_unavailable');}
    }
}
