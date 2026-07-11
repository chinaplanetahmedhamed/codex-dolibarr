<?php
require_once dirname(__DIR__).'/lib/routeguard.lib.php';

$external = (object) array('id' => 10, 'socid' => 2, 'admin' => 0);
$internal = (object) array('id' => 11, 'socid' => 0, 'admin' => 0);
$admin = (object) array('id' => 1, 'socid' => 0, 'admin' => 1);
$cases = array(
    array($external, '/index.php', 'home'),
    array($external, '/core/tools.php', 'home'),
    array($external, '/user/card.php', 'profile'),
    array($external, '/user/logout.php', null),
    array($external, '/user/passwordforgotten.php', null),
    array($external, '/custom/chinaplanetcustomercenter/profile.php', null),
    array($external, '/custom/chinaplanetlogistics/card.php', null),
    array($internal, '/index.php', null),
    array($internal, '/core/tools.php', null),
    array($internal, '/user/card.php', null),
    array($admin, '/index.php', null),
    array($admin, '/admin/modules.php', null),
);

foreach ($cases as $case) {
    $actual = cpcc_external_route_decision($case[0], $case[1]);
    if ($actual !== $case[2]) {
        fwrite(STDERR, $case[1].': expected '.var_export($case[2], true).', got '.var_export($actual, true).PHP_EOL);
        exit(1);
    }
}
print "route guard matrix: OK\n";
