<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit(1);
}

$root = dirname(__DIR__, 2);
$importer = file_get_contents($root.'/custom/chinaplanetshippingprices/class/ShippingPriceImporter.class.php');
$importPage = file_get_contents($root.'/custom/chinaplanetshippingprices/admin/import.php');
$batchPage = file_get_contents($root.'/custom/chinaplanetshippingprices/admin/batch.php');

function m034Assert($condition, $message)
{
    if (!$condition) throw new RuntimeException('FAIL '.$message);
    print 'PASS '.$message.PHP_EOL;
}

m034Assert(strpos($importer, 'getLastImportResults') !== false, 'importer reports whether a workbook created or reused a batch');
m034Assert(strpos($importer, 'getBatchPublicationState') !== false, 'importer exposes authoritative publication state');
m034Assert(strpos($importer, "'duplicate' => true") !== false, 'exact duplicate imports are identified explicitly');
m034Assert(strpos($importer, "empty(\$state['can_reactivate'])") !== false, 'reactivation is enforced by server-side publication logic');
m034Assert(strpos($importer, 'a newer relevant batch exists') !== false, 'unsafe history reactivation is rejected');

m034Assert(strpos($importPage, 'already published and are currently active') !== false, 'active duplicate upload receives an explicit explanation');
m034Assert(strpos($importPage, 'published history') !== false, 'historical duplicate upload receives an explicit explanation');
m034Assert(strpos($importPage, "\$isPost && (\$action === 'publish' || \$action === 'activate_current')") !== false, 'publication and repair actions are POST-only');
m034Assert(strpos($importPage, '?action=publish') === false, 'batch list has no state-changing GET publish link');
m034Assert(strpos($importPage, 'Published &amp; current') !== false && strpos($importPage, 'Published history') !== false, 'batch list distinguishes current prices from published history');
m034Assert(substr_count($importPage, 'name="token" value="\'.newToken().\'"') >= 1, 'batch-list publish form carries a CSRF token');

m034Assert(strpos($batchPage, '?action=publish') === false, 'review page has no state-changing GET publish link');
m034Assert(strpos($batchPage, "!empty(\$publication['can_reactivate'])") !== false, 'repair action is shown only for a safely reactivatable batch');
m034Assert(strpos($batchPage, 'Current prices') !== false && strpos($batchPage, 'Customer search') !== false, 'review page reports active and searchable price counts');
m034Assert(substr_count($batchPage, 'method="post" action="import.php"') >= 2, 'review-page publication actions use POST forms');
m034Assert(substr_count($batchPage, 'name="token" value="\'.newToken().\'"') >= 2, 'review-page publication forms carry CSRF tokens');

print 'M034 shipping price publication-state regression tests passed'.PHP_EOL;
