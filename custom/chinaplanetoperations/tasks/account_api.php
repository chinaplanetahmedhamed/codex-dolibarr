<?php
if($_SERVER['REQUEST_METHOD']==='POST')define('CSRFCHECK_WITH_TOKEN',1);
require $_SERVER['DOCUMENT_ROOT'].'/main.inc.php';
require_once __DIR__.'/../lib/account_bootstrap.lib.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
try {
    if(GETPOST('errorcode','aZ09')==='InvalidToken')throw new RuntimeException('Session token expired. Reload and retry.',403);
    $service=cpo_account_service($db,$user,$conf);$customer=GETPOSTINT('customer');
    if($_SERVER['REQUEST_METHOD']!=='POST')throw new RuntimeException('Use POST for account commands.',405);
    $raw=GETPOST('payload','none');if(!is_string($raw)||strlen($raw)>100000)throw new InvalidArgumentException('Invalid or oversized payload.');
    $d=json_decode($raw,true,64,JSON_THROW_ON_ERROR);if(!is_array($d))throw new InvalidArgumentException('Invalid payload.');
    $result=$service->mutate(GETPOST('action','aZ09'),$customer,$d,GETPOST('request','alphanohtml'));
    echo json_encode(['ok'=>true,'data'=>$result],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR);
} catch(Throwable $e){$code=(int)$e->getCode();if($e instanceof InternalTaskAccessException)$code=403;if($e instanceof InvalidArgumentException||$e instanceof JsonException)$code=422;if(!in_array($code,[403,409,405,422],true))$code=500;http_response_code($code);echo json_encode(['ok'=>false,'error'=>$code===403?'Finance or native scope access denied.':($code===500?'Save outcome could not be confirmed. Retry the same command.':$e->getMessage())],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT);}
$db->close();
