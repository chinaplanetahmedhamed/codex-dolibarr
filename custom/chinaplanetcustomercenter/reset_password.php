<?php
define('NOLOGIN', 1);
define('NOREQUIREMENU', 1);
define('NOREQUIREHTML', 1);
require '../../main.inc.php';
require_once __DIR__.'/class/PortalInvitationService.class.php';
require_once __DIR__.'/lib/portal.lib.php';

header('Referrer-Policy: no-referrer');
header('Cache-Control: private, no-store');
$langs->load('chinaplanetcustomercenter@chinaplanetcustomercenter');
$activeLanguage = strtolower((string) ($langs->defaultlang ?? 'en_US'));
$pageDirection = strpos($activeLanguage, 'ar') === 0 ? 'rtl' : 'ltr';
$action = GETPOST('action', 'aZ09');
$submitted = false;
$csrfError = false;
if ($action === 'request_reset') {
    if (!function_exists('checkToken') || !checkToken(GETPOST('csrf_token', 'alphanohtml'))) {
        $csrfError = true;
    } else {
        $submitted = true;
        try {
            $service = new PortalInvitationService($db);
            $service->requestPasswordReset(GETPOST('email', 'alphanohtml'), true);
        } catch (Throwable $e) {
            dol_syslog('M068 public portal reset request was not completed: '.$e->getMessage(), LOG_WARNING);
        }
    }
}

$morecss = array(cpcc_portal_asset('/custom/chinaplanetcustomercenter/css/activation.css'));
llxHeader('', $langs->trans('PortalResetPasswordTitle'), '', '', 0, 0, array(), $morecss);
print '<main class="cpcc-activation" dir="'.$pageDirection.'"><div class="fichecenter"><h1>'.$langs->trans('PortalResetPasswordTitle').'</h1>';
if ($csrfError) print '<div class="error" role="alert">'.dol_escape_htmltag($langs->trans('portal_activation_csrf_invalid')).'</div>';
if ($submitted) {
    print '<div class="ok" role="status">'.dol_escape_htmltag($langs->trans('PortalResetPasswordSubmitted')).'</div>';
} else {
    print '<p>'.$langs->trans('PortalResetPasswordHelp').'</p><form method="post">';
    print '<input type="hidden" name="action" value="request_reset"><input type="hidden" name="csrf_token" value="'.newToken().'">';
    print '<label><span>'.$langs->trans('Email').'</span><input type="email" name="email" maxlength="190" autocomplete="email" required></label>';
    print '<button class="button button-save" type="submit">'.$langs->trans('PortalResetPasswordSubmit').'</button></form>';
}
print '</div></main>';
llxFooter();
