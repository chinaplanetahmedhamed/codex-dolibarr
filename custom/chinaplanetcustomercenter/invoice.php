<?php
require_once __DIR__.'/lib/ui.lib.php';
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
else cpcc_admin_shell_open('portal', cpcc_ui_t('UIInvoiceDetails397a6'), cpcc_ui_english_label('Invoice lines, paid amount and remaining balance.', 'بنود الفاتورة والمدفوع والمتبقي'), 'fa-file-invoice-dollar', array('socid'=>$socid));
?>
<main class="cpcc-dashboard" dir="<?php echo cpcc_ui_english_label('ltr', 'rtl'); ?>">
  <header class="cpcc-page-head"><div><span class="cpcc-eyebrow"><?php echo dol_escape_htmltag($invoice['ref']); ?></span><h1><?php echo dol_escape_htmltag(cpcc_ui_t('UIInvoiceDetails397a6')); ?></h1><p><?php echo dol_escape_htmltag($invoice['project_ref'].' '.$invoice['project_title']); ?></p></div></header>
  <section class="cpcc-financial-cards">
    <div class="cpcc-card invoices"><span><?php echo dol_escape_htmltag(cpcc_ui_t('UIGrossTotalfa70e')); ?></span><strong><?php echo cpcc_portal_money($invoice['total']).' '.dol_escape_htmltag($invoice['currency']); ?></strong></div>
    <div class="cpcc-card received"><span><?php echo dol_escape_htmltag(cpcc_ui_t('UIFundedf6a99')); ?></span><strong><?php echo cpcc_portal_money($invoice['paid']).' '.dol_escape_htmltag($invoice['currency']); ?></strong></div>
    <div class="cpcc-card required"><span><?php echo dol_escape_htmltag(cpcc_ui_t('UIRemainingcc632')); ?></span><strong><?php echo cpcc_portal_money($invoice['remaining']).' '.dol_escape_htmltag($invoice['currency']); ?></strong></div>
  </section>
  <section class="cpcc-section-head"><div><h2><?php echo dol_escape_htmltag(cpcc_ui_t('UIServiceLinesa5a47')); ?></h2></div></section>
  <div class="cpcc-table-wrap"><table class="cpcc-table"><thead><tr><th><?php echo dol_escape_htmltag(cpcc_ui_t('UIService329cb')); ?></th><th><?php echo dol_escape_htmltag(cpcc_ui_t('UIQty1e5ff')); ?></th><th><?php echo dol_escape_htmltag(cpcc_ui_t('UIUnitPrice1eea6')); ?></th><th><?php echo dol_escape_htmltag(cpcc_ui_t('UITotalb2592')); ?></th></tr></thead><tbody>
  <?php foreach ($lines as $line) { $multi=$invoice['currency']!==$currencyFallback; $price=$multi&&$line->multicurrency_subprice!=0?$line->multicurrency_subprice:$line->subprice; $total=$multi&&$line->multicurrency_total_ht!=0?$line->multicurrency_total_ht:$line->total_ht; ?>
    <tr><td><?php echo dol_escape_htmltag($line->description); ?></td><td><?php echo cpcc_portal_money($line->qty); ?></td><td><?php echo cpcc_portal_money($price); ?></td><td><?php echo cpcc_portal_money($total).' '.dol_escape_htmltag($invoice['currency']); ?></td></tr>
  <?php } ?></tbody></table></div>
  <section class="cpcc-section-head"><div><h2><?php echo dol_escape_htmltag(cpcc_ui_t('UIAppliedCustomerCreditd0128')); ?></h2><p><?php echo cpcc_ui_english_label('Deposits converted by Dolibarr into credit and applied to this invoice.', 'دفعات مقدمة حوّلها Dolibarr إلى رصيد واستُخدمت في هذه الفاتورة.'); ?></p></div></section>
  <div class="cpcc-table-wrap"><table class="cpcc-table"><thead><tr><th><?php echo dol_escape_htmltag(cpcc_ui_t('UISource6da13')); ?></th><th><?php echo dol_escape_htmltag(cpcc_ui_t('UIAmount43dc8')); ?></th></tr></thead><tbody>
  <?php if (empty($appliedCredits)) { ?><tr><td colspan="2"><?php echo dol_escape_htmltag(cpcc_ui_t('UINoCustomerCreditApplied88983')); ?></td></tr><?php }
  foreach ($appliedCredits as $credit) { ?>
    <tr><td><?php echo dol_escape_htmltag($credit['source_invoice_ref'] ?: ('#'.$credit['source_invoice_id'])); ?></td><td><?php echo cpcc_portal_money($credit['amount']).' '.dol_escape_htmltag($credit['currency']); ?></td></tr>
  <?php } ?></tbody></table></div>
  <section class="cpcc-section-head"><div><h2><?php echo dol_escape_htmltag(cpcc_ui_t('UILinkedPayments17e6e')); ?></h2></div></section>
  <div class="cpcc-table-wrap"><table class="cpcc-table"><thead><tr><th><?php echo dol_escape_htmltag(cpcc_ui_t('UIDateeb9a4')); ?></th><th><?php echo dol_escape_htmltag(cpcc_ui_t('UIReferencedb1c7')); ?></th><th><?php echo dol_escape_htmltag(cpcc_ui_t('UIAmount43dc8')); ?></th></tr></thead><tbody>
  <?php if (empty($payments)) { ?><tr><td colspan="3"><?php echo dol_escape_htmltag(cpcc_ui_t('UINoLinkedPayments4d8e7')); ?></td></tr><?php }
  foreach ($payments as $payment) { $amount=$invoice['currency']!==$currencyFallback&&$payment->multicurrency_amount!=0?$payment->multicurrency_amount:$payment->amount; ?>
    <tr><td><?php echo dol_escape_htmltag(cpcc_portal_date($payment->datep,'day')); ?></td><td><?php echo dol_escape_htmltag($payment->num_paiement?:$payment->ref); ?></td><td><?php echo cpcc_portal_money($amount).' '.dol_escape_htmltag($invoice['currency']); ?></td></tr>
  <?php } ?></tbody></table></div>
</main>
<?php
if (!$isExternal) cpcc_admin_shell_close();
llxFooter();
