<?php
if (!isset($_SERVER['QUERY_STRING'])) $_SERVER['QUERY_STRING'] = '';
require_once __DIR__.'/lib/main.lib.php';
require_once __DIR__.'/lib/admin_shell.lib.php';
require_once __DIR__.'/lib/portal.lib.php';
require_once __DIR__.'/lib/export.lib.php';
require_once __DIR__.'/lib/financial.lib.php';
require_once __DIR__.'/lib/operation_reports.lib.php';
require_once __DIR__.'/../chinaplanetlogistics/lib/chinaplanetlogistics.lib.php';
require_once __DIR__.'/../chinaplanetoperations/lib/main.lib.php';
require_once __DIR__.'/../chinaplanetoperations/class/OperationsService.class.php';
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
$documentsUrl = cpcc_portal_scoped_url('/chinaplanetcustomercenter/documents.php', $is_external, $socid);
$customerOptions = array();
if (!$is_external) {
    $resql = $db->query("SELECT rowid,nom FROM ".MAIN_DB_PREFIX."societe WHERE entity=".(int) $conf->entity." AND status=1 AND client IN (1,3) ORDER BY nom");
    if ($resql) while ($obj = $db->fetch_object($resql)) $customerOptions[(int) $obj->rowid] = (string) $obj->nom;
}

$centerOverview = array('customers_total'=>0, 'customers_active'=>0, 'orders_active'=>0, 'shipments_active'=>0, 'shipments_critical'=>0);
$centerFinance = array();
if (!$is_external && $socid <= 0) {
    $boardCount = function ($sql) use ($db) {
        $resql = $db->query($sql);
        if ($resql && ($obj = $db->fetch_object($resql))) return (int) $obj->nb;
        return 0;
    };
    $entity = (int) $conf->entity;
    $centerOverview['customers_total'] = $boardCount("SELECT COUNT(*) nb FROM ".MAIN_DB_PREFIX."societe WHERE entity=".$entity." AND client IN (1,3)");
    $centerOverview['customers_active'] = $boardCount("SELECT COUNT(*) nb FROM ".MAIN_DB_PREFIX."societe WHERE entity=".$entity." AND status=1 AND client IN (1,3)");
    $centerOverview['orders_active'] = $boardCount("SELECT COUNT(*) nb FROM ".MAIN_DB_PREFIX."commande WHERE entity IN (".getEntity('commande').") AND fk_statut IN (1,2)");
    $centerOverview['shipments_active'] = $boardCount("SELECT COUNT(*) nb FROM ".MAIN_DB_PREFIX."chinaplanetlogistics WHERE entity=".$entity." AND fk_statut IN (".chinaplanetlogistics_status_sql_list('active').")");
    $criticalUntil = date('Y-m-d', strtotime('+7 days'));
    $centerOverview['shipments_critical'] = $boardCount("SELECT COUNT(*) nb FROM ".MAIN_DB_PREFIX."chinaplanetlogistics WHERE entity=".$entity." AND fk_statut IN (".chinaplanetlogistics_status_sql_list('active').") AND ((freetime_end IS NOT NULL AND freetime_end<='".$db->escape($criticalUntil)."') OR (date_eta IS NOT NULL AND date_eta<'".$db->escape(date('Y-m-d'))."'))");

    foreach (array_keys($customerOptions) as $overviewSocid) {
        foreach (cpcc_financial_summary($db, (int) $overviewSocid, $currencyFallback) as $currency=>$totals) {
            if (!isset($centerFinance[$currency])) $centerFinance[$currency] = cpcc_financial_empty_summary();
            foreach (array_keys($centerFinance[$currency]) as $metric) {
                $centerFinance[$currency][$metric] += isset($totals[$metric]) ? (float) $totals[$metric] : 0.0;
            }
        }
    }
    ksort($centerFinance);
}

$shipActive = 0;
$ordersActive = 0;
$ordersCompleted = 0;
$ordersTotal = 0;
$projectsActive = 0;
$projectsTotal = 0;
$invoiceTotal = 0;
$documentTotal = 0;
$operationReportTotal = 0;
$canViewShippingOrders = !$is_external && cpo_can($user, 'read');
$shippingOrderStats = array('total_current'=>0,'active'=>0,'confirmed'=>0,'closed'=>0,'cancelled'=>0);
$shippingOrderLabels = array('active'=>'نشط / Active','confirmed'=>'مؤكد / تم استلام SO / Confirmed','closed'=>'مغلق / Closed','cancelled'=>'ملغي / Cancelled');
$recentShippingOrders = array();
if ($socid > 0) {
    $resql = $db->query("SELECT COUNT(*) nb FROM ".MAIN_DB_PREFIX."chinaplanetlogistics WHERE entity=".(int)$conf->entity." AND fk_soc=".$socid." AND fk_statut IN (".chinaplanetlogistics_status_sql_list('active').")");
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
    $documentTotal = cpcc_portal_customer_document_count($db, $user, $socid);
    if (cpcc_operation_reports_schema_ready($db)) {
        // Reuse the publication-gated projection so draft QC operations never
        // inflate the customer-facing approved report count.
        $operationReportTotal = count(cpcc_operation_reports($db, $socid));
    }
    if ($canViewShippingOrders) {
        $shippingOperations = new OperationsService($db);
        $shippingOrderStats = $shippingOperations->shippingOrderStats($socid, true);
        $recentShippingOrders = $shippingOperations->searchShippingOrders(array('socid'=>$socid, 'customer_projection'=>true, 'limit'=>5));
    }
}

$boardCss = array('/custom/chinaplanetcustomercenter/css/customer_dashboard.css', '/custom/chinaplanetcustomercenter/css/funding.css', cpcc_admin_shell_asset('/custom/chinaplanetcustomercenter/css/ux_fixes.css'));
if (!$is_external) $boardCss[] = cpcc_admin_shell_asset('/custom/chinaplanetcustomercenter/css/admin_dashboard.css');
llxHeader('', 'ChinaPlanet Customer Center', '', '', 0, 0, array(), $boardCss);
if ($is_external) cpcc_portal_navigation($user, 'dashboard');
else cpcc_admin_shell_open('portal', 'Customer Center / مركز العملاء', 'ملخص جميع العملاء أو عرض آمن ومفصل لعميل محدد', 'fa-users', array('socid'=>$socid));
?>
<main class="cpcc-dashboard" dir="rtl">
  <header class="cpcc-hero">
    <div><span class="cpcc-eyebrow">CHINAPLANET CUSTOMER CENTER</span><?php if (!$is_external && $socid <= 0) { ?><h1>مركز العملاء / Customer Center</h1><p>ملخص تشغيلي لجميع العملاء، مع إمكانية فتح تفاصيل كل عميل.</p><?php } else { ?><h1>مرحبًا <?php echo dol_escape_htmltag($customerName ?: 'بك'); ?></h1><p>العمليات والرصيد والمشاريع والشحنات في مكان واحد.</p><?php } ?></div>
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
    <section class="cpcc-admin-overview" aria-labelledby="customer-center-overview-title">
      <div class="cpcc-admin-overview-head"><div><span>ALL CUSTOMERS</span><h2 id="customer-center-overview-title">ملخص مركز العملاء / Customer Center Overview</h2><p>صورة تشغيلية لجميع العملاء داخل الكيان الحالي. اختر عميلًا أعلاه لفتح تفاصيله.</p></div></div>
      <div class="cpcc-admin-overview-grid">
        <a href="<?php echo dol_buildpath('/societe/list.php',1).'?type=c'; ?>"><span class="fas fa-building"></span><strong><?php echo $centerOverview['customers_total']; ?></strong><b>إجمالي العملاء / Total customers</b><small><?php echo $centerOverview['customers_active']; ?> active</small></a>
        <a href="<?php echo dol_buildpath('/commande/list.php',1).'?search_status=1'; ?>"><span class="fas fa-shopping-cart"></span><strong><?php echo $centerOverview['orders_active']; ?></strong><b>الطلبات النشطة / Active orders</b><small>Validated and in progress</small></a>
        <a href="<?php echo dol_buildpath('/chinaplanetlogistics/list.php',1).'?status=active'; ?>"><span class="fas fa-ship"></span><strong><?php echo $centerOverview['shipments_active']; ?></strong><b>الشحنات النشطة / Active shipments</b><small>Across all customers</small></a>
        <a class="is-attention" href="<?php echo dol_buildpath('/chinaplanetlogistics/list.php',1).'?status=critical'; ?>"><span class="fas fa-exclamation-triangle"></span><strong><?php echo $centerOverview['shipments_critical']; ?></strong><b>تحتاج متابعة / Need attention</b><small>ETA overdue or free time ≤ 7 days</small></a>
      </div>
      <div class="cpcc-admin-finance-overview">
        <div class="cpcc-admin-finance-title"><div><span class="fas fa-wallet"></span><h3>الوضع المالي حسب العملة / Finance by currency</h3></div><a href="<?php echo dol_buildpath('/chinaplanetcustomercenter/admin/financial_portfolio.php',1); ?>">فتح المركز المالي / Open Financial Center</a></div>
        <?php if (empty($centerFinance)) { ?><div class="cpcc-admin-overview-empty">لا توجد حركات مالية مسجلة للعملاء حتى الآن.</div><?php } ?>
        <div class="cpcc-admin-finance-grid">
          <?php foreach ($centerFinance as $currency=>$totals) { ?>
            <a href="<?php echo dol_buildpath('/chinaplanetcustomercenter/admin/financial_portfolio.php',1).'?currency='.urlencode($currency); ?>">
              <div><strong><?php echo dol_escape_htmltag($currency); ?></strong><small>Original currency only</small></div>
              <dl><div><dt>المستلم / Received</dt><dd><?php echo cpcc_portal_money($totals['received']); ?></dd></div><div><dt>المصروف / Spent</dt><dd><?php echo cpcc_portal_money($totals['spent']); ?></dd></div><div><dt>المتاح / Available</dt><dd><?php echo cpcc_portal_money($totals['available']); ?></dd></div><div><dt>المطلوب / Required</dt><dd><?php echo cpcc_portal_money($totals['required_funding']); ?></dd></div></dl>
            </a>
          <?php } ?>
        </div>
      </div>
    </section>
  <?php } else { ?>
    <?php if (!cpcc_financial_schema_ready($db)) { ?><div class="warning">الطبقة المالية لم تُفعّل بعد. بيانات Vision System الأساسية ستظل متاحة دون تغيير.</div><?php } ?>

    <section class="cpcc-operational-cards" aria-label="Operational summary">
      <a href="<?php echo dol_escape_htmltag($activeShipmentsUrl); ?>" class="cpcc-card"><span>الشحنات النشطة</span><strong><?php echo $shipActive; ?></strong><small>Active shipments</small></a>
      <a href="<?php echo dol_escape_htmltag($allOrdersUrl); ?>" class="cpcc-card"><span>الطلبات / Orders</span><strong><?php echo $ordersTotal; ?> <em>الكل</em></strong><small><?php echo $ordersActive; ?> Active · <?php echo $ordersCompleted; ?> Completed · <?php echo $ordersTotal; ?> Total</small></a>
      <a href="<?php echo dol_escape_htmltag($activeProjectsUrl); ?>" class="cpcc-card"><span>المشاريع / Projects</span><strong><?php echo $projectsActive; ?> <em>نشطة</em></strong><small><?php echo $projectsActive; ?> Active · <?php echo $projectsTotal; ?> Total</small></a>
      <a href="<?php echo dol_escape_htmltag($invoicesUrl); ?>" class="cpcc-card"><span>الفواتير / Invoices</span><strong><?php echo $invoiceTotal; ?></strong><small>Approved customer invoices</small></a>
      <a href="<?php echo dol_escape_htmltag($documentsUrl); ?>" class="cpcc-card"><span>المستندات / Documents</span><strong><?php echo $documentTotal; ?></strong><small>Published customer documents</small></a>
      <a href="<?php echo dol_escape_htmltag(cpcc_portal_scoped_url('/chinaplanetcustomercenter/operation_reports.php',$is_external,$socid)); ?>" class="cpcc-card"><span>التقارير / Reports</span><strong><?php echo $operationReportTotal; ?></strong><small>Approved customer reports</small></a>
      <?php if ($canViewShippingOrders) { ?><a href="<?php echo dol_buildpath('/chinaplanetoperations/operations/shipping_orders.php',1).'?'.http_build_query(array('socid'=>$socid)); ?>" class="cpcc-card shipping-orders"><span>طلبات الشحن / Shipping Orders</span><strong><?php echo (int) $shippingOrderStats['total_current']; ?> <em>الحالي</em></strong><small><?php echo (int) $shippingOrderStats['active']; ?> Active · <?php echo (int) $shippingOrderStats['confirmed']; ?> Confirmed · <?php echo (int) $shippingOrderStats['closed']; ?> Closed · <?php echo (int) $shippingOrderStats['cancelled']; ?> Cancelled</small></a><?php } ?>
    </section>

    <?php if ($canViewShippingOrders) { ?>
    <section class="cpcc-section-head"><div><h2>طلبات الشحن / Shipping Orders</h2><p>أحدث طلبات الحجز الخاصة بهذا العميل؛ منفصلة عن طلبات البيع والشحنات الفعلية.</p></div><a href="<?php echo dol_buildpath('/chinaplanetoperations/operations/shipping_orders.php',1).'?'.http_build_query(array('socid'=>$socid)); ?>">عرض كل طلبات الشحن ←</a></section>
    <?php if (empty($recentShippingOrders)) { ?><div class="cpcc-empty">لا توجد طلبات شحن لهذا العميل / No shipping orders for this customer.</div><?php } else { ?><div class="cpcc-shipping-orders" role="list">
      <?php foreach ($recentShippingOrders as $shippingOrder) { $route=trim(($shippingOrder['route']['pol']??'').' → '.($shippingOrder['route']['pod']??''), ' →'); ?>
      <article role="listitem"><div><a href="<?php echo dol_buildpath('/chinaplanetoperations/operations/view.php?id='.(int)$shippingOrder['operation_id'],1); ?>"><bdi dir="ltr"><?php echo dol_escape_htmltag($shippingOrder['reference']); ?></bdi></a><small dir="auto"><?php echo dol_escape_htmltag($shippingOrder['next_action'] ?: '—'); ?></small></div><dl><div><dt>الحالة / Status</dt><dd><?php echo dol_escape_htmltag($shippingOrderLabels[$shippingOrder['lifecycle_bucket']] ?? $shippingOrder['lifecycle_bucket']); ?></dd></div><div><dt>المسار / Route</dt><dd><bdi dir="ltr"><?php echo dol_escape_htmltag($route ?: 'Not captured'); ?></bdi></dd></div><div><dt>المعدات / Equipment</dt><dd><bdi dir="ltr"><?php echo dol_escape_htmltag($shippingOrder['equipment'] ? $shippingOrder['equipment'].' × '.(int)$shippingOrder['quantity'] : 'Not captured'); ?></bdi></dd></div><div><dt>السعر المقبول / Accepted</dt><dd><bdi dir="ltr"><?php echo $shippingOrder['accepted_total'] !== null ? dol_escape_htmltag(number_format((float)$shippingOrder['accepted_total'],2).' '.$shippingOrder['currency']) : 'Not captured'; ?></bdi></dd></div></dl></article>
      <?php } ?>
    </div><?php } ?>
    <?php } ?>

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
    <?php foreach ($statement as $row) { ?><tr><td><?php echo dol_escape_htmltag(cpcc_portal_date($row['date'])); ?></td><td><?php echo dol_escape_htmltag(cpcc_financial_type_label($row['type'])); ?></td><td><?php echo dol_escape_htmltag($row['description']); ?></td><td><?php echo $row['direction']==='D'?cpcc_portal_money($row['amount']).' '.dol_escape_htmltag($row['currency']):'—'; ?></td><td><?php echo $row['direction']==='C'?cpcc_portal_money($row['amount']).' '.dol_escape_htmltag($row['currency']):'—'; ?></td><td><?php echo cpcc_portal_money($row['balance']).' '.dol_escape_htmltag($row['currency']); ?></td></tr><?php } ?>
    </tbody></table></div>
  <?php } ?>
</main>
<?php if (!$is_external) cpcc_admin_shell_close(); llxFooter();
