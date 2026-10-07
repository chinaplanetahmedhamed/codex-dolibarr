<?php
if(PHP_SAPI!=='cli'||getenv('M097_LOCAL_RUNTIME')!=='1')exit(1);
$config=getenv('M097_QA_CONFIG');require $config;
if(!preg_match('/^m097_qa_[a-f0-9]{10}$/D',$dolibarr_main_db_name)||$dolibarr_main_db_host!=='127.0.0.1')throw new RuntimeException('Synthetic local only.');
$_SERVER['DOCUMENT_ROOT']=$dolibarr_main_document_root;$_SERVER['SERVER_NAME']='127.0.0.1';$_SERVER['HTTP_HOST']='127.0.0.1:8107';require $dolibarr_main_document_root.'/master.inc.php';require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';require_once DOL_DOCUMENT_ROOT.'/custom/chinaplanetoperations/class/InternalTaskBoardsService.class.php';require_once DOL_DOCUMENT_ROOT.'/custom/chinaplanetoperations/class/InternalTaskBoardsMigration.class.php';
$r=$db->query('SELECT DATABASE() n');if(!preg_match('/^m097_qa_[a-f0-9]{10}$/D',$db->fetch_object($r)->n))throw new RuntimeException('Database drift.');
function actor($id){global $db;$u=new User($db);$u->fetch($id);$u->loadRights();return $u;}
require_once DOL_DOCUMENT_ROOT.'/custom/chinaplanetoperations/class/InternalTaskAssetService.class.php';
$n=0;function ok($b,$s){global $n;if(!$b)throw new RuntimeException('FAIL '.$s);echo 'PASS '.(++$n).' '.$s."\n";}
function denied($fn,$s,$type=InternalTaskAccessException::class){try{$fn();}catch(Throwable $e){ok($e instanceof $type,$s.' ['.get_class($e).']');return;}ok(false,$s);}
require_once DOL_DOCUMENT_ROOT.'/custom/chinaplanetoperations/class/InternalTaskBoardsLegacyMigration.class.php';
function taskDigest97(){global $db;$r=$db->query('SELECT * FROM '.MAIN_DB_PREFIX.'cpo_task ORDER BY rowid');$rows=[];while($o=$db->fetch_object($r))$rows[]=(array)$o;return hash('sha256',json_encode($rows));}
$admin=actor(1);$svc=new InternalTaskService($db,$admin,1);$db->begin();$tid=0;
try{
 $tid=$svc->createTask(['title'=>'Migration regression only','description'=>'Synthetic migration','priority'=>'normal','fk_user_assign'=>2,'fk_usergroup'=>1]);
 $before=taskDigest97();$depth=$db->transaction_opened;
 $result=(new InternalTaskBoardsLegacyMigration($db,$admin,1))->apply();ok($result['tasks']>=1,'unplaced task migrated');ok(taskDigest97()===$before,'canonical tasks remain byte equivalent');ok($db->transaction_opened===$depth,'migration and service nesting retain transaction depth');
 $result=(new InternalTaskBoardsLegacyMigration($db,$admin,1))->apply();ok($result===['boards'=>0,'tasks'=>0],'migration replay is idempotent');
 $r=$db->query('SELECT fk_board FROM '.MAIN_DB_PREFIX.'cpo_board_card WHERE entity=1 AND fk_task='.$tid);$board=(int)$db->fetch_object($r)->fk_board;$b=new InternalTaskBoardsService($db,actor(2),1);ok(count($b->workspace($board)['cards'])>=1,'native assignee retains authorized board access');
}finally{while($db->transaction_opened>0)$db->rollback();}
$r=$db->query('SELECT rowid FROM '.MAIN_DB_PREFIX.'cpo_task WHERE rowid='.$tid);ok(!$db->fetch_object($r),'outer rollback removes synthetic task and migration');
echo "M097_MIGRATION=PASS ASSERTIONS=$n\n";
