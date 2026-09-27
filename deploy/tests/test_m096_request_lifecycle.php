<?php
// Destructive fixtures are allowed only in the named isolated local QA database.
if (PHP_SAPI !== 'cli' || getenv('M091_LOCAL_RUNTIME') !== '1') exit(1);
require getenv('M091_QA_CONFIG');
if (!preg_match('/^m091_qa_[a-f0-9]{10}$/D', $dolibarr_main_db_name) || $dolibarr_main_db_host !== '127.0.0.1') throw new RuntimeException('Synthetic local database required.');
define('NOLOGIN',1); define('NOREQUIREMENU',1); define('NOREQUIREHTML',1); define('NOCSRFCHECK',1);
$_SERVER['DOCUMENT_ROOT']=$dolibarr_main_document_root;
require $dolibarr_main_document_root.'/master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
require_once dirname(__DIR__,2).'/custom/chinaplanetoperations/class/PublicWebsiteRequestService.class.php';
require_once dirname(__DIR__,2).'/custom/chinaplanetoperations/class/PublicRequestQuotationService.class.php';
require_once dirname(__DIR__,2).'/custom/chinaplanetoperations/class/PublicRequestContactService.class.php';
require_once dirname(__DIR__).'/migrations/m096_public_request_lifecycle.php';
$n=0; $created=array();
function ok96($ok,$message){global $n;if(!$ok)throw new RuntimeException('FAIL '.$message);echo 'PASS '.(++$n).' '.$message."\n";}
function deny96($fn,$message,$code=null){try{$fn();}catch(RuntimeException $e){if($code!==null)ok96($e->getMessage()===$code,$message);else ok96(true,$message);return;}throw new RuntimeException('FAIL '.$message);}
function actor96($id){global $db;$u=new User($db);$u->fetch($id);$u->loadRights();return $u;}
function raw96($id){global $db;return (array)$db->fetch_object($db->query('SELECT * FROM '.MAIN_DB_PREFIX.'cpo_public_request WHERE rowid='.(int)$id));}
class Fail96DB {
    private $db;
    function __construct($db){$this->db=$db;}
    function query($sql){if(strpos($sql,' SET lifecycle_state=')!==false)return false;return $this->db->query($sql);}
    function __call($name,$args){return $this->db->$name(...$args);}
}
class Busy96DB {
    private $db;
    function __construct($db){$this->db=$db;}
    function query($sql){return $this->db->query(strpos($sql,'SELECT GET_LOCK(')===0?'SELECT 0 AS acquired':$sql);}
    function __call($name,$args){return $this->db->$name(...$args);}
}
$admin=actor96(1);$worker=actor96(2);$worker->rights->chinaplanetoperations=(object)array('publicrequest_read'=>1,'publicrequest_update'=>1);
$readonly=clone $worker;$readonly->rights=clone $worker->rights;$readonly->rights->chinaplanetoperations=(object)array('publicrequest_read'=>1);
$foreign=clone $worker;$foreign->entity=2;$external=clone $worker;$external->socid=7;$inactive=clone $worker;$inactive->statut=0;
$key=str_repeat('m096',8);$s=new PublicWebsiteRequestService($db,1,$key);
try {
 $sql=file_get_contents(dirname(__DIR__,2).'/custom/chinaplanetoperations/sql/llx_cpo_public_request.sql');
 $sql=str_replace('CREATE TABLE llx_','CREATE TABLE IF NOT EXISTS '.MAIN_DB_PREFIX,$sql);
 ok96((bool)$db->query($sql),'local request schema available');
 ok96(m096RequestLifecycleSchema($db,true)&&m096RequestLifecycleSchema($db,true)&&m096RequestLifecycleSchema($db),'additive migration and repeat check pass');
 ok96($s->canManageLifecycle($admin)&&$s->canManageLifecycle($worker),'native administrator and permitted internal staff allowed');
 foreach(array($readonly,$foreign,$external,$inactive) as $index=>$actor)ok96(!$s->canManageLifecycle($actor),'denied actor '.$index);
 $input=array('request_type'=>'quote_request','idempotency_key'=>'m096-'.bin2hex(random_bytes(12)),'locale'=>'ar','contact'=>array('name'=>'M096 synthetic request','email'=>'m096@example.invalid'),'details'=>array('message'=>'Immutable test evidence','quantity'=>5));
 $r=$s->create($input,$admin);$id=$r['id'];$created[]=$id;$before=raw96($id);
 ok96($before['lifecycle_state']==='active'&&(int)$before['lifecycle_version']===1,'existing insert contract defaults to Active');
 foreach(array($readonly,$foreign,$external,$inactive) as $index=>$actor)deny96(function()use($s,$id,$actor){$s->changeLifecycle($id,'delete',1,$actor);},'unauthorized mutation '.$index,'PublicRequestAccessDenied');
 deny96(function()use($db,$key,$id,$admin){(new PublicWebsiteRequestService($db,2,$key))->changeLifecycle($id,'delete',1,$admin);},'cross-entity request inaccessible','PublicRequestNotFound');
 ok96($s->changeLifecycle($id,'archive',1,$worker)==='archived','staff archives request');
 $a=$s->get($id);ok96($a['status']===$before['status']&&$a['request']['details']['message']==='Immutable test evidence','business status and payload survive archive');
 ok96(!in_array($id,array_column($s->listing(),'rowid'))&&in_array($id,array_column($s->listing(array('view'=>'archived')),'rowid')),'default Active excludes archived; Archived includes it');
 deny96(function()use($s,$id,$admin){$s->changeLifecycle($id,'restore',1,$admin);},'stale browser version rejected','PublicRequestStale');
 deny96(function()use($s,$id){$s->updateStatus($id,'closed','',1);},'archived status update denied','PublicRequestRestoreFirst');
 deny96(function()use($s,$id){$s->promote($id,1,1);},'archived promotion denied','PublicRequestRestoreFirst');
 deny96(function()use($db,$a,$admin){(new PublicRequestContactService($db))->sendEmail($a,'test','test',$admin);},'archived contact action denied before sending','PublicRequestRestoreFirst');
 ok96($s->changeLifecycle($id,'delete',2,$admin)==='deleted','archived request moves to Deleted');
 $retry=$s->create($input,$admin);ok96($retry['id']===$id&&$retry['duplicate']&&$s->get($id)['lifecycle_state']==='deleted','website retry cannot recreate or restore deleted request');
 ok96(in_array($id,array_column($s->listing(array('view'=>'deleted')),'rowid'))&&!in_array($id,array_column($s->listing(array('view'=>'archived')),'rowid')),'Deleted view separated from Archived');
 ok96($s->changeLifecycle($id,'restore',3,$admin)==='active','deleted request restores to Active');
 $after=raw96($id);foreach(array('status','request_json','snapshot_json','idempotency_key','request_hash','contact_name') as $field)ok96($before[$field]===$after[$field],'restore preserves '.$field);
 $history=json_decode($after['lifecycle_history'],true);ok96(count($history)===3&&$history[0]['user_id']===(int)$worker->id&&$history[2]['to']==='active'&&!empty($history[0]['at']),'audit persists actor time and each state transition');
 foreach(array('fk_operation','fk_propal') as $link){$db->query('UPDATE '.MAIN_DB_PREFIX.'cpo_public_request SET '.$link.'=999999 WHERE rowid='.$id);deny96(function()use($s,$id,$admin){$s->changeLifecycle($id,'delete',4,$admin);},$link.' blocks deletion','PublicRequestLinkedDeleteDenied');$db->query('UPDATE '.MAIN_DB_PREFIX.'cpo_public_request SET '.$link.'=NULL WHERE rowid='.$id);}
 $db->query('UPDATE '.MAIN_DB_PREFIX.'cpo_public_request SET fk_operation=999999 WHERE rowid='.$id);
 ok96($s->changeLifecycle($id,'archive',4,$admin)==='archived'&&(int)$s->get($id)['fk_operation']===999999,'linked request can archive without modifying operation link');
 $s->changeLifecycle($id,'restore',5,$admin);
 $db->query('UPDATE '.MAIN_DB_PREFIX.'cpo_public_request SET fk_operation=NULL WHERE rowid='.$id);
 $stable=raw96($id);
 $failing=new PublicWebsiteRequestService(new Fail96DB($db),1,$key);
 deny96(function()use($failing,$id,$admin){$failing->changeLifecycle($id,'delete',6,$admin);},'database failure returns safe error','PublicRequestActionFailed');
 ok96(raw96($id)===$stable,'failed transition leaves state version and history unchanged');
 ok96($s->changeLifecycle($id,'delete',6,$worker)==='deleted','failed transaction releases lock and subsequent transition succeeds');
 $catalog=$input;$catalog['idempotency_key']='m096-catalog-'.bin2hex(random_bytes(8));$catalog['request_type']='catalog_order';$cr=$s->create($catalog,$admin);$created[]=$cr['id'];$s->changeLifecycle($cr['id'],'archive',1,$admin);
 deny96(function()use($db,$cr,$admin){(new PublicRequestQuotationService($db,1))->saveDraft($cr['id'],0,0,'USD','','',array(),$admin);},'archived quotation creation denied','PublicRequestRestoreFirst');
 $busy=new PublicWebsiteRequestService(new Busy96DB($db),1,$key);
 deny96(function()use($busy,$id,$admin){$busy->changeLifecycle($id,'restore',7,$admin);},'busy quotation lock returns conflict (fault injection)','PublicRequestStale');
 ok96($s->get($id)['lifecycle_state']==='deleted'&&(int)$s->get($id)['lifecycle_version']===7,'lock conflict changes no request state');
 ok96($s->changeLifecycle($id,'restore',7,$admin)==='active','real database transition remains available after injected conflict');
 echo 'M096_LIFECYCLE=PASS ASSERTIONS='.$n."\n";
} finally {
 foreach($created as $id)$db->query('DELETE FROM '.MAIN_DB_PREFIX.'cpo_public_request WHERE rowid='.(int)$id." AND contact_name='M096 synthetic request'");
}
