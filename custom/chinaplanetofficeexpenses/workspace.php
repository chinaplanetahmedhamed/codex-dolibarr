<?php
declare(strict_types=1);
define('CSRFCHECK_WITH_TOKEN',1);
require $_SERVER['DOCUMENT_ROOT'].'/main.inc.php';
require_once __DIR__.'/class/OfficeExpenseWorkspaceRuntime.php';
require_once __DIR__.'/class/OfficeExpenseWorkspacePage.php';
header('Cache-Control: private, no-store');header('X-Content-Type-Options: nosniff');header('Referrer-Policy: same-origin');
try{
    if(!OfficeExpenseWorkspaceRuntime::ready($db,$conf))throw new DomainException('Branch workspace is not activated.');
    $pilot=OfficeExpensePilotConfiguration::read(OfficeExpensePilotConfiguration::PATH,DOL_DOCUMENT_ROOT);
    $access=OfficeExpenseStaffRuntime::access($db,$user,$conf,$pilot);
    $service=new OfficeExpenseWorkspaceService($db,$user,(int)$conf->entity,$access);
    $method=(string)($_SERVER['REQUEST_METHOD']??'GET');
    $nativeLogin=$method==='POST'&&($_POST['actionlogin']??null)==='login'&&is_string($_POST['username']??null)&&is_string($_POST['password']??null)&&($_SESSION['dol_login']??null)===($user->login??null);
    if($nativeLogin&&!empty($_FILES)){http_response_code(415);exit;}
    $result=(new OfficeExpenseWorkspaceController($service,(string)currentToken()))->handle($nativeLogin?'GET':$method,$_GET??[],$nativeLogin?[]:($_POST??[]),$_FILES??[]);
    if($nativeLogin&&$result['status']===200){header('Location: workspace.php?'.http_build_query(['view'=>$result['view'],'office'=>$result['office'],'lang'=>$result['language']]),true,303);exit;}
    http_response_code($result['status']);if($result['redirect']!==null){header('Location: '.$result['redirect'],true,303);exit;}
    if($result['csv']!==null){header('Content-Type: text/csv; charset=UTF-8');header('Content-Disposition: attachment; filename="office-requests.csv"');echo $result['csv'];exit;}
    if($result['status']===405)header('Allow: GET, POST');
    try{$result['manage_staff']=(bool)$access->managedOffices();}catch(DomainException $e){$result['manage_staff']=false;}
    $asset=DOL_URL_ROOT.'/custom/chinaplanetofficeexpenses/';$hasCsp=false;
    foreach(headers_list()as$h)if(stripos($h,'Content-Security-Policy:')===0)$hasCsp=true;
    if(!$hasCsp&&!defined('MAIN_SECURITY_FORCECSP')&&(!function_exists('getDolGlobalString')||getDolGlobalString('MAIN_SECURITY_FORCECSP')==='')){
        $nonce=function_exists('getNonce')?(string)getNonce():'';$source=$nonce!==''?" 'nonce-".$nonce."'":'';
        header("Content-Security-Policy: default-src 'none'; script-src 'self'".$source."; style-src 'self'".$source."; style-src-attr 'unsafe-inline'; manifest-src 'self'; font-src 'self' data:; img-src 'self' data:; connect-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'self'; object-src 'none'");
    }
    $moduleDirectory=__DIR__;$styles=[];foreach(['employee.css','staff.css','workspace.css']as$file)$styles[]=$asset.$file.'?v='.substr(hash_file('sha256',$moduleDirectory.'/'.$file),0,12);
    $conf->dol_hide_leftmenu=1;
    llxHeader('',$result['language']==='ar'?'إدارة الفرع':'Branch workspace','','',0,0,[$asset.'workspace.js?v='.substr(hash_file('sha256',__DIR__.'/workspace.js'),0,12)],array_merge([$asset.'assets/fontawesome.css',$asset.'assets/fontawesome-solid.css'],$styles));
    echo (new OfficeExpenseWorkspacePage())->render($result,(string)newToken());llxFooter();
}catch(DomainException $e){http_response_code(403);echo 'Branch workspace is not activated or not authorized.';}
catch(Throwable $e){http_response_code(503);echo 'Branch workspace is temporarily unavailable. No write was confirmed.';}
