<?php
/** Synthetic, database-free acceptance checks for M004. */

function m004Assert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, 'FAIL '.$message.PHP_EOL);
        exit(1);
    }
    print 'PASS '.$message.PHP_EOL;
}

$repo = dirname(__DIR__, 2);
require_once $repo.'/custom/chinaplanetlogistics/lib/chinaplanetlogistics.lib.php';

$expectedMap = array(
    0 => 'Inquiry',
    1 => 'Booking Confirmed',
    8 => 'Loaded & Going to Export Customs',
    9 => 'Export Customs Cleared',
    2 => 'On Vessel',
    3 => 'Arrived Port',
    4 => 'Under Customs',
    5 => 'Customs Cleared',
    6 => 'Delivered',
    7 => 'Closed',
);

$expectedSets = array(
    'active' => array(0, 1, 8, 9, 2, 3, 4, 5),
    'pre_sailing' => array(0, 1, 8, 9),
    'in_transit' => array(2),
    'arrived' => array(3, 4, 5),
    'terminal' => array(6, 7),
);

$statusMap = chinaplanetlogistics_status_map();
m004Assert($statusMap === $expectedMap, 'canonical status map preserves the required business display order');
m004Assert(count(array_unique(array_keys($statusMap))) === count($statusMap), 'stored status values are unique');
m004Assert($statusMap[8] === 'Loaded & Going to Export Customs', 'status 8 identifies loaded export-customs handling');
m004Assert($statusMap[9] === 'Export Customs Cleared', 'status 9 identifies completed export clearance');
m004Assert(chinaplanetlogistics_status_label(4) === 'Under Customs' && chinaplanetlogistics_status_label(5) === 'Customs Cleared', 'destination customs values 4 and 5 remain unchanged');
m004Assert(chinaplanetlogistics_status_label(999, 'Unknown') === 'Unknown', 'unknown status uses the requested safe fallback');

$sets = chinaplanetlogistics_status_sets();
m004Assert($sets === $expectedSets, 'canonical lifecycle sets match the approved M004 mapping');
foreach ($expectedSets as $name => $values) {
    m004Assert(chinaplanetlogistics_status_values($name) === $values, $name.' lifecycle helper preserves order and values');
    m004Assert(chinaplanetlogistics_status_sql_list($name) === implode(',', $values), $name.' lifecycle helper produces an explicit SQL set');
}
m004Assert(chinaplanetlogistics_status_in(8, 'active') && chinaplanetlogistics_status_in(8, 'pre_sailing'), 'status 8 is active and pre-sailing');
m004Assert(chinaplanetlogistics_status_in(9, 'active') && chinaplanetlogistics_status_in(9, 'pre_sailing'), 'status 9 is active and pre-sailing');
m004Assert(chinaplanetlogistics_status_in(2, 'in_transit') && !chinaplanetlogistics_status_in(1, 'in_transit'), 'only On Vessel is in transit/on water');
m004Assert(chinaplanetlogistics_status_in(6, 'terminal') && chinaplanetlogistics_status_in(7, 'terminal'), 'Delivered and Closed are terminal');

$files = array(
    'card' => 'custom/chinaplanetlogistics/card.php',
    'list' => 'custom/chinaplanetlogistics/list.php',
    'index' => 'custom/chinaplanetlogistics/index.php',
    'box' => 'custom/chinaplanetlogistics/core/boxes/box_chinaplanetlogistics.php',
    'admin dashboard' => 'custom/chinaplanetcustomercenter/admin_dashboard.php',
    'customer board' => 'custom/chinaplanetcustomercenter/board.php',
    'operations library' => 'custom/chinaplanetcustomercenter/lib/operations.lib.php',
    'project' => 'custom/chinaplanetcustomercenter/project.php',
    'documents' => 'custom/chinaplanetcustomercenter/documents.php',
    'admin operations' => 'custom/chinaplanetcustomercenter/admin/operations.php',
);

$sources = array();
foreach ($files as $name => $relative) {
    $source = file_get_contents($repo.'/'.$relative);
    m004Assert($source !== false, $name.' renderer is readable');
    $sources[$name] = $source;
}

m004Assert(strpos($sources['card'], '$statuses = chinaplanetlogistics_status_map();') !== false, 'Shipment Card select uses the canonical ordered map');
m004Assert(strpos($sources['list'], 'chinaplanetlogistics_status_label') !== false, 'Shipment List renders canonical labels');
m004Assert(strpos($sources['index'], 'chinaplanetlogistics_status_label') !== false, 'Logistics dashboard renders canonical labels');
m004Assert(strpos($sources['admin dashboard'], '$statusLabels = chinaplanetlogistics_status_map();') !== false, 'admin dashboard renders canonical labels');
m004Assert(strpos($sources['project'], 'chinaplanetlogistics_status_label') !== false, 'customer project renders labels instead of raw status numbers');
m004Assert(strpos($sources['documents'], 'chinaplanetlogistics_status_label') !== false, 'customer documents renders labels instead of raw status numbers');
m004Assert(strpos($sources['admin operations'], 'chinaplanetlogistics_status_label') !== false, 'admin operations renders canonical labels');

foreach (array('list', 'index', 'box') as $name) {
    m004Assert(!preg_match('/fk_statut\s*(?:<=|>=|<|>)\s*\d+/', $sources[$name]), $name.' contains no numeric-range shipment lifecycle comparison');
}

foreach (array('admin dashboard', 'customer board', 'operations library') as $name) {
    foreach (preg_split('/\R/', $sources[$name]) as $line) {
        if (strpos($line, 'chinaplanetlogistics') === false) continue;
        m004Assert(!preg_match('/(?:[a-z]\.)?fk_statut\s*(?:<=|>=|<|>)\s*\d+/', $line), $name.' shipment query uses explicit lifecycle sets');
    }
}

print 'M004 synthetic shipment status sequence tests passed'.PHP_EOL;
