<?php

define('MAIN_DB_PREFIX', 'llx_');
require_once dirname(__DIR__, 2).'/custom/chinaplanetoperations/class/QcMigration.class.php';

class M074FakeResult
{
    public $rows;
    public $index = 0;
    public function __construct($rows) { $this->rows = $rows; }
}

class M074FakeDatabase
{
    public $beginResult = 1;
    public $commitResult = 1;
    public $rollbackResult = 1;
    public $failInsertAt = 0;
    public $insertAttempts = 0;
    public $rollbackCalls = 0;
    public $committed = array();
    public $pending = array();
    public $initialRows = array();

    public function escape($value) { return addslashes((string) $value); }
    public function begin() { return $this->beginResult; }
    public function commit()
    {
        if ($this->commitResult <= 0) return $this->commitResult;
        $this->committed = array_merge($this->committed, $this->pending);
        $this->pending = array();
        return $this->commitResult;
    }
    public function rollback()
    {
        $this->rollbackCalls++;
        if ($this->rollbackResult <= 0) return $this->rollbackResult;
        $this->pending = array();
        return $this->rollbackResult;
    }
    public function fetch_object($result)
    {
        if (!isset($result->rows[$result->index])) return null;
        return (object) $result->rows[$result->index++];
    }
    public function query($sql)
    {
        if (strpos($sql, 'SELECT id,entity,module,perms,bydefault') === 0) {
            preg_match('/id=([0-9]+)/', $sql, $idMatch);
            preg_match("/perms='([^']+)'/", $sql, $permsMatch);
            $id = isset($idMatch[1]) ? (int) $idMatch[1] : 0;
            $perms = isset($permsMatch[1]) ? $permsMatch[1] : '';
            $matches = array();
            foreach (array_merge($this->initialRows, $this->committed, $this->pending) as $row) {
                if ((int) $row['id'] === $id || ((string) $row['module'] === 'chinaplanetoperations' && (string) $row['perms'] === $perms)) $matches[] = $row;
            }
            return new M074FakeResult($matches);
        }
        if (strpos($sql, 'FROM information_schema.tables') !== false) return new M074FakeResult(array(array('count_rows' => 1)));
        if (strpos($sql, 'SELECT COUNT(*) count_rows') === 0) {
            preg_match('/id=([0-9]+)/', $sql, $idMatch);
            preg_match('/entity=([0-9]+)/', $sql, $entityMatch);
            preg_match("/perms='([^']+)'/", $sql, $permsMatch);
            $id = isset($idMatch[1]) ? (int) $idMatch[1] : 0;
            $entity = isset($entityMatch[1]) ? (int) $entityMatch[1] : 0;
            $perms = isset($permsMatch[1]) ? $permsMatch[1] : '';
            $count = 0;
            foreach (array_merge($this->initialRows, $this->committed, $this->pending) as $row) {
                if ((int) $row['id'] === $id && (int) $row['entity'] === $entity && (string) $row['module'] === 'chinaplanetoperations' && (string) $row['perms'] === $perms && (int) $row['bydefault'] === 0) $count++;
            }
            return new M074FakeResult(array(array('count_rows' => $count)));
        }
        if (strpos($sql, 'INSERT INTO llx_rights_def') === 0) {
            $this->insertAttempts++;
            if ($this->failInsertAt === $this->insertAttempts) return false;
            preg_match('/VALUES \(([0-9]+),([0-9]+),/', $sql, $matches);
            $codes = array(500528 => 'qc_plan', 500529 => 'qc_execute', 500530 => 'qc_review', 500531 => 'qc_publish');
            $id = (int) $matches[1];
            $entity = (int) $matches[2];
            $this->pending[] = array('id' => $id, 'entity' => $entity, 'module' => 'chinaplanetoperations', 'perms' => $codes[$id], 'bydefault' => 0);
            return true;
        }
        return false;
    }
}

function m074Module()
{
    $module = new stdClass();
    $module->rights_class = 'chinaplanetoperations';
    $module->name = 'ChinaPlanetOperations';
    $module->family = 'projects';
    $module->module_position = 90;
    $module->rights = array(
        array(500528, 'Plan QC', '', 0, 'qc_plan'),
        array(500529, 'Execute QC', '', 0, 'qc_execute'),
        array(500530, 'Review QC', '', 0, 'qc_review'),
        array(500531, 'Publish QC', '', 0, 'qc_publish'),
    );
    return $module;
}

function m074ExpectFailure($database, $message)
{
    try {
        (new QcMigration($database))->installRights(m074Module(), 1);
    } catch (Throwable $exception) {
        if (strpos($exception->getMessage(), $message) !== false) return;
        fwrite(STDERR, 'M074_RIGHTS_TRANSACTION=FAIL REASON=UNEXPECTED_EXCEPTION MESSAGE='.$exception->getMessage()."\n");
        exit(1);
    }
    fwrite(STDERR, "M074_RIGHTS_TRANSACTION=FAIL REASON=EXPECTED_FAILURE_MISSING\n");
    exit(1);
}

$beginFailure = new M074FakeDatabase();
$beginFailure->beginResult = 0;
m074ExpectFailure($beginFailure, 'begin QC permission transaction');
if ($beginFailure->insertAttempts !== 0 || $beginFailure->rollbackCalls !== 0) exit(2);

$commitFailure = new M074FakeDatabase();
$commitFailure->commitResult = 0;
m074ExpectFailure($commitFailure, 'commit QC permission transaction');
if ($commitFailure->insertAttempts !== 4 || $commitFailure->rollbackCalls !== 1 || $commitFailure->pending) exit(3);

$rollbackFailure = new M074FakeDatabase();
$rollbackFailure->failInsertAt = 2;
$rollbackFailure->rollbackResult = 0;
m074ExpectFailure($rollbackFailure, 'roll back QC permission transaction');
if ($rollbackFailure->insertAttempts !== 2 || $rollbackFailure->rollbackCalls !== 1) exit(4);

$success = new M074FakeDatabase();
if (!(new QcMigration($success))->installRights(m074Module(), 1) || count($success->committed) !== 4 || $success->pending) exit(5);

$entityTwoRows = array();
foreach (QcMigration::rightCodes() as $id => $code) {
    $entityTwoRows[] = array('id' => $id, 'entity' => 2, 'module' => 'chinaplanetoperations', 'perms' => $code, 'bydefault' => 0);
}
$multiEntity = new M074FakeDatabase();
$multiEntity->initialRows = $entityTwoRows;
$multiEntityMigration = new QcMigration($multiEntity);
if ($multiEntityMigration->rightsReady(1) || !$multiEntityMigration->rightsReady(2)) exit(6);
if (!$multiEntityMigration->installRights(m074Module(), 1) || count($multiEntity->committed) !== 4 || !$multiEntityMigration->rightsReady(1)) exit(7);

$collision = new M074FakeDatabase();
$collision->initialRows[] = array('id' => 500528, 'entity' => 2, 'module' => 'chinaplanetoperations', 'perms' => 'wrong_code', 'bydefault' => 0);
m074ExpectFailure($collision, 'right definition collision');
if ($collision->insertAttempts !== 0) exit(8);

echo "M074_RIGHTS_TRANSACTION=PASS BEGIN_FAIL=SAFE COMMIT_FAIL=ROLLED_BACK ROLLBACK_FAIL=EXPLICIT SUCCESS=4 MULTI_ENTITY=SAFE\n";
