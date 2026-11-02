<?php
declare(strict_types=1);
define('CSRFCHECK_WITH_TOKEN',1);
require $_SERVER['DOCUMENT_ROOT'].'/main.inc.php';
require_once __DIR__.'/class/OfficeExpenseEmployeeController.php';
require_once __DIR__.'/class/OfficeExpenseEmployeePage.php';
require_once __DIR__.'/class/OfficeExpenseNativeStorageFactory.php';
require_once __DIR__.'/class/OfficeExpensePilotConfiguration.php';
require_once __DIR__.'/class/OfficeExpenseNativeMenuSyncService.php';
require_once __DIR__.'/class/OfficeExpenseModuleAccess.php';
require_once __DIR__.'/class/OfficeExpenseStaffRuntime.php';
require_once __DIR__.'/class/OfficeExpenseWorkspaceRuntime.php';
header('Cache-Control: private, no-store');header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');header('Content-Type: text/html; charset=UTF-8');
// Trusted native bootstrap may supply a factory. Otherwise only the explicitly
// provisioned administrator-only staging pilot can bind native storage.
$factory=$GLOBALS['cpoOfficeExpenseDraftFactory']??null;
try {
    if(!OfficeExpenseModuleAccess::allows($user,$conf)){http_response_code(403);echo 'Office Expenses module is disabled or access is not authorized.';exit;}
    $receiptFactory=$GLOBALS['cpoOfficeExpenseReceiptFactory']??null;
    if (!is_callable($factory)) {
        $pilot=OfficeExpensePilotConfiguration::read(OfficeExpensePilotConfiguration::PATH,DOL_DOCUMENT_ROOT);
        $staffAccess=OfficeExpenseStaffRuntime::enabled($conf)?OfficeExpenseStaffRuntime::access($db,$user,$conf,$pilot):null;
        $offices=$staffAccess!==null?$staffAccess->offices():$pilot->scope($user,$conf);
        if($staffAccess!==null&&!$offices)throw new OfficeExpenseStaffAccessDenied('No assigned branch.');
        $binding=new OfficeExpenseNativeStorageFactory($db,$user,$conf,$offices,$pilot->privateRoot(),DOL_DOCUMENT_ROOT,array(),10,26214400,$pilot->precision(),$staffAccess);
        $factory=array($binding,'draftFactory');$receiptFactory=array($binding,'receiptFactory');
    }
    $service=$factory($db,$user,$conf);
    if (!$service instanceof OfficeExpenseDraftService || !$service->usesNativeContext($user,(int)$conf->entity)) throw new RuntimeException('Native context mismatch.');
    $language=strpos((string)($langs->defaultlang??'en_US'),'ar')===0?'ar':'en';
    $langs->load('chinaplanetofficeexpenses@chinaplanetofficeexpenses');
    $receipts=null;
    if(is_callable($receiptFactory)){
        $receipts=$receiptFactory($db,$user,$conf,$service);
        if(!$receipts instanceof OfficeExpenseReceiptService||!$receipts->usesDraftService($service))throw new RuntimeException('Receipt context mismatch.');
    }
    $controller=new OfficeExpenseEmployeeController($service,(string)currentToken(),(string)newToken(),$language,$receipts);
    $method=(string)($_SERVER['REQUEST_METHOD']??'GET');
    $menuSync=new OfficeExpenseNativeMenuSyncService($db,$user,$conf);
    $staffMode=OfficeExpenseStaffRuntime::enabled($conf);
    $menuSyncAvailable=!$staffMode&&$menuSync->isAuthorized()&&!$menuSync->hasMenu();
    if($method==='POST'&&GETPOST('action','aZ09')==='sync_office_menu'){
        $submittedToken=GETPOST('token','alphanohtml');
        $expectedToken=(string)currentToken();
        if($staffMode||!$menuSync->isAuthorized()||!is_string($submittedToken)||$submittedToken===''||$expectedToken===''||!hash_equals($expectedToken,$submittedToken)||!empty($_FILES)){
            http_response_code(403);echo 'Office Expenses menu synchronization is not authorized.';exit;
        }
        try{$menuCreated=$menuSync->synchronize();}
        catch(Throwable $error){dol_syslog('Office Expenses menu synchronization failed: '.$error->getMessage(),LOG_ERR);throw $error;}
        if(function_exists('setEventMessages')){
            $message=$menuCreated?'OfficeExpensesMenuAdded':'OfficeExpensesMenuAlreadyPresent';
            setEventMessages($langs->trans($message),null,'mesgs');
        }
        header('Location: index.php?lang='.$language,true,303);exit;
    }
    // Native main.inc.php authenticates a direct-to-module login POST before reaching this page.
    // Authorize its read view, then redirect so login credentials are never treated as a draft save.
    $nativeLoginPost=$method==='POST' && ($_POST['actionlogin']??null)==='login'
        && is_string($_POST['username']??null) && is_string($_POST['password']??null)
        && !empty($user->id) && isset($_SESSION['dol_login']) && $_SESSION['dol_login']===($user->login??null);
    if($nativeLoginPost&&!empty($_FILES)){http_response_code(415);echo 'Login does not accept attachments.';exit;}
    $result=$controller->handle($nativeLoginPost?'GET':$method,$_GET??array(),$nativeLoginPost?array():($_POST??array()),$_FILES??array());
    if(isset($staffAccess)&&$staffAccess!==null){$result['staff_mode']=true;try{$result['manage_staff']=(bool)$staffAccess->managedOffices();}catch(DomainException $e){$result['manage_staff']=false;}}
    if ($nativeLoginPost && $result['status']===200) {
        $result['status']=303;$result['redirect']='index.php?lang='.$result['language'].'&view='.$result['view'];
    }
    $result['workspace_ready']=OfficeExpenseWorkspaceRuntime::ready($db,$conf);
    if($method==='GET'&&$result['status']===200&&$result['view']==='overview'&&$result['workspace_ready']&&isset($staffAccess)&&$staffAccess!==null){
        $dashboardOffice=is_string($_GET['office']??null)&&isset($result['offices'][$_GET['office']])?$_GET['office']:array_key_first($result['offices']);
        if($dashboardOffice!==null){header('Location: workspace.php?'.http_build_query(['lang'=>$result['language'],'office'=>$dashboardOffice,'view'=>'overview']),true,303);exit;}
    }
    http_response_code($result['status']);
    if ($result['redirect']!==null) {header('Location: '.$result['redirect'],true,303);exit;}
    if($result['download']!==null){foreach(OfficeExpenseReceiptHttpResponse::headers($result['download'],$result['download_id']) as $name=>$value)header($name.': '.$value);echo $result['download']['bytes'];exit;}
    if ($result['status']===405) header('Allow: GET, POST');
    $page=(new OfficeExpenseEmployeePage())->render($result,$result['language'],(string)newToken(),false,$menuSyncAvailable);
    $assetRoot=DOL_URL_ROOT.'/custom/chinaplanetofficeexpenses/';
    $styleVersion=substr(hash_file('sha256',__DIR__.'/employee.css'),0,12);
    $mainmenu='chinaplanetexpenses';$leftmenu='officeexpenses';
    $hasCsp=false;
    foreach(headers_list() as $responseHeader){
        if(stripos($responseHeader,'Content-Security-Policy:')===0){$hasCsp=true;break;}
    }
    $forcedCsp=defined('MAIN_SECURITY_FORCECSP')
        || (function_exists('getDolGlobalString')&&getDolGlobalString('MAIN_SECURITY_FORCECSP')!=='');
    if(!$hasCsp&&!$forcedCsp){
        $nonce=function_exists('getNonce')?(string)getNonce():'';
        $nonceSource=$nonce!==''?" 'nonce-".$nonce."'":'';
        header("Content-Security-Policy: default-src 'none'; script-src 'self'".$nonceSource."; style-src 'self'".$nonceSource."; style-src-attr 'unsafe-inline'; font-src 'self' data:; img-src 'self' data:; connect-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'self'; object-src 'none'");
    }
    // This module supplies its own sidebar. Keep the native top bar and authentication.
    // Request-local only: never persist Dolibarr's hide-menu session option.
    $conf->dol_hide_leftmenu=1;
    llxHeader('', $langs->trans('OfficeExpenses'), '', '', 0, 0, array($assetRoot.'workspace.js?v='.substr(hash_file('sha256',__DIR__.'/workspace.js'),0,12)), array(
        $assetRoot.'assets/fontawesome.css',
        $assetRoot.'assets/fontawesome-solid.css',
        $assetRoot.'employee.css?v='.$styleVersion,
    ));
    echo $page;
    llxFooter();
} catch (OfficeExpensePilotAccessDenied $error) {
    http_response_code(403);echo 'Office expenses pilot is restricted to its approved administrator.';
} catch (OfficeExpenseStaffAccessDenied $error) {
    http_response_code(403);echo 'Office expenses branch access is not authorized.';
} catch (Throwable $error) {
    http_response_code(503);echo 'Office expenses is temporarily unavailable. No draft has been submitted.';
}
