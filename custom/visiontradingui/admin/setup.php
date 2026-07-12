<?php

define('CSRFCHECK_WITH_TOKEN', 1);
require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';

$langs->loadLangs(array('admin', 'visiontradingui@visiontradingui'));

if (empty($user->admin)) accessforbidden();

$action = GETPOST('action', 'alpha');
if ($action === 'save') {
    $enabled = GETPOSTINT('VISIONTRADINGUI_ENABLE_DASHBOARD') ? 1 : 0;
    if (dolibarr_set_const($db, 'VISIONTRADINGUI_ENABLE_DASHBOARD', $enabled, 'chaine', 0, '', $conf->entity) > 0) {
        setEventMessages($langs->trans('SetupSaved'), null, 'mesgs');
        header('Location: '.$_SERVER['PHP_SELF']);
        exit;
    }
    setEventMessages($langs->trans('ErrorFailedToSaveConfig'), null, 'errors');
}

$title = $langs->trans('ModuleSetup').' '.$langs->trans('ModuleVisionTradingUIName');
llxHeader('', $title);

$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans('BackToModuleList').'</a>';
print load_fiche_titre($title, $linkback, 'title_setup');

$isStaging = strtolower((string) getenv('VISIONTRADING_ENVIRONMENT')) === 'staging';
print '<div class="info">'.dol_escape_htmltag($langs->trans('VisionTradingUISetupHelp')).'</div>';
print '<form method="POST" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="save">';
print '<div class="div-table-responsive"><table class="noborder centpercent">';
print '<tr class="liste_titre"><th>'.$langs->trans('Parameter').'</th><th>'.$langs->trans('Value').'</th></tr>';
print '<tr><td>'.$langs->trans('VisionTradingUIEnableDashboard').'</td><td>';
print '<label><input type="checkbox" name="VISIONTRADINGUI_ENABLE_DASHBOARD" value="1"'.(getDolGlobalInt('VISIONTRADINGUI_ENABLE_DASHBOARD') ? ' checked' : '').'> ';
print $langs->trans('Enabled').'</label></td></tr>';
print '<tr><td>'.$langs->trans('VisionTradingUIEnvironment').'</td><td>';
print $isStaging ? img_picto('', 'info').' '.$langs->trans('VisionTradingUIEnvironmentStaging') : img_picto('', 'warning').' '.$langs->trans('VisionTradingUIEnvironmentNotStaging');
print '</td></tr>';
print '</table></div>';
print '<div class="center"><button class="button button-save" type="submit">'.$langs->trans('Save').'</button></div>';
print '</form>';

llxFooter();
