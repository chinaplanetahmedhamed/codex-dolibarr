<?php
declare(strict_types=1);
define('CSRFCHECK_WITH_TOKEN',1);
require $_SERVER['DOCUMENT_ROOT'].'/main.inc.php';
require_once __DIR__.'/class/OfficeExpenseEmployeeController.php';
require_once __DIR__.'/class/OfficeExpenseEmployeePage.php';
require_once __DIR__.'/class/OfficeExpenseNativeStorageFactory.php';
require_once __DIR__.'/class/OfficeExpensePilotConfiguration.php';
header('Cache-Control: private, no-store');header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'; style-src 'self'; script-src 'self'; font-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
header('Referrer-Policy: same-origin');header('Content-Type: text/html; charset=UTF-8');
// Trusted native bootstrap may supply a factory. Otherwise only the explicitly
// provisioned administrator-only staging pilot can bind native storage.
$factory=$GLOBALS['cpoOfficeExpenseDraftFactory']??null;
try {
    $receiptFactory=$GLOBALS['cpoOfficeExpenseReceiptFactory']??null;
    if (!is_callable($factory)) {
        $pilot=OfficeExpensePilotConfiguration::read(OfficeExpensePilotConfiguration::PATH,DOL_DOCUMENT_ROOT);
        $offices=$pilot->scope($user,$conf);
        $binding=new OfficeExpenseNativeStorageFactory($db,$user,$conf,$offices,$pilot->privateRoot(),DOL_DOCUMENT_ROOT,array(),10,26214400,$pilot->precision());
        $factory=array($binding,'draftFactory');$receiptFactory=array($binding,'receiptFactory');
    }
    $service=$factory($db,$user,$conf);
    if (!$service instanceof OfficeExpenseDraftService || !$service->usesNativeContext($user,(int)$conf->entity)) throw new RuntimeException('Native context mismatch.');
    $language=strpos((string)($langs->defaultlang??'en_US'),'ar')===0?'ar':'en';
    $receipts=null;
    if(is_callable($receiptFactory)){
        $receipts=$receiptFactory($db,$user,$conf,$service);
        if(!$receipts instanceof OfficeExpenseReceiptService||!$receipts->usesDraftService($service))throw new RuntimeException('Receipt context mismatch.');
    }
    $controller=new OfficeExpenseEmployeeController($service,(string)currentToken(),(string)newToken(),$language,$receipts);
    $method=(string)($_SERVER['REQUEST_METHOD']??'GET');
    // Native main.inc.php authenticates a direct-to-module login POST before reaching this page.
    // Authorize its read view, then redirect so login credentials are never treated as a draft save.
    $nativeLoginPost=$method==='POST' && ($_POST['actionlogin']??null)==='login'
        && is_string($_POST['username']??null) && is_string($_POST['password']??null)
        && !empty($user->id) && isset($_SESSION['dol_login']) && $_SESSION['dol_login']===($user->login??null);
    if($nativeLoginPost&&!empty($_FILES)){http_response_code(415);echo 'Login does not accept attachments.';exit;}
    $result=$controller->handle($nativeLoginPost?'GET':$method,$_GET??array(),$nativeLoginPost?array():($_POST??array()),$_FILES??array());
    if ($nativeLoginPost && $result['status']===200) {
        $result['status']=303;$result['redirect']='index.php?lang='.$result['language'].'&view='.$result['view'];
    }
    http_response_code($result['status']);
    if ($result['redirect']!==null) {header('Location: '.$result['redirect'],true,303);exit;}
    if($result['download']!==null){foreach(OfficeExpenseReceiptHttpResponse::headers($result['download'],$result['download_id']) as $name=>$value)header($name.': '.$value);echo $result['download']['bytes'];exit;}
    if ($result['status']===405) header('Allow: GET, POST');
    echo (new OfficeExpenseEmployeePage())->render($result,$result['language'],(string)newToken());
} catch (OfficeExpensePilotAccessDenied $error) {
    http_response_code(403);echo 'Office expenses pilot is restricted to its approved administrator.';
} catch (Throwable $error) {
    http_response_code(503);echo 'Office expenses is temporarily unavailable. No draft has been submitted.';
}
