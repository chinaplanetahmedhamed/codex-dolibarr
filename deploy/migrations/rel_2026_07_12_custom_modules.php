<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit(1);
}
if ($argc !== 2 || $argv[1] !== '--apply') {
    fwrite(STDERR, "Usage: php rel_2026_07_12_custom_modules.php --apply\n");
    exit(2);
}

$confFile = getenv('DOLIBARR_CONF_FILE');
if (!is_readable($confFile)) {
    fwrite(STDERR, "Set DOLIBARR_CONF_FILE to the active Dolibarr conf.php\n");
    exit(2);
}

require $confFile;
require __DIR__.'/rel_2026_07_12_custom_modules.lib.php';

mysqli_report(MYSQLI_REPORT_OFF);
$db = new mysqli($dolibarr_main_db_host, $dolibarr_main_db_user, $dolibarr_main_db_pass, $dolibarr_main_db_name, (int) $dolibarr_main_db_port);
if ($db->connect_errno) {
    fwrite(STDERR, "Database connection failed\n");
    exit(1);
}
$db->set_charset('utf8mb4');

try {
    $actions = rel20260712Apply($db, $dolibarr_main_db_name, $dolibarr_main_db_prefix);
    foreach ($actions as $action) {
        print 'APPLIED '.$action."\n";
    }
    print 'OK actions='.count($actions)."\n";
} catch (Exception $e) {
    fwrite(STDERR, 'Migration failed: '.$e->getMessage()."\n");
    exit(1);
} finally {
    $db->close();
}
