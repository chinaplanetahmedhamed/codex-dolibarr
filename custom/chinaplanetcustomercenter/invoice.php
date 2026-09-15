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

$appliedCredits = cpcc_financial_applied_credits($db, $socid, $id, $currencyFallback);
$creditLineIds = array();
foreach ($appliedCredits as $credit) if (!empty($credit['line_id'])) $creditLineIds[] = (int) $credit['line_id'];
$lines = array();
$lineSql = "SELECT rowid,description,qty,subprice,total_ht,multicurrency_subprice,multicurrency_total_ht FROM ".MAIN_DB_PREFIX."facturedet WHERE fk_facture=".$id;
if (!empty($creditLineIds)) $lineSql .= " AND rowid NOT IN (".implode(',', $creditLineIds).")";
$lineSql .= " ORDER BY rang,rowid";
$res = $db->query($lineSql);
if ($res) while ($o = $db->fetch_object($res)) $lines[] = $o;
$payments = array();
$res = $db->query("SELECT p.datep,p.ref,p.num_paiement,pf.amount,pf.multicurrency_amount FROM ".MAIN_DB_PREFIX."paiement p JOIN ".MAIN_DB_PREFIX."paiement_facture pf ON pf.fk_paiement=p.rowid WHERE pf.fk_facture=".$id." AND ".cpcc_financial_payment_status_sql('p')." ORDER BY p.datep,p.rowid");
if ($res) while ($o = $db->fetch_object($res)) $payments[] = $o;

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
  <header class="cpcc-page-head"><div><span class="cpcc-eyebrow"><?php echo dol_escape_htmltag($invoice['ref']); ?></span><h1>تفاصيل الفاتورة / Invoice Details</h1><p><?php echo dol_escape_htmltag($invoice['project_ref'].' '.$invoice['project_title']); ?></p></div></header>
  <section class="cpcc-financial-cards">
    <div class="cpcc-card invoices"><span>إجمالي الفاتورة / Gross Total</span><strong><?php echo cpcc_portal_money($invoice['total']).' '.dol_escape_htmltag($invoice['currency']); ?></strong></div>
    <div class="cpcc-card received"><span>الممول / Funded</span><strong><?php echo cpcc_portal_money($invoice['paid']).' '.dol_escape_htmltag($invoice['currency']); ?></strong></div>
    <div class="cpcc-card required"><span>المتبقي / Remaining</span><strong><?php echo cpcc_portal_money($invoice['remaining']).' '.dol_escape_htmltag($invoice['currency']); ?></strong></div>
  </section>
  <section class="cpcc-section-head"><div><h2>بنود الخدمات / Service Lines</h2></div></section>
  <div class="cpcc-table-wrap"><table class="cpcc-table"><thead><tr><th>الخدمة / Service</th><th>الكمية / Qty</th><th>سعر الوحدة / Unit Price</th><th>الإجمالي / Total</th></tr></thead><tbody>
  <?php foreach ($lines as $line) { $multi=$invoice['currency']!==$currencyFallback; $price=$multi&&$line->multicurrency_subprice!=0?$line->multicurrency_subprice:$line->subprice; $total=$multi&&$line->multicurrency_total_ht!=0?$line->multicurrency_total_ht:$line->total_ht; ?>
    <tr><td><?php echo dol_escape_htmltag($line->description); ?></td><td><?php echo cpcc_portal_money($line->qty); ?></td><td><?php echo cpcc_portal_money($price); ?></td><td><?php echo cpcc_portal_money($total).' '.dol_escape_htmltag($invoice['currency']); ?></td></tr>
  <?php } ?></tbody></table></div>
  <section class="cpcc-section-head"><div><h2>الرصيد المطبّق / Applied Customer Credit</h2><p>دفعات مقدمة حوّلها Dolibarr إلى رصيد واستُخدمت في هذه الفاتورة.</p></div></section>
  <div class="cpcc-table-wrap"><table class="cpcc-table"><thead><tr><th>المصدر / Source</th><th>المبلغ / Amount</th></tr></thead><tbody>
  <?php if (empty($appliedCredits)) { ?><tr><td colspan="2">لم يُستخدم رصيد مقدم في هذه الفاتورة / No customer credit applied.</td></tr><?php }
  foreach ($appliedCredits as $credit) { ?>
    <tr><td><?php echo dol_escape_htmltag($credit['source_invoice_ref'] ?: ('#'.$credit['source_invoice_id'])); ?></td><td><?php echo cpcc_portal_money($credit['amount']).' '.dol_escape_htmltag($credit['currency']); ?></td></tr>
  <?php } ?></tbody></table></div>
  <section class="cpcc-section-head"><div><h2>الدفعات المرتبطة / Linked Payments</h2></div></section>
  <div class="cpcc-table-wrap"><table class="cpcc-table"><thead><tr><th>التاريخ / Date</th><th>المرجع / Reference</th><th>المبلغ / Amount</th></tr></thead><tbody>
  <?php if (empty($payments)) { ?><tr><td colspan="3">لا توجد دفعات مرتبطة / No linked payments.</td></tr><?php }
  foreach ($payments as $payment) { $amount=$invoice['currency']!==$currencyFallback&&$payment->multicurrency_amount!=0?$payment->multicurrency_amount:$payment->amount; ?>
    <tr><td><?php echo dol_escape_htmltag(cpcc_portal_date($payment->datep,'day')); ?></td><td><?php echo dol_escape_htmltag($payment->num_paiement?:$payment->ref); ?></td><td><?php echo cpcc_portal_money($amount).' '.dol_escape_htmltag($invoice['currency']); ?></td></tr>
  <?php } ?></tbody></table></div>
</main>
<?php
if (!$isExternal) cpcc_admin_shell_close();
llxFooter();
