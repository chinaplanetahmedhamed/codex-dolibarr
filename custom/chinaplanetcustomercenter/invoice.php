<?php
if (!isset($_SERVER['QUERY_STRING'])) $_SERVER['QUERY_STRING'] = '';
require_once __DIR__.'/lib/main.lib.php';
require_once __DIR__.'/lib/admin_shell.lib.php';
require_once __DIR__.'/lib/portal.lib.php';
require_once __DIR__.'/lib/financial.lib.php';

cpcc_portal_require_access($user);
$scope = cpcc_portal_user_scope($user);
$isExternal = !empty($scope['is_external']);
$socid = cpcc_portal_customer_scope_id($db, $user, GETPOSTINT('socid'));
$id = GETPOSTINT('id');
$currencyFallback = cpcc_financial_currency(!empty($conf->currency) ? $conf->currency : 'USD');
$rows = $socid > 0 ? cpcc_financial_customer_invoices($db, $socid, $currencyFallback) : array();
$invoice = null;
foreach ($rows as $row) {
    if ($row['id'] === $id) { $invoice = $row; break; }
}
if (!$invoice) cpcc_portal_access_denied($user, 'invoices');

$invoiceMeta = null;
$metaSql = "SELECT total_ht,total_tva,multicurrency_total_ht,multicurrency_total_tva,note_public FROM ".MAIN_DB_PREFIX."facture WHERE rowid=".$id." AND entity=".(int)$conf->entity." AND fk_soc=".(int)$socid." AND fk_statut>0 AND type IN (0,1,5)";
$metaResult = $db->query($metaSql);
if ($metaResult) $invoiceMeta = $db->fetch_object($metaResult);
$isMultiCurrency = !empty($invoice['is_multicurrency']);
$subtotal = $invoiceMeta ? (float) ($isMultiCurrency ? $invoiceMeta->multicurrency_total_ht : $invoiceMeta->total_ht) : 0.0;
$tax = $invoiceMeta ? (float) ($isMultiCurrency ? $invoiceMeta->multicurrency_total_tva : $invoiceMeta->total_tva) : 0.0;
$invoicePublicNote = $invoiceMeta ? trim((string) $invoiceMeta->note_public) : '';
$linkedOrder = null;
$orderSql = "SELECT c.rowid,c.ref FROM ".MAIN_DB_PREFIX."element_element ee INNER JOIN ".MAIN_DB_PREFIX."commande c ON c.rowid=ee.fk_source WHERE ee.sourcetype='commande' AND ee.targettype='facture' AND ee.fk_target=".$id." AND c.entity=".(int)$conf->entity." AND c.fk_soc=".(int)$socid." ORDER BY c.rowid LIMIT 1";
$orderResult = $db->query($orderSql);
if ($orderResult) $linkedOrder = $db->fetch_object($orderResult);

$appliedCredits = cpcc_financial_applied_credits($db, $socid, $id, $currencyFallback);
$creditLineIds = array();
foreach ($appliedCredits as $credit) if (!empty($credit['line_id'])) $creditLineIds[] = (int) $credit['line_id'];
$lines = array();
$lineSql = "SELECT fd.rowid,fd.description,fd.label,fd.product_type,fd.qty,fd.subprice,fd.remise_percent,fd.tva_tx,fd.total_ht,fd.total_tva,fd.multicurrency_subprice,fd.multicurrency_total_ht,fd.multicurrency_total_tva,COALESCE(NULLIF(TRIM(u.short_label),''),NULLIF(TRIM(u.label),''),NULLIF(TRIM(u.code),'')) AS unit_public FROM ".MAIN_DB_PREFIX."facturedet fd LEFT JOIN ".MAIN_DB_PREFIX."c_units u ON u.rowid=fd.fk_unit WHERE fd.fk_facture=".$id;
if (!empty($creditLineIds)) $lineSql .= " AND fd.rowid NOT IN (".implode(',', $creditLineIds).")";
$lineSql .= " ORDER BY fd.rang,fd.rowid";
$res = $db->query($lineSql);
if ($res) while ($o = $db->fetch_object($res)) $lines[] = $o;
$payments = array();
$publicNoteSql = cpcc_financial_payment_public_note_sql($db, 'p', 'payment_extra');
$res = $db->query("SELECT p.datep,p.ref,p.num_paiement,pf.amount,pf.multicurrency_amount,".$publicNoteSql['select']." FROM ".MAIN_DB_PREFIX."paiement p JOIN ".MAIN_DB_PREFIX."paiement_facture pf ON pf.fk_paiement=p.rowid".$publicNoteSql['join']." WHERE pf.fk_facture=".$id." AND ".cpcc_financial_payment_status_sql('p')." ORDER BY p.datep,p.rowid");
if ($res) while ($o = $db->fetch_object($res)) $payments[] = $o;
$invoiceStatusLabels = array('paid'=>'مدفوعة / Paid','partial'=>'مدفوعة جزئياً / Partially paid','open'=>'غير مدفوعة / Open','overdue'=>'متأخرة / Overdue');
$dueDate = cpcc_portal_date($invoice['due_date']);
$discount = 0.0;
foreach ($lines as $line) {
    $unitPrice = $isMultiCurrency && (float)$line->multicurrency_subprice != 0.0 ? (float)$line->multicurrency_subprice : (float)$line->subprice;
    $lineSubtotal = $isMultiCurrency && (float)$line->multicurrency_total_ht != 0.0 ? (float)$line->multicurrency_total_ht : (float)$line->total_ht;
    $discount += max(0.0, ((float)$line->qty * $unitPrice) - $lineSubtotal);
}

$css = array('/custom/chinaplanetcustomercenter/css/customer_dashboard.css');
if (!$isExternal) {
    $css[] = cpcc_admin_shell_asset('/custom/chinaplanetcustomercenter/css/admin_dashboard.css');
    $css[] = cpcc_admin_shell_asset('/custom/chinaplanetcustomercenter/css/ux_fixes.css');
}
llxHeader('', 'Invoice '.$invoice['ref'], '', '', 0, 0, array(), $css);
if ($isExternal) cpcc_portal_navigation($user, 'invoices');
else cpcc_admin_shell_open('portal', 'Invoice Details / تفاصيل الفاتورة', 'بنود الفاتورة والمدفوع والمتبقي', 'fa-file-invoice-dollar', array('socid'=>$socid));
?>
<main class="cpcc-dashboard" dir="rtl">
  <header class="cpcc-page-head"><div><span class="cpcc-eyebrow"><?php echo dol_escape_htmltag($invoice['ref']); ?></span><h1>تفاصيل الفاتورة / Invoice Details</h1><p><?php echo dol_escape_htmltag($invoice['project_ref'].' '.$invoice['project_title']); ?></p></div><?php if(!$isExternal&&($user->admin||$user->hasRight('chinaplanetcustomercenter','email_write'))){ ?><a class="button cpcc-section-email-action" href="<?php echo dol_escape_htmltag(dol_buildpath('/chinaplanetcustomercenter/section_email.php',1).'?section=invoice&socid='.(int)$socid.'&contextid='.(int)$id); ?>"><span class="fas fa-envelope" aria-hidden="true"></span> إرسال الفاتورة / Send invoice</a><?php } ?></header>
  <section class="cpcc-invoice-meta" aria-label="Invoice metadata / بيانات الفاتورة">
    <div><span>تاريخ الإصدار / Issue date</span><strong><?php echo dol_escape_htmltag(cpcc_portal_date($invoice['date'])); ?></strong></div>
    <div><span>الاستحقاق / Due date</span><strong><?php echo $dueDate!==''?dol_escape_htmltag($dueDate):'—'; ?></strong></div>
    <div><span>المشروع / Project</span><strong><?php echo dol_escape_htmltag($invoice['project_ref']?:'—'); ?></strong></div>
    <div><span>طلب العميل / Customer order</span><strong><?php echo dol_escape_htmltag($linkedOrder?$linkedOrder->ref:'—'); ?></strong></div>
  </section>
  <section class="cpcc-financial-cards">
    <div class="cpcc-card services"><span>قبل الضريبة / Subtotal</span><strong><?php echo cpcc_portal_money($subtotal).' '.dol_escape_htmltag($invoice['currency']); ?></strong></div>
    <?php if($discount>0.005){ ?><div class="cpcc-card obligations"><span>الخصم / Discount</span><strong><?php echo cpcc_portal_money($discount).' '.dol_escape_htmltag($invoice['currency']); ?></strong></div><?php } ?>
    <div class="cpcc-card services"><span>الضريبة / Tax</span><strong><?php echo cpcc_portal_money($tax).' '.dol_escape_htmltag($invoice['currency']); ?></strong></div>
    <div class="cpcc-card invoices"><span>إجمالي الفاتورة / Gross total</span><strong><?php echo cpcc_portal_money($invoice['total']).' '.dol_escape_htmltag($invoice['currency']); ?></strong></div>
    <div class="cpcc-card received"><span>دفعات مباشرة / Direct payments</span><strong><?php echo cpcc_portal_money($invoice['direct_paid']).' '.dol_escape_htmltag($invoice['currency']); ?></strong></div>
    <div class="cpcc-card received"><span>رصيد مطبق / Applied credit</span><strong><?php echo cpcc_portal_money($invoice['applied_credit']).' '.dol_escape_htmltag($invoice['currency']); ?></strong></div>
    <div class="cpcc-card received"><span>الممول / Funded</span><strong><?php echo cpcc_portal_money($invoice['paid']).' '.dol_escape_htmltag($invoice['currency']); ?></strong></div>
    <div class="cpcc-card required"><span>المتبقي / Remaining</span><strong><?php echo cpcc_portal_money($invoice['remaining']).' '.dol_escape_htmltag($invoice['currency']); ?></strong></div>
    <div class="cpcc-card <?php echo $invoice['status']==='overdue'?'required':''; ?>"><span>الاستحقاق / Due</span><strong><?php echo $dueDate!==''?dol_escape_htmltag($dueDate):'غير محدد / Not specified'; ?></strong><small><?php echo dol_escape_htmltag($invoiceStatusLabels[$invoice['status']]); ?></small></div>
  </section>
  <?php if($invoicePublicNote!==''){ ?><section class="cpcc-public-note-block"><h2>ملاحظة الفاتورة / Invoice note</h2><p><?php echo nl2br(dol_escape_htmltag(str_replace('\\n',"\n",$invoicePublicNote))); ?></p></section><?php } ?>
  <section class="cpcc-section-head"><div><h2>بنود الخدمات / Service Lines</h2></div></section>
  <?php if (empty($lines)) { ?><div class="<?php echo $isExternal?'cpcc-empty':'warning'; ?>">لا تتوفر تفاصيل بنود لهذه الفاتورة. يرجى التواصل مع فريق China Planet. / Invoice line details are unavailable; please contact the China Planet team.<?php if(!$isExternal){ ?> <strong>Data quality: validated invoice has no visible normal lines.</strong><?php } ?></div><?php } else { ?>
  <div class="cpcc-table-wrap"><table class="cpcc-table"><thead><tr><th>الخدمة / Service</th><th>الكمية / Qty</th><th>الوحدة / Unit</th><th>سعر الوحدة / Unit price</th><th>الضريبة / Tax</th><th>الإجمالي / Total</th></tr></thead><tbody>
  <?php foreach ($lines as $line) { $multi=!empty($invoice['is_multicurrency']); $price=$multi&&$line->multicurrency_subprice!=0?$line->multicurrency_subprice:$line->subprice; $total=$multi&&$line->multicurrency_total_ht!=0?$line->multicurrency_total_ht:$line->total_ht; ?>
    <tr><td><?php echo nl2br(dol_escape_htmltag(str_replace('\\n',"\n",trim((string)($line->description?:$line->label))))); ?></td><td><?php echo cpcc_portal_money($line->qty); ?></td><td><?php echo trim((string)$line->unit_public)!==''?dol_escape_htmltag($line->unit_public):'—'; ?></td><td><?php echo cpcc_portal_money($price); ?></td><td><?php echo cpcc_portal_money($line->tva_tx); ?>%</td><td><?php echo cpcc_portal_money($total).' '.dol_escape_htmltag($invoice['currency']); ?></td></tr>
  <?php } ?></tbody></table></div><?php } ?>
  <section class="cpcc-section-head"><div><h2>الرصيد المطبّق / Applied Customer Credit</h2><p>دفعات مقدمة حوّلها Dolibarr إلى رصيد واستُخدمت في هذه الفاتورة.</p></div></section>
  <div class="cpcc-table-wrap"><table class="cpcc-table"><thead><tr><th>المصدر / Source</th><th>المبلغ / Amount</th></tr></thead><tbody>
  <?php if (empty($appliedCredits)) { ?><tr><td colspan="2">لم يُستخدم رصيد مقدم في هذه الفاتورة / No customer credit applied.</td></tr><?php }
  foreach ($appliedCredits as $credit) { ?>
    <tr><td><?php echo dol_escape_htmltag($credit['source_invoice_ref'] ?: ('#'.$credit['source_invoice_id'])); ?></td><td><?php echo cpcc_portal_money($credit['amount']).' '.dol_escape_htmltag($credit['currency']); ?></td></tr>
  <?php } ?></tbody></table></div>
  <section class="cpcc-section-head"><div><h2>الدفعات المرتبطة / Linked Payments</h2></div></section>
  <div class="cpcc-table-wrap"><table class="cpcc-table"><thead><tr><th>التاريخ / Date</th><th>المرجع / Reference</th><th>المبلغ / Amount</th><th>ملاحظة الدفع / Payment note</th></tr></thead><tbody>
  <?php if (empty($payments)) { ?><tr><td colspan="4">لا توجد دفعات مرتبطة / No linked payments.</td></tr><?php }
  foreach ($payments as $payment) { $amount=!empty($invoice['is_multicurrency'])&&$payment->multicurrency_amount!=0?$payment->multicurrency_amount:$payment->amount; ?>
    <tr><td><?php echo dol_escape_htmltag(cpcc_portal_date($payment->datep)); ?></td><td><?php echo dol_escape_htmltag($payment->num_paiement?:$payment->ref); ?></td><td><?php echo cpcc_portal_money($amount).' '.dol_escape_htmltag($invoice['currency']); ?></td><td><?php echo trim((string)$payment->public_note)!==''?nl2br(dol_escape_htmltag($payment->public_note)):'—'; ?></td></tr>
  <?php } ?></tbody></table></div>
</main>
<?php
if (!$isExternal) cpcc_admin_shell_close();
llxFooter();
