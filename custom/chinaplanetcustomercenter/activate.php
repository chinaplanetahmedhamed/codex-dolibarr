<?php
define('NOLOGIN', 1);
define('NOREQUIREMENU', 1);
define('NOREQUIREHTML', 1);
require '../../main.inc.php';
require_once __DIR__.'/class/PortalInvitationService.class.php';
header('Referrer-Policy: no-referrer');
header('Cache-Control: private, no-store');

$langs->load('chinaplanetcustomercenter@chinaplanetcustomercenter');
$activeLanguage = strtolower((string) ($langs->defaultlang ?? 'en_US'));
$pageDirection = strpos($activeLanguage, 'ar') === 0 ? 'rtl' : 'ltr';
$token = GETPOST('token', 'alphanohtml');
$action = GETPOST('action', 'aZ09');
$error = '';
try {
    $service = new PortalInvitationService($db);
    $invitation = $service->inspect($token);
    if ($action === 'activate') {
        if (!function_exists('checkToken') || !checkToken(GETPOST('csrf_token','alphanohtml'))) throw new PortalInvitationException('portal_activation_csrf_invalid');
        $service->accept($token, GETPOST('password','none'), GETPOST('password_confirm','none'));
        header('Location: '.DOL_URL_ROOT.'/index.php?portal_activated=1'); exit;
    }
} catch (Throwable $e) {
    $publicErrors = array('portal_activation_invalid','portal_activation_csrf_invalid','portal_activation_password_invalid','portal_activation_rate_limited');
    $error = in_array($e->getMessage(), $publicErrors, true) ? $e->getMessage() : 'portal_activation_unavailable';
    $invitation = null;
}

$morecss = array(cpcc_portal_asset('/custom/chinaplanetcustomercenter/css/activation.css'));
llxHeader('', $langs->trans('PortalActivationTitle'), '', '', 0, 0, array(), $morecss);
print '<main class="cpcc-activation" dir="'.$pageDirection.'"><div class="fichecenter"><h1>'.$langs->trans('PortalActivationTitle').'</h1>';
if ($error !== '') print '<div class="error" role="alert">'.dol_escape_htmltag($langs->trans($error)).'</div>';
if ($invitation) {
    print '<p>'.$langs->trans('PortalActivationHelp', dol_escape_htmltag($invitation['ref'])).'</p>';
    print '<form method="post"><input type="hidden" name="action" value="activate"><input type="hidden" name="token" value="'.dol_escape_htmltag($token).'"><input type="hidden" name="csrf_token" value="'.newToken().'">';
    print '<label><span>'.$langs->trans('Password').'</span><input type="password" name="password" minlength="12" maxlength="128" autocomplete="new-password" required></label>';
    print '<label><span>'.$langs->trans('PasswordRetype').'</span><input type="password" name="password_confirm" minlength="12" maxlength="128" autocomplete="new-password" required></label>';
    print '<button class="button button-save" type="submit">'.$langs->trans('PortalActivationSubmit').'</button></form>';
} elseif ($error === '') print '<div class="warning" role="alert">'.$langs->trans('portal_activation_invalid').'</div>';
print '<p><a href="'.dol_escape_htmltag(dol_buildpath('/chinaplanetcustomercenter/reset_password.php', 1)).'">'.$langs->trans('PortalResetPasswordLink').'</a></p>';
print '</div></main>';
llxFooter();
