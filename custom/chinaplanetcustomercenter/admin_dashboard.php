<?php
if (!isset($_SERVER['QUERY_STRING'])) $_SERVER['QUERY_STRING'] = '';
require_once __DIR__.'/lib/main.lib.php';
require_once __DIR__.'/lib/admin_shell.lib.php';
require_once __DIR__.'/lib/financial.lib.php';
require_once __DIR__.'/../chinaplanetlogistics/lib/chinaplanetlogistics.lib.php';
require_once __DIR__.'/../chinaplanetoperations/class/OperationsService.class.php';

if (empty($user->id) || empty($user->admin) || !empty($user->socid)) {
    accessforbidden('Administrator access required.');
}

$entity = (int) $conf->entity;
$currency = !empty($conf->currency) ? $conf->currency : 'USD';
$year = (int) date('Y');

function cpadScalar($db, $sql, $field = 'value')
{
    $resql = $db->query($sql);
    if (!$resql) return 0;
    $obj = $db->fetch_object($resql);
    return $obj && isset($obj->{$field}) ? $obj->{$field} : 0;
}

function cpadMoney($value, $currency)
{
    return number_format((float) $value, 0, '.', ',').' '.$currency;
}

function cpadPct($value, $max)
{
    if ($max <= 0) return 0;
    return max(3, min(100, round(((float) $value / (float) $max) * 100)));
}

function cpadTableExists($db, $table)
{
    static $cache = array();
    if (isset($cache[$table])) return $cache[$table];
    $resql = $db->query("SHOW TABLES LIKE '".$db->escape($table)."'");
    $cache[$table] = $resql && $db->num_rows($resql) > 0;
    return $cache[$table];
}

function cpadAddCurrencySummary(&$target, $source)
{
    foreach ($source as $code=>$values) {
        if (!isset($target[$code])) $target[$code] = cpcc_financial_empty_summary();
        foreach (array_keys(cpcc_financial_empty_summary()) as $field) {
            $target[$code][$field] += isset($values[$field]) ? (float) $values[$field] : 0.0;
        }
    }
}

$customers = (int) cpadScalar($db, "SELECT COUNT(*) value FROM ".MAIN_DB_PREFIX."societe WHERE entity IN (0,".$entity.") AND client IN (1,2,3)");
$activeProjects = (int) cpadScalar($db, "SELECT COUNT(*) value FROM ".MAIN_DB_PREFIX."projet WHERE entity = ".$entity." AND fk_statut = 1");
$yearOrders = (int) cpadScalar($db, "SELECT COUNT(*) value FROM ".MAIN_DB_PREFIX."commande WHERE entity = ".$entity." AND YEAR(date_commande) = ".$year." AND fk_statut > 0");
$activeOrders = (int) cpadScalar($db, "SELECT COUNT(*) value FROM ".MAIN_DB_PREFIX."commande WHERE entity = ".$entity." AND fk_statut IN (1,2)");
$completedOrders = (int) cpadScalar($db, "SELECT COUNT(*) value FROM ".MAIN_DB_PREFIX."commande WHERE entity = ".$entity." AND fk_statut = 3");
$allOrders = (int) cpadScalar($db, "SELECT COUNT(*) value FROM ".MAIN_DB_PREFIX."commande WHERE entity = ".$entity);
$yearRevenue = (float) cpadScalar($db, "SELECT COALESCE(SUM(total_ttc),0) value FROM ".MAIN_DB_PREFIX."facture WHERE entity = ".$entity." AND YEAR(datef) = ".$year." AND fk_statut > 0");
$unpaid = (float) cpadScalar($db, "SELECT COALESCE(SUM(GREATEST(f.total_ttc-COALESCE(p.paid,0),0)),0) value FROM ".MAIN_DB_PREFIX."facture f LEFT JOIN (SELECT fk_facture,SUM(amount) paid FROM ".MAIN_DB_PREFIX."paiement_facture GROUP BY fk_facture) p ON p.fk_facture=f.rowid WHERE f.entity = ".$entity." AND f.fk_statut = 1 AND f.paye = 0");
$unpaidCount = (int) cpadScalar($db, "SELECT COUNT(*) value FROM ".MAIN_DB_PREFIX."facture WHERE entity = ".$entity." AND fk_statut=1 AND paye=0");
$overdueCount = (int) cpadScalar($db, "SELECT COUNT(*) value FROM ".MAIN_DB_PREFIX."facture WHERE entity = ".$entity." AND fk_statut=1 AND paye=0 AND date_lim_reglement IS NOT NULL AND date_lim_reglement<CURDATE()");
$shipTotal = (int) cpadScalar($db, "SELECT COUNT(*) value FROM ".MAIN_DB_PREFIX."chinaplanetlogistics WHERE entity = ".$entity);
$activeShipments = (int) cpadScalar($db, "SELECT COUNT(*) value FROM ".MAIN_DB_PREFIX."chinaplanetlogistics WHERE entity = ".$entity." AND fk_statut IN (".chinaplanetlogistics_status_sql_list('active').")");
$inTransit = (int) cpadScalar($db, "SELECT COUNT(*) value FROM ".MAIN_DB_PREFIX."chinaplanetlogistics WHERE entity = ".$entity." AND fk_statut IN (".chinaplanetlogistics_status_sql_list('in_transit').")");
$arriving = (int) cpadScalar($db, "SELECT COUNT(*) value FROM ".MAIN_DB_PREFIX."chinaplanetlogistics WHERE entity = ".$entity." AND fk_statut IN (".chinaplanetlogistics_status_sql_list('active').") AND date_eta BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 14 DAY)");
$arrived = (int) cpadScalar($db, "SELECT COUNT(*) value FROM ".MAIN_DB_PREFIX."chinaplanetlogistics WHERE entity = ".$entity." AND fk_statut IN (".chinaplanetlogistics_status_sql_list('arrived').")");
$critical = (int) cpadScalar($db, "SELECT COUNT(*) value FROM ".MAIN_DB_PREFIX."chinaplanetlogistics WHERE entity = ".$entity." AND fk_statut IN (".chinaplanetlogistics_status_sql_list('active').") AND ((date_eta < CURDATE()) OR freetime_end BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY))");
$cardsActive = (int) cpadScalar($db, "SELECT COUNT(*) value FROM ".MAIN_DB_PREFIX."cpkanban_card WHERE entity = ".$entity." AND status = 1");
$exportDocuments = cpadTableExists($db, MAIN_DB_PREFIX.'cpcc_export_document') ? (int) cpadScalar($db, "SELECT COUNT(*) value FROM ".MAIN_DB_PREFIX."cpcc_export_document WHERE entity=".$entity." AND status=1") : 0;
$publishedRates = cpadTableExists($db, MAIN_DB_PREFIX.'chinaplanetsp_rate') ? (int) cpadScalar($db, "SELECT COUNT(*) value FROM ".MAIN_DB_PREFIX."chinaplanetsp_rate WHERE entity=".$entity." AND status=1 AND is_current=1") : 0;
$shippingInquiries = cpadTableExists($db, MAIN_DB_PREFIX.'chinaplanetsp_inquiry') ? (int) cpadScalar($db, "SELECT COUNT(*) value FROM ".MAIN_DB_PREFIX."chinaplanetsp_inquiry WHERE entity=".$entity." AND status=1") : 0;
$shippingOrderStats = (new OperationsService($db))->shippingOrderStats();
$shipmentsWithoutExportDocs = 0;
if (cpadTableExists($db, MAIN_DB_PREFIX.'cpcc_export_document')) {
    $shipmentsWithoutExportDocs = (int) cpadScalar($db, "SELECT COUNT(*) value FROM ".MAIN_DB_PREFIX."chinaplanetlogistics l WHERE l.entity=".$entity." AND l.fk_statut IN (".chinaplanetlogistics_status_sql_list('active').") AND NOT EXISTS (SELECT 1 FROM ".MAIN_DB_PREFIX."cpcc_export_document d WHERE d.entity=l.entity AND d.fk_shipment=l.rowid AND d.status=1)");
}

$financialByCurrency = array();
$customerResult = $db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."societe WHERE entity IN (0,".$entity.") AND client IN (1,2,3) ORDER BY rowid");
if ($customerResult) while ($customerRow = $db->fetch_object($customerResult)) {
    cpadAddCurrencySummary($financialByCurrency, cpcc_financial_summary($db, (int) $customerRow->rowid, $currency));
}
ksort($financialByCurrency);

$recentOrders = array();
$orderSql = "SELECT c.rowid,c.ref,c.date_commande,c.total_ttc,c.multicurrency_code,c.multicurrency_total_ttc,c.fk_statut,s.rowid customer_id,s.nom customer";
$orderSql .= " FROM ".MAIN_DB_PREFIX."commande c LEFT JOIN ".MAIN_DB_PREFIX."societe s ON s.rowid=c.fk_soc";
$orderSql .= " WHERE c.entity=".$entity." ORDER BY c.date_commande DESC,c.rowid DESC LIMIT 6";
$orderResult = $db->query($orderSql);
if ($orderResult) while ($orderRow = $db->fetch_object($orderResult)) $recentOrders[] = $orderRow;

$recentFinance = array();
if (cpadTableExists($db, MAIN_DB_PREFIX.'cpcc_financial_entry')) {
    $financeSql = "SELECT e.rowid,e.entry_date,e.entry_type,e.direction,e.description,e.amount,e.currency_code,e.fk_soc,s.nom customer";
    $financeSql .= " FROM ".MAIN_DB_PREFIX."cpcc_financial_entry e LEFT JOIN ".MAIN_DB_PREFIX."societe s ON s.rowid=e.fk_soc";
    $financeSql .= " WHERE e.entity=".$entity." AND e.status=1 ORDER BY e.entry_date DESC,e.rowid DESC LIMIT 6";
    $financeResult = $db->query($financeSql);
    if ($financeResult) while ($financeRow = $db->fetch_object($financeResult)) $recentFinance[] = $financeRow;
}

$months = array();
for ($i = 6; $i >= 0; $i--) {
    $key = date('Y-m', strtotime('-'.$i.' months', strtotime(date('Y-m-01'))));
    $monthStart = strtotime($key.'-01');
    $monthQuery = http_build_query(array(
        'search_status' => '1,2,3',
        'search_date_startday' => 1,
        'search_date_startmonth' => (int) date('m', $monthStart),
        'search_date_startyear' => (int) date('Y', $monthStart),
        'search_date_endday' => (int) date('t', $monthStart),
        'search_date_endmonth' => (int) date('m', $monthStart),
        'search_date_endyear' => (int) date('Y', $monthStart),
    ));
    $months[$key] = array('label' => date('M', $monthStart), 'value' => 0,
        'url' => dol_buildpath('/compta/facture/list.php', 1).'?'.$monthQuery);
}
$resql = $db->query("SELECT DATE_FORMAT(datef,'%Y-%m') month_key, COALESCE(SUM(total_ttc),0) amount FROM ".MAIN_DB_PREFIX."facture WHERE entity = ".$entity." AND fk_statut > 0 AND datef >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 6 MONTH),'%Y-%m-01') GROUP BY month_key ORDER BY month_key");
if ($resql) while ($obj = $db->fetch_object($resql)) if (isset($months[$obj->month_key])) $months[$obj->month_key]['value'] = (float) $obj->amount;
$monthMax = 0;
foreach ($months as $month) $monthMax = max($monthMax, $month['value']);

$currencyRows = array();
$resql = $db->query("SELECT COALESCE(NULLIF(multicurrency_code,''),'".$db->escape($currency)."') code, COUNT(*) invoice_count, COALESCE(SUM(CASE WHEN multicurrency_total_ttc > 0 THEN multicurrency_total_ttc ELSE total_ttc END),0) amount FROM ".MAIN_DB_PREFIX."facture WHERE entity = ".$entity." AND fk_statut > 0 GROUP BY code ORDER BY amount DESC LIMIT 3");
if ($resql) while ($obj = $db->fetch_object($resql)) $currencyRows[] = array('code'=>$obj->code, 'count'=>(int)$obj->invoice_count, 'amount'=>(float)$obj->amount);
$currencyTotal = array_sum(array_column($currencyRows, 'amount'));
$p1 = $currencyTotal > 0 && isset($currencyRows[0]) ? round($currencyRows[0]['amount'] / $currencyTotal * 100) : 34;
$p2 = $currencyTotal > 0 && isset($currencyRows[1]) ? min(100, $p1 + round($currencyRows[1]['amount'] / $currencyTotal * 100)) : 67;

$recentShipments = array();
$sql = "SELECT l.rowid,l.ref,l.container_no,l.port_destination,l.date_eta,l.fk_statut,s.nom customer FROM ".MAIN_DB_PREFIX."chinaplanetlogistics l LEFT JOIN ".MAIN_DB_PREFIX."societe s ON s.rowid=l.fk_soc WHERE l.entity = ".$entity." AND l.fk_statut IN (".chinaplanetlogistics_status_sql_list('active').") ORDER BY COALESCE(l.date_eta,'2999-12-31') ASC,l.rowid DESC LIMIT 7";
$resql = $db->query($sql);
if ($resql) while ($obj = $db->fetch_object($resql)) $recentShipments[] = $obj;

$topCustomers = array();
$sql = "SELECT s.rowid,s.nom,COUNT(f.rowid) invoice_count,COALESCE(SUM(f.total_ttc),0) amount FROM ".MAIN_DB_PREFIX."societe s LEFT JOIN ".MAIN_DB_PREFIX."facture f ON f.fk_soc=s.rowid AND f.fk_statut>0 WHERE s.entity IN (0,".$entity.") AND s.client IN (1,2,3) GROUP BY s.rowid,s.nom ORDER BY amount DESC,invoice_count DESC LIMIT 6";
$resql = $db->query($sql);
$topMax = 0;
if ($resql) while ($obj = $db->fetch_object($resql)) { $topCustomers[]=$obj; $topMax=max($topMax,(float)$obj->amount); }

$statusLabels = chinaplanetlogistics_status_map();
$orderStatusLabels = array(-1=>'Canceled',0=>'Draft',1=>'Validated',2=>'In progress',3=>'Closed');

$widgetLabels = array(
    'kpi_customers'=>cpcc_ui_t('UICustomers035ea'),
    'kpi_orders'=>cpcc_ui_t('UIActiveOrdersc6aea'),
    'kpi_projects'=>cpcc_ui_t('UIActiveProjects8eadc'),
    'kpi_shipments'=>cpcc_ui_t('UIActiveShipments5c2bb'),
    'kpi_alerts'=>cpcc_ui_t('UICriticalAlertsccd1d'),
    'kpi_tasks'=>cpcc_ui_t('UIActiveKanbanea4cf'),
    'attention_center'=>cpcc_ui_t('UIActionRequired4cd4f'),
    'shipment_control'=>cpcc_ui_t('UIShipmentControl403aa'),
    'customer_finance'=>cpcc_ui_t('UICustomerFundsByCurrency41e69'),
    'workflow'=>cpcc_ui_t('UIWorkflowd7a48'),
    'monthly_revenue'=>cpcc_ui_t('UIMonthlyInvoicedAmount2d154'),
    'live_shipments'=>cpcc_ui_t('UILiveShipments9c12e'),
    'recent_orders'=>cpcc_ui_t('UIRecentOrdersae649'),
    'recent_finance'=>cpcc_ui_t('UIRecentFinancialActivity71c87'),
    'top_customers'=>cpcc_ui_t('UITopCustomers87779'),
    'documents_center'=>cpcc_ui_t('UIDocumentsCenter57dad'),
    'shipping_prices'=>cpcc_ui_t('UIShippingPricesb7428'),
    'shipping_orders'=>cpcc_ui_t('UIShippingOrders93ae0'),
    'quick_access'=>cpcc_ui_t('UIQuickAccess85257'),
);
$savedLayout = array();
$layoutTable = $db->query("SHOW TABLES LIKE '".$db->escape(MAIN_DB_PREFIX.'cpcc_dashboard_layout')."'");
if ($layoutTable && $db->num_rows($layoutTable) > 0) {
    $layoutSql = "SELECT widget_key,position,widget_size,visible FROM ".MAIN_DB_PREFIX."cpcc_dashboard_layout";
    $layoutSql .= " WHERE entity=".$entity." AND fk_user=".(int)$user->id." AND dashboard_key='admin_operations' ORDER BY position,rowid";
    $layoutRes = $db->query($layoutSql);
    if ($layoutRes) while ($layoutRow = $db->fetch_object($layoutRes)) {
        $savedLayout[] = array(
            'key'=>(string)$layoutRow->widget_key,
            'position'=>(int)$layoutRow->position,
            'size'=>(string)$layoutRow->widget_size,
            'visible'=>(int)$layoutRow->visible,
        );
    }
}

llxHeader('', 'Vision Operations Dashboard', '', '', 0, 0, array(cpcc_admin_shell_asset('/custom/chinaplanetcustomercenter/js/admin_dashboard.js')), array(cpcc_admin_shell_asset('/custom/chinaplanetcustomercenter/css/admin_dashboard.css')));
?>
<div class="cpad-shell">
  <?php cpcc_admin_shell_navigation('dashboard'); ?>
  <?php cpcc_admin_shell_header(); ?>

  <div id="cpad-widget-grid" class="cpad-widget-grid" aria-live="polite"></div>

  <section class="cpad-kpis">
    <div class="cpad-kpi" data-cpad-widget="kpi_customers" data-default-size="small"><a class="cpad-widget-link" href="<?php echo dol_buildpath('/chinaplanetcustomercenter/board.php',1); ?>"><span class="cpad-kpi-icon fas fa-users"></span><b><?php echo $customers; ?></b><span><?php echo dol_escape_htmltag(cpcc_ui_t('UICustomers035ea')); ?></span><small><?php echo dol_escape_htmltag(cpcc_ui_t('UIOpenCustomerCentera4675')); ?></small></a></div>
    <div class="cpad-kpi" data-cpad-widget="kpi_orders" data-default-size="small"><a class="cpad-widget-link" href="<?php echo dol_buildpath('/chinaplanetcustomercenter/orders.php?status=active',1); ?>"><span class="cpad-kpi-icon fas fa-shopping-cart"></span><b><?php echo $activeOrders; ?></b><span><?php echo dol_escape_htmltag(cpcc_ui_t('UIActiveOrdersc6aea')); ?></span><small><?php echo $allOrders; ?> total · <?php echo $completedOrders; ?> completed</small></a></div>
    <div class="cpad-kpi" data-cpad-widget="kpi_projects" data-default-size="small"><a class="cpad-widget-link" href="<?php echo dol_buildpath('/chinaplanetcustomercenter/projects.php?status=active',1); ?>"><span class="cpad-kpi-icon fas fa-project-diagram"></span><b><?php echo $activeProjects; ?></b><span><?php echo dol_escape_htmltag(cpcc_ui_t('UIActiveProjects8eadc')); ?></span><small><?php echo dol_escape_htmltag(cpcc_ui_t('UIOpenOperationalProjects76d61')); ?></small></a></div>
    <div class="cpad-kpi" data-cpad-widget="kpi_shipments" data-default-size="small"><a class="cpad-widget-link" href="<?php echo dol_buildpath('/chinaplanetlogistics/list.php?status=active',1); ?>"><span class="cpad-kpi-icon fas fa-ship"></span><b><?php echo $activeShipments; ?></b><span><?php echo dol_escape_htmltag(cpcc_ui_t('UIActiveShipments5c2bb')); ?></span><small><?php echo $shipTotal; ?> total shipments</small></a></div>
    <div class="cpad-kpi danger" data-cpad-widget="kpi_alerts" data-default-size="small"><a class="cpad-widget-link" href="<?php echo dol_buildpath('/chinaplanetlogistics/list.php?status=critical',1); ?>"><span class="cpad-kpi-icon fas fa-exclamation-triangle"></span><b><?php echo $critical; ?></b><span><?php echo dol_escape_htmltag(cpcc_ui_t('UICriticalAlertsccd1d')); ?></span><small><?php echo dol_escape_htmltag(cpcc_ui_t('UIEtaOrFreeTimeRisk7d503')); ?></small></a></div>
    <div class="cpad-kpi gold" data-cpad-widget="kpi_tasks" data-default-size="small"><a class="cpad-widget-link" href="<?php echo dol_buildpath('/cpkanban/board.php',1); ?>"><span class="cpad-kpi-icon fas fa-columns"></span><b><?php echo $cardsActive; ?></b><span><?php echo dol_escape_htmltag(cpcc_ui_t('UIActiveKanbanea4cf')); ?></span><small><?php echo dol_escape_htmltag(cpcc_ui_t('UIOpenOperationsBoard216d1')); ?></small></a></div>
  </section>

  <section class="cpad-panel cpad-attention" data-cpad-widget="attention_center" data-default-size="full">
    <div class="cpad-title"><span><?php echo dol_escape_htmltag(cpcc_ui_t('UIActionRequired4cd4f')); ?></span><small><?php echo dol_escape_htmltag(cpcc_ui_t('UIReviewCurrentRisksAndRequiredActions782ad')); ?></small></div>
    <div class="cpad-attention-grid">
      <a class="<?php echo $critical > 0 ? 'is-danger' : 'is-safe'; ?>" href="<?php echo dol_buildpath('/chinaplanetlogistics/list.php?status=critical',1); ?>"><span class="fas fa-ship"></span><strong><?php echo $critical; ?></strong><b><?php echo dol_escape_htmltag(cpcc_ui_t('UICriticalShipments132a7')); ?></b><small><?php echo dol_escape_htmltag(cpcc_ui_t('UIReviewEtaAndFreeTime610c0')); ?></small></a>
      <a class="<?php echo $overdueCount > 0 ? 'is-danger' : 'is-safe'; ?>" href="<?php echo dol_buildpath('/compta/facture/list.php',1); ?>?search_status=1&search_datelimit_end=<?php echo date('Y-m-d'); ?>"><span class="fas fa-file-invoice-dollar"></span><strong><?php echo $overdueCount; ?></strong><b><?php echo dol_escape_htmltag(cpcc_ui_t('UIOverdueInvoices747a2')); ?></b><small><?php echo $unpaidCount; ?> total unpaid</small></a>
      <a class="<?php echo $shipmentsWithoutExportDocs > 0 ? 'is-warn' : 'is-safe'; ?>" href="<?php echo dol_buildpath('/chinaplanetlogistics/list.php',1).'?status=missingdocs'; ?>"><span class="fas fa-file-export"></span><strong><?php echo $shipmentsWithoutExportDocs; ?></strong><b><?php echo dol_escape_htmltag(cpcc_ui_t('UIMissingExportDocsa61f3')); ?></b><small><?php echo dol_escape_htmltag(cpcc_ui_t('UIActiveShipmentsRequiringFilesebb6d')); ?></small></a>
      <a class="is-info" href="<?php echo dol_buildpath('/cpkanban/board.php',1); ?>"><span class="fas fa-tasks"></span><strong><?php echo $cardsActive; ?></strong><b><?php echo dol_escape_htmltag(cpcc_ui_t('UIOpenTasksef296')); ?></b><small><?php echo dol_escape_htmltag(cpcc_ui_t('UIContinueInternalOperationsc51fb')); ?></small></a>
    </div>
  </section>

  <section class="cpad-panel cpad-shipping-orders" data-cpad-widget="shipping_orders" data-default-size="full">
    <div class="cpad-title"><span><?php echo dol_escape_htmltag(cpcc_ui_t('UIShippingOrders93ae0')); ?></span><a href="<?php echo dol_buildpath('/chinaplanetoperations/operations/shipping_orders.php',1); ?>"><?php echo dol_escape_htmltag(cpcc_ui_t('UIOpenRegister2e7cc')); ?></a></div>
    <p class="cpad-shipping-orders-note"><?php echo dol_escape_htmltag(cpcc_ui_t('UICustomerBookingOperationsSeparateFromErpSalesOrd9607c')); ?></p>
    <div class="cpad-shipping-order-metrics">
      <a href="<?php echo dol_buildpath('/chinaplanetoperations/operations/shipping_orders.php?bucket=all',1); ?>"><b><?php echo (int)$shippingOrderStats['total_current']; ?></b><span><?php echo dol_escape_htmltag(cpcc_ui_t('UITotalCurrent622d7')); ?></span></a>
      <a class="active" href="<?php echo dol_buildpath('/chinaplanetoperations/operations/shipping_orders.php?bucket=active',1); ?>"><b><?php echo (int)$shippingOrderStats['active']; ?></b><span><?php echo dol_escape_htmltag(cpcc_ui_t('UIActivea733b')); ?></span></a>
      <a class="confirmed" href="<?php echo dol_buildpath('/chinaplanetoperations/operations/shipping_orders.php?bucket=confirmed',1); ?>"><b><?php echo (int)$shippingOrderStats['confirmed']; ?></b><span><?php echo dol_escape_htmltag(cpcc_ui_t('UIConfirmedShippingOrder')); ?></span></a>
      <a class="closed" href="<?php echo dol_buildpath('/chinaplanetoperations/operations/shipping_orders.php?bucket=closed',1); ?>"><b><?php echo (int)$shippingOrderStats['closed']; ?></b><span><?php echo dol_escape_htmltag(cpcc_ui_t('UIClosed88d86')); ?></span></a>
      <a class="cancelled" href="<?php echo dol_buildpath('/chinaplanetoperations/operations/shipping_orders.php?bucket=cancelled',1); ?>"><b><?php echo (int)$shippingOrderStats['cancelled']; ?></b><span><?php echo dol_escape_htmltag(cpcc_ui_t('UICancelleda1bf9')); ?></span></a>
    </div>
  </section>

  <section class="cpad-panel" data-cpad-widget="customer_finance" data-default-size="full">
    <div class="cpad-title"><span><?php echo dol_escape_htmltag(cpcc_ui_t('UICustomerFundsByCurrency41e69')); ?></span><small><?php echo dol_escape_htmltag(cpcc_ui_t('UIDashboardCaption1')); ?></small></div>
    <?php if (empty($financialByCurrency)) { ?><div class="cpad-empty"><?php echo dol_escape_htmltag(cpcc_ui_t('UINoCustomerFinancialMovementsYet01057')); ?></div><?php } else { ?>
    <div class="cpad-finance-grid">
      <?php foreach ($financialByCurrency as $code=>$totals) { ?>
      <a href="<?php echo dol_buildpath('/chinaplanetcustomercenter/admin/financial_portfolio.php',1); ?>" class="cpad-finance-card">
        <div><span><?php echo dol_escape_htmltag($code); ?></span><small><?php echo dol_escape_htmltag(cpcc_ui_t('UICustomerWalletea707')); ?></small></div>
        <dl><div><dt><?php echo dol_escape_htmltag(cpcc_ui_t('UIReceived27548')); ?></dt><dd><?php echo number_format($totals['received'],2); ?></dd></div><div><dt><?php echo dol_escape_htmltag(cpcc_ui_t('UISpentc0d3c')); ?></dt><dd><?php echo number_format($totals['spent'],2); ?></dd></div><div><dt><?php echo dol_escape_htmltag(cpcc_ui_t('UIAvailable7c62a')); ?></dt><dd class="<?php echo $totals['available'] < 0 ? 'negative' : 'positive'; ?>"><?php echo number_format($totals['available'],2); ?></dd></div><div><dt><?php echo dol_escape_htmltag(cpcc_ui_t('UIRequiredeed6b')); ?></dt><dd class="<?php echo $totals['required_funding'] > 0 ? 'negative' : 'positive'; ?>"><?php echo number_format($totals['required_funding'],2); ?></dd></div></dl>
      </a>
      <?php } ?>
    </div><?php } ?>
  </section>

  <section class="cpad-panel" data-cpad-widget="workflow" data-default-size="large">
    <div class="cpad-title"><span><?php echo dol_escape_htmltag(cpcc_ui_t('UIOperationsFlow84245')); ?></span><small><?php echo dol_escape_htmltag(cpcc_ui_t('UIDashboardCaption2')); ?></small></div>
    <div class="cpad-flow">
      <a href="<?php echo dol_buildpath('/chinaplanetcustomercenter/orders.php?status=active',1); ?>"><span class="fas fa-shopping-basket"></span><b><?php echo $activeOrders; ?></b><small><?php echo dol_escape_htmltag(cpcc_ui_t('UIActiveOrders4bc56')); ?></small></a>
      <a href="<?php echo dol_buildpath('/chinaplanetcustomercenter/projects.php?status=active',1); ?>"><span class="fas fa-project-diagram"></span><b><?php echo $activeProjects; ?></b><small><?php echo dol_escape_htmltag(cpcc_ui_t('UIActiveProjectsf3619')); ?></small></a>
      <a href="<?php echo dol_buildpath('/cpkanban/board.php',1); ?>"><span class="fas fa-columns"></span><b><?php echo $cardsActive; ?></b><small><?php echo dol_escape_htmltag(cpcc_ui_t('UIKanbanTasksa1bb0')); ?></small></a>
      <a href="<?php echo dol_buildpath('/chinaplanetlogistics/list.php?status=active',1); ?>"><span class="fas fa-ship"></span><b><?php echo $activeShipments; ?></b><small><?php echo dol_escape_htmltag(cpcc_ui_t('UILiveShipmentsd7216')); ?></small></a>
      <a href="<?php echo dol_buildpath('/chinaplanetcustomercenter/commercial_documents.php',1); ?>"><span class="fas fa-file-alt"></span><b><?php echo $exportDocuments; ?></b><small><?php echo dol_escape_htmltag(cpcc_ui_t('UIExportDocuments6eb05')); ?></small></a>
      <a href="<?php echo dol_buildpath('/chinaplanetcustomercenter/orders.php?status=completed',1); ?>"><span class="fas fa-check-circle"></span><b><?php echo $completedOrders; ?></b><small><?php echo dol_escape_htmltag(cpcc_ui_t('UICompletedOrdersb3feb')); ?></small></a>
    </div>
  </section>

  <section class="cpad-panel" data-cpad-widget="shipment_control" data-default-size="large">
    <div class="cpad-title"><span><?php echo dol_escape_htmltag(cpcc_ui_t('UIShipmentControl403aa')); ?></span><a href="<?php echo dol_buildpath('/chinaplanetlogistics/list.php',1); ?>"><?php echo dol_escape_htmltag(cpcc_ui_t('UIAllShipmentsd108e')); ?></a></div>
    <div class="cpad-status cpad-status-links">
      <a href="<?php echo dol_buildpath('/chinaplanetlogistics/list.php?status=intransit',1); ?>"><b><?php echo $inTransit; ?></b><span><?php echo dol_escape_htmltag(cpcc_ui_t('UIInTransit63b0b')); ?></span></a>
      <a href="<?php echo dol_buildpath('/chinaplanetlogistics/list.php?status=arriving',1); ?>"><b><?php echo $arriving; ?></b><span><?php echo dol_escape_htmltag(cpcc_ui_t('UIEtaWithin14Days980a9')); ?></span></a>
      <a class="gold" href="<?php echo dol_buildpath('/chinaplanetlogistics/list.php?status=arrived',1); ?>"><b><?php echo $arrived; ?></b><span><?php echo dol_escape_htmltag(cpcc_ui_t('UIArriveda22d6')); ?></span></a>
      <a class="danger" href="<?php echo dol_buildpath('/chinaplanetlogistics/list.php?status=critical',1); ?>"><b><?php echo $critical; ?></b><span><?php echo dol_escape_htmltag(cpcc_ui_t('UICriticalAlerts08df4')); ?></span></a>
    </div>
  </section>

  <section class="cpad-panel" data-cpad-widget="monthly_revenue" data-default-size="large">
    <div class="cpad-title"><span><?php echo dol_escape_htmltag(cpcc_ui_t('UIMonthlyInvoicedAmount2d154')); ?></span><a href="<?php echo dol_buildpath('/compta/facture/list.php',1); ?>"><?php echo dol_escape_htmltag(cpcc_ui_t('UIAllInvoices656d6')); ?></a></div>
    <div class="cpad-bars">
      <?php foreach ($months as $month) { $height = $monthMax > 0 ? max(3, round($month['value']/$monthMax*100)) : 3; ?>
        <a class="cpad-bar" href="<?php echo dol_escape_htmltag($month['url']); ?>" style="--h:<?php echo $height; ?>%" title="<?php echo dol_escape_htmltag($month['label'].' '.number_format($month['value'],2).' '.$currency); ?>"><em><?php echo number_format($month['value'],0); ?></em><span><?php echo dol_escape_htmltag($month['label']); ?></span></a>
      <?php } ?>
    </div>
  </section>

  <section class="cpad-panel" data-cpad-widget="live_shipments" data-default-size="large">
    <div class="cpad-title"><span><?php echo dol_escape_htmltag(cpcc_ui_t('UILiveShipments9c12e')); ?></span><a href="<?php echo dol_buildpath('/chinaplanetlogistics/list.php?status=active',1); ?>"><?php echo dol_escape_htmltag(cpcc_ui_t('UIOpenLiveList2dab6')); ?></a></div>
    <?php if (empty($recentShipments)) { ?><div class="cpad-empty"><?php echo dol_escape_htmltag(cpcc_ui_t('UIDashboardCaption7')); ?></div><?php } else { ?>
    <div class="cpad-table-scroll"><table class="cpad-table">
      <thead><tr><th><?php echo dol_escape_htmltag(cpcc_ui_t('UIDashboardREFCONTAINER')); ?></th><th><?php echo dol_escape_htmltag(cpcc_ui_t('UIDashboardCUSTOMER')); ?></th><th><?php echo dol_escape_htmltag(cpcc_ui_t('UIDashboardDESTINATION')); ?></th><th>ETA</th><th><?php echo dol_escape_htmltag(cpcc_ui_t('UIDashboardSTATUS')); ?></th></tr></thead>
      <tbody><?php foreach ($recentShipments as $shipment) { $late=!empty($shipment->date_eta)&&$shipment->date_eta<date('Y-m-d')&&(chinaplanetlogistics_status_in($shipment->fk_statut,'pre_sailing')||chinaplanetlogistics_status_in($shipment->fk_statut,'in_transit')); ?>
        <tr>
          <td><a href="<?php echo dol_buildpath('/chinaplanetlogistics/card.php?id='.(int)$shipment->rowid,1); ?>"><?php echo dol_escape_htmltag($shipment->container_no ?: $shipment->ref); ?></a></td>
          <td><?php echo dol_escape_htmltag($shipment->customer ?: '—'); ?></td><td><?php echo dol_escape_htmltag($shipment->port_destination ?: '—'); ?></td><td><?php echo dol_escape_htmltag($shipment->date_eta ?: '—'); ?></td>
          <td><span class="cpad-badge <?php echo $late?'danger':((int)$shipment->fk_statut===1?'warn':''); ?>"><?php echo dol_escape_htmltag($statusLabels[(int)$shipment->fk_statut] ?? 'Unknown'); ?></span></td>
        </tr>
      <?php } ?></tbody>
    </table></div><?php } ?>
  </section>

  <section class="cpad-panel" data-cpad-widget="recent_orders" data-default-size="large">
    <div class="cpad-title"><span><?php echo dol_escape_htmltag(cpcc_ui_t('UIRecentOrdersae649')); ?></span><a href="<?php echo dol_buildpath('/chinaplanetcustomercenter/orders.php?status=all',1); ?>"><?php echo dol_escape_htmltag(cpcc_ui_t('UIAllOrderse6909')); ?></a></div>
    <?php if (empty($recentOrders)) { ?><div class="cpad-empty"><?php echo dol_escape_htmltag(cpcc_ui_t('UINoOrdersYet9f61f')); ?></div><?php } else { ?>
    <div class="cpad-table-scroll"><table class="cpad-table"><thead><tr><th><?php echo dol_escape_htmltag(cpcc_ui_t('UIDashboardCaption9')); ?></th><th><?php echo dol_escape_htmltag(cpcc_ui_t('UIDashboardCUSTOMER')); ?></th><th><?php echo dol_escape_htmltag(cpcc_ui_t('UIDashboardCaption10')); ?></th><th><?php echo dol_escape_htmltag(cpcc_ui_t('UIDashboardCaption11')); ?></th><th><?php echo dol_escape_htmltag(cpcc_ui_t('UIDashboardSTATUS')); ?></th></tr></thead><tbody>
      <?php foreach ($recentOrders as $order) { $orderCurrency=trim((string)$order->multicurrency_code); $orderAmount=$orderCurrency!==''&&(float)$order->multicurrency_total_ttc>0?(float)$order->multicurrency_total_ttc:(float)$order->total_ttc; if ($orderCurrency==='') $orderCurrency=$currency; ?>
      <tr><td><a href="<?php echo dol_buildpath('/chinaplanetcustomercenter/order.php?id='.(int)$order->rowid,1); ?>"><?php echo dol_escape_htmltag($order->ref); ?></a></td><td><a href="<?php echo dol_buildpath('/chinaplanetcustomercenter/board.php?socid='.(int)$order->customer_id,1); ?>"><?php echo dol_escape_htmltag($order->customer ?: '—'); ?></a></td><td><?php echo dol_escape_htmltag(!empty($order->date_commande)?substr($order->date_commande,0,10):'—'); ?></td><td><?php echo dol_escape_htmltag(number_format($orderAmount,2).' '.$orderCurrency); ?></td><td><span class="cpad-badge"><?php echo dol_escape_htmltag($orderStatusLabels[(int)$order->fk_statut] ?? 'Unknown'); ?></span></td></tr>
      <?php } ?>
    </tbody></table></div><?php } ?>
  </section>

  <section class="cpad-panel" data-cpad-widget="recent_finance" data-default-size="large">
    <div class="cpad-title"><span><?php echo dol_escape_htmltag(cpcc_ui_t('UIRecentFinancialActivity71c87')); ?></span></div>
    <?php if (empty($recentFinance)) { ?><div class="cpad-empty"><?php echo dol_escape_htmltag(cpcc_ui_t('UINoRecentCustomFinancialEntries0f4fd')); ?></div><?php } else { ?>
    <div class="cpad-activity-list"><?php foreach ($recentFinance as $entry) { ?>
      <a href="<?php echo ($entry->fk_soc > 0) ? dol_buildpath('/chinaplanetcustomercenter/balance.php',1).'?socid='.(int)$entry->fk_soc : dol_buildpath('/chinaplanetcustomercenter/admin/financial_portfolio.php',1); ?>"><span class="cpad-activity-icon <?php echo $entry->direction==='C'?'credit':'debit'; ?> fas <?php echo $entry->direction==='C'?'fa-arrow-down':'fa-arrow-up'; ?>"></span><span><b><?php echo dol_escape_htmltag($entry->customer ?: '—'); ?></b><small><?php echo dol_escape_htmltag($entry->description); ?></small></span><strong><?php echo $entry->direction==='C'?'+':'−'; ?> <?php echo dol_escape_htmltag(number_format($entry->amount,2).' '.$entry->currency_code); ?></strong><time><?php echo dol_escape_htmltag(substr($entry->entry_date,0,10)); ?></time></a>
    <?php } ?></div><?php } ?>
  </section>

  <section class="cpad-panel" data-cpad-widget="top_customers" data-default-size="medium">
    <div class="cpad-title"><span><?php echo dol_escape_htmltag(cpcc_ui_t('UITopCustomers87779')); ?></span><small><?php echo dol_escape_htmltag(cpcc_ui_t('UIDashboardCaption3')); ?></small></div>
    <div class="cpad-ranking"><?php if (empty($topCustomers)) { ?><div class="cpad-empty"><?php echo dol_escape_htmltag(cpcc_ui_t('UIDashboardCaption8')); ?></div><?php } ?><?php foreach ($topCustomers as $customer) { ?>
      <a class="cpad-rank" href="<?php echo dol_buildpath('/chinaplanetcustomercenter/board.php?socid='.(int)$customer->rowid,1); ?>"><span title="<?php echo dol_escape_htmltag($customer->nom); ?>"><?php echo dol_escape_htmltag($customer->nom); ?></span><div class="cpad-track"><div class="cpad-fill" style="--w:<?php echo cpadPct($customer->amount,$topMax); ?>%"></div></div><b><?php echo (int)$customer->invoice_count; ?></b></a>
    <?php } ?></div>
  </section>

  <section class="cpad-panel" data-cpad-widget="documents_center" data-default-size="medium">
    <div class="cpad-title"><span><?php echo dol_escape_htmltag(cpcc_ui_t('UIDocumentsCenter57dad')); ?></span><small><?php echo dol_escape_htmltag(cpcc_ui_t('UIDashboardCaption4')); ?></small></div>
    <div class="cpad-mini-metrics"><a href="<?php echo dol_buildpath('/chinaplanetcustomercenter/commercial_documents.php',1); ?>"><b><?php echo $exportDocuments; ?></b><span><?php echo dol_escape_htmltag(cpcc_ui_t('UIExportDocuments6eb05')); ?></span></a><a href="<?php echo dol_buildpath('/chinaplanetlogistics/list.php?status=missingdocs',1); ?>"><b><?php echo $shipmentsWithoutExportDocs; ?></b><span><?php echo dol_escape_htmltag(cpcc_ui_t('UIActiveShipmentsMissingDocs0fb9e')); ?></span></a></div>
    <div class="cpad-panel-actions"><a href="<?php echo dol_buildpath('/chinaplanetcustomercenter/admin/export_document.php',1); ?>"><span class="fas fa-plus"></span><?php echo dol_escape_htmltag(cpcc_ui_t('UICreateExportDocument9a4d1')); ?></a></div>
  </section>

  <section class="cpad-panel" data-cpad-widget="shipping_prices" data-default-size="medium">
    <div class="cpad-title"><span><?php echo dol_escape_htmltag(cpcc_ui_t('UIShippingPricesb7428')); ?></span><small><?php echo dol_escape_htmltag(cpcc_ui_t('UIDashboardCaption5')); ?></small></div>
    <div class="cpad-mini-metrics"><a href="<?php echo dol_buildpath('/chinaplanetshippingprices/index.php',1); ?>"><b><?php echo $publishedRates; ?></b><span><?php echo dol_escape_htmltag(cpcc_ui_t('UIPublishedCurrentRatesa0b3b')); ?></span></a><a href="<?php echo dol_buildpath('/chinaplanetshippingprices/admin/dashboard.php',1); ?>"><b><?php echo $shippingInquiries; ?></b><span><?php echo dol_escape_htmltag(cpcc_ui_t('UISavedInquiriesed0b0')); ?></span></a></div>
    <div class="cpad-panel-actions"><a href="<?php echo dol_buildpath('/chinaplanetshippingprices/index.php',1); ?>"><span class="fas fa-search-dollar"></span><?php echo dol_escape_htmltag(cpcc_ui_t('UISearchPrice91d5b')); ?></a><a href="<?php echo dol_buildpath('/chinaplanetshippingprices/admin/import.php',1); ?>"><span class="fas fa-file-import"></span><?php echo dol_escape_htmltag(cpcc_ui_t('UIImportRates82b1c')); ?></a></div>
  </section>

  <section class="cpad-panel" data-cpad-widget="quick_access" data-default-size="full">
    <div class="cpad-title"><span><?php echo dol_escape_htmltag(cpcc_ui_t('UIQuickActionsc4081')); ?></span><small><?php echo dol_escape_htmltag(cpcc_ui_t('UIDashboardCaption6')); ?></small></div>
    <div class="cpad-quick-grid">
      <a href="<?php echo dol_buildpath('/chinaplanetoperations/operations/public_requests.php',1); ?>"><span class="fas fa-inbox"></span><b><?php echo dol_escape_htmltag(cpcc_ui_t('UIQuickWebsiteRequests')); ?></b></a>
      <a href="<?php echo dol_buildpath('/chinaplanetlogistics/card.php?action=create',1); ?>"><span class="fas fa-plus-circle"></span><b><?php echo dol_escape_htmltag(cpcc_ui_t('UIQuickNewShipment')); ?></b></a>
      <a href="<?php echo dol_buildpath('/chinaplanetoperations/index.php',1); ?>"><span class="fas fa-route"></span><b><?php echo dol_escape_htmltag(cpcc_ui_t('UIQuickInternalOperations')); ?></b></a>
      <a href="<?php echo dol_buildpath('/chinaplanetoperations/sop/index.php',1); ?>"><span class="fas fa-book-open"></span><b><?php echo dol_escape_htmltag(cpcc_ui_t('UIQuickSOPLibrary')); ?></b></a>
      <a href="<?php echo dol_buildpath('/chinaplanetoperations/operations/edit.php?service_type=shipping_booking',1); ?>"><span class="fas fa-ship"></span><b><?php echo dol_escape_htmltag(cpcc_ui_t('UIQuickNewShippingBooking')); ?></b></a>
      <a href="<?php echo dol_buildpath('/chinaplanetoperations/tasks/board.php',1); ?>"><span class="fas fa-tasks"></span><b><?php echo dol_escape_htmltag(cpcc_ui_t('UIQuickInternalTasks')); ?></b></a>
      <a href="<?php echo dol_buildpath('/chinaplanetcustomercenter/admin/operations.php',1); ?>"><span class="fas fa-project-diagram"></span><b><?php echo dol_escape_htmltag(cpcc_ui_t('UIQuickCustomerProjectOperations')); ?></b></a>
      <a href="<?php echo dol_buildpath('/chinaplanetcustomercenter/admin/financial.php',1); ?>"><span class="fas fa-wallet"></span><b><?php echo dol_escape_htmltag(cpcc_ui_t('UIQuickFinancialEntry')); ?></b></a>
      <a href="<?php echo dol_buildpath('/chinaplanetcustomercenter/admin/export_document.php',1); ?>"><span class="fas fa-file-export"></span><b><?php echo dol_escape_htmltag(cpcc_ui_t('UIQuickExportDocument')); ?></b></a>
      <a href="<?php echo dol_buildpath('/cpkanban/board.php',1); ?>"><span class="fas fa-columns"></span><b><?php echo dol_escape_htmltag(cpcc_ui_t('UIQuickOperationsKanban')); ?></b></a>
      <a href="<?php echo dol_buildpath('/chinaplanetshippingprices/index.php',1); ?>"><span class="fas fa-search-dollar"></span><b><?php echo dol_escape_htmltag(cpcc_ui_t('UIQuickShippingPrice')); ?></b></a>
      <a href="<?php echo dol_buildpath('/chinaplanetcustomercenter/board.php',1); ?>"><span class="fas fa-user-tie"></span><b><?php echo dol_escape_htmltag(cpcc_ui_t('UIQuickCustomerCenter')); ?></b></a>
      <a href="<?php echo dol_buildpath('/chinaplanetlogistics/list.php',1); ?>"><span class="fas fa-list"></span><b><?php echo dol_escape_htmltag(cpcc_ui_t('UIQuickAllShipments')); ?></b></a>
    </div>
  </section>
  <div class="cpad-customize-toolbar">
    <button type="button" id="cpad-customize" class="cpad-tool primary"><span class="fas fa-sliders-h"></span><?php echo dol_escape_htmltag(cpcc_ui_t('UICustomizeDashboard388a2')); ?></button>
    <button type="button" id="cpad-save-layout" class="cpad-tool success" hidden><span class="fas fa-save"></span><?php echo dol_escape_htmltag(cpcc_ui_t('UISaveLayout3a511')); ?></button>
    <button type="button" id="cpad-widget-library-button" class="cpad-tool" hidden><span class="fas fa-th"></span><?php echo dol_escape_htmltag(cpcc_ui_t('UIWidgetLibrary977d0')); ?></button>
    <button type="button" id="cpad-reset-layout" class="cpad-tool warn" hidden><span class="fas fa-undo"></span><?php echo dol_escape_htmltag(cpcc_ui_t('UIResetDefaultc46b7')); ?></button>
    <button type="button" id="cpad-cancel-layout" class="cpad-tool" hidden><?php echo dol_escape_htmltag(cpcc_ui_t('UICancel77dfd')); ?></button>
    <span id="cpad-layout-message" class="cpad-layout-message" hidden></span>
  </div>
  <section class="cpad-work-start" aria-labelledby="cpad-work-start-title">
    <div class="cpad-work-start-head"><div><span><?php echo dol_escape_htmltag(cpcc_ui_t('UIDailyWork7915b')); ?></span><h2 id="cpad-work-start-title"><?php echo dol_escape_htmltag(cpcc_ui_t('UIStartDailyWork78031')); ?></h2><p><?php echo dol_escape_htmltag(cpcc_ui_t('UIChooseTheTaskYouNeedToWorkOnToday294bd')); ?></p></div><a href="<?php echo dol_buildpath('/chinaplanetoperations/workspace.php',1); ?>"><?php echo dol_escape_htmltag(cpcc_ui_t('UIOpenDailyWorkspace71505')); ?></a></div>
    <div class="cpad-work-start-grid">
      <a href="<?php echo dol_buildpath('/chinaplanetoperations/operations/edit.php?service_type=order',1); ?>"><span class="fas fa-shopping-cart"></span><b><?php echo dol_escape_htmltag(cpcc_ui_t('UICustomerOrderc60ba')); ?></b></a>
      <a href="<?php echo dol_buildpath('/chinaplanetoperations/operations/edit.php?service_type=sourcing',1); ?>"><span class="fas fa-search"></span><b><?php echo dol_escape_htmltag(cpcc_ui_t('UIProductSourcing9224f')); ?></b></a>
      <a href="<?php echo dol_buildpath('/chinaplanetoperations/operations/edit.php?service_type=purchasing',1); ?>"><span class="fas fa-boxes"></span><b><?php echo dol_escape_htmltag(cpcc_ui_t('UIPurchasing6e3d5')); ?></b></a>
      <a href="<?php echo dol_buildpath('/chinaplanetoperations/operations/edit.php?service_type=quality_control',1); ?>"><span class="fas fa-clipboard-check"></span><b><?php echo dol_escape_htmltag(cpcc_ui_t('UIQualityControl3dad0')); ?></b></a>
      <a href="<?php echo dol_buildpath('/chinaplanetoperations/operations/edit.php?service_type=shipping_booking',1); ?>"><span class="fas fa-ship"></span><b><?php echo dol_escape_htmltag(cpcc_ui_t('UIShippingBooking138da')); ?></b></a>
      <a href="<?php echo dol_buildpath('/chinaplanetoperations/operations/edit.php?service_type=customs',1); ?>"><span class="fas fa-file-contract"></span><b><?php echo dol_escape_htmltag(cpcc_ui_t('UICustomsAndDocuments2244d')); ?></b></a>
    </div>
  </section>
  <dialog id="cpad-widget-library" class="cpad-widget-library" aria-labelledby="cpad-library-title">
    <div class="cpad-library-head"><div><strong id="cpad-library-title"><?php echo dol_escape_htmltag(cpcc_ui_t('UIWidgetLibrary977d0')); ?></strong><small><?php echo dol_escape_htmltag(cpcc_ui_t('UIShowAndHideDashboardWidgets96363')); ?></small></div><button type="button" id="cpad-widget-library-close" aria-label="Close">×</button></div>
    <div id="cpad-widget-library-list" class="cpad-library-list"></div>
  </dialog>
  <script type="application/json" id="cpad-layout-data"><?php echo json_encode(array(
      'layout'=>$savedLayout,
      'labels'=>$widgetLabels,
      'ui'=>array('move'=>cpcc_ui_t('UIMoveWidget'),'resize'=>cpcc_ui_t('UIResizeWidget'),'hide'=>cpcc_ui_t('UIHideWidget'),'saved'=>cpcc_ui_t('UILayoutSaved'),'small'=>cpcc_ui_t('UISizeSmall'),'medium'=>cpcc_ui_t('UISizeMedium'),'large'=>cpcc_ui_t('UISizeLarge'),'full'=>cpcc_ui_t('UISizeFull')),
      'token'=>currentToken(),
      'endpoint'=>dol_buildpath('/chinaplanetcustomercenter/ajax/dashboard_layout.php', 1),
  ), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT); ?></script>
  <div class="cpad-footnote"><?php echo dol_escape_htmltag(cpcc_ui_t('UIDashboardCaption12')); ?></div>
</div>
<?php
llxFooter();
$db->close();
