<?php
define('DOL_URL_ROOT', '/dolibarr');
function dol_escape_htmltag($value) { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function dol_buildpath($path, $type = 0) { return '/custom'.$path; }
require_once dirname(__DIR__).'/lib/portal.lib.php';

$external = (object) array('id' => 10, 'socid' => 2, 'admin' => 0);
$internal = (object) array('id' => 11, 'socid' => 0, 'admin' => 0);

ob_start();
cpcc_portal_navigation($external, 'dashboard');
$externalHtml = ob_get_clean();

$required = array(
    'data-cpcc-logout',
    'data-cpcc-logout-dialog',
    'data-cpcc-logout-cancel',
    'data-cpcc-logout-confirm',
    'هل تريد حقًا تسجيل الخروج؟',
    '/dolibarr/user/logout.php',
    '/custom/chinaplanetcustomercenter/js/logout-confirm.js',
);
foreach ($required as $needle) {
    if (strpos($externalHtml, $needle) === false) {
        fwrite(STDERR, 'Missing portal logout confirmation marker: '.$needle.PHP_EOL);
        exit(1);
    }
}

ob_start();
cpcc_portal_navigation($internal, 'dashboard');
$internalHtml = ob_get_clean();
if ($internalHtml !== '') {
    fwrite(STDERR, 'Internal navigation must not receive the customer logout dialog.'.PHP_EOL);
    exit(1);
}

print "customer-only logout confirmation markup: OK\n";
