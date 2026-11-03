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
$currencyFallback = cpcc_financial_currency(!empty($conf->currency) ? $conf->currency : 'USD');
$selectedCurrency = cpcc_financial_currency(GETPOST('currency', 'alpha'), '');
$summary = $socid > 0 ? cpcc_financial_summary($db, $socid, $currencyFallback) : array();
$commitments = $socid > 0 ? cpcc_financial_commitment_rows($db, $socid, $currencyFallback, 0, '', !$isExternal) : array();
$invoices = $socid > 0 ? cpcc_financial_unpaid_invoices($db, $socid, $currencyFallback) : array();

if ($selectedCurrency !== '') {
    $commitments = array_values(array_filter($commitments, function ($row) use ($selectedCurrency) {
        return $row['currency'] === $selectedCurrency;
    }));
    $invoices = array_values(array_filter($invoices, function ($row) use ($selectedCurrency) {
        return $row['currency'] === $selectedCurrency;
    }));
}

$supplierRows = array_values(array_filter($commitments, function ($row) {
    return !empty($row['is_supplier']) && $row['remaining'] > 0.005;
}));
$serviceRows = array_values(array_filter($commitments, function ($row) {
    return empty($row['is_supplier']) && empty($row['is_billed']) && $row['remaining'] > 0.005;
}));

llxHeader('', cpcc_ui_english_label('Required Funding', 'Required Funding / التمويل المطلوب'), '', '', 0, 0, array(), $isExternal?array('/custom/chinaplanetcustomercenter/css/customer_dashboard.css','/custom/chinaplanetcustomercenter/css/funding.css'):array('/custom/chinaplanetcustomercenter/css/customer_dashboard.css','/custom/chinaplanetcustomercenter/css/funding.css',cpcc_admin_shell_asset('/custom/chinaplanetcustomercenter/css/admin_dashboard.css'),cpcc_admin_shell_asset('/custom/chinaplanetcustomercenter/css/ux_fixes.css')));
if($isExternal)cpcc_portal_navigation($user,'funding');else cpcc_admin_shell_open('portal',cpcc_ui_english_label('Required Funding', 'Required Funding / التمويل المطلوب'),cpcc_ui_english_label('Customer obligations and available balances by currency.', 'تفصيل التزامات العميل والرصيد المتاح لكل عملة'),'fa-coins',array('socid'=>$socid));
?>
<main class="cpcc-dashboard" dir="<?php echo cpcc_ui_english_label('ltr', 'rtl'); ?>">
  <header class="cpcc-page-head">
    <div><span class="cpcc-eyebrow">CUSTOMER FUNDING</span><h1><?php echo cpcc_ui_english_label('Additional funding required', 'المبلغ الإضافي المطلوب'); ?></h1><p><?php echo cpcc_ui_english_label('Obligations and available balances are shown separately for each currency.', 'تفصيل الالتزامات والرصيد المتاح لكل عملة دون خلط العملات.'); ?></p></div>
  </header>

  <?php if ($socid <= 0) { ?>
    <div class="warning"><?php echo cpcc_ui_english_label('No customer selected.', 'لا يوجد عميل محدد.'); ?></div>
  <?php } else { ?>
    <section class="cpcc-formula" aria-label="<?php echo cpcc_ui_english_label('Required funding calculation', 'معادلة التمويل المطلوب'); ?>">
      <b><?php echo cpcc_ui_english_label('Supplier remaining', 'المتبقي للموردين'); ?></b><span>+</span><b><?php echo cpcc_ui_english_label('Approved future services', 'الخدمات المستقبلية المعتمدة'); ?></b><span>+</span><b><?php echo cpcc_ui_english_label('Unpaid invoices', 'الفواتير غير المدفوعة'); ?></b><span>−</span><b><?php echo cpcc_ui_english_label('Available balance', 'الرصيد المتاح'); ?></b><span>=</span><strong><?php echo cpcc_ui_english_label('Required amount', 'المبلغ المطلوب'); ?></strong>
    </section>

    <div class="cpcc-balance-pills">
      <?php foreach ($summary as $code => $totals) {
          $url = cpcc_portal_scoped_url('/chinaplanetcustomercenter/funding.php', $isExternal, $socid, array('currency'=>$code)); ?>
        <a class="<?php echo $selectedCurrency === $code ? 'active' : ''; ?>" href="<?php echo dol_escape_htmltag($url); ?>"><b><?php echo dol_escape_htmltag($code); ?></b><span><?php echo cpcc_portal_money($totals['required_funding']); ?></span></a>
      <?php } ?>
      <a href="<?php echo dol_escape_htmltag(cpcc_portal_scoped_url('/chinaplanetcustomercenter/funding.php', $isExternal, $socid)); ?><?php echo cpcc_ui_english_label('">All currencies', '">كل العملات'); ?></a>
    </div>

    <?php foreach ($summary as $code => $totals) {
        if ($selectedCurrency !== '' && $selectedCurrency !== $code) continue; ?>
      <section class="cpcc-currency-block">
        <h3><?php echo dol_escape_htmltag($code); ?></h3>
        <div class="cpcc-financial-cards cpcc-funding-cards">
          <div class="cpcc-card obligations"><span><?php echo cpcc_ui_english_label('Supplier remaining', 'المتبقي للموردين'); ?></span><strong><?php echo cpcc_portal_money($totals['supplier_remaining']); ?> <em><?php echo dol_escape_htmltag($code); ?></em></strong><small>Supplier balances due</small></div>
          <div class="cpcc-card services"><span><?php echo cpcc_ui_english_label('Approved future services', 'خدمات مستقبلية معتمدة'); ?></span><strong><?php echo cpcc_portal_money($totals['future_services']); ?> <em><?php echo dol_escape_htmltag($code); ?></em></strong><small>Approved future services</small></div>
          <div class="cpcc-card invoices"><span><?php echo cpcc_ui_english_label('Unpaid invoices', 'فواتير غير مدفوعة'); ?></span><strong><?php echo cpcc_portal_money($totals['unpaid_invoices']); ?> <em><?php echo dol_escape_htmltag($code); ?></em></strong><small>Issued unpaid invoices</small></div>
          <div class="cpcc-card balance"><span><?php echo cpcc_ui_english_label('Available balance', 'الرصيد المتاح'); ?></span><strong><?php echo cpcc_portal_money($totals['available']); ?> <em><?php echo dol_escape_htmltag($code); ?></em></strong><small>Available customer funds</small></div>
          <div class="cpcc-card required"><span><?php echo cpcc_ui_english_label('Additional funding required', 'المبلغ الإضافي المطلوب'); ?></span><strong><?php echo cpcc_portal_money($totals['required_funding']); ?> <em><?php echo dol_escape_htmltag($code); ?></em></strong><small>Additional funds required</small></div>
        </div>
      </section>
    <?php } ?>

    <section class="cpcc-section-head"><div><h2><?php echo cpcc_ui_english_label('Remaining supplier balances', 'أرصدة الموردين المتبقية'); ?></h2><p><?php echo cpcc_ui_english_label('Product and supplier costs that have not been fully paid.', 'تكاليف المنتجات والموردين التي لم تُدفع بالكامل.'); ?></p></div></section>
    <div class="cpcc-table-wrap"><table class="cpcc-table"><thead><tr><th><?php echo cpcc_ui_english_label('Project', 'المشروع'); ?></th><th><?php echo cpcc_ui_english_label('Supplier / item', 'المورد / البند'); ?></th><th><?php echo cpcc_ui_english_label('Value', 'القيمة'); ?></th><th><?php echo cpcc_ui_english_label('Paid', 'المدفوع'); ?></th><th><?php echo cpcc_ui_english_label('Remaining', 'المتبقي'); ?></th><th><?php echo cpcc_ui_english_label('Currency', 'العملة'); ?></th></tr></thead><tbody>
    <?php if (empty($supplierRows)) { ?><tr><td colspan="6"><?php echo cpcc_ui_english_label('No matching supplier balances.', 'لا توجد أرصدة موردين مطابقة.'); ?></td></tr><?php } ?>
    <?php foreach ($supplierRows as $row) {
        $projectUrl = cpcc_portal_scoped_url('/chinaplanetcustomercenter/project.php', $isExternal, $socid, array('id'=>$row['project_id'])); ?>
      <tr><td><?php if ($row['project_id'] > 0) { ?><a href="<?php echo dol_escape_htmltag($projectUrl); ?>"><?php echo dol_escape_htmltag($row['project_ref'] ?: $row['project_title']); ?></a><?php } else { echo '—'; } ?></td><td><?php echo dol_escape_htmltag($row['supplier'] ?: $row['label']); ?><small><?php echo dol_escape_htmltag($row['reference']); ?></small></td><td><?php echo cpcc_portal_money($row['amount']); ?></td><td><?php echo cpcc_portal_money($row['paid']); ?></td><td><b><?php echo cpcc_portal_money($row['remaining']); ?></b></td><td><?php echo dol_escape_htmltag($row['currency']); ?></td></tr>
    <?php } ?>
    </tbody></table></div>

    <section class="cpcc-section-head"><div><h2><?php echo cpcc_ui_english_label('Approved future services', 'الخدمات المستقبلية المعتمدة'); ?></h2><p><?php echo cpcc_ui_english_label('Invoiced items are excluded here and appear only in the invoice table.', 'البنود المفوترة فعلياً مستبعدة هنا وتظهر في جدول الفواتير فقط.'); ?></p></div></section>
    <div class="cpcc-table-wrap"><table class="cpcc-table"><thead><tr><th><?php echo cpcc_ui_english_label('Project', 'المشروع'); ?></th><th><?php echo cpcc_ui_english_label('Service', 'الخدمة'); ?></th><th><?php echo cpcc_ui_english_label('Category', 'التصنيف'); ?></th><th><?php echo cpcc_ui_english_label('Value', 'القيمة'); ?></th><th><?php echo cpcc_ui_english_label('Allocated', 'المخصص'); ?></th><th><?php echo cpcc_ui_english_label('Remaining', 'المتبقي'); ?></th><th><?php echo cpcc_ui_english_label('Currency', 'العملة'); ?></th></tr></thead><tbody>
    <?php if (empty($serviceRows)) { ?><tr><td colspan="7"><?php echo cpcc_ui_english_label('No matching future services.', 'لا توجد خدمات مستقبلية مطابقة.'); ?></td></tr><?php } ?>
    <?php foreach ($serviceRows as $row) {
        $projectUrl = cpcc_portal_scoped_url('/chinaplanetcustomercenter/project.php', $isExternal, $socid, array('id'=>$row['project_id'])); ?>
      <tr><td><?php if ($row['project_id'] > 0) { ?><a href="<?php echo dol_escape_htmltag($projectUrl); ?>"><?php echo dol_escape_htmltag($row['project_ref'] ?: $row['project_title']); ?></a><?php } else { echo '—'; } ?></td><td><?php echo dol_escape_htmltag($row['label']); ?></td><td><?php echo dol_escape_htmltag($row['category']); ?></td><td><?php echo cpcc_portal_money($row['amount']); ?></td><td><?php echo cpcc_portal_money($row['paid']); ?></td><td><b><?php echo cpcc_portal_money($row['remaining']); ?></b></td><td><?php echo dol_escape_htmltag($row['currency']); ?></td></tr>
    <?php } ?>
    </tbody></table></div>

    <section class="cpcc-section-head"><div><h2><?php echo cpcc_ui_english_label('Unpaid Vision invoices', 'فواتير Vision غير المدفوعة'); ?></h2><p><?php echo cpcc_ui_english_label('Validated Vision System invoices only; drafts are excluded.', 'فواتير Vision System المعتمدة فقط؛ الفواتير المسودة غير محسوبة.'); ?></p></div></section>
    <div class="cpcc-table-wrap"><table class="cpcc-table"><thead><tr><th><?php echo cpcc_ui_english_label('Invoice', 'الفاتورة'); ?></th><th><?php echo cpcc_ui_english_label('Project', 'المشروع'); ?></th><th><?php echo cpcc_ui_english_label('Date', 'التاريخ'); ?></th><th><?php echo cpcc_ui_english_label('Due date', 'الاستحقاق'); ?></th><th><?php echo cpcc_ui_english_label('Total', 'الإجمالي'); ?></th><th><?php echo cpcc_ui_english_label('Paid', 'المسدد'); ?></th><th><?php echo cpcc_ui_english_label('Remaining', 'المتبقي'); ?></th><th><?php echo cpcc_ui_english_label('Currency', 'العملة'); ?></th></tr></thead><tbody>
    <?php if (empty($invoices)) { ?><tr><td colspan="8"><?php echo cpcc_ui_english_label('No unpaid validated invoices.', 'لا توجد فواتير معتمدة غير مدفوعة.'); ?></td></tr><?php } ?>
    <?php foreach ($invoices as $invoice) { ?>
      <tr id="invoice-<?php echo (int) $invoice['id']; ?>"><td><b><?php echo dol_escape_htmltag($invoice['ref']); ?></b></td><td><?php echo dol_escape_htmltag($invoice['project_ref'] ?: '—'); ?></td><td><?php echo dol_escape_htmltag(dol_print_date($invoice['date'], 'day')); ?></td><td><?php echo $invoice['due_date'] !== '' ? dol_escape_htmltag(dol_print_date($invoice['due_date'], 'day')) : '—'; ?></td><td><?php echo cpcc_portal_money($invoice['total']); ?></td><td><?php echo cpcc_portal_money($invoice['paid']); ?></td><td><b><?php echo cpcc_portal_money($invoice['remaining']); ?></b></td><td><?php echo dol_escape_htmltag($invoice['currency']); ?></td></tr>
    <?php } ?>
    </tbody></table></div>
  <?php } ?>
</main>
<?php if(!$isExternal)cpcc_admin_shell_close();llxFooter();
