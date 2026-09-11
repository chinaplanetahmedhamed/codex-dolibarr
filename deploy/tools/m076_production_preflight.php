<?php
define('NOLOGIN', 1);
define('NOREQUIREMENU', 1);
define('NOREQUIREHTML', 1);
define('NOCSRFCHECK', 1);

$documentRoot = getenv('M076_DOL_DOCUMENT_ROOT') ?: '/var/www/html';
if (!is_file($documentRoot.'/master.inc.php')) {
    fwrite(STDERR, "M076_PRODUCTION_PREREQUISITES=FAIL REASON=DOLIBARR_ROOT_INVALID\n");
    exit(1);
}
$_SERVER['DOCUMENT_ROOT'] = $documentRoot;
$_SERVER['SERVER_NAME'] = 'erp.visiontrading.biz';
$_SERVER['SERVER_PORT'] = '443';
require $documentRoot.'/master.inc.php';
require_once $documentRoot.'/custom/chinaplanetoperations/class/InternalTaskMigration.class.php';

global $db, $conf;
$entity = (int) $conf->entity;
$migration = new InternalTaskMigration($db);
if (!$migration->isReady(true)) {
    fwrite(STDERR, 'M076_PRODUCTION_PREREQUISITES=FAIL REASON='.$migration->readinessReason()."\n");
    exit(1);
}

$sql = 'SELECT COUNT(DISTINCT CONCAT(gu.fk_usergroup,\':\',gu.fk_user)) members'
    .' FROM '.MAIN_DB_PREFIX.'usergroup_user gu'
    .' INNER JOIN '.MAIN_DB_PREFIX.'user u ON u.rowid=gu.fk_user AND u.entity=gu.entity'
    .' INNER JOIN '.MAIN_DB_PREFIX.'usergroup g ON g.rowid=gu.fk_usergroup AND g.entity=gu.entity'
    .' WHERE gu.entity='.$entity.' AND u.statut=1 AND COALESCE(u.fk_soc,0)=0';
$res = $db->query($sql);
$row = $res ? $db->fetch_object($res) : null;
$members = $row ? (int) $row->members : 0;
if ($members < 1) {
    fwrite(STDERR, "M076_PRODUCTION_PREREQUISITES=FAIL REASON=ACTIVE_INTERNAL_TEAM_MEMBERSHIP_REQUIRED\n");
    exit(1);
}

echo 'M076_PRODUCTION_PREREQUISITES=PASS ENTITY='.$entity.' ACTIVE_INTERNAL_TEAM_MEMBERSHIPS='.$members." DATABASE_MUTATED=NO\n";
