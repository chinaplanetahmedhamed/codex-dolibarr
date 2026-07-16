<?php
define('NOLOGIN', 1);
define('NOREQUIREMENU', 1);
define('NOREQUIREHTML', 1);
define('NOCSRFCHECK', 1);

$_SERVER['DOCUMENT_ROOT'] = '/Users/mac/Documents/dev/dolibarr/dolibarr-Dev/dolibarr-23.0.3/htdocs';
$_SERVER['SERVER_NAME'] = '127.0.0.1';
$_SERVER['SERVER_PORT'] = '8088';
require $_SERVER['DOCUMENT_ROOT'].'/master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/chinaplanetcustomercenter/lib/portal.lib.php';
require_once DOL_DOCUMENT_ROOT.'/custom/chinaplanetcustomercenter/lib/export.lib.php';
require_once DOL_DOCUMENT_ROOT.'/custom/chinaplanetcustomercenter/lib/financial.lib.php';

function scopeAssert($condition, $message)
{
    if (!$condition) throw new RuntimeException('FAIL: '.$message);
    print 'PASS '.$message.PHP_EOL;
}

$res = $db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."user WHERE login='china.gate.portal' LIMIT 1");
$obj = $res ? $db->fetch_object($res) : null;
scopeAssert(!empty($obj->rowid), 'China Gate portal user exists');
$portalUser = new User($db);
scopeAssert($portalUser->fetch((int) $obj->rowid) > 0, 'portal user loads');

$scope = cpcc_portal_user_scope($portalUser);
scopeAssert(!empty($scope['is_external']), 'portal user is external');
scopeAssert((int) $scope['socid'] === 10, 'portal user is linked to China Gate only');
scopeAssert(cpcc_portal_customer_scope_id($db, $portalUser, 2) === 10, 'tampered socid=2 is ignored');

scopeAssert(cpcc_export_fetch($db, 1, 10, true) !== null, 'China Gate can fetch its commercial invoice');
scopeAssert(cpcc_export_fetch($db, 1, 2, true) === null, 'another customer cannot fetch China Gate commercial invoice');

$chinaGateInvoices = cpcc_financial_customer_invoices($db, 10, 'USD');
$otherInvoices = cpcc_financial_customer_invoices($db, 2, 'USD');
$chinaGateIds = array_column($chinaGateInvoices, 'id');
$otherIds = array_column($otherInvoices, 'id');
scopeAssert(in_array(9, $chinaGateIds, true), 'China Gate service invoice is in its portal scope');
scopeAssert(!in_array(9, $otherIds, true), 'China Gate service invoice is absent from another customer scope');

$res = $db->query("SELECT COUNT(*) value FROM ".MAIN_DB_PREFIX."projet WHERE rowid=36 AND fk_soc=10");
$project = $res ? $db->fetch_object($res) : null;
scopeAssert(!empty($project->value), 'AG-001 project ownership is tied to China Gate');

print 'Customer portal scope integration tests passed'.PHP_EOL;
