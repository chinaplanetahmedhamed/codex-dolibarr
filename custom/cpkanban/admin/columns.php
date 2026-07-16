<?php
/**
 * China Planet Kanban - Admin: manage columns.
 * Add / edit / reorder / recolor / toggle customer-visibility / activate.
 */
@error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);

require_once __DIR__.'/../lib/main.lib.php';

require_once __DIR__.'/../class/cpkanbancolumn.class.php';
require_once __DIR__.'/../lib/cpkanban.lib.php';
require_once DOL_DOCUMENT_ROOT.'/custom/chinaplanetcustomercenter/lib/admin_shell.lib.php';

$langs->loadLangs(array("admin", "other"));

// Admin/manage permission, internal only
if (empty($user->id) || !empty($user->socid) || (!$user->hasRight('cpkanban', 'admin') && empty($user->admin))) {
    accessforbidden();
}

$action = GETPOST('action', 'alpha');
$id     = GETPOST('id', 'int');

if ($action == 'add' && ($user->hasRight('cpkanban','admin') || $user->admin)) {
    $col = new CpKanbanColumn($db);
    $col->code         = GETPOST('code', 'aZ09');
    $col->label        = GETPOST('label', 'alphanohtml');
    $col->label_en     = GETPOST('label_en', 'alphanohtml');
    $col->color        = GETPOST('color', 'alpha');
    $col->position     = GETPOST('position', 'int');
    $col->cust_visible = GETPOST('cust_visible', 'int') ? 1 : 0;
    $col->auto_progress= (GETPOST('auto_progress','int') !== '') ? GETPOST('auto_progress','int') : null;
    $col->active       = 1;
    if ($col->create($user) > 0) setEventMessages('تمت إضافة العمود', null, 'mesgs');
    else setEventMessages($col->error, null, 'errors');
    header('Location: '.$_SERVER['PHP_SELF']); exit;
}

if ($action == 'update' && $id > 0) {
    $col = new CpKanbanColumn($db);
    $col->fetch($id);
    $col->label        = GETPOST('label', 'alphanohtml');
    $col->label_en     = GETPOST('label_en', 'alphanohtml');
    $col->color        = GETPOST('color', 'alpha');
    $col->position     = GETPOST('position', 'int');
    $col->cust_visible = GETPOST('cust_visible', 'int') ? 1 : 0;
    $col->auto_progress= (GETPOST('auto_progress','int') !== '') ? GETPOST('auto_progress','int') : null;
    $col->active       = GETPOST('active', 'int') ? 1 : 0;
    if ($col->update($user) > 0) setEventMessages('تم تحديث العمود', null, 'mesgs');
    else setEventMessages($col->error, null, 'errors');
    header('Location: '.$_SERVER['PHP_SELF']); exit;
}

if ($action == 'confirm_delete' && $id > 0) {
    $col = new CpKanbanColumn($db);
    $col->fetch($id);
    $r = $col->delete($user);
    if ($r > 0) setEventMessages('تم حذف العمود', null, 'mesgs');
    elseif ($r === 0) setEventMessages('لا يمكن حذف عمود يحتوي بطاقات. انقل البطاقات أولاً أو ألغِ تفعيل العمود.', null, 'warnings');
    else setEventMessages($col->error, null, 'errors');
    header('Location: '.$_SERVER['PHP_SELF']); exit;
}

llxHeader('', 'إدارة الأعمدة', '', '', 0, 0, array(), array('/custom/cpkanban/css/board.css',cpcc_admin_shell_asset('/custom/chinaplanetcustomercenter/css/admin_dashboard.css'),cpcc_admin_shell_asset('/custom/chinaplanetcustomercenter/css/ux_fixes.css')));
cpcc_admin_shell_open('operations','Kanban Columns / أعمدة كانبان','ترتيب مراحل العمل والرؤية والصلاحيات','fa-columns');
print load_fiche_titre('إدارة أعمدة لوحة المشاريع', '', 'project');

$cols = CpKanbanColumn::getAll($db, false, false); // all, including inactive

print '<div dir="rtl">';
print '<table class="liste centpercent"><tr class="liste_titre">';
print '<th>الترتيب</th><th>الكود</th><th>الاسم (عربي)</th><th>English</th><th>اللون</th><th>تقدّم تلقائي</th><th>مرئي للعميل</th><th>نشط</th><th></th></tr>';

foreach ($cols as $c) {
    print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
    print '<input type="hidden" name="token" value="'.newToken().'">';
    print '<input type="hidden" name="action" value="update">';
    print '<input type="hidden" name="id" value="'.(int)$c->rowid.'">';
    print '<tr class="oddeven">';
    print '<td><input type="number" name="position" class="flat width50" value="'.(int)$c->position.'"></td>';
    print '<td><code>'.dol_escape_htmltag($c->code).'</code></td>';
    print '<td><input type="text" name="label" class="flat" value="'.dol_escape_htmltag($c->label).'"></td>';
    print '<td><input type="text" name="label_en" class="flat" value="'.dol_escape_htmltag($c->label_en).'"></td>';
    print '<td><input type="color" name="color" value="'.dol_escape_htmltag($c->color).'"></td>';
    print '<td><input type="number" name="auto_progress" class="flat width50" min="0" max="100" value="'.($c->auto_progress!==null?(int)$c->auto_progress:'').'"></td>';
    print '<td class="center"><input type="checkbox" name="cust_visible" value="1" '.($c->cust_visible?'checked':'').'></td>';
    print '<td class="center"><input type="checkbox" name="active" value="1" '.($c->active?'checked':'').'></td>';
    print '<td><input type="submit" class="button small" value="حفظ"> ';
    print '<a class="button small button-cancel" href="'.$_SERVER['PHP_SELF'].'?action=confirm_delete&id='.(int)$c->rowid.'&token='.newToken().'" onclick="return confirm(\'حذف هذا العمود؟\')">حذف</a></td>';
    print '</tr></form>';
}
print '</table>';

// Add new column
print '<br><h3>إضافة عمود جديد</h3>';
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="add">';
print '<table class="border centpercent">';
print '<tr><td class="titlefield">الكود (إنجليزي بدون مسافات)</td><td><input type="text" name="code" class="flat" required></td></tr>';
print '<tr><td>الاسم (عربي)</td><td><input type="text" name="label" class="flat" required></td></tr>';
print '<tr><td>English</td><td><input type="text" name="label_en" class="flat"></td></tr>';
print '<tr><td>اللون</td><td><input type="color" name="color" value="#2980b9"></td></tr>';
print '<tr><td>الترتيب</td><td><input type="number" name="position" class="flat" value="50"></td></tr>';
print '<tr><td>تقدّم تلقائي %</td><td><input type="number" name="auto_progress" class="flat" min="0" max="100" placeholder="اختياري"></td></tr>';
print '<tr><td>مرئي للعميل</td><td><input type="checkbox" name="cust_visible" value="1" checked></td></tr>';
print '</table>';
print '<div class="center" style="margin-top:12px"><input type="submit" class="button" value="إضافة العمود"></div>';
print '</form>';
print '</div>';

cpcc_admin_shell_close();
llxFooter();
