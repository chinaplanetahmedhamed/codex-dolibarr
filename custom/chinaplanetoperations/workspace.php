<?php
require $_SERVER['DOCUMENT_ROOT'].'/main.inc.php';
require_once __DIR__.'/lib/main.lib.php';
require_once __DIR__.'/class/OperationsService.class.php';
require_once __DIR__.'/class/InternalTaskService.class.php';
require_once __DIR__.'/../chinaplanetlogistics/lib/chinaplanetlogistics.lib.php';
require_once DOL_DOCUMENT_ROOT.'/custom/chinaplanetcustomercenter/lib/admin_shell.lib.php';

cpo_require($user, 'read');
$langs->load('chinaplanetoperations@chinaplanetoperations');
$workspaceArabic = cpcc_ui_language() === 'ar_SA';
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
$userLabel = static function (array $row) use ($langs) {
    $name = trim((string) ($row['owner_firstname'] ?? '').' '.(string) ($row['owner_lastname'] ?? ''));
    return $name !== '' ? $name : ((string) ($row['owner_login'] ?? '') ?: $langs->trans('WsUnassigned'));
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
    $taskError = $langs->trans('WsTaskNotReady');
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
llxHeader('', $langs->trans('WsTitle'), '', '', 0, 0, array(), $morecss);
cpcc_admin_shell_open('workspace', $langs->trans('WsTitle'), '', 'fa-compass');
?>
<main class="cpo-page cpw-page" dir="<?php echo $workspaceArabic ? 'rtl' : 'ltr'; ?>">
  <section class="cpw-hero">
    <div>
      <span class="cpo-eyebrow"><?php echo $esc($langs->trans('WsEyebrow')); ?></span>
      <h1><?php echo $view === 'my' ? $langs->trans('WsToday') : ($view === 'customer' ? $esc($customer['nom']) : ($view === 'customers' ? $langs->trans('WsCustomersOperations') : $langs->trans('WsManager'))); ?></h1>
      <?php if (in_array($view, array('my', 'customer'), true)) { ?><p><?php echo $view === 'my' ? $langs->trans('WsMyHelp') : $langs->trans('WsCustomerHelp'); ?></p><?php } ?>
    </div>
    <div class="cpw-hero-actions">
      <?php if ($canCreateOperation) { ?><a class="button button-save" href="operations/edit.php<?php echo $customer ? '?fk_soc='.(int) $customer['rowid'] : ''; ?>"><span class="fas fa-plus" aria-hidden="true"></span> <?php echo $esc($langs->trans('WsNewOperation')); ?></a><?php } ?>
      <a class="button" href="operations/index.php"><span class="fas fa-list" aria-hidden="true"></span> <?php echo $esc($langs->trans('WsRegister')); ?></a>
    </div>
  </section>

  <nav class="cpw-tabs" aria-label="<?php echo $esc($langs->trans('WsNavigation')); ?>">
    <?php if ($canManage) { ?><a<?php echo $view === 'manager' ? ' class="active" aria-current="page"' : ''; ?> href="?view=manager"><?php echo $esc($langs->trans('WsManager')); ?></a><?php } ?>
    <?php if ($taskAuth->canOpenBoard()) { ?><a<?php echo $view === 'my' ? ' class="active" aria-current="page"' : ''; ?> href="?view=my"><?php echo $esc($langs->trans('WsToday')); ?> <span><?php echo count($myOpenTasks); ?></span></a><?php } ?>
    <?php if ($canReadCustomers) { ?><a<?php echo in_array($view, array('customers','customer'), true) ? ' class="active" aria-current="page"' : ''; ?> href="?view=customers"><?php echo $esc($langs->trans('WsCustomersOperations')); ?></a><?php } ?>
  </nav>

  <?php if ($canCreateOperation && in_array($view, array('manager', 'my'), true)) { ?>
  <section class="cpw-launch" aria-labelledby="cpw-launch-title">
    <div class="cpw-launch-head"><div><span><?php echo $esc($langs->trans('WsStartHere')); ?></span><h2 id="cpw-launch-title"><?php echo $esc($langs->trans('WsStartWork')); ?></h2><p><?php echo $esc($langs->trans('WsStartHelp')); ?></p></div><a href="operations/index.php"><?php echo $esc($langs->trans('WsRegisterLink')); ?></a></div>
    <div class="cpw-launch-grid">
      <a href="operations/edit.php?service_type=order"><span class="fas fa-shopping-cart" aria-hidden="true"></span><strong><?php echo $esc($langs->trans('Svc_order')); ?></strong><small><?php echo $esc($langs->trans('SvcHelp_order')); ?></small></a>
      <a href="operations/edit.php?service_type=sourcing"><span class="fas fa-search" aria-hidden="true"></span><strong><?php echo $esc($langs->trans('Svc_sourcing')); ?></strong><small><?php echo $esc($langs->trans('SvcHelp_sourcing')); ?></small></a>
      <a href="operations/edit.php?service_type=purchasing"><span class="fas fa-boxes" aria-hidden="true"></span><strong><?php echo $esc($langs->trans('Svc_purchasing')); ?></strong><small><?php echo $esc($langs->trans('SvcHelp_purchasing')); ?></small></a>
      <a href="operations/edit.php?service_type=quality_control"><span class="fas fa-clipboard-check" aria-hidden="true"></span><strong><?php echo $esc($langs->trans('Svc_quality_control')); ?></strong><small><?php echo $esc($langs->trans('SvcHelp_quality_control')); ?></small></a>
      <a href="operations/edit.php?service_type=shipping_booking"><span class="fas fa-ship" aria-hidden="true"></span><strong><?php echo $esc($langs->trans('Svc_shipping_booking')); ?></strong><small><?php echo $esc($langs->trans('SvcHelp_shipping_booking')); ?></small></a>
      <a href="operations/edit.php?service_type=customs"><span class="fas fa-file-contract" aria-hidden="true"></span><strong><?php echo $esc($langs->trans('Svc_customs')); ?></strong><small><?php echo $esc($langs->trans('SvcHelp_customs')); ?></small></a>
    </div>
  </section>
  <?php } ?>

  <?php if ($view === 'manager') {
      $attention = array_values(array_filter($allOperations, static function ($op) use ($isDone, $isOverdue) {
          return !$isDone($op['stage']) && ($isOverdue($op['date_deadline']) || in_array($op['priority'], array('urgent','high'), true) || in_array($op['stage'], array('waiting_information','waiting_external','on_hold','corrective_action'), true));
      }));
      $unassigned = count(array_filter($allOperations, static function ($op) use ($isDone) { return !$isDone($op['stage']) && empty($op['fk_user_assign']); }));
  ?>
  <section class="cpw-summary" aria-label="<?php echo $esc($langs->trans('WsWorkSummary')); ?>">
    <a href="operations/index.php"><small><?php echo $esc($langs->trans('WsOpenOperations')); ?></small><strong><?php echo (int) $operationStats['active']; ?></strong><span><?php echo $esc($langs->trans('WsOpenRegister')); ?></span></a>
    <a class="warn" href="operations/index.php"><small><?php echo $esc($langs->trans('WsNeedsAttention')); ?></small><strong><?php echo count($attention); ?></strong><span><?php echo $esc($langs->trans('WsAttentionHelp')); ?></span></a>
    <a href="?view=my"><small><?php echo $esc($langs->trans('WsMyOpenTasks')); ?></small><strong><?php echo count($myOpenTasks); ?></strong><span><?php echo $esc($langs->trans('WsOpenToday')); ?></span></a>
    <a class="<?php echo $unassigned ? 'warn' : ''; ?>" href="operations/index.php"><small><?php echo $esc($langs->trans('WsNoOwner')); ?></small><strong><?php echo $unassigned; ?></strong><span><?php echo $esc($langs->trans('WsAssignOwner')); ?></span></a>
  </section>
  <section class="cpo-panel cpw-section">
    <div class="cpo-panel-head"><div><h2><?php echo $esc($langs->trans('WsDecisionFollowup')); ?></h2><p><?php echo $esc($langs->trans('WsPriorityHelp')); ?></p></div><a href="operations/index.php"><?php echo $esc($langs->trans('WsAllOperations')); ?></a></div>
    <div class="cpw-action-list">
      <?php foreach (array_slice($attention, 0, 12) as $op) { $done=$isDone($op['stage']); $overdue=$isOverdue($op['date_deadline'], $done); ?>
      <article class="cpw-action-card<?php echo $overdue ? ' overdue' : ''; ?>">
        <div class="cpw-action-main"><span class="cpw-chip"><?php echo $esc($op['ref']); ?></span><h3><?php echo $esc($op['title']); ?></h3><p><?php echo $esc($op['customer_name'] ?: $langs->trans('WsNoCustomer')); ?> · <?php echo $esc($langs->trans('WsOwner')); ?>: <?php echo $esc($op['owner_login'] ?: $langs->trans('WsUnassigned')); ?></p></div>
        <div class="cpw-next"><small><?php echo $esc($langs->trans('WsNextAction')); ?></small><strong><?php echo $esc($op['next_action'] ?: $langs->trans('WsSetNextAction')); ?></strong><span><?php echo $overdue ? $langs->trans('WsOverduePrefix').' ' : ''; ?><?php echo $esc($op['date_deadline'] ?: $langs->trans('WsNoDeadline')); ?></span></div>
        <a class="button" href="operations/view.php?id=<?php echo (int) $op['rowid']; ?>"><?php echo $esc($langs->trans('WsOpenOperation')); ?></a>
      </article>
      <?php } ?>
      <?php if (!$attention) { ?><div class="cpw-empty"><?php echo $esc($langs->trans('WsNoAttention')); ?></div><?php } ?>
    </div>
  </section>
  <?php if ($canReadCustomers) { ?><section class="cpo-panel cpw-section">
    <div class="cpo-panel-head"><div><h2><?php echo $esc($langs->trans('WsRecentCustomers')); ?></h2><p><?php echo $esc($langs->trans('WsRecentCustomersHelp')); ?></p></div><a href="?view=customers"><?php echo $esc($langs->trans('WsAllCustomers')); ?></a></div>
    <div class="cpw-customer-grid">
      <?php foreach (array_slice($customers, 0, 8) as $row) { ?><a href="?view=customer&amp;socid=<?php echo (int) $row['rowid']; ?>"><strong><?php echo $esc($row['nom']); ?></strong><span><?php echo (int) $row['operation_count']; ?> <?php echo $esc($langs->trans('WsOperationsUnit')); ?><?php echo $canReadShipments ? ' · '.(int) $row['shipment_count'].' '.$langs->trans('WsShipmentsUnit') : ''; ?> · <?php echo (int) $row['contact_count']; ?> <?php echo $esc($langs->trans('WsContactsUnit')); ?></span><b><?php echo $esc($langs->trans('WsOpenCustomer')); ?></b></a><?php } ?>
    </div>
  </section><?php } ?>
  <?php } elseif ($view === 'my') { ?>
  <?php if ($taskError) { ?><div class="cpo-notice"><?php echo $esc($taskError); ?></div><?php } ?>
  <section class="cpw-summary" aria-label="<?php echo $esc($langs->trans('WsMySummary')); ?>">
    <a href="#my-work"><small><?php echo $esc($langs->trans('WsOpen')); ?></small><strong><?php echo count($myOpenTasks); ?></strong><span><?php echo $esc($langs->trans('WsAuthorizedTasks')); ?></span></a>
    <a class="warn" href="#my-work"><small><?php echo $esc($langs->trans('WsWaitingParty')); ?></small><strong><?php echo count(array_filter($myOpenTasks, static function ($task) { return $task['status'] === 'waiting'; })); ?></strong><span><?php echo $esc($langs->trans('WsWaitingHelp')); ?></span></a>
    <a href="#my-work"><small><?php echo $esc($langs->trans('WsForReview')); ?></small><strong><?php echo count(array_filter($myOpenTasks, static function ($task) { return $task['status'] === 'review'; })); ?></strong><span><?php echo $esc($langs->trans('WsReviewHelp')); ?></span></a>
    <a href="#my-work"><small><?php echo $esc($langs->trans('WsOverdue')); ?></small><strong><?php echo count(array_filter($myOpenTasks, static function ($task) use ($isOverdue) { return $isOverdue($task['date_deadline']); })); ?></strong><span><?php echo $esc($langs->trans('WsOldestFirst')); ?></span></a>
  </section>
  <section class="cpo-panel cpw-section" id="my-work">
    <div class="cpo-panel-head"><div><h2><?php echo $esc($langs->trans('WsWhatNext')); ?></h2><p><?php echo $esc($langs->trans('WsTaskCardsHelp')); ?></p></div><a href="tasks/board.php?view=my"><?php echo $esc($langs->trans('WsFullTaskBoard')); ?></a></div>
    <div class="cpw-task-grid">
      <?php foreach ($myOpenTasks as $task) { $overdue=$isOverdue($task['date_deadline']); ?>
      <article class="cpw-task-card priority-<?php echo $esc($task['priority']); ?><?php echo $overdue ? ' overdue' : ''; ?>">
        <div class="cpw-card-top"><span class="cpw-chip"><?php echo $esc($task[$workspaceArabic ? 'stage_label_ar' : 'stage_label_en'] ?: $langs->trans('TaskStatus_'.$task['status'])); ?></span><span><?php echo $esc($langs->trans('Priority_'.$task['priority'])); ?></span></div>
        <h3><?php echo $esc($task['title']); ?></h3>
        <p><?php echo $esc($task['operation_ref'] ? $task['operation_ref'].' · '.$task['operation_title'] : $langs->trans('WsInternalTask')); ?></p>
        <dl><div><dt><?php echo $esc($langs->trans('WsOwner')); ?></dt><dd><?php echo $esc($userLabel($task)); ?></dd></div><div><dt><?php echo $esc($langs->trans('WsDeadline')); ?></dt><dd><?php echo $overdue ? $langs->trans('WsOverduePrefix').' ' : ''; ?><?php echo $esc($task['date_deadline'] ?: $langs->trans('WsNotSet')); ?></dd></div><?php if ($task['status'] === 'waiting') { ?><div><dt><?php echo $esc($langs->trans('WsWaiting')); ?></dt><dd><?php echo $esc(($task['waiting_on'] ? (in_array($task['waiting_on'], InternalTaskService::waitingParties(), true) ? $langs->trans($task['waiting_on'] === 'other' ? 'WsOtherParty' : 'Party_'.$task['waiting_on']) : $task['waiting_on']) : $langs->trans('WsExternalParty')).' — '.($task['waiting_reason'] ?: $langs->trans('WsNoReason'))); ?></dd></div><?php } ?></dl>
        <div class="cpw-card-actions"><a class="button button-save" href="tasks/card.php?id=<?php echo (int) $task['rowid']; ?>"><?php echo $esc($langs->trans('WsRecordProgress')); ?></a><?php if (!empty($task['fk_operation'])) { ?><a class="button" href="operations/view.php?id=<?php echo (int) $task['fk_operation']; ?>"><?php echo $esc($langs->trans('WsOperation')); ?></a><?php } ?></div>
      </article>
      <?php } ?>
      <?php if (!$myOpenTasks && !$taskError) { ?><div class="cpw-empty"><?php echo $esc($langs->trans('WsNoMyTasks')); ?></div><?php } ?>
    </div>
  </section>
  <?php } elseif ($view === 'customers') { ?>
  <section class="cpo-panel cpw-section">
    <div class="cpo-panel-head"><div><h2><?php echo $esc($langs->trans('WsCustomersOperations')); ?></h2><p><?php echo $esc($langs->trans('WsCustomersHelp')); ?></p></div><a class="button" href="<?php echo DOL_URL_ROOT; ?>/societe/card.php?action=create&amp;type=c"><?php echo $esc($langs->trans('WsAddCustomer')); ?></a></div>
    <div class="cpw-customer-grid">
      <?php foreach ($customers as $row) { ?><a href="?view=customer&amp;socid=<?php echo (int) $row['rowid']; ?>"><strong><?php echo $esc($row['nom']); ?></strong><span><?php echo $esc($row['code_client'] ?: $langs->trans('WsNoCode')); ?> · <?php echo (int) $row['operation_count']; ?> <?php echo $esc($langs->trans('WsOperationsUnit')); ?><?php echo $canReadShipments ? ' · '.(int) $row['shipment_count'].' '.$langs->trans('WsShipmentsUnit') : ''; ?> · <?php echo (int) $row['contact_count']; ?> <?php echo $esc($langs->trans('WsContactsUnit')); ?></span><b><?php echo $esc($langs->trans('WsOpenCustomer')); ?></b></a><?php } ?>
      <?php if (!$customers) { ?><div class="cpw-empty"><?php echo $esc($langs->trans('WsNoCustomers')); ?></div><?php } ?>
    </div>
  </section>
  <?php } else { ?>
  <section class="cpw-customer-head">
    <div><span class="cpw-chip"><?php echo $esc($customer['code_client'] ?: $langs->trans('WsCustomer')); ?></span><h2><?php echo $esc($customer['nom']); ?></h2><p><?php echo $esc(implode(' · ', array_filter(array($customer['town'], $customer['phone'], $customer['email'])))); ?></p></div>
    <div class="cpw-card-actions"><a class="button" href="<?php echo DOL_URL_ROOT; ?>/societe/card.php?socid=<?php echo (int) $customer['rowid']; ?>"><?php echo $esc($langs->trans('WsNativeRecord')); ?></a><?php if ($canWriteShipments) { ?><a class="button" href="<?php echo DOL_URL_ROOT; ?>/custom/chinaplanetlogistics/card.php?action=create&amp;socid=<?php echo (int) $customer['rowid']; ?>"><?php echo $esc($langs->trans('WsNewCustomerShipment')); ?></a><?php } ?><?php if ($canCreateOperation) { ?><a class="button button-save" href="operations/edit.php?fk_soc=<?php echo (int) $customer['rowid']; ?>"><?php echo $esc($langs->trans('WsNewCustomerOperation')); ?></a><?php } ?></div>
  </section>
  <section class="cpw-summary" aria-label="<?php echo $esc($langs->trans('WsCustomerSummary')); ?>">
    <a href="#customer-operations"><small><?php echo $esc($langs->trans('WsOperations')); ?></small><strong><?php echo count($customerOperations); ?></strong><span><?php echo $esc($langs->trans('WsServicesFollowups')); ?></span></a>
    <?php if ($canReadShipments) { ?><a href="#customer-shipments"><small><?php echo $esc($langs->trans('WsShipments')); ?></small><strong><?php echo count($customerShipments); ?></strong><span><?php echo $esc($langs->trans('WsTrackingStatus')); ?></span></a><?php } ?>
    <?php if ($canReadOrders) { ?><a href="#customer-orders"><small><?php echo $esc($langs->trans('WsCustomerOrders')); ?></small><strong><?php echo count($customerOrders); ?></strong><span><?php echo $esc($langs->trans('WsNativeRecords')); ?></span></a><?php } ?>
    <?php if ($canReadProjects) { ?><a href="#customer-projects"><small><?php echo $esc($langs->trans('WsProjects')); ?></small><strong><?php echo count($customerProjects); ?></strong><span><?php echo $esc($langs->trans('WsNativeRecords')); ?></span></a><?php } ?>
  </section>
  <section class="cpo-panel cpw-section" id="customer-operations">
    <div class="cpo-panel-head"><div><h2><?php echo $esc($langs->trans('GuidedCustomerOperations')); ?></h2><p><?php echo $esc($langs->trans('GuidedCustomerOperationsHelp')); ?></p></div></div>
    <div class="cpw-action-list">
      <?php foreach ($customerOperations as $op) { $overdue=$isOverdue($op['date_deadline'], $isDone($op['stage'])); ?><article class="cpw-action-card<?php echo $overdue ? ' overdue' : ''; ?>"><div class="cpw-action-main"><span class="cpw-chip"><?php echo $esc($op['ref']); ?></span><h3><?php echo $esc($op['title']); ?></h3><p><?php echo $esc($langs->trans('Stage_'.$op['stage'])); ?> · <?php echo (int) $op['progress']; ?>%</p></div><div class="cpw-next"><small><?php echo $esc($langs->trans('GuidedNextAction')); ?></small><strong><?php echo $esc($op['next_action'] ?: $langs->trans('GuidedSetNextAction')); ?></strong><span><?php echo $esc($langs->trans('GuidedOwnerLabel')); ?>: <?php echo $esc($op['owner_login'] ?: $langs->trans('GuidedUnassigned')); ?> · <?php echo $esc($op['date_deadline'] ?: $langs->trans('GuidedNoDeadline')); ?></span></div><a class="button" href="operations/view.php?id=<?php echo (int) $op['rowid']; ?>"><?php echo $esc($langs->trans('GuidedOpen')); ?></a></article><?php } ?>
      <?php if (!$customerOperations) { ?><div class="cpw-empty"><?php echo $esc($langs->trans('GuidedNoCustomerOperations')); ?></div><?php } ?>
    </div>
  </section>
  <?php if ($canReadShipments) { ?><section class="cpo-panel cpw-section" id="customer-shipments">
    <div class="cpo-panel-head"><div><h2><?php echo $esc($langs->trans('WsCustomerShipments')); ?></h2><p><?php echo $esc($langs->trans('WsCustomerShipmentsHelp')); ?></p></div><a href="<?php echo DOL_URL_ROOT; ?>/custom/chinaplanetlogistics/list.php?socid=<?php echo (int) $customer['rowid']; ?>"><?php echo $esc($langs->trans('WsOpenShippingCenter')); ?></a></div>
    <?php foreach ($customerShipments as $shipment) { ?><a class="cpw-native-row" href="<?php echo DOL_URL_ROOT; ?>/custom/chinaplanetlogistics/card.php?id=<?php echo (int) $shipment['rowid']; ?>"><strong><?php echo $esc($shipment['ref']); ?></strong><span><?php echo $esc($shipment['container_no'] ?: $langs->trans('WsContainerPending')); ?> · <?php echo $esc(isset($shipmentStatuses[(int) $shipment['fk_statut']]) ? $langs->trans('WsShipmentStatus'.(int) $shipment['fk_statut']) : $langs->trans('WsUnknownStatus')); ?> · <?php echo $shipment['date_eta'] ? 'ETA '.$esc($shipment['date_eta']) : $langs->trans('WsEtaNotSet'); ?> · <?php echo !empty($shipment['customer_visible']) ? $langs->trans('WsVisibleToCustomer') : $langs->trans('WsInternal'); ?></span></a><?php } ?>
    <?php if (!$customerShipments) { ?><div class="cpw-empty"><?php echo $esc($langs->trans('WsNoShipments')); ?></div><?php } ?>
  </section><?php } ?>
  <div class="cpw-two-columns">
    <?php if ($canReadOrders) { ?><section class="cpo-panel cpw-section" id="customer-orders"><div class="cpo-panel-head"><h2><?php echo $esc($langs->trans('WsCustomerOrders')); ?></h2><a href="<?php echo DOL_URL_ROOT; ?>/commande/card.php?action=create&amp;socid=<?php echo (int) $customer['rowid']; ?>"><?php echo $esc($langs->trans('WsNewOrder')); ?></a></div><?php foreach ($customerOrders as $order) { ?><a class="cpw-native-row" href="<?php echo DOL_URL_ROOT; ?>/commande/card.php?id=<?php echo (int) $order['rowid']; ?>"><strong><?php echo $esc($order['ref']); ?></strong><span><?php echo price((float) $order['total_ht']); ?> · <?php echo $esc($order['date_commande'] ?: $langs->trans('WsNoDate')); ?></span></a><?php } ?><?php if (!$customerOrders) { ?><div class="cpw-empty"><?php echo $esc($langs->trans('WsNoOrders')); ?></div><?php } ?></section><?php } ?>
    <?php if ($canReadProjects) { ?><section class="cpo-panel cpw-section" id="customer-projects"><div class="cpo-panel-head"><h2><?php echo $esc($langs->trans('WsProjects')); ?></h2><a href="<?php echo DOL_URL_ROOT; ?>/projet/card.php?action=create&amp;socid=<?php echo (int) $customer['rowid']; ?>"><?php echo $esc($langs->trans('WsNewProject')); ?></a></div><?php foreach ($customerProjects as $project) { ?><a class="cpw-native-row" href="<?php echo DOL_URL_ROOT; ?>/projet/card.php?id=<?php echo (int) $project['rowid']; ?>"><strong><?php echo $esc($project['ref']); ?></strong><span><?php echo $esc($project['title']); ?></span></a><?php } ?><?php if (!$customerProjects) { ?><div class="cpw-empty"><?php echo $esc($langs->trans('WsNoProjects')); ?></div><?php } ?></section><?php } ?>
  </div>
  <?php } ?>


</main>
<?php cpcc_admin_shell_close(); llxFooter(); $db->close(); ?>
