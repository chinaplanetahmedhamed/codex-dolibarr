<?php
/**
 * China Planet Kanban - Setup page
 */
require_once __DIR__.'/../lib/main.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';

$langs->loadLangs(array("admin", "cpkanban@cpkanban"));

if (!$user->admin) accessforbidden();

llxHeader('', 'China Planet Kanban Setup');

$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans("BackToModuleList").'</a>';
print load_fiche_titre('China Planet - Customer Project Kanban — Setup', $linkback, 'title_setup');

print '<div class="info">';
print '<h3>🗂️ China Planet Customer Project Kanban</h3>';
print '<p>الوحدة مفعّلة. استخدم قائمة "Customer Projects" في الأعلى.</p>';
print '<ul>';
print '<li><b>لوحة المشاريع:</b> <a href="'.dol_buildpath('/cpkanban/board.php', 1).'">Project Board</a></li>';
print '<li><b>إدارة الأعمدة:</b> <a href="'.dol_buildpath('/cpkanban/admin/columns.php', 1).'">Manage Columns</a></li>';
print '<li><b>بطاقة جديدة:</b> <a href="'.dol_buildpath('/cpkanban/card.php?action=create', 1).'">New Card</a></li>';
print '</ul>';
print '<p><b>ملاحظة:</b> العملاء (المستخدمون الخارجيون المرتبطون بشركة) يرون مشاريع شركتهم فقط، عرض فقط. الأعمدة المعلّمة "غير مرئية للعميل" تظهر للفريق فقط.</p>';
print '</div>';

llxFooter();
