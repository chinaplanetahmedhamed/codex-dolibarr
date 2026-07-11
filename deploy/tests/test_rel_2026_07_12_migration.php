<?php

if (PHP_SAPI !== 'cli') exit(1);

$confFile = getenv('DOLIBARR_CONF_FILE');
if (!$confFile) {
    $confFile = dirname(__DIR__, 5).'/dolibarr-23.0.3/htdocs/conf/conf.php';
}
if (!is_readable($confFile)) {
    fwrite(STDERR, "Set DOLIBARR_CONF_FILE to a local DEVELOPMENT conf.php\n");
    exit(2);
}
require $confFile;
require dirname(__DIR__).'/migrations/rel_2026_07_12_custom_modules.lib.php';

mysqli_report(MYSQLI_REPORT_OFF);
$db = new mysqli($dolibarr_main_db_host, $dolibarr_main_db_user, $dolibarr_main_db_pass, $dolibarr_main_db_name, (int) $dolibarr_main_db_port);
if ($db->connect_errno) {
    fwrite(STDERR, "Local DEVELOPMENT database unavailable\n");
    exit(2);
}

$schema = $dolibarr_main_db_name;
$prefix = 'codextest_'.bin2hex(random_bytes(6)).'_';
$logistics = $prefix.'chinaplanetlogistics';
$card = $prefix.'cpkanban_card';
$asset = $prefix.'cpkanban_asset';
try {
    $legacy = 'CREATE TABLE '.rel20260712Identifier($logistics).' ('
        .'`rowid` integer AUTO_INCREMENT PRIMARY KEY, `ref` varchar(30) NOT NULL,'
        .'`entity` integer DEFAULT 1 NOT NULL, `fk_soc` integer NOT NULL,'
        .'`fk_commande` integer DEFAULT 0 NOT NULL, `fk_user_creat` integer NOT NULL,'
        .'`date_ata` date DEFAULT NULL, `notes` text DEFAULT NULL'
        .') ENGINE=InnoDB';
    if (!$db->query($legacy)) throw new RuntimeException('Cannot create production-shaped legacy table: '.$db->error);
    if (!$db->query('CREATE TABLE '.rel20260712Identifier($card).' (`rowid` integer AUTO_INCREMENT PRIMARY KEY) ENGINE=InnoDB')) {
        throw new RuntimeException('Cannot create production-shaped Kanban card table: '.$db->error);
    }

    $first = rel20260712Apply($db, $schema, $prefix);
    $second = rel20260712Apply($db, $schema, $prefix);
    if (count($first) !== 10) throw new RuntimeException('Expected 10 first-run actions, got '.count($first));
    if (count($second) !== 0) throw new RuntimeException('Second run was not a no-op');

    foreach (array('fk_project', 'freetime_days', 'customer_note') as $column) {
        if (!rel20260712ColumnExists($db, $schema, $logistics, $column)) {
            throw new RuntimeException('Missing logistics column '.$column);
        }
    }
    if (!rel20260712IndexExists($db, $schema, $logistics, 'idx_chinaplanetlogistics_fk_project')) {
        throw new RuntimeException('Missing logistics project index');
    }
    if (!rel20260712TableExists($db, $schema, $asset)) {
        throw new RuntimeException('Missing asset table');
    }
    print 'PASS first_actions='.count($first).' second_actions='.count($second)."\n";
} catch (Exception $e) {
    fwrite(STDERR, 'FAIL '.$e->getMessage()."\n");
    exit(1);
} finally {
    $db->query('DROP TABLE IF EXISTS '.rel20260712Identifier($asset));
    $db->query('DROP TABLE IF EXISTS '.rel20260712Identifier($card));
    $db->query('DROP TABLE IF EXISTS '.rel20260712Identifier($logistics));
    $db->close();
}
