<?php
declare(strict_types=1);
define('CSRFCHECK_WITH_TOKEN',1);
require $_SERVER['DOCUMENT_ROOT'].'/main.inc.php';
require_once __DIR__.'/class/OfficeExpenseStaffRuntime.php';
require_once __DIR__.'/class/OfficeExpenseWorkspaceRuntime.php';
require_once __DIR__.'/class/OfficeExpenseStaffController.php';
require_once __DIR__.'/class/OfficeExpenseStaffPage.php';
header('Cache-Control: private, no-store');header('X-Content-Type-Options: nosniff');header('Referrer-Policy: same-origin');
try{
    if(!OfficeExpenseStaffRuntime::enabled($conf))throw new DomainException('Staff access is not activated.');
    $pilot=OfficeExpensePilotConfiguration::read(OfficeExpensePilotConfiguration::PATH,DOL_DOCUMENT_ROOT);
    $access=OfficeExpenseStaffRuntime::access($db,$user,$conf,$pilot);
    $method=(string)($_SERVER['REQUEST_METHOD']??'GET');
    $nativeLogin=$method==='POST'&&($_POST['actionlogin']??null)==='login'&&is_string($_POST['username']??null)&&is_string($_POST['password']??null)&&($_SESSION['dol_login']??null)===($user->login??null);
    if($nativeLogin&&!empty($_FILES)){http_response_code(415);exit;}
    $result=(new OfficeExpenseStaffController($access,(string)currentToken()))->handle($nativeLogin?'GET':$method,$_GET??[],$nativeLogin?[]:($_POST??[]),$_FILES??[]);
    if($nativeLogin&&$result['status']===200){header('Location: users.php',true,303);exit;}
    $result['workspace_ready']=OfficeExpenseWorkspaceRuntime::ready($db,$conf);
    http_response_code($result['status']);if($result['redirect']!==null){header('Location: '.$result['redirect'],true,303);exit;}
    if($result['status']===405)header('Allow: GET, POST');
    $asset=DOL_URL_ROOT.'/custom/chinaplanetofficeexpenses/';
    $hasCsp=false;foreach(headers_list()as$header)if(stripos($header,'Content-Security-Policy:')===0)$hasCsp=true;
    if(!$hasCsp&&!defined('MAIN_SECURITY_FORCECSP')&&(!function_exists('getDolGlobalString')||getDolGlobalString('MAIN_SECURITY_FORCECSP')==='')){
        $nonce=function_exists('getNonce')?(string)getNonce():'';$source=$nonce!==''?" 'nonce-".$nonce."'":'';
        header("Content-Security-Policy: default-src 'none'; script-src 'self'".$source."; style-src 'self'".$source."; style-src-attr 'unsafe-inline'; manifest-src 'self'; font-src 'self' data:; img-src 'self' data:; connect-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'self'; object-src 'none'");
    }
    // This module supplies its own sidebar. Keep the native top bar and authentication.
    // Request-local only: never persist Dolibarr's hide-menu session option.
    $conf->dol_hide_leftmenu=1;
    llxHeader('',$result['language']==='ar'?'مستخدمي الشركة':'Company users','','',0,0,[],[$asset.'assets/fontawesome.css',$asset.'assets/fontawesome-solid.css',$asset.'employee.css?v='.substr(hash_file('sha256',__DIR__.'/employee.css'),0,12),$asset.'staff.css?v='.substr(hash_file('sha256',__DIR__.'/staff.css'),0,12)]);
    echo (new OfficeExpenseStaffPage())->render($result,(string)newToken());llxFooter();
}catch(DomainException $error){http_response_code(403);echo 'Staff access is not activated or not authorized.';}
catch(Throwable $error){http_response_code(503);echo 'Staff access is temporarily unavailable. No assignment was confirmed.';}
