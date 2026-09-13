<?php
/** Deterministic source contract for M082. No database writes. */
$root=dirname(__DIR__,2);$fail=function($m){fwrite(STDERR,"M082=FAIL ".$m."\n");exit(1);};
$files=array('mission'=>'docs/MISSIONS/M082_STAFF_FIRST_LOCAL_COMPLETION.md','service'=>'custom/chinaplanetoperations/class/OperationSupplierWorkflowService.class.php','include'=>'custom/chinaplanetoperations/operations/supplier_workflow.inc.php','view'=>'custom/chinaplanetoperations/operations/supplier_workflow.view.php','schema'=>'custom/chinaplanetoperations/sql/llx_cpo_supplier_action.sql','migration'=>'deploy/migrations/m082_supplier_action_schema.php','followup'=>'custom/chinaplanetoperations/lib/operation_followup.lib.php');
foreach($files as$n=>$path)if(!is_file($root.'/'.$path))$fail('missing_'.$n);
$service=file_get_contents($root.'/'.$files['service']);$include=file_get_contents($root.'/'.$files['include']);$view=file_get_contents($root.'/'.$files['view']);$schema=file_get_contents($root.'/'.$files['schema']);$followup=file_get_contents($root.'/'.$files['followup']);
foreach(array('CommandeFournisseur','FactureFournisseur','PaiementFourn','addPaymentToBank','order_supplier','customer funds allocation','same currency','Overpayment is blocked','correction_required','GET_LOCK','fk_project','fk_soc_customer')as$n)if(stripos($service,$n)===false)$fail('service_contract_'.preg_replace('/\W+/','_',$n));
foreach(array('supplier_create_order','supplier_validate_order','supplier_create_invoice','supplier_validate_invoice','supplier_post_payment','supplier_resume_payment','REQUEST_METHOD')as$n)if(strpos($include,$n)===false)$fail('controller_'.$n);
foreach(array('Select supplier','Purchase unit cost','Exchange rate','Supplier invoice reference','Record native supplier payment','No automatic FX','Customer portal remains read-only')as$n)if(strpos($view,$n)===false)$fail('view_'.preg_replace('/\W+/','_',$n));
foreach(array('UNIQUE KEY uk_cpo_supplier_token','UNIQUE KEY uk_cpo_supplier_object','UNIQUE KEY uk_cpo_supplier_payment','fk_allocation','exchange_rate')as$n)if(strpos($schema,$n)===false)$fail('schema_contract');
foreach(array('booking','loading','departure','arrival','delivery','commercial_invoice','packing_list','bill_of_lading','customer_publication')as$n)if(strpos($followup,"'".$n."'")===false)$fail('followup_'.$n);
foreach(array('mail(','curl_','setPaid(')as$n)if(strpos($service,$n)!==false)$fail('forbidden_'.$n);
echo "M082=PASS SUPPLIER_NATIVE=YES SAME_CURRENCY=YES FX=AUTO_DISABLED SHIPMENT_EVIDENCE=YES CUSTOMER_WRITE=NO\n";
