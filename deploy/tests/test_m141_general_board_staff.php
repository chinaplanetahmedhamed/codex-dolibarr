<?php
/** Synthetic, in-memory tests of the real Boards service. No ERP credentials/data. */
define('MAIN_DB_PREFIX', 'test_');
require_once dirname(__DIR__, 2).'/custom/chinaplanetoperations/class/InternalTaskBoardsService.class.php';

final class M141Database
{
    public PDO $pdo;
    public function __construct() { $this->pdo = $GLOBALS['m141SyntheticPdo'] ?? new PDO('sqlite::memory:'); $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); }
    public function query($sql) {
        if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite') $sql=preg_replace('/ FOR UPDATE\s*$/', '', $sql);
        elseif (str_starts_with($sql, 'CREATE TABLE test_')) {
            $sql=str_replace('rowid INTEGER PRIMARY KEY', 'rowid INTEGER PRIMARY KEY AUTO_INCREMENT', $sql);
            if (!empty($GLOBALS['m141TemporaryTables'])) $sql=str_replace('CREATE TABLE test_', 'CREATE TEMPORARY TABLE test_', $sql);
        }
        return $this->pdo->query($sql);
    }
    public function fetch_object($result) { return $result->fetchObject(); }
    public function escape($value) { return substr($this->pdo->quote($value), 1, -1); }
    public function begin() { return $this->pdo->beginTransaction(); }
    public function commit() { return $this->pdo->commit(); }
    public function rollback() { return $this->pdo->inTransaction() ? $this->pdo->rollBack() : true; }
}
final class M141Actor
{
    public int $id = 1;
    public int $entity = 1;
    public int $statut = 1;
    public int $socid = 0;
    public int $admin = 1;
    public function hasRight(...$args) { return false; }
}
$conf = (object)['entity'=>1, 'global'=>(object)['CPO_BOARDS_ENABLED'=>1]];
$db = new M141Database();
$db->query('CREATE TABLE test_user (rowid INTEGER PRIMARY KEY, entity INT, statut INT, employee INT, fk_soc INT, admin INT DEFAULT 0, login TEXT, firstname TEXT, lastname TEXT)');
$db->query("INSERT INTO test_user VALUES
    (1,1,1,1,0,1,'manager','Manager',''),
    (2,1,1,1,NULL,0,'staff','موظف','الشركة'),
    (3,1,1,0,0,0,'integration','Integration',''),
    (4,1,1,1,90,0,'customer','Customer',''),
    (5,1,0,1,0,0,'inactive','Inactive',''),
    (6,2,1,1,0,0,'other-company','Other',''),
    (7,1,1,NULL,0,0,'unknown','Unknown',''),
    (8,1,1,1,91,0,'supplier','Supplier',''),
    (9,0,1,1,0,1,'global-staff','Global','Staff'),
    (10,0,1,1,0,0,'global-nonadmin','Shared','Nonadmin'),
    (11,0,1,1,90,1,'global-external','External','Admin'),
    (12,0,0,1,0,1,'global-inactive','Inactive','Admin'),
    (13,2,1,1,0,1,'foreign-admin','Other','Admin')");
$db->query('CREATE TABLE test_cpo_board (rowid INTEGER PRIMARY KEY, entity INT, title TEXT, fk_usergroup INT, archived INT, version INT)');
$db->query("INSERT INTO test_cpo_board VALUES (10,1,'Company board',1,0,1),(20,2,'Other company board',1,0,1)");
$db->query('CREATE TABLE test_cpo_board_background (entity INT, fk_board INT, image_hash TEXT)');
$db->query('CREATE TABLE test_cpo_board_member (rowid INTEGER PRIMARY KEY, entity INT, fk_board INT, fk_user INT, role TEXT)');
$db->query("INSERT INTO test_cpo_board_member VALUES (1,1,10,1,'manager'),(2,1,10,3,'viewer'),(3,1,10,5,'viewer'),(4,1,10,4,'viewer'),(5,2,20,6,'manager')");
$db->query('CREATE TABLE test_cpo_card_grant (entity INT, fk_task INT, fk_user INT, role TEXT)');
$db->query('CREATE TABLE test_cpo_board_card (rowid INT, entity INT, fk_board INT, fk_list INT, fk_task INT, position INT, archived INT)');
$db->query('INSERT INTO test_cpo_board_card VALUES (1,1,10,100,1000,1,0)');
$db->query('CREATE TABLE test_cpo_project_board (entity INT, fk_board INT, fk_project INT)');
$db->query('CREATE TABLE test_projet (rowid INT, entity INT, ref TEXT, title TEXT, fk_soc INT)');
$db->query('CREATE TABLE test_societe (rowid INT, entity INT, nom TEXT)');
$db->query('CREATE TABLE test_cpo_board_list (rowid INT, entity INT, fk_board INT, title TEXT, position INT, archived INT)');
$db->query('CREATE TABLE test_cpo_task (rowid INT, entity INT, title TEXT, ref TEXT, status TEXT, priority TEXT, version INT, date_archived TEXT, date_deadline TEXT, fk_user_assign INT)');
$svc = new InternalTaskBoardsService($db, new M141Actor(), 1);
$checks = 0;
function check141($condition, $label) { global $checks; if (!$condition) throw new RuntimeException('FAIL: '.$label); $checks++; }
function version141() { global $db; return (int)$db->fetch_object($db->query('SELECT version FROM test_cpo_board WHERE rowid=10'))->version; }
function share141($id, $role='viewer', $action='share_board') { global $svc; return $svc->mutate($action, ['board'=>10,'version'=>version141(),'user'=>$id,'role'=>$role,'task'=>1000]); }
function denied141(callable $operation, $label, $exception=InternalTaskAccessException::class) {
    global $db;
    $before=$db->pdo->query('SELECT * FROM test_cpo_board_member ORDER BY rowid')->fetchAll();
    $grants=$db->pdo->query('SELECT * FROM test_cpo_card_grant ORDER BY fk_task,fk_user')->fetchAll();
    $version=version141();$caught=false;
    try { $operation(); } catch (Throwable $e) { if (!$e instanceof $exception) throw $e; $caught=true; }
    check141($caught, $label);
    check141($before===$db->pdo->query('SELECT * FROM test_cpo_board_member ORDER BY rowid')->fetchAll() && $grants===$db->pdo->query('SELECT * FROM test_cpo_card_grant ORDER BY fk_task,fk_user')->fetchAll() && $version===version141(), $label.' leaves grants/version unchanged');
}

// staff-only mode must execute the real SQL, not a mock that pre-filters rows.
$offered=array_map('intval', array_column($svc->people(true), 'rowid'));sort($offered);
check141($offered === [1,2,9], 'active internal employees include native shared administrators');
check141(in_array(3, array_map('intval', array_column($svc->people(), 'rowid')), true), 'customer/supplier workspace internal picker retains compatibility');
foreach ([3,4,5,6,7,8,10,11,12,13,999] as $id) {
    denied141(fn()=>share141($id), 'reject ineligible Board invite '.$id);
    denied141(fn()=>share141($id,'editor','share_card'), 'reject ineligible card grant '.$id);
}
share141(2,'editor');
check141($db->fetch_object($db->query('SELECT role FROM test_cpo_board_member WHERE entity=1 AND fk_board=10 AND fk_user=2'))->role==='editor', 'employee membership persisted');
share141(9,'editor');
check141((bool)$db->fetch_object($db->query('SELECT * FROM test_cpo_board_member WHERE entity=1 AND fk_board=10 AND fk_user=9')), 'shared administrator employee can join a company Board');
if (empty($GLOBALS['m141TemporaryTables'])) check141(in_array(9,array_map('intval',array_column($svc->workspace(10)['members'],'fk_user')),true), 'shared administrator member remains visible in the Board response');
share141(9,'remove');
check141(!$db->fetch_object($db->query('SELECT * FROM test_cpo_board_member WHERE entity=1 AND fk_board=10 AND fk_user=9')), 'shared administrator membership can be removed');
share141(2,'viewer','share_card');
check141($db->fetch_object($db->query('SELECT role FROM test_cpo_card_grant WHERE entity=1 AND fk_task=1000 AND fk_user=2'))->role==='viewer', 'employee card grant persisted');
$db->query("INSERT INTO test_cpo_card_grant VALUES (1,1000,3,'viewer')");
share141(3,'remove','share_card');
check141(!$db->fetch_object($db->query('SELECT * FROM test_cpo_card_grant WHERE entity=1 AND fk_task=1000 AND fk_user=3')), 'legacy non-employee card grant removable');
share141(2,'remove');
check141(!$db->fetch_object($db->query('SELECT * FROM test_cpo_board_member WHERE entity=1 AND fk_board=10 AND fk_user=2')), 'employee access removable');
foreach ([3,4,5] as $id) {
    share141($id,'remove');
    check141(!$db->fetch_object($db->query('SELECT * FROM test_cpo_board_member WHERE entity=1 AND fk_board=10 AND fk_user='.$id)), 'excluded legacy member removable '.$id);
}
denied141(fn()=>share141(1,'remove'), 'last manager preserved', RuntimeException::class);
denied141(fn()=>share141(6,'remove'), 'another entity membership cannot be removed');
denied141(fn()=>$svc->mutate('share_board',['board'=>20,'version'=>1,'user'=>2,'role'=>'editor']), 'another entity board denied');
denied141(fn()=>$svc->mutate('share_board',['board'=>10,'version'=>0,'user'=>2,'role'=>'editor']), 'stale version denied', InternalTaskConflictException::class);
$viewer=new M141Actor();$viewer->id=2;$viewer->admin=0;
denied141(fn()=>(new InternalTaskBoardsService($db,$viewer,1))->mutate('share_board',['board'=>10,'version'=>version141(),'user'=>1,'role'=>'viewer']), 'native read/edit rights required');
$external=new M141Actor();$external->id=4;$external->socid=90;
denied141(fn()=>new InternalTaskBoardsService($db,$external,1), 'external actor denied even with admin flag');

// Customer project Boards retain the existing internal-user eligibility.
// MySQL 5.7 cannot reference one TEMPORARY table twice in a nested query;
// the compatibility case below runs on SQLite and the authenticated lab API.
if (!empty($GLOBALS['m141TemporaryTables'])) {
    echo "PASS M141: $checks native temporary-table permission/persistence checks\n";
    return;
}
$conf->global->CPO_CUSTOMER_WORKSPACES_ENABLED=1;
$db->query("INSERT INTO test_projet VALUES (30,1,'SYNTHETIC-30','Customer project',90)");
$db->query("INSERT INTO test_societe VALUES (90,1,'Synthetic customer')");
$db->query('INSERT INTO test_cpo_project_board VALUES (1,10,30)');
check141($svc->board(10)['staff_only_members']===false, 'customer project Board retains its existing sharing scope');
share141(3,'viewer');
check141((bool)$db->fetch_object($db->query('SELECT * FROM test_cpo_board_member WHERE entity=1 AND fk_board=10 AND fk_user=3')), 'customer project internal non-employee compatibility');
denied141(fn()=>share141(4), 'customer account still cannot join customer project internal Board');
echo "PASS M141: $checks permission/persistence checks\n";
