<?php

/** M082 native supplier UAT in a schema-only, disposable local database. */
if (getenv('M082_FULL_LOCAL_UAT') !== '1') {
    fwrite(STDERR, "M082_FULL_UAT=FAIL REASON=EXPLICIT_LOCAL_GATE_REQUIRED\n");
    exit(1);
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$dolRoot = '/Users/mac/Documents/dev/dolibarr/dolibarr-Dev/dolibarr-23.0.3/htdocs';
$confFile = $dolRoot.'/conf/conf.php';

function m082Assert($condition, $message) { if (!$condition) throw new RuntimeException($message); }
function m082Config($path) {
    return (static function ($path) {
        include $path;
        return array('host'=>$dolibarr_main_db_host, 'port'=>(int) $dolibarr_main_db_port, 'name'=>$dolibarr_main_db_name, 'user'=>$dolibarr_main_db_user, 'pass'=>$dolibarr_main_db_pass);
    })($path);
}
function m082LocalHost($host) {
    return in_array(strtolower(trim((string) $host, " \t\n\r\0\x0B[]")), array('localhost', '127.0.0.1', '::1'), true);
}

m082Assert(is_file($confFile), 'local Dolibarr config missing');
$cfg = m082Config($confFile);
m082Assert(m082LocalHost($cfg['host']), 'remote database forbidden');
$source = new mysqli($cfg['host'], $cfg['user'], $cfg['pass'], $cfg['name'], $cfg['port']);
$admin = null;
$adminCredentials = null;
foreach (array(array('root', 'root'), array('root', ''), array($cfg['user'], $cfg['pass'])) as $candidate) {
    try { $admin = new mysqli($cfg['host'], $candidate[0], $candidate[1], '', $cfg['port']); $adminCredentials=$candidate; break; } catch (Throwable $e) { $admin = null; }
}
m082Assert($admin instanceof mysqli, 'local disposable database admin unavailable');
$database = 'm082_uat_'.bin2hex(random_bytes(6));
m082Assert(preg_match('/^m082_uat_[a-f0-9]{12}$/', $database) === 1, 'unsafe disposable database name');
$created = false;

try {
    $admin->query("CREATE DATABASE `".$database."` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $created = true;
    $admin->query('SET FOREIGN_KEY_CHECKS=0');
    $tables = $source->query("SHOW FULL TABLES WHERE Table_type='BASE TABLE'");
    while ($row = $tables->fetch_row()) {
        $table = $row[0];
        m082Assert(preg_match('/^[a-zA-Z0-9_]+$/', $table) === 1, 'unsafe table name');
        $admin->query("CREATE TABLE `".$database."`.`".$table."` LIKE `".$cfg['name']."`.`".$table."`");
    }
    $admin->query('SET FOREIGN_KEY_CHECKS=1');
    $source->close();
    $source = null;
    $admin->close();
    $admin = null;

    define('NOLOGIN', 1);
    define('NOREQUIREMENU', 1);
    define('NOREQUIREHTML', 1);
    define('NOCSRFCHECK', 1);
    $_SERVER['DOCUMENT_ROOT'] = $dolRoot;
    $_SERVER['SERVER_NAME'] = '127.0.0.1';
    $_SERVER['SERVER_PORT'] = '8088';
    include $confFile;
    $dolibarr_main_db_name = $database;
    $dolibarr_main_db_user = $adminCredentials[0];
    $dolibarr_main_db_pass = $adminCredentials[1];
    chdir($dolRoot);
    require $dolRoot.'/master.inc.php';
    $conf->entity = 1;
    $conf->currency = 'USD';
    $conf->global->MAIN_DISABLE_PDF_AUTOUPDATE = 1;
    $conf->global->MAIN_MONNAIE = 'USD';
    $conf->global->COMMANDE_SUPPLIER_ADDON_NUMBER = 'mod_commande_fournisseur_muguet';
    $conf->global->INVOICE_SUPPLIER_ADDON_NUMBER = 'mod_facture_fournisseur_cactus';
    $conf->global->FACTURE_ADDON = 'mod_facture_terre';
    $conf->global->PROPALE_ADDON = 'mod_propale_marbre';
    $conf->global->COMMANDE_ADDON = 'mod_commande_marbre';
    foreach (array('fournisseur','banque','multicurrency','chinaplanetoperations','chinaplanetcustomercenter','product','facture','commande','propal') as $module) $conf->modules[$module] = 1;
    $conf->facture = (object) array('dir_output'=>sys_get_temp_dir().'/m082-uat-customer-invoices');
    $conf->commande = (object) array('dir_output'=>sys_get_temp_dir().'/m082-uat-customer-orders','multidir_output'=>array(1=>sys_get_temp_dir().'/m082-uat-customer-orders'));
    $conf->propal = (object) array('dir_output'=>sys_get_temp_dir().'/m082-uat-proposals','multidir_output'=>array(1=>sys_get_temp_dir().'/m082-uat-proposals'));
    $conf->fournisseur = (object) array('commande'=>(object) array('dir_output'=>sys_get_temp_dir().'/m082-uat-supplier-orders'), 'facture'=>(object) array('dir_output'=>sys_get_temp_dir().'/m082-uat-supplier-invoices'));

    require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
    require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
    require_once DOL_DOCUMENT_ROOT.'/custom/chinaplanetcustomercenter/class/ManagedFundsNativeGuard.class.php';
    require_once DOL_DOCUMENT_ROOT.'/custom/chinaplanetoperations/class/OperationCommercialWorkflowService.class.php';
    require_once DOL_DOCUMENT_ROOT.'/custom/chinaplanetoperations/class/OperationBillingWorkflowService.class.php';
    require_once DOL_DOCUMENT_ROOT.'/custom/chinaplanetoperations/class/OperationSupplierWorkflowService.class.php';
    require_once DOL_DOCUMENT_ROOT.'/custom/chinaplanetoperations/lib/operation_followup.lib.php';

    $user = new User($db);
    $user->id = 82001;
    $user->entity = 1;
    $user->login = 'm082-uat-staff';
    $user->lastname = 'M082 UAT';
    $user->admin = 0;
    $user->statut = 1;
    $user->socid = 0;
    $user->rights = new stdClass();
    $user->rights->chinaplanetoperations = (object) array('operation_write'=>1);
    $user->rights->fournisseur = new stdClass();
    $user->rights->fournisseur->lire = 1;
    $user->rights->fournisseur->commande = (object) array('lire'=>1, 'creer'=>1);
    $user->rights->fournisseur->facture = (object) array('lire'=>1, 'creer'=>1);
    $user->rights->facture = (object) array('creer'=>1, 'paiement'=>1);
    $user->rights->propal = (object) array('creer'=>1, 'lire'=>1);
    $user->rights->commande = (object) array('creer'=>1, 'lire'=>1);
    $user->rights->produit = (object) array('lire'=>1);
    $user->rights->chinaplanetcustomercenter = (object) array('financial_write'=>1);
    $user->rights->banque = (object) array('lire'=>1);
    $GLOBALS['user'] = $user;

    $staffActor = clone $user; $staffActor->socid=0;
    unset($conf->global->MAIN_USE_ADVANCED_PERMS);
    m082Assert(OperationSupplierWorkflowService::canCreateSupplierOrder($staffActor), 'non-admin supplier-order creator rejected');
    m082Assert(OperationSupplierWorkflowService::canValidateSupplierOrder($staffActor), 'non-admin standard supplier-order validator rejected');
    m082Assert(OperationSupplierWorkflowService::canCreateSupplierInvoice($staffActor), 'non-admin supplier-invoice creator rejected');
    m082Assert(OperationSupplierWorkflowService::canValidateSupplierInvoice($staffActor), 'non-admin standard supplier-invoice validator rejected');
    m082Assert(OperationSupplierWorkflowService::canPostSupplierPayment($staffActor), 'non-admin native supplier-payment actor rejected');
    $staffNoBank = clone $staffActor; $staffNoBank->rights = clone $staffActor->rights; $staffNoBank->rights->banque = (object) array('lire'=>0);
    m082Assert(!OperationSupplierWorkflowService::canPostSupplierPayment($staffNoBank), 'supplier payment accepted without bank read');
    $conf->global->MAIN_USE_ADVANCED_PERMS = 1;
    m082Assert(!OperationSupplierWorkflowService::canValidateSupplierOrder($staffActor), 'advanced supplier-order validation accepted without native right');
    m082Assert(!OperationSupplierWorkflowService::canValidateSupplierInvoice($staffActor), 'advanced supplier-invoice validation accepted without native right');
    $staffActor->rights->fournisseur->supplier_order_advance = (object) array('validate'=>1);
    $staffActor->rights->fournisseur->supplier_invoice_advance = (object) array('validate'=>1);
    m082Assert(OperationSupplierWorkflowService::canValidateSupplierOrder($staffActor), 'advanced supplier-order validator rejected');
    m082Assert(OperationSupplierWorkflowService::canValidateSupplierInvoice($staffActor), 'advanced supplier-invoice validator rejected');
    unset($conf->global->MAIN_USE_ADVANCED_PERMS);
    $externalActor = clone $staffActor; $externalActor->socid=82002;
    m082Assert(!OperationSupplierWorkflowService::canPostSupplierPayment($externalActor), 'external customer passed supplier-payment permission');

    $db->query("INSERT INTO llx_user(rowid,entity,login,lastname,admin,statut) VALUES(82001,1,'m082-uat-staff','M082 UAT',0,1)");
    $db->query("INSERT INTO llx_societe(rowid,nom,entity,statut,status,code_client,code_fournisseur,client,fournisseur,fk_pays,datec,fk_user_creat) VALUES(82002,'M082 UAT Synthetic Customer',1,1,1,'M082-CUST',NULL,1,0,117,NOW(),82001),(82003,'M082 UAT Synthetic Supplier',1,1,1,NULL,'M082-SUP',0,1,117,NOW(),82001),(82004,'M082 UAT Other Customer',1,1,1,'M082-OTHER',NULL,1,0,117,NOW(),82001)");
    $db->query("INSERT INTO llx_product(rowid,ref,entity,datec,label,tva_tx,fk_user_author,tosell,tobuy,fk_product_type) VALUES(82005,'M082-UAT-ITEM',1,NOW(),'M082 synthetic item',14,82001,1,1,0)");
    $db->query("INSERT INTO llx_c_paiement(id,entity,code,libelle,type,active,position) VALUES(82006,1,'VIR','M082 UAT transfer',2,1,1)");
    $db->query("INSERT INTO llx_multicurrency(rowid,date_create,code,name,entity,fk_user) VALUES(82041,NOW(),'USD','US Dollar',1,82001),(82042,NOW(),'CNY','Chinese Yuan',1,82001),(82043,NOW(),'EGP','Egyptian Pound',1,82001)");
    $db->query("INSERT INTO llx_multicurrency_rate(rowid,date_sync,rate,rate_indirect,fk_multicurrency,entity) VALUES(82051,NOW(),1,1,82041,1),(82052,NOW(),7.2,0.1388888889,82042,1),(82053,NOW(),48.5,0.0206185567,82043,1)");
    foreach (array('USD'=>82007, 'CNY'=>82008, 'EGP'=>82009) as $currency=>$accountId) {
        $db->query("INSERT INTO llx_bank_account(rowid,datec,ref,label,entity,fk_user_author,bank,fk_pays,courant,clos,rappro,currency_code) VALUES(".$accountId.",NOW(),'M82".$currency."','M082 UAT ".$currency."',1,82001,'M082 SYNTHETIC',117,1,0,1,'".$currency."')");
    }

    $fixtures = array(
        'USD'=>array('project'=>82011, 'operation'=>82014, 'tx'=>1.0, 'sale'=>600, 'cost'=>360),
        'CNY'=>array('project'=>82021, 'operation'=>82024, 'tx'=>7.2, 'sale'=>4320, 'cost'=>2592),
        'EGP'=>array('project'=>82031, 'operation'=>82034, 'tx'=>48.5, 'sale'=>29100, 'cost'=>17460),
    );
    foreach ($fixtures as $currency=>$f) {
        $db->query("INSERT INTO llx_projet(rowid,fk_soc,datec,dateo,ref,entity,title,fk_user_creat,public,fk_statut) VALUES(".$f['project'].",82002,NOW(),'2026-09-14','M082-".$currency."',1,'M082 UAT ".$currency." project',82001,0,1)");
        $db->query("INSERT INTO llx_cpo_operation(rowid,entity,ref,service_type,title,fk_soc,fk_project,fk_commande,fk_user_assign,stage,status,fk_user_creat,date_creation) VALUES(".$f['operation'].",1,'CP-OR-M082-".$currency."','order','M082 UAT ".$currency." full cycle',82002,".$f['project'].",NULL,82001,'new',1,82001,NOW())");
    }

    m082Assert(ManagedFundsNativeGuard::repair($db) && ManagedFundsNativeGuard::verify($db), 'managed-funds guards unavailable in disposable database');
    $commercial = new OperationCommercialWorkflowService($db, 1);
    $billing = new OperationBillingWorkflowService($db, 1);
    $service = new OperationSupplierWorkflowService($db, 1);
    $results = array();

    foreach ($fixtures as $currency=>$f) {
        $commercial->saveDraft($f['operation'],array('currency'=>$currency,'validity_days'=>15,'lines'=>array(array('fk_product'=>82005,'qty'=>1,'unit_price'=>$f['sale'],'description'=>'M082 '.$currency.' synthetic line'))),$user);
        $commercial->validateQuotation($f['operation'],$user);
        $commercial->acceptQuotation($f['operation'],'M082-'.$currency.'-SYNTHETIC-ACCEPTANCE',$user);
        $customerOrder=$commercial->createOrder($f['operation'],$user);
        $commercial->validateOrder($f['operation'],$user);
        m082Assert($customerOrder->fetch($customerOrder->id)>0&&!empty($customerOrder->lines),'native quotation/order cycle failed for '.$currency);
        $f['order']=(int)$customerOrder->id;
        $f['line']=(int)$customerOrder->lines[0]->id;
        $customerInvoiceId=$billing->createInvoice($f['operation'],$user,sha1($database.':'.$currency.':customer-invoice'));
        m082Assert($billing->createInvoice($f['operation'],$user,sha1($database.':'.$currency.':customer-invoice'))===$customerInvoiceId,'customer invoice retry failed for '.$currency);
        $billing->validateInvoice($f['operation'],$user);
        $customerState=$billing->state($f['operation'],true);
        m082Assert($customerState['summary']['currency']===$currency&&$customerState['summary']['total']>0,'customer invoice currency/total failed for '.$currency);
        $customerPartial=round((float)$customerState['summary']['total']*0.4,2);
        $customerFirst=$billing->postReceipt($f['operation'],$customerPartial,82007+array_search($currency,array('USD','CNY','EGP'),true),'VIR','2026-09-14','M082-'.$currency.'-CUSTOMER-PARTIAL',$user,sha1($database.':'.$currency.':customer-partial'));
        m082Assert($billing->postReceipt($f['operation'],$customerPartial,82007+array_search($currency,array('USD','CNY','EGP'),true),'VIR','2026-09-14','M082-'.$currency.'-CUSTOMER-PARTIAL',$user,sha1($database.':'.$currency.':customer-partial'))===$customerFirst,'customer receipt retry failed for '.$currency);
        $customerRemaining=(float)$billing->state($f['operation'],true)['summary']['remaining'];
        m082Assert($customerRemaining>0,'customer partial receipt did not persist for '.$currency);
        $billing->postReceipt($f['operation'],$customerRemaining,82007+array_search($currency,array('USD','CNY','EGP'),true),'VIR','2026-09-14','M082-'.$currency.'-CUSTOMER-FINAL',$user,sha1($database.':'.$currency.':customer-final'));
        m082Assert($billing->state($f['operation'],true)['summary']['remaining']<0.01,'customer full receipt did not persist for '.$currency);

        $orderInput = array(
            'supplier_id'=>82003,
            'supplier_reference'=>'M082-'.$currency.'-PO',
            'delivery_date'=>'2026-10-20',
            'currency'=>$currency,
            'exchange_rate'=>$f['tx'],
            'lines'=>array(array('customer_line_id'=>$f['line'], 'unit_cost'=>$f['cost'], 'vat'=>14, 'supplier_ref'=>'M082-'.$currency.'-ITEM')),
        );
        $orderKey = sha1($database.':'.$currency.':supplier-order');
        $supplierOrderId = $service->createOrder($f['operation'], $orderInput, $user, $orderKey);
        m082Assert($service->createOrder($f['operation'], $orderInput, $user, $orderKey) === $supplierOrderId, 'supplier-order retry failed for '.$currency);
        $changedRejected = false;
        try { $changed=$orderInput; $changed['supplier_reference'].='-CHANGED'; $service->createOrder($f['operation'], $changed, $user, $orderKey); } catch (RuntimeException $e) { $changedRejected = strpos($e->getMessage(), 'payload changed') !== false; }
        m082Assert($changedRejected, 'changed supplier-order retry accepted for '.$currency);
        $service->validateOrder($f['operation'], $user);
        $invoiceId = $service->createInvoice($f['operation'], 'M082-'.$currency.'-INV', '2026-09-14', $user, sha1($database.':'.$currency.':supplier-invoice'));
        $service->validateInvoice($f['operation'], $user);
        $state = $service->state($f['operation'], true);
        m082Assert($state['summary']['currency'] === $currency && $state['summary']['total'] > 0, 'supplier invoice currency/total failed for '.$currency);
        $total = (float) $state['summary']['total'];
        $partial = round($total * 0.4, 2);

        $wrongBankRejected = false;
        $wrongBank = $currency === 'USD' ? 82008 : 82007;
        try { $service->postPayment($f['operation'], $partial, $wrongBank, 'VIR', '2026-09-14', 'M082-'.$currency.'-WRONG', 0, $user, sha1($database.':'.$currency.':wrong')); } catch (RuntimeException $e) { $wrongBankRejected = strpos($e->getMessage(), 'same currency') !== false; }
        m082Assert($wrongBankRejected, 'wrong-currency bank accepted for '.$currency);

        $allocationId = 0;
        if ($currency === 'USD') {
            $depositInvoice = 82101; $customerPayment = 82102; $receipt = 82103; $allocationId = 82104;
            $db->query("INSERT INTO llx_facture(rowid,entity,ref,fk_soc,fk_projet,fk_statut,type,datef,total_ht,total_tva,total_ttc,multicurrency_code,multicurrency_tx,multicurrency_total_ht,multicurrency_total_tva,multicurrency_total_ttc) VALUES(".$depositInvoice.",1,'M082-UAT-DEPOSIT',82002,".$f['project'].",1,3,'2026-09-14',".$partial.",0,".$partial.",'USD',1,".$partial.",0,".$partial.")");
            $db->query("INSERT INTO llx_paiement(rowid,ref,entity,datec,datep,amount,multicurrency_amount,fk_paiement,num_paiement,note,fk_user_creat,statut) VALUES(".$customerPayment.",'M082-UAT-CPAY',1,NOW(),'2026-09-14',".$partial.",".$partial.",82006,'M082-UAT-CPAY','M082 synthetic deposit',82001,1)");
            $db->query("INSERT INTO llx_paiement_facture(fk_facture,fk_paiement,amount,multicurrency_amount) VALUES(".$depositInvoice.",".$customerPayment.",".$partial.",".$partial.")");
            $db->query("INSERT INTO llx_cpcc_customer_receipt(rowid,entity,fk_soc,receipt_date,amount,currency_code,reference,status,native_source_type,native_source_id,request_key,payload_hash,fk_user_creat,fk_user_confirm,date_confirmation,date_creation) VALUES(".$receipt.",1,82002,'2026-09-14',".$partial.",'USD','M082 synthetic receipt','confirmed','customer_payment',".$customerPayment.",'".hash('sha256','m082-receipt')."','".hash('sha256','m082-receipt-payload')."',82001,82001,NOW(),NOW())");
            $db->query("INSERT INTO llx_cpcc_fund_allocation(rowid,entity,fk_receipt,fk_soc,target_type,target_id,fk_project,fk_operation,amount,currency_code,status,request_key,payload_hash,fk_user_creat,date_creation) VALUES(".$allocationId.",1,".$receipt.",82002,'operation',".$f['operation'].",".$f['project'].",".$f['operation'].",".$partial.",'USD','reserved','".hash('sha256','m082-allocation')."','".hash('sha256','m082-allocation-payload')."',82001,NOW())");
            $allocationCheck=$db->fetch_object($db->query("SELECT rowid FROM llx_cpcc_fund_allocation WHERE entity=1 AND rowid=".$allocationId." AND status='reserved' AND target_type='operation' AND target_id=".$f['operation']." AND fk_operation=".$f['operation']." AND fk_soc=82002 AND fk_project=".$f['project']." AND currency_code='USD' AND amount<=".$partial));
            m082Assert($allocationCheck, 'synthetic customer-funds allocation fixture did not persist');
        }

        if ($currency === 'USD') {
            $concurrent = new mysqli($cfg['host'], $adminCredentials[0], $adminCredentials[1], $database, $cfg['port']);
            $lockName='CPO-SUP-PAY-1-'.$invoiceId;
            $lockRow=$concurrent->query("SELECT GET_LOCK('".$concurrent->real_escape_string($lockName)."',0) acquired")->fetch_assoc();
            m082Assert((int)$lockRow['acquired']===1,'concurrency fixture could not hold invoice payment lock');
            $concurrentRejected=false;$concurrentKey=sha1($database.':USD:concurrent-second-token');
            try{$service->postPayment($f['operation'],$partial,82007,'VIR','2026-09-14','M082-USD-CONCURRENT',0,$user,$concurrentKey);}catch(RuntimeException $e){$concurrentRejected=strpos($e->getMessage(),'busy')!==false;}
            $concurrent->query("SELECT RELEASE_LOCK('".$concurrent->real_escape_string($lockName)."')");$concurrent->close();
            m082Assert($concurrentRejected,'distinct-token concurrent supplier payment was not serialized');
            $concurrentToken='CPO-SPY-'.strtoupper($concurrentKey);
            m082Assert((int)$db->fetch_object($db->query("SELECT COUNT(*) n FROM llx_cpo_supplier_action WHERE request_token='".$db->escape($concurrentToken)."'"))->n===0,'blocked concurrent request created a marker');
        }

        $partialResult = $service->postPayment($f['operation'], $partial, 82007 + array_search($currency, array('USD','CNY','EGP'), true), 'VIR', '2026-09-14', 'M082-'.$currency.'-PARTIAL', $allocationId, $user, sha1($database.':'.$currency.':partial'));
        $partialRetry = $service->postPayment($f['operation'], $partial, 82007 + array_search($currency, array('USD','CNY','EGP'), true), 'VIR', '2026-09-14', 'M082-'.$currency.'-PARTIAL', $allocationId, $user, sha1($database.':'.$currency.':partial'));
        m082Assert($partialResult === $partialRetry, 'supplier-payment retry failed for '.$currency);
        if ($allocationId) {
            $allocation = $db->fetch_object($db->query("SELECT status,disbursement_type,disbursement_id FROM llx_cpcc_fund_allocation WHERE rowid=".$allocationId));
            m082Assert($allocation && $allocation->status === 'settled' && $allocation->disbursement_type === 'supplier_payment' && (int) $allocation->disbursement_id === (int) $partialResult['payment_id'], 'explicit customer-funds attribution failed');
            $cashBefore=$db->fetch_object($db->query("SELECT (SELECT COUNT(*) FROM llx_paiementfourn WHERE rowid=".(int)$partialResult['payment_id'].") payments,(SELECT COUNT(*) FROM llx_bank WHERE rowid=".(int)$partialResult['bank_id'].") banks"));
            $db->query("UPDATE llx_cpcc_fund_allocation SET status='reserved',disbursement_type=NULL,disbursement_id=NULL,fk_user_settle=NULL,date_settlement=NULL WHERE rowid=".$allocationId);
            $crashRetry=$service->postPayment($f['operation'],$partial,82007,'VIR','2026-09-14','M082-USD-PARTIAL',$allocationId,$user,sha1($database.':USD:partial'));
            $allocation=$db->fetch_object($db->query("SELECT status,disbursement_type,disbursement_id FROM llx_cpcc_fund_allocation WHERE rowid=".$allocationId));
            $cashAfter=$db->fetch_object($db->query("SELECT (SELECT COUNT(*) FROM llx_paiementfourn WHERE rowid=".(int)$partialResult['payment_id'].") payments,(SELECT COUNT(*) FROM llx_bank WHERE rowid=".(int)$partialResult['bank_id'].") banks"));
            m082Assert($crashRetry===$partialResult&&$allocation->status==='settled'&&(int)$allocation->disbursement_id===(int)$partialResult['payment_id']&&(int)$cashBefore->payments===(int)$cashAfter->payments&&(int)$cashBefore->banks===(int)$cashAfter->banks,'post-cash allocation recovery recreated cash or failed to settle');

            $db->query("UPDATE llx_cpcc_customer_receipt SET amount=amount-1 WHERE rowid=".$receipt);
            $receiptMutationRejected=false;try{(new CustomerFundsService($db,1))->settleWithSupplierPayment($allocationId,(int)$partialResult['payment_id'],82001);}catch(RuntimeException $e){$receiptMutationRejected=strpos($e->getMessage(),'evidence')!==false||strpos($e->getMessage(),'coverage')!==false;}
            m082Assert($receiptMutationRejected,'changed native receipt coverage was accepted');
            $db->query("UPDATE llx_cpcc_customer_receipt SET amount=amount+1 WHERE rowid=".$receipt);

            $db->query("UPDATE llx_cpcc_fund_allocation SET status='reversed',disbursement_type=NULL,disbursement_id=NULL WHERE rowid=".$allocationId);
            $db->query("INSERT INTO llx_societe_remise_except(entity,fk_soc,discount_type,datec,amount_ht,amount_tva,amount_ttc,tva_tx,fk_user,fk_facture,fk_facture_source,description,multicurrency_code,multicurrency_tx,multicurrency_amount_ht,multicurrency_amount_tva,multicurrency_amount_ttc) VALUES(1,82002,0,NOW(),1,0,1,0,82001,".$customerInvoiceId.",".$depositInvoice.",'M082 synthetic pre-existing native consumption','USD',1,1,0,1)");
            $creditId=(int)$db->last_insert_id('llx_societe_remise_except');$db->query("UPDATE llx_cpcc_fund_allocation SET status='reserved' WHERE rowid=".$allocationId);
            $consumptionRejected=false;try{(new CustomerFundsService($db,1))->settleWithSupplierPayment($allocationId,(int)$partialResult['payment_id'],82001);}catch(RuntimeException $e){$consumptionRejected=strpos($e->getMessage(),'coverage')!==false;}
            m082Assert($consumptionRejected,'native deposit-credit consumption did not reduce allocatable coverage');
            $db->query("UPDATE llx_cpcc_fund_allocation SET status='reversed' WHERE rowid=".$allocationId);$db->query("DELETE FROM llx_societe_remise_except WHERE rowid=".$creditId);$db->query("UPDATE llx_cpcc_fund_allocation SET status='reserved' WHERE rowid=".$allocationId);
            m082Assert((new CustomerFundsService($db,1))->settleWithSupplierPayment($allocationId,(int)$partialResult['payment_id'],82001),'allocation did not recover after valid coverage was restored');
        }
        if($currency==='CNY'){
            $bank=$db->fetch_object($db->query("SELECT amount,amount_main_currency FROM llx_bank WHERE rowid=".(int)$partialResult['bank_id']));
            $db->query("UPDATE llx_bank SET amount=amount+1 WHERE rowid=".(int)$partialResult['bank_id']);$bankMutationRejected=false;
            try{$service->postPayment($f['operation'],$partial,82008,'VIR','2026-09-14','M082-CNY-PARTIAL',0,$user,sha1($database.':CNY:partial'));}catch(OperationSupplierEvidenceException $e){$bankMutationRejected=true;}
            m082Assert($bankMutationRejected,'changed supplier bank amount was accepted');$db->query("UPDATE llx_bank SET amount=".(float)$bank->amount.",amount_main_currency=".($bank->amount_main_currency===null?'NULL':(float)$bank->amount_main_currency)." WHERE rowid=".(int)$partialResult['bank_id']);
            $link=$db->fetch_object($db->query("SELECT rowid,url FROM llx_bank_url WHERE fk_bank=".(int)$partialResult['bank_id']." AND url_id=".(int)$partialResult['payment_id']." AND type='payment_supplier'"));m082Assert($link,'supplier bank/payment link missing');
            $db->query("UPDATE llx_bank_url SET url='/broken' WHERE rowid=".(int)$link->rowid);$linkMutationRejected=false;
            try{$service->postPayment($f['operation'],$partial,82008,'VIR','2026-09-14','M082-CNY-PARTIAL',0,$user,sha1($database.':CNY:partial'));}catch(OperationSupplierEvidenceException $e){$linkMutationRejected=true;}
            m082Assert($linkMutationRejected,'changed supplier bank/payment link was accepted');$db->query("UPDATE llx_bank_url SET url='".$db->escape((string)$link->url)."' WHERE rowid=".(int)$link->rowid);
            m082Assert($service->postPayment($f['operation'],$partial,82008,'VIR','2026-09-14','M082-CNY-PARTIAL',0,$user,sha1($database.':CNY:partial'))===$partialResult,'supplier payment did not recover after bank evidence restoration');
        }
        $remaining = (float) $service->state($f['operation'], true)['summary']['remaining'];
        m082Assert($remaining > 0 && $remaining < $total, 'partial supplier payment failed for '.$currency);
        $finalResult = $service->postPayment($f['operation'], $remaining, 82007 + array_search($currency, array('USD','CNY','EGP'), true), 'VIR', '2026-09-14', 'M082-'.$currency.'-FINAL', 0, $user, sha1($database.':'.$currency.':final'));
        $finalRetry = $service->postPayment($f['operation'], $remaining, 82007 + array_search($currency, array('USD','CNY','EGP'), true), 'VIR', '2026-09-14', 'M082-'.$currency.'-FINAL', 0, $user, sha1($database.':'.$currency.':final'));
        m082Assert($finalResult === $finalRetry, 'fully paid supplier retry failed for '.$currency);
        m082Assert($service->state($f['operation'], true)['summary']['remaining'] < 0.01, 'full supplier payment failed for '.$currency);
        $overpayRejected = false;
        try { $service->postPayment($f['operation'], 1, 82007 + array_search($currency, array('USD','CNY','EGP'), true), 'VIR', '2026-09-14', 'M082-'.$currency.'-OVER', 0, $user, sha1($database.':'.$currency.':over')); } catch (RuntimeException $e) { $overpayRejected = strpos($e->getMessage(), 'Overpayment') !== false || strpos($e->getMessage(), 'validated') !== false; }
        m082Assert($overpayRejected, 'overpayment accepted for '.$currency);
        $results[$currency] = array($supplierOrderId, $invoiceId, $partialResult['payment_id'], $finalResult['payment_id']);
    }

    $counts = $db->fetch_object($db->query("SELECT COUNT(DISTINCT fk_supplier_order) orders,COUNT(DISTINCT fk_supplier_invoice) invoices,COUNT(DISTINCT fk_supplier_payment) payments,COUNT(DISTINCT fk_bank) banks,COUNT(DISTINCT currency_code) currencies FROM llx_cpo_supplier_action WHERE entity=1 AND fk_supplier=82003"));
    m082Assert((int) $counts->orders===3 && (int) $counts->invoices===3 && (int) $counts->payments===6 && (int) $counts->banks===6 && (int) $counts->currencies===3, 'native evidence counts failed');

    $customer = clone $user; $customer->admin=0; $customer->socid=82002;
    $customerRejected = false;
    try { $service->createOrder(82014, array(), $customer, sha1('m082-customer-write')); } catch (RuntimeException $e) { $customerRejected = strpos($e->getMessage(), 'Staff access required') !== false; }
    m082Assert($customerRejected, 'customer reached supplier writer');

    m082Assert($db->query("INSERT INTO llx_chinaplanetlogistics(rowid,entity,ref,fk_soc,fk_project,fk_statut,booking_no,container_no,customer_visible,date_creation,fk_user_creat) VALUES(82110,1,'M082-UAT-SHIP',82002,82011,8,'M082-UAT-BOOKING','M082-UAT-CONTAINER',1,NOW(),82001)"),'synthetic shipment insert failed: '.$db->lasterror());
    $db->query("INSERT INTO llx_cpo_operation(rowid,entity,ref,service_type,title,fk_soc,fk_project,fk_commande,fk_shipment,fk_user_assign,stage,status,fk_user_creat,date_creation) VALUES(82111,1,'CP-SB-M082-UAT','shipping_booking','M082 UAT shipment',82002,82011,NULL,82110,82001,'active',1,82001,NOW())");
    $db->query("INSERT INTO llx_cpo_document(rowid,entity,fk_operation,document_type,original_name,stored_name,mime_type,file_size,version_no,visibility,review_status,is_current,fk_user_creat,fk_user_review,date_review,date_creation) VALUES(82112,1,82111,'commercial_invoice','m082-ci.pdf','m082-ci.pdf','application/pdf',1,1,'customer_after_approval','approved',1,82001,82001,NOW(),NOW()),(82113,1,82111,'packing_list','m082-pl.pdf','m082-pl.pdf','application/pdf',1,1,'internal','final_received',1,82001,82001,NOW(),NOW()),(82114,1,82111,'bill_of_lading','m082-bl.pdf','m082-bl.pdf','application/pdf',1,1,'internal','approved',1,82001,82001,NOW(),NOW())");
    $shipmentContext=array('rowid'=>82111,'fk_soc'=>82002,'fk_project'=>82011,'fk_shipment'=>82110);
    $shipmentCheck=$db->fetch_object($db->query("SELECT rowid,booking_no,container_no,fk_statut FROM llx_chinaplanetlogistics WHERE entity=1 AND rowid=82110 AND fk_soc=82002 AND fk_project=82011"));
    m082Assert($shipmentCheck&&$shipmentCheck->booking_no==='M082-UAT-BOOKING'&&$shipmentCheck->container_no==='M082-UAT-CONTAINER'&&(int)$shipmentCheck->fk_statut===8,'synthetic shipment evidence fixture failed');
    $followup = cpo_operation_followup($db, $shipmentContext, 1);
    m082Assert(isset($followup['steps']) && count($followup['steps'])===9, 'shipment/document evidence checklist incomplete');
    foreach ($followup['steps'] as $complete) m082Assert(is_bool($complete), 'unsupported shipment checklist state');
    foreach (array('booking','loading','commercial_invoice','packing_list','bill_of_lading','customer_publication') as $step) m082Assert($followup['steps'][$step]===true,'verified shipment/document evidence not projected: '.$step);
    foreach (array('departure','arrival','delivery') as $step) m082Assert($followup['steps'][$step]===false,'missing shipment evidence was invented: '.$step);

    echo "M082_FULL_UAT=PASS ACTOR=NON_ADMIN_STAFF SOURCE_DB_MUTATED=NO SCHEMA_ONLY=YES SYNTHETIC_ONLY=YES CURRENCIES=USD,CNY,EGP QUOTATIONS=3 SALES_ORDERS=3 CUSTOMER_INVOICES=3 CUSTOMER_RECEIPTS=6 SUPPLIER_ORDERS=3 SUPPLIER_INVOICES=3 SUPPLIER_PAYMENTS=6 BANK_ENTRIES=12 FUNDS_ATTRIBUTION=PASS RETRY=IDEMPOTENT CONCURRENT_OVERPAYMENT=SERIALIZED POST_CASH_RECOVERY=PASS BANK_EVIDENCE_MUTATION=BLOCKED RECEIPT_MUTATION=BLOCKED NATIVE_CONSUMPTION=BLOCKED WRONG_CURRENCY=BLOCKED OVERPAYMENT=BLOCKED CUSTOMER_WRITE=BLOCKED SHIPMENT_CHECKLIST=EVIDENCE_ONLY\n";
} finally {
    if (isset($db) && is_object($db) && isset($db->database_name) && $db->database_name === $database) $db->close();
    if ($created) {
        m082Assert(preg_match('/^m082_uat_[a-f0-9]{12}$/', $database) === 1, 'unsafe cleanup name');
        $cleanup = new mysqli($cfg['host'], $adminCredentials[0], $adminCredentials[1], '', $cfg['port']);
        $cleanup->query("DROP DATABASE `".$database."`");
        $left = $cleanup->query("SELECT SCHEMA_NAME FROM information_schema.schemata WHERE schema_name='".$cleanup->real_escape_string($database)."'");
        m082Assert($left->num_rows === 0, 'disposable database cleanup failed');
        $cleanup->close();
    }
    if ($source instanceof mysqli) $source->close();
    if ($admin instanceof mysqli) $admin->close();
}
