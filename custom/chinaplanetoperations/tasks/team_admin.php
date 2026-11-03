<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') define('CSRFCHECK_WITH_TOKEN', 1);
require $_SERVER['DOCUMENT_ROOT'].'/main.inc.php';
require_once __DIR__.'/../lib/main.lib.php';
require_once DOL_DOCUMENT_ROOT.'/custom/chinaplanetcustomercenter/lib/ui.lib.php';
cpcc_ui_initialize_language();
require_once __DIR__.'/../class/InternalTaskService.class.php';
cpo_task_require($db, $user);
$langs->load('chinaplanetoperations@chinaplanetoperations');
$service = new InternalTaskService($db, $user);
if (!$service->authorization()->isAdministrator()) accessforbidden();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $requestId = GETPOST('request_id', 'alphanohtml');
        if (!preg_match('/^[a-f0-9]{48}$/', $requestId)) throw new RuntimeException('Invalid grant request.');
        $requestKey = 'grant:'.(int) $user->id.':'.$requestId;
        if (!isset($_SESSION['cpo_m058_requests'])) $_SESSION['cpo_m058_requests'] = array();
        if (!isset($_SESSION['cpo_m058_requests'][$requestKey])) {
            $service->saveGrant(GETPOSTINT('team_id'), GETPOSTINT('user_id'), GETPOSTINT('enabled') === 1);
            $_SESSION['cpo_m058_requests'][$requestKey] = 1;
            if (count($_SESSION['cpo_m058_requests']) > 100) $_SESSION['cpo_m058_requests'] = array_slice($_SESSION['cpo_m058_requests'], -100, null, true);
        }
        setEventMessages($langs->trans('TaskUpdated'), null, 'mesgs');
    } catch (InternalTaskAccessException $e) {
        setEventMessages($langs->trans('InternalTaskSafeDenied'), null, 'errors');
    } catch (Throwable $e) {
        setEventMessages($langs->trans('InternalTaskValidationError'), null, 'errors');
    }
    header('Location: team_admin.php');
    exit;
}

$teams = $service->teamOptions(false);
$users = $service->internalUsers();
$grants = $service->grantRows();
$morecss = array(cpo_asset('/custom/chinaplanetoperations/css/operations.css'));
llxHeader('', $langs->trans('InternalTaskTeamGrants'), '', '', 0, 0, array(), $morecss);cpcc_ui_assets();cpcc_ui_language_controls();
?>
<main class="cpo-page">
<section class="cpo-hero"><div><span class="cpo-eyebrow"><?php echo $langs->trans('InternalTaskLeastPrivilege'); ?></span><h1><?php echo $langs->trans('InternalTaskTeamGrants'); ?></h1><p><?php echo $langs->trans('InternalTaskTeamGrantsHelp'); ?></p></div><a class="button" href="board.php"><?php echo $langs->trans('Back'); ?></a></section>
<section class="cpo-panel"><form method="post"><input type="hidden" name="token" value="<?php echo newToken(); ?>"><input type="hidden" name="request_id" value="<?php echo bin2hex(random_bytes(24)); ?>"><input type="hidden" name="enabled" value="1"><div class="cpo-filters"><label class="cpo-field"><span><?php echo $langs->trans('InternalTaskTeam'); ?></span><select required name="team_id"><?php foreach ($teams as $team) { ?><option value="<?php echo (int) $team['rowid']; ?>"><?php echo dol_escape_htmltag($team['nom']); ?></option><?php } ?></select></label><label class="cpo-field"><span><?php echo $langs->trans('User'); ?></span><select required name="user_id"><?php foreach ($users as $person) { ?><option value="<?php echo (int) $person['rowid']; ?>"><?php echo dol_escape_htmltag(trim($person['firstname'].' '.$person['lastname']) ?: $person['login']); ?></option><?php } ?></select></label><button class="button button-save"><?php echo $langs->trans('InternalTaskGrant'); ?></button></div></form></section>
<section class="cpo-panel"><table class="cpo-table"><thead><tr><th><?php echo $langs->trans('InternalTaskTeam'); ?></th><th><?php echo $langs->trans('User'); ?></th><th><?php echo $langs->trans('Status'); ?></th><th></th></tr></thead><tbody><?php foreach ($grants as $grant) { ?><tr><td><?php echo dol_escape_htmltag($grant['team_name']); ?></td><td><?php echo dol_escape_htmltag(trim($grant['firstname'].' '.$grant['lastname']) ?: $grant['login']); ?></td><td><?php echo $grant['status'] ? $langs->trans('Enabled') : $langs->trans('Disabled'); ?></td><td><form method="post"><input type="hidden" name="token" value="<?php echo newToken(); ?>"><input type="hidden" name="request_id" value="<?php echo bin2hex(random_bytes(24)); ?>"><input type="hidden" name="team_id" value="<?php echo (int) $grant['fk_usergroup']; ?>"><input type="hidden" name="user_id" value="<?php echo (int) $grant['fk_user']; ?>"><input type="hidden" name="enabled" value="<?php echo $grant['status'] ? 0 : 1; ?>"><button class="button"><?php echo $grant['status'] ? $langs->trans('InternalTaskRevoke') : $langs->trans('InternalTaskGrant'); ?></button></form></td></tr><?php } ?></tbody></table></section>
</main>
<?php llxFooter(); $db->close(); ?>
