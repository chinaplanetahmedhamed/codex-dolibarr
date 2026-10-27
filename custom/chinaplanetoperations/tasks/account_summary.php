<?php
require $_SERVER['DOCUMENT_ROOT'].'/main.inc.php';
require_once __DIR__.'/../lib/account_bootstrap.lib.php';
require_once __DIR__.'/../class/CustomerAccountSummary.class.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: private, no-store');
try{
    $service=cpo_account_service($db,$user,$conf); // Finance right checked before scopes or totals are read.
    $raw=GETPOST('scopes','restricthtml');
    if(!is_string($raw)||strlen($raw)>700||!preg_match('/^[0-9]{1,10}:[0-9]{1,10}:[0-9]{1,10}(,[0-9]{1,10}:[0-9]{1,10}:[0-9]{1,10}){0,19}$/D',$raw))throw new InvalidArgumentException('Invalid scope list.');
    $scopes=array_map(function($scope){$ids=array_map('intval',explode(':',$scope));return ['customer'=>$ids[0],'project'=>$ids[1],'task'=>$ids[2]];},explode(',',$raw));
    $rows=(new CustomerAccountSummary($service))->batch($scopes);echo json_encode(['ok'=>true,'summaries'=>$rows],JSON_THROW_ON_ERROR);
}catch(Throwable $e){http_response_code($e instanceof InvalidArgumentException||$e instanceof JsonException?422:403);echo json_encode(['ok'=>false,'error'=>'Account summaries unavailable.']);}
$db->close();
