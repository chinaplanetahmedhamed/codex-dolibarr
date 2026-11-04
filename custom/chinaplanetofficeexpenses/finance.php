<?php
declare(strict_types=1);
define('CSRFCHECK_WITH_TOKEN',1);
require $_SERVER['DOCUMENT_ROOT'].'/main.inc.php';
require_once __DIR__.'/class/OfficeExpenseWorkspaceRuntime.php';
require_once __DIR__.'/class/OfficeExpenseFinancialService.php';
header('Cache-Control: private, no-store');header('X-Content-Type-Options: nosniff');header('Referrer-Policy: same-origin');
function cpoOfficeFinanceError(int $status,string $message,string $language,string $target): void
{
    http_response_code($status);header('Content-Type: text/html; charset=UTF-8');$ar=$language==='ar';$e=static fn($s)=>htmlspecialchars((string)$s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
    $copy=$status===503?'لم تتأكد نتيجة العملية. أعد فتح السجل وطابق مراجع الترحيل قبل إرسال دفعة أخرى.':($status===403?'انتهت صلاحية النموذج. أعد فتح السجل قبل المحاولة.':'لم تُؤكد العملية. راجع حالة الطلب والصلاحيات وإعدادات المحاسبة، ثم أعد فتح السجل.');
    if(str_contains(strtolower($message),'funds')||str_contains(strtolower($message),'overdraw'))$copy='الرصيد المتاح لا يكفي لهذه الحركة في تاريخها. راجع كشف الحساب قبل المحاولة.';
    elseif(str_contains(strtolower($message),'period'))$copy='لا توجد فترة محاسبية مفتوحة ومطابقة لتاريخ الترحيل. راجع إعدادات الفترات في Dolibarr.';
    $asset=DOL_URL_ROOT.'/custom/chinaplanetofficeexpenses/';
    echo '<!doctype html><html lang="'.($ar?'ar':'en').'" dir="'.($ar?'rtl':'ltr').'"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.($ar?'العملية المالية':'Financial operation').'</title><link rel="stylesheet" href="'.$e($asset.'employee.css').'"><link rel="stylesheet" href="'.$e($asset.'workspace.css').'"></head><body><main class="office-expenses-app workspace-finance-error"><section class="workspace-card"><h1>'.($ar?'لم يتم تأكيد العملية المالية':'Financial operation not confirmed').'</h1><p>'.$e($ar?$copy:$message).'</p>'.($ar?'<details><summary>التفاصيل</summary><p dir="ltr">'.$e($message).'</p></details>':'').'<a class="button button-brand" href="'.$e($target).'">'.($ar?'رجوع إلى السجل':'Back to record').'</a></section></main></body></html>';
}
$target='index.php';
try{
    if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){header('Allow: POST');http_response_code(405);exit;}
    if(!OfficeExpenseWorkspaceRuntime::ready($db,$conf))throw new DomainException('Branch workspace is not activated.');
    $p=$_POST;if(array_diff(array_keys($p),['token','action','office','lang','kind','id','revision','key','payload','file_id']))throw new InvalidArgumentException('Unexpected request fields.');
    if(!is_string($p['token']??null)||(string)currentToken()===''||!hash_equals((string)currentToken(),$p['token'])){cpoOfficeFinanceError(403,'Session expired. Reopen the record before retrying.',is_string($p['lang']??null)&&$p['lang']==='en'?'en':'ar',$target);exit;}
    foreach(['action','office','lang','kind','id','key']as$key)if(!is_string($p[$key]??null))throw new InvalidArgumentException('Incomplete request.');
    if(!in_array($p['lang'],['ar','en'],true)||!isset(OfficeExpenseStaffAccess::OFFICES[$p['office']])||(!in_array($p['kind'],OfficeExpenseFinancialPlan::KINDS,true)&&$p['kind']!=='settings'))throw new InvalidArgumentException('Invalid branch or document kind.');
    $target='workspace.php?'.http_build_query(['lang'=>$p['lang'],'office'=>$p['office'],'view'=>OfficeExpenseWorkspaceDocument::SECTIONS[$p['kind']],'kind'=>$p['kind']]+($p['id']!==''?['edit'=>$p['id']]:[]));
    $pilot=OfficeExpensePilotConfiguration::read(OfficeExpensePilotConfiguration::PATH,DOL_DOCUMENT_ROOT);$access=OfficeExpenseStaffRuntime::access($db,$user,$conf,$pilot);$source=new OfficeExpenseWorkspaceService($db,$user,(int)$conf->entity,$access);
    $evidence=new OfficeExpenseWorkspaceEvidence($db,$source,(int)$conf->entity,$source->actorId(),$pilot->privateRoot(),DOL_DOCUMENT_ROOT);$finance=new OfficeExpenseFinancialService($db,$user,$conf,$access,$source,$evidence);
    $revision=OfficeExpenseWorkspaceDocument::number($p['revision']??'0',true);
    if($p['action']==='attach'){
        if(array_keys($_FILES??[])!==['evidence']||(isset($p['payload'])||isset($p['file_id'])))throw new InvalidArgumentException('Invalid evidence request.');$file=OfficeExpenseWorkspaceEvidence::upload($_FILES['evidence']);$evidence->attach($p['office'],$p['kind'],$p['id'],$p['key'],$file['name'],$file['bytes']);
    }elseif($p['action']==='download'){
        if(!empty($_FILES)||isset($p['payload'])||!is_string($p['file_id']??null))throw new InvalidArgumentException('Invalid evidence request.');$file=$evidence->download($p['office'],$p['kind'],$p['id'],$p['file_id']);foreach(OfficeExpenseWorkspaceEvidence::headers($file,$p['file_id'])as$k=>$value)header($k.': '.$value);echo $file['bytes'];exit;
    }else{
        if(!empty($_FILES)||isset($p['file_id'])||!is_array($p['payload']??null))throw new InvalidArgumentException('Invalid financial request.');
        if($p['action']==='prepare_opening'){if($p['kind']!=='opening'||$p['id']!==''||array_keys($p['payload'])!==['bank_id']||!is_string($p['payload']['bank_id']))throw new InvalidArgumentException('Invalid opening preparation.');$opening=$finance->prepareOpening($p['office'],$p['payload']['bank_id'],$p['key']);$target='workspace.php?'.http_build_query(['lang'=>$p['lang'],'office'=>$p['office'],'view'=>'treasury','kind'=>'opening','edit'=>$opening['id']]);}
        elseif($p['action']==='configure'){
            if($p['kind']!=='settings'||$p['id']!=='')throw new InvalidArgumentException('Invalid setup request.');foreach(['customer_ids','invoice_ids','category_accounts']as$list)if(!isset($p['payload'][$list]))$p['payload'][$list]=[];if(!isset($p['payload']['roles']))$p['payload']['roles']=[];if(!is_array($p['payload']['roles']))throw new InvalidArgumentException('Invalid delegates.');foreach(['approve','post','reverse']as$role)if(!isset($p['payload']['roles'][$role]))$p['payload']['roles'][$role]=[];$finance->settings()->save($p['office'],$p['payload'],$p['key'],$revision);
        }else $finance->transition($p['office'],$p['kind'],$p['id'],$revision,$p['action'],$p['key'],$p['payload']);
    }
    header('Location: '.$target,true,303);exit;
}catch(InvalidArgumentException $e){$errorStatus=422;$message='Check the submitted fields and reopen the record. No operation was confirmed.';}
catch(DomainException $e){$errorStatus=409;$message=$e->getMessage().' Reopen the record before retrying.';}
catch(Throwable $e){$errorStatus=503;$message='The result was not confirmed. Reopen the source and reconcile its native references before submitting another payment.';}
cpoOfficeFinanceError($errorStatus,$message,is_string($_POST['lang']??null)&&$_POST['lang']==='en'?'en':'ar',$target);
