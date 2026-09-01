<?php
/** Root-only data repair primitive for the separately approved M061-R01 release. */

$allowed = array('dry-run', 'apply', 'verify', 'rollback', 'status');
if (PHP_SAPI !== 'cli' || $argc !== 2 || !in_array($argv[1], $allowed, true)) {
    fwrite(STDERR, "M061_REPAIR=FAIL REASON=INVALID_COMMAND\n");
    exit(64);
}
$command = $argv[1];
if (in_array($command, array('apply', 'rollback'), true)) {
    $effectiveUid = function_exists('posix_geteuid') ? posix_geteuid() : -1;
    if ($effectiveUid !== 0 || getenv('M061_REPAIR_CONTEXT') !== 'm061-r01-root-only') {
        fwrite(STDERR, "M061_REPAIR=FAIL REASON=RELEASE_CONTEXT_REQUIRED\n");
        exit(66);
    }
}

define('NOLOGIN', 1);
define('NOREQUIREMENU', 1);
define('NOREQUIREHTML', 1);
define('NOCSRFCHECK', 1);

$documentRoot = getenv('M061_DOL_DOCUMENT_ROOT');
if ($documentRoot !== '/var/www/html' || !is_file($documentRoot.'/master.inc.php')) {
    fwrite(STDERR, "M061_REPAIR=FAIL REASON=DOL_DOCUMENT_ROOT_INVALID\n");
    exit(65);
}
$_SERVER['DOCUMENT_ROOT'] = $documentRoot;
$_SERVER['SERVER_NAME'] = '127.0.0.1';
$_SERVER['SERVER_PORT'] = '80';
require $documentRoot.'/master.inc.php';
require_once $documentRoot.'/user/class/user.class.php';
require_once dirname(__DIR__, 2).'/custom/chinaplanetlogistics/lib/m061_repair.lib.php';

function cplM061Fail($reason, $code = 1)
{
    $reason = preg_replace('/[^A-Z0-9_]/', '_', strtoupper((string) $reason));
    fwrite(STDERR, 'M061_REPAIR=FAIL REASON='.$reason."\n");
    exit($code);
}

function cplM061PlanFromStdin($entity)
{
    $raw = stream_get_contents(STDIN, 1048577);
    if ($raw === false || $raw === '' || strlen($raw) > 1048576) cplM061Fail('PLAN_INPUT_INVALID');
    $plan = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE || !cpl_m061_repair_validate_plan($plan, $entity)) cplM061Fail('PLAN_INVALID');
    return $plan;
}

function cplM061Administrator($db, $entity)
{
    $sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'user WHERE entity='.(int) $entity.' AND statut=1 AND admin=1 AND COALESCE(fk_soc,0)=0 ORDER BY rowid LIMIT 1';
    $resql = $db->query($sql);
    $row = $resql ? $db->fetch_object($resql) : null;
    if (!$row) cplM061Fail('ACTIVE_ADMINISTRATOR_UNAVAILABLE');
    $administrator = new User($db);
    if ($administrator->fetch((int) $row->rowid) <= 0) cplM061Fail('ADMINISTRATOR_LOAD_FAILED');
    return $administrator;
}

global $db, $conf;
$entity = (int) $conf->entity;
if ($entity <= 0 || !cpl_shipment_schema_ready($db)) cplM061Fail('SHIPMENT_SCHEMA_NOT_READY');

try {
    if ($command === 'dry-run' || $command === 'status') {
        $plan = cpl_m061_repair_scan_plan($db, $entity);
        if ($command === 'dry-run') {
            echo json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
        } else {
            echo 'M061_REPAIR_STATUS=READY ENTITY='.$entity.' CANDIDATES='.$plan['candidate_count'].' AMBIGUOUS='.$plan['ambiguous_count'].' PLAN_SHA='.$plan['plan_sha256']."\n";
        }
        exit(0);
    }

    $plan = cplM061PlanFromStdin($entity);
    if ($command === 'verify') {
        $result = cpl_m061_repair_verify_plan($db, $entity, $plan);
        if (empty($result['ok'])) cplM061Fail('VERIFY_FAILED', 2);
        echo 'M061_REPAIR_VERIFY=PASS CANDIDATES='.$result['candidate_count'].' PLAN_SHA='.$plan['plan_sha256']."\n";
        exit(0);
    }
    if ($command === 'apply') {
        $result = cpl_m061_repair_apply_plan($db, $entity, $plan, cplM061Administrator($db, $entity));
        echo 'M061_REPAIR_APPLY=PASS CHANGED='.$result['changed'].' ALREADY_APPLIED='.(!empty($result['already_applied']) ? 'YES' : 'NO').' PLAN_SHA='.$plan['plan_sha256']."\n";
        exit(0);
    }
    $result = cpl_m061_repair_rollback_plan($db, $entity, $plan, cplM061Administrator($db, $entity));
    echo 'M061_REPAIR_ROLLBACK=PASS CHANGED='.$result['changed'].' ALREADY_ROLLED_BACK='.(!empty($result['already_rolled_back']) ? 'YES' : 'NO').' PLAN_SHA='.$plan['plan_sha256']."\n";
} catch (Throwable $exception) {
    cplM061Fail($exception->getMessage());
}
