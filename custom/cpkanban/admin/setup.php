<?php
require_once __DIR__.'/../../chinaplanetcustomercenter/lib/ui.lib.php';
/**
 * China Planet Kanban - Setup page
 */
require_once __DIR__.'/../lib/main.lib.php';
cpcc_ui_initialize_language();
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';

$langs->loadLangs(array("admin", "cpkanban@cpkanban"));

if (!$user->admin) accessforbidden();

llxHeader('', 'China Planet Kanban Setup');
cpcc_ui_assets();
cpcc_ui_language_controls();

$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans("BackToModuleList").'</a>';
print load_fiche_titre('China Planet - Customer Project Kanban — Setup', $linkback, 'title_setup');

print '<div class="info">';
print '<h3>🗂️ China Planet Customer Project Kanban</h3>';
print '<p>The module is enabled. Use the Customer Projects menu above.</p>';
print '<ul>';
print '<li><b>Project board:</b> <a href="'.dol_buildpath('/cpkanban/board.php', 1).'">Project Board</a></li>';
print '<li><b>Manage columns:</b> <a href="'.dol_buildpath('/cpkanban/admin/columns.php', 1).'">Manage Columns</a></li>';
print '<li><b>New card:</b> <a href="'.dol_buildpath('/cpkanban/card.php?action=create', 1).'">New Card</a></li>';
print '</ul>';
print '<p><b>Note:</b> Customers (external users linked to a company) can only view their own company\'s projects. Columns marked as not customer visible are shown to staff only.</p>';
print '</div>';

llxFooter();
