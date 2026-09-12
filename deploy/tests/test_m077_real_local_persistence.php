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
    private $afterQuery;
    public function __construct(mysqli $mysqli) { $this->mysqli = $mysqli; }
    public function afterNextMatchingQuery($needle, callable $callback) { $this->afterQuery=array($needle,$callback); }
    public function query($sql) { try { $result=$this->mysqli->query($sql); if($this->afterQuery&&strpos($sql,$this->afterQuery[0])!==false){$callback=$this->afterQuery[1];$this->afterQuery=null;$callback();} return $result; } catch (Throwable $e) { $this->error=$e->getMessage(); return false; } }
    public function fetch_object($result) { return $result instanceof mysqli_result ? $result->fetch_object() : null; }
    public function num_rows($result) { return $result instanceof mysqli_result ? $result->num_rows : 0; }
    public function escape($value) { return $this->mysqli->real_escape_string((string) $value); }
    public function idate($value) { return date('Y-m-d H:i:s', is_numeric($value) ? (int) $value : strtotime((string) $value)); }
    public function last_insert_id($table = '') { return (int) $this->mysqli->insert_id; }
    public function begin() { return $this->mysqli->begin_transaction(); }
    public function commit() { return $this->mysqli->commit(); }
    public function rollback() { return $this->mysqli->rollback(); }
    public function lasterror() { return $this->error !== '' ? $this->error : $this->mysqli->error; }
}

require_once $repoRoot.'/custom/chinaplanetcustomercenter/class/CustomerFundsService.class.php';
require_once $repoRoot.'/custom/chinaplanetcustomercenter/class/ManagedFundsNativeGuard.class.php';
require_once $repoRoot.'/custom/chinaplanetoperations/class/OperationShipmentService.class.php';
require_once $repoRoot.'/custom/chinaplanetcustomercenter/lib/export.lib.php';

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
    $requiredTables = array('llx_user','llx_societe','llx_projet','llx_commande','llx_facture','llx_facturedet','llx_paiement','llx_paiement_facture','llx_societe_remise_except','llx_cpo_operation','llx_cpo_operation_data','llx_cpo_activity','llx_chinaplanetlogistics','llx_cpl_shipment_event','llx_cpcc_financial_entry','llx_cpcc_export_document','llx_cpcc_export_document_line');
    foreach ($requiredTables as $table) {
        $result = $sourceDb->query("SHOW CREATE TABLE ".$table);
        $row = $result->fetch_row();
        m077RealAssert(!empty($row[1]), 'missing source schema '.$table);
        $target->query($row[1]);
    }
    $visibilityColumn=$target->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA='".$target->real_escape_string($database)."' AND TABLE_NAME='llx_chinaplanetlogistics' AND COLUMN_NAME='customer_visible'");
    if ($visibilityColumn->num_rows===0) $target->query("ALTER TABLE llx_chinaplanetlogistics ADD COLUMN customer_visible tinyint NOT NULL DEFAULT 1 AFTER customer_note");
    $target->query('SET FOREIGN_KEY_CHECKS=1');
    foreach (array('llx_cpcc_customer_receipt.sql','llx_cpcc_fund_allocation.sql','llx_cpcc_fund_event.sql','llx_cpcc_guided_receipt.sql') as $file) {
        $sql = file_get_contents($repoRoot.'/custom/chinaplanetcustomercenter/sql/'.$file);
        m077RealAssert($sql !== false, 'missing migration '.$file);
        $target->query($sql);
        $target->query($sql);
    }

    $target->query("INSERT INTO llx_user(rowid,entity,login,lastname,admin,statut) VALUES(1,1,'m077-local-test','M077 LOCAL TEST',1,1)");
    $target->query("INSERT INTO llx_societe(rowid,entity,nom,status,client) VALUES(77001,1,'LOCAL TEST - M077 Customer',1,1),(77002,1,'LOCAL TEST - Other Customer',1,1)");
    $target->query("INSERT INTO llx_projet(rowid,entity,ref,title,fk_soc,fk_user_creat) VALUES(77003,1,'LOCAL-M077-PROJ','LOCAL TEST - M077 Project',77001,1),(77012,1,'LOCAL-M077-OTHER-PROJ','LOCAL TEST - Other Project',77002,1)");
    $target->query("INSERT INTO llx_commande(rowid,entity,ref,fk_soc) VALUES(77004,1,'LOCAL-M077-ORDER',77001),(77013,1,'LOCAL-M077-OTHER-ORDER',77002)");
    $target->query("INSERT INTO llx_facture(rowid,entity,ref,fk_soc,fk_projet,fk_statut,type,multicurrency_code,multicurrency_total_ttc,total_ttc) VALUES(77005,1,'LOCAL-M077-DEPOSIT',77001,77003,1,3,'USD',10000,10000),(77015,1,'LOCAL-M077-USD-INV',77001,77003,1,0,'USD',5000,5000),(77016,1,'LOCAL-M077-EUR-INV',77001,77003,1,0,'EUR',5000,5000),(77017,1,'LOCAL-M077-ORDINARY',77001,77003,1,0,'USD',1000,1000),(77025,1,'LOCAL-M077-COVERAGE-DEPOSIT',77001,77003,1,3,'USD',12000,12000)");
    $target->query("INSERT INTO llx_paiement(rowid,entity,ref,datep,amount,multicurrency_amount,statut,fk_paiement) VALUES(77006,1,'LOCAL-M077-PAY','2026-09-12',10000,10000,1,1)");
    $target->query("INSERT INTO llx_paiement_facture(fk_facture,fk_paiement,amount,multicurrency_amount) VALUES(77005,77006,10000,10000)");
    $target->query("INSERT INTO llx_paiement(rowid,entity,ref,datep,amount,multicurrency_amount,statut,fk_paiement) VALUES(77018,1,'LOCAL-M077-ORD-PAY','2026-09-12',1000,1000,1,1)");
    $target->query("INSERT INTO llx_paiement_facture(fk_facture,fk_paiement,amount,multicurrency_amount) VALUES(77017,77018,1000,1000)");
    $target->query("INSERT INTO llx_paiement(rowid,entity,ref,datep,amount,multicurrency_amount,statut,fk_paiement) VALUES(77026,1,'LOCAL-M077-COVERAGE-PAY','2026-09-12',12000,12000,1,1)");
    $target->query("INSERT INTO llx_paiement_facture(fk_facture,fk_paiement,amount,multicurrency_amount) VALUES(77025,77026,12000,12000)");
    $serviceJson = $target->real_escape_string(json_encode(array('pol'=>'NANSHA','pod'=>'SOKHNA','container_type'=>'40HQ','etd'=>'2026-10-01','eta'=>'2026-10-25')));
    $target->query("INSERT INTO llx_cpo_operation(rowid,entity,ref,service_type,title,fk_soc,fk_project,fk_commande,fk_user_assign,stage,status,fk_user_creat,date_creation) VALUES(77007,1,'CP-SB-LOCAL-M077-A','shipping_booking','LOCAL TEST - M077 Shipping A',77001,77003,77004,1,'new',1,1,NOW()),(77023,1,'CP-SB-LOCAL-M077-B','shipping_booking','LOCAL TEST - M077 Shipping B',77001,77003,77004,1,'new',1,1,NOW())");
    $target->query("INSERT INTO llx_cpo_operation_data(entity,fk_operation,section_code,data_json,fk_user_modif) VALUES(1,77007,'service','".$serviceJson."',1),(1,77023,'service','".$serviceJson."',1)");
    $target->query("INSERT INTO llx_cpcc_financial_entry(rowid,entity,fk_soc,fk_project,entry_date,entry_type,direction,description,amount,currency_code,source_type,source_id,customer_visible,status,fk_user_creat,date_creation) VALUES(77008,1,77001,77003,NOW(),'supplier_payment','D','LOCAL TEST - actual posted debit',4000,'USD','operation',77007,0,1,1,NOW()),(77019,1,77001,77003,NOW(),'supplier_payment','D','LOCAL TEST - unrelated debit',4000,'USD','project',77003,0,1,1,NOW())");

    $db = new M077DolDb($target);
    m077RealAssert(ManagedFundsNativeGuard::install($db),'native-credit guard installation failed');
    m077RealAssert(ManagedFundsNativeGuard::verify($db),'native-credit guard did not verify after installation');
    $target->query('DROP TRIGGER llx_cpcc_m077_credit_insert');
    $target->query('CREATE TRIGGER llx_cpcc_m077_credit_insert BEFORE INSERT ON llx_societe_remise_except FOR EACH ROW SET @m077_noop=1');
    m077RealAssert(!ManagedFundsNativeGuard::verify($db),'same-name no-op trigger passed definition verification');
    m077RealAssert(!ManagedFundsNativeGuard::install($db),'install accepted same-name changed trigger');
    m077RealAssert(ManagedFundsNativeGuard::repair($db)&&ManagedFundsNativeGuard::verify($db),'guard repair did not restore reviewed definitions');
    $guidedToken='CPMF-'.str_repeat('A',40);$guidedPayload=hash('sha256','guided fixture');
    $target->query("INSERT INTO llx_cpcc_guided_receipt(entity,fk_soc,fk_invoice,origin_type,origin_id,request_token,payload_hash,status,fk_user_creat,date_creation) VALUES(1,77001,77025,'commande',77004,'".$guidedToken."','".$guidedPayload."','invoice_ready',1,NOW())");
    $target->query("INSERT INTO llx_paiement(rowid,entity,ref,datep,amount,multicurrency_amount,statut,fk_paiement,num_paiement) VALUES(77028,1,'LOCAL-M077-NATIVE-RACE','2026-09-12',1,1,1,1,'NATIVE'),(77029,1,'LOCAL-M077-GUIDED-RACE','2026-09-12',1,1,1,1,'GUIDED'),(77030,1,'LOCAL-M077-ORDINARY-EXTRA','2026-09-12',1,1,1,1,'ORDINARY')");
    $nativePaymentDb=new mysqli($source['host'],$adminCredentials[0],$adminCredentials[1],$database,$source['port']);
    $nativeGuidedRejected=false;try{$nativePaymentDb->query("INSERT INTO llx_paiement_facture(fk_facture,fk_paiement,amount,multicurrency_amount) VALUES(77025,77028,1,1)");}catch(mysqli_sql_exception $e){$nativeGuidedRejected=strpos($e->getMessage(),'Guided deposit receipt is protected')!==false;}
    m077RealAssert($nativeGuidedRejected,'native payment linked to a guided deposit invoice outside the authorized session');
    $target->query("SET @cpcc_guided_receipt_token='".$guidedToken."'");$target->query("INSERT INTO llx_paiement_facture(fk_facture,fk_paiement,amount,multicurrency_amount) VALUES(77025,77029,1,1)");$target->query("SET @cpcc_guided_receipt_token=NULL");
    $nativePaymentDb->query("INSERT INTO llx_paiement_facture(fk_facture,fk_paiement,amount,multicurrency_amount) VALUES(77017,77030,1,1)");
    $nativePaymentDb->close();
    $target->query("DELETE FROM llx_paiement_facture WHERE fk_paiement IN (77029,77030)");$target->query("DELETE FROM llx_paiement WHERE rowid IN (77028,77029,77030)");$target->query("DELETE FROM llx_cpcc_guided_receipt WHERE fk_invoice=77025");
    $funds = new CustomerFundsService($db, 1);
    $fixtureKey=hash('sha256','LOCAL TEST M077 settlement coverage fixture');
    $target->query("INSERT INTO llx_cpcc_customer_receipt(rowid,entity,fk_soc,receipt_date,amount,currency_code,reference,status,native_source_type,native_source_id,request_key,payload_hash,fk_user_creat,fk_user_confirm,date_confirmation,date_creation) VALUES(77110,1,77001,'2026-09-12',12000,'USD','LOCAL-M077-COVERAGE','confirmed','customer_payment',77026,'".$fixtureKey."','".$fixtureKey."',1,1,NOW(),NOW())");
    $target->query("INSERT INTO llx_societe_remise_except(entity,fk_soc,datec,amount_ht,amount_ttc,fk_user,fk_facture,fk_facture_source,description,multicurrency_code,multicurrency_amount_ht,multicurrency_amount_ttc) VALUES(1,77001,NOW(),6500,6500,1,77015,77025,'LOCAL TEST - coverage native use','USD',6500,6500)");
    $target->query("INSERT INTO llx_cpcc_fund_allocation(rowid,entity,fk_receipt,fk_soc,target_type,target_id,fk_project,amount,currency_code,status,disbursement_type,disbursement_id,request_key,payload_hash,fk_user_creat,date_creation) VALUES(77111,1,77110,77001,'project',77003,77003,3000,'USD','settled','financial_entry',77019,'".hash('sha256','coverage A')."','".hash('sha256','coverage A payload')."',1,NOW()),(77112,1,77110,77001,'project',77003,77003,2500,'USD','reserved',NULL,NULL,'".hash('sha256','coverage B')."','".hash('sha256','coverage B payload')."',1,NOW()),(77113,1,77110,77001,'project',77003,77003,1000,'USD','settled','financial_entry',77019,'".hash('sha256','coverage C')."','".hash('sha256','coverage C payload')."',1,NOW())");
    $coverageRejected=false;
    try{$funds->settle(77112,77019,1);}catch(RuntimeException $e){$coverageRejected=strpos($e->getMessage(),'settled spending plus this settlement exceeds')!==false;}
    m077RealAssert($coverageRejected,'settlement ignored a later-row already-settled allocation');
    m077RealAssert(!ManagedFundsNativeGuard::remove($db)&&ManagedFundsNativeGuard::verify($db),'active allocations did not block guard removal');
    $target->query('DELETE FROM llx_cpcc_fund_allocation WHERE fk_receipt=77110');
    $target->query('DELETE FROM llx_societe_remise_except WHERE fk_facture_source=77025');
    $target->query('DELETE FROM llx_cpcc_customer_receipt WHERE rowid=77110');
    $receiptData = array('fk_soc'=>77001,'receipt_date'=>'2026-09-12','amount'=>10000,'currency_code'=>'USD','reference'=>'LOCAL-M077-RECEIPT','note_private'=>'LOCAL TEST only','request_key'=>hash('sha256','LOCAL TEST M077 receipt'));
    $receiptId = $funds->createPendingReceipt($receiptData, 1);
    m077RealAssert($receiptId === $funds->createPendingReceipt($receiptData, 1), 'receipt retry duplicated');
    $pending = $target->query("SELECT status,native_source_id FROM llx_cpcc_customer_receipt WHERE rowid=".$receiptId)->fetch_assoc();
    m077RealAssert($pending['status']==='pending' && $pending['native_source_id']===null, 'pending receipt claimed native evidence');
    $funds->confirmReceipt($receiptId, 77006, 1);
    $funds->confirmReceipt($receiptId, 77006, 1);
    $target->query("UPDATE llx_paiement SET statut=9 WHERE rowid=77006");
    $invalidatedRejected = false;
    try { $funds->reserve($receiptId, array('target_type'=>'project','target_id'=>77003,'amount'=>100,'request_key'=>hash('sha256','LOCAL TEST M077 invalid evidence')), 1); } catch (RuntimeException $e) { $invalidatedRejected = strpos($e->getMessage(), 'no longer valid') !== false; }
    m077RealAssert($invalidatedRejected, 'invalidated native payment remained allocatable');
    $target->query("UPDATE llx_paiement SET statut=1 WHERE rowid=77006");
    $ordinaryData = array('fk_soc'=>77001,'receipt_date'=>'2026-09-12','amount'=>1000,'currency_code'=>'USD','reference'=>'LOCAL-M077-ORDINARY','request_key'=>hash('sha256','LOCAL TEST M077 ordinary'));
    $ordinaryReceipt = $funds->createPendingReceipt($ordinaryData, 1);
    $ordinaryRejected = false;
    try { $funds->confirmReceipt($ordinaryReceipt, 77018, 1); } catch (RuntimeException $e) { $ordinaryRejected = true; }
    m077RealAssert($ordinaryRejected, 'ordinary invoice payment became managed money');
    $target->query("INSERT INTO llx_societe_remise_except(entity,fk_soc,datec,amount_ht,amount_ttc,fk_user,fk_facture,fk_facture_source,description,multicurrency_code,multicurrency_amount_ht,multicurrency_amount_ttc) VALUES(1,77001,NOW(),2000,2000,1,77015,77005,'LOCAL TEST - native deposit consumption','USD',2000,2000)");
    $initialReconciliation=$funds->reconcileReceipt($receiptId);
    m077RealAssert(abs((float)$initialReconciliation['native_consumed']-2000.0)<0.00001 && abs((float)$initialReconciliation['available']-8000.0)<0.00001,'native consumption not reflected in authoritative availability');
    $target->query("INSERT INTO llx_societe_remise_except(entity,fk_soc,datec,amount_ht,amount_ttc,fk_user,fk_facture_source,description,multicurrency_code,multicurrency_amount_ht,multicurrency_amount_ttc) VALUES(1,77001,NOW(),100,100,1,77005,'LOCAL TEST - race candidate','USD',100,100)");
    $raceCreditId=(int)$target->insert_id;
    $raceDb=new mysqli($source['host'],$adminCredentials[0],$adminCredentials[1],$database,$source['port']);
    $db->afterNextMatchingQuery('cpcc_customer_receipt WHERE entity=1 AND rowid='.$receiptId.' LIMIT 1',function()use($raceDb,$raceCreditId){$raceDb->query('UPDATE llx_societe_remise_except SET fk_facture=77015 WHERE rowid='.$raceCreditId);});
    $raceRejected=false;
    try{$funds->reserve($receiptId,array('target_type'=>'project','target_id'=>77003,'amount'=>8000,'request_key'=>hash('sha256','LOCAL TEST M077 exact snapshot race')),1);}catch(RuntimeException $e){$raceRejected=strpos($e->getMessage(),'native Dolibarr credit use')!==false;}
    m077RealAssert($raceRejected,'allocation used the stale snapshot created before concurrent native credit use');
    m077RealAssert((int)$target->query('SELECT fk_facture FROM llx_societe_remise_except WHERE rowid='.$raceCreditId)->fetch_assoc()['fk_facture']===77015,'deterministic native race writer did not commit');
    $target->query('UPDATE llx_societe_remise_except SET fk_facture=NULL WHERE rowid='.$raceCreditId);
    $raceDb->close();

    $shipmentService = new OperationShipmentService($db, 1);
    $target->query("INSERT INTO llx_cpo_operation(rowid,entity,ref,service_type,title,fk_soc,fk_project,fk_commande,fk_user_assign,stage,status,fk_user_creat,date_creation) VALUES(77021,1,'CP-SB-M077-BAD-PROJ','shipping_booking','LOCAL TEST - wrong project',77001,77012,77004,1,'new',1,1,NOW()),(77022,1,'CP-SB-M077-BAD-ORDER','shipping_booking','LOCAL TEST - wrong order',77001,77003,77013,1,'new',1,1,NOW())");
    $target->query("INSERT INTO llx_cpo_operation_data(entity,fk_operation,section_code,data_json,fk_user_modif) VALUES(1,77021,'service','".$serviceJson."',1),(1,77022,'service','".$serviceJson."',1)");
    foreach (array(77021,77022) as $invalidOperationId) {
        $ownershipRejected=false;
        try { $shipmentService->createLinkedDraft($invalidOperationId,1); } catch (RuntimeException $e) { $ownershipRejected=strpos($e->getMessage(),'does not belong')!==false; }
        m077RealAssert($ownershipRejected,'invalid project/order ownership created shipment');
    }
    $shipmentId = $shipmentService->createLinkedDraft(77007, 1);
    m077RealAssert($shipmentId === $shipmentService->createLinkedDraft(77007, 1), 'shipment retry duplicated');
    $shipmentIdB=$shipmentService->createLinkedDraft(77023,1);
    m077RealAssert($shipmentIdB!==$shipmentId && $shipmentIdB===$shipmentService->createLinkedDraft(77023,1),'second shipment was not independently idempotent');
    $shipment = $target->query("SELECT s.fk_statut,s.fk_soc,s.fk_project,s.fk_commande,s.customer_visible,o.fk_shipment FROM llx_chinaplanetlogistics s INNER JOIN llx_cpo_operation o ON o.rowid=77007 WHERE s.rowid=".$shipmentId)->fetch_assoc();
    m077RealAssert((int)$shipment['fk_statut']===0 && (int)$shipment['fk_soc']===77001 && (int)$shipment['fk_project']===77003 && (int)$shipment['fk_commande']===77004 && (int)$shipment['customer_visible']===0 && (int)$shipment['fk_shipment']===$shipmentId, 'draft shipment context or visibility mismatch');
    $event = $target->query("SELECT event_type,milestone_code,source_type,customer_visible FROM llx_cpl_shipment_event WHERE fk_shipment=".$shipmentId)->fetch_assoc();
    m077RealAssert($event['event_type']==='baseline' && $event['milestone_code']==='inquiry' && $event['source_type']==='operation_draft' && (int)$event['customer_visible']===0, 'shipment provenance mismatch');
    $target->query("INSERT INTO llx_cpcc_export_document(entity,fk_soc,fk_project,fk_shipment,document_type,ref,document_date,currency_code,total_amount,port_loading,port_destination,customer_visible,status,fk_user_creat,date_creation) VALUES(1,77001,77003,".$shipmentId.",'commercial_invoice','LOCAL-M077-PRIVATE-EXPORT','2026-09-12','USD',1000,'NANSHA','SOKHNA',1,1,1,NOW())");
    $privateDocumentId=(int)$target->insert_id;
    $target->query("INSERT INTO llx_cpcc_export_document_line(fk_document,position,description,quantity,unit_price,total_amount,date_creation) VALUES(".$privateDocumentId.",1,'LOCAL TEST private shipment item',1,1000,1000,NOW())");
    m077RealAssert(cpcc_export_fetch($db,$privateDocumentId,77001,true)===null,'private shipment export detail leaked');
    m077RealAssert(count(cpcc_export_documents($db,77001,0,0,true))===0,'private shipment export list leaked');

    $allocAData = array('target_type'=>'operation','target_id'=>77007,'amount'=>3000,'request_key'=>hash('sha256','LOCAL TEST M077 A'),'note_private'=>'shipment A');
    $allocationA = $funds->reserve($receiptId, $allocAData, 1);
    m077RealAssert($allocationA === $funds->reserve($receiptId, $allocAData, 1), 'allocation retry duplicated');
    $allocationB = $funds->reserve($receiptId, array('target_type'=>'shipment','target_id'=>$shipmentIdB,'amount'=>2500,'request_key'=>hash('sha256','LOCAL TEST M077 B'),'note_private'=>'shipment B'), 1);
    $wrongCustomerRejected=false;
    try { $funds->reserve($receiptId,array('target_type'=>'project','target_id'=>77012,'amount'=>100,'request_key'=>hash('sha256','LOCAL TEST M077 other customer')),1); } catch (RuntimeException $e) { $wrongCustomerRejected=strpos($e->getMessage(),'does not belong')!==false; }
    m077RealAssert($wrongCustomerRejected,'other-customer allocation target accepted');
    $currencyRejected = false;
    try { $funds->reserve($receiptId, array('target_type'=>'customer_invoice','target_id'=>77016,'amount'=>1000,'request_key'=>hash('sha256','LOCAL TEST M077 EUR'),'note_private'=>'must fail'), 1); } catch (RuntimeException $e) { $currencyRejected = strpos($e->getMessage(), 'currency') !== false; }
    m077RealAssert($currencyRejected, 'invoice currency mismatch did not fail closed');
    $allocationC = $funds->reserve($receiptId, array('target_type'=>'customer_invoice','target_id'=>77015,'amount'=>1000,'request_key'=>hash('sha256','LOCAL TEST M077 C'),'note_private'=>'standalone service'), 1);
    $changedPayloadRejected = false;
    try { $funds->reserve($receiptId, array('target_type'=>'operation','target_id'=>77007,'amount'=>3001,'request_key'=>hash('sha256','LOCAL TEST M077 A'),'note_private'=>'shipment A'), 1); } catch (RuntimeException $e) { $changedPayloadRejected = strpos($e->getMessage(), 'payload changed') !== false; }
    m077RealAssert($changedPayloadRejected, 'changed allocation retry payload accepted');
    $unrelatedDebitRejected = false;
    try { $funds->settle($allocationA, 77019, 1); } catch (RuntimeException $e) { $unrelatedDebitRejected = strpos($e->getMessage(), 'exact allocation target') !== false; }
    m077RealAssert($unrelatedDebitRejected, 'unrelated debit settled allocation');
    $funds->settle($allocationA, 77008, 1);
    $target->query("INSERT INTO llx_cpcc_financial_entry(rowid,entity,fk_soc,fk_project,entry_date,entry_type,direction,description,amount,currency_code,source_type,source_id,customer_visible,status,fk_user_creat,date_creation) VALUES(77020,1,77001,77003,NOW(),'service','D','LOCAL TEST - invoice debit',1000,'USD','customer_invoice',77015,0,1,1,NOW())");
    $funds->settle($allocationC, 77020, 1);
    $allocationD=$funds->reserve($receiptId,array('target_type'=>'operation','target_id'=>77007,'amount'=>1001,'request_key'=>hash('sha256','LOCAL TEST M077 settlement ceiling')),1);
    $settlementCeilingRejected=false;
    try { $funds->settle($allocationD,77008,1); } catch (RuntimeException $e) { $settlementCeilingRejected=strpos($e->getMessage(),'exceed')!==false; }
    m077RealAssert($settlementCeilingRejected,'settlement ceiling was not enforced');
    $funds->reverseAllocation($allocationD,'LOCAL TEST ceiling reservation released',1);
    $nativeConcurrentRejected=false;
    try { $target->query("INSERT INTO llx_societe_remise_except(entity,fk_soc,datec,amount_ht,amount_ttc,fk_user,fk_facture,fk_facture_source,description,multicurrency_code,multicurrency_amount_ht,multicurrency_amount_ttc) VALUES(1,77001,NOW(),100,100,1,77015,77005,'LOCAL TEST - forbidden concurrent native use','USD',100,100)"); } catch (mysqli_sql_exception $e) { $nativeConcurrentRejected=strpos($e->getMessage(),'Managed funds are active')!==false; }
    m077RealAssert($nativeConcurrentRejected,'native deposit use committed while managed allocations were active');
    $consumedCreditId=(int)$target->query("SELECT rowid FROM llx_societe_remise_except WHERE fk_facture_source=77005 AND fk_facture=77015 ORDER BY rowid LIMIT 1")->fetch_assoc()['rowid'];
    $target->query("UPDATE llx_societe_remise_except SET description='LOCAL TEST - metadata correction allowed' WHERE rowid=".$consumedCreditId);
    $nativeAmountChangeRejected=false;
    try{$target->query("UPDATE llx_societe_remise_except SET multicurrency_amount_ttc=2100 WHERE rowid=".$consumedCreditId);}catch(mysqli_sql_exception $e){$nativeAmountChangeRejected=strpos($e->getMessage(),'Managed funds are active')!==false;}
    m077RealAssert($nativeAmountChangeRejected,'native linked-credit accounting amount changed while managed allocations were active');
    $nativeUseRejected = false;
    try { $funds->reserve($receiptId, array('target_type'=>'project','target_id'=>77003,'amount'=>1600,'request_key'=>hash('sha256','LOCAL TEST M077 native use'),'note_private'=>'must fail'), 1); } catch (RuntimeException $e) { $nativeUseRejected = strpos($e->getMessage(), 'native Dolibarr credit use') !== false; }
    m077RealAssert($nativeUseRejected, 'native deposit consumption was ignored');
    $failedExcess = false;
    try { $funds->reserve($receiptId, array('target_type'=>'project','target_id'=>77003,'amount'=>4000,'request_key'=>hash('sha256','LOCAL TEST M077 EXCESS'),'note_private'=>'must fail'), 1); } catch (RuntimeException $e) { $failedExcess = strpos($e->getMessage(), 'exceeds') !== false; }
    m077RealAssert($failedExcess, 'excess allocation did not fail closed');
    $funds->reverseAllocation($allocationB, 'LOCAL TEST reservation released', 1);
    $settledReverseRejected = false;
    try { $funds->reverseAllocation($allocationA, 'must fail', 1); } catch (RuntimeException $e) { $settledReverseRejected = strpos($e->getMessage(), 'Only an unsettled') !== false; }
    m077RealAssert($settledReverseRejected, 'settled allocation reversed without native correction');

    $counts = $target->query("SELECT (SELECT COUNT(*) FROM llx_cpcc_customer_receipt) receipts,(SELECT COUNT(*) FROM llx_cpcc_fund_allocation) allocations,(SELECT COUNT(*) FROM llx_cpcc_fund_event) events,(SELECT COUNT(*) FROM llx_chinaplanetlogistics) shipments")->fetch_assoc();
    $expectedAllocations=4;$expectedEvents=11;
    m077RealAssert((int)$counts['receipts']===2 && (int)$counts['allocations']===$expectedAllocations && (int)$counts['events']===$expectedEvents && (int)$counts['shipments']===2, 'persistence/idempotency counts incorrect '.json_encode($counts));
    $settled = (float) $target->query("SELECT COALESCE(SUM(amount),0) total FROM llx_cpcc_fund_allocation WHERE status='settled'")->fetch_assoc()['total'];
    $reserved = (float) $target->query("SELECT COALESCE(SUM(amount),0) total FROM llx_cpcc_fund_allocation WHERE status='reserved'")->fetch_assoc()['total'];
    m077RealAssert(abs($settled-4000.0)<0.00001 && abs($reserved)<0.00001, 'persisted reconciliation mismatch');
    $finalReconciliation=$funds->reconcileReceipt($receiptId);
    m077RealAssert(abs((float)$finalReconciliation['available']-4000.0)<0.00001,'final authoritative availability ignored native consumption');
    echo "M077_REAL_LOCAL=PASS SOURCE_DB_MUTATED=NO RECEIPTS=2 MANAGED_CONFIRMED=1 ORDINARY_REJECTED=1 NATIVE_GUARD=PASS NATIVE_RACE=PASS EXPORT_PRIVATE=PASS ALLOCATIONS=".$expectedAllocations." SETTLED=4000 RESERVED=0 UNALLOCATED=4000 SHIPMENTS=2 SHIPMENT_STATUS=0 CUSTOMER_VISIBLE=0 MILESTONE=inquiry\n";
} finally {
    if ($target instanceof mysqli) $target->close();
    if ($created) {
        m077RealAssert(preg_match('/^m077_local_[a-f0-9]{12}$/', $database) === 1, 'unsafe cleanup database name');
        $serverDb->query("DROP DATABASE ".$database);
        $absence = $serverDb->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME='".$serverDb->real_escape_string($database)."'");
        m077RealAssert($absence->num_rows===0, 'disposable database cleanup failed');
    }
}
