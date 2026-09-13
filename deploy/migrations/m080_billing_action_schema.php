<?php
/** Additive M080 C01 billing-action marker migration. */

if (PHP_SAPI !== 'cli') { fwrite(STDERR, "M080_SCHEMA=FAIL REASON=CLI_ONLY\n"); exit(1); }
if ($argc !== 2 || !in_array($argv[1], array('check','apply','rollback-empty'), true)) { fwrite(STDERR, "M080_SCHEMA=FAIL REASON=COMMAND_NOT_ALLOWED\n"); exit(1); }

define('NOLOGIN', 1);
define('NOREQUIREMENU', 1);
define('NOREQUIREHTML', 1);
define('NOREQUIREAJAX', 1);
define('NOCSRFCHECK', 1);

$root = getenv('M080_DOL_DOCUMENT_ROOT');
if ($root === false || $root === '') $root = '/var/www/html';
$root = rtrim($root, '/');
if (!is_file($root.'/master.inc.php')) { fwrite(STDERR, "M080_SCHEMA=FAIL REASON=DOLIBARR_ROOT_INVALID\n"); exit(1); }
$_SERVER['DOCUMENT_ROOT']=$root; $_SERVER['SERVER_NAME']='localhost'; $_SERVER['SERVER_PORT']='80';
require $root.'/master.inc.php';
global $db;

$table=MAIN_DB_PREFIX.'cpo_billing_action';
$expectedColumns=array('entity','fk_operation','action_type','request_token','invoice_key','payload_hash','status','fk_soc','fk_project','fk_order','fk_invoice','fk_payment','fk_bank','fk_bank_account','fk_payment_mode','payment_code','payment_date','payment_reference','amount','currency_code','error_code','fk_user_creat','fk_user_modif','date_creation','tms');
$expectedIndexes=array(
    'uk_cpo_billing_token'=>array('entity','request_token'),
    'uk_cpo_billing_invoice_key'=>array('entity','invoice_key'),
    'uk_cpo_billing_payment'=>array('entity','fk_payment'),
    'idx_cpo_billing_operation'=>array('entity','fk_operation','action_type','status'),
    'idx_cpo_billing_invoice'=>array('entity','fk_invoice','status'),
);
$query=function($sql,$reason)use($db){$res=$db->query($sql);if(!$res){fwrite(STDERR,'M080_SCHEMA=FAIL REASON='.$reason."\n");exit(1);}return $res;};
$exists=function()use($query,$db,$table){$pattern=str_replace(array('\\','_','%'),array('\\\\','\\_','\\%'),$table);return $db->num_rows($query("SHOW TABLES LIKE '".$db->escape($pattern)."'",'TABLE_CHECK_FAILED'))===1;};
$ready=function()use($exists,$query,$db,$table,$expectedColumns,$expectedIndexes){
    if(!$exists())return false;
    $actualColumns=array();$res=$query("SHOW COLUMNS FROM `".$table."`",'COLUMN_CHECK_FAILED');while($row=$db->fetch_object($res))$actualColumns[]=(string)$row->Field;
    foreach($expectedColumns as $column)if(!in_array($column,$actualColumns,true))return false;
    foreach($expectedIndexes as $name=>$columns){$actual=array();$res=$query("SHOW INDEX FROM `".$table."` WHERE Key_name='".$db->escape($name)."'",'INDEX_CHECK_FAILED');while($row=$db->fetch_object($res))$actual[(int)$row->Seq_in_index]=(string)$row->Column_name;ksort($actual);if(array_values($actual)!==$columns)return false;}
    return true;
};

if($argv[1]==='check'){
    if(!$exists()){echo "M080_SCHEMA_CHECK=ABSENT\n";exit(2);}if(!$ready()){echo "M080_SCHEMA_CHECK=INVALID\n";exit(3);}echo "M080_SCHEMA_CHECK=PASS\n";exit(0);
}
if($argv[1]==='rollback-empty'){
    if(!$exists()){echo "M080_SCHEMA_ROLLBACK=PASS ALREADY_ABSENT=YES\n";exit(0);}
    $res=$query("SELECT COUNT(*) count_rows FROM `".$table."`",'ROW_COUNT_FAILED');$row=$db->fetch_object($res);if((int)$row->count_rows!==0){fwrite(STDERR,"M080_SCHEMA=FAIL REASON=TABLE_NOT_EMPTY\n");exit(1);} $query("DROP TABLE `".$table."`",'DROP_FAILED');echo "M080_SCHEMA_ROLLBACK=PASS DATA_DELETED=NO\n";exit(0);
}

if($exists()){
    if(!$ready()){fwrite(STDERR,"M080_SCHEMA=FAIL REASON=EXISTING_TABLE_INVALID\n");exit(1);}echo "M080_SCHEMA_APPLY=PASS ALREADY_READY=YES\n";exit(0);
}
$sql=file_get_contents(dirname(__DIR__,2).'/custom/chinaplanetoperations/sql/llx_cpo_billing_action.sql');
if($sql===false){fwrite(STDERR,"M080_SCHEMA=FAIL REASON=SQL_FILE_MISSING\n");exit(1);}
$sql=preg_replace('/\bllx_/',MAIN_DB_PREFIX,$sql);
$query($sql,'CREATE_FAILED');
if(!$ready()){fwrite(STDERR,"M080_SCHEMA=FAIL REASON=READINESS_FAILED\n");exit(1);}echo "M080_SCHEMA_APPLY=PASS\n";
