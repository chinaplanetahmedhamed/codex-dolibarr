<?php
/** Read-only M068 staging readiness check. Never print secrets or customer data. */

if (PHP_SAPI !== 'cli') { fwrite(STDERR, "M068_STAGING_CONFIGURATION=FAIL REASON=CLI_ONLY\n"); exit(1); }
define('NOLOGIN', 1);
define('NOREQUIREMENU', 1);
define('NOREQUIREHTML', 1);
define('NOCSRFCHECK', 1);
$documentRoot = rtrim((string) (getenv('M068_DOL_DOCUMENT_ROOT') ?: '/var/www/html'), '/');
if (!is_file($documentRoot.'/master.inc.php')) { fwrite(STDERR, "M068_STAGING_CONFIGURATION=FAIL REASON=DOLIBARR_ROOT_INVALID\n"); exit(1); }
$_SERVER['DOCUMENT_ROOT'] = $documentRoot;
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['SERVER_PORT'] = '80';
require $documentRoot.'/master.inc.php';
global $db, $conf;

$fail = static function ($reason) { fwrite(STDERR, "M068_STAGING_CONFIGURATION=FAIL REASON=".$reason."\n"); exit(1); };
$key = (string) getenv('CHINAPLANET_PORTAL_INVITATION_HMAC_KEY');
if (strlen($key) < 32 && function_exists('getDolGlobalString')) $key = (string) getDolGlobalString('CHINAPLANET_PORTAL_INVITATION_HMAC_KEY');
if (strlen($key) < 32) $fail('HMAC_SECRET_MISSING');
$key = str_repeat('*', strlen($key));
$from = function_exists('getDolGlobalString') ? (string) getDolGlobalString('MAIN_MAIL_EMAIL_FROM') : '';
if (!filter_var($from, FILTER_VALIDATE_EMAIL)) $fail('MAIL_FROM_INVALID');
$sendMode = function_exists('getDolGlobalString') ? trim((string) getDolGlobalString('MAIN_MAIL_SENDMODE')) : '';
$smtpServer = function_exists('getDolGlobalString') ? strtolower(trim((string) getDolGlobalString('MAIN_MAIL_SMTP_SERVER'))) : '';
$smtpPort = function_exists('getDolGlobalInt') ? getDolGlobalInt('MAIN_MAIL_SMTP_PORT') : 0;
if (!in_array($sendMode, array('smtp', 'smtps'), true) || !in_array($smtpServer, array('mailpit', 'erp-staging-mailpit-1'), true) || $smtpPort !== 1025) $fail('MAIL_TRANSPORT_NOT_STAGING_MAILPIT');
$entity = (int) $conf->entity;
if ($entity <= 0) $fail('ENTITY_INVALID');
if (!function_exists('getDolGlobalInt') || getDolGlobalInt('MAIN_MODULE_CHINAPLANETCUSTOMERCENTER') !== 1) $fail('CUSTOMER_CENTER_MODULE_DISABLED');
$right = $db->query("SELECT id FROM ".MAIN_DB_PREFIX."rights_def WHERE entity=".$entity." AND id=500201 AND module='chinaplanetcustomercenter' AND enabled='1'");
if (!$right || $db->num_rows($right) !== 1) $fail('PORTAL_RIGHT_MISSING');
$group = $db->query("SELECT g.rowid FROM ".MAIN_DB_PREFIX."usergroup g INNER JOIN ".MAIN_DB_PREFIX."usergroup_rights gr ON gr.fk_usergroup=g.rowid AND gr.fk_id=500201 AND gr.entity=".$entity." WHERE g.nom='Customers Portal' AND g.entity IN (0,".$entity.") ORDER BY g.entity DESC LIMIT 2");
if (!$group || $db->num_rows($group) !== 1) $fail('PORTAL_GROUP_OR_RIGHT_AMBIGUOUS');
$coreReset = $db->query("SELECT COUNT(*) AS total FROM ".MAIN_DB_PREFIX."usergroup g INNER JOIN ".MAIN_DB_PREFIX."usergroup_rights gr ON gr.fk_usergroup=g.rowid AND gr.entity=".$entity." WHERE g.nom='Customers Portal' AND g.entity IN (0,".$entity.") AND gr.fk_id=343");
$coreResetRow = $coreReset ? $db->fetch_object($coreReset) : null;
if (!$coreResetRow || (int) $coreResetRow->total !== 0) $fail('PORTAL_GROUP_CORE_RESET_RIGHT_FORBIDDEN');
echo "M068_STAGING_CONFIGURATION=PASS HMAC=CONFIGURED MAIL=CONFIGURED CUSTOMER_CENTER=ENABLED PORTAL_GROUP_RIGHT=READY CUSTOMER_DATA=UNREAD\n";
