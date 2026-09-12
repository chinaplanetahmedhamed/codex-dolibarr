<?php

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "M077_CONNECTED_FOUNDATION=FAIL REASON=CLI_ONLY\n");
    exit(1);
}

define('MAIN_DB_PREFIX', 'llx_');
$conf = (object) array('entity'=>1);
require_once dirname(__DIR__, 2).'/custom/chinaplanetcustomercenter/class/CustomerFundsService.class.php';
require_once dirname(__DIR__, 2).'/custom/chinaplanetoperations/class/OperationShipmentService.class.php';

function m077Assert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "M077_CONNECTED_FOUNDATION=FAIL REASON=".$message."\n");
        exit(1);
    }
}

function m077Source($relative)
{
    $source = file_get_contents(dirname(__DIR__, 2).'/'.$relative);
    m077Assert($source !== false, 'missing source '.$relative);
    return $source;
}

// Synthetic local reconciliation: one receipt supports two shipments and one
// standalone service in one currency. Reservation is not spending.
$summary = CustomerFundsService::summarize(10000.00, array(
    array('status'=>'settled', 'amount'=>3000.00), // synthetic shipment A
    array('status'=>'reserved', 'amount'=>2500.00), // synthetic shipment B
    array('status'=>'settled', 'amount'=>1000.00), // synthetic standalone service
    array('status'=>'reversed', 'amount'=>500.00),
));
m077Assert(abs($summary['confirmed'] - 10000.00) < 0.00001, 'confirmed receipt changed');
m077Assert(abs($summary['reserved'] - 2500.00) < 0.00001, 'reservation classified as spending');
m077Assert(abs($summary['settled'] - 4000.00) < 0.00001, 'settled total incorrect');
m077Assert(abs($summary['unallocated'] - 3500.00) < 0.00001, 'unallocated total incorrect');
m077Assert(CustomerFundsService::receiptStatuses() === array('pending','confirmed','reversed'), 'receipt lifecycle changed');
m077Assert(CustomerFundsService::allocationStatuses() === array('reserved','settled','reversed'), 'allocation lifecycle changed');
m077Assert(OperationShipmentService::eligibleServiceTypes() === array('shipping_inquiry','shipping_booking'), 'shipment eligibility broadened');

$funds = m077Source('custom/chinaplanetcustomercenter/class/CustomerFundsService.class.php');
foreach (array(
    "native_source_type='customer_payment'",
    "cpcc_financial_payment_status_sql('p')",
    "Native payment amount does not match the receipt.",
    "Allocation exceeds funds remaining after native Dolibarr credit use.",
    "f.type=3",
    "nativeConsumedAmount",
    "reconcileReceipt",
    "lockNativeEvidence",
    "Customer invoice currency does not match",
    "Settlement requires a posted customer debit.",
    "Settlement currency does not match the allocation.",
    "Posted debit is not bound to this exact allocation target.",
    "Only an unsettled reservation can be reversed.",
    "status IN ('reserved','settled') FOR UPDATE",
    'cpcc_fund_event',
    'GET_LOCK(',
) as $needle) m077Assert(strpos($funds, $needle) !== false, 'missing finance invariant '.$needle);
m077Assert(strpos($funds, 'INSERT INTO '.MAIN_DB_PREFIX.'paiement') === false, 'service creates native customer payment');
m077Assert(strpos($funds, 'INSERT INTO '.MAIN_DB_PREFIX.'paiementfourn') === false, 'service creates native supplier payment');
m077Assert(strpos($funds, 'exchange_rate') === false, 'service invents FX');

$shipment = m077Source('custom/chinaplanetoperations/class/OperationShipmentService.class.php');
foreach (array(
    "fk_statut,notes,customer_note,customer_visible,date_creation",
    ",'baseline','inquiry',0,",
    "'operation_draft'",
    "customer_visible,",
    "COALESCE(fk_shipment,0)=0",
    "Origin and destination are required",
    "Operation project does not belong to the customer.",
    "Operation order does not belong to the customer.",
    "no booking or movement confirmed",
) as $needle) m077Assert(stripos($shipment, $needle) !== false, 'missing shipment invariant '.$needle);
foreach (array('mailfile','sendmail','curl_exec','curl_init','file_get_contents("http','->send(') as $forbidden) {
    m077Assert(stripos($shipment, $forbidden) === false, 'shipment service has external side effect '.$forbidden);
}

$view = m077Source('custom/chinaplanetoperations/operations/view.php');
m077Assert(strpos($view, "cpo_require(\$user,'operation_write')") !== false, 'draft action lacks operation-write gate');
m077Assert(strpos($view, "\$_SERVER['REQUEST_METHOD']!=='POST'") !== false, 'draft action lacks POST gate');
m077Assert(strpos($view, 'newToken()') !== false, 'draft action lacks CSRF token');
m077Assert(strpos($view, 'لا يؤكد Booking أو Loading أو Sailing') !== false, 'draft UI overclaims shipment state');

$fundsPage = m077Source('custom/chinaplanetcustomercenter/admin/managed_funds.php');
foreach (array(
    "hasRight('chinaplanetcustomercenter', 'financial_write')",
    "\$_SERVER['REQUEST_METHOD'] !== 'POST'",
    'newToken()',
    "'create_pending'",
    "'confirm_receipt'",
    "'reserve'",
    "'settle'",
    "'reverse'",
    'Pending or invalid evidence is never available',
    'Reserved and settled remain separate',
    'Due now',
    'Future due',
    'Currently allocatable',
    'Invalid evidence',
    '$settledByReceipt',
) as $needle) m077Assert(strpos($fundsPage, $needle) !== false, 'managed-funds UI missing '.$needle);
m077Assert(strpos($fundsPage, "preg_match('/^(operation|shipment|project|customer_invoice):[1-9][0-9]*$/") !== false, 'allocation target is not allowlisted');
$nativeAdvance=m077Source('custom/chinaplanetcustomercenter/class/NativeAdvanceWorkflowService.class.php');
foreach(array('createDepositFromOrigin','Facture::createDepositFromOrigin','createDepositFromOrigin($origin','postBankReceipt','addPaymentToBank',"code<>'INTR'",'Bank account currency must exactly match','fk_bank','createPendingReceipt','confirmReceipt','GET_LOCK') as $needle)m077Assert(strpos($nativeAdvance,$needle)!==false,'guided native receipt invariant missing '.$needle);
m077Assert(strpos($fundsPage,"'create_native_deposit'")!==false&&strpos($fundsPage,"'post_native_receipt'")!==false&&strpos($fundsPage,'Same-currency bank account')!==false,'guided native receipt UI missing');

$descriptor = m077Source('custom/chinaplanetcustomercenter/core/modules/modChinaPlanetCustomerCenter.class.php');
m077Assert(strpos($descriptor, "'financial_read'") !== false && strpos($descriptor, "'financial_write'") !== false, 'dedicated disabled-by-default finance rights missing');
m077Assert(strpos($descriptor, "'2.5.0'") !== false, 'module version not advanced for guarded schema');
$logisticsDescriptor = m077Source('custom/chinaplanetlogistics/core/modules/modChinaPlanetLogistics.class.php');
m077Assert(strpos($logisticsDescriptor, "'1.4.0'") !== false, 'logistics module version not advanced for visibility schema');
$financeAdmin = m077Source('custom/chinaplanetcustomercenter/admin/financial.php');
m077Assert(strpos($financeAdmin, "name=\"finance_target\"") !== false || strpos($financeAdmin, "'finance_target'") !== false, 'posted debit cannot be bound to an exact target');
$nativeGuard = m077Source('custom/chinaplanetcustomercenter/class/ManagedFundsNativeGuard.class.php');
foreach (array('BEFORE INSERT','BEFORE UPDATE',"SIGNAL SQLSTATE '45000'","a.status IN ('reserved','settled')",'DROP TRIGGER IF EXISTS','INFORMATION_SCHEMA.TRIGGERS','ACTION_STATEMENT','LIFECYCLE_LOCK','multicurrency_amount_ttc','function repair') as $needle) m077Assert(strpos($nativeGuard,$needle)!==false,'native-credit concurrency guard missing '.$needle);
$descriptor = m077Source('custom/chinaplanetcustomercenter/core/modules/modChinaPlanetCustomerCenter.class.php');
m077Assert(strpos($descriptor,'Reverse or formally close active managed-funds allocations')!==false,'module removal does not preserve active native-credit guard');
m077Assert(strpos($descriptor,'ManagedFundsNativeGuard::acquireLifecycle')<strpos($descriptor,"_load_tables('/chinaplanetcustomercenter/sql/')")&&strpos($descriptor,'ManagedFundsNativeGuard::install($this->db, true)')<strpos($descriptor,'return $this->_init'), 'module init is not lifecycle-locked through exact guard installation');
$exportLib = m077Source('custom/chinaplanetcustomercenter/lib/export.lib.php');
m077Assert(substr_count($exportLib,'d.fk_shipment IS NULL OR l.customer_visible=1')>=2,'private shipment export list/detail gate missing');

$localSchema = m077Source('deploy/tools/m077_local_schema.php');
foreach (array('REMOTE_DATABASE_FORBIDDEN','--single-transaction','--routines','--triggers','--events','M077_TABLES_ALREADY_PRESENT','NEW_TABLE_NOT_EMPTY','GET_LOCK(','MIGRATION_FAILED_TABLES_PRESERVED') as $needle) {
    m077Assert(strpos($localSchema, $needle) !== false, 'local schema safety contract missing '.$needle);
}
m077Assert(strpos($localSchema, 'DROP TABLE') === false, 'local migration can drop tables after partial DDL');
m077Assert(strpos($localSchema, "'rows'=>array_fill_keys(\$tables,0)") !== false, 'local migration does not attest empty new tables');
m077Assert(is_file(dirname(__DIR__).'/tests/test_m077_real_local_persistence.php'), 'real local persistence test missing');

foreach (array(
    'custom/chinaplanetlogistics/list.php',
    'custom/chinaplanetlogistics/card.php',
    'custom/chinaplanetlogistics/index.php',
    'custom/chinaplanetcustomercenter/project.php',
    'custom/chinaplanetcustomercenter/documents.php',
    'custom/chinaplanetcustomercenter/lib/portal.lib.php',
    'custom/chinaplanetcustomercenter/lib/mobile_api_data.lib.php',
) as $file) m077Assert(strpos(m077Source($file), 'customer_visible') !== false, 'customer draft publication gate missing '.$file);

foreach (array(
    'custom/chinaplanetcustomercenter/sql/llx_cpcc_customer_receipt.sql'=>array('uk_cpcc_receipt_request','uk_cpcc_receipt_native','payload_hash'),
    'custom/chinaplanetcustomercenter/sql/llx_cpcc_fund_allocation.sql'=>array('uk_cpcc_allocation_request','disbursement_type','fk_shipment'),
    'custom/chinaplanetcustomercenter/sql/llx_cpcc_fund_event.sql'=>array('action_code','status_from','status_to'),
) as $file=>$needles) {
    $sql = m077Source($file);
    m077Assert(stripos($sql, 'CREATE TABLE IF NOT EXISTS') !== false, 'schema is not additive '.$file);
    foreach ($needles as $needle) m077Assert(strpos($sql, $needle) !== false, 'schema missing '.$needle);
}

echo "M077_CONNECTED_FOUNDATION=PASS CONFIRMED=10000 RESERVED=2500 SETTLED=4000 UNALLOCATED=3500 SHIPMENT_DRAFT=STATUS_0\n";
