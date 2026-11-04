<?php
/** M121 supplier alias regression: actual service/helper SQL on synthetic SQLite.
 * MySQL advisory locks/trigger metadata are assumed healthy; FOR UPDATE is stripped.
 * These are failure/identity probes, NOT a real concurrent InnoDB/trigger test.
 */
if (PHP_SAPI !== 'cli') exit(1);
error_reporting(E_ALL);
set_error_handler(function($severity,$message,$file,$line){throw new ErrorException($message,0,$severity,$file,$line);});
define('MAIN_DB_PREFIX','llx_'); $conf=(object)array('entity'=>1);
function getDolGlobalString($key){return $key==='MAIN_MONNAIE'?'USD':'';}
function getDolGlobalInt($key){return 0;}
function getDolCurrency(){return 'USD';}
require_once dirname(__DIR__,2).'/custom/chinaplanetcustomercenter/class/CustomerFundsService.class.php';
class M121AliasRows {public $rows;function __construct($rows){$this->rows=$rows;}}
class M121AliasDb {
 public $pdo;public $queries=array();public $failBegin=false;public $failCommit=false;public $failEvent=false;
 function __construct(){
  $this->pdo=new PDO('sqlite::memory:',null,null,array(PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION));
  foreach(array(
   'CREATE TABLE llx_societe(rowid INTEGER,entity INTEGER)',
   'CREATE TABLE llx_cpcc_customer_receipt(rowid INTEGER PRIMARY KEY AUTOINCREMENT,entity INTEGER,fk_soc INTEGER,receipt_date TEXT,amount REAL,currency_code TEXT,reference TEXT,status TEXT,native_source_type TEXT,native_source_id INTEGER,request_key TEXT,payload_hash TEXT,note_private TEXT,fk_user_creat INTEGER,fk_user_modif INTEGER,fk_user_confirm INTEGER,date_confirmation TEXT,date_creation TEXT)',
   'CREATE TABLE llx_cpcc_fund_event(rowid INTEGER PRIMARY KEY AUTOINCREMENT,entity INTEGER,fk_soc INTEGER,object_type TEXT,object_id INTEGER,action_code TEXT,status_from TEXT,status_to TEXT,message TEXT,fk_user INTEGER,date_action TEXT)',
   'CREATE TABLE llx_cpcc_fund_allocation(rowid INTEGER PRIMARY KEY,entity INTEGER,fk_receipt INTEGER,fk_soc INTEGER,target_type TEXT,target_id INTEGER,fk_project INTEGER,fk_operation INTEGER,amount REAL,currency_code TEXT,status TEXT,disbursement_type TEXT,disbursement_id INTEGER,fk_user_settle INTEGER,date_settlement TEXT,request_key TEXT)',
   'CREATE TABLE llx_paiement(rowid INTEGER PRIMARY KEY,entity INTEGER,statut INTEGER)',
   'CREATE TABLE llx_paiement_facture(fk_paiement INTEGER,fk_facture INTEGER,amount REAL,multicurrency_amount REAL)',
   'CREATE TABLE llx_facture(rowid INTEGER PRIMARY KEY,entity INTEGER,fk_soc INTEGER,type INTEGER,multicurrency_code TEXT)',
   'CREATE TABLE llx_societe_remise_except(rowid INTEGER PRIMARY KEY,entity INTEGER,fk_facture_source INTEGER,fk_facture INTEGER,fk_facture_line INTEGER,amount_ttc REAL,multicurrency_amount_ttc REAL)',
   'CREATE TABLE llx_facturedet(rowid INTEGER,fk_facture INTEGER)',
   'CREATE TABLE llx_paiementfourn(rowid INTEGER PRIMARY KEY,entity INTEGER,fk_bank INTEGER,fk_paiement INTEGER,datep TEXT)',
   'CREATE TABLE llx_paiementfourn_facturefourn(fk_paiementfourn INTEGER,fk_facturefourn INTEGER,amount REAL,multicurrency_amount REAL)',
   'CREATE TABLE llx_facture_fourn(rowid INTEGER PRIMARY KEY,entity INTEGER,fk_soc INTEGER,fk_projet INTEGER,multicurrency_code TEXT)',
   'CREATE TABLE llx_bank(rowid INTEGER PRIMARY KEY,fk_account INTEGER)',
   'CREATE TABLE llx_cpo_supplier_action(rowid INTEGER,entity INTEGER,fk_operation INTEGER,fk_supplier_payment INTEGER,fk_soc_customer INTEGER,fk_project INTEGER)',
   'CREATE TABLE llx_cpo_operation(rowid INTEGER,entity INTEGER,fk_soc INTEGER,fk_project INTEGER)'
  ) as $sql)$this->pdo->exec($sql);
  $this->pdo->exec('INSERT INTO llx_societe VALUES(7,1)');
 }
 function begin(){if($this->failBegin)return 0;return $this->pdo->beginTransaction()?1:0;}
 function commit(){if($this->failCommit)return 0;return $this->pdo->commit()?1:0;}
 function rollback(){return $this->pdo->inTransaction()?$this->pdo->rollBack():0;}
 function escape($s){return str_replace("'","''",$s);}
 function idate($time){return '2026-11-03 12:00:00';}
 function last_insert_id($table){return (int)$this->pdo->lastInsertId();}
 function lasterror(){return 'Synthetic injected API failure';}
 function fetch_object($r){return array_shift($r->rows);}
 function query($sql){
  $this->queries[]=$sql;
  if(strpos($sql,'GET_LOCK(')!==false || strpos($sql,'RELEASE_LOCK(')!==false)return new M121AliasRows(array((object)array('acquired'=>1)));
  if(strpos($sql,'INFORMATION_SCHEMA.TRIGGERS')!==false){
   $method=new ReflectionMethod('ManagedFundsNativeGuard','definitions');$method->setAccessible(true);
   foreach($method->invoke(null) as $name=>$d)if(strpos($sql,"TRIGGER_NAME='".$name."'")!==false)return new M121AliasRows(array((object)array('ACTION_TIMING'=>'BEFORE','EVENT_MANIPULATION'=>$d['event'],'EVENT_OBJECT_TABLE'=>$d['table'],'ACTION_STATEMENT'=>$d['body'])));
   throw new RuntimeException('Unexpected trigger metadata request');
  }
  if($this->failEvent && strpos($sql,'INSERT INTO llx_cpcc_fund_event')===0)return false;
  $sql=preg_replace('/ FOR UPDATE$/','',$sql);
  $stmt=$this->pdo->query($sql); return new M121AliasRows($stmt->columnCount()?$stmt->fetchAll(PDO::FETCH_OBJ):array());
 }
 function count($table){return (int)$this->pdo->query('SELECT COUNT(*) FROM '.$table)->fetchColumn();}
}

// Only private picker/assert helpers are exercised; native cash object bootstraps are empty files.
$nativeStubs=sys_get_temp_dir().'/m121-alias-'.bin2hex(random_bytes(6));
$stubFiles=array('commande/class/commande.class.php','fourn/class/fournisseur.commande.class.php','fourn/class/fournisseur.facture.class.php','fourn/class/paiementfourn.class.php','compta/bank/class/account.class.php','societe/class/societe.class.php','custom/chinaplanetcustomercenter/class/CustomerFundsService.class.php');
foreach($stubFiles as$file){$path=$nativeStubs.'/'.$file;if(!is_dir(dirname($path)))mkdir(dirname($path),0700,true);file_put_contents($path,"<?php\n");}
register_shutdown_function(function()use($nativeStubs){$iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($nativeStubs,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);foreach($iterator as$file){if($file->isDir())rmdir($file->getPathname());else unlink($file->getPathname());}rmdir($nativeStubs);});
define('DOL_DOCUMENT_ROOT',$nativeStubs);
require_once dirname(__DIR__,2).'/custom/chinaplanetoperations/class/OperationSupplierWorkflowService.class.php';
function m121a_call($object,$method,...$arguments){$r=new ReflectionMethod($object,$method);$r->setAccessible(true);return $r->invokeArgs($object,$arguments);}
$m121AliasChecks=0;
function m121a_assert($ok,$message){$GLOBALS['m121AliasChecks']++;if(!$ok)throw new RuntimeException($message);}
function m121a_throws($call,$message){try{$call();}catch(RuntimeException$e){m121a_assert(true,$message);return;}m121a_assert(false,$message);}
function m121a_fixture($nativeCurrency,$managedCurrency){
 $db=new M121AliasDb();$receiptCurrency=cpcc_financial_currency($managedCurrency,'');$depositNative=$receiptCurrency==='RMB'?'CNY':$receiptCurrency;
 $baseAmount=$nativeCurrency==='USD'?40:8;$multiAmount=$nativeCurrency==='USD'?7:40;
 foreach(array(
 "INSERT INTO llx_paiement VALUES(10,1,1)",
 "INSERT INTO llx_facture VALUES(20,1,7,3,'".$depositNative."')",
 "INSERT INTO llx_paiement_facture VALUES(10,20,100,100)",
 "INSERT INTO llx_cpcc_customer_receipt(rowid,entity,fk_soc,receipt_date,amount,currency_code,status,native_source_type,native_source_id) VALUES(1,1,7,'2026-11-03',100,'".$receiptCurrency."','confirmed','customer_payment',10)",
 "INSERT INTO llx_cpcc_fund_allocation(rowid,entity,fk_receipt,fk_soc,target_type,target_id,fk_project,fk_operation,amount,currency_code,status,request_key) VALUES(1,1,1,7,'operation',101,11,101,40,'".$managedCurrency."','reserved','SYN')",
 "INSERT INTO llx_cpo_operation VALUES(101,1,7,11)",
 "INSERT INTO llx_paiementfourn VALUES(200,1,300,1,'2026-11-03')",
 "INSERT INTO llx_facture_fourn VALUES(201,1,88,11,'".$nativeCurrency."')",
 "INSERT INTO llx_paiementfourn_facturefourn VALUES(200,201,".$baseAmount.",".$multiAmount.")",
 "INSERT INTO llx_bank VALUES(300,11)",
 "INSERT INTO llx_cpo_supplier_action VALUES(400,1,101,200,7,11)"
 )as$sql)$db->pdo->exec($sql);
 return $db;
}
$operation=array('rowid'=>101,'fk_soc'=>7,'fk_project'=>11);
m121a_assert(cpcc_financial_currency('CNY','')==='RMB','Fixture managed alias comes from existing canonical normalizer');
foreach(array(array('CNY','RMB'),array('RMB','CNY'),array('CNY','CNY'),array('USD','USD'))as$pair){
 list($native,$managed)=$pair;$db=m121a_fixture($native,$managed);$ops=new OperationSupplierWorkflowService($db,1);
 m121a_assert(count(m121a_call($ops,'availableAllocations',$operation,$native))===1,'Picker keeps equivalent original-currency aliases '.$native.'/'.$managed);
 m121a_call($ops,'assertAllocationSelectable',1,$operation,$native,40);m121a_assert(true,'Exact operation allocation assertion accepts alias');
 m121a_throws(function()use($ops,$operation,$native){m121a_call($ops,'assertAllocationSelectable',1,$operation,$native,39);},'Amount ceiling remains enforced');
 $funds=new CustomerFundsService($db,1);m121a_assert($funds->settleWithSupplierPayment(1,200,9),'Actual supplier settlement uses original amount40, not base8 or USD multi7');
 m121a_assert($funds->settleWithSupplierPayment(1,200,9),'Same native identity retry remains idempotent');
 m121a_assert($db->count('llx_cpcc_fund_event')===1,'Retry does not append another settlement event');
 m121a_throws(function()use($funds){$funds->settleWithSupplierPayment(1,201,9);},'Changed payment identity retry still fails');
 $row=$db->pdo->query('SELECT currency_code,amount,disbursement_id FROM llx_cpcc_fund_allocation')->fetch(PDO::FETCH_ASSOC);
 m121a_assert($row['currency_code']===$managed&&(float)$row['amount']===40.0&&(int)$row['disbursement_id']===200,'Normalization does not rewrite managed amount/code/source');
 m121a_assert($db->pdo->query('SELECT multicurrency_code FROM llx_facture_fourn')->fetchColumn()===$native,'Native original currency code preserved');
}
$db=m121a_fixture('EUR','RMB');$ops=new OperationSupplierWorkflowService($db,1);
m121a_assert(m121a_call($ops,'availableAllocations',$operation,'EUR')===array(),'Unrelated currency not offered');
m121a_throws(function()use($ops,$operation){m121a_call($ops,'assertAllocationSelectable',1,$operation,'EUR',40);},'Unrelated currency assertion rejects');
m121a_throws(function()use($db){(new CustomerFundsService($db,1))->settleWithSupplierPayment(1,200,9);},'Unrelated native payment cannot settle allocation');
m121a_assert($db->pdo->query('SELECT status FROM llx_cpcc_fund_allocation')->fetchColumn()==='reserved','Failed comparison does not settle');
foreach(array("entity=2","fk_soc=8","fk_project=22","target_id=102","fk_operation=102","status='reversed'")as$change){
 $db=m121a_fixture('CNY','RMB');$db->pdo->exec('UPDATE llx_cpcc_fund_allocation SET '.$change);$ops=new OperationSupplierWorkflowService($db,1);
 m121a_assert(m121a_call($ops,'availableAllocations',$operation,'CNY')===array(),'Picker preserves exact funder/operation/project/entity/status scope '.$change);
 m121a_throws(function()use($ops,$operation){m121a_call($ops,'assertAllocationSelectable',1,$operation,'CNY',40);},'Assertion preserves same scope '.$change);
 m121a_throws(function()use($db){(new CustomerFundsService($db,1))->settleWithSupplierPayment(1,200,9);},'Settlement preserves existing scope checks '.$change);
}
echo 'M121 supplier currency alias regressions passed ('.$m121AliasChecks." checks; actual SQLite SQL; healthy guard assumptions, no cash posting or concurrency proof)\n";
