<?php
// Native bootstrap: supports standard custom directories and symlinked modules.
$loaded = false;
foreach (array(__DIR__.'/../../main.inc.php', __DIR__.'/../../../main.inc.php', ($_SERVER['DOCUMENT_ROOT'] ?? '').'/main.inc.php') as $bootstrap) {
    if (is_file($bootstrap)) { require_once $bootstrap; $loaded = true; break; }
}
if (!$loaded) { http_response_code(503); exit('Dolibarr bootstrap unavailable'); }
require_once __DIR__.'/class/DocumentsWorkspace.class.php';
require_once __DIR__.'/lib/workspace.lib.php';
if (!isModEnabled('chinaplanetdocuments') || !DocumentsWorkspace::canRead($user)) accessforbidden();
// This page has no actions. Reject writes rather than handling arbitrary actions.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') { http_response_code(405); header('Allow: GET'); exit; }
$langs->loadLangs(array('chinaplanetdocuments@chinaplanetdocuments'));
$socid = GETPOSTINT('socid');
$projectid = GETPOSTINT('projectid');
$shipmentid = GETPOSTINT('shipmentid');
$returnto = GETPOST('returnto', 'alpha') ?: 'workspace';
if ($socid < 0 || $projectid < 0 || $shipmentid < 0 || !in_array($returnto, array('workspace','customer','project','shipment'), true)) accessforbidden();
$canLogistics = isModEnabled('chinaplanetlogistics') && (!empty($user->admin) || $user->hasRight('chinaplanetlogistics', 'read'));
$shipment = null;
if ($shipmentid > 0) {
    if (!$canLogistics) accessforbidden();
    dol_include_once('/chinaplanetlogistics/class/chinaplanetlogistics.class.php');
    $shipment = new ChinaPlanetLogistics($db);
    if ($shipment->fetch($shipmentid) <= 0 || (int) $shipment->entity !== (int) $conf->entity) accessforbidden();
    if ($socid === 0) $socid = (int) $shipment->fk_soc;
    if ($projectid === 0) $projectid = (int) $shipment->fk_project;
}
$customer = null;
$project = null;
if ($projectid > 0) {
    require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';
    $project = new Project($db);
    if ($project->fetch($projectid) <= 0 || (int) $project->entity !== (int) $conf->entity) accessforbidden();
    restrictedArea($user, 'projet', $project->id, 'projet&project');
    if ($project->restrictedProjectArea($user, 'read') <= 0) accessforbidden();
    if ($socid === 0) $socid = (int) $project->socid;
}
if ($socid > 0) {
    require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
    $customer = new Societe($db);
    if ($customer->fetch($socid) <= 0 || (int) $customer->entity !== (int) $conf->entity) accessforbidden();
    restrictedArea($user, 'societe', $customer->id);
}
try { $context = DocumentsWorkspace::context($conf->entity, $customer, $project, $shipment); }
catch (InvalidArgumentException $e) { accessforbidden(); }
$projects = array();
$shipments = array();
$companyHtml = '';
$choiceError = false;
if (!empty($user->admin) || $user->hasRight('societe', 'lire')) {
    require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
    $form = new Form($db);
    $companyHtml = $form->select_company($socid, 'socid', '', 1, 0, 1);
}
if ($customer) {
    try {
        $allowed = array();
        if (!empty($user->admin) || $user->hasRight('projet', 'lire') || $user->hasRight('projet', 'all', 'lire')) {
            require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';
            $nativeProject = new Project($db);
            $mode = (!empty($user->admin) || $user->hasRight('projet', 'all', 'lire')) ? 2 : 0;
            $allowed = $nativeProject->getProjectsAuthorizedForUser($user, $mode, 0, $socid);
            if (!is_array($allowed)) throw new RuntimeException('Project choices unavailable');
            $projects = DocumentsWorkspace::projectChoices($db, $conf->entity, $customer, $allowed);
        }
        if ($canLogistics) $shipments = DocumentsWorkspace::shipmentChoices($db, $conf->entity, $customer, $project, $allowed);
    } catch (RuntimeException $e) { $choiceError = true; }
}
$css = array('/chinaplanetdocuments/css/workspace.css');
llxHeader('', $langs->trans('DocumentsWorkspace'), '', '', 0, 0, array(), $css);
print load_fiche_titre($langs->trans('DocumentsWorkspace'), '', 'file');
$translate = function ($key) use ($langs) { return $langs->trans($key); };
if ($choiceError) print '<p class="warning">'.dol_escape_htmltag($langs->trans('DocumentsChoicesUnavailable')).'</p>';
else print cpd_documents_selectors_html($context, $companyHtml, $projects, $shipments, $translate, DOL_URL_ROOT, $returnto);
print cpd_documents_workspace_html($context, getDolGlobalString('CHINAPLANETDOCUMENTS_EDITOR_URL'), function ($key) use ($langs) { return $langs->trans($key); }, DOL_URL_ROOT, $returnto);
// Opt in only after the existing editor has installed the same-origin receiver hook.
if (getDolGlobalString('CHINAPLANETDOCUMENTS_CONTEXT_ADAPTER') === '1') {
    $handoff = DocumentsWorkspace::handoffPayload($conf->entity, $context, DOL_URL_ROOT, $returnto);
    if ($handoff !== null) print cpd_documents_adapter_html($handoff, DOL_URL_ROOT);
}
llxFooter();
$db->close();
