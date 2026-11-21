<?php
/** Internal configuration only; no API, quote writer, or public category exposure. */
define('CSRFCHECK_WITH_TOKEN', 1);
require $_SERVER['DOCUMENT_ROOT'].'/main.inc.php';
require_once __DIR__.'/../class/SupplierCategorySourcing.class.php';
require_once __DIR__.'/../class/SupplierCategorySourcingView.class.php';

$advanced = (bool) getDolGlobalString('MAIN_USE_ADVANCED_PERMS');
if (!isModEnabled('chinaplanetoperations') || !isModEnabled('categorie')
    || !SupplierCategorySourcing::canManage($user, (int) $conf->entity, $advanced)) accessforbidden();
$langs->load('supplier_sourcing@chinaplanetoperations');
$service = new SupplierCategorySourcing($db, (int) $conf->entity, $advanced);
$action = GETPOST('action', 'alpha');
if ($action !== '') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $action !== 'save') accessforbidden();
    try {
        $service->save(GETPOSTINT('product_category'), GETPOSTINT('supplier_category'),
            GETPOSTINT('descendants') === 1, GETPOSTINT('enabled') === 1, GETPOSTINT('version'), $user);
        setEventMessages($langs->trans('SourcingMappingSaved'), null, 'mesgs');
        $language = preg_match('/^[a-z]{2}_[A-Z]{2}$/D', $langs->defaultlang) ? '?lang='.$langs->defaultlang : '';
        header('Location: '.dol_buildpath('/chinaplanetoperations/sourcing/categories.php', 1).$language);
        exit;
    } catch (Throwable $e) {
        // Do not expose SQL, internal messages, or submitted values.
        setEventMessages($langs->trans('SourcingMappingFailed'), null, 'errors');
    }
}
llxHeader('', $langs->trans('SourcingManageCategories'));
try {
    print SupplierCategorySourcingView::render($service->categories($user), $service->mappings($user), $langs, newToken(), DOL_URL_ROOT);
} catch (Throwable $e) {
    print '<p class="warning">'.dol_escape_htmltag($langs->trans('SourcingUnavailable')).'</p>';
}
llxFooter();
$db->close();
