<?php

/** M044-C01 source/runtime contract for the Shipping Prices import dependency. */

$root = getenv('M044_C01_DOL_DOCUMENT_ROOT');
if ($root === false || $root === '') {
    $root = dirname(__DIR__, 2);
}
$root = rtrim($root, '/');

$importerPath = $root.'/custom/chinaplanetshippingprices/class/ShippingPriceImporter.class.php';
$servicePath = $root.'/custom/chinaplanetshippingprices/class/ShippingNoteTranslationService.class.php';
$importPagePath = $root.'/custom/chinaplanetshippingprices/admin/import.php';

foreach (array($importerPath, $servicePath, $importPagePath) as $path) {
    if (!is_file($path) || is_link($path)) {
        throw new RuntimeException('M044-C01 required file is missing or unsafe: '.$path);
    }
}

$importer = file_get_contents($importerPath);
$service = file_get_contents($servicePath);
$importPage = file_get_contents($importPagePath);
if ($importer === false || $service === false || $importPage === false) {
    throw new RuntimeException('M044-C01 source is unreadable.');
}
if (strpos($importer, "require_once __DIR__.'/ShippingNoteTranslationService.class.php';") === false) {
    throw new RuntimeException('Importer dependency declaration is absent.');
}
if (strpos($service, 'class ShippingNoteTranslationService') === false) {
    throw new RuntimeException('Translation service class is absent.');
}
if (strpos($importPage, 'ShippingPriceImporter.class.php') === false) {
    throw new RuntimeException('Import page no longer loads the importer.');
}

require_once $importerPath;
if (!class_exists('ShippingPriceImporter', false) || !class_exists('ShippingNoteTranslationService', false)) {
    throw new RuntimeException('Importer dependency closure did not load.');
}

echo "M044_C01_IMPORT_DEPENDENCY=PASS importer=PASS service=PASS page_contract=PASS\n";
