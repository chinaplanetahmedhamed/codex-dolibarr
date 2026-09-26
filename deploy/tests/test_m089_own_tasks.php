<?php
// Runs only against the isolated synthetic M089 database, never a business runtime.
if (getenv('M089_LOCAL_RUNTIME') !== '1') exit(1);
$config = getenv('M089_QA_CONFIG');
require $config;
if (!preg_match('/^m089_qa_[a-f0-9]{10}$/D', $dolibarr_main_db_name) || $dolibarr_main_db_host !== '127.0.0.1') throw new RuntimeException('LOCAL_ONLY');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn = new mysqli($dolibarr_main_db_host, $dolibarr_main_db_user, $dolibarr_main_db_pass, $dolibarr_main_db_name, (int)$dolibarr_main_db_port);
define('MAIN_DB_PREFIX', 'llx_');
$conf = (object) array('entity'=>1);
class M089TestDb {
    public $db;
    function __construct($db) {$this->db=$db;}
    function query($sql) {return $this->db->query($sql);}
    function fetch_object($r) {return $r instanceof mysqli_result ? $r->fetch_object() : null;}
    function num_rows($r) {return $r instanceof mysqli_result ? $r->num_rows : 0;}
    function escape($s) {return $this->db->real_escape_string($s);}
    function begin() {return $this->db->begin_transaction();}
    function commit() {return $this->db->commit();}
    function rollback() {return $this->db->rollback();}
    function last_insert_id($table='') {return $this->db->insert_id;}
    function affected_rows($r=null) {return $this->db->affected_rows;}
    function lasterror() {return $this->db->error;}
}
class M089TestActor {
    public $id, $admin=0, $entity=1, $socid=0, $statut=1;
    private $rights;
    function __construct($id, $rights, $admin=0) {$this->id=$id;$this->rights=$rights;$this->admin=$admin;}
    function hasRight($module,$right) {return in_array($right,$this->rights,true);}
}
function check089($ok,$label) {if(!$ok)throw new RuntimeException($label);echo "PASS $label\n";}
function denied089($call,$label) {try{$call();}catch(InternalTaskAccessException $e){check089(true,$label);return;}throw new RuntimeException('NOT_DENIED '.$label);}
require dirname(__DIR__,2).'/custom/chinaplanetoperations/class/InternalTaskService.class.php';
$db = new M089TestDb($conn);
$rights = array('task_read_own','task_update_own','task_upload_own','task_comment','task_checklist');
$worker = new InternalTaskService($db,new M089TestActor(2,$rights),1);
$other = new InternalTaskService($db,new M089TestActor(3,$rights),1);
$admin = new InternalTaskService($db,new M089TestActor(1,$rights,1),1);
$tag = 'M089_'.bin2hex(random_bytes(4));
$data = array('title'=>$tag.' personal','description'=>'My expected result','fk_usergroup'=>1,'request_hash'=>hash('sha256',$tag));
$id=$worker->createTask($data);
$task=$worker->requireReadableTask($id);
check089((int)$task['fk_user_assign']===2 && (int)$task['fk_user_creat']===2 && empty($task['fk_operation']),'own creation forces actor assignment and creator');
check089($worker->createTask($data)===$id,'same request creates exactly one task');
$conflict=false;try{$worker->createTask(array_merge($data,array('title'=>'changed')));}catch(InternalTaskConflictException $e){$conflict=true;}check089($conflict,'changed replay rejected');
check089($worker->authorization()->availableViews()===array('my','archive'),'worker can select own work and scoped archive, never team or all views');
check089(array_column($worker->ownTaskTeams(),'rowid')==array(1),'creation teams contain memberships only');
check089($worker->userOptionsForTeam(1)===array(),'worker cannot enumerate other team members');
foreach(array(array('fk_user_assign'=>3),array('fk_usergroup'=>2),array('fk_usergroup'=>3),array('fk_operation'=>100)) as $forged)denied089(fn()=>$worker->createTask(array_merge($data,$forged)),'forged creation scope '.json_encode($forged));
$noUpdate = new InternalTaskService($db,new M089TestActor(2,array('task_read_own')),1);
denied089(fn()=>$noUpdate->createTask($data),'read-only worker cannot create');
denied089(fn()=>(new InternalTaskService($db,new M089TestActor(6,$rights),1))->createTask($data),'inactive worker cannot create');
$externalActor=new M089TestActor(4,$rights);$externalActor->socid=100;
denied089(fn()=>(new InternalTaskService($db,$externalActor,1))->createTask($data),'external user cannot create');
denied089(fn()=>(new InternalTaskService($db,new M089TestActor(5,$rights),1))->createTask($data),'cross-entity worker cannot create');
denied089(fn()=>$other->requireReadableTask($id),'same-team colleague cannot read personal task');
check089(!in_array($id,array_map('intval',array_column($other->listTasks(array('view'=>'all')),'rowid')),true),'forged all view cannot list colleague task');
$version=$worker->updateTask($id,1,array('title'=>$tag.' edited','description'=>'Updated by owner','priority'=>'high','date_deadline'=>'2026-10-01T12:00'));
$reloaded=(new InternalTaskService($db,new M089TestActor(2,$rights),1))->requireReadableTask($id);
check089($version===2 && $reloaded['description']==='Updated by owner' && (int)$reloaded['fk_user_assign']===2,'self-created instructions persist after fresh service reload');
foreach(array(array('fk_user_assign'=>3),array('fk_user_assign'=>0),array('fk_usergroup'=>2),array('fk_usergroup'=>0),array('fk_operation'=>100)) as $forged)denied089(fn()=>$worker->updateTask($id,2,$forged),'forged edit scope '.json_encode($forged));
denied089(fn()=>$other->updateTask($id,2,array('title'=>'Other user edit')),'colleague cannot edit');
$stale=false;try{$worker->updateTask($id,1,array('title'=>'stale'));}catch(InternalTaskConflictException $e){$stale=true;}check089($stale,'stale edit rejected');
$assigned=$admin->createTask(array('title'=>$tag.' manager assigned','description'=>'Manager instructions','fk_usergroup'=>1,'fk_user_assign'=>2));
denied089(fn()=>$worker->updateTask($assigned,1,array('title'=>'Overwrite manager')),'worker cannot edit manager-created instructions');
check089($worker->transition($assigned,1,'in_progress')===2,'worker can still start manager-assigned task');
check089($worker->updateProgress($assigned,2,35)===3,'worker can still report progress');
$admin->updateTask($assigned,3,array('title'=>$tag.' manager edit','fk_usergroup'=>1,'fk_user_assign'=>2));
check089($admin->getTask($assigned)['title']===$tag.' manager edit','manager instruction editing retained');
$conn->query('DELETE FROM llx_usergroup_user WHERE fk_user=2 AND fk_usergroup=1 AND entity=1');
try{denied089(fn()=>$worker->updateTask($id,2,array('title'=>'revoked')),'membership revocation denies own edit');denied089(fn()=>$worker->createTask($data),'membership revocation denies creation');}finally{$conn->query('INSERT INTO llx_usergroup_user(entity,fk_user,fk_usergroup) VALUES(1,2,1)');}
// Retain synthetic fixtures for native UI acceptance; no business records copied.
echo "M089_OWN_TASKS=PASS TASK_ID=$id ASSIGNED_ID=$assigned\n";
