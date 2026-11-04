<?php
if(PHP_SAPI!=='cli'||getenv('M089_LOCAL_RUNTIME')!=='1')exit(1);
require getenv('M089_QA_CONFIG');
if(!preg_match('/^m089_qa_[a-f0-9]{10}$/D',$dolibarr_main_db_name)||$dolibarr_main_db_host!=='127.0.0.1')throw new RuntimeException('LOCAL ONLY');
$_SERVER['DOCUMENT_ROOT']=$dolibarr_main_document_root;$_SERVER['SERVER_NAME']='127.0.0.1';$_SERVER['HTTP_HOST']='127.0.0.1:8099';
require $dolibarr_main_document_root.'/master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/chinaplanetoperations/class/InternalTaskExperienceMigration.class.php';
(new InternalTaskExperienceMigration($db))->apply(1);(new InternalTaskExperienceMigration($db))->apply(1);
require_once DOL_DOCUMENT_ROOT.'/custom/chinaplanetoperations/class/InternalTaskPersonalBoard.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/chinaplanetoperations/class/InternalTaskDetailsService.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/chinaplanetoperations/class/actions_chinaplanetoperations.class.php';
function actor($id){global $db;$u=new User($db);$u->fetch($id);$u->loadRights();return $u;}
$n=0;function ok($b,$s){global $n;if(!$b)throw new RuntimeException('FAIL '.$s);echo 'PASS '.(++$n).' '.$s."\n";}
function denied($fn,$s,$type=InternalTaskAccessException::class){try{$fn();}catch(Throwable $e){ok($e instanceof $type,$s.' ['.get_class($e).']');return;}ok(false,$s);}
$user=actor(2);$other=actor(3);$admin=actor(1);$svc=new InternalTaskService($db,$user);$extra=new InternalTaskDetailsService($db,$user);$board=new InternalTaskPersonalBoard($db,$user);$otherBoard=new InternalTaskPersonalBoard($db,$other);$adminBoard=new InternalTaskPersonalBoard($db,$admin);$run=bin2hex(random_bytes(4));
$db->begin();
try{
 $base=['title'=>'C03 TEST '.$run,'description'=>'Synthetic task details','priority'=>'normal','fk_usergroup'=>1,'fk_user_assign'=>2];
 $data=['start_date'=>'2026-09-24','cover_color'=>'#2563eb','labels'=>[['name'=>'تصميم','color'=>'#16a34a']],'fields'=>[['name'=>'Quantity','type'=>'number','value'=>'5.5'],['name'=>'Approved','type'=>'checkbox','value'=>'0']],'notes'=>'Internal notes'];
 $request=bin2hex(random_bytes(24));$id=$extra->compose($base,$data,['Draft','Review'],'Initial comment',$request);ok($id>0,'rich composer saves');
 $saved=$extra->get($id);ok($saved['data']['labels'][0]['name']==='تصميم'&&$saved['data']['fields'][0]['value']==='5.5','rich values persist');
 $co=new InternalTaskCollaborationService($db,$user);ok(count($co->listChecklists($id))===1,'checklist persists');ok(count($co->listComments($id))===1,'comment persists');
 ok($extra->compose($base,$data,['Draft','Review'],'Initial comment',$request)===$id,'replay is idempotent');
 denied(fn()=>$extra->compose($base,$data,['Other'],'Initial comment',$request),'changed replay conflicts',InternalTaskConflictException::class);
 denied(fn()=>(new InternalTaskDetailsService($db,$other))->get($id),'other worker cannot read details');
 denied(fn()=>(new InternalTaskDetailsService($db,$other))->save($id,1,$data),'other worker cannot edit details');
 $extra->save($id,1,array_merge($data,['notes'=>'updated']));denied(fn()=>$extra->save($id,1,$data),'stale details rejected',InternalTaskConflictException::class);
 $owned=(new InternalTaskService($db,$admin))->createTask(array_merge($base,['title'=>'Manager assigned '.$run]));denied(fn()=>$extra->save($owned,0,$data),'assigned instructions stay manager owned');
 $state=$board->get();$others=$otherBoard->get();$admins=$adminBoard->get();$native=$svc->getTask($id);
 $input=array_map(fn($c)=>['id'=>$c['rowid'],'name'=>$c['label_en'],'color'=>$c['color']],$state['columns']);$input[0]['name']='My private list';$input[0]['color']='#ff8800';$input[]=['id'=>0,'name'=>'Personal new','color'=>'#e05252'];
 $changed=$board->saveColumns($state['version'],array_reverse($input));ok($changed['columns'][0]['label_en']==='Personal new','column add/reorder');ok($otherBoard->get()===$others&&$adminBoard->get()===$admins,'other user/admin columns unchanged');
 denied(fn()=>$board->saveColumns($state['version'],$input),'stale columns rejected',InternalTaskConflictException::class);
 $col=$changed['columns'][0]['rowid'];$changed=$board->move($changed['version'],$id,$col);ok($changed['cards'][$col][0]===$id,'move persists');
 ok($svc->getTask($id)['status']===$native['status']&&$svc->getTask($id)['fk_stage']===$native['fk_stage'],'personal move preserves operational status');
 denied(fn()=>$otherBoard->move($others['version'],$id,$col),'forged task move denied');
 $inColumn=$extra->compose(array_merge($base,['title'=>'In personal column '.$run]),[],[],'',bin2hex(random_bytes(24)),$col);$changed=$board->get();ok(in_array($inColumn,$changed['cards'][$col],true),'composer honors selected personal column');
 $second=$extra->compose(array_merge($base,['title'=>'Second '.$run]),[],[],'',bin2hex(random_bytes(24)));
 $third=$extra->compose(array_merge($base,['title'=>'Third '.$run]),[],[],'',bin2hex(random_bytes(24)));
 $default=$svc->getTask($second)['fk_stage'];$changed=$board->move($changed['version'],$third,$default,$second);$ids=$changed['cards'][$default];ok(array_search($third,$ids)<array_search($second,$ids),'insert before untouched card persists');
 foreach([4,5,6] as $uid){$bad=actor($uid);denied(fn()=>(new InternalTaskPersonalBoard($db,$bad))->get(),'restricted actor '.$uid);denied(fn()=>(new InternalTaskDetailsService($db,$bad))->get($id),'details restricted actor '.$uid);}
 denied(fn()=>$extra->validate(['cover_color'=>'red;display:none']),'CSS injection denied',RuntimeException::class);denied(fn()=>$extra->validate(['notes'=>'<script>bad</script>']),'HTML injection denied',RuntimeException::class);
 $hook=new ActionsChinaplanetoperations($db);$a='';$worker=actor(2);$hook->afterLogin([],$worker,$a,null);ok(strpos($worker->conf->MAIN_LANDING_PAGE,'workspace.php?view=my')!==false,'worker login landing');
 $old=$admin->conf->MAIN_LANDING_PAGE??null;$hook->afterLogin([],$admin,$a,null);ok(($admin->conf->MAIN_LANDING_PAGE??null)===$old,'admin landing preserved');
 // Simulate collaboration denial after base task creation to verify the entire save rolls back.
 $db->query('DELETE FROM '.MAIN_DB_PREFIX.'user_rights WHERE fk_user=2 AND fk_id=500517');$noCollab=actor(2);$badTitle='Rollback '.$run;
 denied(fn()=>(new InternalTaskDetailsService($db,$noCollab))->compose(array_merge($base,['title'=>$badTitle]),$data,['Fails'],'',bin2hex(random_bytes(24))),'failed checklist aborts compose');
 // Outer test transaction rollback is exercised after every assertion; persistent browser fixtures use separate writes.
 echo "C03 TESTS PASS=$n\n";
} finally {while($db->transaction_opened>0)$db->rollback();}

// A top-level failure must remove the base record as well as every child record.
$noCollab=actor(2);$noCollab->rights->chinaplanetoperations->task_checklist=0;
$failedBase=array_merge($base,['title'=>'Atomic rollback '.$run]);
denied(fn()=>(new InternalTaskDetailsService($db,$noCollab))->compose($failedBase,$data,['Requires checklist right'],'',bin2hex(random_bytes(24))),'top-level failure rejected');
$result=$db->query("SELECT COUNT(*) total FROM ".MAIN_DB_PREFIX."cpo_task WHERE title='".$db->escape($failedBase['title'])."'");
ok((int)$db->fetch_object($result)->total===0,'failed rich save leaves no partial task');
echo "M089_PERSONAL_BOARD=PASS ASSERTIONS=$n\n";
