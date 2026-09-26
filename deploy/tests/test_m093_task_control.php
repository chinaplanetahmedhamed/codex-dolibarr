<?php
if(PHP_SAPI!=='cli'||getenv('M091_LOCAL_RUNTIME')!=='1')exit(1);
require getenv('M091_QA_CONFIG');
if(!preg_match('/^m091_qa_[a-f0-9]{10}$/D',$dolibarr_main_db_name)||$dolibarr_main_db_host!=='127.0.0.1')throw new RuntimeException('Synthetic local database required.');
$_SERVER['DOCUMENT_ROOT']=$dolibarr_main_document_root;$_SERVER['SERVER_NAME']='127.0.0.1';$_SERVER['HTTP_HOST']='127.0.0.1:8101';
require $dolibarr_main_document_root.'/master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/chinaplanetoperations/class/InternalTaskDetailsService.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/chinaplanetoperations/class/InternalTaskAgendaService.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/chinaplanetoperations/lib/task_experience.lib.php';
function actor93($id){global $db;$u=new User($db);$u->fetch($id);$u->loadRights();return $u;}
$n=0;function ok93($yes,$text){global $n;if(!$yes)throw new RuntimeException('FAIL '.$text);echo 'PASS '.(++$n).' '.$text."\n";}
function no93($fn,$class,$text){try{$fn();}catch(Throwable $e){ok93($e instanceof $class,$text);return;}ok93(false,$text);}
function ids93($rows){return array_map('intval',array_column($rows,'rowid'));}
class Failing93Db{private $db;private $prefix;public $fired=false;public function __construct($db,$prefix){$this->db=$db;$this->prefix=$prefix;}public function __call($name,$args){return $this->db->$name(...$args);}public function query($sql){if(strpos($sql,$this->prefix)===0){$this->fired=true;return false;}return $this->db->query($sql);}}
$worker=actor93(2);$other=actor93(3);$manager=actor93(1);$manager->rights->chinaplanetoperations=(object)array('task_archive'=>1);
$s=new InternalTaskService($db,$worker,1);$m=new InternalTaskService($db,$manager,1);$d=new InternalTaskDetailsService($db,$worker,1);
$base=array('title'=>'M093 synthetic control','description'=>'Original details','priority'=>'normal','fk_usergroup'=>1,'fk_user_assign'=>2,'date_deadline'=>'2026-09-29T17:30');
$id=$d->compose($base,array('start_date'=>'2026-09-29','start_time'=>'09:15','notes'=>'Keep rich notes'),array(),'',bin2hex(random_bytes(24)));$created=array($id);
try{
    $db->query("UPDATE ".MAIN_DB_PREFIX."cpo_task SET date_creation='2000-01-01 00:00:00' WHERE rowid=".$id);
    $t=$s->getTask($id);$v=(int)$t['version'];
    ok93($t['creator_login']===$worker->login&&$t['owner_login']===$worker->login,'creator and assignee resolved on old own task');
    $v=$s->renameTask($id,$v,'Renamed shipping follow-up');$fresh=(new InternalTaskService($db,actor93(2),1))->getTask($id);
    ok93($fresh['title']==='Renamed shipping follow-up','renamed old task persists after service reload');
    foreach(array('description','priority','fk_usergroup','fk_user_assign','fk_user_creat','date_deadline','date_creation') as $key)ok93($t[$key]===$fresh[$key],'rename preserves '.$key);
    no93(function()use($s,$id,$v){$s->renameTask($id,$v-1,'Stale');},InternalTaskConflictException::class,'stale rename denied');
    no93(function()use($s,$id,$v){$s->renameTask($id,$v,'');},RuntimeException::class,'empty rename denied');
    no93(function()use($db,$other,$id,$v){(new InternalTaskService($db,$other,1))->renameTask($id,$v,'Foreign');},InternalTaskAccessException::class,'foreign worker rename denied');
    $v=$s->updateTask($id,$v,array_merge($base,array('title'=>'Edited task','description'=>'Updated details','priority'=>'high')));
    ok93($s->getTask($id)['description']==='Updated details'&&$s->getTask($id)['priority']==='high','information edit persists');
    no93(function()use($s,$id,$v,$base){$s->updateTask($id,$v,array_merge($base,array('fk_user_assign'=>3)));},InternalTaskAccessException::class,'own edit cannot reassign task');
    $assigned=$m->createTask($base);$created[]=$assigned;$a=$s->getTask($assigned);
    ok93($a['creator_login']===$manager->login&&$a['owner_login']===$worker->login,'manager creator and staff assignee shown separately');
    foreach(array('renameTask','trashTask','cancelAndArchive') as $method)no93(function()use($s,$assigned,$method){$s->$method($assigned,1,'Denied');},InternalTaskAccessException::class,'staff cannot '.$method.' manager-assigned task');
    $mv=$m->renameTask($assigned,1,'Manager changed assignment');$mv=$m->trashTask($assigned,$mv,'Manager removed work');$deleted=$m->getTask($assigned,false,true);
    ok93(!$s->authorization()->canRestoreTrash($deleted),'worker cannot restore manager-deleted task');
    $m->restoreTrash($assigned,$mv,'Manager resumes');
    $v=$s->transition($id,$v,'in_progress');$v=$s->updateProgress($id,$v,42);
    $before=$s->getTask($id);$details=$d->get($id);
    $fault=new Failing93Db($db,'INSERT INTO '.MAIN_DB_PREFIX.'cpo_activity');
    no93(function()use($fault,$worker,$id,$v){(new InternalTaskService($fault,$worker,1))->trashTask($id,$v,'Fail after write');},RuntimeException::class,'audit failure aborts trash');
    ok93($fault->fired&&$s->getTask($id)['status']==='in_progress'&&(int)$s->getTask($id)['version']===$v,'failed trash rolls back status and version');
    $v=$s->trashTask($id,$v,'No longer required');$deleted=$s->getTask($id,false,true);
    ok93($s->getTask($id)===null&&$deleted['status']==='deleted','trash removes active record without hard deletion');
    ok93(in_array($id,ids93($s->listTrash()),true)&&!in_array($id,ids93($s->listTasks(array('view'=>'my'))),true),'own Trash contains deleted task and My Tasks excludes it');
    no93(function()use($s,$id){$s->requireReadableTask($id);},InternalTaskAccessException::class,'normal task and content routes deny deleted record');
    ok93(!in_array($id,ids93((new InternalTaskService($db,$other,1))->listTrash()),true),'other staff Trash excludes deleted task');
    $cal=(new InternalTaskAgendaService($db,$worker,1))->events(strtotime('2026-09-01'),strtotime('2026-10-01'));$found=false;foreach($cal as $events)foreach($events as $event)if($event->id===-$id)$found=true;ok93(!$found,'deleted task absent from Agenda');
    no93(function()use($s,$id,$v){$s->restoreTrash($id,$v-1,'Stale');},InternalTaskConflictException::class,'stale recovery denied');
    $fault=new Failing93Db($db,'INSERT INTO '.MAIN_DB_PREFIX.'cpo_activity');
    no93(function()use($fault,$worker,$id,$v){(new InternalTaskService($fault,$worker,1))->restoreTrash($id,$v,'Fail after restore');},RuntimeException::class,'audit failure aborts restore');
    ok93($fault->fired&&$s->getTask($id)===null&&(int)$s->getTask($id,false,true)['version']===$v,'failed restore leaves task in Trash');
    $v=$s->restoreTrash($id,$v,'Resume work');$restored=$s->getTask($id);
    foreach(array('status','date_archived','progress','fk_stage','fk_user_creat','fk_user_assign','fk_user_modif') as $key)ok93($restored[$key]===$before[$key],'restore preserves original '.$key);
    ok93($d->get($id)===$details,'restore retains rich metadata and version');
    ok93(!in_array('task_trash_snapshot',array_column($s->activities($id),'action_code'),true),'recovery metadata hidden from user-facing timeline');
    $v=$m->cancelAndArchive($id,$v,'Manager cancellation');$before=$s->getTask($id);$v=$s->trashTask($id,$v,'Own cleanup');$v=$s->restoreTrash($id,$v,'Recover archive');
    ok93($s->getTask($id)['status']==='cancelled'&&$s->getTask($id)['date_archived']===$before['date_archived'],'trash recovery preserves prior archive state');
    no93(function()use($s,$id,$v){$s->restoreCancelled($id,$v,'Undo manager');},InternalTaskAccessException::class,'trash recovery does not bypass manager cancellation');
    $v=$m->restoreCancelled($id,$v,'Manager resumes');
    $teamManager=actor93(3);$teamManager->rights->chinaplanetoperations=(object)array('task_manage_team'=>1);
    $teamService=new InternalTaskService($db,$teamManager,1);$m->saveGrant(1,3,true);
    try{
        $v=$teamService->renameTask($id,$v,'Team manager edit');
        $v=$teamService->cancelAndArchive($id,$v,'Team manager cancel');$v=$teamService->restoreCancelled($id,$v,'Team manager resume');
        $v=$teamService->trashTask($id,$v,'Team manager delete');$v=$teamService->restoreTrash($id,$v,'Team manager recover');
        ok93($s->getTask($id)['title']==='Team manager edit','granted non-admin manager controls own team task');
        $m->saveGrant(1,3,false);$revoked=new InternalTaskService($db,$teamManager,1);
        no93(function()use($revoked,$id,$v){$revoked->renameTask($id,$v,'Revoked');},InternalTaskAccessException::class,'revoked team grant denies control immediately');
        ok93(!in_array($id,ids93($revoked->listTrash()),true),'revoked manager cannot list team Trash');
    }finally{$m->saveGrant(1,3,false);}
    $db->query('UPDATE '.MAIN_DB_PREFIX.'cpo_task SET fk_user_creat=5 WHERE entity=1 AND rowid='.$assigned);
    $foreignCreator=$m->getTask($assigned);ok93(empty($foreignCreator['creator_login']),'foreign entity creator identity is not joined');
    ok93(cpo_task_person_name(array('fk_user_assign'=>0),'owner')===$langs->trans('Unassigned'),'unassigned task has an explicit fallback');
    $db->query('UPDATE '.MAIN_DB_PREFIX.'cpo_task SET fk_user_creat=6 WHERE entity=1 AND rowid='.$assigned);
    ok93(!empty($m->getTask($assigned)['creator_login']),'inactive former creator name remains visible');
    $originalUser=$db->fetch_object($db->query('SELECT entity,admin FROM '.MAIN_DB_PREFIX.'user WHERE rowid=6'));
    try{$db->query('UPDATE '.MAIN_DB_PREFIX.'user SET entity=0,admin=1 WHERE rowid=6');ok93(!empty($m->getTask($assigned)['creator_login']),'native entity-zero administrator creator remains identifiable');}
    finally{$db->query('UPDATE '.MAIN_DB_PREFIX.'user SET entity='.(int)$originalUser->entity.',admin='.(int)$originalUser->admin.' WHERE rowid=6');}


    foreach(array(4,5,6) as $uid){no93(function()use($db,$uid,$id,$v){(new InternalTaskService($db,actor93($uid),1))->trashTask($id,$v,'Denied');},InternalTaskAccessException::class,'external foreign entity inactive trash denied '.$uid);}
}finally{foreach($created as $cleanupId)foreach(array('cpo_task_details'=>'fk_task','cpo_activity'=>'fk_task','cpo_task'=>'rowid') as $table=>$key)$db->query('DELETE FROM '.MAIN_DB_PREFIX.$table.' WHERE entity=1 AND '.$key.'='.(int)$cleanupId);}
echo 'M093_CONTROLS=PASS ASSERTIONS='.$n."\n";
