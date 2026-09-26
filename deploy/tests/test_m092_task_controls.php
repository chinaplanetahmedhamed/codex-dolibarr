<?php
if(PHP_SAPI!=='cli'||getenv('M091_LOCAL_RUNTIME')!=='1')exit(1);
require getenv('M091_QA_CONFIG');
if(!preg_match('/^m091_qa_[a-f0-9]{10}$/D',$dolibarr_main_db_name)||$dolibarr_main_db_host!=='127.0.0.1')throw new RuntimeException('Synthetic local database required.');
$_SERVER['DOCUMENT_ROOT']=$dolibarr_main_document_root;$_SERVER['SERVER_NAME']='127.0.0.1';$_SERVER['HTTP_HOST']='127.0.0.1:8101';
require $dolibarr_main_document_root.'/master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/chinaplanetoperations/class/InternalTaskDetailsService.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/chinaplanetoperations/class/InternalTaskAgendaService.class.php';
function a92($id){global $db;$u=new User($db);$u->fetch($id);$u->loadRights();return $u;}
$n=0;function ok92($yes,$text){global $n;if(!$yes)throw new RuntimeException('FAIL '.$text);echo 'PASS '.(++$n).' '.$text."\n";}
function no92($fn,$class,$text){try{$fn();}catch(Throwable $e){ok92($e instanceof $class,$text);return;}ok92(false,$text);}
function ids92($rows){return array_map('intval',array_column($rows,'rowid'));}
class Failing92Db {
    private $inner; private $prefix; public $fired=false;
    public function __construct($db,$prefix){$this->inner=$db;$this->prefix=$prefix;}
    public function __call($name,$args){return $this->inner->$name(...$args);}
    public function query($sql){if(strpos($sql,$this->prefix)===0){$this->fired=true;return false;}return $this->inner->query($sql);}
}
$worker=a92(2);$other=a92(3);$admin=a92(1);$admin->rights->chinaplanetoperations=(object)array('task_archive'=>1);
$tasks=new InternalTaskService($db,$worker,1);$details=new InternalTaskDetailsService($db,$worker,1);$manager=new InternalTaskService($db,$admin,1);
$base=array('title'=>'M092 synthetic controls','description'=>'Local only','priority'=>'normal','fk_usergroup'=>1,'fk_user_assign'=>2,'date_deadline'=>'2026-09-27T15:30');
$extra=array('start_date'=>'2026-09-27','start_time'=>'09:15','notes'=>'Preserved notes');
$id=$details->compose($base,$extra,array(),'',bin2hex(random_bytes(24)));$created=array($id);
try {
    $old=$tasks->getTask($id);$saved=$details->get($id);
    $changed=$details->saveWithSchedule($id,$saved['version'],$old['version'],array_merge($extra,array('start_date'=>'2026-09-28','start_time'=>'10:00')),'2026-09-28T18:45');
    $freshTasks=new InternalTaskService($db,a92(2),1);$freshDetails=new InternalTaskDetailsService($db,a92(2),1);$task=$freshTasks->getTask($id);
    ok92($task['date_deadline']==='2026-09-28 18:45:00'&&$freshDetails->get($id)['data']['start_time']==='10:00','start and deadline save together and survive independent reload');
    foreach(array('title','description','priority','fk_usergroup','fk_user_assign') as $key)ok92($task[$key]===$old[$key],'schedule preserves '.$key);
    $oldVersion=(int)$task['version'];$detailVersion=$changed['version'];
    no92(function()use($details,$id,$detailVersion,$oldVersion,$extra){$details->saveWithSchedule($id,$detailVersion,$oldVersion,$extra,'2026-09-26T08:00');},RuntimeException::class,'reversed combined schedule rejected');
    ok92($tasks->getTask($id)['date_deadline']==='2026-09-28 18:45:00'&&$details->get($id)['version']===$detailVersion,'invalid save changes neither record');
    no92(function()use($details,$id,$detailVersion,$oldVersion,$extra){$details->saveWithSchedule($id,$detailVersion,$oldVersion-1,$extra,'2026-09-29T18:00');},InternalTaskConflictException::class,'stale task version rejects combined save');
    no92(function()use($details,$id,$detailVersion,$oldVersion,$extra){$details->saveWithSchedule($id,$detailVersion-1,$oldVersion,$extra,'2026-09-29T18:00');},InternalTaskConflictException::class,'stale details version rejects combined save');
    no92(function()use($db,$other,$id,$detailVersion,$oldVersion,$extra){(new InternalTaskDetailsService($db,$other,1))->saveWithSchedule($id,$detailVersion,$oldVersion,$extra,'2026-09-29T18:00');},InternalTaskAccessException::class,'other worker cannot change schedule');
    $fault=new Failing92Db($db,'UPDATE '.MAIN_DB_PREFIX.'cpo_task_details');
    no92(function()use($fault,$worker,$id,$detailVersion,$oldVersion,$extra){(new InternalTaskDetailsService($fault,$worker,1))->saveWithSchedule($id,$detailVersion,$oldVersion,$extra,'2026-09-29T18:00');},RuntimeException::class,'details write failure aborts combined save');
    ok92($fault->fired&&$tasks->getTask($id)['date_deadline']==='2026-09-28 18:45:00'&&(int)$tasks->getTask($id)['version']===$oldVersion&&$details->get($id)['version']===$detailVersion,'post-write failure rolls back deadline and versions');
    $fault=new Failing92Db($db,'INSERT INTO '.MAIN_DB_PREFIX.'cpo_activity');
    no92(function()use($fault,$worker,$id,$oldVersion){(new InternalTaskService($fault,$worker,1))->cancelAndArchive($id,$oldVersion,'Failure test');},RuntimeException::class,'audit failure aborts cancellation');
    $unchanged=$tasks->getTask($id);
    ok92($fault->fired&&$unchanged['status']==='todo'&&empty($unchanged['date_archived'])&&(int)$unchanged['version']===$oldVersion,'post-write failure rolls back status archive and version');
    $assigned=$manager->createTask($base);$created[]=$assigned;
    no92(function()use($details,$assigned,$extra){$details->saveWithSchedule($assigned,0,1,$extra,'2026-09-29T18:00');},InternalTaskAccessException::class,'manager-assigned deadline remains protected');
    no92(function()use($tasks,$assigned){$tasks->cancelAndArchive($assigned,1,'Wrong authority');},InternalTaskAccessException::class,'worker cannot cancel manager-assigned work');
    no92(function()use($tasks,$id,$oldVersion){$tasks->cancelAndArchive($id,$oldVersion,'');},RuntimeException::class,'cancellation reason required');
    no92(function()use($tasks,$id,$oldVersion){$tasks->cancelAndArchive($id,$oldVersion-1,'Stale');},InternalTaskConflictException::class,'stale cancellation changes nothing');
    ok92(empty($tasks->getTask($id)['date_archived']),'failed cancellation leaves task active');
    $oldVersion=$tasks->transition($id,$oldVersion,'in_progress');$oldVersion=$tasks->updateProgress($id,$oldVersion,35);
    $version=$tasks->cancelAndArchive($id,$oldVersion,'Work no longer needed');$task=$tasks->getTask($id);
    ok92($task['status']==='cancelled'&&!empty($task['date_archived']),'one cancellation atomically sets cancelled and archived');
    ok92((int)$task['version']===$version&&$version===$oldVersion+1,'cancellation increments optimistic version exactly once');
    ok92(!in_array($id,ids92($tasks->listTasks(array('view'=>'my'))),true),'cancelled task disappears from active My Tasks');
    ok92(in_array($id,ids92($tasks->listTasks(array('view'=>'archive'))),true),'cancelled task appears in own Archive');
    $cal=(new InternalTaskAgendaService($db,$worker,1))->events(strtotime('2026-09-01'),strtotime('2026-10-01'));$found=false;foreach($cal as $events)foreach($events as $event)if($event->id===-$id)$found=true;
    ok92(!$found,'cancelled task disappears from Agenda');
    ok92($details->get($id)['data']['notes']==='Preserved notes','cancellation retains rich details');
    $activity=$tasks->activities($id);ok92(in_array('cancelled: Work no longer needed',array_column($activity,'message'),true)&&in_array('archive: Work no longer needed',array_column($activity,'message'),true),'cancellation and archive reasons retained in history');
    $foreign=new InternalTaskService($db,$other,1);ok92(!in_array($id,ids92($foreign->listTasks(array('view'=>'archive'))),true),'other worker archive cannot expose own task');
    $manager->saveGrant(1,3,true);ok92(!in_array($id,ids92($foreign->listTasks(array('view'=>'archive'))),true),'stored team grant without manager permission cannot widen Archive');$manager->saveGrant(1,3,false);
    no92(function()use($foreign,$id,$version){$foreign->restoreCancelled($id,$version,'Foreign');},InternalTaskAccessException::class,'other worker cannot restore task');
    no92(function()use($tasks,$id,$version){$tasks->restoreCancelled($id,$version-1,'Stale');},InternalTaskConflictException::class,'stale restore leaves archive untouched');
    $version=$tasks->restoreCancelled($id,$version,'Work resumed');$task=$tasks->getTask($id);
    ok92($task['status']==='in_progress'&&empty($task['date_archived'])&&(int)$task['progress']===35,'restore returns prior active stage and progress');
    ok92(in_array($id,ids92($tasks->listTasks(array('view'=>'my'))),true),'restored task returns to My Tasks');
    $version=$manager->cancelAndArchive($id,$version,'Manager decision');
    no92(function()use($tasks,$id,$version){$tasks->restoreCancelled($id,$version,'Undo manager');},InternalTaskAccessException::class,'worker cannot undo manager cancellation');
    $manager->restoreCancelled($id,$version,'Manager resumes');
    foreach(array(4,5,6) as $uid)no92(function()use($db,$uid,$id,$tasks){(new InternalTaskService($db,a92($uid),1))->cancelAndArchive($id,$tasks->getTask($id)['version'],'Denied');},InternalTaskAccessException::class,'external foreign-entity inactive cancellation denied '.$uid);
} finally {
    foreach($created as $cleanupId)foreach(array('cpo_task_details'=>'fk_task','cpo_activity'=>'fk_task','cpo_task'=>'rowid') as $table=>$key)$db->query('DELETE FROM '.MAIN_DB_PREFIX.$table.' WHERE entity=1 AND '.$key.'='.(int)$cleanupId);
}
echo 'M092_CONTROLS=PASS ASSERTIONS='.$n."\n";
