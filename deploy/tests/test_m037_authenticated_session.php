<?php
/** Create or destroy a short-lived synthetic HTTP test session for M037 staging checks. */

if (PHP_SAPI !== 'cli') { fwrite(STDERR, "M037_SESSION=FAIL REASON=CLI_ONLY\n"); exit(1); }
if ($argc !== 2 || !in_array($argv[1], array('admin','external','destroy'), true)) { fwrite(STDERR, "M037_SESSION=FAIL REASON=COMMAND_NOT_ALLOWED\n"); exit(1); }

define('NOLOGIN', 1);
define('NOREQUIREMENU', 1);
define('NOREQUIREHTML', 1);
define('NOREQUIREAJAX', 1);
define('NOCSRFCHECK', 1);

$documentRoot = getenv('M037_DOL_DOCUMENT_ROOT');
if ($documentRoot === false || $documentRoot === '') $documentRoot = '/var/www/html';
$documentRoot = rtrim($documentRoot, '/');
if (!is_file($documentRoot.'/main.inc.php')) { fwrite(STDERR, "M037_SESSION=FAIL REASON=DOLIBARR_ROOT_INVALID\n"); exit(1); }
$_SERVER['DOCUMENT_ROOT'] = $documentRoot;
$_SERVER['SERVER_NAME'] = '127.0.0.1';
$_SERVER['SERVER_PORT'] = '80';
if ($argv[1] === 'destroy') {
    $cookie = getenv('M037_AUTH_COOKIE');
    if (!$cookie || strpos($cookie, '=') === false) { fwrite(STDERR, "M037_SESSION=FAIL REASON=DESTROY_COOKIE_INVALID\n"); exit(1); }
    list($cookieName, $cookieValue) = explode('=', $cookie, 2);
    if (!preg_match('/^DOLSESSID_[A-Za-z0-9_]+$/', $cookieName) || !preg_match('/^[A-Za-z0-9,-]+$/', $cookieValue)) { fwrite(STDERR, "M037_SESSION=FAIL REASON=DESTROY_COOKIE_INVALID\n"); exit(1); }
    $_COOKIE[$cookieName] = $cookieValue;
}
require $documentRoot.'/main.inc.php';
global $db, $conf;

if ($argv[1] === 'destroy') {
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION = array();
        if (!session_destroy()) { fwrite(STDERR, "M037_SESSION=FAIL REASON=DESTROY_FAILED\n"); exit(1); }
    }
    echo "M037_SESSION_DESTROY=PASS\n";
    exit(0);
}

$where = $argv[1] === 'admin'
    ? "u.admin=1 AND u.fk_soc IS NULL"
    : "u.fk_soc IS NOT NULL AND u.fk_soc>0 AND EXISTS (SELECT 1 FROM ".MAIN_DB_PREFIX."user_rights ur WHERE ur.fk_user=u.rowid AND ur.fk_id=500411)";
$res = $db->query("SELECT u.login,u.fk_soc FROM ".MAIN_DB_PREFIX."user u WHERE u.entity=".(int)$conf->entity." AND u.statut=1 AND ".$where." ORDER BY u.rowid LIMIT 1");
$row = $res ? $db->fetch_object($res) : null;
if (!$row || $row->login === '') { fwrite(STDERR, "M037_SESSION=FAIL REASON=ROLE_USER_ABSENT\n"); exit(1); }

$_SESSION['dol_login'] = $row->login;
$_SESSION['dol_logindate'] = time();
$_SESSION['dol_authmode'] = 'm037-synthetic-session';
$_SESSION['dol_entity'] = (int)$conf->entity;
$cookie = session_name().'='.session_id();
$socid = (int)$row->fk_soc;
if (!session_write_close()) { fwrite(STDERR, "M037_SESSION=FAIL REASON=WRITE_FAILED\n"); exit(1); }
echo "M037_AUTH_COOKIE=".$cookie." SOCID=".$socid."\n";
