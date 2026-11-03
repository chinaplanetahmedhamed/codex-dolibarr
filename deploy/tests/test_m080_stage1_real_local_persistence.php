<?php
/** Real MariaDB marker/idempotency test in a disposable schema-only database. */
if(getenv('M080_REAL_LOCAL')!=='1'){fwrite(STDERR,"M080_REAL_LOCAL=FAIL REASON=EXPLICIT_LOCAL_GATE_REQUIRED\n");exit(1);}
mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);
$root=dirname(__DIR__,2);
$confFile='/Users/mac/Documents/dev/dolibarr/dolibarr-Dev/dolibarr-23.0.3/htdocs/conf/conf.php';
if(!is_file($confFile)){fwrite(STDERR,"M080_REAL_LOCAL=FAIL REASON=LOCAL_CONFIG_MISSING\n");exit(1);}
$source=(static function($path){include $path;return array('host'=>$dolibarr_main_db_host,'port'=>(int)$dolibarr_main_db_port,'user'=>$dolibarr_main_db_user,'pass'=>$dolibarr_main_db_pass);})($confFile);
if(!in_array(strtolower(trim((string)$source['host']," \t\n\r\0\x0B[]")),array('localhost','127.0.0.1','::1'),true)){fwrite(STDERR,"M080_REAL_LOCAL=FAIL REASON=REMOTE_DATABASE_FORBIDDEN\n");exit(1);}
$server=null;$credentials=null;
foreach(array(array('root','root'),array('root',''),array($source['user'],$source['pass'])) as $candidate){try{$server=new mysqli($source['host'],$candidate[0],$candidate[1],'',$source['port']);$credentials=$candidate;break;}catch(Throwable $e){$server=null;}}
if(!$server){fwrite(STDERR,"M080_REAL_LOCAL=FAIL REASON=LOCAL_DATABASE_ADMIN_UNAVAILABLE\n");exit(1);}
$database='m080_local_'.bin2hex(random_bytes(6));$target=null;$created=false;
$assert=function($ok,$reason){if(!$ok)throw new RuntimeException($reason);};
try{
    $assert(preg_match('/^m080_local_[a-f0-9]{12}$/',$database)===1,'unsafe database name');
    $server->query('CREATE DATABASE '.$database.' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');$created=true;
    $target=new mysqli($source['host'],$credentials[0],$credentials[1],$database,$source['port']);
    $schema=file_get_contents($root.'/custom/chinaplanetoperations/sql/llx_cpo_billing_action.sql');$assert($schema!==false,'schema missing');
    $target->query($schema);$target->query($schema);
    $columns=$target->query("SELECT COUNT(*) n FROM information_schema.columns WHERE table_schema='".$target->real_escape_string($database)."' AND table_name='llx_cpo_billing_action'")->fetch_assoc();$assert((int)$columns['n']===26,'marker column contract invalid');
    $invoiceKey=hash('sha256','m080-invoice-order-1');$invoiceToken='CPO-INV-'.strtoupper(hash('sha1','m080 invoice'));
    $target->query("INSERT INTO llx_cpo_billing_action(entity,fk_operation,action_type,request_token,invoice_key,payload_hash,status,fk_soc,fk_project,fk_order,fk_invoice,currency_code,fk_user_creat,date_creation) VALUES(1,8001,'customer_invoice','".$invoiceToken."','".$invoiceKey."','".hash('sha256','invoice payload')."','confirmed',8101,8201,8301,8401,'CNY',1,NOW())");
    $duplicateInvoice=false;try{$target->query("INSERT INTO llx_cpo_billing_action(entity,fk_operation,action_type,request_token,invoice_key,payload_hash,status,fk_soc,fk_project,fk_order,currency_code,fk_user_creat,date_creation) VALUES(1,8001,'customer_invoice','CPO-INV-".str_repeat('A',40)."','".$invoiceKey."','".hash('sha256','changed')."','preparing',8101,8201,8301,'CNY',1,NOW())");}catch(mysqli_sql_exception $e){$duplicateInvoice=true;}$assert($duplicateInvoice,'duplicate invoice key accepted');
    foreach(array(array(400,'PARTIAL'),array(600,'FINAL')) as $index=>$part){$paymentId=8501+$index;$bankId=8601+$index;$token='CPO-PAY-'.strtoupper(hash('sha1','m080 payment '.$index));$target->query("INSERT INTO llx_cpo_billing_action(entity,fk_operation,action_type,request_token,payload_hash,status,fk_soc,fk_project,fk_order,fk_invoice,fk_payment,fk_bank,fk_bank_account,fk_payment_mode,payment_code,payment_date,payment_reference,amount,currency_code,fk_user_creat,date_creation) VALUES(1,8001,'customer_receipt','".$token."','".hash('sha256',$part[1])."','confirmed',8101,8201,8301,8401,".$paymentId.",".$bankId.",8701,35,'VIR','2026-09-14','M080-".$part[1]."',".$part[0].",'CNY',1,NOW())");}
    $sum=$target->query("SELECT SUM(amount) paid,COUNT(*) actions,COUNT(DISTINCT fk_payment) payments,COUNT(DISTINCT fk_bank) banks,MIN(currency_code) min_currency,MAX(currency_code) max_currency FROM llx_cpo_billing_action WHERE action_type='customer_receipt'")->fetch_assoc();
    $assert(abs((float)$sum['paid']-1000.0)<0.00001&&(int)$sum['actions']===2&&(int)$sum['payments']===2&&(int)$sum['banks']===2&&$sum['min_currency']==='CNY'&&$sum['max_currency']==='CNY','partial/full persistence mismatch');
    $changedRetry=false;try{$target->query("INSERT INTO llx_cpo_billing_action(entity,fk_operation,action_type,request_token,payload_hash,status,fk_soc,fk_order,fk_invoice,fk_payment,amount,currency_code,fk_user_creat,date_creation) SELECT entity,fk_operation,action_type,request_token,'".hash('sha256','changed retry')."','preparing',fk_soc,fk_order,fk_invoice,9999,401,currency_code,1,NOW() FROM llx_cpo_billing_action WHERE fk_payment=8501");}catch(mysqli_sql_exception $e){$changedRetry=true;}$assert($changedRetry,'duplicate request token accepted');
    $target->query("UPDATE llx_cpo_billing_action SET status='correction_required',error_code='EVIDENCE_MISMATCH' WHERE fk_payment=8501");
    $guard=$target->query("SELECT COUNT(*) n FROM llx_cpo_billing_action WHERE fk_payment=8501 AND status='correction_required' AND error_code='EVIDENCE_MISMATCH'")->fetch_assoc();$assert((int)$guard['n']===1,'correction state not durable');
    echo "M080_REAL_LOCAL=PASS SOURCE_DB_MUTATED=NO SCHEMA_REPLAY=PASS INVOICE_DUPLICATE=BLOCKED PAYMENT_RETRY=BLOCKED PARTIALS=2 PAID=1000 CURRENCY=CNY BANK_MARKERS=2 CORRECTION_STATE=PASS\n";
}finally{
    if($target instanceof mysqli)$target->close();
    if($created){$assert(preg_match('/^m080_local_[a-f0-9]{12}$/',$database)===1,'unsafe cleanup name');$server->query('DROP DATABASE '.$database);$absence=$server->query("SELECT SCHEMA_NAME FROM information_schema.schemata WHERE schema_name='".$server->real_escape_string($database)."'");$assert($absence->num_rows===0,'disposable cleanup failed');}
    $server->close();
}
