<?php
define('CSRFCHECK_WITH_TOKEN',1);
require $_SERVER['DOCUMENT_ROOT'].'/main.inc.php';
require_once __DIR__.'/../lib/main.lib.php';
require_once __DIR__.'/../class/InternalTaskStageService.class.php';
cpo_task_require($db,$user);$langs->load('chinaplanetoperations@chinaplanetoperations');
if($_SERVER['REQUEST_METHOD']!=='POST')accessforbidden();
$service=new InternalTaskStageService($db,$user,(int)$conf->entity);$action=GETPOST('action','alpha');
try{
 if($action==='create')$service->createWorkStage(array('code'=>GETPOST('code','alphanohtml'),'label_en'=>GETPOST('label_en','alphanohtml'),'label_ar'=>GETPOST('label_ar','alphanohtml'),'label_zh'=>GETPOST('label_zh','alphanohtml'),'color'=>GETPOST('color','alphanohtml')),GETPOST('config_version','alphanohtml'));
 elseif($action==='update')$service->updateStage(GETPOSTINT('stage_id'),GETPOSTINT('version'),array('label_en'=>GETPOST('label_en','alphanohtml'),'label_ar'=>GETPOST('label_ar','alphanohtml'),'label_zh'=>GETPOST('label_zh','alphanohtml'),'color'=>GETPOST('color','alphanohtml')));
 elseif($action==='toggle')$service->setActive(GETPOSTINT('stage_id'),GETPOSTINT('version'),GETPOSTINT('active')===1,GETPOST('config_version','alphanohtml'));
 elseif($action==='reorder'){$ids=isset($_POST['stage_ids'])&&is_array($_POST['stage_ids'])?array_values(array_map('intval',$_POST['stage_ids'])):array();$move=GETPOSTINT('move_id');$index=array_search($move,$ids,true);$direction=GETPOST('direction','alpha');if($index===false)throw new RuntimeException('Invalid task stage order.');$swap=$direction==='up'?$index-1:$index+1;if($swap>=0&&$swap<count($ids)){$tmp=$ids[$index];$ids[$index]=$ids[$swap];$ids[$swap]=$tmp;}$service->reorder($ids,GETPOST('config_version','alphanohtml'));}
 else throw new RuntimeException('Invalid task stage action.');
 setEventMessages($langs->trans('TaskUpdated'),null,'mesgs');
}catch(InternalTaskConflictException $e){setEventMessages($langs->trans('InternalTaskStale'),null,'warnings');}catch(InternalTaskAccessException $e){accessforbidden();}catch(Throwable $e){setEventMessages($langs->trans('InternalTaskColumnValidationError'),null,'errors');}
header('Location: stages.php');exit;
