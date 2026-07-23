<?php

define('NOLOGIN', 1);
define('NOREQUIREMENU', 1);
define('NOREQUIREHTML', 1);
define('NOCSRFCHECK', 1);
$_SERVER['DOCUMENT_ROOT'] = '/Users/mac/Documents/dev/dolibarr/dolibarr-Dev/dolibarr-23.0.3/htdocs';
$_SERVER['SERVER_NAME'] = '127.0.0.1';
$_SERVER['SERVER_PORT'] = '8088';
require $_SERVER['DOCUMENT_ROOT'].'/master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/custom/chinaplanetshippingprices/class/ShippingPriceImporter.class.php';

function m034RuntimeAssert($condition, $message)
{
    if (!$condition) throw new RuntimeException('FAIL '.$message);
    print 'PASS '.$message.PHP_EOL;
}

$res = $db->query("SELECT b.rowid FROM ".MAIN_DB_PREFIX."chinaplanetsp_batch b INNER JOIN ".MAIN_DB_PREFIX."chinaplanetsp_rate r ON r.fk_batch=b.rowid AND r.entity=b.entity WHERE b.entity=".(int) $conf->entity." AND b.status=2 GROUP BY b.rowid ORDER BY SUM(r.status=1 AND r.is_current=1) DESC,b.rowid DESC LIMIT 1");
$batch = $res ? $db->fetch_object($res) : null;
m034RuntimeAssert($batch && (int) $batch->rowid > 0, 'local runtime contains a published shipping-price batch');

$runtimeUser = new stdClass();
$runtimeUser->id = 1;
$importer = new ShippingPriceImporter($db, $runtimeUser, (int) $conf->entity);
$state = $importer->getBatchPublicationState((int) $batch->rowid);

m034RuntimeAssert(!empty($state['is_published']), 'selected runtime batch is reported as published');
m034RuntimeAssert((int) $state['total_rates'] > 0, 'publication state reports stored prices');
m034RuntimeAssert((int) $state['published_rates'] <= (int) $state['total_rates'], 'published-price count is coherent');
m034RuntimeAssert((int) $state['current_rates'] <= (int) $state['published_rates'], 'current-price count is coherent');
m034RuntimeAssert((int) $state['searchable_rates'] <= (int) $state['current_rates'], 'customer-search count is coherent');
if ((int) $state['current_rates'] > 0) {
    m034RuntimeAssert(!empty($state['is_current']), 'batch with active rates is labeled current');
}

print 'M034 runtime publication state: batch='.(int) $state['batch_id'].' total='.(int) $state['total_rates'].' published='.(int) $state['published_rates'].' current='.(int) $state['current_rates'].' searchable='.(int) $state['searchable_rates'].PHP_EOL;
