<?php
/** Stream only the configured canonical backup DB to the private parent pipe. */
if (PHP_SAPI !== 'cli') exit(1);
ini_set('display_errors','0'); error_reporting(0);
try {
    require '/var/www/html/conf/conf.php';
    $expected = getenv('CPO_BOARDS_EXPECTED_DB_IP');
    if ($dolibarr_main_db_host !== 'mariadb' || $dolibarr_main_db_name !== 'dolibarr_db'
        || $dolibarr_main_db_prefix !== 'llx_' || (int)($dolibarr_main_db_port ?: 3306) !== 3306
        || !filter_var($expected,FILTER_VALIDATE_IP,FILTER_FLAG_IPV4)
        || gethostbyname($dolibarr_main_db_host) !== $expected) throw new RuntimeException();
    $binary = is_executable('/usr/bin/mariadb-dump') ? '/usr/bin/mariadb-dump' : '/usr/bin/mysqldump';
    if (!is_executable($binary)) throw new RuntimeException();
    mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);
    $connection=new mysqli($dolibarr_main_db_host,$dolibarr_main_db_user,$dolibarr_main_db_pass,'dolibarr_db',3306);
    $counts=$connection->query("SELECT COUNT(*) n,SUM(ENGINE<>'InnoDB') other_engine FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE'")->fetch_assoc();
    if (!(int)$counts['n'] || (int)$counts['other_engine']) throw new RuntimeException();
    fwrite(STDERR,'BOARDS_PRIVATE_BACKUP_TABLES='.(int)$counts['n']."\n");
    $connection->close();
    // Credentials stay inside this server-side process tree; never print/store them.
    $args = [$binary,'--host=mariadb','--port=3306','--user='.$dolibarr_main_db_user,'--single-transaction','--skip-lock-tables','--quick','--hex-blob','--no-tablespaces','--routines','--events','--triggers','--default-character-set=utf8mb4','dolibarr_db'];
    $pipes=[];
    $proc=proc_open($args,[0=>['pipe','r'],1=>STDOUT,2=>['pipe','w']],$pipes,'/', ['PATH'=>'/usr/bin:/bin','MYSQL_PWD'=>$dolibarr_main_db_pass]);
    if (!is_resource($proc)) throw new RuntimeException();
    fclose($pipes[0]);
    // Do not emit client errors, which may contain connection/account information.
    while (!feof($pipes[2])) fread($pipes[2],8192);
    fclose($pipes[2]);
    if (proc_close($proc)!==0) throw new RuntimeException();
} catch (Throwable $error) {
    fwrite(STDERR,"BOARDS_PRIVATE_BACKUP_FAILED\n"); exit(2);
}
