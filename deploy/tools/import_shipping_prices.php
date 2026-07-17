<?php
define('NOLOGIN', 1);
define('NOREQUIREMENU', 1);
define('NOREQUIREHTML', 1);
define('NOCSRFCHECK', 1);

if ($argc < 2 || !is_readable($argv[1])) {
    fwrite(STDERR, "Usage: php import_shipping_prices.php /path/to/rates.xls [current|history] [--publish]\n");
    exit(2);
}
$_SERVER['DOCUMENT_ROOT'] = '/Users/mac/Documents/dev/dolibarr/dolibarr-Dev/dolibarr-23.0.3/htdocs';
$_SERVER['SERVER_NAME'] = '127.0.0.1';
$_SERVER['SERVER_PORT'] = '8088';
require $_SERVER['DOCUMENT_ROOT'].'/master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/custom/chinaplanetshippingprices/class/ShippingPriceImporter.class.php';

$adminSql = "SELECT rowid FROM ".MAIN_DB_PREFIX."user WHERE admin=1 AND statut=1 ORDER BY rowid LIMIT 1";
$adminRes = $db->query($adminSql); $adminRow = $adminRes ? $db->fetch_object($adminRes) : null;
if (!$adminRow) throw new RuntimeException('No active administrator found.');
$importUser = new User($db); $importUser->fetch((int) $adminRow->rowid);
$importer = new ShippingPriceImporter($db, $importUser);
$ids = $importer->importFile($argv[1], basename($argv[1]), array(
    'mode' => isset($argv[2]) && $argv[2] === 'history' ? 'history' : 'current',
    'source_name' => 'Shipping agent',
    'ignore_below' => $conf->global->CHINAPLANETSP_IGNORE_BELOW ?? 1000,
    'currency' => $conf->global->CHINAPLANETSP_CURRENCY ?? 'USD',
    'stored_file' => '',
));
foreach ($ids as $id) {
    print "Prepared batch ".$id."\n";
    if (in_array('--publish', $argv, true)) { $importer->publishBatch($id); print "Published batch ".$id."\n"; }
}
