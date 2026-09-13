<?php
require_once __DIR__.'/../class/OperationSupplierWorkflowService.class.php';
$supplierEligible=!empty($operation['fk_commande'])&&!empty($operation['fk_project']);
$supplierCanRead=$supplierEligible&&empty($user->socid)&&(!empty($user->admin)||($user->hasRight('fournisseur','commande','lire')&&$user->hasRight('fournisseur','facture','lire')));
$supplierCanOperate=$supplierCanRead&&cpo_can($user,'operation_write');
$supplierCanOrder=$supplierCanOperate&&(!empty($user->admin)||$user->hasRight('fournisseur','commande','creer'));
$supplierCanInvoice=$supplierCanOperate&&(!empty($user->admin)||$user->hasRight('fournisseur','facture','creer'));
$supplierCanPay=$supplierCanOperate&&(!empty($user->admin)||($user->hasRight('fournisseur','facture','payer')&&$user->hasRight('banque','lire')));
$supplierService=$supplierCanRead?new OperationSupplierWorkflowService($db):null;
$supplierActions=array('supplier_create_order','supplier_validate_order','supplier_create_invoice','supplier_validate_invoice','supplier_post_payment','supplier_resume_payment');
if(in_array($action,$supplierActions,true)){
    if($_SERVER['REQUEST_METHOD']!=='POST'||!$supplierCanRead)accessforbidden();
    try{
        if($action==='supplier_create_order'){$objectId=$supplierService->createOrder($id,array('supplier_id'=>GETPOSTINT('supplier_id'),'supplier_reference'=>GETPOST('supplier_reference','alphanohtml'),'delivery_date'=>GETPOST('supplier_delivery_date','alphanohtml'),'currency'=>GETPOST('supplier_currency','alpha'),'exchange_rate'=>GETPOST('supplier_exchange_rate','alphanohtml'),'lines'=>GETPOST('supplier_lines','array')),$user,GETPOST('supplier_request_key','alphanohtml'));setEventMessages('Native supplier order draft #'.$objectId.' created. Nothing was sent.',null,'mesgs');}
        elseif($action==='supplier_validate_order'){$objectId=$supplierService->validateOrder($id,$user);setEventMessages('Native supplier order #'.$objectId.' validated. Nothing was sent.',null,'mesgs');}
        elseif($action==='supplier_create_invoice'){$objectId=$supplierService->createInvoice($id,GETPOST('supplier_invoice_reference','alphanohtml'),GETPOST('supplier_invoice_date','alphanohtml'),$user,GETPOST('supplier_request_key','alphanohtml'));setEventMessages('Native supplier invoice draft #'.$objectId.' created and linked.',null,'mesgs');}
        elseif($action==='supplier_validate_invoice'){$objectId=$supplierService->validateInvoice($id,$user);setEventMessages('Native supplier invoice #'.$objectId.' validated. Nothing was sent.',null,'mesgs');}
        elseif($action==='supplier_post_payment'){$result=$supplierService->postPayment($id,GETPOST('supplier_payment_amount','alphanohtml'),GETPOSTINT('supplier_bank_account_id'),GETPOST('supplier_payment_code','alpha'),GETPOST('supplier_payment_date','alphanohtml'),GETPOST('supplier_payment_reference','alphanohtml'),GETPOSTINT('supplier_allocation_id'),$user,GETPOST('supplier_request_key','alphanohtml'));setEventMessages('Native supplier payment #'.$result['payment_id'].' posted with bank evidence #'.$result['bank_id'].'.',null,'mesgs');}
        elseif($action==='supplier_resume_payment'){$result=$supplierService->resumePayment($id,GETPOSTINT('supplier_marker_id'),$user);setEventMessages('Supplier payment #'.$result['payment_id'].' safely verified and recovered.',null,'mesgs');}
    }catch(Throwable$e){setEventMessages($e->getMessage(),null,'errors');}
}
$supplierState=null;$supplierCurrencies=array(strtoupper((string)getDolCurrency()));
if($supplierCanRead){try{$supplierState=$supplierService->state($id,$supplierCanPay);}catch(Throwable$e){setEventMessages($e->getMessage(),null,'errors');}$res=$db->query("SELECT DISTINCT code FROM ".MAIN_DB_PREFIX."multicurrency WHERE entity IN (".getEntity('multicurrency').") AND code IS NOT NULL AND code<>'' ORDER BY code");while($res&&($row=$db->fetch_object($res)))if(preg_match('/^[A-Z]{3}$/',strtoupper((string)$row->code)))$supplierCurrencies[]=strtoupper((string)$row->code);$supplierCurrencies=array_values(array_unique($supplierCurrencies));}
$supplierRequestKey=bin2hex(random_bytes(20));
