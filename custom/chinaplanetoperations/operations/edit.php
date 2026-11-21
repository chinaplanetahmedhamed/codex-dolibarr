<?php
define('CSRFCHECK_WITH_TOKEN', 1);
require $_SERVER['DOCUMENT_ROOT'].'/main.inc.php';
require_once __DIR__.'/../lib/main.lib.php';
require_once __DIR__.'/../class/OperationsService.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/chinaplanetcustomercenter/lib/admin_shell.lib.php';
cpo_require($user, 'operation_write');
$canReadCustomers = !empty($user->admin) || $user->hasRight('societe', 'lire');
if (!$canReadCustomers) accessforbidden();
$canCreateCustomers = !empty($user->admin) || $user->hasRight('societe', 'creer');
$canReadOrders = !empty($user->admin) || $user->hasRight('commande', 'lire');
$canReadProjects = !empty($user->admin) || $user->hasRight('projet', 'all', 'lire');
$langs->load('chinaplanetoperations@chinaplanetoperations');
$service = new OperationsService($db);
$id = GETPOSTINT('id');
$action = GETPOST('action', 'aZ09');
$operation = $id ? $service->get($id) : null;
if ($id && !$operation) accessforbidden();
$type = $operation['service_type'] ?? GETPOST('service_type', 'alphanohtml');
if (!in_array($type, OperationsService::serviceTypes(), true)) $type = 'general';
$selectedSocid = $operation ? (int) $operation['fk_soc'] : (GETPOSTINT('fk_soc') ?: GETPOSTINT('socid'));
$operationCreateUrl = dol_buildpath('/custom/chinaplanetoperations/operations/edit.php', 1).'?service_type='.rawurlencode($type);
$newCustomerUrl = DOL_URL_ROOT.'/societe/card.php?action=create&client=1&backtopage='.rawurlencode($operationCreateUrl);
$customerAccessSql = !empty($user->admin) || $user->hasRight('societe', 'client', 'voir') ? '' : ' AND EXISTS (SELECT 1 FROM '.MAIN_DB_PREFIX.'societe_commerciaux csc WHERE csc.fk_soc=s.rowid AND csc.fk_user='.(int) $user->id.')';

$companies = array(0=>'— اختر العميل أولاً —');
$res = $db->query("SELECT s.rowid,s.nom FROM ".MAIN_DB_PREFIX."societe s WHERE s.entity=".(int) $conf->entity." AND s.status=1 AND s.client>0".$customerAccessSql." ORDER BY s.nom LIMIT 1000");
while ($res && ($o = $db->fetch_object($res))) $companies[(int) $o->rowid] = $o->nom;
if ($operation && (!isset($companies[(int) $operation['fk_soc']]) || (int) $operation['fk_soc'] <= 0)) accessforbidden();
$users = array(0=>'— غير مسند —');
$res = $db->query("SELECT rowid,login,firstname,lastname FROM ".MAIN_DB_PREFIX."user WHERE entity=".(int) $conf->entity." AND statut=1 AND COALESCE(fk_soc,0)=0 ORDER BY firstname,lastname,login");
while ($res && ($o = $db->fetch_object($res))) $users[(int) $o->rowid] = trim($o->firstname.' '.$o->lastname) ?: $o->login;
$assignableUserIds = array_keys($users);
$assignableUserIds = array_values(array_filter(array_map('intval', $assignableUserIds)));
$teams = array();
$res = $db->query("SELECT g.rowid,g.nom,GROUP_CONCAT(gu.fk_user ORDER BY gu.fk_user) member_ids FROM ".MAIN_DB_PREFIX."usergroup g LEFT JOIN ".MAIN_DB_PREFIX."usergroup_user gu ON gu.entity=g.entity AND gu.fk_usergroup=g.rowid WHERE g.entity=".(int) $conf->entity." GROUP BY g.rowid,g.nom ORDER BY g.nom");
while ($res && ($o = $db->fetch_object($res))) {
    $members = array_values(array_intersect($assignableUserIds, array_filter(array_map('intval', explode(',', (string) $o->member_ids)))));
    if ($members) $teams[] = array('id'=>(int) $o->rowid,'label'=>$o->nom,'members'=>$members);
}
$taskMigration = new InternalTaskMigration($db);
$taskBoardReady = $taskMigration->isReady(true);
$taskBoardReason = $taskBoardReady ? 'READY' : $taskMigration->readinessReason();
$createReady = $taskBoardReady && !empty($teams);
$selectedTeamId = 0;
if ($id > 0) {
    $res = $db->query("SELECT fk_usergroup FROM ".MAIN_DB_PREFIX."cpo_task WHERE entity=".(int) $conf->entity." AND fk_operation=".(int) $id." AND fk_usergroup IS NOT NULL AND fk_usergroup>0 ORDER BY rowid LIMIT 1");
    $existingTeam = $res ? $db->fetch_object($res) : null;
    $selectedTeamId = $existingTeam ? (int) $existingTeam->fk_usergroup : 0;
}
$projects = array();
if ($canReadProjects) {
    $res = $db->query("SELECT p.rowid,p.ref,p.title,p.fk_soc FROM ".MAIN_DB_PREFIX."projet p INNER JOIN ".MAIN_DB_PREFIX."societe s ON s.rowid=p.fk_soc AND s.entity=p.entity WHERE p.entity=".(int) $conf->entity.$customerAccessSql." ORDER BY p.rowid DESC LIMIT 1000");
    while ($res && ($o = $db->fetch_object($res))) $projects[] = array('id'=>(int) $o->rowid,'socid'=>(int) $o->fk_soc,'label'=>$o->ref.' · '.$o->title);
}
$orders = array();
if ($canReadOrders) {
    $res = $db->query("SELECT o.rowid,o.ref,o.fk_soc FROM ".MAIN_DB_PREFIX."commande o INNER JOIN ".MAIN_DB_PREFIX."societe s ON s.rowid=o.fk_soc AND s.entity=o.entity WHERE o.entity=".(int) $conf->entity.$customerAccessSql." ORDER BY o.rowid DESC LIMIT 1000");
    while ($res && ($o = $db->fetch_object($res))) $orders[] = array('id'=>(int) $o->rowid,'socid'=>(int) $o->fk_soc,'label'=>$o->ref);
}
$shipments = array();
$res = $db->query("SELECT l.rowid,l.ref,l.fk_soc,l.fk_project,s.nom customer_name FROM ".MAIN_DB_PREFIX."chinaplanetlogistics l INNER JOIN ".MAIN_DB_PREFIX."societe s ON s.rowid=l.fk_soc AND s.entity=l.entity WHERE l.entity=".(int) $conf->entity.$customerAccessSql." ORDER BY l.rowid DESC LIMIT 1000");
while ($res && ($o = $db->fetch_object($res))) $shipments[] = array('id'=>(int) $o->rowid,'socid'=>(int) $o->fk_soc,'projectid'=>(int) $o->fk_project,'label'=>$o->ref.' · '.($o->customer_name ?: '—'));
$csrfToken = newToken();
$requestHash = GETPOST('request_hash', 'alphanohtml');
if ($requestHash === '') $requestHash = hash('sha256', (string) session_id().':'.(int) $user->id.':'.$csrfToken.':operation-create');

if ($action === 'save') try {
    $serviceData = array();
    $postedServiceData = GETPOST('service_data', 'array');
    foreach (OperationsService::serviceFields($type) as $field) $serviceData[$field] = trim((string) ($postedServiceData[$field] ?? ''));
    $requestedProjectId = GETPOSTINT('fk_project');
    $requestedOrderId = GETPOSTINT('fk_commande');
    if ($id <= 0 && !$canReadProjects && $requestedProjectId > 0) throw new RuntimeException('Project link requires native project permission.');
    if ($id <= 0 && !$canReadOrders && $requestedOrderId > 0) throw new RuntimeException('Order link requires native order permission.');
    $postedProjectId = $id > 0 && !$canReadProjects ? (int) $operation['fk_project'] : $requestedProjectId;
    $postedOrderId = $id > 0 && !$canReadOrders ? (int) $operation['fk_commande'] : $requestedOrderId;
    $data = array(
        'request_hash'=>$requestHash,'service_type'=>$type,'title'=>GETPOST('title','alphanohtml'),'fk_soc'=>GETPOSTINT('fk_soc'),'fk_usergroup'=>GETPOSTINT('fk_usergroup'),
        'fk_project'=>$postedProjectId,'fk_commande'=>$postedOrderId,'fk_shipment'=>GETPOSTINT('fk_shipment'),'require_assignment'=>1,
        'fk_user_assign'=>GETPOSTINT('fk_user_assign'),'priority'=>GETPOST('priority','alpha'),'stage'=>GETPOST('stage','alphanohtml') ?: 'new',
        'waiting_on'=>GETPOST('waiting_on','alphanohtml'),'next_action'=>GETPOST('next_action','alphanohtml'),'date_deadline'=>GETPOST('date_deadline','alphanohtml'),
        'note_private'=>GETPOST('note_private','restricthtml'),'note_public'=>GETPOST('note_public','restricthtml'),'customer_visible'=>GETPOSTINT('customer_visible'),'service_data'=>$serviceData,
    );
    $allowedProjectIds = array_map('intval', array_column($projects, 'id'));
    $allowedOrderIds = array_map('intval', array_column($orders, 'id'));
    $allowedShipmentIds = array_map('intval', array_column($shipments, 'id'));
    if ($data['fk_soc'] <= 0 || !isset($companies[$data['fk_soc']])) throw new RuntimeException('Customer is outside your authorized customer scope.');
    if ($canReadProjects && $data['fk_project'] > 0 && !in_array($data['fk_project'], $allowedProjectIds, true)) throw new RuntimeException('Project is outside your authorized project scope.');
    if ($canReadOrders && $data['fk_commande'] > 0 && !in_array($data['fk_commande'], $allowedOrderIds, true)) throw new RuntimeException('Order is outside your authorized order scope.');
    if ($data['fk_shipment'] > 0 && !in_array($data['fk_shipment'], $allowedShipmentIds, true)) throw new RuntimeException('Shipment is outside your authorized customer scope.');
    if (!$createReady) throw new RuntimeException('Operation creation is unavailable until the internal task board and an active team are ready.');
    if (!$data['title']) throw new RuntimeException($langs->trans('TitleRequired'));
    if (!$data['fk_soc']) throw new RuntimeException('Customer is required.');
    if (!$data['fk_user_assign']) throw new RuntimeException('Operation owner is required.');
    if (!$data['next_action']) throw new RuntimeException('Next action is required.');
    if ($id) $service->update($id, $data, (int) $user->id); else $id = $service->create($data, (int) $user->id);
    header('Location:view.php?id='.$id); exit;
} catch (Throwable $e) {
    $db->rollback(); setEventMessages($e->getMessage(), null, 'errors');
}
$v = function ($key, $default='') use ($operation, $selectedSocid) {
    if ($key === 'fk_soc' && !$operation) return $selectedSocid;
    return $operation[$key] ?? $default;
};
$sd = $operation['service_data'] ?? array();
$renderField = function ($field) use ($langs, $sd) {
    $value=$sd[$field]??''; $options=OperationsService::fieldOptions($field); ?>
    <label class="cpo-field"><span><?php echo $langs->trans('Field_'.$field); ?></span><?php if ($options) { ?><select name="service_data[<?php echo $field; ?>]"><option value="">—</option><?php foreach ($options as $option) { ?><option value="<?php echo dol_escape_htmltag($option); ?>"<?php echo $value === $option ? ' selected' : ''; ?>><?php echo dol_escape_htmltag(ucwords(str_replace('_',' ',$option))); ?></option><?php } ?></select><?php } elseif (in_array($field, array('customer_requirements','customer_warnings','findings','advice','product_details','customer_notes','cargo_summary','evidence_summary','resolution','contact_links'), true)) { ?><textarea name="service_data[<?php echo $field; ?>]"><?php echo dol_escape_htmltag($value); ?></textarea><?php } else { ?><input name="service_data[<?php echo $field; ?>]" value="<?php echo dol_escape_htmltag($value); ?>"><?php } ?></label><?php
};
$morecss=array(cpo_asset('/custom/chinaplanetoperations/css/operations.css'),cpcc_admin_shell_asset('/custom/chinaplanetcustomercenter/css/admin_dashboard.css'));
llxHeader('', $langs->trans($id ? 'EditOperation' : 'NewOperation'), '', '', 0, 0, array(), $morecss);
cpcc_admin_shell_open('workspace', $langs->trans($id ? 'EditOperation' : 'NewOperation'), $langs->trans('OperationsRegisterHelp'), 'fa-route');
?>
<main class="cpo-page"><section class="cpo-hero"><div><span class="cpo-eyebrow">GUIDED OPERATION FORM</span><h1><?php echo $langs->trans($id?'EditOperation':'NewOperation'); ?></h1><p><?php echo $id?dol_escape_htmltag($operation['ref']):$langs->trans('ChooseServiceHelp'); ?></p></div><a class="button" href="<?php echo $id?'view.php?id='.$id:'index.php'; ?>"><?php echo $langs->trans('Back'); ?></a></section>
<?php if(!$id&&$action!=='save'){ ?><section class="cpo-form-section"><h2><?php echo $langs->trans('ChooseService'); ?></h2><div class="cpo-service-grid"><?php foreach(OperationsService::serviceTypes() as $code){ ?><a href="?service_type=<?php echo $code; ?>"><strong><?php echo $langs->trans('Svc_'.$code); ?></strong><small><?php echo $langs->trans('SvcHelp_'.$code); ?></small></a><?php } ?></div></section><?php } ?>
<form method="post" id="cpo-guided-operation-form">
  <input type="hidden" name="token" value="<?php echo dol_escape_htmltag($csrfToken); ?>">
  <input type="hidden" name="request_hash" value="<?php echo dol_escape_htmltag($requestHash); ?>">
  <input type="hidden" name="action" value="save">
  <input type="hidden" name="id" value="<?php echo (int) $id; ?>">
  <input type="hidden" name="service_type" value="<?php echo dol_escape_htmltag($type); ?>">
  <section class="cpo-form-section">
    <h2>١. العميل والمسؤول · <?php echo $langs->trans('Svc_'.$type); ?></h2>
    <p class="opacitymedium">اختر العميل أولاً؛ بعدها تظهر مشروعاته وطلباته وشحناته فقط.</p>
    <div class="cpo-context-grid">
      <div class="cpo-field cpo-customer-control"><span><?php echo $langs->trans('Customer'); ?> *</span><select required id="cpo-customer" name="fk_soc"><?php foreach ($companies as $key=>$label) { ?><option value="<?php echo $key; ?>"<?php echo (int) $v('fk_soc') === $key ? ' selected' : ''; ?>><?php echo dol_escape_htmltag($label); ?></option><?php } ?></select><?php if (!$id && $canCreateCustomers) { ?><a class="button cpo-new-customer" href="<?php echo dol_escape_htmltag($newCustomerUrl); ?>"><span class="fas fa-user-plus" aria-hidden="true"></span> إضافة عميل جديد في النظام</a><small>يُحفظ العميل في سجل العملاء الأصلي، ثم يعود هنا محدداً تلقائياً.</small><?php } ?></div>
      <label class="cpo-field"><span><?php echo $langs->trans('Title'); ?> *</span><input required name="title" value="<?php echo dol_escape_htmltag($v('title')); ?>" placeholder="مثال: LOCAL TEST - متابعة طلبية وتجهيز الشحن"></label>
      <label class="cpo-field"><span><?php echo $langs->trans('Owner'); ?> *</span><select required id="cpo-owner" name="fk_user_assign"><?php foreach ($users as $key=>$label) { ?><option value="<?php echo $key; ?>"<?php echo (int) $v('fk_user_assign') === $key ? ' selected' : ''; ?>><?php echo dol_escape_htmltag($label); ?></option><?php } ?></select></label>
      <label class="cpo-field"><span>فريق مهام العملية *</span><select required id="cpo-team" name="fk_usergroup"<?php echo !$teams ? ' disabled' : ''; ?>><option value="">— اختر فريق المسؤول —</option><?php foreach ($teams as $team) { ?><option value="<?php echo $team['id']; ?>" data-members="<?php echo dol_escape_htmltag(implode(',', $team['members'])); ?>"<?php echo $selectedTeamId === (int) $team['id'] ? ' selected' : ''; ?>><?php echo dol_escape_htmltag($team['label']); ?></option><?php } ?></select><small>تظهر الفرق التي لديها أعضاء داخليون نشطون فقط.</small></label>
      <label class="cpo-field"><span><?php echo $langs->trans('Priority'); ?></span><select name="priority"><?php foreach (array('urgent','high','normal','low') as $p) { ?><option value="<?php echo $p; ?>"<?php echo $v('priority','normal') === $p ? ' selected' : ''; ?>><?php echo $langs->trans('Priority_'.$p); ?></option><?php } ?></select></label>
      <?php if ($id) { ?><label class="cpo-field"><span><?php echo $langs->trans('Stage'); ?></span><select name="stage"><?php foreach (OperationsService::stages() as $s=>$progress) { ?><option value="<?php echo $s; ?>"<?php echo $v('stage') === $s ? ' selected' : ''; ?>><?php echo $langs->trans('Stage_'.$s); ?></option><?php } ?></select></label><?php } ?>
      <label class="cpo-field"><span><?php echo $langs->trans('WaitingOn'); ?></span><select name="waiting_on"><?php foreach (array('internal','customer','supplier','factory','forwarder','customs','none') as $w) { ?><option value="<?php echo $w; ?>"<?php echo $v('waiting_on','internal') === $w ? ' selected' : ''; ?>><?php echo $langs->trans('Party_'.$w); ?></option><?php } ?></select></label>
      <label class="cpo-field"><span><?php echo $langs->trans('NextAction'); ?> *</span><input required name="next_action" value="<?php echo dol_escape_htmltag($v('next_action')); ?>" placeholder="فعل واحد واضح يجب تنفيذه بعد ذلك"></label>
      <label class="cpo-field"><span><?php echo $langs->trans('Deadline'); ?></span><input type="datetime-local" name="date_deadline" value="<?php echo $v('date_deadline') ? date('Y-m-d\TH:i', strtotime($v('date_deadline'))) : ''; ?>"></label>
    </div>
  </section>
<?php if($type==='shipping_booking'){ ?><section class="cpo-form-section"><h2><?php echo $langs->trans('BookingRequestInformation'); ?></h2><p class="opacitymedium"><?php echo $langs->trans('BookingRequestInformationHelp'); ?></p><div class="cpo-context-grid"><?php foreach(OperationsService::bookingRequestFields() as $field)$renderField($field); ?></div></section><?php if($id){ ?><section class="cpo-form-section cpo-forwarder-confirmation"><h2><?php echo $langs->trans('ForwarderConfirmationInformation'); ?></h2><div class="cpo-notice"><?php echo $langs->trans('ForwarderConfirmationHelp'); ?></div><div class="cpo-context-grid"><?php foreach(OperationsService::bookingConfirmationFields() as $field)$renderField($field); ?></div></section><?php } ?><?php }else{ ?><section class="cpo-form-section"><h2><?php echo $langs->trans('ServiceDetails'); ?></h2><div class="cpo-context-grid"><?php foreach(OperationsService::serviceFields($type) as $field)$renderField($field); ?></div></section><?php } ?>
<section class="cpo-form-section">
  <h2>٣. الربط والملاحظات</h2>
  <div class="cpo-context-grid">
    <?php if ($canReadProjects) { ?><label class="cpo-field"><span><?php echo $langs->trans('DolibarrProjectId'); ?></span><select id="cpo-project" name="fk_project"><option value="0">— اختياري —</option><?php foreach ($projects as $project) { ?><option value="<?php echo $project['id']; ?>" data-socid="<?php echo $project['socid']; ?>"<?php echo (int) $v('fk_project') === $project['id'] ? ' selected' : ''; ?>><?php echo dol_escape_htmltag($project['label']); ?></option><?php } ?></select></label><?php } ?>
    <?php if ($canReadOrders) { ?><label class="cpo-field"><span><?php echo $langs->trans('DolibarrOrderId'); ?></span><select id="cpo-order" name="fk_commande"><option value="0">— اختياري —</option><?php foreach ($orders as $order) { ?><option value="<?php echo $order['id']; ?>" data-socid="<?php echo $order['socid']; ?>"<?php echo (int) $v('fk_commande') === $order['id'] ? ' selected' : ''; ?>><?php echo dol_escape_htmltag($order['label']); ?></option><?php } ?></select></label><?php } ?>
    <label class="cpo-field"><span><?php echo $langs->trans('Shipment'); ?></span><select id="cpo-shipment" name="fk_shipment"><option value="0">— لا تنشئ حالة شحن وهمية —</option><?php foreach ($shipments as $shipment) { ?><option value="<?php echo $shipment['id']; ?>" data-socid="<?php echo $shipment['socid']; ?>" data-projectid="<?php echo $shipment['projectid']; ?>"<?php echo (int) $v('fk_shipment') === $shipment['id'] ? ' selected' : ''; ?>><?php echo dol_escape_htmltag($shipment['label']); ?></option><?php } ?></select><small>تظهر فقط الشحنات التابعة للعميل، ويجب أن يتطابق المشروع عند اختياره.</small></label>
    <label class="cpo-field"><span><?php echo $langs->trans('InternalNotes'); ?></span><textarea name="note_private"><?php echo dol_escape_htmltag($v('note_private')); ?></textarea></label>
    <label class="cpo-field"><span><?php echo $langs->trans('CustomerVisibleNotes'); ?></span><textarea name="note_public"><?php echo dol_escape_htmltag($v('note_public')); ?></textarea></label>
  </div>
  <label class="cpo-check-option"><input type="checkbox" name="customer_visible" value="1"<?php echo $v('customer_visible') ? ' checked' : ''; ?>> <span><?php echo $langs->trans('AllowApprovedCustomerOutput'); ?> — لا ينشر أو يرسل شيئاً تلقائياً</span></label>
  <?php if (!$createReady) { ?><div class="cpo-notice cpo-notice-warning"><strong>الإنشاء متوقف بأمان.</strong> لوحة المهام أو عضوية الفرق غير جاهزة (<?php echo dol_escape_htmltag($taskBoardReady ? 'NO_ACTIVE_TEAM_MEMBERS' : $taskBoardReason); ?>). راجع إعداد لوحة المهام والفرق أولاً؛ لم يتم حفظ عملية ناقصة.</div><?php } ?>
  <div class="cpo-form-actions"><a class="button" href="../workspace.php<?php echo $selectedSocid ? '?view=customer&amp;socid='.(int) $selectedSocid : ''; ?>">إلغاء</a><button class="button button-save"<?php echo !$createReady ? ' disabled' : ''; ?>><?php echo $langs->trans('Save'); ?></button></div>
</section>
</form>
<script>
(function(){
  var customer=document.getElementById('cpo-customer'),project=document.getElementById('cpo-project'),order=document.getElementById('cpo-order'),shipment=document.getElementById('cpo-shipment'),team=document.getElementById('cpo-team'),owner=document.getElementById('cpo-owner');
  function filter(select,socid){if(!select)return;Array.prototype.forEach.call(select.options,function(option){if(!option.value){option.hidden=false;return;}var matches=String(option.dataset.socid||'')===String(socid);option.hidden=!matches;if(!matches&&option.selected)select.value='0';});}
  function refresh(){var socid=customer?customer.value:'0';filter(project,socid);filter(order,socid);filter(shipment,socid);if(shipment&&project){Array.prototype.forEach.call(shipment.options,function(option){if(!option.value||option.hidden)return;var projectid=option.dataset.projectid||'0';option.disabled=project.value!=='0'&&projectid!=='0'&&projectid!==project.value;});if(shipment.selectedOptions[0]&&shipment.selectedOptions[0].disabled)shipment.value='0';}}
  function refreshOwners(){if(!team||!owner)return;var selected=team.selectedOptions[0],members=selected?String(selected.dataset.members||'').split(','):[];Array.prototype.forEach.call(owner.options,function(option){if(!option.value){option.hidden=false;return;}option.hidden=members.indexOf(option.value)<0;if(option.hidden&&option.selected)owner.value='0';});}
  if(customer)customer.addEventListener('change',refresh);if(project)project.addEventListener('change',refresh);if(team)team.addEventListener('change',refreshOwners);refresh();refreshOwners();
})();
</script>
</main>
<?php cpcc_admin_shell_close(); llxFooter(); $db->close(); ?>
