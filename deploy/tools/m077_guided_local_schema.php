<?php

/** Local-only guided-receipt marker migration with a full pre-change dump. */
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "M077_GUIDED_SCHEMA=FAIL REASON=CLI_ONLY\n");
    exit(1);
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$mode = $argv[1] ?? 'status';
$repoRoot = dirname(__DIR__, 2);
$confPath = '/Users/mac/Documents/dev/dolibarr/dolibarr-Dev/dolibarr-23.0.3/htdocs/conf/conf.php';
$config = (static function ($path) {
    include $path;
    return array(
        'host'=>$dolibarr_main_db_host,
        'port'=>(int) $dolibarr_main_db_port,
        'name'=>$dolibarr_main_db_name,
        'user'=>$dolibarr_main_db_user,
        'pass'=>$dolibarr_main_db_pass,
    );
})($confPath);

$host = strtolower(trim((string) $config['host'], " \t\n\r\0\x0B[]"));
if (!in_array($host, array('localhost', '127.0.0.1', '::1'), true)) throw new RuntimeException('REMOTE_DATABASE_FORBIDDEN');
if (!preg_match('/^[a-zA-Z0-9_]+$/', $config['name'])) throw new RuntimeException('UNSAFE_DATABASE_NAME');

$db = new mysqli($config['host'], $config['user'], $config['pass'], $config['name'], $config['port']);
$table = 'llx_cpcc_guided_receipt';
$schema = $db->real_escape_string($config['name']);
$exists = $db->query("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA='".$schema."' AND TABLE_NAME='".$table."'")->num_rows === 1;
if ($mode === 'status') {
    echo "M077_GUIDED_SCHEMA=STATUS DATABASE=LOCAL TABLE=".($exists ? 'PRESENT' : 'ABSENT')."\n";
    exit($exists ? 0 : 2);
}
if ($mode !== 'apply' || getenv('M077_GUIDED_SCHEMA_APPLY') !== '1') throw new RuntimeException('EXPLICIT_LOCAL_APPLY_GATE_REQUIRED');
if ($exists) throw new RuntimeException('GUIDED_TABLE_ALREADY_PRESENT');

$lock = 'm077-guided-schema-'.$config['name'];
$escapedLock = $db->real_escape_string($lock);
$got = $db->query("SELECT GET_LOCK('".$escapedLock."',10) acquired")->fetch_assoc();
if ((int) $got['acquired'] !== 1) throw new RuntimeException('MIGRATION_LOCK_UNAVAILABLE');

try {
    $backupRoot = sys_get_temp_dir().'/m077-guided-schema-'.gmdate('Ymd\\THis\\Z').'-'.bin2hex(random_bytes(4));
    if (!mkdir($backupRoot, 0700, true)) throw new RuntimeException('BACKUP_DIRECTORY_FAILED');
    $dump = $backupRoot.'/dolibarr-before-guided.sql';
    $stderr = $backupRoot.'/mysqldump.stderr';
    $tool = '/Applications/MAMP/Library/bin/mysqldump';
    if (!is_file($tool)) throw new RuntimeException('MYSQLDUMP_UNAVAILABLE');
    $process = proc_open(
        array($tool, '--single-transaction', '--quick', '--skip-lock-tables', '--hex-blob', '--routines', '--triggers', '--events', '--default-character-set=utf8mb4', '-h', $config['host'], '-P', (string) $config['port'], '-u', $config['user'], $config['name']),
        array(0=>array('pipe', 'r'), 1=>array('file', $dump, 'w'), 2=>array('file', $stderr, 'w')),
        $pipes,
        $repoRoot,
        array_merge($_ENV, array('MYSQL_PWD'=>$config['pass']))
    );
    if (!is_resource($process)) throw new RuntimeException('DATABASE_BACKUP_START_FAILED');
    fclose($pipes[0]);
    $exit = proc_close($process);
    if ($exit !== 0 || !is_file($dump) || filesize($dump) < 1024) throw new RuntimeException('DATABASE_BACKUP_FAILED');
    chmod($dump, 0600);

    $sql = file_get_contents($repoRoot.'/custom/chinaplanetcustomercenter/sql/llx_cpcc_guided_receipt.sql');
    if ($sql === false || trim($sql) === '') throw new RuntimeException('MIGRATION_FILE_INVALID');
    $db->query($sql);
    $db->query($sql);
    $count = (int) $db->query("SELECT COUNT(*) total FROM ".$table)->fetch_assoc()['total'];
    if ($count !== 0) throw new RuntimeException('NEW_TABLE_NOT_EMPTY');
    echo "M077_GUIDED_SCHEMA=APPLIED TABLE=".$table." ROWS=0 BACKUP=".$dump." BACKUP_SHA256=".hash_file('sha256', $dump)."\n";
} finally {
    $db->query("SELECT RELEASE_LOCK('".$escapedLock."')");
}
