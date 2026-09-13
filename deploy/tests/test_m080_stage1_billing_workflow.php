<?php
/** Source-contract tests for M080 C01. No database writes. */

$root=dirname(__DIR__,2);
$files=array(
    'service'=>$root.'/custom/chinaplanetoperations/class/OperationBillingWorkflowService.class.php',
    'controller'=>$root.'/custom/chinaplanetoperations/operations/billing_workflow.inc.php',
    'view'=>$root.'/custom/chinaplanetoperations/operations/billing_workflow.view.php',
    'schema'=>$root.'/custom/chinaplanetoperations/sql/llx_cpo_billing_action.sql',
    'migration'=>$root.'/deploy/migrations/m080_billing_action_schema.php',
    'mission'=>$root.'/docs/MISSIONS/M080_STAFF_FIRST_FULL_ORDER_CYCLE.md',
);
foreach($files as $name=>$file)if(!is_file($file)){fwrite(STDERR,"M080_STAGE1=FAIL missing_{$name}\n");exit(1);}
$service=file_get_contents($files['service']);$controller=file_get_contents($files['controller']);$view=file_get_contents($files['view']);$schema=file_get_contents($files['schema']);$mission=file_get_contents($files['mission']);
$must=array(
    'native invoice creation'=>'createFromOrder($order, $user)',
    'separate validation'=>'$invoice->validate($user)',
    'native payment creation'=>'$payment->create($user, 1, $thirdparty)',
    'native bank entry'=>'addPaymentToBank(',
    'same currency bank guard'=>'Bank account currency must exactly match the invoice currency.',
    'overpayment guard'=>'Overpayment is blocked',
    'staff ownership guard'=>'!empty($user->socid)',
    'operation permission'=>"hasRight('chinaplanetoperations','operation_write')",
    'financial permission'=>"hasRight('chinaplanetcustomercenter','financial_write')",
    'payment permission'=>"hasRight('facture','paiement')",
    'bank permission'=>"hasRight('banque','lire')",
    'request lock'=>'GET_LOCK(',
    'recovery path'=>'resumeReceipt(',
    'fail-closed recovery'=>'correction_required',
    'original currency'=>'multicurrency_amounts',
    'VAT comparison'=>'tva_tx',
);
foreach($must as $label=>$needle)if(strpos($service,$needle)===false){fwrite(STDERR,"M080_STAGE1=FAIL missing_".str_replace(' ','_',$label)."\n");exit(1);}
foreach(array('request_token varchar(64)','UNIQUE KEY uk_cpo_billing_token','UNIQUE KEY uk_cpo_billing_invoice_key','UNIQUE KEY uk_cpo_billing_payment','payload_hash char(64)','status varchar(24)','currency_code varchar(3)') as $needle)if(strpos($schema,$needle)===false){fwrite(STDERR,"M080_STAGE1=FAIL schema_contract\n");exit(1);}
foreach(array('billing_create_invoice','billing_validate_invoice','billing_post_receipt','REQUEST_METHOD') as $needle)if(strpos($controller,$needle)===false){fwrite(STDERR,"M080_STAGE1=FAIL controller_contract\n");exit(1);}
foreach(array('$billingCanCreate','$billingCanValidate','$billingCanPay','state($id, $billingCanPay)') as $needle)if(strpos($controller,$needle)===false){fwrite(STDERR,"M080_STAGE1=FAIL permission_projection\n");exit(1);}
foreach(array('Remaining / المتبقي','Same-currency bank','Record native receipt','No email','Verify and resume','correction_required') as $needle)if(strpos($view,$needle)===false){fwrite(STDERR,"M080_STAGE1=FAIL view_contract\n");exit(1);}
if(strpos($service,'setPaid(')!==false||strpos($service,'mail(')!==false||strpos($service,'curl_')!==false){fwrite(STDERR,"M080_STAGE1=FAIL forbidden_writer\n");exit(1);}
foreach(array('C02 — Supplier purchasing and payment','C03 — Shipment and document follow-ups','C04 — Full local UAT','R01 — Release preparation') as $stage)if(strpos($mission,$stage)===false){fwrite(STDERR,"M080_STAGE1=FAIL delivery_plan_missing\n");exit(1);}
echo "M080_STAGE1=PASS NATIVE_INVOICE=YES PARTIAL_PAYMENT=YES BANK_EVIDENCE=YES DUPLICATE_GUARD=YES EXTERNAL_SEND=NO\n";
