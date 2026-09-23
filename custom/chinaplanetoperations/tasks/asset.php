<?php
if($_SERVER['REQUEST_METHOD']==='POST')define('CSRFCHECK_WITH_TOKEN',1);
require $_SERVER['DOCUMENT_ROOT'].'/main.inc.php';
require_once __DIR__.'/../lib/main.lib.php';
require_once __DIR__.'/../class/InternalTaskAssetService.class.php';
cpo_task_require($db,$user);$langs->load('chinaplanetoperations@chinaplanetoperations');$service=new InternalTaskAssetService($db,$user);$action=GETPOST('action','alpha');
if($_SERVER['REQUEST_METHOD']==='POST'){
 $ajax=!empty($_SERVER['HTTP_X_REQUESTED_WITH'])&&strtolower((string)$_SERVER['HTTP_X_REQUESTED_WITH'])==='xmlhttprequest';$status=200;$message=$langs->trans('TaskUpdated');$newVersion=0;
 try{if($action==='upload'){$uploaded=$service->upload(GETPOSTINT('task_id'),GETPOSTINT('version'),isset($_FILES['task_file'])?$_FILES['task_file']:array(),GETPOSTINT('replace_asset_id'));$newVersion=(int)$uploaded['version'];}elseif($action==='cover')$newVersion=$service->setCover(GETPOSTINT('task_id'),GETPOSTINT('asset_id'),GETPOSTINT('version'));elseif($action==='cover_clear')$newVersion=$service->clearCover(GETPOSTINT('task_id'),GETPOSTINT('version'));else accessforbidden();setEventMessages($message,null,'mesgs');}catch(InternalTaskConflictException $e){$status=409;$message=$langs->trans('InternalTaskStale');setEventMessages($message,null,'warnings');}catch(InternalTaskAccessException $e){$status=403;$message=$langs->trans('InternalTaskSafeDenied');setEventMessages($message,null,'errors');}catch(Throwable $e){error_log('CPO internal task asset rejected: '.get_class($e).' - '.$e->getMessage());$status=422;$message=$langs->trans('InternalTaskValidationError');setEventMessages($message,null,'errors');}
 if($ajax){http_response_code($status);header('Content-Type: application/json; charset=utf-8');header('Cache-Control: private, no-store');echo json_encode(array('ok'=>$status===200,'message'=>$message,'version'=>$newVersion,'assetId'=>isset($uploaded)?(int)$uploaded['asset_id']:0));exit;}
 header('Location: card.php?id='.GETPOSTINT('task_id').(GETPOSTINT('embed')===1?'&embed=1&result='.($status===200?'success':($status===409?'stale':($status===403?'denied':'validation'))):''));exit;
}
try{$history=GETPOSTINT('history')===1;$asset=$service->getAsset(GETPOSTINT('asset_id'),$history);$path=$service->pathFor($asset);}catch(Throwable $e){accessforbidden();}
header('X-Content-Type-Options: nosniff');header('Cache-Control: private, no-store, max-age=0');header('Pragma: no-cache');header('Content-Length: '.filesize($path));
if($action==='preview'&&$asset['asset_type']==='image'){header('Content-Type: '.$asset['mime_type']);header("Content-Security-Policy: default-src 'none'; img-src 'self'; sandbox");header('Content-Disposition: inline');}
elseif($action==='download'){header('Content-Type: application/octet-stream');$safe=preg_replace('/[^A-Za-z0-9._-]/','_',str_replace(array("\r","\n"),'',(string)$asset['original_name']));header("Content-Disposition: attachment; filename=\"".$safe."\"; filename*=UTF-8''".rawurlencode((string)$asset['original_name']));}
else accessforbidden();
readfile($path);exit;
