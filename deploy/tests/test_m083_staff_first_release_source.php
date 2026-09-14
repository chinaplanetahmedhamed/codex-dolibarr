<?php
/** Self-contained artifact source contract for the M083 staff-first release. */
$root=dirname(__DIR__,2);
$required=array(
 'custom/chinaplanetoperations/class/OperationBillingWorkflowService.class.php'=>array('cpo_billing_action','createInvoice','postReceipt'),
 'custom/chinaplanetoperations/class/OperationSupplierWorkflowService.class.php'=>array('cpo_supplier_action','createOrder','postPayment'),
 'custom/chinaplanetcustomercenter/class/ManagedFundsNativeGuard.class.php'=>array('cpcc_m077_guided_payment_insert','acquireLifecycle'),
 'custom/chinaplanetoperations/operations/billing_workflow.inc.php'=>array('OperationBillingWorkflowService'),
 'custom/chinaplanetoperations/operations/supplier_workflow.inc.php'=>array('OperationSupplierWorkflowService'),
 'custom/chinaplanetcustomercenter/board.php'=>array('customer_visible'),
 'custom/chinaplanetlogistics/list.php'=>array('customer_visible'),
 'deploy/migrations/m083_staff_first_production_schema.php'=>array("'empty-check'",'INFORMATION_SCHEMA.TABLES','SHOW INDEX'),
 'deploy/tools/m083_production_preflight.php'=>array('DATABASE_MUTATED=NO','ACTIVE_INTERNAL_STAFF_REQUIRED'),
 'docs/MISSIONS/M083_STAFF_FIRST_PRODUCTION_RELEASE.md'=>array('M083','Production'),
);
foreach($required as$relative=>$needles){$path=$root.'/'.$relative;if(!is_file($path)){fwrite(STDERR,"M083_SOURCE_TEST=FAIL FILE=$relative REASON=MISSING\n");exit(1);}$body=file_get_contents($path);foreach($needles as$needle)if(strpos($body,$needle)===false){fwrite(STDERR,"M083_SOURCE_TEST=FAIL FILE=$relative REASON=CONTRACT_MISSING\n");exit(1);}}
$runtime=file($root.'/deploy/releases/m083-runtime-files.txt',FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES);if(count($runtime)!==58||count($runtime)!==count(array_unique($runtime))){fwrite(STDERR,"M083_SOURCE_TEST=FAIL REASON=RUNTIME_MANIFEST\n");exit(1);}foreach($runtime as$relative)if(!is_file($root.'/'.$relative)){fwrite(STDERR,"M083_SOURCE_TEST=FAIL FILE=$relative REASON=RUNTIME_MISSING\n");exit(1);}echo"M083_SOURCE_TEST=PASS FILES=58 CUSTOMER_VISIBILITY=ENFORCED STAFF_CYCLES=READY\n";
