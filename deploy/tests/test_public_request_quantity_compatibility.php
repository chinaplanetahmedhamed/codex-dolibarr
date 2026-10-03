<?php
/** Pure normalization regression: no database or business record mutations. */
$root = dirname(__DIR__, 2);
$core = getenv('CP_TEST_DOL_DOCUMENT_ROOT') ?: '/Users/mac/Documents/dev/dolibarr/dolibarr-Dev/dolibarr-23.0.3/htdocs';
define('DOL_DOCUMENT_ROOT', $core);
require $root.'/custom/chinaplanetoperations/class/PublicRequestQuotationService.class.php';
$service = new PublicRequestQuotationService(null, 1);
function quantityLine($service, $line) {
    return $service->catalogLines(array('request'=>array('details'=>array('lines'=>array($line)))))[0];
}
function quantityAssert($ok, $message) { if (!$ok) throw new RuntimeException($message); }
foreach (array('machine', 'piece', 'set') as $unit) {
    $line = quantityLine($service, array('productId'=>'motor-754', 'wooProductId'=>754, 'requestedUnit'=>$unit, 'requestedQuantity'=>2, 'cartons'=>null, 'piecesPerCarton'=>null, 'packingStatus'=>'pending', 'selectedOptionLabel'=>'220 V'));
    quantityAssert($line['cartons'] === 0 && $line['total_cbm'] == 0, 'Pending packing fabricated cartons or volume');
    quantityAssert($line['requested_unit'] === $unit && $line['requested_quantity'] === 2 && $line['total_units'] === 2, 'Requested selling units lost');
    quantityAssert($line['product_id'] === 754 && $line['option'] === '220 V', 'Modern mapping or option lost');
}
$line = quantityLine($service, array('requestedUnit'=>'carton','requestedQuantity'=>3,'cartons'=>3,'piecesPerCarton'=>24,'cbmPerCarton'=>0.4));
quantityAssert($line['cartons'] === 3 && $line['total_units'] === 72 && abs($line['total_cbm']-1.2)<0.00001, 'Confirmed modern carton packing changed');
$line = quantityLine($service, array('quantity'=>3,'unitsPerCarton'=>24));
quantityAssert($line['cartons'] === 3 && $line['total_units'] === 72, 'Legacy numeric carton packing changed');
$line = quantityLine($service, array('quantity'=>'2 machines (packing pending)','cartons'=>null));
quantityAssert($line['cartons'] === 0 && $line['total_units'] === 0, 'Unknown legacy packing fabricated carton');
$method = new ReflectionMethod($service, 'withProductPacking');
$line = quantityLine($service, array('requestedUnit'=>'machine','requestedQuantity'=>2,'piecesPerCarton'=>24));
$line = $method->invoke($service, $line, null);
quantityAssert($line['cartons'] === 0 && $line['total_units'] === 2, 'Known product packing replaced requested units');
echo "PUBLIC_REQUEST_QUANTITY=PASS modern_units=3 modern_cartons=PASS legacy=PASS unknown_packing=PASS\n";
