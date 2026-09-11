<?php

define('CSRFCHECK_WITH_TOKEN', 1);
require_once __DIR__.'/../lib/main.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';

$langs->loadLangs(array('admin', 'visiontradingui@visiontradingui'));

if (empty($user->admin)) accessforbidden();

$action = GETPOST('action', 'alpha');
if ($action === 'save') {
    $enabled = GETPOSTINT('VISIONTRADINGUI_ENABLE_DASHBOARD') ? 1 : 0;
	$whiteLabelEnabled = GETPOSTINT('VISIONTRADINGUI_ENABLE_WHITE_LABEL') ? 1 : 0;
	$brandName = trim(GETPOST('VISIONTRADINGUI_BRAND_NAME', 'alphanohtml'));
	if ($brandName === '') $brandName = 'Vision-China Planet OS';
	$results = array(
		dolibarr_set_const($db, 'VISIONTRADINGUI_ENABLE_DASHBOARD', $enabled, 'chaine', 0, '', $conf->entity),
		dolibarr_set_const($db, 'VISIONTRADINGUI_ENABLE_WHITE_LABEL', $whiteLabelEnabled, 'chaine', 0, '', $conf->entity),
		dolibarr_set_const($db, 'VISIONTRADINGUI_BRAND_NAME', $brandName, 'chaine', 0, '', $conf->entity),
	);
	if ($whiteLabelEnabled) {
		$results[] = dolibarr_set_const($db, 'MAIN_APPLICATION_TITLE', $brandName, 'chaine', 0, '', $conf->entity);
		$results[] = dolibarr_set_const($db, 'MAIN_SHOW_VERSION', 0, 'chaine', 0, '', $conf->entity);
		$results[] = dolibarr_set_const($db, 'MAIN_SHOW_LOGO', 1, 'chaine', 0, '', $conf->entity);
		$results[] = dolibarr_set_const($db, 'MAIN_FAVICON_URL', dol_buildpath('/visiontradingui/img/vision-system-mark.svg', 1), 'chaine', 0, '', $conf->entity);
	}
	if (!in_array(-1, $results, true)) {
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
print '<tr><td>'.$langs->trans('VisionTradingUIEnableWhiteLabel').'</td><td>';
print '<label><input type="checkbox" name="VISIONTRADINGUI_ENABLE_WHITE_LABEL" value="1"'.(getDolGlobalInt('VISIONTRADINGUI_ENABLE_WHITE_LABEL') ? ' checked' : '').'> ';
print $langs->trans('Enabled').'</label></td></tr>';
print '<tr><td>'.$langs->trans('VisionTradingUIBrandName').'</td><td>';
print '<input class="minwidth300" name="VISIONTRADINGUI_BRAND_NAME" value="'.dol_escape_htmltag(getDolGlobalString('VISIONTRADINGUI_BRAND_NAME', 'Vision-China Planet OS')).'">';
print '</td></tr>';
print '<tr><td>'.$langs->trans('VisionTradingUIEnvironment').'</td><td>';
print $isStaging ? img_picto('', 'info').' '.$langs->trans('VisionTradingUIEnvironmentStaging') : img_picto('', 'warning').' '.$langs->trans('VisionTradingUIEnvironmentNotStaging');
print '</td></tr>';
print '</table></div>';
print '<div class="center"><button class="button button-save" type="submit">'.$langs->trans('Save').'</button></div>';
print '</form>';

llxFooter();
