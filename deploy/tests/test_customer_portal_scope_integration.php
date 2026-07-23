<?php
define('NOLOGIN', 1);
define('NOREQUIREMENU', 1);
define('NOREQUIREHTML', 1);
define('NOCSRFCHECK', 1);

$documentRoot = getenv('M037_DOL_DOCUMENT_ROOT');
if ($documentRoot === false || $documentRoot === '') $documentRoot = '/Users/mac/Documents/dev/dolibarr/dolibarr-Dev/dolibarr-23.0.3/htdocs';
$_SERVER['DOCUMENT_ROOT'] = rtrim($documentRoot, '/');
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

$res = $db->query(
    "SELECT rowid FROM ".MAIN_DB_PREFIX."user".
    " WHERE entity IN (0,".(int) $conf->entity.")".
    " AND fk_soc IS NOT NULL AND fk_soc>0 AND statut=1 AND admin=0".
    " ORDER BY entity DESC,rowid LIMIT 1"
);
$obj = $res ? $db->fetch_object($res) : null;
scopeAssert(!empty($obj->rowid), 'an active external portal user exists');
$portalUser = new User($db);
scopeAssert($portalUser->fetch((int) $obj->rowid) > 0, 'portal user loads');

$scope = cpcc_portal_user_scope($portalUser);
scopeAssert(!empty($scope['is_external']), 'portal user is external');
$portalSocid = (int) $scope['socid'];
scopeAssert($portalSocid > 0 && $portalSocid === (int) $portalUser->socid, 'portal scope comes from the authenticated user third party');
$tamperedSocid = $portalSocid + 1000000;
scopeAssert(cpcc_portal_customer_scope_id($db, $portalUser, $tamperedSocid) === $portalSocid, 'tampered customer id is ignored');

if (cpcc_export_schema_ready($db)) {
    $res = $db->query(
        "SELECT rowid,fk_soc FROM ".MAIN_DB_PREFIX."cpcc_export_document".
        " WHERE entity=".(int) $conf->entity." AND status=1 AND customer_visible=1".
        " ORDER BY rowid LIMIT 1"
    );
    scopeAssert($res !== false, 'customer-visible export fixture lookup executes');
    $document = $db->fetch_object($res);
    if ($document) {
        $documentId = (int) $document->rowid;
        $documentSocid = (int) $document->fk_soc;
        scopeAssert(cpcc_export_fetch($db, $documentId, $documentSocid, true) !== null, 'export owner can fetch its customer-visible document');
        scopeAssert(cpcc_export_fetch($db, $documentId, $documentSocid + 1000000, true) === null, 'foreign customer cannot fetch the export document');
    } else {
        print 'PASS no customer-visible export fixture is required for scope enforcement'.PHP_EOL;
    }
} else {
    print 'PASS optional export schema is absent'.PHP_EOL;
}

$res = $db->query(
    "SELECT rowid,fk_soc FROM ".MAIN_DB_PREFIX."facture".
    " WHERE entity=".(int) $conf->entity." AND fk_statut>0 AND type IN (0,1,5)".
    " ORDER BY rowid LIMIT 1"
);
scopeAssert($res !== false, 'customer invoice fixture lookup executes');
$invoice = $db->fetch_object($res);
if ($invoice) {
    $invoiceId = (int) $invoice->rowid;
    $invoiceSocid = (int) $invoice->fk_soc;
    $ownerIds = array_column(cpcc_financial_customer_invoices($db, $invoiceSocid, 'USD'), 'id');
    $foreignIds = array_column(cpcc_financial_customer_invoices($db, $invoiceSocid + 1000000, 'USD'), 'id');
    scopeAssert(in_array($invoiceId, $ownerIds, true), 'customer invoice is present in its owner scope');
    scopeAssert(!in_array($invoiceId, $foreignIds, true), 'customer invoice is absent from a foreign scope');
} else {
    print 'PASS no validated customer invoice fixture is required for scope enforcement'.PHP_EOL;
}

print 'Customer portal scope integration tests passed'.PHP_EOL;
