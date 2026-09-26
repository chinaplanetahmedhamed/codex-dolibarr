<?php
define('CSRFCHECK_WITH_TOKEN', 1);
require $_SERVER['DOCUMENT_ROOT'].'/main.inc.php';
require_once __DIR__.'/../lib/main.lib.php';
require_once __DIR__.'/../class/InternalTaskService.class.php';
cpo_task_require($db, $user);
$langs->load('chinaplanetoperations@chinaplanetoperations');
if($_SERVER['REQUEST_METHOD']!=='POST')accessforbidden();

$service = new InternalTaskService($db, $user);
$action = GETPOST('action', 'aZ09');
$taskId = GETPOSTINT('task_id');
$version = GETPOSTINT('version');
$embedded = GETPOSTINT('embed') === 1;
$ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string)$_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
$result = 'success';
$status = 200;

$posted = array(
    'title'=>GETPOST('title','alphanohtml'), 'description'=>GETPOST('description','restricthtml'),
    'fk_usergroup'=>GETPOSTINT('fk_usergroup'), 'fk_user_assign'=>GETPOSTINT('fk_user_assign'),
    'priority'=>GETPOST('priority','alpha'), 'date_deadline'=>GETPOST('date_deadline','alphanohtml'),
    'target'=>GETPOST('target','alpha'), 'target_stage_id'=>GETPOSTINT('target_stage_id'),
    'progress'=>GETPOSTINT('progress'), 'waiting_on'=>GETPOST('waiting_on','alpha'),
    'waiting_reason'=>GETPOST('waiting_reason','restricthtml'), 'date_followup'=>GETPOST('date_followup','alphanohtml'),
    'reason'=>GETPOST('reason','restricthtml'),
);

try {
    if ($action === 'create') {
        $requestId = GETPOST('request_id','alphanohtml');
        if ($requestId === '') $requestId = substr(hash('sha256',(int)$user->id.':'.GETPOST('token','alphanohtml')),0,48);
        if (!preg_match('/^[a-f0-9]{48}$/',$requestId)) throw new RuntimeException('Invalid task request.');
        $requestKey = 'create:'.(int)$user->id.':'.$requestId;
        if (!isset($_SESSION['cpo_m058_requests'])) $_SESSION['cpo_m058_requests']=array();
        if (isset($_SESSION['cpo_m058_requests'][$requestKey])) $taskId=(int)$_SESSION['cpo_m058_requests'][$requestKey];
        else {
            $taskId=$service->createTask(array_merge($posted,array('request_hash'=>hash('sha256',(int)$conf->entity.':'.(int)$user->id.':'.$requestId))));
            $_SESSION['cpo_m058_requests'][$requestKey]=$taskId;
            if(count($_SESSION['cpo_m058_requests'])>100)$_SESSION['cpo_m058_requests']=array_slice($_SESSION['cpo_m058_requests'],-100,null,true);
        }
        unset($_SESSION['cpo_m059_create_retry']);
    } elseif ($action === 'rename') {
        $service->renameTask($taskId,$version,$posted['title']);
    } elseif ($action === 'trash') {
        $service->trashTask($taskId,$version,$posted['reason']);
    } elseif ($action === 'restore_trash') {
        $service->restoreTrash($taskId,$version,$posted['reason']);
    } elseif ($action === 'update') {
        $service->updateTask($taskId,$version,$posted);
    } elseif ($action === 'transition') {
        $service->transition($taskId,$version,$posted['target'],$posted);
    } elseif ($action === 'move_stage') {
        $service->moveToStage($taskId,$version,$posted['target_stage_id'],$posted);
    } elseif ($action === 'progress') {
        $service->updateProgress($taskId,$version,$posted['progress']);
    } elseif ($action === 'archive') {
        $service->archive($taskId,$version,$posted['reason']);
    } elseif ($action === 'cancel_archive') {
        $service->cancelAndArchive($taskId,$version,$posted['reason']);
    } elseif ($action === 'restore_cancelled') {
        $service->restoreCancelled($taskId,$version,$posted['reason']);
    } elseif ($action === 'restore') {
        $service->restore($taskId,$version,$posted['reason']);
    } elseif ($action === 'delete') {
        $service->softDelete($taskId,$version,$posted['reason']);
    } else throw new RuntimeException('Invalid task action.');
    setEventMessages($langs->trans($action==='create'?'TaskCreated':'TaskUpdated'),null,'mesgs');
} catch (InternalTaskConflictException $e) {
    $result='stale'; $status=409;
    if ($taskId>0) $_SESSION['cpo_m058_retry'][$taskId]=array_merge(array('action'=>$action),$posted);
    elseif ($action==='create') $_SESSION['cpo_m059_create_retry']=$posted;
    setEventMessages($langs->trans('InternalTaskStale'),null,'warnings');
} catch (InternalTaskAccessException $e) {
    $result='denied'; $status=403;
    setEventMessages($langs->trans('InternalTaskSafeDenied'),null,'errors');
} catch (Throwable $e) {
    $result='validation'; $status=422;
    if ($taskId>0) $_SESSION['cpo_m058_retry'][$taskId]=array_merge(array('action'=>$action),$posted);
    elseif ($action==='create') $_SESSION['cpo_m059_create_retry']=$posted;
    setEventMessages($langs->trans('InternalTaskValidationError'),null,'errors');
}

if ($ajax) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: private, no-store');
    echo json_encode(array('ok'=>$status===200,'result'=>$result,'taskId'=>(int)$taskId,'message'=>$langs->trans($status===200?'TaskUpdated':($status===409?'InternalTaskStale':($status===403?'InternalTaskSafeDenied':'InternalTaskValidationError')))));
    exit;
}
if($taskId>0&&$embedded&&in_array($action,array('delete','trash'),true)&&$result==='success'){
    header("Content-Security-Policy: frame-ancestors 'self'");
    echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head><body><main role="status">'.dol_escape_htmltag($langs->trans('TaskUpdated')).'</main><script>window.parent.postMessage({type:"cpo-task-mutated",taskId:'.(int)$taskId.'},window.location.origin);</script></body></html>';exit;
}
if($action==='trash'&&$result==='success'){header('Location: trash.php');exit;}
if($action==='restore_trash'&&$result!=='success'){header('Location: trash.php');exit;}
if ($action==='create') {
    $view=$service->authorization()->normalizeView(GETPOST('return_view','alpha'));
    $query=array('view'=>$view);
    foreach(array('q','status','priority','due','team_id','assignee_id','operation_id') as $key){$value=GETPOST('return_'.$key,'alphanohtml');if($value!=='')$query[$key]=$value;}
    if($result!=='success')$query['create_result']=$result;
    header('Location: board.php?'.http_build_query($query));exit;
}
if($taskId>0){header('Location: card.php?id='.(int)$taskId.($embedded?'&embed=1&result='.$result:''));exit;}
header('Location: board.php');exit;
