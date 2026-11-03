<?php
require_once __DIR__.'/lib/ui.lib.php';
if (!isset($_SERVER['QUERY_STRING'])) $_SERVER['QUERY_STRING'] = '';
require_once __DIR__.'/lib/main.lib.php';
require_once __DIR__.'/lib/admin_shell.lib.php';
require_once __DIR__.'/lib/portal.lib.php';
require_once __DIR__.'/lib/financial.lib.php';
require_once __DIR__.'/lib/commercial_trace.lib.php';
require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';

$langs->loadLangs(array('orders', 'companies'));

cpcc_portal_require_access($user);
$scope = cpcc_portal_user_scope($user);
$is_external = !empty($scope['is_external']);
$canOpenCoreOrder = !$is_external && isModEnabled('order') && $user->hasRight('commande', 'lire');
$id = GETPOSTINT('id');
$order = new Commande($db);
if ($id <= 0 || $order->fetch($id) <= 0) cpcc_portal_access_denied($user, 'orders');
if ((int) $order->entity !== (int) $conf->entity) cpcc_portal_access_denied($user, 'orders');
if ($is_external && (int) $order->socid !== (int) $scope['socid']) cpcc_portal_access_denied($user, 'orders');
if ($is_external && (int) $order->statut <= 0) cpcc_portal_access_denied($user, 'orders');
$currencyFallback = cpcc_financial_currency(!empty($conf->currency) ? $conf->currency : 'USD');
$orderHasMulticurrency = trim((string) $order->multicurrency_code) !== '';
$orderCurrency = cpcc_financial_currency($order->multicurrency_code, $currencyFallback);
$orderTotal = $orderHasMulticurrency ? (float) $order->multicurrency_total_ttc : (float) $order->total_ttc;
$orderTrace = cpcc_customer_order_trace($db, $order->id, $order->socid, $is_external);
$orderProject = null;
if ((int) $order->fk_project > 0) {
	$resql = $db->query("SELECT rowid,ref,title FROM ".MAIN_DB_PREFIX."projet WHERE entity IN (".getEntity('project').") AND rowid=".(int) $order->fk_project." AND fk_soc=".(int) $order->socid);
	$orderProject = $resql ? $db->fetch_object($resql) : null;
}

$orderStatusLabels = array(
	-1 => cpcc_ui_english_label('Canceled', 'Canceled / ملغي'),
	0 => cpcc_ui_english_label('Draft', 'Draft / مسودة'),
	1 => cpcc_ui_english_label('Validated', 'Validated / معتمد'),
	2 => cpcc_ui_english_label('In progress', 'In progress / قيد التنفيذ'),
	3 => cpcc_ui_english_label('Closed', 'Closed / مغلق'),
);
$orderStatusLabel = isset($orderStatusLabels[(int) $order->statut])
	? $orderStatusLabels[(int) $order->statut]
	: $order->getLibStatut(1);

$pageCss = array(cpcc_portal_asset('/custom/chinaplanetcustomercenter/css/customer_dashboard.css'), cpcc_portal_asset('/custom/chinaplanetcustomercenter/css/quotations.css'));
if (!$is_external) {
	$pageCss[] = cpcc_admin_shell_asset('/custom/chinaplanetcustomercenter/css/admin_dashboard.css');
}

llxHeader('', cpcc_ui_english_label('Order', 'Order / الطلب'), '', '', 0, 0, array(), $pageCss);
if($is_external) cpcc_portal_navigation($user, 'orders'); else cpcc_admin_shell_open('portal',cpcc_ui_english_label('Order Details', 'Order Details / تفاصيل الطلب'),cpcc_ui_english_label('View order status and line items.', 'عرض الطلب وحالته وبنوده'),'fa-shopping-cart',array('socid'=>(int)$order->socid));
?>
<main class="cpcc-dashboard" dir="<?php echo cpcc_ui_english_label('ltr', 'rtl'); ?>">
  <header class="cpcc-page-head"><div><span class="cpcc-eyebrow"><?php echo dol_escape_htmltag($order->ref); ?></span><h1><?php echo cpcc_ui_english_label('Order Details', 'تفاصيل الطلب / Order Details'); ?></h1><p><?php echo dol_escape_htmltag($orderStatusLabel); ?></p></div></header>
  <?php if ($canOpenCoreOrder) { ?><p><a class="button" target="_blank" rel="noopener" href="<?php echo dol_escape_htmltag(dol_buildpath('/commande/card.php',1).'?id='.(int)$order->id); ?><?php echo cpcc_ui_english_label('">Vision Advanced', '">Vision Advanced / فتح السجل المتقدم ↗'); ?></a></p><?php } ?>
  <section class="cpcc-financial-cards">
    <div class="cpcc-card"><span><?php echo cpcc_ui_english_label('Date', 'التاريخ'); ?></span><strong><?php echo dol_escape_htmltag(dol_print_date($order->date,'day')); ?></strong><small>Order date</small></div>
    <div class="cpcc-card"><span><?php echo cpcc_ui_english_label('Total', 'الإجمالي'); ?></span><strong><?php echo cpcc_portal_money($orderTotal).' '.dol_escape_htmltag($orderCurrency); ?></strong><small>Total incl. tax</small></div>
    <div class="cpcc-card"><span><?php echo cpcc_ui_english_label('Status', 'الحالة / Status'); ?></span><strong><?php echo dol_escape_htmltag($orderStatusLabel); ?></strong><small><?php echo cpcc_ui_english_label('Current order status', 'Current order status / حالة الطلب الحالية'); ?></small></div>
  </section>
  <section class="cpcc-commercial-trace" aria-label="Commercial trace"><h2><?php echo cpcc_ui_english_label('Order Journey', 'مسار الطلب / Order Journey'); ?></h2><div class="cpcc-trace-grid">
    <article><span><?php echo cpcc_ui_english_label('Project', 'المشروع / Project'); ?></span><?php if($orderProject){$url=cpcc_portal_scoped_url('/chinaplanetcustomercenter/project.php',$is_external,$order->socid,array('id'=>(int)$orderProject->rowid));?><a href="<?php echo dol_escape_htmltag($url); ?>"><?php echo dol_escape_htmltag($orderProject->ref); ?></a><?php }else{ ?><strong><?php echo cpcc_ui_english_label('Not linked', 'غير مرتبط / Not linked'); ?></strong><?php } ?></article>
    <article><span><?php echo cpcc_ui_english_label('Quotation', 'عرض السعر / Quotation'); ?></span><?php if($orderTrace['quotations']){foreach($orderTrace['quotations'] as $quotation){$url=cpcc_portal_scoped_url('/chinaplanetcustomercenter/quotation.php',$is_external,$order->socid,array('id'=>(int)$quotation->rowid));?><a href="<?php echo dol_escape_htmltag($url); ?>"><?php echo dol_escape_htmltag($quotation->ref); ?></a><?php }}else{ ?><strong><?php echo cpcc_ui_english_label('Not linked', 'غير مرتبط / Not linked'); ?></strong><?php } ?></article>
    <article><span><?php echo cpcc_ui_english_label('Sales Order', 'طلب البيع / Sales Order'); ?></span><strong><?php echo dol_escape_htmltag($order->ref); ?></strong></article>
    <article><span><?php echo cpcc_ui_english_label('Invoice', 'الفاتورة / Invoice'); ?></span><?php if($orderTrace['invoices']){foreach($orderTrace['invoices'] as $invoice){$url=cpcc_portal_scoped_url('/chinaplanetcustomercenter/invoice.php',$is_external,$order->socid,array('id'=>(int)$invoice->rowid));?><a href="<?php echo dol_escape_htmltag($url); ?>"><?php echo dol_escape_htmltag($invoice->ref); ?></a><?php }}else{ ?><strong><?php echo cpcc_ui_english_label('Pending', 'بانتظار الفاتورة / Pending'); ?></strong><?php } ?></article>
    <article><span><?php echo cpcc_ui_english_label('Shipment', 'الشحنة / Shipment'); ?></span><?php if($orderTrace['shipments']){foreach($orderTrace['shipments'] as $shipment){$url=cpcc_portal_scoped_url('/chinaplanetlogistics/card.php',$is_external,$order->socid,array('id'=>(int)$shipment->rowid));?><a href="<?php echo dol_escape_htmltag($url); ?>"><?php echo dol_escape_htmltag($shipment->ref); ?></a><?php }}else{ ?><strong><?php echo cpcc_ui_english_label('Pending', 'بانتظار الربط / Pending'); ?></strong><?php } ?></article>
  </div></section>
  <section class="cpcc-section-head"><div><h2><?php echo cpcc_ui_english_label('Order lines', 'بنود الطلب'); ?></h2><p>Customer-approved sales order lines only.</p></div></section>
  <div class="cpcc-table-wrap"><table class="cpcc-table"><thead><tr><th><?php echo cpcc_ui_english_label('Description', 'الوصف'); ?></th><th><?php echo cpcc_ui_english_label('Quantity', 'الكمية'); ?></th><th><?php echo cpcc_ui_english_label('Unit price', 'سعر الوحدة'); ?></th><th><?php echo cpcc_ui_english_label('Total', 'الإجمالي'); ?></th></tr></thead><tbody>
  <?php if (empty($order->lines)) { ?><tr><td colspan="4"><?php echo cpcc_ui_english_label('No line items.', 'لا توجد بنود.'); ?></td></tr><?php } ?>
  <?php foreach ($order->lines as $line) { $linePrice=$orderHasMulticurrency&&(float)$line->multicurrency_subprice!=0?(float)$line->multicurrency_subprice:(float)$line->subprice; $lineTotal=$orderHasMulticurrency&&(float)$line->multicurrency_total_ttc!=0?(float)$line->multicurrency_total_ttc:(float)$line->total_ttc; ?><tr><td><?php echo dol_escape_htmltag($line->desc); ?></td><td><?php echo dol_escape_htmltag((string)$line->qty); ?></td><td><?php echo cpcc_portal_money($linePrice).' '.dol_escape_htmltag($orderCurrency); ?></td><td><?php echo cpcc_portal_money($lineTotal).' '.dol_escape_htmltag($orderCurrency); ?></td></tr><?php } ?>
  </tbody></table></div>
</main>
<?php if(!$is_external) cpcc_admin_shell_close(); llxFooter();
