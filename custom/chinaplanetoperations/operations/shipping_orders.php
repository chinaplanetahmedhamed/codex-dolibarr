<?php

require $_SERVER['DOCUMENT_ROOT'].'/main.inc.php';
require_once __DIR__.'/../lib/main.lib.php';
require_once __DIR__.'/../class/OperationsService.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/chinaplanetcustomercenter/lib/admin_shell.lib.php';
require_once DOL_DOCUMENT_ROOT.'/custom/chinaplanetcustomercenter/lib/portal.lib.php';

cpo_require($user, 'read');
$langs->load('chinaplanetoperations@chinaplanetoperations');

$service = new OperationsService($db);
$bucket = OperationsService::normalizeShippingOrderBucket(GETPOST('bucket', 'alphanohtml'));
$query = trim((string) GETPOST('q', 'alphanohtml'));
$requestedSocid = GETPOSTINT('socid');
$socid = cpcc_portal_customer_scope_id($db, $user, $requestedSocid);
$canOpenCustomerCenter = cpcc_portal_has_any_right($user, array(array('chinaplanetcustomercenter', 'read')));
$canWrite = cpo_can($user, 'operation_write');

$customers = array();
$customerSql = "SELECT rowid,nom FROM ".MAIN_DB_PREFIX."societe WHERE entity=".(int) $conf->entity." AND status=1 AND client IN (1,3) ORDER BY nom";
$customerResult = $db->query($customerSql);
if ($customerResult) while ($customer = $db->fetch_object($customerResult)) $customers[(int) $customer->rowid] = (string) $customer->nom;

$filters = array('bucket'=>$bucket, 'q'=>$query, 'socid'=>$socid, 'limit'=>500);
$rows = $service->searchShippingOrders($filters);
$stats = $service->shippingOrderStats($socid, false, $query);
$esc = function ($value) { return dol_escape_htmltag((string) $value); };
$bucketLabels = array(
    'all'=>array('Total Current', 'الإجمالي الحالي'),
    'active'=>array('Active', 'نشط'),
    'confirmed'=>array('Confirmed / SO Received', 'مؤكد / تم استلام SO'),
    'closed'=>array('Closed', 'مغلق'),
    'cancelled'=>array('Cancelled', 'ملغي'),
);
$bucketCounts = array('all'=>$stats['total_current']) + array_intersect_key($stats, array_flip(array('active','confirmed','closed','cancelled')));
$filterUrl = function ($targetBucket, array $extra = array()) use ($query, $socid) {
    $params = array('bucket'=>$targetBucket);
    if ($query !== '') $params['q'] = $query;
    if ($socid > 0) $params['socid'] = $socid;
    foreach ($extra as $key=>$value) $params[$key] = $value;
    return dol_buildpath('/chinaplanetoperations/operations/shipping_orders.php', 1).'?'.http_build_query($params);
};
$routeLabel = function ($row) {
    $pol = trim((string) ($row['route']['pol'] ?? ''));
    $pod = trim((string) ($row['route']['pod'] ?? ''));
    return $pol !== '' || $pod !== '' ? trim($pol.' → '.$pod, " →") : '';
};
$equipmentLabel = function ($row) {
    if ($row['equipment'] === '' && empty($row['quantity'])) return '';
    return trim($row['equipment'].' × '.(int) $row['quantity']);
};
$acceptedPrice = function ($row) {
    if ($row['accepted_total'] === null || $row['currency'] === '') return '';
    return number_format((float) $row['accepted_total'], 2, '.', ',').' '.$row['currency'];
};
$carrierReference = function ($row) {
    if ($row['shipping_order_no'] !== '') return 'SO: '.$row['shipping_order_no'];
    if ($row['carrier_booking_no'] !== '') return 'Booking: '.$row['carrier_booking_no'];
    return '';
};

$morecss = array(
    cpcc_admin_shell_asset('/custom/chinaplanetcustomercenter/css/admin_dashboard.css'),
    cpo_asset('/custom/chinaplanetoperations/css/operations.css'),
    cpo_asset('/custom/chinaplanetoperations/css/shipping_orders.css'),
);
llxHeader('', 'Shipping Orders / طلبات الشحن', '', '', 0, 0, array(), $morecss);
cpcc_admin_shell_open('shipping', 'Shipping Orders / طلبات الشحن', 'إدارة حجوزات الشحن المؤكدة من العميل دون خلطها بطلبات البيع أو الشحنات الفعلية', 'fa-clipboard-list');
?>
<main class="cpos" dir="rtl">
  <header class="cpos-hero">
    <div><span>SHIPPING BOOKING OPERATIONS</span><h1>طلبات الشحن / Shipping Orders</h1><p>سجل تشغيلي واحد من طلب العميل حتى تأكيد الناقل وربط الشحنة والإغلاق.</p></div>
    <?php if ($canWrite) { ?><a class="cpos-primary" href="<?php echo dol_buildpath('/chinaplanetoperations/operations/edit.php?service_type=shipping_booking', 1); ?>"><span class="fas fa-plus" aria-hidden="true"></span> حجز داخلي جديد / New internal booking</a><?php } ?>
  </header>

  <nav class="cpos-kpis" aria-label="Shipping order status summary">
    <?php foreach ($bucketLabels as $code=>$labels) { ?><a class="cpos-kpi cpos-kpi-<?php echo $esc($code); ?><?php echo $bucket === $code ? ' active' : ''; ?>" href="<?php echo $esc($filterUrl($code)); ?>"<?php echo $bucket === $code ? ' aria-current="page"' : ''; ?> aria-label="<?php echo $esc($labels[0].' '.$bucketCounts[$code]); ?>">
      <strong><?php echo (int) $bucketCounts[$code]; ?></strong><span><?php echo $esc($labels[1]); ?></span><small dir="ltr"><?php echo $esc($labels[0]); ?></small>
    </a><?php } ?>
  </nav>

  <section class="cpos-filter-panel" aria-labelledby="cpos-filter-title">
    <div><h2 id="cpos-filter-title">البحث والتصفية / Search & filters</h2><p role="status" aria-live="polite"><?php echo count($rows); ?> نتيجة / result(s)</p></div>
    <form method="get" class="cpos-filters">
      <label for="cpos-q"><span>بحث / Search</span><input id="cpos-q" name="q" value="<?php echo $esc($query); ?>" placeholder="CP-SB, customer, shipment, next action"></label>
      <label for="cpos-customer"><span>العميل / Customer</span><select id="cpos-customer" name="socid"><option value="0">كل العملاء / All customers</option><?php foreach ($customers as $id=>$name) { ?><option value="<?php echo (int) $id; ?>"<?php echo $socid === (int) $id ? ' selected' : ''; ?>><?php echo $esc($name); ?></option><?php } ?></select></label>
      <label for="cpos-bucket"><span>الحالة / Status</span><select id="cpos-bucket" name="bucket"><?php foreach ($bucketLabels as $code=>$labels) { ?><option value="<?php echo $esc($code); ?>"<?php echo $bucket === $code ? ' selected' : ''; ?>><?php echo $esc($labels[1].' / '.$labels[0]); ?></option><?php } ?></select></label>
      <div class="cpos-filter-actions"><button type="submit"><span class="fas fa-search" aria-hidden="true"></span> عرض / Show</button><a href="<?php echo dol_buildpath('/chinaplanetoperations/operations/shipping_orders.php', 1); ?>">إعادة الضبط / Reset</a></div>
    </form>
  </section>

  <?php if ($requestedSocid > 0 && $socid <= 0) { ?><div class="cpos-alert" role="alert">العميل المطلوب غير صالح أو خارج نطاق الكيان الحالي. / The requested customer is invalid or outside the current entity.</div><?php } ?>

  <section class="cpos-register" aria-labelledby="cpos-register-title">
    <div class="cpos-section-head"><div><h2 id="cpos-register-title">سجل طلبات الشحن / Shipping Orders Register</h2><p>CP-SB هو طلب حجز ChinaPlanet؛ Carrier SO ورقم طلب البيع في Dolibarr مراجع مختلفة.</p></div></div>
    <?php if (empty($rows)) { ?><div class="cpos-empty"><span class="fas fa-inbox" aria-hidden="true"></span><h3>لا توجد طلبات مطابقة / No matching shipping orders</h3><p>غيّر الفلاتر أو اعرض كل الطلبات الحالية.</p><a href="<?php echo dol_buildpath('/chinaplanetoperations/operations/shipping_orders.php', 1); ?>">عرض الكل / Show all</a></div><?php } else { ?>
    <div class="cpos-table-wrap">
      <table class="cpos-table" aria-labelledby="cpos-register-title">
        <caption class="sr-only">Internal Shipping Orders register</caption>
        <thead><tr><th scope="col">طلب ChinaPlanet</th><th scope="col">العميل</th><th scope="col">المسار والمعدات</th><th scope="col">السعر المقبول</th><th scope="col">Carrier SO</th><th scope="col">الحالة</th><th scope="col">التاريخ والإجراء التالي</th><th scope="col">الشحنة</th></tr></thead>
        <tbody><?php foreach ($rows as $row) { $route=$routeLabel($row);$equipment=$equipmentLabel($row);$price=$acceptedPrice($row);$carrier=$carrierReference($row); ?>
          <tr>
            <td><a href="<?php echo dol_buildpath('/chinaplanetoperations/operations/view.php?id='.(int) $row['operation_id'], 1); ?>"><bdi dir="ltr"><strong><?php echo $esc($row['reference']); ?></strong></bdi></a><small><?php echo $row['source'] === 'customer_direct' ? 'Customer direct / عميل مباشر' : 'Manual / Legacy · يدوي / قديم'; ?></small><?php if ($row['integrity_exception']) { ?><em class="cpos-issue">Ownership integrity exception</em><?php } ?></td>
            <td><?php if ($canOpenCustomerCenter && $row['customer_id'] > 0) { ?><a href="<?php echo dol_buildpath('/chinaplanetcustomercenter/board.php?socid='.(int) $row['customer_id'], 1); ?>" dir="auto"><?php echo $esc($row['customer_name'] ?: '—'); ?></a><?php } else { ?><span dir="auto"><?php echo $esc($row['customer_name'] ?: '—'); ?></span><?php } ?></td>
            <td><?php if ($route !== '') { ?><bdi dir="ltr"><?php echo $esc($route); ?></bdi><?php } else { ?><span class="cpos-muted">Not captured / غير مسجل</span><?php } ?><?php if ($equipment !== '') { ?><small><bdi dir="ltr"><?php echo $esc($equipment); ?></bdi></small><?php } ?></td>
            <td><?php if ($price !== '') { ?><bdi dir="ltr" class="cpos-money"><?php echo $esc($price); ?></bdi><?php } else { ?><span class="cpos-muted">Not captured / غير مسجل</span><?php } ?></td>
            <td><?php if ($carrier !== '') { ?><bdi dir="ltr"><?php echo $esc($carrier); ?></bdi><?php } else { ?><span class="cpos-muted">Not received / لم يستلم</span><?php } ?></td>
            <td><span class="cpos-status cpos-status-<?php echo $esc($row['lifecycle_bucket']); ?>"><?php echo $esc($bucketLabels[$row['lifecycle_bucket']][1].' / '.$bucketLabels[$row['lifecycle_bucket']][0]); ?></span><?php if ($row['confirmation_incomplete']) { ?><em class="cpos-issue">Confirmation data incomplete</em><?php } ?></td>
            <td><bdi dir="ltr"><?php echo $esc(substr($row['accepted_at'] ?: $row['created_at'], 0, 10)); ?></bdi><small dir="auto"><?php echo $esc($row['next_action'] ?: '—'); ?></small></td>
            <td><?php if ($row['shipment_id'] > 0 && $row['shipment_reference'] !== '') { ?><a href="<?php echo dol_buildpath('/chinaplanetlogistics/card.php?id='.(int) $row['shipment_id'], 1); ?>"><bdi dir="ltr"><?php echo $esc($row['shipment_reference']); ?></bdi></a><?php } elseif ($row['shipment_integrity_exception']) { ?><em class="cpos-issue">Shipment ownership mismatch</em><?php } else { ?><span class="cpos-muted">Not linked / غير مرتبطة</span><?php } ?></td>
          </tr>
        <?php } ?></tbody>
      </table>
    </div>
    <div class="cpos-cards" role="list"><?php foreach ($rows as $row) { $route=$routeLabel($row);$equipment=$equipmentLabel($row);$price=$acceptedPrice($row);$carrier=$carrierReference($row); ?>
      <article class="cpos-card" role="listitem">
        <header><div><a href="<?php echo dol_buildpath('/chinaplanetoperations/operations/view.php?id='.(int) $row['operation_id'], 1); ?>"><bdi dir="ltr"><?php echo $esc($row['reference']); ?></bdi></a><small><?php echo $row['source'] === 'customer_direct' ? 'Customer direct / عميل مباشر' : 'Manual / Legacy · يدوي / قديم'; ?></small></div><span class="cpos-status cpos-status-<?php echo $esc($row['lifecycle_bucket']); ?>"><?php echo $esc($bucketLabels[$row['lifecycle_bucket']][1]); ?></span></header>
        <?php if ($row['integrity_exception']) { ?><div class="cpos-card-alert">Ownership integrity exception — accepted evidence hidden</div><?php } elseif ($row['confirmation_incomplete']) { ?><div class="cpos-card-alert">Confirmation data incomplete / بيانات التأكيد ناقصة</div><?php } ?>
        <dl>
          <div><dt>العميل / Customer</dt><dd dir="auto"><?php echo $esc($row['customer_name'] ?: '—'); ?></dd></div>
          <div><dt>المسار / Route</dt><dd><bdi dir="ltr"><?php echo $esc($route ?: 'Not captured'); ?></bdi></dd></div>
          <div><dt>المعدات / Equipment</dt><dd><bdi dir="ltr"><?php echo $esc($equipment ?: 'Not captured'); ?></bdi></dd></div>
          <div><dt>السعر المقبول / Accepted</dt><dd class="cpos-money"><bdi dir="ltr"><?php echo $esc($price ?: 'Not captured'); ?></bdi></dd></div>
          <div><dt>Carrier SO / Booking</dt><dd><bdi dir="ltr"><?php echo $esc($carrier ?: 'Not received'); ?></bdi></dd></div>
          <div><dt>التاريخ / Date</dt><dd><bdi dir="ltr"><?php echo $esc(substr($row['accepted_at'] ?: $row['created_at'], 0, 10)); ?></bdi></dd></div>
          <div class="wide"><dt>الإجراء التالي / Next action</dt><dd dir="auto"><?php echo $esc($row['next_action'] ?: '—'); ?></dd></div>
          <div class="wide"><dt>الشحنة / Shipment</dt><dd><?php if ($row['shipment_id'] > 0 && $row['shipment_reference'] !== '') { ?><a href="<?php echo dol_buildpath('/chinaplanetlogistics/card.php?id='.(int) $row['shipment_id'], 1); ?>"><bdi dir="ltr"><?php echo $esc($row['shipment_reference']); ?></bdi></a><?php } elseif ($row['shipment_integrity_exception']) { ?><em class="cpos-issue">Shipment ownership mismatch / اختلاف ملكية الشحنة</em><?php } else { ?>Not linked / غير مرتبطة<?php } ?></dd></div>
        </dl>
      </article>
    <?php } ?></div>
    <?php } ?>
  </section>
</main>
<?php
cpcc_admin_shell_close();
llxFooter();
$db->close();
