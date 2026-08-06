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

function m044RowIds($db, $entity, $scope, $country)
{
    $ids = array();
    $sql = "SELECT rowid FROM ".MAIN_DB_PREFIX.$scope['table']." WHERE entity=".(int) $entity." AND ".$scope['column']."='".$db->escape($country)."' ORDER BY rowid";
    $res = $db->query($sql);
    if (!$res) m044Fail('ROWIDS_FAILED_'.$scope['table']);
    while ($row = $db->fetch_object($res)) $ids[] = (int) $row->rowid;
    return $ids;
}

function m044SnapshotPath()
{
    $path = (string) getenv('M044_SNAPSHOT_PATH');
    if (!preg_match('#^/tmp/m044-country-snapshot-[0-9]+\.json$#', $path)) m044Fail('SNAPSHOT_PATH_INVALID');
    return $path;
}

function m044BuildSnapshot($db, $entity)
{
    $snapshot = array('schema' => 1, 'entity' => (int) $entity, 'rows' => array());
    foreach (m044Scopes() as $scope) $snapshot['rows'][$scope['table']] = m044RowIds($db, $entity, $scope, 'IL');
    return $snapshot;
}

function m044LoadSnapshot($db, $entity)
{
    $path = m044SnapshotPath();
    if (!is_file($path) || is_link($path) || filesize($path) > 1048576) m044Fail('SNAPSHOT_FILE_INVALID');
    $snapshot = json_decode((string) file_get_contents($path), true);
    if (!is_array($snapshot) || ($snapshot['schema'] ?? 0) !== 1 || ($snapshot['entity'] ?? 0) !== (int) $entity || !is_array($snapshot['rows'] ?? null)) m044Fail('SNAPSHOT_CONTENT_INVALID');
    foreach (m044Scopes() as $scope) {
        $ids = $snapshot['rows'][$scope['table']] ?? null;
        if (!is_array($ids)) m044Fail('SNAPSHOT_SCOPE_MISSING_'.$scope['table']);
        $normalized = array_values(array_unique(array_map('intval', $ids)));
        sort($normalized, SORT_NUMERIC);
        if ($normalized !== $ids || (!empty($normalized) && min($normalized) < 1)) m044Fail('SNAPSHOT_IDS_INVALID_'.$scope['table']);
    }
    return $snapshot;
}

function m044CountIdsWithCountry($db, $entity, $scope, $ids, $country)
{
    $total = 0;
    foreach (array_chunk($ids, 500) as $chunk) {
        if (empty($chunk)) continue;
        $sql = "SELECT COUNT(*) total FROM ".MAIN_DB_PREFIX.$scope['table']." WHERE entity=".(int) $entity." AND rowid IN (".implode(',', array_map('intval', $chunk)).") AND ".$scope['column']."='".$db->escape($country)."'";
        $res = $db->query($sql);
        if (!$res) m044Fail('SNAPSHOT_COUNT_FAILED_'.$scope['table']);
        $row = $db->fetch_object($res);
        $total += (int) $row->total;
    }
    return $total;
}

$command = $argv[1] ?? '';
if (!in_array($command, array('check', 'snapshot', 'apply', 'rollback'), true)) m044Fail('COMMAND_NOT_ALLOWED');

$entity = (int) $conf->entity;
$country = $db->query("SELECT code FROM ".MAIN_DB_PREFIX."c_country WHERE code='PS'");
if (!$country || !$db->fetch_object($country)) m044Fail('PS_COUNTRY_ABSENT');

$beforeIl = m044Counts($db, $entity, 'IL');
$beforePs = m044Counts($db, $entity, 'PS');

if ($command === 'snapshot') {
    $snapshot = m044BuildSnapshot($db, $entity);
    $path = m044SnapshotPath();
    $json = json_encode($snapshot, JSON_UNESCAPED_SLASHES);
    if ($json === false || file_put_contents($path, $json, LOCK_EX) === false || !chmod($path, 0600)) m044Fail('SNAPSHOT_WRITE_FAILED');
    print 'M044_PALESTINE_SNAPSHOT=PASS ENTITY='.$entity.' ROWS='.array_sum(array_map('count', $snapshot['rows'])).PHP_EOL;
    exit(0);
}

if ($command === 'apply') {
    $snapshot = m044LoadSnapshot($db, $entity);
    foreach (m044Scopes() as $scope) {
        if (m044RowIds($db, $entity, $scope, 'IL') !== $snapshot['rows'][$scope['table']]) m044Fail('SNAPSHOT_DRIFT_'.$scope['table']);
    }
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

if ($command === 'rollback') {
    $snapshot = m044LoadSnapshot($db, $entity);
    $db->begin();
    foreach (m044Scopes() as $scope) {
        $ids = $snapshot['rows'][$scope['table']];
        if (m044CountIdsWithCountry($db, $entity, $scope, $ids, 'PS') !== count($ids)) {
            $db->rollback();
            m044Fail('ROLLBACK_DRIFT_'.$scope['table']);
        }
        foreach (array_chunk($ids, 500) as $chunk) {
            if (empty($chunk)) continue;
            $sql = "UPDATE ".MAIN_DB_PREFIX.$scope['table']." SET ".$scope['column']."='IL' WHERE entity=".$entity." AND rowid IN (".implode(',', array_map('intval', $chunk)).") AND ".$scope['column']."='PS'";
            if (!$db->query($sql)) {
                $db->rollback();
                m044Fail('ROLLBACK_FAILED_'.$scope['table']);
            }
        }
    }
    if (!$db->commit()) {
        $db->rollback();
        m044Fail('ROLLBACK_COMMIT_FAILED');
    }
    print 'M044_PALESTINE_ROLLBACK=PASS ENTITY='.$entity.' RESTORED='.array_sum(array_map('count', $snapshot['rows'])).PHP_EOL;
    exit(0);
}

print 'M044_PALESTINE_CHECK=PASS ENTITY='.$entity.' IL='.array_sum($beforeIl).' PS='.array_sum($beforePs).PHP_EOL;
