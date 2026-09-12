<?php

if (!isset($_SERVER['QUERY_STRING'])) $_SERVER['QUERY_STRING'] = '';
define('CSRFCHECK_WITH_TOKEN', 1);
require_once __DIR__.'/../lib/main.lib.php';
require_once __DIR__.'/../lib/admin_shell.lib.php';
require_once __DIR__.'/../lib/financial.lib.php';
require_once __DIR__.'/../lib/portal.lib.php';
require_once __DIR__.'/../class/CustomerFundsService.class.php';

$canRead = !empty($user->admin) || $user->hasRight('chinaplanetcustomercenter', 'financial_read') || $user->hasRight('chinaplanetcustomercenter', 'financial_write');
$canWrite = !empty($user->admin) || $user->hasRight('chinaplanetcustomercenter', 'financial_write');
if (empty($user->id) || !empty($user->socid) || !$canRead) accessforbidden();
if (!cpcc_managed_funds_schema_ready($db)) accessforbidden('Managed customer-funds tables are not installed. Apply the reviewed M077 migration first.');

$service = new CustomerFundsService($db);
$socid = GETPOSTINT('socid');
$action = GETPOST('action', 'aZ09');
if ($action !== '' && $_SERVER['REQUEST_METHOD'] !== 'POST') accessforbidden();

try {
    if ($action !== '') {
        if (!$canWrite) accessforbidden();
        if ($action === 'create_pending') {
            $service->createPendingReceipt(array(
                'fk_soc'=>GETPOSTINT('socid'),
                'receipt_date'=>GETPOST('receipt_date', 'alphanohtml'),
                'amount'=>price2num(GETPOST('amount', 'alphanohtml')),
                'currency_code'=>GETPOST('currency_code', 'alpha'),
                'reference'=>GETPOST('reference', 'alphanohtml'),
                'note_private'=>GETPOST('note_private', 'restricthtml'),
                'request_key'=>GETPOST('request_key', 'alphanohtml'),
            ), (int) $user->id);
            setEventMessages('Pending receipt registered. It is not available for allocation until linked to a validated Dolibarr payment.', null, 'mesgs');
        } elseif ($action === 'confirm_receipt') {
            $service->confirmReceipt(GETPOSTINT('receipt_id'), GETPOSTINT('native_payment_id'), (int) $user->id);
            setEventMessages('Receipt confirmed against the selected native Dolibarr payment. No second cash entry was created.', null, 'mesgs');
        } elseif ($action === 'reserve') {
            $targetValue = GETPOST('target', 'alphanohtml');
            if (!preg_match('/^(operation|shipment|project|customer_invoice):[1-9][0-9]*$/', $targetValue)) throw new RuntimeException('Choose a valid allocation target.');
            $target = explode(':', $targetValue, 2);
            if (count($target) !== 2) throw new RuntimeException('Choose an allocation target.');
            $service->reserve(GETPOSTINT('receipt_id'), array(
                'target_type'=>$target[0],
                'target_id'=>(int) $target[1],
                'amount'=>price2num(GETPOST('amount', 'alphanohtml')),
                'request_key'=>GETPOST('request_key', 'alphanohtml'),
                'note_private'=>GETPOST('note_private', 'restricthtml'),
            ), (int) $user->id);
            setEventMessages('Funds reserved. Reservation is not reported as an actual payment.', null, 'mesgs');
        } elseif ($action === 'settle') {
            $service->settle(GETPOSTINT('allocation_id'), GETPOSTINT('financial_entry_id'), (int) $user->id);
            setEventMessages('Allocation linked to the posted debit and marked settled.', null, 'mesgs');
        } elseif ($action === 'reverse') {
            $service->reverseAllocation(GETPOSTINT('allocation_id'), GETPOST('reason', 'restricthtml'), (int) $user->id);
            setEventMessages('Allocation reversed. Audit history was retained.', null, 'mesgs');
        }
    }
} catch (Throwable $e) {
    setEventMessages($e->getMessage(), null, 'errors');
}

$customers = array();
$res = $db->query("SELECT rowid,nom FROM ".MAIN_DB_PREFIX."societe WHERE entity=".(int) $conf->entity." AND status=1 AND client IN (1,2,3) ORDER BY nom");
while ($res && ($row = $db->fetch_object($res))) $customers[(int) $row->rowid] = (string) $row->nom;
if ($socid > 0 && !isset($customers[$socid])) accessforbidden();

$receipts = array(); $allocations = array(); $paymentOptions = array(); $targetOptions = array(); $debitOptions = array(); $currencySummaries = array(); $receiptReconciliation = array(); $allocationCoverage = array();
if ($socid > 0) {
    $res = $db->query("SELECT * FROM ".MAIN_DB_PREFIX."cpcc_customer_receipt WHERE entity=".(int) $conf->entity." AND fk_soc=".$socid." ORDER BY receipt_date DESC,rowid DESC");
    while ($res && ($row = $db->fetch_object($res))) $receipts[] = get_object_vars($row);
    $res = $db->query("SELECT a.*,r.reference receipt_reference FROM ".MAIN_DB_PREFIX."cpcc_fund_allocation a INNER JOIN ".MAIN_DB_PREFIX."cpcc_customer_receipt r ON r.entity=a.entity AND r.rowid=a.fk_receipt AND r.fk_soc=a.fk_soc WHERE a.entity=".(int) $conf->entity." AND a.fk_soc=".$socid." ORDER BY a.rowid DESC");
    while ($res && ($row = $db->fetch_object($res))) $allocations[] = get_object_vars($row);
    foreach ($receipts as $receipt) {
        $currency = cpcc_financial_currency($receipt['currency_code'], '');
        if (!isset($currencySummaries[$currency])) $currencySummaries[$currency] = array('pending'=>0.0,'confirmed'=>0.0,'reserved'=>0.0,'settled'=>0.0,'unallocated'=>0.0,'native_consumed'=>0.0,'blocked'=>0.0,'invalid'=>0,'due_now'=>0.0,'future_due'=>0.0);
        if ($receipt['status'] === 'pending') $currencySummaries[$currency]['pending'] += (float) $receipt['amount'];
        if ($receipt['status'] === 'confirmed') {
            $reconciliation = $service->reconcileReceipt((int) $receipt['rowid']);
            $receiptReconciliation[(int) $receipt['rowid']] = $reconciliation;
            $currencySummaries[$currency]['confirmed'] += (float) $receipt['amount'];
            $currencySummaries[$currency]['native_consumed'] += (float) $reconciliation['native_consumed'];
            $currencySummaries[$currency]['unallocated'] += (float) $reconciliation['available'];
            $currencySummaries[$currency]['blocked'] += (float) $reconciliation['blocked'];
            if (empty($reconciliation['evidence_valid'])) $currencySummaries[$currency]['invalid']++;
        }
    }
    foreach ($allocations as $allocation) {
        $currency = cpcc_financial_currency($allocation['currency_code'], '');
        if (!isset($currencySummaries[$currency])) $currencySummaries[$currency] = array('pending'=>0.0,'confirmed'=>0.0,'reserved'=>0.0,'settled'=>0.0,'unallocated'=>0.0,'native_consumed'=>0.0,'blocked'=>0.0,'invalid'=>0,'due_now'=>0.0,'future_due'=>0.0);
        if ($allocation['status'] === 'reserved') $currencySummaries[$currency]['reserved'] += (float) $allocation['amount'];
        if ($allocation['status'] === 'settled') $currencySummaries[$currency]['settled'] += (float) $allocation['amount'];
    }
    $settledByReceipt=array();
    foreach ($allocations as $allocation) {
        if ($allocation['status']!=='settled') continue;
        $receiptId=(int)$allocation['fk_receipt'];
        if (!isset($settledByReceipt[$receiptId])) $settledByReceipt[$receiptId]=0.0;
        $settledByReceipt[$receiptId]+=(float)$allocation['amount'];
    }
    foreach ($allocations as $allocation) {
        if ($allocation['status']!=='reserved') continue;
        $receiptId=(int)$allocation['fk_receipt'];
        $reconciliation=$receiptReconciliation[$receiptId]??array('evidence_valid'=>false,'allocatable'=>0);
        $settled=(float)($settledByReceipt[$receiptId]??0.0);
        $allocationCoverage[(int)$allocation['rowid']]=!empty($reconciliation['evidence_valid'])&&$settled+(float)$allocation['amount']<=(float)$reconciliation['allocatable']+0.00000001;
    }

    $baseCurrency = cpcc_financial_currency(!empty($conf->currency) ? $conf->currency : 'EUR', 'EUR');
    foreach (cpcc_financial_unpaid_invoices($db, $socid, $baseCurrency) as $invoice) {
        if ((float) $invoice['remaining'] <= 0.005) continue;
        $currency = cpcc_financial_currency($invoice['currency'], $baseCurrency);
        if (!isset($currencySummaries[$currency])) $currencySummaries[$currency] = array('pending'=>0.0,'confirmed'=>0.0,'reserved'=>0.0,'settled'=>0.0,'unallocated'=>0.0,'native_consumed'=>0.0,'blocked'=>0.0,'invalid'=>0,'due_now'=>0.0,'future_due'=>0.0);
        $dueDate = trim((string) $invoice['due_date']);
        $currencySummaries[$currency][($dueDate !== '' && $dueDate <= date('Y-m-d')) ? 'due_now' : 'future_due'] += (float) $invoice['remaining'];
    }
    $currencyExpr = "CASE WHEN TRIM(COALESCE(f.multicurrency_code,''))<>'' THEN UPPER(f.multicurrency_code) ELSE '".$db->escape($baseCurrency)."' END";
    $amountExpr = "CASE WHEN TRIM(COALESCE(f.multicurrency_code,''))<>'' THEN pf.multicurrency_amount ELSE pf.amount END";
    $sql = "SELECT p.rowid,p.ref,p.datep,MIN(".$currencyExpr.") currency,COUNT(DISTINCT ".$currencyExpr.") currency_count,SUM(".$amountExpr.") amount,GROUP_CONCAT(DISTINCT f.ref ORDER BY f.ref) invoice_refs FROM ".MAIN_DB_PREFIX."paiement p INNER JOIN ".MAIN_DB_PREFIX."paiement_facture pf ON pf.fk_paiement=p.rowid INNER JOIN ".MAIN_DB_PREFIX."facture f ON f.rowid=pf.fk_facture AND f.entity=p.entity WHERE p.entity=".(int) $conf->entity." AND f.fk_soc=".$socid." AND f.type=3 AND ".cpcc_financial_payment_status_sql('p')." GROUP BY p.rowid,p.ref,p.datep ORDER BY p.datep DESC,p.rowid DESC";
    $res = $db->query($sql);
    while ($res && ($row = $db->fetch_object($res))) if ((int) $row->currency_count === 1) $paymentOptions[(int) $row->rowid] = ($row->ref ?: '#'.$row->rowid).' · '.$row->datep.' · '.cpcc_portal_money($row->amount).' '.$row->currency.' · '.$row->invoice_refs;

    $queries = array(
        'operation'=>"SELECT rowid,ref label FROM ".MAIN_DB_PREFIX."cpo_operation WHERE entity=".(int) $conf->entity." AND fk_soc=".$socid." AND status=1 ORDER BY rowid DESC",
        'shipment'=>"SELECT rowid,ref label FROM ".MAIN_DB_PREFIX."chinaplanetlogistics WHERE entity=".(int) $conf->entity." AND fk_soc=".$socid." ORDER BY rowid DESC",
        'project'=>"SELECT rowid,CONCAT(ref,' · ',title) label FROM ".MAIN_DB_PREFIX."projet WHERE entity=".(int) $conf->entity." AND fk_soc=".$socid." ORDER BY rowid DESC",
        'customer_invoice'=>"SELECT rowid,ref label FROM ".MAIN_DB_PREFIX."facture WHERE entity=".(int) $conf->entity." AND fk_soc=".$socid." AND fk_statut>0 ORDER BY rowid DESC",
    );
    foreach ($queries as $type=>$sql) {
        $res = $db->query($sql);
        while ($res && ($row = $db->fetch_object($res))) $targetOptions[$type.':'.(int) $row->rowid] = ucfirst(str_replace('_',' ',$type)).' · '.$row->label;
    }
    $res = $db->query("SELECT rowid,entry_date,entry_type,source_type,source_id,description,amount,currency_code,reference FROM ".MAIN_DB_PREFIX."cpcc_financial_entry WHERE entity=".(int) $conf->entity." AND fk_soc=".$socid." AND direction='D' AND status=1 AND source_type IN ('operation','shipment','project','customer_invoice') AND source_id>0 ORDER BY entry_date DESC,rowid DESC");
    while ($res && ($row = $db->fetch_object($res))) $debitOptions[(int) $row->rowid] = '#'.$row->rowid.' · '.$row->source_type.' #'.$row->source_id.' · '.$row->entry_date.' · '.$row->entry_type.' · '.cpcc_portal_money($row->amount).' '.$row->currency_code.' · '.($row->reference ?: $row->description);
}

function m077RequestKey($userId, $purpose)
{
    return hash('sha256', session_id().':'.(int) $userId.':'.$purpose.':'.bin2hex(random_bytes(16)));
}

$morecss = array(cpcc_admin_shell_asset('/custom/chinaplanetcustomercenter/css/admin_dashboard.css'), cpcc_admin_shell_asset('/custom/chinaplanetcustomercenter/css/ux_fixes.css'));
llxHeader('', 'Managed Customer Funds', '', '', 0, 0, array(), $morecss);
cpcc_admin_shell_open('financial', 'Managed Customer Funds / أموال العملاء المخصصة', 'Native payment evidence, reservation, actual settlement and audit — without duplicate cash or mixed currencies', 'fa-shield-halved', array('socid'=>$socid));
?>
<main class="cpad-finance-portfolio-theme">
<div class="info">هذه الصفحة لا تنشئ دفعة بنكية ولا دفع مورد. هي تربط دفعة Dolibarr مؤكدة بتخصيصات تشغيلية قابلة للتدقيق. Pending ليس رصيداً متاحاً، وReserved ليس دفعاً فعلياً.</div>
<form method="get" class="cpad-context-form"><div class="cpad-context-fields"><label><span>Customer / العميل</span><select required name="socid"><option value="">— اختر العميل —</option><?php foreach($customers as $id=>$name){ ?><option value="<?php echo $id; ?>"<?php echo $socid===$id?' selected':''; ?>><?php echo dol_escape_htmltag($name); ?></option><?php } ?></select></label><button class="button">Load / عرض</button></div></form>
<?php if($socid>0){ ?>
<section class="cpad-kpis"><?php foreach($currencySummaries as $currency=>$totals){ ?><div class="cpad-kpi"><b><?php echo dol_escape_htmltag($currency).' '.cpcc_portal_money($totals['unallocated']); ?></b><span>Currently allocatable / المتاح للتخصيص الآن</span><small>Native credit used <?php echo cpcc_portal_money($totals['native_consumed']); ?> · Blocked allocations <?php echo cpcc_portal_money($totals['blocked']); ?><?php echo $totals['invalid']?' · Invalid evidence '.$totals['invalid']:''; ?></small><small>Due now <?php echo cpcc_portal_money($totals['due_now']); ?> · Future due <?php echo cpcc_portal_money($totals['future_due']); ?></small><small>Pending <?php echo cpcc_portal_money($totals['pending']); ?> · Reserved <?php echo cpcc_portal_money($totals['reserved']); ?> · Settled <?php echo cpcc_portal_money($totals['settled']); ?></small></div><?php } ?><?php if(!$currencySummaries){ ?><div class="cpad-kpi"><b>—</b><span>No managed receipts or due invoices / لا توجد حوالات أو فواتير مستحقة</span></div><?php } ?></section>
<?php if($canWrite){ ?><div class="fichecenter"><div class="fichehalfleft">
<section class="cpad-panel"><h2>1. Register pending receipt / تسجيل حوالة معلقة</h2><form method="post"><input type="hidden" name="token" value="<?php echo newToken(); ?>"><input type="hidden" name="action" value="create_pending"><input type="hidden" name="socid" value="<?php echo $socid; ?>"><input type="hidden" name="request_key" value="<?php echo m077RequestKey($user->id,'receipt'); ?>"><label class="cpo-field"><span>Date / التاريخ</span><input required type="date" name="receipt_date" value="<?php echo date('Y-m-d'); ?>"></label><label class="cpo-field"><span>Amount / المبلغ</span><input required inputmode="decimal" name="amount"></label><label class="cpo-field"><span>Currency / العملة</span><input required maxlength="3" name="currency_code" placeholder="USD"></label><label class="cpo-field"><span>Reference / المرجع</span><input name="reference"></label><label class="cpo-field"><span>Internal note / ملاحظة داخلية</span><textarea name="note_private"></textarea></label><button class="button button-save">Register pending only / تسجيل كمعلق فقط</button></form></section>
<section class="cpad-panel"><h2>2. Confirm with native payment / تأكيد بدفعة أصلية</h2><form method="post"><input type="hidden" name="token" value="<?php echo newToken(); ?>"><input type="hidden" name="action" value="confirm_receipt"><input type="hidden" name="socid" value="<?php echo $socid; ?>"><label class="cpo-field"><span>Pending receipt / الحوالة المعلقة</span><select required name="receipt_id"><option value="">—</option><?php foreach($receipts as $receipt)if($receipt['status']==='pending'){ ?><option value="<?php echo (int)$receipt['rowid']; ?>">#<?php echo (int)$receipt['rowid'].' · '.dol_escape_htmltag($receipt['reference']?:'—').' · '.cpcc_portal_money($receipt['amount']).' '.dol_escape_htmltag($receipt['currency_code']); ?></option><?php } ?></select></label><label class="cpo-field"><span>Dolibarr payment / دفعة Dolibarr</span><select required name="native_payment_id"><option value="">—</option><?php foreach($paymentOptions as $id=>$label){ ?><option value="<?php echo $id; ?>"><?php echo dol_escape_htmltag($label); ?></option><?php } ?></select></label><button class="button button-save">Confirm exact match / تأكيد التطابق</button></form></section>
</div><div class="fichehalfright">
<section class="cpad-panel"><h2>3. Reserve by operation / تخصيص تشغيلي</h2><form method="post"><input type="hidden" name="token" value="<?php echo newToken(); ?>"><input type="hidden" name="action" value="reserve"><input type="hidden" name="socid" value="<?php echo $socid; ?>"><input type="hidden" name="request_key" value="<?php echo m077RequestKey($user->id,'allocation'); ?>"><label class="cpo-field"><span>Valid receipt with available funds / حوالة صالحة برصيد متاح</span><select required name="receipt_id"><option value="">—</option><?php foreach($receipts as $receipt){$rr=$receiptReconciliation[(int)$receipt['rowid']]??null;if($receipt['status']==='confirmed'&&$rr&&!empty($rr['evidence_valid'])&&(float)$rr['available']>0.005){ ?><option value="<?php echo (int)$receipt['rowid']; ?>">#<?php echo (int)$receipt['rowid'].' · '.dol_escape_htmltag($receipt['reference']?:'—').' · available '.cpcc_portal_money($rr['available']).' '.dol_escape_htmltag($receipt['currency_code']); ?></option><?php }} ?></select></label><label class="cpo-field"><span>Target / العملية أو الشحنة أو المشروع أو الفاتورة</span><select required name="target"><option value="">—</option><?php foreach($targetOptions as $key=>$label){ ?><option value="<?php echo dol_escape_htmltag($key); ?>"><?php echo dol_escape_htmltag($label); ?></option><?php } ?></select></label><label class="cpo-field"><span>Amount / المبلغ بنفس عملة الحوالة</span><input required inputmode="decimal" name="amount"></label><label class="cpo-field"><span>Internal note / ملاحظة</span><textarea name="note_private"></textarea></label><button class="button button-save">Reserve, not pay / تخصيص فقط بدون دفع</button></form></section>
<section class="cpad-panel"><h2>4. Settle against posted debit / تسوية بدفع فعلي</h2><form method="post"><input type="hidden" name="token" value="<?php echo newToken(); ?>"><input type="hidden" name="action" value="settle"><input type="hidden" name="socid" value="<?php echo $socid; ?>"><label class="cpo-field"><span>Covered reserved allocation / تخصيص محجوز ومغطى</span><select required name="allocation_id"><option value="">—</option><?php foreach($allocations as $allocation)if($allocation['status']==='reserved'&&!empty($allocationCoverage[(int)$allocation['rowid']])){ ?><option value="<?php echo (int)$allocation['rowid']; ?>">#<?php echo (int)$allocation['rowid'].' · '.dol_escape_htmltag($allocation['target_type']).' #'.(int)$allocation['target_id'].' · '.cpcc_portal_money($allocation['amount']).' '.dol_escape_htmltag($allocation['currency_code']); ?></option><?php } ?></select></label><label class="cpo-field"><span>Posted customer debit / المصروف الفعلي المسجل</span><select required name="financial_entry_id"><option value="">—</option><?php foreach($debitOptions as $id=>$label){ ?><option value="<?php echo $id; ?>"><?php echo dol_escape_htmltag($label); ?></option><?php } ?></select></label><button class="button button-save">Link and settle / ربط وتسوية</button></form></section>
</div></div><?php } ?>
<section class="cpad-panel"><div class="cpad-title"><span>Receipts / الحوالات</span><small>Pending or invalid evidence is never available</small></div><div class="cpad-table-scroll"><table class="cpad-table"><thead><tr><th>ID</th><th>Date</th><th>Reference</th><th>Amount</th><th>Status</th><th>Native evidence</th></tr></thead><tbody><?php foreach($receipts as $receipt){$rr=$receiptReconciliation[(int)$receipt['rowid']]??null;$displayStatus=$receipt['status'];if($rr&&empty($rr['evidence_valid']))$displayStatus='blocked: invalid native evidence';elseif($rr&&(float)$rr['blocked']>0.005)$displayStatus='blocked allocation '.cpcc_portal_money($rr['blocked']); ?><tr><td>#<?php echo (int)$receipt['rowid']; ?></td><td><?php echo dol_escape_htmltag($receipt['receipt_date']); ?></td><td><?php echo dol_escape_htmltag($receipt['reference']?:'—'); ?></td><td><?php echo cpcc_portal_money($receipt['amount']).' '.dol_escape_htmltag($receipt['currency_code']); ?></td><td><?php echo dol_escape_htmltag($displayStatus); ?></td><td><?php echo $receipt['native_source_id']?'Vision payment #'.(int)$receipt['native_source_id']:'—'; ?></td></tr><?php } ?></tbody></table></div></section>
<section class="cpad-panel"><div class="cpad-title"><span>Allocations / التخصيصات</span><small>Reserved and settled remain separate</small></div><div class="cpad-table-scroll"><table class="cpad-table"><thead><tr><th>ID</th><th>Target</th><th>Amount</th><th>Status</th><th>Actual debit</th><th>Action</th></tr></thead><tbody><?php foreach($allocations as $allocation){ ?><tr><td>#<?php echo (int)$allocation['rowid']; ?></td><td><?php echo dol_escape_htmltag($allocation['target_type']).' #'.(int)$allocation['target_id']; ?></td><td><?php echo cpcc_portal_money($allocation['amount']).' '.dol_escape_htmltag($allocation['currency_code']); ?></td><td><?php echo dol_escape_htmltag($allocation['status']); ?></td><td><?php echo $allocation['disbursement_id']?'Entry #'.(int)$allocation['disbursement_id']:'—'; ?></td><td><?php if($canWrite&&$allocation['status']==='reserved'){ ?><details><summary>Reverse / عكس</summary><form method="post"><input type="hidden" name="token" value="<?php echo newToken(); ?>"><input type="hidden" name="action" value="reverse"><input type="hidden" name="socid" value="<?php echo $socid; ?>"><input type="hidden" name="allocation_id" value="<?php echo (int)$allocation['rowid']; ?>"><input required name="reason" maxlength="500" placeholder="Reason / السبب"><button class="button">Confirm reversal</button></form></details><?php }elseif($allocation['status']==='settled'){ ?><small>Reverse native debit first / اعكس القيد الأصلي أولاً</small><?php } ?></td></tr><?php } ?></tbody></table></div></section>
<?php } ?>
</main>
<?php cpcc_admin_shell_close(); llxFooter(); $db->close();
