<?php
define('CSRFCHECK_WITH_TOKEN',1);
require $_SERVER['DOCUMENT_ROOT'].'/main.inc.php';
require_once __DIR__.'/../lib/main.lib.php';
require_once __DIR__.'/../class/InternalTaskCollaborationService.class.php';
cpo_task_require($db,$user);$langs->load('chinaplanetoperations@chinaplanetoperations');
if($_SERVER['REQUEST_METHOD']!=='POST')accessforbidden();
$service=new InternalTaskCollaborationService($db,$user);$action=GETPOST('action','alpha');$taskId=GETPOSTINT('task_id');$ajax=strtolower((string)(isset($_SERVER['HTTP_X_REQUESTED_WITH'])?$_SERVER['HTTP_X_REQUESTED_WITH']:''))==='xmlhttprequest';$status=200;$result='success';$payload=array();
$intArray=function($name){$value=isset($_POST[$name])?$_POST[$name]:array();if(!is_array($value))$value=array($value);return array_values(array_filter(array_map('intval',$value),function($id){return $id>0;}));};
$raw=function($name){return isset($_POST[$name])&&!is_array($_POST[$name])?(string)$_POST[$name]:'';};
try{
    if($action==='comment_create'){$payload['comment']=$service->createComment($taskId,$raw('body'),$intArray('mention_ids'),GETPOST('request_key','alphanohtml'));$payload['can_redact']=$service->authorization()->isAdministrator();}
    elseif($action==='comment_redact')$payload['comment']=$service->redactComment($taskId,GETPOSTINT('comment_id'),GETPOSTINT('version'),$raw('reason'));
    elseif($action==='checklist_create')$payload['rowid']=$service->createChecklist($taskId,$raw('title'));
    elseif($action==='checklist_mutate')$service->mutateChecklist($taskId,GETPOSTINT('checklist_id'),GETPOSTINT('version'),GETPOST('mutation','alpha'),$raw('value'));
    elseif($action==='item_create')$payload['rowid']=$service->createItem($taskId,GETPOSTINT('checklist_id'),$raw('label'));
    elseif($action==='item_mutate')$service->mutateItem($taskId,GETPOSTINT('item_id'),GETPOSTINT('version'),GETPOST('mutation','alpha'),$raw('value'));
    elseif($action==='reorder')$payload['fingerprint']=$service->reorder($taskId,GETPOST('scope','alpha'),GETPOSTINT('parent_id'),$intArray('ids'),GETPOST('fingerprint','alphanohtml'));
    elseif($action==='labels_set')$payload['fingerprint']=$service->setTaskLabels($taskId,$intArray('label_ids'),GETPOST('fingerprint','alphanohtml'));
    elseif($action==='notification_read')$payload['taskId']=$service->markRead($taskId,GETPOSTINT('notification_id'));
    elseif($action==='label_save')$payload['rowid']=$service->saveLabel(GETPOSTINT('label_id'),GETPOSTINT('version'),array('code'=>GETPOST('code','alphanohtml'),'label_en'=>$raw('label_en'),'label_ar'=>$raw('label_ar'),'label_zh'=>$raw('label_zh'),'color'=>GETPOST('color','alphanohtml')));
    elseif($action==='label_active')$service->setLabelActive(GETPOSTINT('label_id'),GETPOSTINT('version'),GETPOSTINT('active')===1);
    else throw new RuntimeException('Unsupported collaboration action.');
}catch(InternalTaskConflictException $e){$status=409;$result='stale';}
catch(InternalTaskAccessException $e){$status=403;$result='denied';}
catch(Throwable $e){$status=422;$result='validation';}
$messageKey=$status===200?'InternalTaskCollaborationSaved':($status===409?'InternalTaskStale':($status===403?'InternalTaskSafeDenied':'InternalTaskValidationError'));
if($ajax){http_response_code($status);header('Content-Type: application/json; charset=utf-8');header('Cache-Control: private, no-store');echo json_encode(array_merge(array('ok'=>$status===200,'result'=>$result,'message'=>$langs->trans($messageKey)),$payload));exit;}
if($action==='notification_read'&&!empty($payload['taskId'])){header('Location: card.php?id='.(int)$payload['taskId']);exit;}
if(in_array($action,array('label_save','label_active'),true)){header('Location: labels.php?result='.$result);exit;}
header('Location: card.php?id='.(int)$taskId.(GETPOSTINT('embed')===1?'&embed=1':'').'&result='.$result);exit;
