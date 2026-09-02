<?php
/** Staging-only Mailpit configuration gate for M068. Never sends mail. */

if (PHP_SAPI !== 'cli') { fwrite(STDERR, "M068_MAILPIT=FAIL REASON=CLI_ONLY\n"); exit(1); }
if ($argc !== 2 || !in_array($argv[1], array('check', 'apply', 'verify'), true)) { fwrite(STDERR, "M068_MAILPIT=FAIL REASON=COMMAND_NOT_ALLOWED\n"); exit(1); }

define('NOLOGIN', 1);
define('NOREQUIREMENU', 1);
define('NOREQUIREHTML', 1);
define('NOREQUIREAJAX', 1);
define('NOCSRFCHECK', 1);
$documentRoot = rtrim((string) (getenv('M068_DOL_DOCUMENT_ROOT') ?: '/var/www/html'), '/');
if ($documentRoot === '' || !is_file($documentRoot.'/master.inc.php')) { fwrite(STDERR, "M068_MAILPIT=FAIL REASON=DOLIBARR_ROOT_INVALID\n"); exit(1); }
$_SERVER['DOCUMENT_ROOT'] = $documentRoot;
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['SERVER_PORT'] = '80';
require $documentRoot.'/master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
global $db, $conf;

$lockName = '';
$locked = false;
$releaseLock = static function () use ($db, &$lockName, &$locked) {
    if (!$locked) return true;
    $res = $db->query("SELECT RELEASE_LOCK('".$db->escape($lockName)."') AS released");
    $row = $res ? $db->fetch_object($res) : null;
    if (!$row || (int) $row->released !== 1) return false;
    $locked = false;
    return true;
};
$fail = static function ($reason) use (&$releaseLock) {
    if (!$releaseLock()) $reason = 'LOCK_RELEASE_FAILED_AFTER_'.$reason;
    fwrite(STDERR, 'M068_MAILPIT=FAIL REASON='.$reason."\n");
    exit(1);
};
$entity = (int) $conf->entity;
if ($entity <= 0) $fail('ENTITY_INVALID');
$from = function_exists('getDolGlobalString') ? trim((string) getDolGlobalString('MAIN_MAIL_EMAIL_FROM')) : '';
if (!filter_var($from, FILTER_VALIDATE_EMAIL)) $fail('MAIL_FROM_INVALID');
$lockName = 'chinaplanetos:m068:mailpit:'.$entity;
$lockResult = $db->query("SELECT GET_LOCK('".$db->escape($lockName)."',0) AS acquired");
$lockRow = $lockResult ? $db->fetch_object($lockResult) : null;
if (!$lockRow || (int) $lockRow->acquired !== 1) $fail('LOCK_UNAVAILABLE');
$locked = true;

$rows = array();
foreach (array('MAIN_MAIL_SENDMODE', 'MAIN_MAIL_SMTP_SERVER', 'MAIN_MAIL_SMTP_PORT') as $name) {
    $res = $db->query("SELECT rowid,value,type,visible FROM ".MAIN_DB_PREFIX."const WHERE entity=".$entity." AND name='".$db->escape($name)."'");
    if (!$res) $fail('CONSTANT_QUERY_FAILED');
    if ($db->num_rows($res) > 1) $fail('CONSTANT_DUPLICATE');
    $rows[$name] = $db->fetch_object($res) ?: null;
}
$ready = $rows['MAIN_MAIL_SENDMODE'] && strtolower(trim((string) $rows['MAIN_MAIL_SENDMODE']->value)) === 'smtp'
    && $rows['MAIN_MAIL_SMTP_SERVER'] && strtolower(trim((string) $rows['MAIN_MAIL_SMTP_SERVER']->value)) === 'mailpit'
    && $rows['MAIN_MAIL_SMTP_PORT'] && (int) $rows['MAIN_MAIL_SMTP_PORT']->value === 1025;

if ($argv[1] === 'check') {
    if ($ready) { if (!$releaseLock()) $fail('LOCK_RELEASE_FAILED'); echo "M068_MAILPIT_CHECK=READY MAIL_SEND_EXECUTED=NO SECRET_OUTPUT=NO\n"; exit(0); }
    if (!$releaseLock()) $fail('LOCK_RELEASE_FAILED');
    echo "M068_MAILPIT_CHECK=CONFIGURATION_REQUIRED MAIL_SEND_EXECUTED=NO SECRET_OUTPUT=NO\n";
    exit(2);
}
if ($argv[1] === 'verify') {
    if (!$ready) $fail('VERIFY_NOT_READY');
    if (!$releaseLock()) $fail('LOCK_RELEASE_FAILED');
    echo "M068_MAILPIT_VERIFY=PASS TRANSPORT=MAILPIT PORT=1025 MAIL_SEND_EXECUTED=NO SECRET_OUTPUT=NO\n";
    exit(0);
}
if ($ready) {
    if (!$releaseLock()) $fail('LOCK_RELEASE_FAILED');
    echo "M068_MAILPIT_APPLY=NOOP_ALREADY_CONFIGURED TRANSPORT=MAILPIT PORT=1025 MAIL_SEND_EXECUTED=NO SECRET_OUTPUT=NO\n";
    exit(0);
}

if (!$db->begin()) $fail('TRANSACTION_BEGIN_FAILED');
$changes = array(
    'MAIN_MAIL_SENDMODE' => 'smtp',
    'MAIN_MAIL_SMTP_SERVER' => 'mailpit',
    'MAIN_MAIL_SMTP_PORT' => '1025',
);
foreach ($changes as $name => $value) {
    if (dolibarr_set_const($db, $name, $value, 'chaine', 0, 'M068 staging-only Mailpit safety route', $entity) <= 0) {
        if (!$db->rollback()) $fail('TRANSACTION_ROLLBACK_FAILED_AFTER_WRITE');
        $fail('CONSTANT_WRITE_FAILED');
    }
}
if (!$db->commit()) {
    if (!$db->rollback()) $fail('TRANSACTION_ROLLBACK_FAILED_AFTER_COMMIT');
    $fail('TRANSACTION_COMMIT_FAILED');
}

foreach ($changes as $name => $value) {
    $res = $db->query("SELECT value FROM ".MAIN_DB_PREFIX."const WHERE entity=".$entity." AND name='".$db->escape($name)."'");
    if (!$res || $db->num_rows($res) !== 1) $fail('POST_WRITE_QUERY_FAILED');
    $row = $db->fetch_object($res);
    if (!$row || (string) $row->value !== $value) $fail('POST_WRITE_VERIFY_FAILED');
}
if (!$releaseLock()) $fail('LOCK_RELEASE_FAILED');
echo "M068_MAILPIT_APPLY=PASS TRANSPORT=MAILPIT PORT=1025 DATABASE_CONFIGURATION_CHANGED=YES MAIL_SEND_EXECUTED=NO SECRET_OUTPUT=NO CUSTOMER_DATA_OUTPUT=NO PRODUCTION_ACCESSED=NO\n";
