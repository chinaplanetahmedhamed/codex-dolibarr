<?php

/**
 * Real MariaDB persistence test using a disposable schema-only clone.
 * The source local Dolibarr database is read-only; no business rows are copied.
 */
if (getenv('M077_REAL_LOCAL') !== '1') {
    fwrite(STDERR, "M077_REAL_LOCAL=FAIL REASON=EXPLICIT_LOCAL_GATE_REQUIRED\n");
    exit(1);
}
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
define('MAIN_DB_PREFIX', 'llx_');
$conf = (object) array('entity'=>1);
$repoRoot = dirname(__DIR__, 2);
$sourceConf = '/Users/mac/Documents/dev/dolibarr/dolibarr-Dev/dolibarr-23.0.3/htdocs/conf/conf.php';

function m077RealAssert($condition, $message) { if (!$condition) throw new RuntimeException($message); }
function m077RealConfig($path) {
    return (static function ($path) {
        include $path;
        return array('host'=>$dolibarr_main_db_host,'port'=>(int)$dolibarr_main_db_port,'name'=>$dolibarr_main_db_name,'user'=>$dolibarr_main_db_user,'pass'=>$dolibarr_main_db_pass);
    })($path);
}
function m077RealLocalHost($host) {
    return in_array(strtolower(trim((string) $host, " \t\n\r\0\x0B[]")), array('localhost','127.0.0.1','::1'), true);
}

final class M077DolDb
{
    private $mysqli;
    private $error = '';
    public function __construct(mysqli $mysqli) { $this->mysqli = $mysqli; }
    public function query($sql) { try { return $this->mysqli->query($sql); } catch (Throwable $e) { $this->error=$e->getMessage(); return false; } }
    public function fetch_object($result) { return $result instanceof mysqli_result ? $result->fetch_object() : null; }
    public function escape($value) { return $this->mysqli->real_escape_string((string) $value); }
    public function idate($value) { return date('Y-m-d H:i:s', is_numeric($value) ? (int) $value : strtotime((string) $value)); }
    public function last_insert_id($table = '') { return (int) $this->mysqli->insert_id; }
    public function begin() { return $this->mysqli->begin_transaction(); }
    public function commit() { return $this->mysqli->commit(); }
    public function rollback() { return $this->mysqli->rollback(); }
    public function lasterror() { return $this->error !== '' ? $this->error : $this->mysqli->error; }
}

require_once $repoRoot.'/custom/chinaplanetcustomercenter/class/CustomerFundsService.class.php';
require_once $repoRoot.'/custom/chinaplanetoperations/class/OperationShipmentService.class.php';

$source = m077RealConfig($sourceConf);
m077RealAssert(m077RealLocalHost($source['host']), 'remote source database forbidden');
$sourceDb = new mysqli($source['host'], $source['user'], $source['pass'], $source['name'], $source['port']);
$serverDb = null; $adminCredentials = null;
foreach (array(array('root','root'),array('root','')) as $credentials) {
    try { $serverDb = new mysqli($source['host'], $credentials[0], $credentials[1], '', $source['port']); $adminCredentials=$credentials; break; } catch (Throwable $e) { $serverDb = null; }
}
m077RealAssert($serverDb instanceof mysqli, 'local disposable database admin unavailable');
$database = 'm077_local_'.bin2hex(random_bytes(6));
m077RealAssert(preg_match('/^m077_local_[a-f0-9]{12}$/', $database) === 1, 'unsafe disposable database name');
$target = null; $created = false;

try {
    $serverDb->query("CREATE DATABASE ".$database." CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $created = true;
    $target = new mysqli($source['host'], $adminCredentials[0], $adminCredentials[1], $database, $source['port']);
    $target->query('SET FOREIGN_KEY_CHECKS=0');
    $requiredTables = array('llx_societe','llx_projet','llx_commande','llx_facture','llx_paiement','llx_paiement_facture','llx_cpo_operation','llx_cpo_operation_data','llx_cpo_activity','llx_chinaplanetlogistics','llx_cpl_shipment_event','llx_cpcc_financial_entry');
    foreach ($requiredTables as $table) {
        $result = $sourceDb->query("SHOW CREATE TABLE ".$table);
        $row = $result->fetch_row();
        m077RealAssert(!empty($row[1]), 'missing source schema '.$table);
        $target->query($row[1]);
    }
    $target->query('SET FOREIGN_KEY_CHECKS=1');
    foreach (array('llx_cpcc_customer_receipt.sql','llx_cpcc_fund_allocation.sql','llx_cpcc_fund_event.sql') as $file) {
        $sql = file_get_contents($repoRoot.'/custom/chinaplanetcustomercenter/sql/'.$file);
        m077RealAssert($sql !== false, 'missing migration '.$file);
        $target->query($sql);
        $target->query($sql);
    }

    $target->query("INSERT INTO llx_societe(rowid,entity,nom,status,client) VALUES(77001,1,'LOCAL TEST - M077 Customer',1,1),(77002,1,'LOCAL TEST - Other Customer',1,1)");
    $target->query("INSERT INTO llx_projet(rowid,entity,ref,title,fk_soc,fk_user_creat) VALUES(77003,1,'LOCAL-M077-PROJ','LOCAL TEST - M077 Project',77001,1)");
    $target->query("INSERT INTO llx_commande(rowid,entity,ref,fk_soc) VALUES(77004,1,'LOCAL-M077-ORDER',77001)");
    $target->query("INSERT INTO llx_facture(rowid,entity,ref,fk_soc,fk_projet,fk_statut,type,multicurrency_code,multicurrency_total_ttc,total_ttc) VALUES(77005,1,'LOCAL-M077-INV',77001,77003,1,0,'USD',10000,10000)");
    $target->query("INSERT INTO llx_paiement(rowid,entity,ref,datep,amount,multicurrency_amount,statut,fk_paiement) VALUES(77006,1,'LOCAL-M077-PAY','2026-09-12',10000,10000,1,1)");
    $target->query("INSERT INTO llx_paiement_facture(fk_facture,fk_paiement,amount,multicurrency_amount) VALUES(77005,77006,10000,10000)");
    $serviceJson = $target->real_escape_string(json_encode(array('pol'=>'NANSHA','pod'=>'SOKHNA','container_type'=>'40HQ','etd'=>'2026-10-01','eta'=>'2026-10-25')));
    $target->query("INSERT INTO llx_cpo_operation(rowid,entity,ref,service_type,title,fk_soc,fk_project,fk_commande,fk_user_assign,stage,status,fk_user_creat,date_creation) VALUES(77007,1,'CP-SB-LOCAL-M077','shipping_booking','LOCAL TEST - M077 Shipping',77001,77003,77004,1,'new',1,1,NOW())");
    $target->query("INSERT INTO llx_cpo_operation_data(entity,fk_operation,section_code,data_json,fk_user_modif) VALUES(1,77007,'service','".$serviceJson."',1)");
    $target->query("INSERT INTO llx_cpcc_financial_entry(rowid,entity,fk_soc,entry_date,entry_type,direction,description,amount,currency_code,source_type,source_id,customer_visible,status,fk_user_creat,date_creation) VALUES(77008,1,77001,NOW(),'supplier_payment','D','LOCAL TEST - actual posted debit',4000,'USD','supplier_payment',77009,0,1,1,NOW())");

    $db = new M077DolDb($target);
    $funds = new CustomerFundsService($db, 1);
    $receiptData = array('fk_soc'=>77001,'receipt_date'=>'2026-09-12','amount'=>10000,'currency_code'=>'USD','reference'=>'LOCAL-M077-RECEIPT','note_private'=>'LOCAL TEST only','request_key'=>hash('sha256','LOCAL TEST M077 receipt'));
    $receiptId = $funds->createPendingReceipt($receiptData, 1);
    m077RealAssert($receiptId === $funds->createPendingReceipt($receiptData, 1), 'receipt retry duplicated');
    $pending = $target->query("SELECT status,native_source_id FROM llx_cpcc_customer_receipt WHERE rowid=".$receiptId)->fetch_assoc();
    m077RealAssert($pending['status']==='pending' && $pending['native_source_id']===null, 'pending receipt claimed native evidence');
    $funds->confirmReceipt($receiptId, 77006, 1);
    $funds->confirmReceipt($receiptId, 77006, 1);

    $shipmentService = new OperationShipmentService($db, 1);
    $shipmentId = $shipmentService->createLinkedDraft(77007, 1);
    m077RealAssert($shipmentId === $shipmentService->createLinkedDraft(77007, 1), 'shipment retry duplicated');
    $shipment = $target->query("SELECT s.fk_statut,s.fk_soc,s.fk_project,s.fk_commande,o.fk_shipment FROM llx_chinaplanetlogistics s INNER JOIN llx_cpo_operation o ON o.rowid=77007 WHERE s.rowid=".$shipmentId)->fetch_assoc();
    m077RealAssert((int)$shipment['fk_statut']===0 && (int)$shipment['fk_soc']===77001 && (int)$shipment['fk_project']===77003 && (int)$shipment['fk_commande']===77004 && (int)$shipment['fk_shipment']===$shipmentId, 'draft shipment context mismatch');
    $event = $target->query("SELECT event_type,milestone_code,source_type,customer_visible FROM llx_cpl_shipment_event WHERE fk_shipment=".$shipmentId)->fetch_assoc();
    m077RealAssert($event['event_type']==='baseline' && $event['milestone_code']==='inquiry' && $event['source_type']==='operation_draft' && (int)$event['customer_visible']===0, 'shipment provenance mismatch');

    $allocAData = array('target_type'=>'operation','target_id'=>77007,'amount'=>3000,'request_key'=>hash('sha256','LOCAL TEST M077 A'),'note_private'=>'shipment A');
    $allocationA = $funds->reserve($receiptId, $allocAData, 1);
    m077RealAssert($allocationA === $funds->reserve($receiptId, $allocAData, 1), 'allocation retry duplicated');
    $allocationB = $funds->reserve($receiptId, array('target_type'=>'shipment','target_id'=>$shipmentId,'amount'=>2500,'request_key'=>hash('sha256','LOCAL TEST M077 B'),'note_private'=>'shipment B'), 1);
    $allocationC = $funds->reserve($receiptId, array('target_type'=>'customer_invoice','target_id'=>77005,'amount'=>1000,'request_key'=>hash('sha256','LOCAL TEST M077 C'),'note_private'=>'standalone service'), 1);
    $funds->settle($allocationA, 77008, 1);
    $funds->settle($allocationC, 77008, 1);
    $failedExcess = false;
    try { $funds->reserve($receiptId, array('target_type'=>'project','target_id'=>77003,'amount'=>4000,'request_key'=>hash('sha256','LOCAL TEST M077 EXCESS'),'note_private'=>'must fail'), 1); } catch (RuntimeException $e) { $failedExcess = strpos($e->getMessage(), 'exceeds') !== false; }
    m077RealAssert($failedExcess, 'excess allocation did not fail closed');
    $funds->reverseAllocation($allocationB, 'LOCAL TEST reservation released', 1);

    $counts = $target->query("SELECT (SELECT COUNT(*) FROM llx_cpcc_customer_receipt) receipts,(SELECT COUNT(*) FROM llx_cpcc_fund_allocation) allocations,(SELECT COUNT(*) FROM llx_cpcc_fund_event) events,(SELECT COUNT(*) FROM llx_chinaplanetlogistics) shipments")->fetch_assoc();
    m077RealAssert((int)$counts['receipts']===1 && (int)$counts['allocations']===3 && (int)$counts['events']===8 && (int)$counts['shipments']===1, 'persistence/idempotency counts incorrect '.json_encode($counts));
    $settled = (float) $target->query("SELECT COALESCE(SUM(amount),0) total FROM llx_cpcc_fund_allocation WHERE status='settled'")->fetch_assoc()['total'];
    $reserved = (float) $target->query("SELECT COALESCE(SUM(amount),0) total FROM llx_cpcc_fund_allocation WHERE status='reserved'")->fetch_assoc()['total'];
    m077RealAssert(abs($settled-4000.0)<0.00001 && abs($reserved)<0.00001, 'persisted reconciliation mismatch');
    echo "M077_REAL_LOCAL=PASS SOURCE_DB_MUTATED=NO RECEIPTS=1 ALLOCATIONS=3 SETTLED=4000 RESERVED=0 UNALLOCATED=6000 SHIPMENTS=1 SHIPMENT_STATUS=0 MILESTONE=inquiry\n";
} finally {
    if ($target instanceof mysqli) $target->close();
    if ($created) {
        m077RealAssert(preg_match('/^m077_local_[a-f0-9]{12}$/', $database) === 1, 'unsafe cleanup database name');
        $serverDb->query("DROP DATABASE ".$database);
        $absence = $serverDb->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME='".$serverDb->real_escape_string($database)."'");
        m077RealAssert($absence->num_rows===0, 'disposable database cleanup failed');
    }
}
