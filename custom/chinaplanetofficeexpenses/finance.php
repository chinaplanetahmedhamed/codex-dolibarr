<?php
declare(strict_types=1);
define('CSRFCHECK_WITH_TOKEN',1);
require $_SERVER['DOCUMENT_ROOT'].'/main.inc.php';
require_once __DIR__.'/class/OfficeExpenseWorkspaceRuntime.php';
require_once __DIR__.'/class/OfficeExpenseFinancialService.php';
header('Cache-Control: private, no-store');header('X-Content-Type-Options: nosniff');header('Referrer-Policy: same-origin');
$target='workspace.php';
try{
    if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){header('Allow: POST');http_response_code(405);exit;}
    if(!OfficeExpenseWorkspaceRuntime::ready($db,$conf))throw new DomainException('Branch workspace is not activated.');
    $p=$_POST;if(array_diff(array_keys($p),['token','action','office','lang','kind','id','revision','key','payload','file_id']))throw new InvalidArgumentException('Unexpected request fields.');
    if(!is_string($p['token']??null)||(string)currentToken()===''||!hash_equals((string)currentToken(),$p['token'])){http_response_code(403);echo 'Session expired. Reopen the record before retrying.';exit;}
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
        if($p['action']==='configure'){
            if($p['kind']!=='settings'||$p['id']!=='')throw new InvalidArgumentException('Invalid setup request.');foreach(['customer_ids','invoice_ids','category_accounts']as$list)if(!isset($p['payload'][$list]))$p['payload'][$list]=[];if(!isset($p['payload']['roles']))$p['payload']['roles']=[];if(!is_array($p['payload']['roles']))throw new InvalidArgumentException('Invalid delegates.');foreach(['approve','post','reverse']as$role)if(!isset($p['payload']['roles'][$role]))$p['payload']['roles'][$role]=[];$finance->settings()->save($p['office'],$p['payload'],$p['key'],$revision);
        }else $finance->transition($p['office'],$p['kind'],$p['id'],$revision,$p['action'],$p['key'],$p['payload']);
    }
    header('Location: '.$target,true,303);exit;
}catch(InvalidArgumentException $e){http_response_code(422);$message='Check the submitted fields and reopen the record. No operation was confirmed.';}
catch(DomainException $e){http_response_code(409);$message=$e->getMessage().' Reopen the record before retrying.';}
catch(Throwable $e){http_response_code(503);$message='The result was not confirmed. Reopen the source and reconcile its native references before submitting another payment.';}
header('Content-Type: text/html; charset=UTF-8');echo '<!doctype html><meta charset="utf-8"><title>Financial operation</title><p>'.htmlspecialchars($message,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'</p><a href="'.htmlspecialchars($target,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'">Back to record / رجوع إلى السجل</a>';
