<?php
if (!isset($_SERVER['QUERY_STRING'])) $_SERVER['QUERY_STRING'] = '';
require_once __DIR__.'/lib/main.lib.php';
require_once __DIR__.'/lib/admin_shell.lib.php';
require_once __DIR__.'/lib/portal.lib.php';
require_once __DIR__.'/lib/financial.lib.php';
require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';

$langs->loadLangs(array('orders', 'companies'));

cpcc_portal_require_access($user);
$scope = cpcc_portal_user_scope($user);
$is_external = !empty($scope['is_external']);
$canOpenCoreOrder = !$is_external && isModEnabled('order') && $user->hasRight('commande', 'lire');
$id = GETPOSTINT('id');
$order = new Commande($db);
if ($id <= 0 || $order->fetch($id) <= 0) cpcc_portal_access_denied($user, 'orders');
if ($is_external && (int) $order->socid !== (int) $scope['socid']) cpcc_portal_access_denied($user, 'orders');
$currencyFallback = cpcc_financial_currency(!empty($conf->currency) ? $conf->currency : 'USD');
$orderHasMulticurrency = trim((string) $order->multicurrency_code) !== '';
$orderCurrency = cpcc_financial_currency($order->multicurrency_code, $currencyFallback);
$orderTotal = $orderHasMulticurrency ? (float) $order->multicurrency_total_ttc : (float) $order->total_ttc;

$orderStatusLabels = array(
	-1 => 'Canceled / ملغي',
	0 => 'Draft / مسودة',
	1 => 'Validated / معتمد',
	2 => 'In progress / قيد التنفيذ',
	3 => 'Closed / مغلق',
);
$orderStatusLabel = isset($orderStatusLabels[(int) $order->statut])
	? $orderStatusLabels[(int) $order->statut]
	: $order->getLibStatut(1);

$pageCss = array(cpcc_admin_shell_asset('/custom/chinaplanetcustomercenter/css/customer_dashboard.css'));
if (!$is_external) {
	$pageCss[] = cpcc_admin_shell_asset('/custom/chinaplanetcustomercenter/css/admin_dashboard.css');
}

llxHeader('', 'Order / الطلب', '', '', 0, 0, array(), $pageCss);
if($is_external) cpcc_portal_navigation($user, 'orders'); else cpcc_admin_shell_open('portal','Order Details / تفاصيل الطلب','عرض الطلب وحالته وبنوده','fa-shopping-cart',array('socid'=>(int)$order->socid));
?>
<main class="cpcc-dashboard" dir="rtl">
  <header class="cpcc-page-head"><div><span class="cpcc-eyebrow"><?php echo dol_escape_htmltag($order->ref); ?></span><h1>تفاصيل الطلب / Order Details</h1><p><?php echo dol_escape_htmltag($orderStatusLabel); ?></p></div></header>
  <?php if ($canOpenCoreOrder) { ?><p><a class="button" target="_blank" rel="noopener" href="<?php echo dol_escape_htmltag(dol_buildpath('/commande/card.php',1).'?id='.(int)$order->id); ?>">Vision Advanced / فتح السجل المتقدم ↗</a></p><?php } ?>
  <section class="cpcc-financial-cards">
    <div class="cpcc-card"><span>التاريخ</span><strong><?php echo dol_escape_htmltag(dol_print_date($order->date,'day')); ?></strong><small>Order date</small></div>
    <div class="cpcc-card"><span>الإجمالي</span><strong><?php echo cpcc_portal_money($orderTotal).' '.dol_escape_htmltag($orderCurrency); ?></strong><small>Total incl. tax</small></div>
    <div class="cpcc-card"><span>الحالة / Status</span><strong><?php echo dol_escape_htmltag($orderStatusLabel); ?></strong><small>Current order status / حالة الطلب الحالية</small></div>
  </section>
  <section class="cpcc-section-head"><div><h2>بنود الطلب</h2><p>Customer-approved sales order lines only.</p></div></section>
  <div class="cpcc-table-wrap"><table class="cpcc-table"><thead><tr><th>الوصف</th><th>الكمية</th><th>سعر الوحدة</th><th>الإجمالي</th></tr></thead><tbody>
  <?php if (empty($order->lines)) { ?><tr><td colspan="4">لا توجد بنود.</td></tr><?php } ?>
  <?php foreach ($order->lines as $line) { $linePrice=$orderHasMulticurrency&&(float)$line->multicurrency_subprice!=0?(float)$line->multicurrency_subprice:(float)$line->subprice; $lineTotal=$orderHasMulticurrency&&(float)$line->multicurrency_total_ttc!=0?(float)$line->multicurrency_total_ttc:(float)$line->total_ttc; ?><tr><td><?php echo dol_escape_htmltag($line->desc); ?></td><td><?php echo dol_escape_htmltag((string)$line->qty); ?></td><td><?php echo cpcc_portal_money($linePrice).' '.dol_escape_htmltag($orderCurrency); ?></td><td><?php echo cpcc_portal_money($lineTotal).' '.dol_escape_htmltag($orderCurrency); ?></td></tr><?php } ?>
  </tbody></table></div>
</main>
<?php if(!$is_external) cpcc_admin_shell_close(); llxFooter();
