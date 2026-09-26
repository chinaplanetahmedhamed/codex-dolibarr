<?php
if(PHP_SAPI!=='cli'||getenv('M091_LOCAL_RUNTIME')!=='1')exit(1);
require getenv('M091_QA_CONFIG');if(!preg_match('/^m091_qa_[a-f0-9]{10}$/D',$dolibarr_main_db_name)||$dolibarr_main_db_host!=='127.0.0.1')throw new RuntimeException('Synthetic local database required.');
$_SERVER['DOCUMENT_ROOT']=$dolibarr_main_document_root;require $dolibarr_main_document_root.'/master.inc.php';require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';require_once DOL_DOCUMENT_ROOT.'/custom/chinaplanetoperations/class/InternalTaskBoardPresentation.class.php';
function actor95($id){global $db;$u=new User($db);$u->fetch($id);$u->loadRights();return $u;}
$n=0;function ok95($ok,$label){global $n;if(!$ok)throw new RuntimeException('FAIL '.$label);echo 'PASS '.(++$n).' '.$label."\n";}
function no95($fn,$label){try{$fn();}catch(InternalTaskAccessException $e){ok95(true,$label);return;}throw new RuntimeException('FAIL '.$label);}
$admin=actor95(1);$worker=actor95(2);$manager=actor95(3);$manager->rights->chinaplanetoperations=(object)array('task_manage_team'=>1,'task_read_own'=>1);
$a=new InternalTaskService($db,$admin,1);$personal=new InternalTaskPersonalBoard($db,$worker,1);$saved=$db->fetch_object($db->query("SELECT value FROM ".MAIN_DB_PREFIX."user_param WHERE entity=1 AND fk_user=2 AND param='CPO_PERSONAL_BOARD_V1'"));$created=array();$linkAdded=false;
try{
 $a->saveGrant(1,3,true);
 $r=$db->query('SELECT rowid FROM '.MAIN_DB_PREFIX.'usergroup_user WHERE entity=1 AND fk_user=2 AND fk_usergroup=2');if(!$db->fetch_object($r)){$db->query('INSERT INTO '.MAIN_DB_PREFIX.'usergroup_user(entity,fk_user,fk_usergroup) VALUES(1,2,2)');$linkAdded=true;}
 $base=array('title'=>'M095 dashboard scope','description'=>'Synthetic employee board','priority'=>'normal','fk_user_assign'=>2,'fk_usergroup'=>1);
 $first=$a->createTask($base);$created[]=$first;$second=$a->createTask($base);$created[]=$second;$outside=$a->createTask(array_merge($base,array('fk_usergroup'=>2)));$created[]=$outside;
 $state=$personal->get();$cols=array();foreach($state['columns'] as $column)$cols[]=array('id'=>$column['rowid'],'name'=>$column['label_en'],'color'=>$column['color']);$cols[0]['name']='M095 worker private queue';$cols[0]['color']='#123456';$state=$personal->saveColumns($state['version'],$cols);
 $state=$personal->move($state['version'],$second,$state['columns'][0]['rowid']);$state=$personal->move($state['version'],$first,$state['columns'][0]['rowid'],$second);
 $self=(new InternalTaskBoardPresentation($db,$worker,1))->load(array('view'=>'my'));
 $adminView=(new InternalTaskBoardPresentation($db,$admin,1))->load(array('view'=>'all','assignee_id'=>2));
 ok95($self['columns']===$adminView['columns'],'administrator sees employee column names colors and order');
 ok95(array_column($self['tasks'],'rowid')===array_column($adminView['tasks'],'rowid'),'administrator sees employee card placement and order');
 ok95($self['editable']&&!$adminView['editable']&&$adminView['personal']&&!$adminView['list'],'staff layout editable only from owner dashboard');
 ok95((new InternalTaskPersonalBoard($db,$worker,1))->get()===$state,'viewing staff dashboard writes no preferences');
 $adminPersonal=new InternalTaskPersonalBoard($db,$admin,1);ok95($adminPersonal->get()['columns']!==$adminView['columns'],'manager own columns stay independent');
 $managerView=(new InternalTaskBoardPresentation($db,$manager,1))->load(array('view'=>'team','assignee_id'=>2));$ids=array_map('intval',array_column($managerView['tasks'],'rowid'));
 ok95(in_array($first,$ids,true)&&in_array($second,$ids,true)&&!in_array($outside,$ids,true),'supervisor only sees granted-team tasks on employee board');
 $placements=array();foreach($managerView['state']['cards'] as $idsInColumn)$placements=array_merge($placements,$idsInColumn);ok95(!in_array($outside,array_map('intval',$placements),true),'hidden-team placement IDs removed from viewed state');
 $all=(new InternalTaskBoardPresentation($db,$admin,1))->load(array('view'=>'all'));ok95($all['list']&&!$all['personal']&&$all['columns']===array()&&$all['state']===null,'All tasks uses list without manager columns');
 $team=(new InternalTaskBoardPresentation($db,$manager,1))->load(array('view'=>'team'));ok95($team['list']&&!in_array($outside,array_map('intval',array_column($team['tasks'],'rowid')),true),'supervisor all-task list remains team-scoped');
 $filtered=(new InternalTaskBoardPresentation($db,$admin,1))->load(array('view'=>'all','assignee_id'=>2,'q'=>'M095 dashboard scope'));ok95(count($filtered['tasks'])===3&&$filtered['columns']===$self['columns'],'search retains employee dashboard layout');
 no95(function()use($db,$worker){(new InternalTaskBoardPresentation($db,$worker,1))->load(array('view'=>'all','assignee_id'=>3));},'staff forged staff filter denied');
 foreach(array(4,5,6,999999) as $uid)no95(function()use($db,$admin,$uid){(new InternalTaskPersonalBoard($db,$admin,1))->getForUser($uid);},'external foreign inactive nonexistent board denied '.$uid);
 $a->saveGrant(1,3,false);no95(function()use($db,$manager){(new InternalTaskBoardPresentation($db,$manager,1))->load(array('view'=>'team','assignee_id'=>2));},'revoked supervisor grant denies employee board immediately');
 ok95((new InternalTaskBoardPresentation($db,$worker,1))->staffOptions()===array(),'staff cannot enumerate other dashboards');
 $ownAsManager=(new InternalTaskBoardPresentation($db,$admin,1))->load(array('view'=>'all','assignee_id'=>1));ok95($ownAsManager['personal']&&$ownAsManager['editable'],'manager own dashboard uses own settings');
}finally{
 $a->saveGrant(1,3,false);
 if($saved)$db->query("UPDATE ".MAIN_DB_PREFIX."user_param SET value='".$db->escape($saved->value)."' WHERE entity=1 AND fk_user=2 AND param='CPO_PERSONAL_BOARD_V1'");else $db->query("DELETE FROM ".MAIN_DB_PREFIX."user_param WHERE entity=1 AND fk_user=2 AND param='CPO_PERSONAL_BOARD_V1'");
 foreach($created as $id)foreach(array('cpo_activity'=>'fk_task','cpo_task'=>'rowid') as $table=>$key)$db->query('DELETE FROM '.MAIN_DB_PREFIX.$table.' WHERE entity=1 AND '.$key.'='.$id);
 if($linkAdded)$db->query('DELETE FROM '.MAIN_DB_PREFIX.'usergroup_user WHERE entity=1 AND fk_user=2 AND fk_usergroup=2');
}
echo 'M095_STAFF_BOARDS=PASS ASSERTIONS='.$n."\n";
