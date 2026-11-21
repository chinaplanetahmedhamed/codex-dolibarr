<?php
require_once __DIR__.'/../lib/main.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/custom/chinaplanetcustomercenter/lib/admin_shell.lib.php';

$langs->loadLangs(array("admin", "chinaplanetlogistics@chinaplanetlogistics"));

if (!$user->admin) accessforbidden();

$action = GETPOST('action', 'alpha');
$backtopage = GETPOST('backtopage', 'restricthtml');

llxHeader('', 'China Planet Logistics Setup', '', '', 0, 0, array(), array(cpcc_admin_shell_asset('/custom/chinaplanetcustomercenter/css/admin_dashboard.css'),cpcc_admin_shell_asset('/custom/chinaplanetcustomercenter/css/ux_fixes.css')));
cpcc_admin_shell_open('tracking','Logistics Settings / إعدادات الشحن','روابط إعداد ومراجعة وحدة الشحن','fa-cog');

$linkback = '<a href="'.($backtopage ? $backtopage : DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1').'">'.$langs->trans("BackToModuleList").'</a>';
print load_fiche_titre('China Planet Logistics - Setup', $linkback, 'title_setup');

print '<div class="info">';
print '<h3>🚢 China Planet Logistics Control Tower</h3>';
print '<p>The module is active. Use the top menu "China Planet Logistics" to manage shipments.</p>';
print '<ul>';
print '<li><b>Dashboard:</b> <a href="'.dol_buildpath('/chinaplanetlogistics/index.php', 1).'">Operations Control Tower</a></li>';
print '<li><b>Shipment List:</b> <a href="'.dol_buildpath('/chinaplanetlogistics/list.php', 1).'">View all shipments</a></li>';
print '<li><b>Documents Management:</b> <a href="'.dol_buildpath('/chinaplanetcustomercenter/documents.php', 1).'">Shipment documents manager</a></li>';
print '<li><b>Customer Portal (Public):</b> <a href="'.dol_buildpath('/chinaplanetlogistics/public/track.php', 1).'" target="_blank">Tracking Page</a></li>';
print '</ul>';
print '</div>';

cpcc_admin_shell_close();
llxFooter();
?>
