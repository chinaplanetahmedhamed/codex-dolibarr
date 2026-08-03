<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit(1);
}

$root = dirname(__DIR__, 2);
$source = file_get_contents($root.'/custom/chinaplanetshippingprices/admin/import.php');

function shippingUploadAssert($condition, $message)
{
    if (!$condition) throw new RuntimeException('FAIL '.$message);
    print 'PASS '.$message.PHP_EOL;
}

shippingUploadAssert(strpos($source, "if (in_array(\$m041PostedAction") !== false && strpos($source, "define('CSRFCHECK_WITH_TOKEN', 1)") !== false, 'recognized POST actions enable explicit Dolibarr token enforcement');
shippingUploadAssert(strpos($source, "\$isPost && \$action === 'upload'") !== false, 'workbook upload is POST-only');
shippingUploadAssert(strpos($source, 'action="?uploadform=1"') !== false, 'oversized POST keeps Dolibarr uploadform signal in the URL');
shippingUploadAssert(strpos($source, 'name="token" value="\'.newToken().\'"') !== false, 'normal upload form carries a fresh CSRF token');
shippingUploadAssert(strpos($source, "define('NOCSRFCHECK'") === false && strpos($source, 'dolibarr_nocsrfcheck') === false && strpos($source, 'MAIN_SECURITY_CSRF_WITH_TOKEN') === false, 'route uses standard CSRF protection without weakening it');

print 'Shipping prices upload CSRF regression tests passed'.PHP_EOL;
