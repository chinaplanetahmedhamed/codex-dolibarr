<?php
/** Real SQL/persistence tests in private in-memory SQLite. No ERP bootstrap/data. */
define('MAIN_DB_PREFIX','llx_');
class InternalTaskAccessException extends RuntimeException{}
class InternalTaskConflictException extends RuntimeException{}
require dirname(__DIR__,3).'/custom/chinaplanetoperations/class/InternalTaskWorkspaceService.class.php';
class DB {
    public $pdo;
    public function __construct(){$this->pdo=new PDO('sqlite::memory:');$this->pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);}
    public function query($s){try{return $this->pdo->query($s);}catch(Throwable $e){return false;}}
    public function fetch_object($r){return $r->fetch(PDO::FETCH_OBJ);}
    public function escape($s){return str_replace("'","''",$s);}
}
class Actor {
    public $id=2,$entity=1,$socid=0,$fk_soc=0,$statut=1,$admin=0,$rights=array();
    public function hasRight(...$args){return !empty($this->rights[implode('.',$args)]);}
}
$n=0;
function ok($v,$msg){global $n;if(!$v)throw new RuntimeException('FAIL '.$msg);echo 'PASS '.(++$n).' '.$msg."\n";}
function deny($fn,$msg){try{$fn();}catch(InternalTaskAccessException $e){ok(true,$msg);return;}throw new RuntimeException('FAIL '.$msg);}
$db=new DB();$db->pdo->exec(<<<SQL
CREATE TABLE llx_societe(rowid INTEGER PRIMARY KEY,entity INT,nom TEXT,client INT);
CREATE TABLE llx_societe_commerciaux(fk_soc INT,fk_user INT);
CREATE TABLE llx_societe_extrafields(fk_object INT PRIMARY KEY,cpo_board_workspace INT);
CREATE TABLE llx_projet(rowid INTEGER PRIMARY KEY,entity INT,fk_soc INT,ref TEXT,title TEXT,fk_statut INT);
CREATE TABLE llx_cpo_board(rowid INTEGER PRIMARY KEY,entity INT,title TEXT,archived INT);
CREATE TABLE llx_cpo_board_member(entity INT,fk_board INT,fk_user INT,role TEXT);
CREATE TABLE llx_cpo_project_board(rowid INTEGER PRIMARY KEY,entity INT,fk_project INT,fk_board INT,UNIQUE(entity,fk_project),UNIQUE(entity,fk_board));
CREATE TABLE llx_cpo_board_card(entity INT,fk_board INT,fk_task INT,archived INT,fk_list INT);
CREATE TABLE llx_cpo_board_list(rowid INT,entity INT,fk_board INT,archived INT);
CREATE TABLE llx_cpo_task(rowid INTEGER PRIMARY KEY,entity INT,date_archived TEXT,status TEXT);
INSERT INTO llx_societe VALUES(1,1,'Customer A',1),(2,1,'Customer B',1),(3,2,'Other entity',1),(4,1,'Disabled space',1);
INSERT INTO llx_societe_extrafields VALUES(1,1),(2,1),(3,1),(4,0);
INSERT INTO llx_societe_commerciaux VALUES(1,2),(2,3);
INSERT INTO llx_projet VALUES(11,1,1,'P-A','Project A',1),(12,1,2,'P-B','Project B',1),(13,2,3,'P-X','Other entity',1),(14,1,4,'P-D','Disabled',1),(15,1,1,'P-A2','Second project',1);
INSERT INTO llx_cpo_board VALUES(101,1,'Board A',0),(102,1,'Board B',0),(103,2,'Other entity board',0),(104,1,'Generic',0);
INSERT INTO llx_cpo_project_board VALUES(1,1,11,101),(2,1,12,102),(3,2,13,103);
INSERT INTO llx_cpo_board_member VALUES(1,101,2,'viewer'),(1,102,2,'viewer');
INSERT INTO llx_cpo_task VALUES(201,1,NULL,'todo'),(202,1,NULL,'todo'),(203,2,NULL,'todo'),(204,1,NULL,'todo'),(205,1,NULL,'done'),(206,1,'2026-10-01','todo');
INSERT INTO llx_cpo_board_card VALUES(1,101,201,0,101),(1,102,202,0,102),(2,103,203,0,103),(1,104,204,0,104),(1,101,205,0,101),(1,101,206,0,101);
INSERT INTO llx_cpo_board_list VALUES(101,1,101,0),(102,1,102,0),(103,2,103,0),(104,1,104,0);
SQL);
$conf=(object)array('global'=>(object)array('CPO_CUSTOMER_WORKSPACES_ENABLED'=>1));
$a=new Actor();$a->rights=['societe.lire'=>1,'projet.all.lire'=>1];$svc=new InternalTaskWorkspaceService($db,$a,1);
$r=$svc->customers();ok(count($r['customers'])===1&&$r['customers'][0]['rowid']==1,'customer ownership filters gallery despite other board membership');
$p=$svc->projects(1);ok(count($p['projects'])===2,'only authorized customer projects returned');ok($p['projects'][1]['open_tasks']==1,'counts exclude completed and archived tasks');
$ctx=$svc->boardProject(101);ok($ctx['rowid']==11&&$ctx['fk_soc']==1,'board derives native project/customer ownership');
deny(fn()=>$svc->projects(2),'direct cross-customer workspace denied');deny(fn()=>$svc->projects(3),'cross-entity workspace denied');deny(fn()=>$svc->project(13),'cross-entity project denied');deny(fn()=>$svc->boardProject(102),'board membership cannot bypass native customer rights');
ok(!$svc->taskAllowed(202),'task context scope rejects other customer even direct card grants');ok($svc->taskAllowed(201),'own customer grouped task allowed');ok($svc->taskAllowed(204),'generic tasks preserve legacy scope');
$sql='SELECT t.rowid FROM llx_cpo_task t WHERE t.entity=1 AND '.$svc->taskScopeSql('t');$r=$db->query($sql);$ids=[];while($o=$db->fetch_object($r))$ids[]=$o->rowid;ok(in_array(201,$ids)&&!in_array(202,$ids)&&in_array(204,$ids),'SQL search/list predicate enforces same customer isolation');
$a->rights['societe.client.voir']=1;ok(count($svc->customers()['customers'])===2,'all-customer right permits authorized cross-customer overview');
$a->rights['projet.all.lire']=0;ok(count($svc->customers()['customers'])===0,'missing project rights hide workspaces');$a->rights['projet.all.lire']=1;
$db->pdo->exec('DELETE FROM llx_cpo_board_member WHERE fk_board=102');$p=$svc->projects(2)['projects'][0];ok($p['board']===null&&$p['open_tasks']==0,'native customer rights alone do not expose board metadata/counts');
$external=clone $a;$external->socid=1;$ex=new InternalTaskWorkspaceService($db,$external,1);deny(fn()=>$ex->customers(),'external customers cannot open internal workspaces');
$inactive=clone $a;$inactive->statut=0;deny(fn()=>(new InternalTaskWorkspaceService($db,$inactive,1))->customers(),'inactive user denied');
$other=clone $a;$other->entity=2;deny(fn()=>(new InternalTaskWorkspaceService($db,$other,1))->projects(1),'actor entity mismatch denied');
deny(fn()=>$svc->assertMove(101,102),'cross-project list move denied');deny(fn()=>$svc->assertMove(101,104),'grouped list cannot move into generic board');$svc->assertMove(104,104);ok(true,'generic legacy move remains permitted');
$admin=clone $a;$admin->admin=1;$adminSvc=new InternalTaskWorkspaceService($db,$admin,1);ok($adminSvc->canCreate(),'native administrator can provision project boards');deny(fn()=>$adminSvc->project(14,0,true),'disabled workspace rejects project board provisioning');
$db->pdo->beginTransaction();$adminSvc->link(105,15);ok($adminSvc->boardProject(105)['rowid']==15,'mapping persists in enclosing transaction');try{$adminSvc->link(106,15);throw new RuntimeException('Duplicate mapping accepted');}catch(InternalTaskConflictException $e){ok(true,'one project board unique key rejects race/duplicate');}$db->pdo->rollBack();ok($adminSvc->boardProject(105)===null,'rollback removes mapping without touching native records');
$before=$db->pdo->query('SELECT COUNT(*) FROM llx_cpo_task')->fetchColumn();$conf->global->CPO_CUSTOMER_WORKSPACES_ENABLED=0;ok($svc->taskAllowed(202)&&$svc->boardProject(102)===null&&$svc->taskScopeSql('t')==='1=1','disabled flag restores legacy authorization paths');ok($db->pdo->query('SELECT COUNT(*) FROM llx_cpo_task')->fetchColumn()===$before,'compatibility flag leaves tasks intact');
echo "WORKSPACE_SQL_TESTS=$n PASS; private SQLite persistence, not native Dolibarr/MySQL UAT\n";
