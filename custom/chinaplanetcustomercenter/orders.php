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
$canOpenCoreOrder = !$isExternal && isModEnabled('order') && $user->hasRight('commande', 'lire');
$currencyFallback = cpcc_financial_currency(!empty($conf->currency) ? $conf->currency : 'USD');

$requestedView = GETPOST('status', 'alpha');
$view = in_array($requestedView, array('active', 'completed', 'all'), true) ? $requestedView : 'all';
$searchRef = trim(GETPOST('search_ref', 'alphanohtml'));
$dateFrom = trim(GETPOST('date_from', 'alpha'));
$dateTo = trim(GETPOST('date_to', 'alpha'));
$dateFrom = preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) ? $dateFrom : '';
$dateTo = preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo) ? $dateTo : '';
$specificStatusRaw = GETPOST('order_status', 'alpha');
$allowedStatuses = array('-1', '0', '1', '2', '3');
$specificStatus = in_array($specificStatusRaw, $allowedStatuses, true) ? (int) $specificStatusRaw : null;

$scopeSql = "c.entity IN (".getEntity('commande').")";
if ($socid > 0) {
	$scopeSql .= " AND c.fk_soc=".(int) $socid;
} elseif ($isExternal) {
	$scopeSql .= " AND 1=0";
}

$orderCounts = array('active'=>0, 'completed'=>0, 'all'=>0);
$countSql = "SELECT"
	." SUM(CASE WHEN c.fk_statut IN (1,2) THEN 1 ELSE 0 END) AS active_count,"
	." SUM(CASE WHEN c.fk_statut=3 THEN 1 ELSE 0 END) AS completed_count,"
	." COUNT(*) AS total_count"
	." FROM ".MAIN_DB_PREFIX."commande c WHERE ".$scopeSql;
$countRes = $db->query($countSql);
if ($countRes && ($countObj = $db->fetch_object($countRes))) {
	$orderCounts['active'] = (int) $countObj->active_count;
	$orderCounts['completed'] = (int) $countObj->completed_count;
	$orderCounts['all'] = (int) $countObj->total_count;
}

$viewUrls = array();
foreach (array('active', 'completed', 'all') as $viewKey) {
	$viewUrls[$viewKey] = cpcc_portal_scoped_url('/chinaplanetcustomercenter/orders.php', $isExternal, $socid, array('status'=>$viewKey));
}
$resetUrl = $viewUrls[$view];

$pageCss = array(
	cpcc_admin_shell_asset('/custom/chinaplanetcustomercenter/css/customer_dashboard.css'),
	cpcc_admin_shell_asset('/custom/chinaplanetcustomercenter/css/orders.css'),
);
if (!$isExternal) $pageCss[] = cpcc_admin_shell_asset('/custom/chinaplanetcustomercenter/css/admin_dashboard.css');

llxHeader('', 'Orders / الطلبات', '', '', 0, 0, array(), $pageCss);
if ($isExternal) cpcc_portal_navigation($user, 'orders');
else cpcc_admin_shell_open('portal', 'Orders / الطلبات', 'الطلبات النشطة والمكتملة وجميع طلبات العميل', 'fa-shopping-cart', array('socid'=>$socid));

$orderStatuses = array(
	-1 => 'ملغي / Canceled',
	0 => 'مسودة / Draft',
	1 => 'معتمد / Validated',
	2 => 'قيد التنفيذ / In progress',
	3 => 'مغلق / Closed',
);
?>
<main class="cpcc-dashboard cpcc-orders-page" dir="rtl">
	<header class="cpcc-page-head">
		<div>
			<span class="cpcc-eyebrow">CUSTOMER ORDERS</span>
			<h1>الطلبات / Orders</h1>
			<p>عرض الطلبات حسب دورة العمل مع البحث والتصفية بالتاريخ والحالة.</p>
		</div>
	</header>

	<?php if ($isExternal && $socid <= 0) { ?>
		<div class="warning">لا يوجد حساب عميل مرتبط بهذا المستخدم. / No linked customer account was found.</div>
	<?php } else { ?>
		<section class="cpcc-order-view-cards" aria-label="Order views">
			<a class="cpcc-order-view-card<?php echo $view === 'active' ? ' is-active' : ''; ?>" href="<?php echo dol_escape_htmltag($viewUrls['active']); ?>">
				<span>الطلبات النشطة / Active Orders</span><strong><?php echo $orderCounts['active']; ?></strong><small>Validated + in progress / المعتمدة وقيد التنفيذ</small>
			</a>
			<a class="cpcc-order-view-card<?php echo $view === 'completed' ? ' is-active' : ''; ?>" href="<?php echo dol_escape_htmltag($viewUrls['completed']); ?>">
				<span>الطلبات المكتملة / Completed Orders</span><strong><?php echo $orderCounts['completed']; ?></strong><small>Closed orders / الطلبات المغلقة</small>
			</a>
			<a class="cpcc-order-view-card<?php echo $view === 'all' ? ' is-active' : ''; ?>" href="<?php echo dol_escape_htmltag($viewUrls['all']); ?>">
				<span>كل الطلبات / All Orders</span><strong><?php echo $orderCounts['all']; ?></strong><small>Includes drafts and canceled / تشمل المسودات والملغاة</small>
			</a>
		</section>

		<form method="get" class="cpcc-order-filters" aria-label="Order filters">
			<input type="hidden" name="status" value="<?php echo dol_escape_htmltag($view); ?>">
			<?php if (!$isExternal && $socid > 0) { ?><input type="hidden" name="socid" value="<?php echo (int) $socid; ?>"><?php } ?>
			<label><span>مرجع الطلب / Order Ref</span><input type="search" name="search_ref" value="<?php echo dol_escape_htmltag($searchRef); ?>" placeholder="SO2607-0001"></label>
			<label><span>من تاريخ / Date From</span><input type="date" name="date_from" value="<?php echo dol_escape_htmltag($dateFrom); ?>"></label>
			<label><span>إلى تاريخ / Date To</span><input type="date" name="date_to" value="<?php echo dol_escape_htmltag($dateTo); ?>"></label>
			<label><span>الحالة التفصيلية / Exact Status</span>
				<select name="order_status">
					<option value="">كل الحالات داخل العرض / Any in this view</option>
					<?php foreach ($orderStatuses as $statusValue=>$statusLabel) { ?>
						<option value="<?php echo (int) $statusValue; ?>"<?php echo $specificStatus !== null && $specificStatus === (int) $statusValue ? ' selected' : ''; ?>><?php echo dol_escape_htmltag($statusLabel); ?></option>
					<?php } ?>
				</select>
			</label>
			<div class="cpcc-order-filter-actions">
				<button type="submit">تطبيق الفلاتر / Apply Filters</button>
				<a href="<?php echo dol_escape_htmltag($resetUrl); ?>">مسح الفلاتر / Reset</a>
			</div>
		</form>

		<?php
		$where = array($scopeSql);
		if ($view === 'active') $where[] = 'c.fk_statut IN (1,2)';
		elseif ($view === 'completed') $where[] = 'c.fk_statut=3';
		if ($specificStatus !== null) $where[] = 'c.fk_statut='.(int) $specificStatus;
		if ($searchRef !== '') $where[] = "c.ref LIKE '%".$db->escape($searchRef)."%'";
		if ($dateFrom !== '') $where[] = "c.date_commande >= '".$db->escape($dateFrom)." 00:00:00'";
		if ($dateTo !== '') $where[] = "c.date_commande <= '".$db->escape($dateTo)." 23:59:59'";

		$sql = "SELECT c.rowid,c.ref,c.date_commande,c.total_ttc,c.multicurrency_code,c.multicurrency_total_ttc,c.fk_statut,s.nom AS customer_name"
			." FROM ".MAIN_DB_PREFIX."commande c"
			." LEFT JOIN ".MAIN_DB_PREFIX."societe s ON s.rowid=c.fk_soc"
			." WHERE ".implode(' AND ', $where)
			." ORDER BY c.date_commande DESC,c.rowid DESC";
		$resql = $db->query($sql);
		$resultCount = $resql ? $db->num_rows($resql) : 0;
		?>

		<section class="cpcc-section-head">
			<div><h2>النتائج / Results</h2><p><?php echo (int) $resultCount; ?> طلب مطابق / matching orders</p></div>
		</section>
		<div class="cpcc-table-wrap">
			<table class="cpcc-table cpcc-orders-table">
				<thead><tr><th>الطلب / Order</th><?php if (!$isExternal) { ?><th>العميل / Customer</th><?php } ?><th>التاريخ / Date</th><th>الإجمالي / Total</th><th>الحالة / Status</th><th>الإجراء / Action</th></tr></thead>
				<tbody>
				<?php if (!$resql || $resultCount === 0) { ?><tr><td colspan="<?php echo $isExternal ? 5 : 6; ?>" class="center opacitymedium">لا توجد طلبات مطابقة للفلاتر / No matching orders.</td></tr><?php } ?>
				<?php if ($resql) while ($obj = $db->fetch_object($resql)) {
					$orderUrl = dol_buildpath('/chinaplanetcustomercenter/order.php', 1).'?id='.(int) $obj->rowid.(!$isExternal ? '&socid='.(int) $socid : '');
					$orderHasMulticurrency = trim((string) $obj->multicurrency_code) !== '';
					$orderCurrency = cpcc_financial_currency($obj->multicurrency_code, $currencyFallback);
					$orderAmount = $orderHasMulticurrency ? (float) $obj->multicurrency_total_ttc : (float) $obj->total_ttc;
				?>
					<tr>
						<td><a class="cpcc-order-ref" href="<?php echo dol_escape_htmltag($orderUrl); ?>"><?php echo dol_escape_htmltag($obj->ref); ?></a></td>
						<?php if (!$isExternal) { ?><td><?php echo dol_escape_htmltag($obj->customer_name ?: '-'); ?></td><?php } ?>
						<td><?php echo !empty($obj->date_commande) ? dol_print_date($obj->date_commande, 'day') : '-'; ?></td>
						<td class="money"><?php echo cpcc_portal_money($orderAmount).' '.dol_escape_htmltag($orderCurrency); ?></td>
						<td><span class="cpcc-order-status status-<?php echo (int) $obj->fk_statut; ?>"><?php echo dol_escape_htmltag(isset($orderStatuses[(int) $obj->fk_statut]) ? $orderStatuses[(int) $obj->fk_statut] : (string) $obj->fk_statut); ?></span></td>
						<td><a class="cpcc-order-open" href="<?php echo dol_escape_htmltag($orderUrl); ?>">فتح / Open</a><?php if ($canOpenCoreOrder) { ?> <a class="cpcc-order-core" target="_blank" rel="noopener" href="<?php echo dol_escape_htmltag(dol_buildpath('/commande/card.php', 1).'?id='.(int) $obj->rowid); ?>">Vision Advanced ↗</a><?php } ?></td>
					</tr>
				<?php } ?>
				</tbody>
			</table>
		</div>
	<?php } ?>
</main>
<?php if (!$isExternal) cpcc_admin_shell_close(); llxFooter();
