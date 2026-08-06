<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit(1);
}

$documentRoot = getenv('M044_DOL_DOCUMENT_ROOT');
if ($documentRoot === false || $documentRoot === '') $documentRoot = '/var/www/html';
$_SERVER['DOCUMENT_ROOT'] = rtrim($documentRoot, '/');
$_SERVER['SERVER_NAME'] = '127.0.0.1';
$_SERVER['SERVER_PORT'] = '8088';

define('NOLOGIN', 1);
define('NOREQUIREMENU', 1);
define('NOREQUIREHTML', 1);
define('NOCSRFCHECK', 1);
require $_SERVER['DOCUMENT_ROOT'].'/master.inc.php';

function m044Fail($reason)
{
    fwrite(STDERR, 'M044_PALESTINE_FAIL='.$reason.PHP_EOL);
    exit(1);
}

function m044Scopes()
{
    return array(
        array('table' => 'chinaplanetsp_rate', 'column' => 'destination_country'),
        array('table' => 'chinaplanetsp_port', 'column' => 'country_code'),
        array('table' => 'chinaplanetsp_rule', 'column' => 'country_code'),
        array('table' => 'chinaplanetsp_block', 'column' => 'country_code'),
        array('table' => 'chinaplanetsp_sop_revision', 'column' => 'country_code'),
    );
}

function m044Counts($db, $entity, $country)
{
    $counts = array();
    foreach (m044Scopes() as $scope) {
        $sql = "SELECT COUNT(*) total FROM ".MAIN_DB_PREFIX.$scope['table']." WHERE entity=".(int) $entity." AND ".$scope['column']."='".$db->escape($country)."'";
        $res = $db->query($sql);
        if (!$res) m044Fail('COUNT_FAILED_'.$scope['table']);
        $row = $db->fetch_object($res);
        $counts[$scope['table']] = (int) $row->total;
    }
    return $counts;
}

$command = $argv[1] ?? '';
if (!in_array($command, array('check', 'apply'), true)) m044Fail('COMMAND_NOT_ALLOWED');

$entity = (int) $conf->entity;
$country = $db->query("SELECT code FROM ".MAIN_DB_PREFIX."c_country WHERE code='PS'");
if (!$country || !$db->fetch_object($country)) m044Fail('PS_COUNTRY_ABSENT');

$beforeIl = m044Counts($db, $entity, 'IL');
$beforePs = m044Counts($db, $entity, 'PS');

if ($command === 'apply') {
    $db->begin();
    foreach (m044Scopes() as $scope) {
        $sql = "UPDATE ".MAIN_DB_PREFIX.$scope['table']." SET ".$scope['column']."='PS' WHERE entity=".$entity." AND ".$scope['column']."='IL'";
        if (!$db->query($sql)) {
            $db->rollback();
            m044Fail('UPDATE_FAILED_'.$scope['table']);
        }
    }
    $afterIl = m044Counts($db, $entity, 'IL');
    $afterPs = m044Counts($db, $entity, 'PS');
    foreach ($afterIl as $table => $count) {
        if ($count !== 0 || $afterPs[$table] !== $beforePs[$table] + $beforeIl[$table]) {
            $db->rollback();
            m044Fail('COUNT_MISMATCH_'.$table);
        }
    }
    if (!$db->commit()) {
        $db->rollback();
        m044Fail('COMMIT_FAILED');
    }
    print 'M044_PALESTINE_APPLY=PASS ENTITY='.$entity.' UPDATED='.array_sum($beforeIl).PHP_EOL;
    exit(0);
}

print 'M044_PALESTINE_CHECK=PASS ENTITY='.$entity.' IL='.array_sum($beforeIl).' PS='.array_sum($beforePs).PHP_EOL;
