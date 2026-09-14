<?php
/** Additive M083 production schema and managed-funds guard. */
if (PHP_SAPI !== 'cli') { fwrite(STDERR,"M083_SCHEMA=FAIL REASON=CLI_ONLY\n"); exit(1); }
if ($argc !== 2 || !in_array($argv[1],array('check','apply','rollback-empty'),true)) { fwrite(STDERR,"M083_SCHEMA=FAIL REASON=COMMAND_NOT_ALLOWED\n"); exit(1); }
define('NOLOGIN',1);define('NOREQUIREMENU',1);define('NOREQUIREHTML',1);define('NOREQUIREAJAX',1);define('NOCSRFCHECK',1);
$root=rtrim(getenv('M083_DOL_DOCUMENT_ROOT')?:'/var/www/html','/');
if(!is_file($root.'/master.inc.php')){fwrite(STDERR,"M083_SCHEMA=FAIL REASON=DOLIBARR_ROOT_INVALID\n");exit(1);}
$_SERVER['DOCUMENT_ROOT']=$root;$_SERVER['SERVER_NAME']='erp.visiontrading.biz';$_SERVER['SERVER_PORT']='443';chdir($root);require $root.'/master.inc.php';global$db;
require_once dirname(__DIR__,2).'/custom/chinaplanetcustomercenter/class/ManagedFundsNativeGuard.class.php';

$spec=array(
 'cpcc_customer_receipt'=>array(
  array('entity','fk_soc','receipt_date','amount','currency_code','reference','status','native_source_type','native_source_id','request_key','payload_hash','note_private','fk_user_creat','fk_user_confirm','fk_user_modif','date_confirmation','date_creation','tms'),
  array('uk_cpcc_receipt_request'=>array('entity','request_key'),'uk_cpcc_receipt_native'=>array('entity','native_source_type','native_source_id'),'idx_cpcc_receipt_customer'=>array('entity','fk_soc','currency_code','status'),'idx_cpcc_receipt_date'=>array('entity','receipt_date')),
  'custom/chinaplanetcustomercenter/sql/llx_cpcc_customer_receipt.sql'),
 'cpcc_fund_allocation'=>array(
  array('entity','fk_receipt','fk_soc','target_type','target_id','fk_project','fk_operation','fk_shipment','amount','currency_code','status','disbursement_type','disbursement_id','request_key','payload_hash','note_private','fk_user_creat','fk_user_settle','fk_user_reverse','date_settlement','date_reversal','date_creation','tms'),
  array('uk_cpcc_allocation_request'=>array('entity','request_key'),'idx_cpcc_allocation_receipt'=>array('entity','fk_receipt','status'),'idx_cpcc_allocation_customer'=>array('entity','fk_soc','currency_code','status'),'idx_cpcc_allocation_target'=>array('entity','target_type','target_id'),'idx_cpcc_allocation_operation'=>array('entity','fk_operation'),'idx_cpcc_allocation_shipment'=>array('entity','fk_shipment'),'idx_cpcc_allocation_disbursement'=>array('entity','disbursement_type','disbursement_id')),
  'custom/chinaplanetcustomercenter/sql/llx_cpcc_fund_allocation.sql'),
 'cpcc_fund_event'=>array(
  array('entity','fk_soc','object_type','object_id','action_code','status_from','status_to','message','fk_user','date_action'),
  array('idx_cpcc_fund_event_object'=>array('entity','object_type','object_id','date_action'),'idx_cpcc_fund_event_customer'=>array('entity','fk_soc','date_action')),
  'custom/chinaplanetcustomercenter/sql/llx_cpcc_fund_event.sql'),
 'cpcc_guided_receipt'=>array(
  array('entity','fk_soc','fk_invoice','fk_payment','origin_type','origin_id','request_token','payload_hash','payment_token','payment_payload_hash','status','fk_user_creat','date_creation','tms'),
  array('uk_cpcc_guided_invoice'=>array('entity','fk_invoice'),'uk_cpcc_guided_payment'=>array('entity','fk_payment'),'uk_cpcc_guided_token'=>array('entity','request_token'),'uk_cpcc_guided_payment_token'=>array('entity','payment_token'),'idx_cpcc_guided_customer'=>array('entity','fk_soc','status')),
  'custom/chinaplanetcustomercenter/sql/llx_cpcc_guided_receipt.sql'),
 'cpo_billing_action'=>array(
  array('entity','fk_operation','action_type','request_token','invoice_key','payload_hash','status','fk_soc','fk_project','fk_order','fk_invoice','fk_payment','fk_bank','fk_bank_account','fk_payment_mode','payment_code','payment_date','payment_reference','amount','currency_code','error_code','fk_user_creat','fk_user_modif','date_creation','tms'),
  array('uk_cpo_billing_token'=>array('entity','request_token'),'uk_cpo_billing_invoice_key'=>array('entity','invoice_key'),'uk_cpo_billing_payment'=>array('entity','fk_payment'),'idx_cpo_billing_operation'=>array('entity','fk_operation','action_type','status'),'idx_cpo_billing_invoice'=>array('entity','fk_invoice','status')),
  'custom/chinaplanetoperations/sql/llx_cpo_billing_action.sql'),
 'cpo_supplier_action'=>array(
  array('entity','fk_operation','action_type','request_token','object_key','payload_hash','status','fk_soc_customer','fk_project','fk_customer_order','fk_supplier','fk_supplier_order','fk_supplier_invoice','fk_supplier_payment','fk_bank','fk_bank_account','fk_payment_mode','fk_allocation','payment_code','payment_date','payment_reference','amount','currency_code','exchange_rate','error_code','fk_user_creat','fk_user_modif','date_creation','tms'),
  array('uk_cpo_supplier_token'=>array('entity','request_token'),'uk_cpo_supplier_object'=>array('entity','object_key'),'uk_cpo_supplier_payment'=>array('entity','fk_supplier_payment'),'idx_cpo_supplier_operation'=>array('entity','fk_operation','action_type','status'),'idx_cpo_supplier_order'=>array('entity','fk_supplier_order','status'),'idx_cpo_supplier_invoice'=>array('entity','fk_supplier_invoice','status'),'idx_cpo_supplier_allocation'=>array('entity','fk_allocation','status')),
  'custom/chinaplanetoperations/sql/llx_cpo_supplier_action.sql'),
);
$query=function($sql,$reason)use($db){$res=$db->query($sql);if(!$res){fwrite(STDERR,'M083_SCHEMA=FAIL REASON='.$reason."\n");exit(1);}return$res;};
$exists=function($name)use($query,$db){$table=MAIN_DB_PREFIX.$name;$pattern=str_replace(array('\\','_','%'),array('\\\\','\\_','\\%'),$table);return$db->num_rows($query("SHOW TABLES LIKE '".$db->escape($pattern)."'",'TABLE_CHECK_FAILED'))===1;};
$ready=function($name,$definition)use($exists,$query,$db){if(!$exists($name))return false;$table=MAIN_DB_PREFIX.$name;$actual=array();$res=$query("SHOW COLUMNS FROM `".$table."`",'COLUMN_CHECK_FAILED');while($row=$db->fetch_object($res))$actual[]=(string)$row->Field;foreach($definition[0]as$column)if(!in_array($column,$actual,true))return false;foreach($definition[1]as$index=>$columns){$got=array();$res=$query("SHOW INDEX FROM `".$table."` WHERE Key_name='".$db->escape($index)."'",'INDEX_CHECK_FAILED');while($row=$db->fetch_object($res))$got[(int)$row->Seq_in_index]=(string)$row->Column_name;ksort($got);if(array_values($got)!==$columns)return false;}return true;};
$prerequisites=function()use($query,$db){foreach(array('cpo_operation','cpo_activity','cpo_document','cpcc_financial_entry','chinaplanetlogistics','paiement','paiement_facture','facture','paiementfourn','paiementfourn_facturefourn','facture_fourn','bank','bank_url','bank_account','societe_remise_except')as$name){$table=MAIN_DB_PREFIX.$name;$pattern=str_replace(array('\\','_','%'),array('\\\\','\\_','\\%'),$table);if($db->num_rows($query("SHOW TABLES LIKE '".$db->escape($pattern)."'",'PREREQUISITE_CHECK_FAILED'))!==1)return false;}$res=$query("SHOW COLUMNS FROM `".MAIN_DB_PREFIX."chinaplanetlogistics` LIKE 'customer_visible'",'VISIBILITY_COLUMN_CHECK_FAILED');return$db->num_rows($res)===1;};

$present=0;$valid=0;foreach($spec as$name=>$definition){if($exists($name)){$present++;if($ready($name,$definition))$valid++;}}
$guards=ManagedFundsNativeGuard::verify($db);
if($argv[1]==='check'){
 if(!$prerequisites()){echo"M083_SCHEMA_CHECK=INVALID REASON=PREREQUISITES\n";exit(3);}
 if($present===0&&!$guards){echo"M083_SCHEMA_CHECK=ABSENT TABLES=0 GUARDS=ABSENT\n";exit(2);}
 if($present===count($spec)&&$valid===count($spec)&&$guards){echo"M083_SCHEMA_CHECK=PASS TABLES=6 GUARDS=4\n";exit(0);}
 echo"M083_SCHEMA_CHECK=INVALID TABLES=".$present." VALID=".$valid." GUARDS=".($guards?'READY':'INVALID')."\n";exit(3);
}
if(!$prerequisites()){fwrite(STDERR,"M083_SCHEMA=FAIL REASON=PREREQUISITES\n");exit(1);}
if($argv[1]==='rollback-empty'){
 if($present===0&&!$guards){echo"M083_SCHEMA_ROLLBACK=PASS ALREADY_ABSENT=YES\n";exit(0);}
 if($present!==count($spec)||$valid!==count($spec)||!$guards){fwrite(STDERR,"M083_SCHEMA=FAIL REASON=ROLLBACK_STATE_INVALID\n");exit(1);}
 foreach(array_keys($spec)as$name){$row=$db->fetch_object($query("SELECT COUNT(*) n FROM `".MAIN_DB_PREFIX.$name."`",'ROW_COUNT_FAILED'));if((int)$row->n!==0){fwrite(STDERR,"M083_SCHEMA=FAIL REASON=TABLE_NOT_EMPTY TABLE=".$name."\n");exit(1);}}
 if(!ManagedFundsNativeGuard::remove($db)){fwrite(STDERR,"M083_SCHEMA=FAIL REASON=GUARD_REMOVE_FAILED\n");exit(1);}
 foreach(array_reverse(array_keys($spec))as$name)$query("DROP TABLE `".MAIN_DB_PREFIX.$name."`",'DROP_FAILED');
 echo"M083_SCHEMA_ROLLBACK=PASS TABLES_DROPPED=6 GUARDS_DROPPED=4 DATA_DELETED=NO\n";exit(0);
}
if($present!==0||$guards){fwrite(STDERR,"M083_SCHEMA=FAIL REASON=NONEMPTY_PRESTATE\n");exit(1);}
foreach($spec as$name=>$definition){$sql=file_get_contents(dirname(__DIR__,2).'/'.$definition[2]);if($sql===false||trim($sql)===''){fwrite(STDERR,"M083_SCHEMA=FAIL REASON=SQL_FILE_INVALID\n");exit(1);}$sql=preg_replace('/\bllx_/',MAIN_DB_PREFIX,$sql);$query($sql,'CREATE_FAILED');if(!$ready($name,$definition)){fwrite(STDERR,"M083_SCHEMA=FAIL REASON=READINESS_FAILED TABLE=".$name."\n");exit(1);}}
if(!ManagedFundsNativeGuard::install($db)||!ManagedFundsNativeGuard::verify($db)){fwrite(STDERR,"M083_SCHEMA=FAIL REASON=GUARD_INSTALL_FAILED\n");exit(1);}
echo"M083_SCHEMA_APPLY=PASS TABLES=6 GUARDS=4 ROWS=0\n";
