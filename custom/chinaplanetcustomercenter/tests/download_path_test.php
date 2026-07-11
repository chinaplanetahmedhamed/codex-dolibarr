<?php

$testRoot = sys_get_temp_dir().'/cpcc-download-path-test-'.getmypid();
define('DOL_DATA_ROOT', $testRoot);

function dol_sanitizeFileName($value)
{
    return preg_replace('/[^A-Za-z0-9_.-]/', '_', (string) $value);
}

require_once dirname(__DIR__).'/lib/portal.lib.php';

mkdir($testRoot.'/chinaplanetlogistics/ship-2', 0777, true);
mkdir($testRoot.'/chinaplanetlogistics/ship-20', 0777, true);
file_put_contents($testRoot.'/chinaplanetlogistics/ship-2/invoice.pdf', 'allowed');
file_put_contents($testRoot.'/chinaplanetlogistics/ship-20/private.pdf', 'denied');

$cases = array(
    'data-root-relative path' => array('chinaplanetlogistics/ship-2/invoice.pdf', true),
    'module-relative path' => array('ship-2/invoice.pdf', true),
    'other shipment' => array('ship-20/private.pdf', false),
    'traversal to other shipment' => array('ship-2/../ship-20/private.pdf', false),
    'missing file' => array('ship-2/missing.pdf', false),
);

$failed = 0;
foreach ($cases as $label => $case) {
    $resolved = cpcc_portal_resolve_shipment_doc_path(2, '', $case[0]);
    $passed = $case[1] ? $resolved !== '' : $resolved === '';
    print ($passed ? 'PASS' : 'FAIL').' '.$label."\n";
    if (!$passed) $failed++;
}

unlink($testRoot.'/chinaplanetlogistics/ship-2/invoice.pdf');
unlink($testRoot.'/chinaplanetlogistics/ship-20/private.pdf');
rmdir($testRoot.'/chinaplanetlogistics/ship-2');
rmdir($testRoot.'/chinaplanetlogistics/ship-20');
rmdir($testRoot.'/chinaplanetlogistics');
rmdir($testRoot);

exit($failed > 0 ? 1 : 0);
