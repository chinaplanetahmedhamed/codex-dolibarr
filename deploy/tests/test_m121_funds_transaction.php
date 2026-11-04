<?php
/** Actual createPendingReceipt SQL on synthetic SQLite; API-failure tests, NOT concurrency proof. */
if (PHP_SAPI !== 'cli') exit(1);
error_reporting(E_ALL);
set_error_handler(function ($s,$m,$f,$l) { throw new ErrorException($m,0,$s,$f,$l); });
define('MAIN_DB_PREFIX','llx_');
$conf=(object)array('entity'=>1);
require_once dirname(__DIR__,2).'/custom/chinaplanetcustomercenter/class/CustomerFundsService.class.php';
class M121FundsRows { public $rows; public function __construct($rows) { $this->rows=$rows; } }
class M121FundsDb {
    public $pdo; public $failBegin=false; public $commitFailure=''; public $failEvent=false;
    public $held=0; public $releases=0; public $rollbacks=0; public $queries=array();
    public function __construct() {
        $this->pdo=new PDO('sqlite::memory:',null,null,array(PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION));
        $this->pdo->exec('CREATE TABLE llx_societe(rowid INTEGER PRIMARY KEY,entity INTEGER)');
        $this->pdo->exec('INSERT INTO llx_societe VALUES(7,1)');
        $this->pdo->exec('CREATE TABLE llx_cpcc_customer_receipt(rowid INTEGER PRIMARY KEY AUTOINCREMENT,entity INTEGER,fk_soc INTEGER,receipt_date TEXT,amount REAL,currency_code TEXT,reference TEXT,status TEXT,request_key TEXT,payload_hash TEXT,note_private TEXT,fk_user_creat INTEGER,date_creation TEXT,UNIQUE(entity,request_key))');
        $this->pdo->exec('CREATE TABLE llx_cpcc_fund_event(rowid INTEGER PRIMARY KEY AUTOINCREMENT,entity INTEGER,fk_soc INTEGER,object_type TEXT,object_id INTEGER,action_code TEXT,status_from TEXT,status_to TEXT,message TEXT,fk_user INTEGER,date_action TEXT)');
    }
    public function begin() { return $this->failBegin ? 0 : (int)$this->pdo->beginTransaction(); }
    public function commit() {
        if ($this->commitFailure==='before') return 0;
        $result=(int)$this->pdo->commit();
        return $this->commitFailure==='after' ? 0 : $result;
    }
    public function rollback() { $this->rollbacks++; return $this->pdo->inTransaction() ? (int)$this->pdo->rollBack() : 0; }
    public function escape($value) { return str_replace("'","''",$value); }
    public function idate($value) { return '2026-11-03 12:00:00'; }
    public function last_insert_id($table) { return (int)$this->pdo->lastInsertId(); }
    public function lasterror() { return 'Synthetic injected API failure'; }
    public function fetch_object($result) { return array_shift($result->rows); }
    public function query($sql) {
        $this->queries[]=$sql;
        // Metadata/control seam only; no fabricated financial rows or modified financial DML.
        if (strpos($sql,'SELECT GET_LOCK(')===0) { $this->held++; return new M121FundsRows(array((object)array('acquired'=>1))); }
        if (strpos($sql,'SELECT RELEASE_LOCK(')===0) { $this->held--; $this->releases++; return new M121FundsRows(array((object)array('released'=>1))); }
        if ($this->failEvent && strpos($sql,'INSERT INTO llx_cpcc_fund_event')===0) return false;
        $sql=preg_replace('/ FOR UPDATE$/','',$sql); // SQLite has no InnoDB row locks; no concurrency claim.
        $statement=$this->pdo->query($sql);
        return new M121FundsRows($statement->columnCount() ? $statement->fetchAll(PDO::FETCH_OBJ) : array());
    }
    public function count($table) { return (int)$this->pdo->query('SELECT COUNT(*) FROM '.$table)->fetchColumn(); }
}
$m121FundsChecks=0;
function m121f_assert($ok,$message) { $GLOBALS['m121FundsChecks']++; if (!$ok) throw new RuntimeException($message); }
function m121f_data() { return array('fk_soc'=>7,'receipt_date'=>'2026-11-03','amount'=>100,'currency_code'=>'USD','reference'=>'SYN','request_key'=>hash('sha256','M121 synthetic managed receipt')); }
function m121f_call($db,$data=null) {
    try { return array('id'=>(new CustomerFundsService($db,1))->createPendingReceipt($data ?? m121f_data(),9),'threw'=>false); }
    catch (RuntimeException $error) { return array('id'=>null,'threw'=>true,'message'=>$error->getMessage()); }
}
// Positive control: same request, same durable row/event; conflicting payload is rejected.
$db=new M121FundsDb(); $first=m121f_call($db); $same=m121f_call($db);
m121f_assert(!$first['threw'] && $first['id']>0 && $first['id']===$same['id'],'Identical receipt retry returns original identity');
m121f_assert($db->count('llx_cpcc_customer_receipt')===1 && $db->count('llx_cpcc_fund_event')===1,'Receipt and event created once');
$changed=m121f_data(); $changed['amount']=101;
m121f_assert(m121f_call($db,$changed)['threw'],'Changed same-key payload fails');
m121f_assert($db->count('llx_cpcc_customer_receipt')===1 && $db->held===0,'Payload conflict preserves row and releases lock');

$db=new M121FundsDb(); $db->failBegin=true;
$r=m121f_call($db);
m121f_assert($r['threw'],'Failed BEGIN must throw before receipt insert');
m121f_assert($db->count('llx_cpcc_customer_receipt')===0 && $db->count('llx_cpcc_fund_event')===0,'Failed BEGIN creates no autocommitted receipt/event');
m121f_assert($db->held===0 && $db->releases===1 && !$db->pdo->inTransaction(),'Failed BEGIN releases request lock');
m121f_assert($db->rollbacks===0,'Failed BEGIN must not roll back an unowned transaction');

$db=new M121FundsDb(); $db->commitFailure='before';
$r=m121f_call($db);
m121f_assert($r['threw'],'Failed COMMIT before persistence cannot report success');
m121f_assert($db->count('llx_cpcc_customer_receipt')===0 && $db->count('llx_cpcc_fund_event')===0,'Failed COMMIT rolls back pending row and event');
m121f_assert($db->rollbacks===1 && !$db->pdo->inTransaction() && $db->held===0,'Failed COMMIT cleans owned transaction and request lock');
$db->commitFailure=''; $recovered=m121f_call($db);
m121f_assert(!$recovered['threw'] && $db->count('llx_cpcc_customer_receipt')===1 && $db->count('llx_cpcc_fund_event')===1,'Same-key retry after rollback creates exactly once');

$db=new M121FundsDb(); $db->commitFailure='after';
$r=m121f_call($db);
m121f_assert($r['threw'],'Lost acknowledgement after underlying COMMIT must not report success');
m121f_assert($db->count('llx_cpcc_customer_receipt')===1 && $db->count('llx_cpcc_fund_event')===1,'Lost acknowledgement preserves actual committed records');
m121f_assert(!$db->pdo->inTransaction() && $db->held===0,'Lost acknowledgement releases control without inventing rollback');
$durable=(int)$db->pdo->query('SELECT rowid FROM llx_cpcc_customer_receipt')->fetchColumn();
$db->commitFailure=''; $retry=m121f_call($db);
m121f_assert(!$retry['threw'] && $retry['id']===$durable,'Same-key retry reconciles committed receipt identity');
m121f_assert($db->count('llx_cpcc_customer_receipt')===1 && $db->count('llx_cpcc_fund_event')===1,'Lost-ack retry does not duplicate event or receipt');

// The existing-row branch must check its COMMIT result too.
foreach (array('before','after') as $failure) {
    $db=new M121FundsDb(); $id=m121f_call($db)['id']; $db->commitFailure=$failure;
    m121f_assert(m121f_call($db)['threw'],'Existing retry COMMIT '.$failure.' failure must throw');
    m121f_assert($db->count('llx_cpcc_customer_receipt')===1 && $db->count('llx_cpcc_fund_event')===1,'Existing retry failure preserves original committed records');
    m121f_assert(!$db->pdo->inTransaction() && $db->held===0,'Existing retry failure releases transaction/lock');
    $db->commitFailure=''; m121f_assert(m121f_call($db)['id']===$id,'Existing retry recovers same identity');
}
$db=new M121FundsDb(); $db->failEvent=true;
m121f_assert(m121f_call($db)['threw'] && $db->count('llx_cpcc_customer_receipt')===0,'Event failure atomically rolls back receipt');
m121f_assert($db->held===0 && !$db->pdo->inTransaction(),'Event failure releases owned transaction and lock');
echo 'M121 funds transaction regressions passed ('.$m121FundsChecks." checks; SQLite API failure injection, not concurrency proof)\n";
