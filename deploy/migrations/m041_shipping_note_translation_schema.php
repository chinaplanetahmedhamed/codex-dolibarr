<?php
/** Additive M041 shipping source-note translation schema migration. */

if (PHP_SAPI !== 'cli') { fwrite(STDERR, "M041_SCHEMA=FAIL REASON=CLI_ONLY\n"); exit(1); }
$commands = array('check','snapshot','apply','rollback','verify-prestate');
if ($argc !== 2 || !in_array($argv[1], $commands, true)) { fwrite(STDERR, "M041_SCHEMA=FAIL REASON=COMMAND_NOT_ALLOWED\n"); exit(1); }

define('NOLOGIN', 1);
define('NOREQUIREMENU', 1);
define('NOREQUIREHTML', 1);
define('NOREQUIREAJAX', 1);
define('NOCSRFCHECK', 1);

$documentRoot = getenv('M041_DOL_DOCUMENT_ROOT');
if ($documentRoot === false || $documentRoot === '') $documentRoot = '/var/www/html';
$documentRoot = rtrim($documentRoot, '/');
if ($documentRoot === '' || !is_file($documentRoot.'/master.inc.php')) { fwrite(STDERR, "M041_SCHEMA=FAIL REASON=DOLIBARR_ROOT_INVALID\n"); exit(1); }
$_SERVER['DOCUMENT_ROOT'] = $documentRoot;
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['SERVER_PORT'] = '80';
require $documentRoot.'/master.inc.php';
global $db, $conf;

$table = MAIN_DB_PREFIX.'chinaplanetsp_rate';
$columns = array(
    'source_notes_raw' => 'mediumtext NULL',
    'source_notes' => 'mediumtext NULL',
    'notes_en' => 'mediumtext NULL',
    'translation_status' => "varchar(16) NOT NULL DEFAULT 'not_needed'",
    'translation_source_hash' => 'char(64) NULL',
    'translation_provider' => 'varchar(64) NULL',
    'translation_model' => 'varchar(128) NULL',
    'translation_error' => 'varchar(128) NULL',
    'fk_user_translation_review' => 'integer NULL',
    'date_translation_review' => 'datetime NULL',
);
$index = 'idx_chinaplanetsp_rate_translation';
$expectedColumns = array(
    'source_notes_raw'=>array('type'=>'mediumtext','null'=>'YES','default'=>null),
    'source_notes'=>array('type'=>'mediumtext','null'=>'YES','default'=>null),
    'notes_en'=>array('type'=>'mediumtext','null'=>'YES','default'=>null),
    'translation_status'=>array('type'=>'varchar(16)','null'=>'NO','default'=>'not_needed'),
    'translation_source_hash'=>array('type'=>'char(64)','null'=>'YES','default'=>null),
    'translation_provider'=>array('type'=>'varchar(64)','null'=>'YES','default'=>null),
    'translation_model'=>array('type'=>'varchar(128)','null'=>'YES','default'=>null),
    'translation_error'=>array('type'=>'varchar(128)','null'=>'YES','default'=>null),
    'fk_user_translation_review'=>array('type'=>'integer','null'=>'YES','default'=>null),
    'date_translation_review'=>array('type'=>'datetime','null'=>'YES','default'=>null),
);

$query = function ($sql, $reason) use ($db) {
    $res = $db->query($sql);
    if (!$res) { fwrite(STDERR, 'M041_SCHEMA=FAIL REASON='.$reason."\n"); exit(1); }
    return $res;
};
$tableExists = function () use ($query, $db, $table) {
    $pattern = str_replace(array('\\','_','%'), array('\\\\','\\_','\\%'), $table);
    return $db->num_rows($query("SHOW TABLES LIKE '".$db->escape($pattern)."'", 'TABLE_CHECK_FAILED')) === 1;
};
$columnExists = function ($column) use ($query, $db, $table) {
    return $db->num_rows($query("SHOW COLUMNS FROM `".$table."` LIKE '".$db->escape($column)."'", 'COLUMN_CHECK_FAILED')) === 1;
};
$indexExists = function () use ($query, $db, $table, $index) {
    return $db->num_rows($query("SHOW INDEX FROM `".$table."` WHERE Key_name='".$db->escape($index)."'", 'INDEX_CHECK_FAILED')) > 0;
};
$schemaExact = function () use ($query, $db, $table, $index, $expectedColumns) {
    foreach ($expectedColumns as $name=>$expected) {
        $res = $query("SHOW COLUMNS FROM `".$table."` LIKE '".$db->escape($name)."'", 'COLUMN_METADATA_FAILED');
        $column = $db->fetch_object($res);
        if (!$column) return false;
        $actualType = strtolower((string) $column->Type);
        $typeOk = $expected['type'] === 'integer' ? preg_match('/^int(?:\([0-9]+\))?$/', $actualType) === 1 : $actualType === $expected['type'];
        if (!$typeOk || strtoupper((string) $column->Null) !== $expected['null']) return false;
        $actualDefault = $column->Default === null ? null : (string) $column->Default;
        if ($actualDefault !== $expected['default']) return false;
    }
    $res = $query("SHOW INDEX FROM `".$table."` WHERE Key_name='".$db->escape($index)."'", 'INDEX_METADATA_FAILED');
    $indexRows = array();
    while ($row = $db->fetch_object($res)) $indexRows[(int) $row->Seq_in_index] = (string) $row->Column_name;
    ksort($indexRows);
    $actual = array_values($indexRows);
    return $actual === array('entity','fk_batch','translation_status');
};
$schemaState = function () use ($columns, $columnExists, $indexExists) {
    $state = array('version'=>1, 'columns'=>array(), 'index'=>$indexExists());
    foreach ($columns as $name=>$definition) $state['columns'][$name] = $columnExists($name);
    return $state;
};
$readSnapshot = function () use ($conf) {
    $encoded = getenv('M041_SCHEMA_PRESTATE_B64');
    $decoded = $encoded ? base64_decode($encoded, true) : false;
    $snapshot = $decoded !== false ? json_decode($decoded, true) : null;
    if (!is_array($snapshot) || (int)($snapshot['version'] ?? 0) !== 1 || (int)($snapshot['entity'] ?? 0) !== (int)$conf->entity || !isset($snapshot['columns']) || !is_array($snapshot['columns']) || !array_key_exists('index', $snapshot)) return null;
    return $snapshot;
};

if (!$tableExists()) { fwrite(STDERR, "M041_SCHEMA=FAIL REASON=RATE_TABLE_ABSENT\n"); exit(1); }

if ($argv[1] === 'snapshot') {
    $snapshot = $schemaState();
    $snapshot['entity'] = (int) $conf->entity;
    $json = json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) { fwrite(STDERR, "M041_SCHEMA=FAIL REASON=SNAPSHOT_ENCODING_FAILED\n"); exit(1); }
    echo 'M041_SCHEMA_SNAPSHOT='.base64_encode($json)."\n";
    exit(0);
}

if ($argv[1] === 'check') {
    $state = $schemaState();
    foreach ($state['columns'] as $exists) if (!$exists) { fwrite(STDERR, "M041_SCHEMA_CHECK=ABSENT\n"); exit(2); }
    if (!$state['index']) { fwrite(STDERR, "M041_SCHEMA_CHECK=ABSENT\n"); exit(2); }
    if (!$schemaExact()) { fwrite(STDERR, "M041_SCHEMA_CHECK=INVALID\n"); exit(3); }
    echo "M041_SCHEMA_CHECK=PASS\n";
    exit(0);
}

if ($argv[1] === 'verify-prestate') {
    $snapshot = $readSnapshot();
    if ($snapshot === null) { fwrite(STDERR, "M041_SCHEMA=FAIL REASON=VERIFY_PRESTATE_INVALID\n"); exit(1); }
    $actual = $schemaState();
    foreach ($columns as $name=>$definition) if ((bool)($snapshot['columns'][$name] ?? false) !== (bool)$actual['columns'][$name]) {
        fwrite(STDERR, "M041_SCHEMA=FAIL REASON=VERIFY_PRESTATE_COLUMN_MISMATCH\n"); exit(1);
    }
    if ((bool)$snapshot['index'] !== (bool)$actual['index']) { fwrite(STDERR, "M041_SCHEMA=FAIL REASON=VERIFY_PRESTATE_INDEX_MISMATCH\n"); exit(1); }
    echo "M041_SCHEMA_PRESTATE=PASS\n";
    exit(0);
}

if ($argv[1] === 'rollback') {
    $snapshot = $readSnapshot();
    if ($snapshot === null) { fwrite(STDERR, "M041_SCHEMA=FAIL REASON=ROLLBACK_PRESTATE_INVALID\n"); exit(1); }
    $actual = $schemaState();
    $dataConditions = array();
    foreach ($actual['columns'] as $name => $exists) {
        if (!$exists) continue;
        if ($name === 'translation_status') $dataConditions[] = "`translation_status`<>'not_needed'";
        else $dataConditions[] = "`".$name."` IS NOT NULL";
    }
    if (!empty($dataConditions)) {
        $res = $query("SELECT COUNT(*) total FROM `".$table."` WHERE ".implode(' OR ', $dataConditions), 'ROLLBACK_DATA_CHECK_FAILED');
        $row = $db->fetch_object($res);
        if (!$row || (int)$row->total > 0) { fwrite(STDERR, "M041_SCHEMA=FAIL REASON=ROLLBACK_DATA_PRESENT\n"); exit(1); }
    }
    if (empty($snapshot['index']) && $actual['index']) $query("ALTER TABLE `".$table."` DROP INDEX `".$index."`", 'ROLLBACK_INDEX_FAILED');
    foreach (array_reverse(array_keys($columns)) as $name) {
        if (empty($snapshot['columns'][$name]) && $columnExists($name)) $query("ALTER TABLE `".$table."` DROP COLUMN `".$name."`", 'ROLLBACK_COLUMN_FAILED');
    }
    echo "M041_SCHEMA_ROLLBACK=PASS\n";
    exit(0);
}

foreach ($columns as $name=>$definition) {
    if (!$columnExists($name)) $query("ALTER TABLE `".$table."` ADD COLUMN `".$name."` ".$definition, 'ADD_COLUMN_FAILED');
}
if (!$indexExists()) $query("ALTER TABLE `".$table."` ADD INDEX `".$index."` (`entity`,`fk_batch`,`translation_status`)", 'ADD_INDEX_FAILED');
$state = $schemaState();
foreach ($state['columns'] as $exists) if (!$exists) { fwrite(STDERR, "M041_SCHEMA=FAIL REASON=POST_APPLY_VERIFY_FAILED\n"); exit(1); }
if (!$state['index']) { fwrite(STDERR, "M041_SCHEMA=FAIL REASON=POST_APPLY_VERIFY_FAILED\n"); exit(1); }
if (!$schemaExact()) { fwrite(STDERR, "M041_SCHEMA=FAIL REASON=POST_APPLY_METADATA_INVALID\n"); exit(1); }
echo "M041_SCHEMA_APPLY=PASS\n";
