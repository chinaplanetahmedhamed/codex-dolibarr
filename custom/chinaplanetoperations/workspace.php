<?php
require $_SERVER['DOCUMENT_ROOT'].'/main.inc.php';
require_once __DIR__.'/lib/main.lib.php';
require_once __DIR__.'/class/OperationsService.class.php';
require_once __DIR__.'/class/InternalTaskService.class.php';
require_once __DIR__.'/../chinaplanetlogistics/lib/chinaplanetlogistics.lib.php';
require_once DOL_DOCUMENT_ROOT.'/custom/chinaplanetcustomercenter/lib/admin_shell.lib.php';

cpo_require($user, 'read');
$langs->load('chinaplanetoperations@chinaplanetoperations');
$entity = (int) $conf->entity;
$operationsService = new OperationsService($db);
$taskService = new InternalTaskService($db, $user, $entity);
$taskAuth = $taskService->authorization();
$canManage = !empty($user->admin) || cpo_can($user, 'operation_write');
$canCreateOperation = cpo_can($user, 'operation_write');
$canReadCustomers = !empty($user->admin) || $user->hasRight('societe', 'lire');
$canReadOrders = !empty($user->admin) || $user->hasRight('commande', 'lire');
$canReadProjects = !empty($user->admin) || $user->hasRight('projet', 'all', 'lire');
$canReadShipments = !empty($user->admin) || $user->hasRight('chinaplanetlogistics', 'read');
$canWriteShipments = !empty($user->admin) || $user->hasRight('chinaplanetlogistics', 'write');
$customerAccessSql = !empty($user->admin) || $user->hasRight('societe', 'client', 'voir') ? '' : ' AND EXISTS (SELECT 1 FROM '.MAIN_DB_PREFIX.'societe_commerciaux csc WHERE csc.fk_soc=s.rowid AND csc.fk_user='.(int) $user->id.')';
$view = GETPOST('view', 'aZ09');
if (!in_array($view, array('manager', 'my', 'customers', 'customer'), true)) $view = $canManage ? 'manager' : 'my';
if (!$canManage && $view === 'manager') $view = 'my';
if (!$canReadCustomers && in_array($view, array('customers', 'customer'), true)) accessforbidden();

$rows = static function ($db, $sql) {
    $out = array();
    $res = $db->query($sql);
    while ($res && ($obj = $db->fetch_object($res))) $out[] = get_object_vars($obj);
    return $out;
};
$esc = static function ($value) { return dol_escape_htmltag((string) $value); };
$userLabel = static function (array $row) {
    $name = trim((string) ($row['owner_firstname'] ?? '').' '.(string) ($row['owner_lastname'] ?? ''));
    return $name !== '' ? $name : ((string) ($row['owner_login'] ?? '') ?: 'غير مسند');
};
$isDone = static function ($stage) { return in_array((string) $stage, array('completed', 'cancelled'), true); };
$isOverdue = static function ($date, $done = false) { return !$done && $date && strtotime((string) $date) < time(); };

$operationStats = $operationsService->stats($user);
$allOperations = $canManage ? $operationsService->search() : array();
$myTasks = array();
$taskError = '';
try {
    if ($taskAuth->canOpenBoard()) $myTasks = $taskService->listTasks(array('view'=>'my'));
} catch (Throwable $e) {
    $taskError = 'لوحة المهام غير جاهزة الآن. افتح سجل العمليات أو اطلب من المدير مراجعة إعداد الفريق.';
}
$myOpenTasks = array_values(array_filter($myTasks, static function ($task) {
    return empty($task['date_archived']) && !in_array((string) $task['status'], array('done', 'cancelled', 'deleted'), true);
}));

$shipmentCountSql = $canReadShipments ? ',COUNT(DISTINCT l.rowid) shipment_count' : ',0 shipment_count';
$shipmentJoinSql = $canReadShipments ? " LEFT JOIN ".MAIN_DB_PREFIX."chinaplanetlogistics l ON l.entity=s.entity AND l.fk_soc=s.rowid" : '';
$customerSortSql = $canReadShipments ? 'MAX(COALESCE(l.tms,o.tms,s.tms))' : 'MAX(COALESCE(o.tms,s.tms))';
$customers = !$canReadCustomers ? array() : $rows($db, "SELECT s.rowid,s.nom,s.code_client,COUNT(DISTINCT o.rowid) operation_count,COUNT(DISTINCT c.rowid) contact_count".$shipmentCountSql.
    " FROM ".MAIN_DB_PREFIX."societe s".
    " LEFT JOIN ".MAIN_DB_PREFIX."cpo_operation o ON o.entity=s.entity AND o.fk_soc=s.rowid AND o.status=1".
    " LEFT JOIN ".MAIN_DB_PREFIX."socpeople c ON c.entity=s.entity AND c.fk_soc=s.rowid AND c.statut=1".
    $shipmentJoinSql.
    " WHERE s.entity=".$entity." AND s.status=1 AND s.client>0".$customerAccessSql.
    " GROUP BY s.rowid,s.nom,s.code_client ORDER BY ".$customerSortSql." DESC,s.nom LIMIT 100");

$customer = null;
$customerOperations = array();
$customerOrders = array();
$customerProjects = array();
$customerShipments = array();
$shipmentStatuses = chinaplanetlogistics_status_map();
if ($view === 'customer') {
    $socid = GETPOSTINT('socid');
    $res = $db->query("SELECT s.rowid,s.nom,s.code_client,s.address,s.town,s.phone,s.email FROM ".MAIN_DB_PREFIX."societe s WHERE s.entity=".$entity." AND s.status=1 AND s.client>0 AND s.rowid=".(int) $socid.$customerAccessSql." LIMIT 1");
    $obj = $res ? $db->fetch_object($res) : null;
    if (!$obj) accessforbidden();
    $customer = get_object_vars($obj);
    $customerOperations = $operationsService->search(array('socid'=>$socid));
    if ($canReadOrders) $customerOrders = $rows($db, "SELECT rowid,ref,fk_statut,total_ht,date_commande FROM ".MAIN_DB_PREFIX."commande WHERE entity=".$entity." AND fk_soc=".(int) $socid." ORDER BY rowid DESC LIMIT 20");
    if ($canReadProjects) $customerProjects = $rows($db, "SELECT rowid,ref,title,fk_statut FROM ".MAIN_DB_PREFIX."projet WHERE entity=".$entity." AND fk_soc=".(int) $socid." ORDER BY rowid DESC LIMIT 20");
    if ($canReadShipments) $customerShipments = $rows($db, "SELECT rowid,ref,container_no,bl_no,date_eta,fk_statut,customer_visible FROM ".MAIN_DB_PREFIX."chinaplanetlogistics WHERE entity=".$entity." AND fk_soc=".(int) $socid." ORDER BY rowid DESC LIMIT 20");
}

$morecss = array(cpo_asset('/custom/chinaplanetoperations/css/operations.css'), cpcc_admin_shell_asset('/custom/chinaplanetcustomercenter/css/admin_dashboard.css'));
llxHeader('', 'مساحة عمل كوكب الصين', '', '', 0, 0, array(), $morecss);
cpcc_admin_shell_open('workspace', 'مساحة عمل كوكب الصين', 'طلب واحد ← مسؤول واحد ← إجراء تالٍ واحد', 'fa-compass');
?>
<main class="cpo-page cpw-page" dir="rtl">
  <section class="cpw-hero">
    <div>
      <span class="cpo-eyebrow">CHINA PLANET · LIVE VISION-CHINA PLANET OS WORKSPACE</span>
      <h1><?php echo $view === 'my' ? 'عملي اليوم' : ($view === 'customer' ? $esc($customer['nom']) : ($view === 'customers' ? 'العملاء والعمليات' : 'لوحة المدير')); ?></h1>
      <p><?php echo $view === 'my' ? 'المهام المسموحة لك فقط، مرتبة حسب الحالة والأولوية والموعد.' : ($view === 'customer' ? 'كل العمليات والمشروعات وطلبات البيع المرتبطة بهذه الشركة في Vision-China Planet OS.' : 'قرارات اليوم من السجلات الحقيقية، دون بيانات تجريبية أو نظام موازٍ.'); ?></p>
    </div>
    <div class="cpw-hero-actions">
      <?php if ($canCreateOperation) { ?><a class="button button-save" href="operations/edit.php<?php echo $customer ? '?fk_soc='.(int) $customer['rowid'] : ''; ?>"><span class="fas fa-plus"></span> عملية جديدة</a><?php } ?>
      <a class="button" href="operations/index.php"><span class="fas fa-list"></span> سجل العمليات</a>
    </div>
  </section>

  <nav class="cpw-tabs" aria-label="مساحة العمل">
    <?php if ($canManage) { ?><a<?php echo $view === 'manager' ? ' class="active" aria-current="page"' : ''; ?> href="?view=manager">لوحة المدير</a><?php } ?>
    <?php if ($taskAuth->canOpenBoard()) { ?><a<?php echo $view === 'my' ? ' class="active" aria-current="page"' : ''; ?> href="?view=my">عملي اليوم <span><?php echo count($myOpenTasks); ?></span></a><?php } ?>
    <?php if ($canReadCustomers) { ?><a<?php echo in_array($view, array('customers','customer'), true) ? ' class="active" aria-current="page"' : ''; ?> href="?view=customers">العملاء والعمليات</a><?php } ?>
  </nav>

  <?php if ($canCreateOperation && in_array($view, array('manager', 'my'), true)) { ?>
  <section class="cpw-launch" aria-labelledby="cpw-launch-title">
    <div class="cpw-launch-head"><div><span>START HERE</span><h2 id="cpw-launch-title">ابدأ عملاً جديداً</h2><p>اختر نوع العمل؛ ستفتح شاشة China Planet OS الموجّهة مع الاحتفاظ بالسجل الأصلي في الخلفية.</p></div><a href="operations/index.php">سجل العمليات ←</a></div>
    <div class="cpw-launch-grid">
      <a href="operations/edit.php?service_type=order"><span class="fas fa-shopping-cart" aria-hidden="true"></span><strong>طلب عميل</strong><small>Customer order</small></a>
      <a href="operations/edit.php?service_type=sourcing"><span class="fas fa-search" aria-hidden="true"></span><strong>بحث عن منتج</strong><small>Product sourcing</small></a>
      <a href="operations/edit.php?service_type=purchasing"><span class="fas fa-boxes" aria-hidden="true"></span><strong>شراء ومتابعة مورد</strong><small>Purchasing</small></a>
      <a href="operations/edit.php?service_type=quality_control"><span class="fas fa-clipboard-check" aria-hidden="true"></span><strong>فحص جودة</strong><small>Quality control</small></a>
      <a href="operations/edit.php?service_type=shipping_booking"><span class="fas fa-ship" aria-hidden="true"></span><strong>حجز ومتابعة شحن</strong><small>Shipping booking</small></a>
      <a href="operations/edit.php?service_type=customs"><span class="fas fa-file-contract" aria-hidden="true"></span><strong>جمارك ومستندات</strong><small>Customs & documents</small></a>
    </div>
  </section>
  <?php } ?>

  <?php if ($view === 'manager') {
      $attention = array_values(array_filter($allOperations, static function ($op) use ($isDone, $isOverdue) {
          return !$isDone($op['stage']) && ($isOverdue($op['date_deadline']) || in_array($op['priority'], array('urgent','high'), true) || in_array($op['stage'], array('waiting_information','waiting_external','on_hold','corrective_action'), true));
      }));
      $unassigned = count(array_filter($allOperations, static function ($op) use ($isDone) { return !$isDone($op['stage']) && empty($op['fk_user_assign']); }));
  ?>
  <section class="cpw-summary" aria-label="ملخص العمل">
    <a href="operations/index.php"><small>العمليات المفتوحة</small><strong><?php echo (int) $operationStats['active']; ?></strong><span>فتح السجل</span></a>
    <a class="warn" href="operations/index.php"><small>تحتاج انتباهك</small><strong><?php echo count($attention); ?></strong><span>متأخرة، أولوية، أو انتظار</span></a>
    <a href="?view=my"><small>مهامي المفتوحة</small><strong><?php echo count($myOpenTasks); ?></strong><span>افتح عملي اليوم</span></a>
    <a class="<?php echo $unassigned ? 'warn' : ''; ?>" href="operations/index.php"><small>دون مسؤول</small><strong><?php echo $unassigned; ?></strong><span>عيّن مالكاً واضحاً</span></a>
  </section>
  <section class="cpo-panel cpw-section">
    <div class="cpo-panel-head"><div><h2>تحتاج قراراً أو متابعة</h2><p>الأولوية الأعلى أولاً، ثم الموعد.</p></div><a href="operations/index.php">كل العمليات</a></div>
    <div class="cpw-action-list">
      <?php foreach (array_slice($attention, 0, 12) as $op) { $done=$isDone($op['stage']); $overdue=$isOverdue($op['date_deadline'], $done); ?>
      <article class="cpw-action-card<?php echo $overdue ? ' overdue' : ''; ?>">
        <div class="cpw-action-main"><span class="cpw-chip"><?php echo $esc($op['ref']); ?></span><h3><?php echo $esc($op['title']); ?></h3><p><?php echo $esc($op['customer_name'] ?: 'بدون عميل'); ?> · المسؤول: <?php echo $esc($op['owner_login'] ?: 'غير مسند'); ?></p></div>
        <div class="cpw-next"><small>الإجراء التالي</small><strong><?php echo $esc($op['next_action'] ?: 'حدّد الإجراء التالي'); ?></strong><span><?php echo $overdue ? 'متأخر · ' : ''; ?><?php echo $esc($op['date_deadline'] ?: 'دون موعد'); ?></span></div>
        <a class="button" href="operations/view.php?id=<?php echo (int) $op['rowid']; ?>">فتح العملية</a>
      </article>
      <?php } ?>
      <?php if (!$attention) { ?><div class="cpw-empty">لا توجد عمليات عالية الأولوية أو متأخرة أو في انتظار حالياً.</div><?php } ?>
    </div>
  </section>
  <?php if ($canReadCustomers) { ?><section class="cpo-panel cpw-section">
    <div class="cpo-panel-head"><div><h2>أحدث العملاء والعمليات</h2><p>ابدأ من الشركة، ثم افتح العمل المرتبط بها.</p></div><a href="?view=customers">كل العملاء</a></div>
    <div class="cpw-customer-grid">
      <?php foreach (array_slice($customers, 0, 8) as $row) { ?><a href="?view=customer&amp;socid=<?php echo (int) $row['rowid']; ?>"><strong><?php echo $esc($row['nom']); ?></strong><span><?php echo (int) $row['operation_count']; ?> عمليات<?php echo $canReadShipments ? ' · '.(int) $row['shipment_count'].' شحنات' : ''; ?> · <?php echo (int) $row['contact_count']; ?> جهات اتصال</span><b>فتح ملف العميل ←</b></a><?php } ?>
    </div>
  </section><?php } ?>
  <?php } elseif ($view === 'my') { ?>
  <?php if ($taskError) { ?><div class="cpo-notice"><?php echo $esc($taskError); ?></div><?php } ?>
  <section class="cpw-summary" aria-label="ملخص مهامي">
    <a href="#my-work"><small>مفتوحة</small><strong><?php echo count($myOpenTasks); ?></strong><span>كل المهام المسموحة لك</span></a>
    <a class="warn" href="#my-work"><small>بانتظار طرف</small><strong><?php echo count(array_filter($myOpenTasks, static function ($task) { return $task['status'] === 'waiting'; })); ?></strong><span>سبب وموعد المتابعة</span></a>
    <a href="#my-work"><small>للمراجعة</small><strong><?php echo count(array_filter($myOpenTasks, static function ($task) { return $task['status'] === 'review'; })); ?></strong><span>تحتاج قرار المسؤول</span></a>
    <a href="#my-work"><small>متأخرة</small><strong><?php echo count(array_filter($myOpenTasks, static function ($task) use ($isOverdue) { return $isOverdue($task['date_deadline']); })); ?></strong><span>ابدأ بالأقدم</span></a>
  </section>
  <section class="cpo-panel cpw-section" id="my-work">
    <div class="cpo-panel-head"><div><h2>ما الذي أفعله الآن؟</h2><p>كل بطاقة توضح العميل، العملية، المسؤول، الموعد، وسبب الانتظار.</p></div><a href="tasks/board.php?view=my">لوحة المهام الكاملة</a></div>
    <div class="cpw-task-grid">
      <?php foreach ($myOpenTasks as $task) { $overdue=$isOverdue($task['date_deadline']); ?>
      <article class="cpw-task-card priority-<?php echo $esc($task['priority']); ?><?php echo $overdue ? ' overdue' : ''; ?>">
        <div class="cpw-card-top"><span class="cpw-chip"><?php echo $esc($task['stage_label_ar'] ?: $task['status']); ?></span><span><?php echo $esc($task['priority']); ?></span></div>
        <h3><?php echo $esc($task['title']); ?></h3>
        <p><?php echo $esc($task['operation_ref'] ? $task['operation_ref'].' · '.$task['operation_title'] : 'مهمة داخلية'); ?></p>
        <dl><div><dt>المسؤول</dt><dd><?php echo $esc($userLabel($task)); ?></dd></div><div><dt>الموعد</dt><dd><?php echo $overdue ? 'متأخر · ' : ''; ?><?php echo $esc($task['date_deadline'] ?: 'لم يحدد'); ?></dd></div><?php if ($task['status'] === 'waiting') { ?><div><dt>بانتظار</dt><dd><?php echo $esc(($task['waiting_on'] ?: 'طرف خارجي').' — '.($task['waiting_reason'] ?: 'لم يسجل السبب')); ?></dd></div><?php } ?></dl>
        <div class="cpw-card-actions"><a class="button button-save" href="tasks/card.php?id=<?php echo (int) $task['rowid']; ?>">سجل التقدم أو النتيجة</a><?php if (!empty($task['fk_operation'])) { ?><a class="button" href="operations/view.php?id=<?php echo (int) $task['fk_operation']; ?>">العملية</a><?php } ?></div>
      </article>
      <?php } ?>
      <?php if (!$myOpenTasks && !$taskError) { ?><div class="cpw-empty">لا توجد مهام مفتوحة مسندة إليك ضمن فرقك الحالية.</div><?php } ?>
    </div>
  </section>
  <?php } elseif ($view === 'customers') { ?>
  <section class="cpo-panel cpw-section">
    <div class="cpo-panel-head"><div><h2>العملاء والعمليات</h2><p>شركة العميل هي نقطة البداية، والأسماء هنا من Vision-China Planet OS.</p></div><a class="button" href="<?php echo DOL_URL_ROOT; ?>/societe/card.php?action=create&amp;type=c">إضافة عميل</a></div>
    <div class="cpw-customer-grid">
      <?php foreach ($customers as $row) { ?><a href="?view=customer&amp;socid=<?php echo (int) $row['rowid']; ?>"><strong><?php echo $esc($row['nom']); ?></strong><span><?php echo $esc($row['code_client'] ?: 'دون كود'); ?> · <?php echo (int) $row['operation_count']; ?> عمليات<?php echo $canReadShipments ? ' · '.(int) $row['shipment_count'].' شحنات' : ''; ?> · <?php echo (int) $row['contact_count']; ?> جهات اتصال</span><b>فتح ملف العميل ←</b></a><?php } ?>
      <?php if (!$customers) { ?><div class="cpw-empty">لا توجد شركات مسجلة كعملاء في هذا الكيان.</div><?php } ?>
    </div>
  </section>
  <?php } else { ?>
  <section class="cpw-customer-head">
    <div><span class="cpw-chip"><?php echo $esc($customer['code_client'] ?: 'عميل'); ?></span><h2><?php echo $esc($customer['nom']); ?></h2><p><?php echo $esc(implode(' · ', array_filter(array($customer['town'], $customer['phone'], $customer['email'])))); ?></p></div>
    <div class="cpw-card-actions"><a class="button" href="<?php echo DOL_URL_ROOT; ?>/societe/card.php?socid=<?php echo (int) $customer['rowid']; ?>">السجل الأصلي</a><?php if ($canWriteShipments) { ?><a class="button" href="<?php echo DOL_URL_ROOT; ?>/custom/chinaplanetlogistics/card.php?action=create&amp;socid=<?php echo (int) $customer['rowid']; ?>">شحنة جديدة لهذا العميل</a><?php } ?><?php if ($canCreateOperation) { ?><a class="button button-save" href="operations/edit.php?fk_soc=<?php echo (int) $customer['rowid']; ?>">عملية جديدة لهذا العميل</a><?php } ?></div>
  </section>
  <section class="cpw-summary" aria-label="ملخص العميل">
    <a href="#customer-operations"><small>العمليات</small><strong><?php echo count($customerOperations); ?></strong><span>الخدمات والمتابعات</span></a>
    <?php if ($canReadShipments) { ?><a href="#customer-shipments"><small>الشحنات</small><strong><?php echo count($customerShipments); ?></strong><span>التتبع والحالة الحالية</span></a><?php } ?>
    <?php if ($canReadOrders) { ?><a href="#customer-orders"><small>طلبات البيع</small><strong><?php echo count($customerOrders); ?></strong><span>السجلات الأصلية</span></a><?php } ?>
    <?php if ($canReadProjects) { ?><a href="#customer-projects"><small>المشروعات</small><strong><?php echo count($customerProjects); ?></strong><span>السجلات الأصلية</span></a><?php } ?>
  </section>
  <section class="cpo-panel cpw-section" id="customer-operations">
    <div class="cpo-panel-head"><div><h2>عمليات العميل</h2><p>الخطوة التالية والمسؤول والموعد واضحة لكل عملية.</p></div></div>
    <div class="cpw-action-list">
      <?php foreach ($customerOperations as $op) { $overdue=$isOverdue($op['date_deadline'], $isDone($op['stage'])); ?><article class="cpw-action-card<?php echo $overdue ? ' overdue' : ''; ?>"><div class="cpw-action-main"><span class="cpw-chip"><?php echo $esc($op['ref']); ?></span><h3><?php echo $esc($op['title']); ?></h3><p><?php echo $esc($langs->trans('Stage_'.$op['stage'])); ?> · <?php echo (int) $op['progress']; ?>%</p></div><div class="cpw-next"><small>الإجراء التالي</small><strong><?php echo $esc($op['next_action'] ?: 'حدّد الإجراء التالي'); ?></strong><span>المسؤول: <?php echo $esc($op['owner_login'] ?: 'غير مسند'); ?> · <?php echo $esc($op['date_deadline'] ?: 'دون موعد'); ?></span></div><a class="button" href="operations/view.php?id=<?php echo (int) $op['rowid']; ?>">فتح</a></article><?php } ?>
      <?php if (!$customerOperations) { ?><div class="cpw-empty">لا توجد عملية مرتبطة بهذا العميل بعد.</div><?php } ?>
    </div>
  </section>
  <?php if ($canReadShipments) { ?><section class="cpo-panel cpw-section" id="customer-shipments">
    <div class="cpo-panel-head"><div><h2>شحنات العميل</h2><p>كل شحنة مرتبطة بهذا العميل وتظهر في مركز الشحن.</p></div><a href="<?php echo DOL_URL_ROOT; ?>/custom/chinaplanetlogistics/list.php?socid=<?php echo (int) $customer['rowid']; ?>">فتح مركز الشحن</a></div>
    <?php foreach ($customerShipments as $shipment) { ?><a class="cpw-native-row" href="<?php echo DOL_URL_ROOT; ?>/custom/chinaplanetlogistics/card.php?id=<?php echo (int) $shipment['rowid']; ?>"><strong><?php echo $esc($shipment['ref']); ?></strong><span><?php echo $esc($shipment['container_no'] ?: 'رقم الحاوية قيد الانتظار'); ?> · <?php echo $esc($shipmentStatuses[(int) $shipment['fk_statut']] ?? 'حالة غير معروفة'); ?> · <?php echo $shipment['date_eta'] ? 'ETA '.$esc($shipment['date_eta']) : 'ETA غير محدد'; ?> · <?php echo !empty($shipment['customer_visible']) ? 'ظاهر للعميل' : 'داخلي'; ?></span></a><?php } ?>
    <?php if (!$customerShipments) { ?><div class="cpw-empty">لا توجد شحنات مرتبطة بهذا العميل.</div><?php } ?>
  </section><?php } ?>
  <div class="cpw-two-columns">
    <?php if ($canReadOrders) { ?><section class="cpo-panel cpw-section" id="customer-orders"><div class="cpo-panel-head"><h2>طلبات البيع</h2><a href="<?php echo DOL_URL_ROOT; ?>/commande/card.php?action=create&amp;socid=<?php echo (int) $customer['rowid']; ?>">طلب جديد</a></div><?php foreach ($customerOrders as $order) { ?><a class="cpw-native-row" href="<?php echo DOL_URL_ROOT; ?>/commande/card.php?id=<?php echo (int) $order['rowid']; ?>"><strong><?php echo $esc($order['ref']); ?></strong><span><?php echo price((float) $order['total_ht']); ?> · <?php echo $esc($order['date_commande'] ?: 'دون تاريخ'); ?></span></a><?php } ?><?php if (!$customerOrders) { ?><div class="cpw-empty">لا توجد طلبات بيع.</div><?php } ?></section><?php } ?>
    <?php if ($canReadProjects) { ?><section class="cpo-panel cpw-section" id="customer-projects"><div class="cpo-panel-head"><h2>المشروعات</h2><a href="<?php echo DOL_URL_ROOT; ?>/projet/card.php?action=create&amp;socid=<?php echo (int) $customer['rowid']; ?>">مشروع جديد</a></div><?php foreach ($customerProjects as $project) { ?><a class="cpw-native-row" href="<?php echo DOL_URL_ROOT; ?>/projet/card.php?id=<?php echo (int) $project['rowid']; ?>"><strong><?php echo $esc($project['ref']); ?></strong><span><?php echo $esc($project['title']); ?></span></a><?php } ?><?php if (!$customerProjects) { ?><div class="cpw-empty">لا توجد مشروعات.</div><?php } ?></section><?php } ?>
  </div>
  <?php } ?>
</main>
<?php cpcc_admin_shell_close(); llxFooter(); $db->close(); ?>
