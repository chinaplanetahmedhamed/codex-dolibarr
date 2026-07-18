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

$csrf = strpos($source, "define('CSRFCHECK_WITH_TOKEN', 1);");
$bootstrap = strpos($source, "require \$_SERVER['DOCUMENT_ROOT'].'/main.inc.php';");

shippingUploadAssert($csrf !== false && $bootstrap !== false && $csrf < $bootstrap, 'CSRF enforcement is declared before Dolibarr bootstrap');
shippingUploadAssert(strpos($source, "\$isPost && \$action === 'upload'") !== false, 'workbook upload is POST-only');
shippingUploadAssert(strpos($source, 'action="?uploadform=1"') !== false, 'oversized POST keeps Dolibarr uploadform signal in the URL');
shippingUploadAssert(strpos($source, 'name="token" value="\'.newToken().\'"') !== false, 'normal upload form carries a fresh CSRF token');
shippingUploadAssert(strpos($source, "define('NOCSRFCHECK'") === false && strpos($source, 'dolibarr_nocsrfcheck') === false, 'route does not disable CSRF protection');

print 'Shipping prices upload CSRF regression tests passed'.PHP_EOL;
