<?php
define('CSRFCHECK_WITH_TOKEN',1);
require $_SERVER['DOCUMENT_ROOT'].'/main.inc.php';
require_once __DIR__.'/../lib/main.lib.php';
require_once __DIR__.'/../class/InternalTaskService.class.php';
cpo_task_require($db,$user);
$langs->load('chinaplanetoperations@chinaplanetoperations');
if($_SERVER['REQUEST_METHOD']!=='POST')accessforbidden();
$service=new InternalTaskService($db,$user);$action=GETPOST('action','aZ09');$taskId=GETPOSTINT('task_id');$version=GETPOSTINT('version');$embedded=GETPOSTINT('embed')===1;$result='success';
try{
 if($action==='create'){
  $requestId=GETPOST('request_id','alphanohtml');if($requestId==='')$requestId=substr(hash('sha256',(int)$user->id.':'.GETPOST('token','alphanohtml')),0,48);if(!preg_match('/^[a-f0-9]{48}$/',$requestId))throw new RuntimeException('Invalid task request.');
  $requestKey='create:'.(int)$user->id.':'.$requestId;if(!isset($_SESSION['cpo_m058_requests']))$_SESSION['cpo_m058_requests']=array();
  if(isset($_SESSION['cpo_m058_requests'][$requestKey]))$taskId=(int)$_SESSION['cpo_m058_requests'][$requestKey];
  else{$taskId=$service->createTask(array('title'=>GETPOST('title','alphanohtml'),'description'=>GETPOST('description','restricthtml'),'fk_usergroup'=>GETPOSTINT('fk_usergroup'),'fk_user_assign'=>GETPOSTINT('fk_user_assign'),'priority'=>GETPOST('priority','alpha'),'date_deadline'=>GETPOST('date_deadline','alphanohtml'),'request_hash'=>hash('sha256',(int)$conf->entity.':'.(int)$user->id.':'.$requestId)));$_SESSION['cpo_m058_requests'][$requestKey]=$taskId;if(count($_SESSION['cpo_m058_requests'])>100)$_SESSION['cpo_m058_requests']=array_slice($_SESSION['cpo_m058_requests'],-100,null,true);}
 }
 elseif($action==='update'){$service->updateTask($taskId,$version,array('title'=>GETPOST('title','alphanohtml'),'description'=>GETPOST('description','restricthtml'),'fk_usergroup'=>GETPOSTINT('fk_usergroup'),'fk_user_assign'=>GETPOSTINT('fk_user_assign'),'priority'=>GETPOST('priority','alpha'),'date_deadline'=>GETPOST('date_deadline','alphanohtml')));}
 elseif($action==='transition'){$service->transition($taskId,$version,GETPOST('target','alpha'),array('waiting_on'=>GETPOST('waiting_on','alpha'),'waiting_reason'=>GETPOST('waiting_reason','restricthtml'),'date_followup'=>GETPOST('date_followup','alphanohtml'),'reason'=>GETPOST('reason','restricthtml')));}
 elseif($action==='progress'){$service->updateProgress($taskId,$version,GETPOSTINT('progress'));}
 elseif($action==='archive'){$service->archive($taskId,$version,GETPOST('reason','restricthtml'));}
 elseif($action==='restore'){$service->restore($taskId,$version,GETPOST('reason','restricthtml'));}
 elseif($action==='delete'){$service->softDelete($taskId,$version,GETPOST('reason','restricthtml'));}
 else throw new RuntimeException('Invalid task action.');
 setEventMessages($langs->trans('TaskUpdated'),null,'mesgs');
}catch(InternalTaskConflictException $e){$result='stale';if($taskId>0)$_SESSION['cpo_m058_retry'][$taskId]=array('action'=>$action,'title'=>GETPOST('title','alphanohtml'),'description'=>GETPOST('description','restricthtml'),'fk_usergroup'=>GETPOSTINT('fk_usergroup'),'fk_user_assign'=>GETPOSTINT('fk_user_assign'),'priority'=>GETPOST('priority','alpha'),'date_deadline'=>GETPOST('date_deadline','alphanohtml'),'target'=>GETPOST('target','alpha'),'progress'=>GETPOSTINT('progress'),'waiting_on'=>GETPOST('waiting_on','alpha'),'waiting_reason'=>GETPOST('waiting_reason','restricthtml'),'date_followup'=>GETPOST('date_followup','alphanohtml'),'reason'=>GETPOST('reason','restricthtml'));setEventMessages($langs->trans('InternalTaskStale'),null,'warnings');}catch(InternalTaskAccessException $e){$result='denied';setEventMessages($langs->trans('InternalTaskSafeDenied'),null,'errors');}catch(Throwable $e){$result='validation';if($taskId>0)$_SESSION['cpo_m058_retry'][$taskId]=array('action'=>$action,'title'=>GETPOST('title','alphanohtml'),'description'=>GETPOST('description','restricthtml'),'fk_usergroup'=>GETPOSTINT('fk_usergroup'),'fk_user_assign'=>GETPOSTINT('fk_user_assign'),'priority'=>GETPOST('priority','alpha'),'date_deadline'=>GETPOST('date_deadline','alphanohtml'),'target'=>GETPOST('target','alpha'),'progress'=>GETPOSTINT('progress'),'waiting_on'=>GETPOST('waiting_on','alpha'),'waiting_reason'=>GETPOST('waiting_reason','restricthtml'),'date_followup'=>GETPOST('date_followup','alphanohtml'),'reason'=>GETPOST('reason','restricthtml'));setEventMessages($langs->trans('InternalTaskValidationError'),null,'errors');}
if($taskId>0&&$embedded&&$action==='delete'&&$result==='success'){header("Content-Security-Policy: frame-ancestors 'self'");echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head><body><main role="status">'.dol_escape_htmltag($langs->trans('TaskUpdated')).'</main><script>window.parent.postMessage({type:"cpo-task-mutated",taskId:'.(int)$taskId.'},window.location.origin);</script></body></html>';exit;}
if($taskId>0){header('Location: card.php?id='.(int)$taskId.($embedded?'&embed=1&result='.$result:''));exit;}
header('Location: board.php?view='.urlencode(GETPOST('return_view','alpha')));exit;
