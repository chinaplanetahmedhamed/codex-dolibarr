<?php
if (!isset($_SERVER['QUERY_STRING'])) $_SERVER['QUERY_STRING'] = '';
require '../../../main.inc.php';

if (!$user->admin) accessforbidden();

llxHeader('', 'ChinaPlanet Customer Center Setup');

print load_fiche_titre('ChinaPlanet Customer Center', '', 'title_setup');
print '<div class="info">';
print '<p>Unified portal, shipment tracking, and internal Kanban module.</p>';
print '<ul>';
print '<li><a href="'.dol_buildpath('/chinaplanetcustomercenter/board.php', 1).'">Portal board</a></li>';
print '<li><a href="'.dol_buildpath('/chinaplanetcustomercenter/index.php', 1).'">Shipment tracker</a></li>';
print '</ul>';
print '</div>';

llxFooter();
