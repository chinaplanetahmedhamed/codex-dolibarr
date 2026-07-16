<?php
if (!isset($_SERVER['QUERY_STRING'])) $_SERVER['QUERY_STRING'] = '';
require_once __DIR__.'/lib/main.lib.php';
require_once __DIR__.'/lib/admin_shell.lib.php';
require_once __DIR__.'/lib/portal.lib.php';
require_once __DIR__.'/lib/financial.lib.php';
require_once __DIR__.'/lib/export.lib.php';
require_once __DIR__.'/lib/operation_reports.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';

cpcc_portal_require_access($user);
$scope = cpcc_portal_user_scope($user);
$is_external = !empty($scope['is_external']);
$socid = cpcc_portal_customer_scope_id($db, $user, GETPOSTINT('socid'));
$currencyFallback = cpcc_financial_currency(!empty($conf->currency) ? $conf->currency : 'USD');
$customerName = $socid > 0 ? cpcc_portal_customer_name($db, $socid) : '';
$summary = $socid > 0 ? cpcc_financial_summary($db, $socid, $currencyFallback) : array();
$internalQuery = !$is_external && $socid > 0 ? 'socid='.$socid.'&' : '';
$activeShipmentsUrl = cpcc_portal_scoped_url('/chinaplanetlogistics/list.php', $is_external, $socid, array('status'=>'active'));
$allOrdersUrl = cpcc_portal_scoped_url('/chinaplanetcustomercenter/orders.php', $is_external, $socid, array('status'=>'all'));
$activeProjectsUrl = cpcc_portal_scoped_url('/chinaplanetcustomercenter/projects.php', $is_external, $socid, array('status'=>'active'));
$invoicesUrl = cpcc_portal_scoped_url('/chinaplanetcustomercenter/invoices.php', $is_external, $socid);
$exportDocumentsUrl = cpcc_portal_scoped_url('/chinaplanetcustomercenter/commercial_documents.php', $is_external, $socid);
$customerOptions = array();
if (!$is_external) {
    $resql = $db->query("SELECT rowid,nom FROM ".MAIN_DB_PREFIX."societe WHERE entity=".(int) $conf->entity." AND status=1 AND client IN (1,3) ORDER BY nom");
    if ($resql) while ($obj = $db->fetch_object($resql)) $customerOptions[(int) $obj->rowid] = (string) $obj->nom;
}

$shipActive = 0;
$ordersActive = 0;
$ordersCompleted = 0;
$ordersTotal = 0;
$projectsActive = 0;
$projectsTotal = 0;
$invoiceTotal = 0;
$exportDocumentTotal = 0;
$operationReportTotal = 0;
if ($socid > 0) {
    $resql = $db->query("SELECT COUNT(*) nb FROM ".MAIN_DB_PREFIX."chinaplanetlogistics WHERE entity=".(int)$conf->entity." AND fk_soc=".$socid." AND fk_statut<4");
    if ($resql && ($obj=$db->fetch_object($resql))) $shipActive=(int)$obj->nb;
    $resql = $db->query("SELECT COUNT(*) nb FROM ".MAIN_DB_PREFIX."commande WHERE entity IN (".getEntity('commande').") AND fk_soc=".$socid." AND fk_statut>=3");
    if ($resql && ($obj=$db->fetch_object($resql))) $ordersCompleted=(int)$obj->nb;
	$resql = $db->query("SELECT COUNT(*) nb FROM ".MAIN_DB_PREFIX."commande WHERE entity IN (".getEntity('commande').") AND fk_soc=".$socid." AND fk_statut IN (1,2)");
	if ($resql && ($obj=$db->fetch_object($resql))) $ordersActive=(int)$obj->nb;
	$resql = $db->query("SELECT COUNT(*) nb FROM ".MAIN_DB_PREFIX."commande WHERE entity IN (".getEntity('commande').") AND fk_soc=".$socid);
	if ($resql && ($obj=$db->fetch_object($resql))) $ordersTotal=(int)$obj->nb;
    $resql = $db->query("SELECT COUNT(*) nb FROM ".MAIN_DB_PREFIX."projet WHERE entity IN (".getEntity('project').") AND fk_soc=".$socid." AND fk_statut=1");
    if ($resql && ($obj=$db->fetch_object($resql))) $projectsActive=(int)$obj->nb;
    $resql = $db->query("SELECT COUNT(*) nb FROM ".MAIN_DB_PREFIX."projet WHERE entity IN (".getEntity('project').") AND fk_soc=".$socid);
    if ($resql && ($obj=$db->fetch_object($resql))) $projectsTotal=(int)$obj->nb;
    $resql = $db->query("SELECT COUNT(*) nb FROM ".MAIN_DB_PREFIX."facture WHERE entity IN (".getEntity('invoice').") AND fk_soc=".$socid." AND fk_statut>0");
    if ($resql && ($obj=$db->fetch_object($resql))) $invoiceTotal=(int)$obj->nb;
    if (cpcc_export_schema_ready($db)) {
        $resql = $db->query("SELECT COUNT(*) nb FROM ".MAIN_DB_PREFIX."cpcc_export_document WHERE entity=".(int)$conf->entity." AND fk_soc=".$socid." AND status=1 AND customer_visible=1");
        if ($resql && ($obj=$db->fetch_object($resql))) $exportDocumentTotal=(int)$obj->nb;
    }
    if (cpcc_operation_reports_schema_ready($db)) {
        $resql = $db->query("SELECT COUNT(*) nb FROM ".MAIN_DB_PREFIX."cpo_operation WHERE entity=".(int)$conf->entity." AND fk_soc=".$socid." AND status=1 AND customer_visible=1");
        if ($resql && ($obj=$db->fetch_object($resql))) $operationReportTotal=(int)$obj->nb;
    }
}

$boardCss = array('/custom/chinaplanetcustomercenter/css/customer_dashboard.css', '/custom/chinaplanetcustomercenter/css/funding.css', cpcc_admin_shell_asset('/custom/chinaplanetcustomercenter/css/ux_fixes.css'));
if (!$is_external) $boardCss[] = cpcc_admin_shell_asset('/custom/chinaplanetcustomercenter/css/admin_dashboard.css');
llxHeader('', 'ChinaPlanet Customer Center', '', '', 0, 0, array(), $boardCss);
if ($is_external) cpcc_portal_navigation($user, 'dashboard');
else cpcc_admin_shell_open('portal', 'Portal Home / بوابة العميل', 'معاينة آمنة لما يراه العميل من رصيد ومشاريع وشحنات', 'fa-th-large', array('socid'=>$socid));
?>
<main class="cpcc-dashboard" dir="rtl">
  <header class="cpcc-hero">
    <div><span class="cpcc-eyebrow">CHINAPLANET CUSTOMER CENTER</span><h1>مرحبًا <?php echo dol_escape_htmltag($customerName ?: 'بك'); ?></h1><p>العمليات والرصيد والمشاريع والشحنات في مكان واحد.</p></div>
    <?php if (!$is_external) { ?>
      <form method="get" class="cpcc-customer-filter">
        <label><span>اختر العميل / Customer</span><?php echo Form::selectarray('socid', $customerOptions, $socid, 'ابحث باسم العميل', 0, 0, '', 0, 0, 0, '', 'minwidth300 cpcc-customer-search', 1); ?></label>
        <button type="submit">عرض</button>
      </form>
    <?php } ?>
  </header>

  <?php if ($is_external && $socid <= 0) { ?>
    <div class="warning">لا يوجد حساب عميل مرتبط بهذا المستخدم.</div>
  <?php } elseif ($socid <= 0) { ?>
    <div class="info">ابحث باسم العميل واختره لعرض لوحة الحساب.</div>
  <?php } else { ?>
    <?php if (!cpcc_financial_schema_ready($db)) { ?><div class="warning">الطبقة المالية لم تُفعّل بعد. بيانات Vision System الأساسية ستظل متاحة دون تغيير.</div><?php } ?>

    <section class="cpcc-operational-cards" aria-label="Operational summary">
      <a href="<?php echo dol_escape_htmltag($activeShipmentsUrl); ?>" class="cpcc-card"><span>الشحنات النشطة</span><strong><?php echo $shipActive; ?></strong><small>Active shipments</small></a>
      <a href="<?php echo dol_escape_htmltag($allOrdersUrl); ?>" class="cpcc-card"><span>الطلبات / Orders</span><strong><?php echo $ordersTotal; ?> <em>الكل</em></strong><small><?php echo $ordersActive; ?> Active · <?php echo $ordersCompleted; ?> Completed · <?php echo $ordersTotal; ?> Total</small></a>
      <a href="<?php echo dol_escape_htmltag($activeProjectsUrl); ?>" class="cpcc-card"><span>المشاريع / Projects</span><strong><?php echo $projectsActive; ?> <em>نشطة</em></strong><small><?php echo $projectsActive; ?> Active · <?php echo $projectsTotal; ?> Total</small></a>
      <a href="<?php echo dol_escape_htmltag($invoicesUrl); ?>" class="cpcc-card"><span>فواتير خدمات Vision</span><strong><?php echo $invoiceTotal; ?></strong><small>Service invoices</small></a>
      <a href="<?php echo dol_escape_htmltag($exportDocumentsUrl); ?>" class="cpcc-card"><span>مستندات التصدير</span><strong><?php echo $exportDocumentTotal; ?></strong><small>Commercial invoices &amp; packing lists</small></a>
      <a href="<?php echo dol_escape_htmltag(cpcc_portal_scoped_url('/chinaplanetcustomercenter/operation_reports.php',$is_external,$socid)); ?>" class="cpcc-card"><span>تقارير الخدمات</span><strong><?php echo $operationReportTotal; ?></strong><small>QC &amp; factory audit reports</small></a>
    </section>

    <section class="cpcc-section-head"><div><h2>الحساب حسب العملة</h2><p>لا يتم جمع العملات المختلفة دون تحويل مسجل.</p></div><a href="<?php echo dol_buildpath('/chinaplanetcustomercenter/balance.php',1).($internalQuery!==''?'?socid='.$socid:''); ?>">فتح كشف الحساب ←</a></section>
    <?php if (empty($summary)) { ?><div class="cpcc-empty">لا توجد حركات مالية مسجلة لهذا العميل حتى الآن.</div><?php } ?>
    <?php foreach ($summary as $currency=>$totals) { $currencyFundingUrl=cpcc_portal_scoped_url('/chinaplanetcustomercenter/funding.php',$is_external,$socid,array('currency'=>$currency)); ?>
      <section class="cpcc-currency-block">
        <h3><?php echo dol_escape_htmltag($currency); ?></h3>
        <div class="cpcc-financial-cards">
          <a href="<?php echo dol_buildpath('/chinaplanetcustomercenter/balance.php',1).'?'.$internalQuery.'currency='.urlencode($currency); ?>" class="cpcc-card balance"><span>الرصيد المتاح</span><strong><?php echo cpcc_portal_money($totals['available']); ?> <em><?php echo dol_escape_htmltag($currency); ?></em></strong><small>Available balance</small></a>
          <a href="<?php echo dol_buildpath('/chinaplanetcustomercenter/balance.php',1).'?'.$internalQuery.'currency='.urlencode($currency).'&view=received'; ?>" class="cpcc-card received"><span>إجمالي المستلم</span><strong><?php echo cpcc_portal_money($totals['received']); ?> <em><?php echo dol_escape_htmltag($currency); ?></em></strong><small>Total received</small></a>
          <a href="<?php echo dol_buildpath('/chinaplanetcustomercenter/balance.php',1).'?'.$internalQuery.'currency='.urlencode($currency).'&view=spent'; ?>" class="cpcc-card spent"><span>إجمالي المصروف</span><strong><?php echo cpcc_portal_money($totals['spent']); ?> <em><?php echo dol_escape_htmltag($currency); ?></em></strong><small>Total spent</small></a>
          <a href="<?php echo dol_escape_htmltag($currencyFundingUrl); ?>" class="cpcc-card obligations"><span>إجمالي الالتزامات</span><strong><?php echo cpcc_portal_money($totals['obligations']); ?> <em><?php echo dol_escape_htmltag($currency); ?></em></strong><small>Suppliers + services + unpaid invoices</small></a>
          <a href="<?php echo dol_escape_htmltag($currencyFundingUrl); ?>" class="cpcc-card required"><span>المبلغ الإضافي المطلوب</span><strong><?php echo cpcc_portal_money($totals['required_funding']); ?> <em><?php echo dol_escape_htmltag($currency); ?></em></strong><small>Additional funds required</small></a>
        </div>
      </section>
    <?php } ?>

    <section class="cpcc-section-head"><div><h2>آخر الحركات / Latest Transactions</h2><p>أحدث الإيداعات والمصروفات والتحويلات.</p></div><a class="cpcc-statement-button" href="<?php echo dol_buildpath('/chinaplanetcustomercenter/balance.php',1).($internalQuery!==''?'?socid='.$socid:''); ?>">كشف حساب العميل / Customer Statement</a></section>
    <?php $statement=array_reverse(array_slice(cpcc_financial_statement($db,$socid,$currencyFallback),-8)); ?>
    <div class="cpcc-table-wrap"><table class="cpcc-table"><thead><tr><th>التاريخ / Date</th><th>النوع / Type</th><th>الوصف / Description</th><th>مدين / Debit</th><th>دائن / Credit</th><th>الرصيد / Balance</th></tr></thead><tbody>
    <?php if (empty($statement)) { ?><tr><td colspan="6">لا توجد حركات.</td></tr><?php } ?>
    <?php foreach ($statement as $row) { ?><tr><td><?php echo dol_escape_htmltag(dol_print_date($row['date'],'day')); ?></td><td><?php echo dol_escape_htmltag(cpcc_financial_type_label($row['type'])); ?></td><td><?php echo dol_escape_htmltag($row['description']); ?></td><td><?php echo $row['direction']==='D'?cpcc_portal_money($row['amount']).' '.dol_escape_htmltag($row['currency']):'—'; ?></td><td><?php echo $row['direction']==='C'?cpcc_portal_money($row['amount']).' '.dol_escape_htmltag($row['currency']):'—'; ?></td><td><?php echo cpcc_portal_money($row['balance']).' '.dol_escape_htmltag($row['currency']); ?></td></tr><?php } ?>
    </tbody></table></div>
  <?php } ?>
</main>
<?php if (!$is_external) cpcc_admin_shell_close(); llxFooter();
