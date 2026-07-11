<?php
/**
 * China Planet Kanban - Card page (view / create / edit / delete).
 * Internal staff: full edit. Customers: read-only, internal notes/files hidden,
 * embedded shipment summary + link to full tracking (the "mix" approach).
 */
@error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);

$res = 0;
if (!$res && file_exists("../../main.inc.php")) $res = @include "../../main.inc.php";
if (!$res && file_exists("../../../main.inc.php")) $res = @include "../../../main.inc.php";
if (!$res) die("Include of main fails");

require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once __DIR__.'/class/chinaplanetcustomercentercard.class.php';
require_once __DIR__.'/class/chinaplanetcustomercentercolumn.class.php';
require_once __DIR__.'/lib/chinaplanetlogistics.lib.php';

$langs->loadLangs(array("companies", "projects", "other"));

if (empty($user->id) || (!$user->hasRight('chinaplanetlogistics', 'read') && empty($user->admin))) {
    accessforbidden();
}

$ctx = chinaplanetcustomercenter_user_context($user);
$is_external = $ctx['is_external'];
$socid = $ctx['socid'];
if ($is_external && $socid <= 0) accessforbidden("No company linked to this user.");

$canwrite = (!$is_external && ($user->hasRight('chinaplanetlogistics', 'write') || !empty($user->admin)));

$id     = GETPOST('id', 'int');
$action = GETPOST('action', 'alpha');

$object  = new CpKanbanCard($db);
$columns = CpKanbanColumn::getAll($db, false, true);
$form    = new Form($db);

$boardurl = dol_buildpath('/chinaplanetlogistics/board.php', 1);

/*
 * ACTIONS (internal staff only)
 */
if ($action == 'add' && $canwrite) {
    $object->fk_soc         = GETPOST('fk_soc', 'int');
    $object->fk_column      = GETPOST('fk_column', 'int');
    $object->title          = GETPOST('title', 'alphanohtml');
    $object->description    = GETPOST('description', 'restricthtml');
    $object->priority       = GETPOST('priority', 'alpha');
    $object->progress       = GETPOST('progress', 'int');
    $object->cust_visible   = GETPOST('cust_visible', 'int') ? 1 : 0;
    $object->fk_shipment    = GETPOST('fk_shipment', 'int');
    $object->note_public    = GETPOST('note_public', 'restricthtml');
    $object->note_private   = GETPOST('note_private', 'restricthtml');
    $r = $object->create($user);
    if ($r > 0) { header('Location: '.$_SERVER['PHP_SELF'].'?id='.$r); exit; }
    else setEventMessages($object->error, null, 'errors');
}

if ($action == 'update' && $canwrite && $id > 0) {
    $object->fetch($id);
    $object->fk_soc       = GETPOST('fk_soc', 'int');
    $object->fk_column    = GETPOST('fk_column', 'int');
    $object->title        = GETPOST('title', 'alphanohtml');
    $object->description  = GETPOST('description', 'restricthtml');
    $object->priority     = GETPOST('priority', 'alpha');
    $object->progress     = GETPOST('progress', 'int');
    $object->cust_visible = GETPOST('cust_visible', 'int') ? 1 : 0;
    $object->fk_shipment  = GETPOST('fk_shipment', 'int');
    $object->note_public  = GETPOST('note_public', 'restricthtml');
    $object->note_private = GETPOST('note_private', 'restricthtml');
    $r = $object->update($user);
    if ($r > 0) { header('Location: '.$_SERVER['PHP_SELF'].'?id='.$id); exit; }
    else setEventMessages($object->error, null, 'errors');
}

if ($action == 'confirm_delete' && $canwrite && $id > 0 && $user->hasRight('chinaplanetlogistics','delete')) {
    $object->fetch($id);
    $object->delete($user);
    header('Location: '.$boardurl); exit;
}

llxHeader('', 'Project Card / بطاقة المشروع', '', '', 0, 0,
    array(), array('/custom/chinaplanetlogistics/chinaplanetlogistics/css/board.css'));

/*
 * CREATE FORM (internal)
 */
if ($action == 'create' && $canwrite) {
    print load_fiche_titre('بطاقة جديدة', '', 'project');
    print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
    print '<input type="hidden" name="token" value="'.newToken().'">';
    print '<input type="hidden" name="action" value="add">';
    print '<table class="border centpercent">';
    print '<tr><td class="titlefieldcreate">العميل</td><td>';
    print $form->select_company(GETPOST('fk_soc','int'), 'fk_soc', '', 'اختر العميل', 0, 0, null, 0, 'minwidth300');
    print '</td></tr>';
    print '<tr><td>العمود</td><td><select name="fk_column" class="flat">';
    foreach ($columns as $col) print '<option value="'.(int)$col->rowid.'">'.dol_escape_htmltag($col->label).'</option>';
    print '</select></td></tr>';
    print '<tr><td>العنوان</td><td><input type="text" name="title" class="flat minwidth300" required></td></tr>';
    print '<tr><td>الوصف</td><td><textarea name="description" class="flat quatrevingtpercent" rows="3"></textarea></td></tr>';
    print '<tr><td>الأولوية</td><td><select name="priority" class="flat"><option value="high">عالية</option><option value="med" selected>متوسطة</option><option value="low">منخفضة</option></select></td></tr>';
    print '<tr><td>نسبة التقدّم</td><td><input type="number" name="progress" class="flat" min="0" max="100" value="0"> %</td></tr>';
    print '<tr><td>مرئي للعميل</td><td><input type="checkbox" name="cust_visible" value="1" checked></td></tr>';
    print '<tr><td>رقم الشحنة المرتبطة (rowid)</td><td><input type="number" name="fk_shipment" class="flat" placeholder="اختياري"></td></tr>';
    print '<tr><td>ملاحظة عامة (يراها العميل)</td><td><textarea name="note_public" class="flat quatrevingtpercent" rows="2"></textarea></td></tr>';
    print '<tr><td>ملاحظة داخلية (للفريق فقط)</td><td><textarea name="note_private" class="flat quatrevingtpercent" rows="2"></textarea></td></tr>';
    print '</table>';
    print '<div class="center" style="margin-top:14px"><input type="submit" class="button" value="حفظ"> ';
    print '<a class="button button-cancel" href="'.$boardurl.'">إلغاء</a></div>';
    print '</form>';
    llxFooter();
    exit;
}

/*
 * VIEW (internal sees everything; customer sees filtered+owned)
 */
$fetchSoc = $is_external ? $socid : 0;
$r = $object->fetch($id, $fetchSoc);
if ($r <= 0) { print '<div class="warning">البطاقة غير موجودة أو لا تملك صلاحية عرضها.</div>'; llxFooter(); exit; }

$colObj = isset($columns[$object->fk_column]) ? $columns[$object->fk_column] : null;
$pri = chinaplanetcustomercenter_priority($object->priority);

print '<div class="cpk-detail-shell" dir="rtl">';
print '<a class="cpk-action-link" href="'.$boardurl.'">← العودة إلى مركز العميل</a>';
print load_fiche_titre(dol_escape_htmltag($object->title), '', 'project');

print '<div class="cpk-action-row">';
print '<a class="cpk-action-link" href="'.$boardurl.'">🗂️ لوحة المشاريع</a>';
print '<a class="cpk-action-link" href="'.dol_buildpath('/chinaplanetlogistics/index.php', 1).'">🚢 الشحنات</a>';
print '<a class="cpk-action-link" href="'.dol_buildpath('/compta/facture/list.php', 1).'">🧾 الفواتير والرصيد</a>';
print '<a class="cpk-action-link" href="'.dol_buildpath('/commande/list.php', 1).'">📦 الطلبيات</a>';
if (!empty($object->fk_project)) print '<a class="cpk-action-link" href="'.dol_buildpath('/projet/card.php', 1).'?id='.(int)$object->fk_project.'">📋 تفاصيل المشروع</a>';
print '</div>';

print '<div class="cpk-detail-card">';
print '<table class="border centpercent">';
print '<tr><td class="titlefield">المرجع</td><td>'.dol_escape_htmltag($object->ref).'</td></tr>';
if (!$is_external) {
    print '<tr><td>العميل</td><td>';
    $soc = new Societe($db); $soc->fetch($object->fk_soc);
    print $soc->getNomUrl(1).'</td></tr>';
}
print '<tr><td>الحالة (العمود)</td><td>';
if ($colObj) print '<span style="background:'.dol_escape_htmltag($colObj->color).';color:#fff;padding:3px 12px;border-radius:8px;font-weight:700">'.dol_escape_htmltag($colObj->label).'</span>';
print '</td></tr>';
print '<tr><td>الأولوية</td><td><span class="cpk-tag '.$pri['cls'].'">'.$pri['label'].'</span></td></tr>';
print '<tr><td>نسبة التقدّم</td><td><b style="color:#D4A017">'.(int)$object->progress.'%</b></td></tr>';
if (!empty($object->description)) print '<tr><td>الوصف</td><td>'.nl2br(dol_escape_htmltag($object->description)).'</td></tr>';
if (!empty($object->date_deadline)) print '<tr><td>الموعد النهائي</td><td>'.dol_print_date($object->date_deadline, 'day').'</td></tr>';

// ---------- Embedded shipment summary (the "mix" approach) ----------
$ship = $object->getShipmentSummary($fetchSoc);
if ($ship) {
    $eta_days = chinaplanetcustomercenter_days_until($ship->date_eta);
    $ft_days  = chinaplanetcustomercenter_days_until($ship->freetime_end);
    print '<tr><td>الشحنة المرتبطة</td><td>';
    print '<div class="cpk-shipment-box">';
    print '<div style="font-weight:800;font-family:Cairo,sans-serif;margin-bottom:8px">🚢 '.dol_escape_htmltag($ship->container_no ? $ship->container_no : $ship->ref).'</div>';
    print '<div class="cpk-count-grid">';
    print '<div class="cpk-count-box" style="background:'.chinaplanetcustomercenter_countdown_color($eta_days).'"><b>'.($eta_days === null ? '-' : ($eta_days < 0 ? 'وصلت' : $eta_days)).'</b><span>أيام حتى الوصول</span></div>';
    print '<div class="cpk-count-box" style="background:'.chinaplanetcustomercenter_countdown_color($ft_days).'"><b>'.($ft_days === null ? '-' : $ft_days).'</b><span>أيام الوقت الحر المتبقية</span></div>';
    print '</div>';
    print '<div style="margin-top:8px;font-size:13px">الحالة: '.chinaplanetcustomercenter_shipment_status($ship->fk_statut).' • السفينة: '.dol_escape_htmltag($ship->vessel_name ? $ship->vessel_name : '-').'</div>';
    // Full tracking link (public track page or internal logistics card)
    $fulltrack = dol_buildpath('/chinaplanetlogistics/card.php', 1).'?id='.(int)$ship->rowid;
    print '<div style="margin-top:12px"><a class="cpk-action-link" href="'.$fulltrack.'">عرض صفحة تتبع الحاوية الكاملة ←</a></div>';
    print '</div></td></tr>';
}

// ---------- Public note (always) ----------
if (!empty($object->note_public)) {
    print '<tr><td>ملاحظة</td><td><div class="cpk-info-box">'.nl2br(dol_escape_htmltag($object->note_public)).'</div></td></tr>';
}

// ---------- Private note + internal info (internal staff ONLY) ----------
if (!$is_external) {
    if (!empty($object->note_private)) {
        print '<tr><td>ملاحظة داخلية</td><td><div class="cpk-private">🔒 '.nl2br(dol_escape_htmltag($object->note_private)).'</div></td></tr>';
    }
    print '<tr><td>مرئي للعميل؟</td><td>'.($object->cust_visible ? 'نعم' : 'لا').'</td></tr>';
}

// ---------- Activity log ----------
$logs = $is_external ? array() : $object->getLogs(0);
if (!empty($logs)) {
    print '<tr><td>سجل الحركة</td><td>';
    foreach ($logs as $lg) {
        $who = trim(($lg->firstname ?? '').' '.($lg->lastname ?? ''));
        print '<div style="border-bottom:1px solid #f0f0f0;padding:5px 0;font-size:13px">'.dol_print_date($db->jdate($lg->date_action), 'dayhour').' — '.dol_escape_htmltag($lg->note ? $lg->note : $lg->action_type).($who ? ' ('.dol_escape_htmltag($who).')' : '').'</div>';
    }
    print '</td></tr>';
}
print '</table>';
print '</div>';

// ---------- Action buttons (internal) ----------
if ($canwrite) {
    print '<div class="tabsAction">';
    print '<a class="butAction" href="'.$_SERVER['PHP_SELF'].'?id='.$id.'&action=edit">✏️ تحرير</a>';
    if ($user->hasRight('chinaplanetlogistics','delete')) {
        print '<a class="butActionDelete" href="'.$_SERVER['PHP_SELF'].'?id='.$id.'&action=confirm_delete&token='.newToken().'">🗑️ حذف</a>';
    }
    print '</div>';
}
print '</div>';

/*
 * EDIT FORM (internal) — shown when action=edit
 */
if ($action == 'edit' && $canwrite) {
    print '<hr><form method="POST" action="'.$_SERVER['PHP_SELF'].'?id='.$id.'">';
    print '<input type="hidden" name="token" value="'.newToken().'">';
    print '<input type="hidden" name="action" value="update">';
    print '<table class="border centpercent">';
    print '<tr><td class="titlefield">العمود</td><td><select name="fk_column" class="flat">';
    foreach ($columns as $col) { $sel = ($col->rowid == $object->fk_column) ? ' selected' : ''; print '<option value="'.(int)$col->rowid.'"'.$sel.'>'.dol_escape_htmltag($col->label).'</option>'; }
    print '</select></td></tr>';
    print '<tr><td>العميل (socid)</td><td><input type="number" name="fk_soc" class="flat" value="'.(int)$object->fk_soc.'"></td></tr>';
    print '<tr><td>العنوان</td><td><input type="text" name="title" class="flat minwidth300" value="'.dol_escape_htmltag($object->title).'"></td></tr>';
    print '<tr><td>الوصف</td><td><textarea name="description" class="flat quatrevingtpercent" rows="3">'.dol_escape_htmltag($object->description).'</textarea></td></tr>';
    print '<tr><td>الأولوية</td><td><select name="priority" class="flat">';
    foreach (array('high'=>'عالية','med'=>'متوسطة','low'=>'منخفضة') as $k=>$v) { $sel=($object->priority==$k)?' selected':''; print '<option value="'.$k.'"'.$sel.'>'.$v.'</option>'; }
    print '</select></td></tr>';
    print '<tr><td>نسبة التقدّم</td><td><input type="number" name="progress" class="flat" min="0" max="100" value="'.(int)$object->progress.'"> %</td></tr>';
    print '<tr><td>مرئي للعميل</td><td><input type="checkbox" name="cust_visible" value="1" '.($object->cust_visible?'checked':'').'></td></tr>';
    print '<tr><td>رقم الشحنة المرتبطة</td><td><input type="number" name="fk_shipment" class="flat" value="'.($object->fk_shipment?(int)$object->fk_shipment:'').'"></td></tr>';
    print '<tr><td>ملاحظة عامة</td><td><textarea name="note_public" class="flat quatrevingtpercent" rows="2">'.dol_escape_htmltag($object->note_public).'</textarea></td></tr>';
    print '<tr><td>ملاحظة داخلية</td><td><textarea name="note_private" class="flat quatrevingtpercent" rows="2">'.dol_escape_htmltag($object->note_private).'</textarea></td></tr>';
    print '</table>';
    print '<div class="center" style="margin-top:14px"><input type="submit" class="button" value="حفظ التعديلات"> <a class="button button-cancel" href="'.$_SERVER['PHP_SELF'].'?id='.$id.'">إلغاء</a></div>';
    print '</form>';
}

llxFooter();
