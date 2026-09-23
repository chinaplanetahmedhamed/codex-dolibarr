<?php
define('CSRFCHECK_WITH_TOKEN',1);
require $_SERVER['DOCUMENT_ROOT'].'/main.inc.php';
require_once __DIR__.'/../lib/main.lib.php';
require_once __DIR__.'/../class/InternalTaskPersonalBoard.class.php';
require_once __DIR__.'/../class/InternalTaskDetailsService.class.php';
cpo_task_require($db,$user);$langs->load('chinaplanetoperations@chinaplanetoperations');
if($_SERVER['REQUEST_METHOD']!=='POST')accessforbidden();
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: private, no-store');
$status=200;$result=array();
try {
    $raw=isset($_POST['payload'])&&is_string($_POST['payload'])?$_POST['payload']:'';
    if(strlen($raw)>100000)throw new RuntimeException('Payload too large.');
    $data=json_decode($raw,true);if(!is_array($data))throw new RuntimeException('Invalid payload.');
    $action=GETPOST('action','aZ09');$version=GETPOSTINT('version');
    if($action==='columns')$result['board']=(new InternalTaskPersonalBoard($db,$user))->saveColumns($version,$data);
    elseif($action==='move')$result['board']=(new InternalTaskPersonalBoard($db,$user))->move($version,GETPOSTINT('task_id'),(int)($data['column']??0),(int)($data['before']??0));
    elseif($action==='details')$result['details']=(new InternalTaskDetailsService($db,$user))->save(GETPOSTINT('task_id'),$version,$data);
    elseif($action==='compose'){
        $task=$data['task']??array();$extra=$data['details']??array();$items=$data['items']??array();if(!is_array($task)||!is_array($extra)||!is_array($items))throw new RuntimeException('Invalid task.');
        $task=array_intersect_key($task,array_flip(array('title','description','priority','date_deadline','fk_usergroup','fk_user_assign')));foreach($task as $value)if(!is_scalar($value))throw new RuntimeException('Invalid task field.');
        $result['taskId']=(new InternalTaskDetailsService($db,$user))->compose($task,$extra,$items,$data['comment']??'',GETPOST('request_id','alphanohtml'),(int)($data['column']??0));
        $saved=(new InternalTaskService($db,$user))->requireReadableTask($result['taskId']);$result['version']=(int)$saved['version'];
    }else throw new RuntimeException('Invalid action.');
}catch(InternalTaskConflictException $e){$status=409;}
catch(InternalTaskAccessException $e){$status=403;}
catch(Throwable $e){$status=422;error_log('CPO personal task rejected: '.get_class($e));}
http_response_code($status);$result['ok']=$status===200;$result['message']=$langs->trans($status===200?'TaskUpdated':($status===409?'InternalTaskStale':($status===403?'InternalTaskSafeDenied':'InternalTaskValidationError')));echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_HEX_TAG);exit;
