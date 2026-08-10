<?php
/** Enumerate M046-C01 runtime policy and mutation call sites. */

$root = dirname(__DIR__, 2);
$module = $root.'/custom/chinaplanetshippingprices';
$matches = array('constant'=>array(), 'policy'=>array(), 'translate'=>array(), 'auto'=>array(), 'mutate'=>array());
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($module, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    if (!$file->isFile() || $file->isLink() || strtolower($file->getExtension()) !== 'php') continue;
    $relative = substr($file->getPathname(), strlen($root) + 1);
    $lines = file($file->getPathname());
    if ($lines === false) throw new RuntimeException('Unreadable '.$relative);
    foreach ($lines as $index=>$line) {
        foreach (array(
            'constant'=>'CHINAPLANETSP_AUTO_TRANSLATION_ENABLED',
            'policy'=>'automaticTranslationEnabled(',
            'translate'=>'->translate(',
            'auto'=>'autoTranslatePending(',
            'mutate'=>'->mutate(',
        ) as $type=>$needle) if (strpos($line, $needle) !== false) $matches[$type][] = $relative.':'.($index + 1);
    }
}
if ($matches['constant'] !== array()) throw new RuntimeException('Direct runtime constant call site: '.implode(',', $matches['constant']));
$expectedPolicyCounts = array(
    'custom/chinaplanetshippingprices/class/ShippingNoteTranslationService.class.php'=>2,
    'custom/chinaplanetshippingprices/class/ShippingNoteReviewService.class.php'=>2,
    'custom/chinaplanetshippingprices/class/ShippingPriceImporter.class.php'=>4,
    'custom/chinaplanetshippingprices/class/ShippingPriceService.class.php'=>1,
    'custom/chinaplanetshippingprices/admin/setup.php'=>1,
    'custom/chinaplanetshippingprices/admin/import.php'=>1,
    'custom/chinaplanetshippingprices/admin/batch.php'=>1,
);
foreach ($expectedPolicyCounts as $path=>$expectedCount) {
    $actualCount = count(array_filter($matches['policy'], function ($site) use ($path) { return strpos($site, $path.':') === 0; }));
    if ($actualCount !== $expectedCount) throw new RuntimeException('Policy site count mismatch for '.$path.': '.$actualCount.' expected '.$expectedCount);
}
if (array_sum($expectedPolicyCounts) !== count($matches['policy'])) throw new RuntimeException('Unexpected policy call site present');
foreach ($matches['translate'] as $site) {
    if (strpos($site, 'custom/chinaplanetshippingprices/class/ShippingNoteReviewService.class.php:') !== 0 && strpos($site, 'custom/chinaplanetshippingprices/class/ShippingPriceImporter.class.php:') !== 0) throw new RuntimeException('Unexpected adapter caller: '.$site);
}
foreach ($matches['auto'] as $site) {
    if (strpos($site, 'custom/chinaplanetshippingprices/class/ShippingNoteReviewService.class.php:') !== 0 && strpos($site, 'custom/chinaplanetshippingprices/admin/batch.php:') !== 0) throw new RuntimeException('Unexpected auto caller: '.$site);
}
foreach ($matches['mutate'] as $site) if (strpos($site, 'custom/chinaplanetshippingprices/admin/batch.php:') !== 0) throw new RuntimeException('Unexpected review mutation caller: '.$site);

echo 'M046_C01_CALL_SITES=PASS CONSTANT=0 POLICY='.count($matches['policy']).' TRANSLATE='.count($matches['translate']).' AUTO='.count($matches['auto']).' MUTATE='.count($matches['mutate'])."\n";
