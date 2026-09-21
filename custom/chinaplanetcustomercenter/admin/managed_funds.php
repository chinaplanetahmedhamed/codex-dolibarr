<?php

if (!isset($_SERVER['QUERY_STRING'])) $_SERVER['QUERY_STRING'] = '';
define('CSRFCHECK_WITH_TOKEN', 1);
require_once __DIR__.'/../lib/main.lib.php';
require_once __DIR__.'/../lib/admin_shell.lib.php';
require_once __DIR__.'/../lib/financial.lib.php';
require_once __DIR__.'/../lib/portal.lib.php';
require_once __DIR__.'/../class/CustomerFundsService.class.php';
require_once __DIR__.'/../class/NativeAdvanceWorkflowService.class.php';

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
        if ($action === 'create_native_deposit') {
            $origin=explode(':',GETPOST('origin','alphanohtml'),2);if(count($origin)!==2)throw new RuntimeException('Choose an eligible native order or proposal.');
            $native=new NativeAdvanceWorkflowService($db,(int)$conf->entity);$invoiceId=$native->createDepositFromOrigin(GETPOSTINT('socid'),$origin[0],(int)$origin[1],$user,GETPOST('request_key','alphanohtml'));
            setEventMessages('Native deposit invoice #'.$invoiceId.' created and validated from the selected source. No cash was recorded.',null,'mesgs');
        } elseif ($action === 'post_native_receipt') {
            $native=new NativeAdvanceWorkflowService($db,(int)$conf->entity);$result=$native->postBankReceipt(GETPOSTINT('socid'),GETPOSTINT('invoice_id'),GETPOSTINT('account_id'),GETPOST('payment_code','alpha'),GETPOST('receipt_date','alpha'),GETPOST('reference','alphanohtml'),$user,GETPOST('request_key','alphanohtml'));
            setEventMessages('Bank-posted payment #'.$result['payment_id'].' verified and managed receipt #'.$result['receipt_id'].' confirmed.',null,'mesgs');
        } elseif ($action === 'resume_native_receipt') {
            $native=new NativeAdvanceWorkflowService($db,(int)$conf->entity);$result=$native->resumeBankReceipt(GETPOSTINT('socid'),GETPOSTINT('marker_id'),$user);
            setEventMessages('Persisted bank payment #'.$result['payment_id'].' verified and managed receipt #'.$result['receipt_id'].' confirmed without creating cash.',null,'mesgs');
        } elseif ($action === 'create_pending') {
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

$receipts = array(); $allocations = array(); $paymentOptions = array(); $targetOptions = array(); $debitOptions = array(); $originOptions=array(); $depositOptions=array(); $resumeOptions=array(); $bankOptions=array(); $receiptModes=array(); $currencySummaries = array(); $receiptReconciliation = array(); $allocationCoverage = array();
if ($socid > 0) {
    $originQueries=array('commande'=>"SELECT c.rowid,c.ref,UPPER(CASE WHEN TRIM(COALESCE(c.multicurrency_code,''))<>'' THEN c.multicurrency_code ELSE '".$db->escape($conf->currency)."' END) currency,t.deposit_percent FROM ".MAIN_DB_PREFIX."commande c INNER JOIN ".MAIN_DB_PREFIX."c_payment_term t ON t.rowid=c.fk_cond_reglement AND t.active=1 AND t.deposit_percent>0 WHERE c.entity=".(int)$conf->entity." AND c.fk_soc=".$socid." AND c.fk_statut>0",'propal'=>"SELECT p.rowid,p.ref,UPPER(CASE WHEN TRIM(COALESCE(p.multicurrency_code,''))<>'' THEN p.multicurrency_code ELSE '".$db->escape($conf->currency)."' END) currency,t.deposit_percent FROM ".MAIN_DB_PREFIX."propal p INNER JOIN ".MAIN_DB_PREFIX."c_payment_term t ON t.rowid=p.fk_cond_reglement AND t.active=1 AND t.deposit_percent>0 WHERE p.entity=".(int)$conf->entity." AND p.fk_soc=".$socid." AND p.fk_statut>0");
    foreach($originQueries as $type=>$sql){$res=$db->query($sql);while($res&&($row=$db->fetch_object($res)))$originOptions[$type.':'.$row->rowid]=ucfirst($type).' · '.$row->ref.' · '.$row->deposit_percent.'% · '.$row->currency;}
    $res=$db->query("SELECT f.rowid,f.ref,UPPER(CASE WHEN TRIM(COALESCE(f.multicurrency_code,''))<>'' THEN f.multicurrency_code ELSE '".$db->escape($conf->currency)."' END) currency,CASE WHEN TRIM(COALESCE(f.multicurrency_code,''))<>'' THEN f.multicurrency_total_ttc ELSE f.total_ttc END amount FROM ".MAIN_DB_PREFIX."facture f INNER JOIN ".MAIN_DB_PREFIX."cpcc_guided_receipt g ON g.entity=f.entity AND g.fk_invoice=f.rowid AND g.fk_soc=f.fk_soc AND g.status='invoice_ready' LEFT JOIN ".MAIN_DB_PREFIX."paiement_facture pf ON pf.fk_facture=f.rowid WHERE f.entity=".(int)$conf->entity." AND f.fk_soc=".$socid." AND f.type=3 AND f.fk_statut=1 GROUP BY f.rowid HAVING COUNT(pf.fk_paiement)=0 ORDER BY f.rowid DESC");while($res&&($row=$db->fetch_object($res)))$depositOptions[(int)$row->rowid]=$row->ref.' · '.cpcc_portal_money($row->amount).' '.$row->currency;
    $res=$db->query("SELECT g.rowid marker_id,f.ref invoice_ref,p.ref payment_ref,DATE(p.datep) receipt_date,UPPER(CASE WHEN TRIM(COALESCE(f.multicurrency_code,''))<>'' THEN f.multicurrency_code ELSE '".$db->escape($conf->currency)."' END) currency,CASE WHEN TRIM(COALESCE(f.multicurrency_code,''))<>'' THEN pf.multicurrency_amount ELSE pf.amount END amount FROM ".MAIN_DB_PREFIX."cpcc_guided_receipt g INNER JOIN ".MAIN_DB_PREFIX."facture f ON f.entity=g.entity AND f.rowid=g.fk_invoice AND f.fk_soc=g.fk_soc AND f.type=3 INNER JOIN ".MAIN_DB_PREFIX."paiement p ON p.entity=g.entity AND p.rowid=g.fk_payment AND p.num_paiement=g.payment_token INNER JOIN ".MAIN_DB_PREFIX."paiement_facture pf ON pf.fk_paiement=p.rowid AND pf.fk_facture=f.rowid WHERE g.entity=".(int)$conf->entity." AND g.fk_soc=".$socid." AND g.status='bank_posted' ORDER BY g.rowid DESC");while($res&&($row=$db->fetch_object($res)))$resumeOptions[(int)$row->marker_id]=$row->invoice_ref.' · '.($row->payment_ref?:'#'.$row->marker_id).' · '.$row->receipt_date.' · '.cpcc_portal_money($row->amount).' '.$row->currency;
    $res=$db->query("SELECT rowid,label,UPPER(CASE WHEN TRIM(COALESCE(currency_code,''))<>'' THEN currency_code ELSE '".$db->escape($conf->currency)."' END) currency FROM ".MAIN_DB_PREFIX."bank_account WHERE entity=".(int)$conf->entity." AND clos=0 ORDER BY label");while($res&&($row=$db->fetch_object($res)))$bankOptions[(int)$row->rowid]=$row->label.' · '.$row->currency;
    $res=$db->query("SELECT code,libelle FROM ".MAIN_DB_PREFIX."c_paiement WHERE active=1 AND code<>'INTR' ORDER BY libelle");while($res&&($row=$db->fetch_object($res)))$receiptModes[$row->code]=$row->libelle;
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
cpcc_admin_shell_open('financial', 'Managed Customer Funds', 'Native payment evidence, reservation, actual settlement and audit — without duplicate cash or mixed currencies', 'fa-shield-halved', array('socid'=>$socid));
?>
<main class="cpad-finance-portfolio-theme">
<style>.cpad-finance-portfolio-theme .cpad-panel h2,.cpad-finance-portfolio-theme .cpad-panel p,.cpad-finance-portfolio-theme .cpad-panel label span{color:#f8fafc}.cpad-finance-portfolio-theme .cpad-panel .cpo-field{display:block;margin:.75rem 0}.cpad-finance-portfolio-theme .cpad-panel .cpo-field select,.cpad-finance-portfolio-theme .cpad-panel .cpo-field input,.cpad-finance-portfolio-theme .cpad-panel .cpo-field textarea{display:block;width:100%;margin-top:.35rem}</style>
<div class="info">The guided workflow creates a native deposit invoice, then a payment and bank entry after explicit selection. A manual Pending entry creates no funds; Reserved is not an actual supplier payment.</div>
<form method="get" class="cpad-context-form"><div class="cpad-context-fields"><label><span>Customer</span><select required name="socid"><option value="">— Select customer —</option><?php foreach($customers as $id=>$name){ ?><option value="<?php echo $id; ?>"<?php echo $socid===$id?' selected':''; ?>><?php echo dol_escape_htmltag($name); ?></option><?php } ?></select></label><button class="button">Load</button></div></form>
<?php if($socid>0){ ?>
<section class="cpad-kpis"><?php foreach($currencySummaries as $currency=>$totals){ ?><div class="cpad-kpi"><b><?php echo dol_escape_htmltag($currency).' '.cpcc_portal_money($totals['unallocated']); ?></b><span>Currently allocatable</span><small>Native credit used <?php echo cpcc_portal_money($totals['native_consumed']); ?> · Blocked allocations <?php echo cpcc_portal_money($totals['blocked']); ?><?php echo $totals['invalid']?' · Invalid evidence '.$totals['invalid']:''; ?></small><small>Due now <?php echo cpcc_portal_money($totals['due_now']); ?> · Future due <?php echo cpcc_portal_money($totals['future_due']); ?></small><small>Pending <?php echo cpcc_portal_money($totals['pending']); ?> · Reserved <?php echo cpcc_portal_money($totals['reserved']); ?> · Settled <?php echo cpcc_portal_money($totals['settled']); ?></small></div><?php } ?><?php if(!$currencySummaries){ ?><div class="cpad-kpi"><b>—</b><span>No managed receipts or due invoices</span></div><?php } ?></section>
<?php if($canWrite){ ?><div class="fichecenter"><div class="fichehalfleft">
<section class="cpad-panel"><h2>Guided deposit request</h2><p>Creates a real validated deposit invoice from the selected customer order/proposal. VAT, currency, project and deposit percentage come from that native source. This does not record cash.</p><form method="post"><input type="hidden" name="token" value="<?php echo newToken(); ?>"><input type="hidden" name="action" value="create_native_deposit"><input type="hidden" name="socid" value="<?php echo $socid; ?>"><input type="hidden" name="request_key" value="<?php echo m077RequestKey($user->id,'native-deposit'); ?>"><label class="cpo-field"><span>Eligible order/proposal</span><select required name="origin"><option value="">—</option><?php foreach($originOptions as $id=>$label){ ?><option value="<?php echo dol_escape_htmltag($id); ?>"><?php echo dol_escape_htmltag($label); ?></option><?php } ?></select></label><button class="button button-save">Create native deposit invoice</button></form><?php if(!$originOptions){ ?><small>No validated source with a configured deposit percentage.</small><?php } ?></section>
<section class="cpad-panel"><h2>1. Register pending receipt</h2><form method="post"><input type="hidden" name="token" value="<?php echo newToken(); ?>"><input type="hidden" name="action" value="create_pending"><input type="hidden" name="socid" value="<?php echo $socid; ?>"><input type="hidden" name="request_key" value="<?php echo m077RequestKey($user->id,'receipt'); ?>"><label class="cpo-field"><span>Date</span><input required type="date" name="receipt_date" value="<?php echo date('Y-m-d'); ?>"></label><label class="cpo-field"><span>Amount</span><input required inputmode="decimal" name="amount"></label><label class="cpo-field"><span>Currency</span><input required maxlength="3" name="currency_code" placeholder="USD"></label><label class="cpo-field"><span>Reference</span><input name="reference"></label><label class="cpo-field"><span>Internal note</span><textarea name="note_private"></textarea></label><button class="button button-save">Register pending only</button></form></section>
<section class="cpad-panel"><h2>2. Confirm with native payment</h2><form method="post"><input type="hidden" name="token" value="<?php echo newToken(); ?>"><input type="hidden" name="action" value="confirm_receipt"><input type="hidden" name="socid" value="<?php echo $socid; ?>"><label class="cpo-field"><span>Pending receipt</span><select required name="receipt_id"><option value="">—</option><?php foreach($receipts as $receipt)if($receipt['status']==='pending'){ ?><option value="<?php echo (int)$receipt['rowid']; ?>">#<?php echo (int)$receipt['rowid'].' · '.dol_escape_htmltag($receipt['reference']?:'—').' · '.cpcc_portal_money($receipt['amount']).' '.dol_escape_htmltag($receipt['currency_code']); ?></option><?php } ?></select></label><label class="cpo-field"><span>Dolibarr payment</span><select required name="native_payment_id"><option value="">—</option><?php foreach($paymentOptions as $id=>$label){ ?><option value="<?php echo $id; ?>"><?php echo dol_escape_htmltag($label); ?></option><?php } ?></select></label><button class="button button-save">Confirm exact match</button></form></section>
</div><div class="fichehalfright">
<section class="cpad-panel"><h2>Guided bank receipt</h2><p>Creates the native customer payment and bank entry. Managed funds are confirmed only after the bank link is verified.</p><form method="post"><input type="hidden" name="token" value="<?php echo newToken(); ?>"><input type="hidden" name="action" value="post_native_receipt"><input type="hidden" name="socid" value="<?php echo $socid; ?>"><input type="hidden" name="request_key" value="<?php echo m077RequestKey($user->id,'native-payment'); ?>"><label class="cpo-field"><span>Unpaid deposit invoice</span><select required name="invoice_id"><option value="">—</option><?php foreach($depositOptions as $id=>$label){ ?><option value="<?php echo $id; ?>"><?php echo dol_escape_htmltag($label); ?></option><?php } ?></select></label><label class="cpo-field"><span>Same-currency bank account</span><select required name="account_id"><option value="">—</option><?php foreach($bankOptions as $id=>$label){ ?><option value="<?php echo $id; ?>"><?php echo dol_escape_htmltag($label); ?></option><?php } ?></select></label><label class="cpo-field"><span>Method</span><select required name="payment_code"><option value="">—</option><?php foreach($receiptModes as $code=>$label){ ?><option value="<?php echo dol_escape_htmltag($code); ?>"><?php echo dol_escape_htmltag($label); ?></option><?php } ?></select></label><label class="cpo-field"><span>Date</span><input required type="date" name="receipt_date" value="<?php echo date('Y-m-d'); ?>"></label><label class="cpo-field"><span>Bank reference</span><input required name="reference"></label><button class="button button-save">Post and confirm</button></form></section>
<?php if($resumeOptions){ ?><section class="cpad-panel"><h2>Resume bank confirmation</h2><p>Uses the persisted native payment and bank evidence only. It does not create another payment or bank movement.</p><form method="post"><input type="hidden" name="token" value="<?php echo newToken(); ?>"><input type="hidden" name="action" value="resume_native_receipt"><input type="hidden" name="socid" value="<?php echo $socid; ?>"><label class="cpo-field"><span>Committed bank receipt</span><select required name="marker_id"><option value="">—</option><?php foreach($resumeOptions as $id=>$label){ ?><option value="<?php echo $id; ?>"><?php echo dol_escape_htmltag($label); ?></option><?php } ?></select></label><button class="button button-save">Verify and resume</button></form></section><?php } ?>
<section class="cpad-panel"><h2>3. Reserve by operation</h2><form method="post"><input type="hidden" name="token" value="<?php echo newToken(); ?>"><input type="hidden" name="action" value="reserve"><input type="hidden" name="socid" value="<?php echo $socid; ?>"><input type="hidden" name="request_key" value="<?php echo m077RequestKey($user->id,'allocation'); ?>"><label class="cpo-field"><span>Valid receipt with available funds</span><select required name="receipt_id"><option value="">—</option><?php foreach($receipts as $receipt){$rr=$receiptReconciliation[(int)$receipt['rowid']]??null;if($receipt['status']==='confirmed'&&$rr&&!empty($rr['evidence_valid'])&&(float)$rr['available']>0.005){ ?><option value="<?php echo (int)$receipt['rowid']; ?>">#<?php echo (int)$receipt['rowid'].' · '.dol_escape_htmltag($receipt['reference']?:'—').' · available '.cpcc_portal_money($rr['available']).' '.dol_escape_htmltag($receipt['currency_code']); ?></option><?php }} ?></select></label><label class="cpo-field"><span>Target operation, shipment, project or invoice</span><select required name="target"><option value="">—</option><?php foreach($targetOptions as $key=>$label){ ?><option value="<?php echo dol_escape_htmltag($key); ?>"><?php echo dol_escape_htmltag($label); ?></option><?php } ?></select></label><label class="cpo-field"><span>Amount in receipt currency</span><input required inputmode="decimal" name="amount"></label><label class="cpo-field"><span>Internal note</span><textarea name="note_private"></textarea></label><button class="button button-save">Reserve, not pay</button></form></section>
<section class="cpad-panel"><h2>4. Settle against posted debit</h2><form method="post"><input type="hidden" name="token" value="<?php echo newToken(); ?>"><input type="hidden" name="action" value="settle"><input type="hidden" name="socid" value="<?php echo $socid; ?>"><label class="cpo-field"><span>Covered reserved allocation</span><select required name="allocation_id"><option value="">—</option><?php foreach($allocations as $allocation)if($allocation['status']==='reserved'&&!empty($allocationCoverage[(int)$allocation['rowid']])){ ?><option value="<?php echo (int)$allocation['rowid']; ?>">#<?php echo (int)$allocation['rowid'].' · '.dol_escape_htmltag($allocation['target_type']).' #'.(int)$allocation['target_id'].' · '.cpcc_portal_money($allocation['amount']).' '.dol_escape_htmltag($allocation['currency_code']); ?></option><?php } ?></select></label><label class="cpo-field"><span>Posted customer debit</span><select required name="financial_entry_id"><option value="">—</option><?php foreach($debitOptions as $id=>$label){ ?><option value="<?php echo $id; ?>"><?php echo dol_escape_htmltag($label); ?></option><?php } ?></select></label><button class="button button-save">Link and settle</button></form></section>
</div></div><?php } ?>
<section class="cpad-panel"><div class="cpad-title"><span>Receipts</span><small>Pending or invalid evidence is never available</small></div><div class="cpad-table-scroll"><table class="cpad-table"><thead><tr><th>ID</th><th>Date</th><th>Reference</th><th>Amount</th><th>Status</th><th>Native evidence</th></tr></thead><tbody><?php foreach($receipts as $receipt){$rr=$receiptReconciliation[(int)$receipt['rowid']]??null;$displayStatus=$receipt['status'];if($rr&&empty($rr['evidence_valid']))$displayStatus='blocked: invalid native evidence';elseif($rr&&(float)$rr['blocked']>0.005)$displayStatus='blocked allocation '.cpcc_portal_money($rr['blocked']); ?><tr><td>#<?php echo (int)$receipt['rowid']; ?></td><td><?php echo dol_escape_htmltag($receipt['receipt_date']); ?></td><td><?php echo dol_escape_htmltag($receipt['reference']?:'—'); ?></td><td><?php echo cpcc_portal_money($receipt['amount']).' '.dol_escape_htmltag($receipt['currency_code']); ?></td><td><?php echo dol_escape_htmltag($displayStatus); ?></td><td><?php echo $receipt['native_source_id']?'Vision payment #'.(int)$receipt['native_source_id']:'—'; ?></td></tr><?php } ?></tbody></table></div></section>
<section class="cpad-panel"><div class="cpad-title"><span>Allocations</span><small>Reserved and settled remain separate</small></div><div class="cpad-table-scroll"><table class="cpad-table"><thead><tr><th>ID</th><th>Target</th><th>Amount</th><th>Status</th><th>Actual debit</th><th>Action</th></tr></thead><tbody><?php foreach($allocations as $allocation){ ?><tr><td>#<?php echo (int)$allocation['rowid']; ?></td><td><?php echo dol_escape_htmltag($allocation['target_type']).' #'.(int)$allocation['target_id']; ?></td><td><?php echo cpcc_portal_money($allocation['amount']).' '.dol_escape_htmltag($allocation['currency_code']); ?></td><td><?php echo dol_escape_htmltag($allocation['status']); ?></td><td><?php echo $allocation['disbursement_id']?'Entry #'.(int)$allocation['disbursement_id']:'—'; ?></td><td><?php if($canWrite&&$allocation['status']==='reserved'){ ?><details><summary>Reverse</summary><form method="post"><input type="hidden" name="token" value="<?php echo newToken(); ?>"><input type="hidden" name="action" value="reverse"><input type="hidden" name="socid" value="<?php echo $socid; ?>"><input type="hidden" name="allocation_id" value="<?php echo (int)$allocation['rowid']; ?>"><input required name="reason" maxlength="500" placeholder="Reason"><button class="button">Confirm reversal</button></form></details><?php }elseif($allocation['status']==='settled'){ ?><small>Reverse native debit first</small><?php } ?></td></tr><?php } ?></tbody></table></div></section>
<?php } ?>
</main>
<?php cpcc_admin_shell_close(); llxFooter(); $db->close();
