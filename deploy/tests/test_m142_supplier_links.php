<?php
/** Synthetic in-memory persistence tests; no ERP credentials or customer records. */
require __DIR__.'/test_m141_general_board_staff.php';
require_once dirname(__DIR__,2).'/custom/chinaplanetoperations/class/SupplierProjectWorkspaceService.class.php';
class M142Database {
 public $pdo; public int $transaction_opened=0;
 public function __construct(private $base){$this->pdo=$base->pdo;}
 public function query($sql){return $this->base->query($sql);}
 public function last_insert_id($table=''){return $this->pdo->lastInsertId();}
 public function fetch_object($r){return $this->base->fetch_object($r);}
 public function escape($s){return $this->base->escape($s);}
 public function begin(){if(!$this->transaction_opened)$this->pdo->beginTransaction();$this->transaction_opened++;return true;}
 public function commit(){if(--$this->transaction_opened===0)return $this->pdo->commit();return true;}
 public function rollback(){if($this->transaction_opened>1){$this->transaction_opened--;return true;}$this->transaction_opened=0;return $this->pdo->inTransaction()?$this->pdo->rollBack():true;}
}
$db=new M142Database($db);
if(empty($conf->global->CPO_CUSTOMER_WORKSPACES_ENABLED)){
 $conf->global->CPO_CUSTOMER_WORKSPACES_ENABLED=1;
 $db->query("INSERT INTO test_projet VALUES (30,1,'SYNTHETIC-30','Customer project',90)");
 $db->query("INSERT INTO test_societe VALUES (90,1,'Synthetic customer')");
 $db->query('INSERT INTO test_cpo_project_board VALUES (1,10,30)');
}
$db->query('CREATE TABLE test_commande_fournisseur (rowid INT,entity INT,fk_soc INT,fk_projet INT,fk_statut INT,ref TEXT)');
$db->query('CREATE TABLE test_cpo_supplier_board (entity INT,fk_supplier_order INT,fk_board INT,fk_supplier INT,fk_project INT)');
$db->query("INSERT INTO test_cpo_task VALUES (1000,1,'Synthetic product card','SYN-1000','todo','normal',1,NULL,NULL,1)");
$db->query('ALTER TABLE test_societe ADD COLUMN fournisseur INT DEFAULT 0');
$db->query("INSERT INTO test_societe VALUES (201,1,'Supplier A',1),(202,1,'Supplier B',1),(203,2,'Foreign supplier',1),(204,1,'Not supplier',0)");
$db->query("DROP TABLE test_element_element");
$db->query("CREATE TABLE test_element_element (rowid INTEGER PRIMARY KEY".($db->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'?' AUTOINCREMENT':'').", fk_source INT,sourcetype VARCHAR(50),fk_target INT,targettype VARCHAR(50),relationtype VARCHAR(255),UNIQUE(fk_source,sourcetype,fk_target,targettype))");
$db->query('CREATE TABLE test_cpo_supplier_workspace (entity INT,fk_supplier INT)');
$db->query('CREATE TABLE test_cpo_activity (entity INT,fk_task INT,fk_user INT,action_code TEXT,message TEXT,date_action TEXT)');
class M142Service extends SupplierProjectWorkspaceService {
 protected function createBoard(array $project,array $supplier,int $team):int {
  $id=500+(int)$supplier['rowid'];$this->testDb->query("INSERT INTO test_cpo_board VALUES ($id,1,'Supplier project',1,0,1)");
  $this->testDb->query("INSERT INTO test_cpo_board_member VALUES ($id,1,$id,1,'manager')");
  $this->linkBoard($id,(int)$project['rowid'],(int)$supplier['rowid']);return $id;
 }
 protected function createNativeDraft(int $project,int $supplier):int {
  $id=900+(int)$this->testDb->pdo->query('SELECT COUNT(*) FROM test_commande_fournisseur')->fetchColumn();
  $this->testDb->query("INSERT INTO test_commande_fournisseur VALUES ($id,1,$supplier,$project,0,'SYN-DRAFT-$id')");return $id;
 }
 public function __construct(public $testDb,$actor){parent::__construct($testDb,$actor,1);}
}
$conf->global->CPO_SUPPLIER_WORKSPACES_ENABLED=1;$s=new M142Service($db,new M141Actor());$n=0;
function ok142($yes,$message){global $n;if(!$yes)throw new RuntimeException($message);$n++;}
$version=(int)$db->pdo->query('SELECT version FROM test_cpo_task WHERE rowid=1000')->fetchColumn();
$r=$s->change(30,1000,201,true,$version);
ok142(count($r['suppliers'])===1,'Card supplier persisted');
ok142((int)$r['suppliers'][0]['board_id']===701,'Immediate supplier project board');
$r=$s->change(30,1000,202,true,$r['version']);ok142(count($r['suppliers'])===2,'Multiple suppliers on one card');
$r=$s->change(30,1000,201,true,$r['version']);ok142(count($r['suppliers'])===2,'Idempotent link without duplicate board');
$r=$s->change(30,1000,201,false,$r['version']);ok142(count($r['suppliers'])===1,'Unlink card supplier');
ok142(count($s->forProject(30))===2,'Project links and supplier boards preserved on card unlink');
$old=$r['version'];
foreach([[201,$old-1],[203,$old],[204,$old]] as [$supplier,$version]){
 try{$s->change(30,1000,$supplier,true,$version);throw new LogicException('Accepted invalid mutation');}catch(InternalTaskAccessException|InternalTaskConflictException $e){$n++;}
}
ok142(count($s->forCard(1000)['suppliers'])===1,'Invalid writes did not change links');
$db->query("INSERT INTO test_element_element(fk_source,sourcetype,fk_target,targettype,relationtype) VALUES (30,'project',204,'societe',NULL)");
ok142(count($s->forProject(30))===2,'Non-supplier native relation not exposed');
$external=new M141Actor();$external->socid=90;
try{(new M142Service($db,$external))->forCard(1000);throw new LogicException('External supplier data leaked');}catch(InternalTaskAccessException $e){$n++;}
$db->query("INSERT INTO test_element_element(fk_source,sourcetype,fk_target,targettype,relationtype) VALUES (701,'cpo_board',202,'societe','cpo_supplier_project')");
try{$s->boardContext(701);throw new LogicException('Ambiguous board mapping accepted');}catch(InternalTaskAccessException $e){$n++;}
$db->query("DELETE FROM test_element_element WHERE fk_source=701 AND sourcetype='cpo_board' AND targettype='societe' AND fk_target=202");
$panel=$s->projectPanel(30);ok142(count($panel['suppliers'])===2,'Project supplier panel and version readback');
try{$s->change(30,0,202,false,$panel['version']);throw new LogicException('Project unlink ignored card links');}catch(InternalTaskConflictException $e){$n++;}
$panel=$s->change(30,0,201,false,$panel['version']);ok142(count($panel['suppliers'])===1,'Project-only unlink');
$panel=$s->change(30,0,201,true,$panel['version']);ok142(count($panel['suppliers'])===2,'Project-only link reuses retained board');
$before=$db->pdo->query('SELECT * FROM test_element_element ORDER BY rowid')->fetchAll();
try{$s->change(30,0,204,true,$panel['version']);throw new LogicException('Unrelated native link overwritten');}catch(InternalTaskAccessException $e){$n++;}
ok142($before===$db->pdo->query('SELECT * FROM test_element_element ORDER BY rowid')->fetchAll(),'Unrelated native relationship preserved');
$draft=$s->createDraft(701,str_repeat('a',48));ok142($draft['id']===900&&!$draft['reused'],'Explicit draft creation');
$retry=$s->createDraft(701,str_repeat('a',48));ok142($retry['id']===900&&$retry['reused'],'Draft retry reuses native order');
$db->query('UPDATE test_commande_fournisseur SET fk_statut=3 WHERE rowid=900');
$retry=$s->createDraft(701,str_repeat('a',48));ok142($retry['id']===900&&$retry['reused'],'Retry never creates duplicate after approval');
$db->query('UPDATE test_commande_fournisseur SET fk_projet=999 WHERE rowid=900');
try{$s->createDraft(701,str_repeat('a',48));throw new LogicException('Changed order context reused');}catch(RuntimeException $e){if($e instanceof LogicException)throw $e;$n++;}
$db->query('UPDATE test_commande_fournisseur SET fk_projet=30 WHERE rowid=900');
$fail=new class($db,new M141Actor()) extends M142Service {protected function createNativeDraft(int $p,int $s):int{$this->testDb->begin();parent::createNativeDraft($p,$s);throw new RuntimeException('Synthetic native failure');}};
$count=(int)$db->pdo->query('SELECT COUNT(*) FROM test_commande_fournisseur')->fetchColumn();
$activities=(int)$db->pdo->query('SELECT COUNT(*) FROM test_cpo_activity')->fetchColumn();
try{$fail->createDraft(701,str_repeat('b',48));throw new LogicException('Failure not surfaced');}catch(RuntimeException $e){if($e instanceof LogicException)throw $e;$n++;}
ok142((int)$db->pdo->query('SELECT COUNT(*) FROM test_commande_fournisseur')->fetchColumn()===$count&&(int)$db->pdo->query('SELECT COUNT(*) FROM test_cpo_activity')->fetchColumn()===$activities,'Failed native create leaves no order or audit');
$db->query("INSERT INTO test_cpo_task VALUES (2000,1,'Synthetic supplier task','SYN-2000','todo','normal',1,NULL,NULL,1)");
$db->query('INSERT INTO test_cpo_board_card VALUES (2,1,701,701,2000,1,0)');
$scope=new InternalTaskWorkspaceService($db,new M141Actor(),1);
ok142($scope->taskAllowed(2000),'Valid supplier Board task scope');
foreach(['CPO_SUPPLIER_WORKSPACES_ENABLED','CPO_CUSTOMER_WORKSPACES_ENABLED'] as $flag){
 $conf->global->$flag=0;
 ok142(!$scope->taskAllowed(2000),'Disabled workspace does not expose supplier task');
 try{(new InternalTaskBoardsService($db,new M141Actor(),1))->board(701);throw new LogicException('Disabled supplier board became general');}catch(InternalTaskAccessException $e){$n++;}
 $conf->global->$flag=1;
}
$db->query("INSERT INTO test_element_element(fk_source,sourcetype,fk_target,targettype,relationtype) VALUES (701,'cpo_board',202,'societe','cpo_supplier_project')");
ok142(!$scope->taskAllowed(2000),'Ambiguous supplier Board task scope denied');
$db->query("DELETE FROM test_element_element WHERE fk_source=701 AND sourcetype='cpo_board' AND targettype='societe' AND fk_target=202");
$db->query('UPDATE test_societe SET entity=2 WHERE rowid=201');ok142(!$scope->taskAllowed(2000),'Foreign supplier prevents direct task reads');
$db->query('UPDATE test_societe SET entity=1 WHERE rowid=201');
$db->query("UPDATE test_cpo_board SET archived=1 WHERE rowid=701");
try{$s->createDraft(701,str_repeat('c',48));throw new LogicException('Archived board creates order');}catch(InternalTaskAccessException $e){$n++;}
$db->query('UPDATE test_cpo_board SET archived=0 WHERE rowid=701');
// Native purchase creation rights are separate from Operations task administration.
$taskAdmin=new class {
 public $id=1,$entity=1,$statut=1,$socid=0,$admin=0;
 public bool $supplierRead=true,$projectRead=true;
 public function hasRight(...$rights){
  $r=implode('.',$rights);
  return in_array($r,['chinaplanetoperations.task_admin','societe.lire','societe.client.voir'],true)
   ||($this->supplierRead&&$r==='fournisseur.commande.lire')||($this->projectRead&&$r==='projet.all.lire');
 }
};
$restricted=new M142Service($db,$taskAdmin);
ok142(!$restricted->boardPanel(701)['can_create_order'],'Task administrator without native purchase-create right cannot create orders');
try{$restricted->createDraft(701,str_repeat('d',48));throw new LogicException('Task admin bypassed native purchasing');}catch(InternalTaskAccessException $e){$n++;}
$db->query('ALTER TABLE test_cpo_board ADD COLUMN background VARCHAR(7)');
$db->query('CREATE TABLE test_cpo_board_deleted (rowid INTEGER PRIMARY KEY,entity INT,fk_board INT,fk_user_delete INT,date_deleted TEXT)');
$conf->global->CPO_BOARD_DELETE_ENABLED=1;
$db->query("INSERT INTO test_cpo_board_deleted VALUES (1,1,701,1,'2026-11-23 00:00:00')");
$panel=$s->forCard(1000); // supplier B remains linked to the customer card
ok142(count($s->forProject(30))===2,'Deleted supplier Board does not hide other supplier links');
$projectState=$s->projectPanel(30);
try{$s->change(30,0,201,true,$projectState['version']);throw new LogicException('Deleted supplier Board silently reused');}catch(InternalTaskConflictException $e){$n++;}
ok142((bool)$s->forProject(30)[0]['board_deleted']&&$s->forProject(30)[0]['board_id']===null,'Deleted supplier Board has explicit recovery state');
$taskAdmin->supplierRead=false;
ok142((new InternalTaskBoardLifecycle($db,$taskAdmin,1))->gallery()['boards']===[],'Deleted gallery hides supplier metadata after native rights revoked');
$taskAdmin->supplierRead=true;$taskAdmin->projectRead=false;
ok142((new InternalTaskBoardLifecycle($db,$taskAdmin,1))->gallery()['boards']===[],'Deleted gallery respects revoked project scope');
$db->query('DELETE FROM test_cpo_board_deleted');$conf->global->CPO_BOARD_DELETE_ENABLED=0;
ok142($db->transaction_opened===0&&!$db->pdo->inTransaction(),'Every command unwinds nested failure transactions');
// Exercise actual BoardsService creation, including its nested transaction and native mapping.
$existing=$db->pdo->query('SELECT rowid,entity,title,fk_usergroup,archived,version FROM test_cpo_board')->fetchAll(PDO::FETCH_ASSOC);
$db->query('DROP TABLE test_cpo_board');
$db->query("CREATE TABLE test_cpo_board (rowid INTEGER PRIMARY KEY,entity INT,title TEXT,fk_usergroup INT,archived INT DEFAULT 0,version INT DEFAULT 1,background VARCHAR(7),fk_user_creat INT,date_creation TEXT)");
foreach($existing as $row){$db->pdo->prepare('INSERT INTO test_cpo_board(rowid,entity,title,fk_usergroup,archived,version) VALUES (?,?,?,?,?,?)')->execute(array_values($row));}
$db->query('DROP TABLE test_cpo_board_list');
$db->query('CREATE TABLE test_cpo_board_list (rowid INTEGER PRIMARY KEY,entity INT,fk_board INT,title TEXT,position INT,archived INT DEFAULT 0)');
$db->query('CREATE TABLE test_cpo_board_request (rowid INTEGER PRIMARY KEY,entity INT,fk_user INT,request_hash VARCHAR(64),digest VARCHAR(64),fk_board INT)');
$db->query('CREATE TABLE test_usergroup (rowid INT,entity INT,nom TEXT)');$db->query("INSERT INTO test_usergroup VALUES (1,1,'Synthetic staff')");
$db->query("INSERT INTO test_societe VALUES (205,1,'<Synthetic supplier with a long name>',1)");
$real=new SupplierProjectWorkspaceService($db,new M141Actor(),1);$state=$real->projectPanel(30);
$created=$real->change(30,0,205,true,$state['version']);
$newBoard=(int)$created['suppliers'][2]['board_id'];ok142($newBoard>702,'Existing Boards service creates immediate supplier project board');
ok142((int)$db->pdo->query('SELECT COUNT(*) FROM test_cpo_board_list WHERE fk_board='.$newBoard)->fetchColumn()===3,'Native Board lists created');
ok142((int)$db->pdo->query('SELECT COUNT(*) FROM test_cpo_board_member WHERE fk_board='.$newBoard)->fetchColumn()===1,'Only explicit creator receives initial Board membership');
ok142((new InternalTaskBoardsService($db,new M141Actor(),1))->board($newBoard)['supplier_context']['project_id']===30,'Created Board resolves native project association');
$again=$real->change(30,0,205,true,$created['version']);ok142((int)$again['suppliers'][2]['board_id']===$newBoard,'Real create path reuses one Board');
echo "PASS M142: $n supplier-link checks\n";
