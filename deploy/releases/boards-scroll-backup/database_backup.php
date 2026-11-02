<?php
/** Read-only backup admission metadata. Never launch processes or emit credentials. */
if (PHP_SAPI !== 'cli') exit(1);
ini_set('display_errors','0'); error_reporting(0);
try {
    require '/var/www/html/conf/conf.php';
    $expected=getenv('CPO_BOARDS_EXPECTED_DB_IP');
    if ($dolibarr_main_db_host!=='mariadb'||$dolibarr_main_db_name!=='dolibarr_db'||$dolibarr_main_db_prefix!=='llx_'
        ||(int)($dolibarr_main_db_port?:3306)!==3306||!filter_var($expected,FILTER_VALIDATE_IP,FILTER_FLAG_IPV4)
        ||gethostbyname($dolibarr_main_db_host)!==$expected) throw new RuntimeException();
    mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);
    $c=new mysqli($dolibarr_main_db_host,$dolibarr_main_db_user,$dolibarr_main_db_pass,'dolibarr_db',3306);
    $r=$c->query("SELECT COUNT(*) n,SUM(ENGINE<>'InnoDB') other_engine FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE'")->fetch_assoc();
    if (!(int)$r['n']||(int)$r['other_engine']) throw new RuntimeException();
    echo json_encode(['tables'=>(int)$r['n'],'user_sha256'=>hash('sha256',$dolibarr_main_db_user)],JSON_THROW_ON_ERROR);$c->close();
} catch (Throwable $error) {fwrite(STDERR,"BOARDS_BACKUP_ADMISSION_FAILED\n");exit(2);}
