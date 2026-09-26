<?php
if(PHP_SAPI!=='cli'||getenv('M091_LOCAL_RUNTIME')!=='1')exit(1);
require getenv('M091_QA_CONFIG');
if(!preg_match('/^m091_qa_[a-f0-9]{10}$/D',$dolibarr_main_db_name)||$dolibarr_main_db_host!=='127.0.0.1')throw new RuntimeException('Synthetic local database required.');
$_SERVER['DOCUMENT_ROOT']=$dolibarr_main_document_root;require $dolibarr_main_document_root.'/master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';require_once DOL_DOCUMENT_ROOT.'/custom/chinaplanetoperations/class/InternalTaskService.class.php';
function actor94($id){global $db;$u=new User($db);$u->fetch($id);$u->loadRights();return $u;}
$n=0;function ok94($yes,$text){global $n;if(!$yes)throw new RuntimeException('FAIL '.$text);echo 'PASS '.(++$n).' '.$text."\n";}
function no94($fn,$label){try{$fn();}catch(RuntimeException $e){ok94($e->getMessage()==='Assignee must be an active team member.',$label);return;}throw new RuntimeException('FAIL '.$label);}
$before=array();foreach(array(1,4,5,6) as $uid)$before[$uid]=$db->fetch_object($db->query('SELECT entity,admin,statut FROM '.MAIN_DB_PREFIX.'user WHERE rowid='.$uid));$links=array();$tasks=array();
try{
 foreach(array(1,4,5,6) as $uid){$r=$db->query('SELECT rowid FROM '.MAIN_DB_PREFIX.'usergroup_user WHERE entity=1 AND fk_usergroup=1 AND fk_user='.$uid);if(!$db->fetch_object($r)){$db->query('INSERT INTO '.MAIN_DB_PREFIX.'usergroup_user(entity,fk_usergroup,fk_user) VALUES(1,1,'.$uid.')');$links[]=$uid;}}
 $db->query('UPDATE '.MAIN_DB_PREFIX.'user SET entity=0,admin=1,statut=1 WHERE rowid=1');$s=new InternalTaskService($db,actor94(1),1);
 $ids=array_map('intval',array_column($s->userOptionsForTeam(1),'rowid'));ok94(in_array(1,$ids,true),'existing native entity-zero administrator appears in team');ok94(in_array(2,$ids,true),'ordinary active native member appears');
 foreach(array(4,5,6) as $uid){ok94(!in_array($uid,$ids,true),'external foreign inactive member excluded '.$uid);ok94(!$s->authorization()->isActiveTeamMember($uid,1),'server assignment excludes invalid member '.$uid);}
 $base=array('title'=>'M094 synthetic assignment','description'=>'Existing team membership test','priority'=>'normal','fk_usergroup'=>1,'fk_user_assign'=>1);
 $id=$s->createTask($base);$tasks[]=$id;$t=$s->getTask($id);ok94((int)$t['fk_user_assign']===1&&!empty($t['owner_login']),'assignment to native global administrator persists with visible assignee');ok94(in_array($id,array_map('intval',array_column($s->listTasks(array('view'=>'my')),'rowid')),true),'assigned administrator sees own task');
 foreach(array(4,5,6) as $uid)no94(function()use($s,$base,$uid){$s->createTask(array_merge($base,array('fk_user_assign'=>$uid)));},'invalid assignee rejected '.$uid);
 $db->query('UPDATE '.MAIN_DB_PREFIX.'user SET entity=0,admin=0,statut=1 WHERE rowid=6');ok94(!$s->authorization()->isActiveTeamMember(6,1),'entity-zero non-administrator remains excluded');
 $db->query('UPDATE '.MAIN_DB_PREFIX.'usergroup_user SET entity=2 WHERE fk_usergroup=1 AND fk_user=1 AND entity=1');
 ok94(!$s->authorization()->isActiveTeamMember(1,1)&&!in_array(1,array_map('intval',array_column($s->userOptionsForTeam(1),'rowid')),true),'foreign membership cannot grant assignment in current entity');
 no94(function()use($s,$base){$s->createTask($base);},'server denies assignment after native membership removed');
 $db->query('UPDATE '.MAIN_DB_PREFIX.'usergroup_user SET entity=1 WHERE fk_usergroup=1 AND fk_user=1 AND entity=2');
 ok94((new InternalTaskService($db,actor94(2),1))->userOptionsForTeam(1)===array(),'staff cannot acquire manager assignment list');
}finally{
 $db->query('UPDATE '.MAIN_DB_PREFIX.'usergroup_user SET entity=1 WHERE fk_usergroup=1 AND fk_user=1 AND entity=2');
 foreach($before as $uid=>$row)$db->query('UPDATE '.MAIN_DB_PREFIX.'user SET entity='.(int)$row->entity.',admin='.(int)$row->admin.',statut='.(int)$row->statut.' WHERE rowid='.$uid);
 foreach($links as $uid)$db->query('DELETE FROM '.MAIN_DB_PREFIX.'usergroup_user WHERE entity=1 AND fk_usergroup=1 AND fk_user='.$uid);
 foreach($tasks as $id)foreach(array('cpo_activity'=>'fk_task','cpo_task'=>'rowid') as $table=>$key)$db->query('DELETE FROM '.MAIN_DB_PREFIX.$table.' WHERE entity=1 AND '.$key.'='.$id);
}
echo 'M094_MEMBERS=PASS ASSERTIONS='.$n."\n";
