<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit(1);
}

$root = dirname(__DIR__, 2);
$importer = file_get_contents($root.'/custom/chinaplanetshippingprices/class/ShippingPriceImporter.class.php');
$service = file_get_contents($root.'/custom/chinaplanetshippingprices/class/ShippingPriceService.class.php');
$migration = file_get_contents($root.'/deploy/migrations/m044_palestine_shipping_country.php');

function m044Assert($condition, $message)
{
    if (!$condition) throw new RuntimeException('FAIL '.$message);
    print 'PASS '.$message.PHP_EOL;
}

m044Assert(strpos($importer, "'ASHDOD'=>'PS','HAIFA'=>'PS'") !== false, 'future Ashdod and Haifa imports map to PS');
m044Assert(strpos($importer, "'ASHDOD'=>'IL'") === false && strpos($importer, "'HAIFA'=>'IL'") === false, 'legacy IL importer mapping is absent');
m044Assert(strpos($service, "if (\$code === 'PS')") !== false && strpos($service, 'ChinaPlanetCountryPS') !== false, 'Shipping Prices applies the scoped Palestine label');
foreach (array('chinaplanetsp_rate', 'chinaplanetsp_port', 'chinaplanetsp_rule', 'chinaplanetsp_block', 'chinaplanetsp_sop_revision') as $table) {
    m044Assert(strpos($migration, "'table' => '".$table."'") !== false, 'migration includes '.$table);
}
m044Assert(strpos($migration, "in_array(\$command, array('check', 'snapshot', 'apply', 'rollback'), true)") !== false, 'migration command surface is exact');
m044Assert(strpos($migration, "SET ".'".$scope[\'column\']."'."='PS'") !== false, 'migration updates only the declared country column to PS');
m044Assert(strpos($migration, "AND ".'".$scope[\'column\']."'."='IL'") !== false, 'migration predicate is limited to IL');
m044Assert(strpos($migration, '$db->begin()') !== false && strpos($migration, '$db->rollback()') !== false && strpos($migration, '$db->commit()') !== false, 'migration is transactional');
m044Assert(strpos($migration, 'WHERE entity=') !== false, 'migration is entity scoped');
m044Assert(strpos($migration, 'SNAPSHOT_DRIFT_') !== false && strpos($migration, 'ROLLBACK_DRIFT_') !== false, 'migration rollback is pinned to the exact changed rows');

print 'M044 Palestine shipping-country source contract passed'.PHP_EOL;
