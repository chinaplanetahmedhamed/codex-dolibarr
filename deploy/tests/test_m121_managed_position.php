<?php
/**
 * M121 current managed-position reference checks, actual service SQL on SQLite memory.
 * Native cash evidence is seeded, never posted. Reuses the integration adapter's
 * explicit healthy advisory-lock/trigger emulation; no native runtime/ACL/concurrency proof.
 * Current managed subset only: this is neither bank reconciliation nor historical accounting.
 */
if (PHP_SAPI !== 'cli') exit(1);
$mpArguments=$argv;
$argv=array(__FILE__); // Included baseline must not consume this test's output arguments.
ob_start();
try { require __DIR__.'/test_m121_financial_integration.php'; }
finally { $mpBaselineOutput=ob_get_clean(); $argv=$mpArguments; }
if ($m121Checks!==46) throw new RuntimeException('Unexpected integration prerequisite assertion count.');
$mpBaselineRows=array();
foreach (array('societe','projet','commande_fournisseur','facture_fourn','element_element','cpo_operation','cpo_supplier_action','cpcc_project_charge','facture','paiement','paiement_facture') as $table) $mpBaselineRows[$table]=$db->rows($table);
$mpChecks=0; $mpPhases=array();
function mp_assert($ok,$label) { $GLOBALS['mpChecks']++; if (!$ok) throw new RuntimeException($label); }
function mp_number($row,$key,$expected,$label) {
    mp_assert(array_key_exists($key,$row)&&is_numeric($row[$key])&&abs((float)$row[$key]-$expected)<0.000001,$label.': '.$key);
}
class M121PositionDb extends M121IntegrationDb {
    public $failNeedle=null;
    function __construct() {
        parent::__construct();
        // MySQL CONCAT propagates NULL; it must not silently treat NULL as ''.
        $this->pdo->sqliteCreateFunction('CONCAT',function (...$parts) { return in_array(null,$parts,true) ? null : implode('',$parts); },-1);
        $this->pdo->exec('CREATE TABLE llx_bank_account(rowid INTEGER PRIMARY KEY,entity INTEGER,currency_code TEXT,clos INTEGER)');
        $this->pdo->exec('CREATE TABLE llx_bank_url(rowid INTEGER PRIMARY KEY,fk_bank INTEGER,url_id INTEGER,url TEXT,label TEXT,type TEXT)');
        $extra=array(
            'bank'=>array('dateo TEXT','datev TEXT','amount_main_currency REAL','fk_type TEXT','num_chq TEXT'),
            'paiement'=>array('amount REAL','multicurrency_amount REAL','fk_paiement INTEGER','note TEXT'),
            'paiementfourn'=>array('amount REAL','multicurrency_amount REAL','num_paiement TEXT','note TEXT','statut INTEGER'),
            'cpo_operation'=>array('fk_commande INTEGER','ref TEXT'),
            'cpcc_fund_allocation'=>array('fk_user_reverse INTEGER','date_reversal TEXT'),
            'cpo_supplier_action'=>array('fk_allocation INTEGER','fk_bank INTEGER','fk_bank_account INTEGER','fk_payment_mode INTEGER','fk_customer_order INTEGER','payment_date TEXT','payment_reference TEXT','request_token TEXT','amount REAL','currency_code TEXT','exchange_rate REAL')
        );
        foreach ($extra as $table=>$columns) foreach ($columns as $column) $this->pdo->exec('ALTER TABLE llx_'.$table.' ADD COLUMN '.$column);
    }
    function query($sql) {
        if ($this->failNeedle!==null&&strpos($sql,$this->failNeedle)!==false) { $this->queries[]=$sql; throw new PDOException('Injected synthetic position SELECT failure'); }
        return parent::query($sql);
    }
}
function mp_fixture() {
    $db=new M121PositionDb();
    foreach ($GLOBALS['mpBaselineRows'] as $table=>$rows) foreach ($rows as $row) {
        if ($table==='cpo_supplier_action'&&$row['action_type']==='supplier_payment') continue;
        $db->insert($table,$row);
    }
    $db->insert('commande',array('rowid'=>30,'entity'=>1,'fk_soc'=>7,'fk_projet'=>11,'fk_statut'=>1));
    $db->pdo->exec("UPDATE llx_cpo_operation SET fk_commande=30,ref='SYN-OP101'");
    $db->insert('cpo_supplier_action',array('rowid'=>399,'entity'=>1,'fk_operation'=>101,'fk_soc_customer'=>7,'fk_project'=>11,'fk_supplier'=>88,'fk_supplier_order'=>77,'action_type'=>'supplier_order','status'=>'confirmed','fk_customer_order'=>30));
    $db->pdo->exec("UPDATE llx_cpo_supplier_action SET fk_customer_order=30,currency_code='USD',exchange_rate=1 WHERE rowid=400");
    $db->insert('bank_account',array('rowid'=>11,'entity'=>1,'currency_code'=>'USD','clos'=>1)); // Closed today is still retained cash evidence.
    $db->insert('bank',array('rowid'=>301,'fk_account'=>11,'amount'=>100,'dateo'=>'2026-11-02','datev'=>'2026-11-02','fk_type'=>'VIR','num_chq'=>'SYN'));
    $db->insert('bank_url',array('rowid'=>1,'fk_bank'=>301,'url_id'=>10,'url'=>'/compta/paiement/card.php?id=','label'=>'(paiement)','type'=>'payment'));
    $db->pdo->exec("UPDATE llx_paiement SET amount=100,multicurrency_amount=100,fk_paiement=1,note='SYN' WHERE rowid=10");
    return array($db,new CustomerFundsService($db,1));
}
function mp_register($db,$service) {
    $id=$service->createPendingReceipt(array('fk_soc'=>7,'receipt_date'=>'2026-11-02','amount'=>100,'currency_code'=>'USD','reference'=>'SYN-POSITION','request_key'=>hash('sha256','M121 position receipt')),9);
    $service->confirmReceipt($id,10,9); return $id;
}
function mp_reserve($service,$receipt,$amount=40,$operation=101) {
    return $service->reserve($receipt,array('target_type'=>'operation','target_id'=>$operation,'amount'=>$amount,'request_key'=>hash('sha256','M121 position allocation '.$operation)),9);
}
function mp_supplier($db,$allocation,$amount=40) {
    $db->insert('bank',array('rowid'=>300,'fk_account'=>11,'amount'=>-$amount,'dateo'=>'2026-11-03','datev'=>'2026-11-03','fk_type'=>'VIR','num_chq'=>'SYN-SUP'));
    $db->insert('bank_url',array('rowid'=>2,'fk_bank'=>300,'url_id'=>200,'url'=>'/fourn/paiement/card.php?id=','label'=>'(paiement)','type'=>'payment_supplier'));
    $db->insert('paiementfourn',array('rowid'=>200,'entity'=>1,'fk_bank'=>300,'fk_paiement'=>1,'datep'=>'2026-11-03','amount'=>$amount,'multicurrency_amount'=>$amount,'num_paiement'=>'SYN-SUP','note'=>'SYN-SUP','statut'=>1));
    $db->insert('paiementfourn_facturefourn',array('rowid'=>1,'fk_paiementfourn'=>200,'fk_facturefourn'=>201,'amount'=>$amount,'multicurrency_amount'=>$amount));
    $db->insert('cpo_supplier_action',array('rowid'=>401,'entity'=>1,'fk_operation'=>101,'fk_soc_customer'=>7,'fk_project'=>11,'fk_supplier'=>88,'fk_supplier_invoice'=>201,'fk_supplier_order'=>77,'fk_supplier_payment'=>200,'action_type'=>'supplier_payment','status'=>'confirmed','fk_allocation'=>$allocation,'fk_bank'=>300,'fk_bank_account'=>11,'fk_payment_mode'=>1,'fk_customer_order'=>30,'payment_date'=>'2026-11-03','payment_reference'=>'SYN-SUP','request_token'=>'SYN-SUP','amount'=>$amount,'currency_code'=>'USD','exchange_rate'=>1));
}
function mp_settled($nativeAmount=40) {
    list($db,$service)=mp_fixture(); $receipt=mp_register($db,$service); $allocation=mp_reserve($service,$receipt);
    mp_supplier($db,$allocation,$nativeAmount); $service->settleWithSupplierPayment($allocation,200,9);
    return array($db,$service,$receipt,$allocation);
}
function mp_state($db) {
    $state=array();
    foreach ($db->pdo->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN) as $table) {
        $state[$table]=$db->pdo->query('SELECT * FROM "'.str_replace('"','""',$table).'" ORDER BY rowid')->fetchAll(PDO::FETCH_ASSOC);
    }
    return $state;
}
function mp_read($db,$service,$project=0,$customer=7) {
    $before=mp_state($db); $start=count($db->queries);
    $result=$service->currentPosition($customer,$project);
    mp_assert(mp_state($db)===$before,'Position read preserves every synthetic table');
    foreach (array_slice($db->queries,$start) as $sql) mp_assert((bool)preg_match('/\A\s*(?:SELECT|SHOW)\b/i',$sql),'Position reader executes SELECT/SHOW only');
    mp_assert(!$db->pdo->inTransaction()&&$db->held===array(),'Position read releases owned transaction/locks');
    mp_assert($result['customer_id']===$customer&&$result['project_id']===$project,'Exact requested scope retained');
    mp_assert($result['scope']===($project?'project_allocations_only':'managed_receipts_only'),'Explicit managed/project subset scope');
    return $result;
}
function mp_bad($result,$reason,$currency='USD') {
    mp_assert(in_array($result['coverage'],array('partial','unavailable'),true),'Broken evidence is not verified');
    mp_assert(in_array($reason,$result['reasons'],true),'Exact top-level reason: '.$reason);
    $bucket=$result['currencies'][$currency];
    mp_assert(in_array($reason,$bucket['reasons'],true),'Exact currency reason: '.$reason);
    foreach (array('received_cash','paid_cash','reserved','held_cash','available_to_allocate','assigned') as $field) mp_assert(array_key_exists($field,$bucket)&&$bucket[$field]===null,'Broken evidence never certifies '.$field);
}
if (!method_exists('CustomerFundsService','currentPosition')) throw new RuntimeException('M121 managed position RED: CustomerFundsService::currentPosition is not implemented.');

list($db,$service)=mp_fixture();
$empty=mp_read($db,$service);
mp_assert($empty['coverage']==='not_managed'&&$empty['currencies']===array(),'No managed receipt is not a fabricated zero balance');
$receipt=mp_register($db,$service);
$position=mp_read($db,$service); $mpPhases['confirmed']=$position;
mp_assert($position['coverage']==='verified_managed','Healthy closed-bank receipt is verified');
foreach (array('received_cash'=>100,'paid_cash'=>0,'reserved'=>0,'held_cash'=>100,'available_to_allocate'=>100) as $k=>$v) mp_number($position['currencies']['USD'],$k,$v,'Confirmed receipt');
$allocation=mp_reserve($service,$receipt);
$position=mp_read($db,$service); $mpPhases['reserved']=$position;
foreach (array('received_cash'=>100,'paid_cash'=>0,'reserved'=>40,'held_cash'=>100,'available_to_allocate'=>60) as $k=>$v) mp_number($position['currencies']['USD'],$k,$v,'Reservation is not cash paid');
$project=mp_read($db,$service,11); $mpPhases['project_reserved']=$project;
foreach (array('received_cash','held_cash','available_to_allocate') as $k) mp_assert($project['currencies']['USD'][$k]===null,'Project does not duplicate customer pool: '.$k);
mp_number($project['currencies']['USD'],'assigned',40,'Project assigned'); mp_number($project['currencies']['USD'],'reserved',40,'Project reserved');
mp_supplier($db,$allocation); $service->settleWithSupplierPayment($allocation,200,9);
$position=mp_read($db,$service); $mpPhases['settled']=$position;
foreach (array('received_cash'=>100,'paid_cash'=>40,'reserved'=>0,'held_cash'=>60,'available_to_allocate'=>60) as $k=>$v) mp_number($position['currencies']['USD'],$k,$v,'Settled actual supplier attribution');
mp_assert($position['currencies']['USD']['receipt_ids']===array($receipt)&&$position['currencies']['USD']['customer_payment_ids']===array(10)&&$position['currencies']['USD']['supplier_payment_ids']===array(200),'Original source identities retained once');
$project=mp_read($db,$service,11); $mpPhases['project_settled']=$project;
mp_number($project['currencies']['USD'],'paid_cash',40,'Project attributed actual payment'); mp_number($project['currencies']['USD'],'reserved',0,'Settled reservation released');
foreach (array('received_cash','held_cash','available_to_allocate') as $k) mp_assert($project['currencies']['USD'][$k]===null,'Settled project still has no customer pool: '.$k);
$again=mp_read($db,$service); mp_assert($again===$position,'Repeated pure read does not duplicate any source');

list($db,$service)=mp_settled(60);
$position=mp_read($db,$service); $mpPhases['partial_supplier_attribution']=$position;
mp_number($position['currencies']['USD'],'paid_cash',40,'Native supplier60 only attributes customer40');
mp_number($position['currencies']['USD'],'held_cash',60,'Unattributed supplier20 is never assigned to this customer');

// Actual row corruption and SQL failure probes: reasons must identify the guard.
$faults=array(
    'missing customer bank'=>array('DELETE FROM llx_bank WHERE rowid=301','customer_bank_evidence_invalid'),
    'customer bank sign'=>array('UPDATE llx_bank SET amount=-100 WHERE rowid=301','customer_bank_evidence_invalid'),
    'customer link missing'=>array('DELETE FROM llx_bank_url WHERE rowid=1','customer_bank_evidence_invalid'),
    'customer link URL'=>array("UPDATE llx_bank_url SET url='/wrong' WHERE rowid=1",'customer_bank_evidence_invalid'),
    'bank account entity'=>array('UPDATE llx_bank_account SET entity=2 WHERE rowid=11','customer_bank_evidence_invalid'),
    'bank account currency'=>array("UPDATE llx_bank_account SET currency_code='EUR' WHERE rowid=11",'customer_bank_evidence_invalid'),
    'missing supplier bank'=>array('DELETE FROM llx_bank WHERE rowid=300','supplier_evidence_invalid'),
    'supplier bank sign'=>array('UPDATE llx_bank SET amount=40 WHERE rowid=300','supplier_evidence_invalid'),
    'supplier link missing'=>array('DELETE FROM llx_bank_url WHERE rowid=2','supplier_evidence_invalid'),
    'supplier link URL'=>array("UPDATE llx_bank_url SET url='/wrong' WHERE rowid=2",'supplier_evidence_invalid'),
    'supplier invoice project drift'=>array('UPDATE llx_facture_fourn SET fk_projet=22 WHERE rowid=201','supplier_evidence_invalid'),
    'supplier invoice party drift'=>array('UPDATE llx_facture_fourn SET fk_soc=99 WHERE rowid=201','supplier_evidence_invalid'),
    'supplier invoice type drift'=>array('UPDATE llx_facture_fourn SET type=2 WHERE rowid=201','supplier_evidence_invalid'),
    'supplier payment marker incomplete'=>array("UPDATE llx_cpo_supplier_action SET status='preparing' WHERE rowid=401",'supplier_evidence_invalid'),
    'supplier payment marker wrong order'=>array('UPDATE llx_cpo_supplier_action SET fk_supplier_order=999 WHERE rowid=401','supplier_evidence_invalid'),
    'supplier order link lost'=>array('DELETE FROM llx_element_element','supplier_evidence_invalid'),
    'supplier order drift'=>array('UPDATE llx_commande_fournisseur SET fk_projet=22 WHERE rowid=77','supplier_evidence_invalid'),
    'operation customer drift'=>array('UPDATE llx_cpo_operation SET fk_soc=8 WHERE rowid=101','allocation_context_invalid'),
    'operation project drift'=>array('UPDATE llx_cpo_operation SET fk_project=22 WHERE rowid=101','allocation_context_invalid'),
    'allocation currency drift'=>array("UPDATE llx_cpcc_fund_allocation SET currency_code='EUR'",'allocation_context_invalid'),
    'customer native amount drift'=>array('UPDATE llx_paiement_facture SET amount=99,multicurrency_amount=99','receipt_evidence_invalid')
);
foreach ($faults as $label=>$fault) {
    list($db,$service)=mp_settled(); $db->pdo->exec($fault[0]);
    mp_bad(mp_read($db,$service),$fault[1]);
}
list($db,$service,$receipt,$allocation)=mp_settled();
$row=$db->rows('cpcc_fund_allocation')[0]; $row['rowid']=2; $row['fk_soc']=8; $row['fk_receipt']=999; $row['amount']=1; $row['request_key']=hash('sha256','foreign cap probe');
$db->insert('cpcc_fund_allocation',$row);
mp_bad(mp_read($db,$service),'supplier_attribution_exceeds_payment');
// Retained-source corruption: a malformed negative foreign row must not mask
// actual positive attributions exceeding the one native payment. Writers reject
// negative amounts; this is a defensive read proof, not a native write exploit.
list($db,$service)=mp_settled();
$row=$db->rows('cpcc_fund_allocation')[0];
$row['rowid']=2; $row['fk_soc']=8; $row['fk_receipt']=999; $row['amount']=20; $row['request_key']=hash('sha256','positive foreign cap probe'); $db->insert('cpcc_fund_allocation',$row);
$row['rowid']=3; $row['amount']=-20; $row['request_key']=hash('sha256','negative foreign masks cap'); $db->insert('cpcc_fund_allocation',$row);
mp_bad(mp_read($db,$service),'supplier_attribution_exceeds_payment');
list($db,$service)=mp_settled(); $db->failNeedle='bank_url';
mp_bad(mp_read($db,$service),'query_failed');

// Missing uniqueness is deliberately an inconsistent retained-schema fixture.
list($db,$service)=mp_settled();
$db->pdo->exec('CREATE TABLE receipt_copy AS SELECT * FROM llx_cpcc_customer_receipt');
$db->pdo->exec('DROP TABLE llx_cpcc_customer_receipt'); $db->pdo->exec('ALTER TABLE receipt_copy RENAME TO llx_cpcc_customer_receipt');
$row=$db->rows('cpcc_customer_receipt')[0]; $row['rowid']=2; $row['request_key']=hash('sha256','duplicate native payment'); $db->insert('cpcc_customer_receipt',$row);
mp_bad(mp_read($db,$service),'duplicate_customer_payment');

// Distinct payment IDs cannot certify the same bank cash source twice, even
// when each has its own otherwise valid exact payment-link row.
list($db,$service)=mp_settled();
$db->insert('facture',array('rowid'=>25,'ref'=>'SYN-SAMEBANK','entity'=>1,'fk_soc'=>7,'fk_projet'=>11,'datef'=>'2026-11-01','total_ttc'=>100,'multicurrency_total_ttc'=>100,'multicurrency_code'=>'USD','fk_statut'=>1,'paye'=>1,'type'=>3));
$db->insert('paiement',array('rowid'=>15,'entity'=>1,'statut'=>1,'ref'=>'SYN-SAMEBANK','datep'=>'2026-11-02','fk_bank'=>301,'amount'=>100,'multicurrency_amount'=>100));
$db->insert('paiement_facture',array('rowid'=>5,'fk_paiement'=>15,'fk_facture'=>25,'amount'=>100,'multicurrency_amount'=>100,'multicurrency_code'=>'USD'));
$db->insert('bank_url',array('rowid'=>5,'fk_bank'=>301,'url_id'=>15,'url'=>'/compta/paiement/card.php?id=','type'=>'payment'));
$r=$service->createPendingReceipt(array('fk_soc'=>7,'receipt_date'=>'2026-11-02','amount'=>100,'currency_code'=>'USD','request_key'=>hash('sha256','M121 shared bank source')),9); $service->confirmReceipt($r,15,9);
mp_bad(mp_read($db,$service),'customer_bank_evidence_invalid');

list($db,$service)=mp_fixture(); $receipt=mp_register($db,$service);
$db->insert('facture',array('rowid'=>21,'ref'=>'SYN-TARGET','entity'=>1,'fk_soc'=>7,'fk_projet'=>11,'datef'=>'2026-11-03','total_ttc'=>10,'multicurrency_total_ttc'=>10,'multicurrency_code'=>'USD','fk_statut'=>1,'paye'=>0,'type'=>0));
$db->insert('societe_remise_except',array('rowid'=>1,'entity'=>1,'fk_soc'=>7,'datec'=>'2026-11-03','fk_facture_source'=>20,'fk_facture'=>21,'amount_ttc'=>10,'multicurrency_amount_ttc'=>10,'multicurrency_code'=>'USD'));
$position=mp_read($db,$service); $mpPhases['credit_consumed']=$position; $bucket=$position['currencies']['USD'];
mp_assert($position['coverage']==='partial'&&in_array('native_credit_consumption',$position['reasons'],true)&&$bucket['held_cash']===null,'Credit use is explicit incomplete cash coverage');
mp_number($bucket,'credit_consumed',10,'Credit consumed separately'); mp_number($bucket,'paid_cash',0,'Credit application is not cash paid'); mp_number($bucket,'available_to_allocate',90,'Healthy existing credit control retained');

list($db,$service,$receipt,$allocation)=mp_settled();
$db->pdo->exec("UPDATE llx_cpcc_fund_allocation SET disbursement_type='financial_entry',disbursement_id=900 WHERE rowid=".$allocation);
$db->insert('cpcc_financial_entry',array('rowid'=>900,'entity'=>1,'fk_soc'=>7,'fk_project'=>11,'entry_date'=>'2026-11-03','entry_type'=>'supplier_payment','direction'=>'D','amount'=>40,'currency_code'=>'USD','source_type'=>'operation','source_id'=>101,'status'=>1,'customer_visible'=>1));
$position=mp_read($db,$service); $mpPhases['manual_settlement']=$position; $bucket=$position['currencies']['USD'];
mp_assert($position['coverage']==='partial'&&in_array('unsupported_settlement',$position['reasons'],true)&&$bucket['held_cash']===null,'Manual posted debit does not certify native cash');
mp_number($bucket,'other_settled',40,'Other settlement separately'); mp_number($bucket,'available_to_allocate',60,'Existing allocation control retained');

list($db,$service)=mp_fixture(); $receipt=mp_register($db,$service); $allocation=mp_reserve($service,$receipt);
$service->reverseAllocation($allocation,'Synthetic release of unused reservation',9);
$position=mp_read($db,$service); $mpPhases['reversed_reservation']=$position;
foreach (array('received_cash'=>100,'paid_cash'=>0,'reserved'=>0,'held_cash'=>100,'available_to_allocate'=>100) as $k=>$v) mp_number($position['currencies']['USD'],$k,$v,'Reversed reservation is not paid or active');

// Separate projects share one customer pool without duplicating it into projects.
list($db,$service)=mp_fixture(); $receipt=mp_register($db,$service); mp_reserve($service,$receipt,40);
$db->insert('projet',array('rowid'=>12,'entity'=>1,'fk_soc'=>7,'ref'=>'SYN-P12','title'=>'Second synthetic project'));
$db->insert('cpo_operation',array('rowid'=>102,'entity'=>1,'fk_soc'=>7,'fk_project'=>12,'fk_shipment'=>null,'fk_commande'=>30)); mp_reserve($service,$receipt,20,102);
$position=mp_read($db,$service); mp_number($position['currencies']['USD'],'reserved',60,'Customer reservations across projects counted once'); mp_number($position['currencies']['USD'],'available_to_allocate',40,'Customer shared pool remaining');
foreach (array(11=>40,12=>20) as $project=>$assigned) { $p=mp_read($db,$service,$project); mp_number($p['currencies']['USD'],'assigned',$assigned,'Exact project assignment'); mp_assert($p['currencies']['USD']['received_cash']===null&&$p['currencies']['USD']['available_to_allocate']===null,'No per-project replication of shared pool'); }

// Independent currencies are retained, never converted or added together.
list($db,$service)=mp_fixture(); mp_register($db,$service);
$db->insert('facture',array('rowid'=>23,'ref'=>'SYN-EUR','entity'=>1,'fk_soc'=>7,'fk_projet'=>11,'datef'=>'2026-11-01','total_ttc'=>60,'multicurrency_total_ttc'=>50,'multicurrency_code'=>'EUR','fk_statut'=>1,'paye'=>1,'type'=>3));
$db->insert('paiement',array('rowid'=>13,'entity'=>1,'statut'=>1,'ref'=>'SYN-EUR','datep'=>'2026-11-02','fk_bank'=>303,'amount'=>60,'multicurrency_amount'=>50));
$db->insert('paiement_facture',array('rowid'=>3,'fk_paiement'=>13,'fk_facture'=>23,'amount'=>60,'multicurrency_amount'=>50,'multicurrency_code'=>'EUR'));
$db->insert('bank_account',array('rowid'=>12,'entity'=>1,'currency_code'=>'EUR','clos'=>1));
$db->insert('bank',array('rowid'=>303,'fk_account'=>12,'amount'=>50,'amount_main_currency'=>60,'dateo'=>'2026-11-02','datev'=>'2026-11-02'));
$db->insert('bank_url',array('rowid'=>3,'fk_bank'=>303,'url_id'=>13,'url'=>'/compta/paiement/card.php?id=','type'=>'payment'));
$r=$service->createPendingReceipt(array('fk_soc'=>7,'receipt_date'=>'2026-11-02','amount'=>50,'currency_code'=>'EUR','request_key'=>hash('sha256','M121 EUR position')),9); $service->confirmReceipt($r,13,9);
$position=mp_read($db,$service); $mpPhases['multiple_currencies']=$position;
mp_number($position['currencies']['USD'],'received_cash',100,'Original USD'); mp_number($position['currencies']['EUR'],'received_cash',50,'Original EUR excludes USD base amount60');
mp_assert(count($position['currencies'])===2&&!array_key_exists('total',$position),'No combined-currency cash total');
$db->pdo->exec('UPDATE llx_bank SET amount=-50 WHERE rowid=303');
$mixed=mp_read($db,$service); mp_bad($mixed,'customer_bank_evidence_invalid','EUR');
mp_number($mixed['currencies']['USD'],'received_cash',100,'Broken EUR evidence does not erase healthy USD subset');

// A normal additional bank_url party link must not look like duplicate payment evidence.
list($db,$service)=mp_settled();
$db->insert('bank_url',array('rowid'=>3,'fk_bank'=>301,'url_id'=>7,'url'=>'/societe/card.php?socid=','type'=>'company'));
mp_assert(mp_read($db,$service)['coverage']==='verified_managed','Native party links coexist with one exact payment link');
$db->insert('bank_url',array('rowid'=>4,'fk_bank'=>301,'url_id'=>10,'url'=>'/compta/paiement/card.php?id=','type'=>'payment'));
mp_bad(mp_read($db,$service),'customer_bank_evidence_invalid');

list($db,$service)=mp_fixture(); mp_register($db,$service);
$db->insert('societe',array('rowid'=>8,'entity'=>1,'client'=>1,'nom'=>'Other synthetic customer'));
$db->insert('projet',array('rowid'=>22,'entity'=>1,'fk_soc'=>8,'ref'=>'FOREIGN','title'=>'Foreign project'));
foreach (array(array(0,0),array(7,-1),array(7,22)) as $scope) {
    $bad=$service->currentPosition($scope[0],$scope[1]);
    mp_assert($bad['coverage']==='unavailable'&&in_array('invalid_scope',$bad['reasons'],true),'Invalid/foreign scope is explicitly unavailable');
    mp_assert($bad['currencies']===array(),'Invalid/foreign scope leaks no financial bucket');
}

// An unmanaged native receipt remains visible as incomplete customer coverage.
list($db,$service)=mp_fixture(); mp_register($db,$service);
$db->insert('facture',array('rowid'=>24,'ref'=>'SYN-UNMANAGED','entity'=>1,'fk_soc'=>7,'fk_projet'=>11,'datef'=>'2026-11-01','total_ttc'=>25,'multicurrency_total_ttc'=>25,'multicurrency_code'=>'USD','fk_statut'=>1,'paye'=>1,'type'=>3));
$db->insert('paiement',array('rowid'=>14,'entity'=>1,'statut'=>1,'ref'=>'SYN-UNMANAGED','datep'=>'2026-11-02','fk_bank'=>304,'amount'=>25,'multicurrency_amount'=>25));
$db->insert('paiement_facture',array('rowid'=>4,'fk_paiement'=>14,'fk_facture'=>24,'amount'=>25,'multicurrency_amount'=>25,'multicurrency_code'=>'USD'));
$db->insert('bank',array('rowid'=>304,'fk_account'=>11,'amount'=>25,'dateo'=>'2026-11-02','datev'=>'2026-11-02'));
$db->insert('bank_url',array('rowid'=>4,'fk_bank'=>304,'url_id'=>14,'url'=>'/compta/paiement/card.php?id=','type'=>'payment'));
$position=mp_read($db,$service); $mpPhases['mixed_unmanaged']=$position;
mp_assert($position['coverage']==='partial'&&in_array('unmanaged_native_payments',$position['reasons'],true),'Unmanaged source prevents complete customer claim');
mp_assert($position['currencies']['USD']['coverage']==='verified_managed','Healthy managed subset remains explicitly verified');
foreach (array('received_cash'=>100,'held_cash'=>100,'available_to_allocate'=>100) as $k=>$v) mp_number($position['currencies']['USD'],$k,$v,'Unmanaged25 is never silently merged or assumed zero');
mp_assert($position['currencies']['USD']['customer_payment_ids']===array(10),'Only managed source identity belongs to certified subset');

$output=null;
foreach (array_slice($argv,1) as $arg) { if (strpos($arg,'--output=')===0) $output=substr($arg,9); else throw new InvalidArgumentException('Use --output=/tmp/name.json'); }
$report=array('status'=>'passed','assertions'=>$mpChecks,'baseline_assertions'=>46,'assurance'=>'synthetic_SQLite_current_managed_subset_only','native_posting_performed'=>false,'historical_statement'=>false,'phases'=>$mpPhases,'source_hash'=>hash_file('sha256',dirname(__DIR__,2).'/custom/chinaplanetcustomercenter/class/CustomerFundsService.class.php'));
if ($output!==null) {
    if (!preg_match('~\A/tmp/[A-Za-z0-9_-]+\.json\z~',$output)||is_link($output)) throw new InvalidArgumentException('Evidence output must be a plain /tmp JSON file.');
    file_put_contents($output,json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR).PHP_EOL); chmod($output,0600);
}
echo 'M121 managed position PASS ('.$mpChecks.' checks; baseline46; actual SQLite SELECT; native cash SEEDED)'.PHP_EOL;
