<?php

/**
 * Local-only M077 schema installer with a full dump and exclusive migration lock.
 * Never accepts a remote database host.
 */
if (PHP_SAPI !== 'cli') { fwrite(STDERR, "M077_LOCAL_SCHEMA=FAIL REASON=CLI_ONLY\n"); exit(1); }
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$mode = isset($argv[1]) ? (string) $argv[1] : 'status';
$repoRoot = dirname(__DIR__, 2);
$confPath = '/Users/mac/Documents/dev/dolibarr/dolibarr-Dev/dolibarr-23.0.3/htdocs/conf/conf.php';
$config = (static function ($path) {
    include $path;
    return array('host'=>$dolibarr_main_db_host,'port'=>(int)$dolibarr_main_db_port,'name'=>$dolibarr_main_db_name,'user'=>$dolibarr_main_db_user,'pass'=>$dolibarr_main_db_pass);
})($confPath);
$host = strtolower(trim((string)$config['host'], " \t\n\r\0\x0B[]"));
if (!in_array($host, array('localhost','127.0.0.1','::1'), true)) throw new RuntimeException('REMOTE_DATABASE_FORBIDDEN');
if (!preg_match('/^[a-zA-Z0-9_]+$/', (string)$config['name'])) throw new RuntimeException('UNSAFE_DATABASE_NAME');
$db = new mysqli($config['host'],$config['user'],$config['pass'],$config['name'],$config['port']);
$lockName = 'm077-local-schema-'.$config['name'];
$lockResult = $db->query("SELECT GET_LOCK('".$db->real_escape_string($lockName)."',10) acquired")->fetch_assoc();
if ((int)($lockResult['acquired'] ?? 0) !== 1) throw new RuntimeException('MIGRATION_LOCK_UNAVAILABLE');
$tables = array('llx_cpcc_customer_receipt','llx_cpcc_fund_allocation','llx_cpcc_fund_event');
$existing = array();
foreach ($tables as $table) {
    $result = $db->query("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA='".$db->real_escape_string($config['name'])."' AND TABLE_NAME='".$db->real_escape_string($table)."'");
    if ($result->num_rows > 0) $existing[] = $table;
}
if ($mode === 'status') {
    echo "M077_LOCAL_SCHEMA=STATUS DATABASE=LOCAL TABLES=".($existing ? implode(',', $existing) : 'ABSENT')."\n";
    $db->query("SELECT RELEASE_LOCK('".$db->real_escape_string($lockName)."')");
    exit(0);
}
if ($mode !== 'apply' || getenv('M077_LOCAL_SCHEMA_APPLY') !== '1') throw new RuntimeException('EXPLICIT_LOCAL_APPLY_GATE_REQUIRED');
if ($existing) throw new RuntimeException('M077_TABLES_ALREADY_PRESENT');

$backupRoot = sys_get_temp_dir().'/m077-local-schema-'.gmdate('Ymd\THis\Z').'-'.bin2hex(random_bytes(4));
if (!mkdir($backupRoot,0700,true)) throw new RuntimeException('BACKUP_DIRECTORY_FAILED');
$dumpPath = $backupRoot.'/dolibarr-before-m077.sql';
$errorPath = $backupRoot.'/mysqldump.stderr';
$dumpTool = '/Applications/MAMP/Library/bin/mysqldump';
if (!is_file($dumpTool)) throw new RuntimeException('MYSQLDUMP_UNAVAILABLE');
$command = array($dumpTool,'--single-transaction','--quick','--skip-lock-tables','--hex-blob','--routines','--triggers','--events','--default-character-set=utf8mb4','-h',$config['host'],'-P',(string)$config['port'],'-u',$config['user'],$config['name']);
$pipes = array();
$process = proc_open($command,array(0=>array('pipe','r'),1=>array('file',$dumpPath,'w'),2=>array('file',$errorPath,'w')),$pipes,$repoRoot,array_merge($_ENV,array('MYSQL_PWD'=>$config['pass'])));
if (!is_resource($process)) throw new RuntimeException('DATABASE_BACKUP_START_FAILED');
fclose($pipes[0]);
$exit = proc_close($process);
if ($exit !== 0 || !is_file($dumpPath) || filesize($dumpPath) < 1024) throw new RuntimeException('DATABASE_BACKUP_FAILED');
chmod($dumpPath,0600);
$absencePath = $backupRoot.'/absent-before-m077.txt';
file_put_contents($absencePath,implode("\n",$tables)."\n");
chmod($absencePath,0600);
if (is_file($errorPath) && filesize($errorPath)===0) unlink($errorPath);

try {
    foreach (array('llx_cpcc_customer_receipt.sql','llx_cpcc_fund_allocation.sql','llx_cpcc_fund_event.sql') as $file) {
        $sql = file_get_contents($repoRoot.'/custom/chinaplanetcustomercenter/sql/'.$file);
        if ($sql === false || trim($sql)==='') throw new RuntimeException('MIGRATION_FILE_INVALID');
        $db->query($sql);
        $db->query($sql);
    }
    foreach ($tables as $table) {
        $result = $db->query("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA='".$db->real_escape_string($config['name'])."' AND TABLE_NAME='".$db->real_escape_string($table)."'");
        if ($result->num_rows !== 1) throw new RuntimeException('SCHEMA_VERIFY_FAILED');
        $count = $db->query("SELECT COUNT(*) total FROM ".$table)->fetch_assoc();
        if ((int)$count['total'] !== 0) throw new RuntimeException('NEW_TABLE_NOT_EMPTY');
    }
} catch (Throwable $e) {
    throw new RuntimeException('MIGRATION_FAILED_TABLES_PRESERVED: '.$e->getMessage(), 0, $e);
}
$manifest = array(
    'environment'=>'LOCAL','database'=>$config['name'],'source_mutated'=>'schema_only',
    'tables'=>$tables,'rows'=>array_fill_keys($tables,0),
    'database_backup'=>$dumpPath,'database_backup_sha256'=>hash_file('sha256',$dumpPath),
    'absent_list'=>$absencePath,'absent_list_sha256'=>hash_file('sha256',$absencePath),
);
$manifestPath = $backupRoot.'/manifest.json';
file_put_contents($manifestPath,json_encode($manifest,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n");
chmod($manifestPath,0600);
$db->query("SELECT RELEASE_LOCK('".$db->real_escape_string($lockName)."')");
echo "M077_LOCAL_SCHEMA=APPLIED TABLES=3 ROWS=0 BACKUP=".$dumpPath." BACKUP_SHA256=".$manifest['database_backup_sha256']." ABSENT_LIST=".$absencePath." ABSENT_SHA256=".$manifest['absent_list_sha256']."\n";
