<?php
/** Additive lifecycle columns. Never rewrite request data or drop rollback evidence. */
function m096RequestLifecycleSchema($db, $apply = false)
{
    $table = MAIN_DB_PREFIX.'cpo_public_request';
    $columns = array(
        'lifecycle_state'=>"varchar(16) NOT NULL DEFAULT 'active'",
        'lifecycle_version'=>'integer NOT NULL DEFAULT 1',
        'lifecycle_history'=>'mediumtext NULL',
    );
    foreach ($columns as $name=>$definition) {
        $res = $db->query("SHOW COLUMNS FROM ".$table." LIKE '".$name."'");
        if (!$res) throw new RuntimeException('M096_TABLE_UNAVAILABLE');
        $column = $db->fetch_object($res);
        if (!$column) {
            if (!$apply) return false;
            if (!$db->query('ALTER TABLE '.$table.' ADD COLUMN '.$name.' '.$definition)) throw new RuntimeException('M096_ADD_COLUMN_FAILED');
        } elseif (($name === 'lifecycle_state' && ($column->Type !== 'varchar(16)' || $column->Default !== 'active' || $column->Null !== 'NO'))
            || ($name === 'lifecycle_version' && (!preg_match('/^int(?:\(\d+\))?$/', $column->Type) || (int) $column->Default !== 1 || $column->Null !== 'NO'))
            || ($name === 'lifecycle_history' && ($column->Type !== 'mediumtext' || $column->Null !== 'YES'))) {
            throw new RuntimeException('M096_COLUMN_CONTRACT_INVALID');
        }
    }
    $res = $db->query("SHOW INDEX FROM ".$table." WHERE Key_name='idx_cpo_public_request_folder'");
    if (!$res) throw new RuntimeException('M096_INDEX_CHECK_FAILED');
    $indexed = array();
    while ($index = $db->fetch_object($res)) $indexed[(int)$index->Seq_in_index] = $index->Column_name;
    ksort($indexed);
    if (!$indexed) {
        if (!$apply) return false;
        if (!$db->query('ALTER TABLE '.$table.' ADD INDEX idx_cpo_public_request_folder (entity,lifecycle_state,date_creation)')) throw new RuntimeException('M096_ADD_INDEX_FAILED');
    } elseif (array_values($indexed) !== array('entity', 'lifecycle_state', 'date_creation')) throw new RuntimeException('M096_INDEX_CONTRACT_INVALID');
    return true;
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    if (PHP_SAPI !== 'cli' || $argc !== 2 || !in_array($argv[1], array('check', 'apply'), true)) exit(1);
    $root = rtrim((string) getenv('M096_DOL_DOCUMENT_ROOT'), '/');
    if (!$root || !is_file($root.'/master.inc.php')) exit(1);
    define('NOLOGIN', 1); define('NOREQUIREMENU', 1); define('NOREQUIREHTML', 1); define('NOCSRFCHECK', 1);
    $_SERVER['DOCUMENT_ROOT'] = $root;
    require $root.'/master.inc.php';
    try {
        $ok = m096RequestLifecycleSchema($db, $argv[1] === 'apply');
        echo 'M096_SCHEMA='.($ok ? 'PASS' : 'ABSENT')."\n";
        exit($ok ? 0 : 2);
    } catch (Throwable $e) {
        fwrite(STDERR, "M096_SCHEMA=FAIL\n"); exit(1);
    }
}
