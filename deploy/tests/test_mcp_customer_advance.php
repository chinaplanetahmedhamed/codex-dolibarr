<?php
/** Integration test: only the dedicated synthetic M088 local database is accepted. */
if (PHP_SAPI !== 'cli') exit(1);
$root = getenv('M088_QA_ROOT');
if (!$root || !is_file($root.'/conf/conf.php')) exit('Set M088_QA_ROOT to the isolated htdocs.');
include $root.'/conf/conf.php';
if (!preg_match('/^m088_qa_[a-f0-9]{10}$/D', $dolibarr_main_db_name) || $dolibarr_main_db_host !== '127.0.0.1' || (int) $dolibarr_main_db_port !== 8898) exit('Isolated local fixture required.');
chdir($root);
define('NOLOGIN',1); define('NOREQUIREMENU',1); define('NOREQUIREHTML',1); define('NOREQUIREAJAX',1);
require $root.'/master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
require_once DOL_DOCUMENT_ROOT.'/ai/class/mcp.class.php';
if (isset($argv[1]) && $argv[1] === '--call') {
    $user=new User($db); $user->fetch(1); $user->loadRights('',1);
    $worker=new McpHandler($db,$user,$conf,McpHandler::CTX_MCP_SERVER);
    echo json_encode($worker->executeTool('record_customer_advance',json_decode(stream_get_contents(STDIN),true)));
    exit;
}
// Register native permission definitions in this empty synthetic installation.
$user = new User($db); $user->fetch(1);
foreach (array('Societe','Facture','Banque','User') as $moduleName) {
    require_once DOL_DOCUMENT_ROOT.'/core/modules/mod'.$moduleName.'.class.php';
    $class = 'mod'.$moduleName;
    $module = new $class($db);
    $module->insert_permissions(1, 1, 1);
}

function check($condition,$label,$detail=null) { if (!$condition) { fwrite(STDERR,'FAIL '.$label.' '.json_encode($detail)."\n"); exit(1); } echo 'PASS '.$label."\n"; }
function asUser($id) { global $db,$user,$conf; $user=new User($db); check($user->fetch($id)>0,'load fixture user '.$id); $user->loadRights('',1); return new McpHandler($db,$user,$conf,McpHandler::CTX_MCP_SERVER); }
function countRows() { global $db; $out=array();foreach(array('facture','facturedet','paiement','paiement_facture','bank','bank_url','societe_remise_except') as $t){$q=$db->query('SELECT COUNT(*) AS n FROM '.MAIN_DB_PREFIX.$t);$out[$t]=(int)$db->fetch_object($q)->n;}return $out; }
$mcp=asUser(1);
$names=array_column($mcp->getToolsSchema(),'name');
check(in_array('record_customer_advance',$names,true) && in_array('get_customer_advance_options',$names,true),'native MCP hook discovery',$names);
$options=$mcp->executeTool('get_customer_advance_options',array('customer_id'=>101));
check(empty($options['error']) && count($options['receiving_accounts'])===3,'entity-filtered receiving accounts',$options);
$base=array('customer_id'=>101,'amount'=>25.0,'currency'=>'USD','receipt_date'=>date('Y-m-d'),'bank_account_id'=>201,'payment_mode'=>'VIR','vat_rate'=>0,'receipt_reference'=>'M088-'.bin2hex(random_bytes(8)));
$initial=countRows();
$receipt=$mcp->executeTool('record_customer_advance',$base);
check(!empty($receipt['success']) && !$receipt['replayed'] && $receipt['available_from_this_receipt']===25.0,'native receipt + available credit',$receipt);
$after=countRows();
foreach (array('facture','facturedet','paiement','paiement_facture','bank','societe_remise_except') as $table) check($after[$table]-$initial[$table]===1,'one native '.$table.' record');
$again=$mcp->executeTool('record_customer_advance',$base);
check(!empty($again['replayed']) && $again['invoice_id']===$receipt['invoice_id'] && countRows()===$after,'idempotent retry',$again);
$bad=$base;$bad['amount']=26.0;
check(isset($mcp->executeTool('record_customer_advance',$bad)['error']) && countRows()===$after,'reference conflict cannot repost');
file_put_contents(dirname($root).'/latest-receipt.json',json_encode($receipt,JSON_PRETTY_PRINT));

function freshReceipt($base, $changes=array()) { return array_merge($base,array('receipt_reference'=>'M088-'.bin2hex(random_bytes(8))),$changes); }
function deniedReceipt($mcp,$args,$label) { $before=countRows();$r=$mcp->executeTool('record_customer_advance',$args);check(isset($r['error']) && countRows()===$before,$label,$r); }
foreach (array(
    'zero amount'=>array('amount'=>0), 'negative amount'=>array('amount'=>-1), 'fractional cent'=>array('amount'=>1.001),
    'numeric string'=>array('amount'=>'25'), 'invalid date'=>array('receipt_date'=>'2026-02-30'), 'future date'=>array('receipt_date'=>'2099-01-01'),
    'closed account'=>array('bank_account_id'=>203), 'cross-entity account'=>array('bank_account_id'=>205),
    'account currency mismatch'=>array('bank_account_id'=>202), 'cash to bank mismatch'=>array('payment_mode'=>'LIQ'),
    'cross-entity customer'=>array('customer_id'=>102), 'unknown customer'=>array('customer_id'=>99999),
    'disabled payment method'=>array('payment_mode'=>'TIP'), 'unknown fields'=>array('internal_override'=>true),
    'foreign currency missing FX'=>array('currency'=>'CNY','bank_account_id'=>202), 'FX for base currency'=>array('exchange_rate'=>1),
    'oversize reference'=>array('receipt_reference'=>str_repeat('X',51))
) as $label=>$changes) deniedReceipt($mcp,freshReceipt($base,$changes),$label);
$missing=freshReceipt($base);unset($missing['vat_rate']);deniedReceipt($mcp,$missing,'VAT cannot be silently defaulted');
foreach(array(array(47,'M088CODE',0),array(48,'',1)) as $specialTax){
    check((bool)$db->query("INSERT INTO ".MAIN_DB_PREFIX."c_tva(fk_pays,taux,code,recuperableonly,active,entity) VALUES(115,".$specialTax[0].",'".$specialTax[1]."',".$specialTax[2].",1,1)"),'create isolated special VAT fixture');
    $specialTaxId=$db->last_insert_id(MAIN_DB_PREFIX.'c_tva');
    try { deniedReceipt($mcp,freshReceipt($base,array('vat_rate'=>$specialTax[0])),'unsupported coded/NPR VAT fails closed'); }
    finally { $db->query('DELETE FROM '.MAIN_DB_PREFIX.'c_tva WHERE rowid='.(int)$specialTaxId); }
}
$mcp=asUser(2);deniedReceipt($mcp,freshReceipt($base),'read-denied internal user');
check(isset($mcp->executeTool('get_customer_advance_options',array('customer_id'=>101))['error']),'read-denied options');
$mcp=asUser(3);deniedReceipt($mcp,freshReceipt($base),'external user even with admin flag');
$mcp=asUser(1);
$conf->global->AI_MCP_SERVER_ALLOWED_TOOLS='get_customer_advance_options';
check(!in_array('record_customer_advance',array_column($mcp->getToolsSchema(),'name'),true),'server allow-list hides write tool');
deniedReceipt($mcp,freshReceipt($base),'server allow-list blocks direct execution');
unset($conf->global->AI_MCP_SERVER_ALLOWED_TOOLS);

// Actual native user-right rows and assigned-customer filtering, not mock permission methods.
$staff=new User($db);$staff->fetch(4);
foreach(array(121,11,111,12,16,112) as $rid) $staff->addrights($rid,'','',1,1);
$db->query('DELETE FROM '.MAIN_DB_PREFIX.'societe_commerciaux WHERE fk_user=4');
$db->query('INSERT INTO '.MAIN_DB_PREFIX.'societe_commerciaux(fk_soc,fk_user) VALUES(101,4)');
$mcp=asUser(4);
deniedReceipt($mcp,freshReceipt($base,array('customer_id'=>103)),'unassigned customer denied');
$conf->global->MAIN_USE_ADVANCED_PERMS=1;
$mcp=asUser(4);deniedReceipt($mcp,freshReceipt($base),'advanced invoice validation permission required');
unset($conf->global->MAIN_USE_ADVANCED_PERMS);
$mcp=asUser(4);$staffReceipt=$mcp->executeTool('record_customer_advance',freshReceipt($base));
check(!empty($staffReceipt['success']),'authorized assigned staff receipt',$staffReceipt);
$staff->delrights(112,'','',1,1);$mcp=asUser(4);deniedReceipt($mcp,freshReceipt($base),'native bank-write permission required');
$mcp=asUser(1);

$fxArgs=freshReceipt($base,array('amount'=>70.0,'currency'=>'CNY','bank_account_id'=>202,'exchange_rate'=>7.0,'exchange_rate_date'=>date('Y-m-d'),'exchange_rate_source'=>'M088 synthetic fixed test rate'));
$fx=$mcp->executeTool('record_customer_advance',$fxArgs);
check(!empty($fx['success']) && $fx['received_amount']===70.0 && $fx['currency']==='CNY','foreign receipt retains original currency',$fx);
$q=$db->query('SELECT f.total_ttc,f.multicurrency_total_ttc,b.amount,b.amount_main_currency FROM '.MAIN_DB_PREFIX.'facture f JOIN '.MAIN_DB_PREFIX.'bank b ON b.rowid='.(int)$fx['bank_transaction_id'].' WHERE f.rowid='.(int)$fx['invoice_id']);$fxRow=$db->fetch_object($q);
check((float)$fxRow->total_ttc===10.0 && (float)$fxRow->multicurrency_total_ttc===70.0 && (float)$fxRow->amount===70.0 && (float)$fxRow->amount_main_currency===10.0,'native FX amount and bank reconciliation',$fxRow);
$cash=$mcp->executeTool('record_customer_advance',freshReceipt($base,array('payment_mode'=>'LIQ','bank_account_id'=>204)));
check(!empty($cash['success']),'native cash account receipt',$cash);
$tax=$mcp->executeTool('record_customer_advance',freshReceipt($base,array('amount'=>110.0,'vat_rate'=>10.0)));
check(!empty($tax['success']) && $tax['available_from_this_receipt']===110.0,'explicit VAT gross amount conserved',$tax);

// Fail the last native bank-link write, after invoice, payment, credit and bank line exist.
$rollbackArgs=freshReceipt($base);$before=countRows();
check((bool)$db->query("CREATE TRIGGER m088_fail_bank_link BEFORE INSERT ON ".MAIN_DB_PREFIX."bank_url FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='M088 injected failure'"),'install isolated failure injection');
try {
    $failed=$mcp->executeTool('record_customer_advance',$rollbackArgs);
    check(isset($failed['error']) && countRows()===$before && $db->transaction_opened===0,'all native records roll back after late bank failure',$failed);
} finally { $db->query('DROP TRIGGER m088_fail_bank_link'); }
$retried=$mcp->executeTool('record_customer_advance',$rollbackArgs);
check(!empty($retried['success']) && !$retried['replayed'],'same reference retry after rollback succeeds',$retried);
// Fresh DB connection sees the persisted invoice/payment/credit/bank join.
$fresh=new mysqli($dolibarr_main_db_host,$dolibarr_main_db_user,$dolibarr_main_db_pass,$dolibarr_main_db_name,(int)$dolibarr_main_db_port);
$persist=$fresh->query('SELECT f.rowid FROM llx_facture f JOIN llx_paiement_facture pf ON pf.fk_facture=f.rowid JOIN llx_paiement p ON p.rowid=pf.fk_paiement JOIN llx_bank b ON b.rowid=p.fk_bank JOIN llx_societe_remise_except r ON r.fk_facture_source=f.rowid WHERE f.rowid='.(int)$retried['invoice_id']);
check($persist->num_rows===1,'fresh-connection persistence');$fresh->close();

$parallelArgs=freshReceipt($base);$parallelBefore=countRows();
$lock='cpcc-advance-'.substr(hash('sha256',$conf->entity.':'.$parallelArgs['receipt_reference']),0,48);
$db->query("SELECT GET_LOCK('".$lock."',1)");
$workers=array();
for($i=0;$i<2;$i++) {
    $process=proc_open(array(PHP_BINARY,__FILE__,'--call'),array(0=>array('pipe','r'),1=>array('pipe','w'),2=>array('pipe','w')),$pipes);
    check(is_resource($process),'start concurrent worker');
    fwrite($pipes[0],json_encode($parallelArgs));fclose($pipes[0]);$workers[]=array($process,$pipes);
}
$waiting=0;
for($i=0;$i<30;$i++) {
    $q=$db->query('SHOW PROCESSLIST');$waiting=0;
    while($row=$db->fetch_object($q)) if(!empty($row->Info) && strpos($row->Info,"GET_LOCK('".$lock."'")!==false) $waiting++;
    if($waiting===2)break;
    usleep(100000);
}
$db->query("SELECT RELEASE_LOCK('".$lock."')");
$workerResults=array();
foreach($workers as $worker){$raw=stream_get_contents($worker[1][1]);$err=stream_get_contents($worker[1][2]);fclose($worker[1][1]);fclose($worker[1][2]);$exit=proc_close($worker[0]);check($exit===0 && $err==='','concurrent worker exits cleanly',$err);$workerResults[]=json_decode($raw,true);}
check($waiting===2,'two independent requests waited on same receipt lock');
check(!empty($workerResults[0]['success']) && !empty($workerResults[1]['success']) && $workerResults[0]['invoice_id']===$workerResults[1]['invoice_id'] && $workerResults[0]['replayed']!==$workerResults[1]['replayed'],'concurrent requests return one receipt',$workerResults);
$parallelAfter=countRows();
foreach(array('facture','paiement','bank','societe_remise_except') as $t)check($parallelAfter[$t]-$parallelBefore[$t]===1,'concurrent exactly-one '.$t);
echo "INTEGRATION SUITE COMPLETE\n";
