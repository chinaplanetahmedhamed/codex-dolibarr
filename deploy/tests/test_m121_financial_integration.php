<?php
/**
 * M121 local integration DEMO/TEST: actual service SQL on one fresh in-memory SQLite DB.
 * Native customer/supplier payments and bank rows are SEEDED evidence boundaries, not
 * posted by this test. Advisory locks/trigger metadata are emulated; FOR UPDATE is stripped.
 * No native-cash posting, authenticated runtime, trigger, decimal or concurrency acceptance.
 */
if (PHP_SAPI !== 'cli') exit(1);
error_reporting(E_ALL);
set_error_handler(function ($s,$m,$f,$l) { throw new ErrorException($m,0,$s,$f,$l); });
$m121Root=dirname(__DIR__,2);
foreach (array('custom/chinaplanetcustomercenter/class/CustomerFundsService.class.php','custom/chinaplanetcustomercenter/class/ManagedFundsNativeGuard.class.php','custom/chinaplanetoperations/class/OperationSupplierWorkflowService.class.php','custom/chinaplanetcustomercenter/lib/operations.lib.php') as $dependency) {
    if (!is_file($m121Root.'/'.$dependency)) { fwrite(STDERR,'M121 INTEGRATION BLOCKED: missing candidate dependency '.$dependency.PHP_EOL); exit(2); }
}
define('MAIN_DB_PREFIX','llx_');
$conf=(object)array('entity'=>1,'currency'=>'USD'); $user=(object)array('id'=>9);
function getDolGlobalString($key) { return $key==='MAIN_MONNAIE'?'USD':''; }
function getDolGlobalInt($key) { return 0; }
function getDolCurrency() { return 'USD'; }
require_once $m121Root.'/custom/chinaplanetcustomercenter/class/CustomerFundsService.class.php';
require_once $m121Root.'/custom/chinaplanetcustomercenter/lib/operations.lib.php';

// Only invoiceSummary's actual SQL is invoked on the supplier workflow class.
// Empty include bootstraps DO NOT implement or simulate any native posting object.
$m121StubRoot=sys_get_temp_dir().'/m121-finance-integration-'.bin2hex(random_bytes(6));
foreach (array('commande/class/commande.class.php','fourn/class/fournisseur.commande.class.php','fourn/class/fournisseur.facture.class.php','fourn/class/paiementfourn.class.php','compta/bank/class/account.class.php','societe/class/societe.class.php','custom/chinaplanetcustomercenter/class/CustomerFundsService.class.php') as $file) {
    $path=$m121StubRoot.'/'.$file; if (!is_dir(dirname($path))) mkdir(dirname($path),0700,true); file_put_contents($path,"<?php\n");
}
register_shutdown_function(function () use ($m121StubRoot) {
    if (!is_dir($m121StubRoot)) return;
    $files=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($m121StubRoot,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) { if ($file->isDir()) rmdir($file->getPathname()); else unlink($file->getPathname()); } rmdir($m121StubRoot);
});
define('DOL_DOCUMENT_ROOT',$m121StubRoot);
require_once $m121Root.'/custom/chinaplanetoperations/class/OperationSupplierWorkflowService.class.php';
class M121IntegrationRows { public $rows; function __construct($rows) { $this->rows=$rows; } }
class M121IntegrationDb {
    public $pdo; public $queries=array(); public $held=array();
    function __construct() {
        $this->pdo=new PDO('sqlite::memory:',null,null,array(PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION));
        $schemas=array(
            'societe'=>'rowid INTEGER PRIMARY KEY,entity INTEGER,client INTEGER,nom TEXT',
            'projet'=>'rowid INTEGER PRIMARY KEY,entity INTEGER,fk_soc INTEGER,ref TEXT,title TEXT',
            'commande'=>'rowid INTEGER PRIMARY KEY,entity INTEGER,fk_soc INTEGER,fk_projet INTEGER,fk_statut INTEGER',
            'cpo_operation'=>'rowid INTEGER PRIMARY KEY,entity INTEGER,fk_soc INTEGER,fk_project INTEGER,fk_shipment INTEGER',
            'facture'=>'rowid INTEGER PRIMARY KEY,ref TEXT,ref_client TEXT,entity INTEGER,fk_soc INTEGER,fk_projet INTEGER,datef TEXT,date_lim_reglement TEXT,multicurrency_code TEXT,total_ttc REAL,multicurrency_total_ttc REAL,fk_statut INTEGER,paye INTEGER,type INTEGER',
            'paiement'=>'rowid INTEGER PRIMARY KEY,entity INTEGER,statut INTEGER,ref TEXT,num_paiement TEXT,datep TEXT,fk_bank INTEGER',
            'paiement_facture'=>'rowid INTEGER PRIMARY KEY,fk_paiement INTEGER,fk_facture INTEGER,amount REAL,multicurrency_amount REAL,multicurrency_code TEXT',
            'societe_remise_except'=>'rowid INTEGER PRIMARY KEY,entity INTEGER,fk_soc INTEGER,datec TEXT,fk_facture_source INTEGER,fk_facture INTEGER,fk_facture_line INTEGER,amount_ttc REAL,multicurrency_amount_ttc REAL,multicurrency_code TEXT',
            'facturedet'=>'rowid INTEGER PRIMARY KEY,fk_facture INTEGER',
            'cpcc_customer_receipt'=>'rowid INTEGER PRIMARY KEY AUTOINCREMENT,entity INTEGER,fk_soc INTEGER,receipt_date TEXT,amount REAL,currency_code TEXT,reference TEXT,status TEXT,native_source_type TEXT,native_source_id INTEGER,request_key TEXT,payload_hash TEXT,note_private TEXT,fk_user_creat INTEGER,fk_user_modif INTEGER,fk_user_confirm INTEGER,date_confirmation TEXT,date_creation TEXT,UNIQUE(entity,request_key),UNIQUE(entity,native_source_type,native_source_id)',
            'cpcc_fund_allocation'=>'rowid INTEGER PRIMARY KEY AUTOINCREMENT,entity INTEGER,fk_receipt INTEGER,fk_soc INTEGER,target_type TEXT,target_id INTEGER,fk_project INTEGER,fk_operation INTEGER,fk_shipment INTEGER,amount REAL,currency_code TEXT,status TEXT,disbursement_type TEXT,disbursement_id INTEGER,request_key TEXT,payload_hash TEXT,note_private TEXT,fk_user_creat INTEGER,fk_user_settle INTEGER,date_creation TEXT,date_settlement TEXT,UNIQUE(entity,request_key)',
            'cpcc_fund_event'=>'rowid INTEGER PRIMARY KEY AUTOINCREMENT,entity INTEGER,fk_soc INTEGER,object_type TEXT,object_id INTEGER,action_code TEXT,status_from TEXT,status_to TEXT,message TEXT,fk_user INTEGER,date_action TEXT',
            'cpcc_project_charge'=>'rowid INTEGER PRIMARY KEY,entity INTEGER,fk_soc INTEGER,fk_project INTEGER,charge_date TEXT,category TEXT,label TEXT,supplier_label TEXT,amount REAL,currency_code TEXT,reference TEXT,source_type TEXT,source_id INTEGER,status INTEGER,customer_visible INTEGER',
            'cpcc_financial_entry'=>'rowid INTEGER PRIMARY KEY,entity INTEGER,fk_soc INTEGER,fk_charge INTEGER,fk_project INTEGER,entry_date TEXT,entry_type TEXT,direction TEXT,description TEXT,amount REAL,currency_code TEXT,reference TEXT,bank_label TEXT,allocated_amount REAL,allocated_currency TEXT,exchange_rate REAL,transfer_group TEXT,proof_ecm_id INTEGER,source_type TEXT,source_id INTEGER,status INTEGER,customer_visible INTEGER',
            'commande_fournisseur'=>'rowid INTEGER PRIMARY KEY,entity INTEGER,fk_soc INTEGER,fk_projet INTEGER,fk_statut INTEGER',
            'facture_fourn'=>'rowid INTEGER PRIMARY KEY,entity INTEGER,fk_soc INTEGER,fk_projet INTEGER,type INTEGER,fk_statut INTEGER,paye INTEGER,total_ttc REAL,multicurrency_code TEXT,multicurrency_total_ttc REAL',
            'paiementfourn'=>'rowid INTEGER PRIMARY KEY,entity INTEGER,fk_bank INTEGER,fk_paiement INTEGER,datep TEXT',
            'paiementfourn_facturefourn'=>'rowid INTEGER PRIMARY KEY,fk_paiementfourn INTEGER,fk_facturefourn INTEGER,amount REAL,multicurrency_amount REAL',
            'bank'=>'rowid INTEGER PRIMARY KEY,fk_account INTEGER,amount REAL',
            'cpo_supplier_action'=>'rowid INTEGER PRIMARY KEY,entity INTEGER,fk_operation INTEGER,fk_soc_customer INTEGER,fk_project INTEGER,fk_supplier INTEGER,fk_supplier_invoice INTEGER,fk_supplier_order INTEGER,fk_supplier_payment INTEGER,action_type TEXT,status TEXT',
            'element_element'=>'rowid INTEGER PRIMARY KEY,sourcetype TEXT,targettype TEXT,fk_source INTEGER,fk_target INTEGER',
            'chinaplanetlogistics'=>'rowid INTEGER PRIMARY KEY,entity INTEGER,fk_soc INTEGER,fk_project INTEGER,fk_statut INTEGER'
        );
        foreach ($schemas as $table=>$columns) $this->pdo->exec('CREATE TABLE llx_'.$table.'('.$columns.')');
    }
    function insert($table,array $row) { $q=$this->pdo->prepare('INSERT INTO llx_'.$table.'('.implode(',',array_keys($row)).') VALUES('.implode(',',array_fill(0,count($row),'?')).')'); $q->execute(array_values($row)); }
    function begin() { return (int)$this->pdo->beginTransaction(); }
    function commit() { return (int)$this->pdo->commit(); }
    function rollback() { return $this->pdo->inTransaction()?(int)$this->pdo->rollBack():0; }
    function escape($s) { return str_replace("'","''",$s); }
    function idate($time) { return '2026-11-03 12:00:00'; }
    function last_insert_id($table) { return (int)$this->pdo->lastInsertId(); }
    function lasterror() { return 'Synthetic integration SQL failure'; }
    function fetch_object($r) { return array_shift($r->rows); }
    function num_rows($r) { return count($r->rows); }
    function query($sql) {
        $this->queries[]=$sql;
        if (preg_match("/^SELECT GET_LOCK\('([^']+)',[0-9]+\) acquired$/",$sql,$m)) { $this->held[$m[1]]=true; return new M121IntegrationRows(array((object)array('acquired'=>1))); }
        if (preg_match("/^SELECT RELEASE_LOCK\('([^']+)'\)/",$sql,$m)) { unset($this->held[$m[1]]); return new M121IntegrationRows(array((object)array('released'=>1))); }
        if (strpos($sql,'INFORMATION_SCHEMA.TRIGGERS')!==false) {
            $method=new ReflectionMethod('ManagedFundsNativeGuard','definitions'); $method->setAccessible(true);
            foreach ($method->invoke(null) as $name=>$d) if (strpos($sql,"TRIGGER_NAME='".$name."'")!==false) return new M121IntegrationRows(array((object)array('ACTION_TIMING'=>'BEFORE','EVENT_MANIPULATION'=>$d['event'],'EVENT_OBJECT_TABLE'=>$d['table'],'ACTION_STATEMENT'=>$d['body'])));
            throw new RuntimeException('Unexpected synthetic trigger metadata query');
        }
        if (preg_match("/^SHOW TABLES LIKE '([^']+)'$/",$sql,$m)) { $q=$this->pdo->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name=?"); $q->execute(array($m[1])); }
        else { $q=$this->pdo->query(preg_replace('/ FOR UPDATE$/','',$sql)); }
        return new M121IntegrationRows($q->columnCount()?$q->fetchAll(PDO::FETCH_OBJ):array());
    }
    function rows($table) { return $this->pdo->query('SELECT * FROM llx_'.$table.' ORDER BY rowid')->fetchAll(PDO::FETCH_ASSOC); }
}
$m121Checks=0;
function fi_assert($ok,$message) { $GLOBALS['m121Checks']++; if (!$ok) throw new RuntimeException($message); }
function fi_near($actual,$expected,$message) { fi_assert(abs((float)$actual-$expected)<0.000001,$message); }
function fi_reject($fn,$message,$expectedGuard) {
    try { $fn(); }
    catch (PDOException $e) { throw new RuntimeException($message.': SQL failures cannot satisfy an expected service guard.',0,$e); }
    catch (RuntimeException $e) {
        if ($e->getMessage()!==$expectedGuard) throw new RuntimeException($message.': unexpected service failure: '.$e->getMessage(),0,$e);
        fi_assert(true,$message); return;
    }
    fi_assert(false,$message);
}
// Negative-test controls: infrastructure failures and unrelated guards must fail the test.
foreach (array(new PDOException('Expected service guard'),new RuntimeException('Unexpected service failure')) as $injected) {
    $beforeChecks=$m121Checks;
    try {
        fi_reject(function () use ($injected) { throw $injected; },'Negative helper control','Expected service guard');
        throw new LogicException('Negative helper incorrectly accepted an unrelated exception');
    } catch (RuntimeException $rejected) {
        fi_assert($rejected->getPrevious()===$injected&&$m121Checks===$beforeChecks,'Negative helper rejects '.get_class($injected).' without counting an expected guard');
    }
}
function fi_supplier($db) { $m=new ReflectionMethod('OperationSupplierWorkflowService','invoiceSummary'); $m->setAccessible(true); return $m->invoke(new OperationSupplierWorkflowService($db,1),201); }
function fi_snapshot($db,$service,$phase,$receipt=0,$allocation=0,$boundary=null) {
    $flags=array(); foreach (cpcc_project_lifecycle($db,7,11,'USD') as $item) $flags[$item['key']]=$item['done'];
    $summary=cpcc_financial_summary($db,7,'USD');
    return array('phase'=>$phase,'seed_boundary'=>$boundary,'managed'=>$receipt?$service->reconcileReceipt($receipt):null,'supplier_invoice'=>fi_supplier($db),'lifecycle'=>$flags,'legacy_customer_summary'=>$summary,'commitments'=>cpcc_financial_commitment_rows($db,7,'USD',11),'receipts'=>$db->rows('cpcc_customer_receipt'),'allocations'=>$db->rows('cpcc_fund_allocation'),'fund_event_count'=>count($db->rows('cpcc_fund_event')),'native_sources'=>array('customer_payment_ids'=>array_column($db->rows('paiement'),'rowid'),'supplier_payment_ids'=>array_column($db->rows('paiementfourn'),'rowid'),'bank_ids'=>array_column($db->rows('bank'),'rowid')),'financial_entry_count'=>count($db->rows('cpcc_financial_entry')));
}
$db=new M121IntegrationDb(); $service=new CustomerFundsService($db,1);
$db->insert('societe',array('rowid'=>7,'entity'=>1,'client'=>1,'nom'=>'Synthetic customer'));
$db->insert('projet',array('rowid'=>11,'entity'=>1,'fk_soc'=>7,'ref'=>'SYN-P11','title'=>'Synthetic project'));
$db->insert('cpo_operation',array('rowid'=>101,'entity'=>1,'fk_soc'=>7,'fk_project'=>11,'fk_shipment'=>null));
$db->insert('commande_fournisseur',array('rowid'=>77,'entity'=>1,'fk_soc'=>88,'fk_projet'=>11,'fk_statut'=>1));
$db->insert('facture_fourn',array('rowid'=>201,'entity'=>1,'fk_soc'=>88,'fk_projet'=>11,'type'=>0,'fk_statut'=>1,'paye'=>0,'total_ttc'=>60,'multicurrency_code'=>'USD','multicurrency_total_ttc'=>60));
$db->insert('element_element',array('rowid'=>1,'sourcetype'=>'order_supplier','targettype'=>'invoice_supplier','fk_source'=>77,'fk_target'=>201));
$db->insert('cpo_supplier_action',array('rowid'=>400,'entity'=>1,'fk_operation'=>101,'fk_soc_customer'=>7,'fk_project'=>11,'fk_supplier'=>88,'fk_supplier_invoice'=>201,'fk_supplier_order'=>77,'action_type'=>'supplier_invoice','status'=>'confirmed'));
$db->insert('cpcc_project_charge',array('rowid'=>1,'entity'=>1,'fk_soc'=>7,'fk_project'=>11,'charge_date'=>'2026-11-01','category'=>'supplier','label'=>'Synthetic supplier commitment','supplier_label'=>'INTERNAL SYNTHETIC SUPPLIER','amount'=>60,'currency_code'=>'USD','status'=>1,'customer_visible'=>1,'source_type'=>'manual'));
$phases=array(fi_snapshot($db,$service,'commitment_only'));
fi_near($phases[0]['legacy_customer_summary']['USD']['available'],0,'A commitment is not received cash');
fi_near($phases[0]['supplier_invoice']['paid'],0,'Unpaid supplier invoice is not a payment');
fi_assert(!$phases[0]['lifecycle']['supplier_paid'],'Initial supplier commitment remains unpaid');

// EXPLICIT FIXTURE BOUNDARY: existing native customer payment/bank evidence, not a posting call.
$db->insert('facture',array('rowid'=>20,'ref'=>'SYN-DEP20','entity'=>1,'fk_soc'=>7,'fk_projet'=>11,'datef'=>'2026-11-01','multicurrency_code'=>'USD','total_ttc'=>100,'multicurrency_total_ttc'=>100,'fk_statut'=>1,'paye'=>1,'type'=>3));
$db->insert('bank',array('rowid'=>301,'fk_account'=>11,'amount'=>100));
$db->insert('paiement',array('rowid'=>10,'entity'=>1,'statut'=>1,'ref'=>'SYN-P10','num_paiement'=>'SYN','datep'=>'2026-11-02 12:00:00','fk_bank'=>301));
$db->insert('paiement_facture',array('rowid'=>1,'fk_paiement'=>10,'fk_facture'=>20,'amount'=>100,'multicurrency_amount'=>100,'multicurrency_code'=>'USD'));
$phases[]=fi_snapshot($db,$service,'native_customer_evidence_seeded',0,0,'Seeded payment10 / deposit20 / bank301; no native posting performed');
$receiptData=array('fk_soc'=>7,'receipt_date'=>'2026-11-02','amount'=>100,'currency_code'=>'USD','reference'=>'SYN-ONLY','request_key'=>hash('sha256','M121 integration receipt'));
$receipt=$service->createPendingReceipt($receiptData,9);
fi_assert($service->createPendingReceipt($receiptData,9)===$receipt,'Receipt retry preserves actual identity');
$phases[]=fi_snapshot($db,$service,'pending_receipt',$receipt);
fi_assert(!$phases[2]['managed']['evidence_valid'],'Pending registration does not prove available funds');
fi_near($phases[2]['managed']['available'],0,'Pending receipt cannot be allocated');
$service->confirmReceipt($receipt,10,9); $service->confirmReceipt($receipt,10,9);
$phases[]=fi_snapshot($db,$service,'confirmed_receipt',$receipt);
fi_near($phases[3]['managed']['available'],100,'Confirmed native receipt available once');
$reserveData=array('target_type'=>'operation','target_id'=>101,'amount'=>40,'request_key'=>hash('sha256','M121 integration allocation'));
$allocation=$service->reserve($receipt,$reserveData,9);
fi_assert($service->reserve($receipt,$reserveData,9)===$allocation,'Reservation retry preserves identity');
$phases[]=fi_snapshot($db,$service,'reserved_attribution',$receipt,$allocation);
fi_near($phases[4]['managed']['available'],60,'Reservation controls40 without posting cash');
fi_near($phases[4]['supplier_invoice']['paid'],0,'Reservation cannot pay a supplier invoice');
fi_assert(!$phases[4]['lifecycle']['supplier_paid'],'Reservation cannot mark supplier fully paid');

// EXPLICIT FIXTURE BOUNDARY: existing native supplier payment/bank evidence, not postPayment().
$db->insert('bank',array('rowid'=>300,'fk_account'=>11,'amount'=>-40));
$db->insert('paiementfourn',array('rowid'=>200,'entity'=>1,'fk_bank'=>300,'fk_paiement'=>1,'datep'=>'2026-11-03'));
$db->insert('paiementfourn_facturefourn',array('rowid'=>1,'fk_paiementfourn'=>200,'fk_facturefourn'=>201,'amount'=>40,'multicurrency_amount'=>40));
$db->insert('cpo_supplier_action',array('rowid'=>401,'entity'=>1,'fk_operation'=>101,'fk_soc_customer'=>7,'fk_project'=>11,'fk_supplier'=>88,'fk_supplier_invoice'=>201,'fk_supplier_order'=>77,'fk_supplier_payment'=>200,'action_type'=>'supplier_payment','status'=>'confirmed'));
$phases[]=fi_snapshot($db,$service,'native_supplier_evidence_seeded',$receipt,$allocation,'Seeded payment200 / supplier invoice201 / bank300; no native posting performed');
$seededNative=array(); foreach (array('paiement','paiement_facture','paiementfourn','paiementfourn_facturefourn','bank') as $table) $seededNative[$table]=$db->rows($table);
fi_assert($service->settleWithSupplierPayment($allocation,200,9),'Actual supplier attribution succeeds');
fi_assert($service->settleWithSupplierPayment($allocation,200,9),'Same source retry remains idempotent');
$phases[]=fi_snapshot($db,$service,'settled_attribution',$receipt,$allocation);
$final=$phases[count($phases)-1];
fi_near($final['managed']['available'],60,'Managed remaining amount60 after attribution');
fi_near($final['managed']['managed_active'],40,'Reserved plus settled count as one controlled40');
fi_near($final['supplier_invoice']['paid'],40,'Native supplier projection paid40');
fi_near($final['supplier_invoice']['remaining'],20,'Native supplier projection remaining20');
fi_assert(!$final['lifecycle']['supplier_paid']&&$final['lifecycle']['deposit'],'Partial actual payment does not mark supplier fully paid');
fi_near($final['legacy_customer_summary']['USD']['available'],100,'Known incomplete legacy projection remains100, never secretly patched');
fi_near($final['commitments'][0]['remaining'],60,'Legacy commitment projection remains distinct from native supplier payment');
fi_assert($final['financial_entry_count']===0,'No fake CPCC debit was inserted to conceal projection gap');
fi_assert(count($final['receipts'])===1&&count($final['allocations'])===1&&$final['fund_event_count']===4,'One receipt, allocation, and four real service events');
fi_assert((int)$final['receipts'][0]['native_source_id']===10&&(int)$final['allocations'][0]['disbursement_id']===200&&$final['allocations'][0]['disbursement_type']==='supplier_payment','Original funding and disbursement source identities persist');
fi_assert($final['native_sources']===array('customer_payment_ids'=>array(10),'supplier_payment_ids'=>array(200),'bank_ids'=>array(300,301)),'Exactly two seeded native payments and bank source IDs remain');

$changed=$receiptData; $changed['amount']=101;
fi_reject(function () use ($service,$changed) { $service->createPendingReceipt($changed,9); },'Changed receipt retry payload rejected','Receipt request payload changed.');
$changed=$reserveData; $changed['amount']=41;
fi_reject(function () use ($service,$receipt,$changed) { $service->reserve($receipt,$changed,9); },'Changed reservation retry payload rejected','Allocation request payload changed.');
$tooMuch=$reserveData; $tooMuch['amount']=61; $tooMuch['request_key']=hash('sha256','M121 overreserve');
fi_reject(function () use ($service,$receipt,$tooMuch) { $service->reserve($receipt,$tooMuch,9); },'Over-reservation rejected','Allocation exceeds funds remaining after native Dolibarr credit use.');
fi_reject(function () use ($service,$receipt) { $service->confirmReceipt($receipt,999,9); },'Different confirmation payment rejected','Receipt is already confirmed with different evidence.');
fi_reject(function () use ($service,$allocation) { $service->settleWithSupplierPayment($allocation,999,9); },'Different supplier source retry rejected','Allocation is already settled with different evidence.');
$contextGuards=array('fk_soc'=>'Customer receipt evidence or allocatable coverage is no longer valid.','currency_code'=>'Customer receipt evidence or allocatable coverage is no longer valid.','fk_project'=>'Supplier payment project or currency does not match the allocation.','entity'=>'Customer funds allocation was not found.');
foreach (array('fk_soc'=>8,'fk_project'=>22,'currency_code'=>'EUR','entity'=>2) as $column=>$badValue) {
    $original=$db->pdo->query('SELECT '.$column.' FROM llx_cpcc_fund_allocation WHERE rowid='.$allocation)->fetchColumn();
    $q=$db->pdo->prepare('UPDATE llx_cpcc_fund_allocation SET '.$column.'=? WHERE rowid=?'); $q->execute(array($badValue,$allocation));
    try { fi_reject(function () use ($service,$allocation) { $service->settleWithSupplierPayment($allocation,200,9); },'Foreign attribution context rejected: '.$column,$contextGuards[$column]); }
    finally { $q->execute(array($original,$allocation)); }
}
$db->pdo->exec('UPDATE llx_cpo_operation SET fk_soc=8 WHERE rowid=101');
try { $foreign=$reserveData; $foreign['request_key']=hash('sha256','M121 foreign target'); fi_reject(function () use ($service,$receipt,$foreign) { $service->reserve($receipt,$foreign,9); },'Reservation rejects foreign target ownership','Allocation target does not belong to the customer and active entity.'); }
finally { $db->pdo->exec('UPDATE llx_cpo_operation SET fk_soc=7 WHERE rowid=101'); }
$after=fi_snapshot($db,$service,'negative_cases_preserved_state',$receipt,$allocation); $phases[]=$after;
fi_assert(count($after['receipts'])===1&&count($after['allocations'])===1&&$after['fund_event_count']===4&&$after['financial_entry_count']===0,'Negative cases preserve records and audit count');
fi_assert($after['native_sources']===$final['native_sources'],'Negative cases never create native payments/banks');
foreach ($seededNative as $table=>$rows) fi_assert($db->rows($table)===$rows,'Attribution and retries leave seeded native evidence byte-equivalent: '.$table);
fi_assert(!$db->pdo->inTransaction()&&$db->held===array(),'Owned synthetic transactions and emulated advisory locks released');
fi_assert(array_column($db->rows('cpcc_fund_event'),'action_code')===array('receipt_created','receipt_confirmed','funds_reserved','funds_settled'),'Actual service audit event sequence retained');
fi_assert(strpos(json_encode($final['lifecycle']),'INTERNAL SYNTHETIC SUPPLIER')===false,'Supplier lifecycle remains boolean projection');
$sourceHashes=array(); foreach (array('custom/chinaplanetcustomercenter/class/CustomerFundsService.class.php','custom/chinaplanetcustomercenter/class/ManagedFundsNativeGuard.class.php','custom/chinaplanetoperations/class/OperationSupplierWorkflowService.class.php','custom/chinaplanetcustomercenter/lib/financial.lib.php','custom/chinaplanetcustomercenter/lib/operations.lib.php') as $file) $sourceHashes[$file]=hash_file('sha256',$m121Root.'/'.$file);
$report=array('title'=>'M121 synthetic financial integration','status'=>'passed_with_known_projection_gap','assertions'=>$m121Checks,'source_root'=>$m121Root,'source_hashes'=>$sourceHashes,'assurance'=>'synthetic_SQLite_service_integration_only','known_projection_gap'=>array('managed_available'=>60,'legacy_customer_available'=>100,'legacy_commitment_remaining'=>60,'native_supplier_remaining'=>20,'resolution'=>'Unresolved; no fake financial entry or independent-ledger sum introduced.'),'native_posting_performed'=>false,'assumptions'=>array('Advisory locks and native trigger metadata emulated healthy','FOR UPDATE stripped; no concurrency/trigger/decimal/auth acceptance','Supplier invoiceSummary helper only; native object include files empty, no posting objects implemented'),'phases'=>$phases);
$json=json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR).PHP_EOL;
$output=null; foreach (array_slice($argv,1) as $arg) { if (strpos($arg,'--output=')===0) $output=substr($arg,9); elseif ($arg!=='--json') throw new InvalidArgumentException('Use --json or --output=/tmp/name.json'); }
if ($output!==null) {
    if (!preg_match('~\A(?:/tmp/|/Users/mac/Documents/dev/chinaplanet-OS/outputs/M121/)[A-Za-z0-9_./-]+\.json\z~',$output)||strpos($output,'..')!==false||is_link($output)) throw new InvalidArgumentException('JSON output must stay in a permitted evidence directory.');
    if (!is_dir(dirname($output))) mkdir(dirname($output),0700,true);
    $parent=realpath(dirname($output)); $tmp=realpath('/tmp'); $evidence=realpath('/Users/mac/Documents/dev/chinaplanet-OS/outputs/M121');
    if (!($parent===$tmp||strpos($parent,$tmp.DIRECTORY_SEPARATOR)===0||($evidence&&($parent===$evidence||strpos($parent,$evidence.DIRECTORY_SEPARATOR)===0)))) throw new InvalidArgumentException('Evidence output parent resolves outside permitted directories.');
    file_put_contents($output,$json); chmod($output,0600);
}
if (in_array('--json',$argv,true)) echo $json;
else echo 'M121 financial integration PASS ('.$m121Checks.' checks; actual SQLite service SQL; known legacy100 vs managed60 projection gap; native payments SEEDED, not posted)'.PHP_EOL;
