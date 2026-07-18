<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit(1);
}

$root = dirname(__DIR__, 2);
$path = $root.'/custom/chinaplanetshippingprices/admin/.htaccess';
$source = file_get_contents($path);

function shippingLimitAssert($condition, $message)
{
    if (!$condition) throw new RuntimeException('FAIL '.$message);
    print 'PASS '.$message.PHP_EOL;
}

shippingLimitAssert(substr_count($source, 'php_value upload_max_filesize 200M') === 1, 'upload limit is exactly 200M');
shippingLimitAssert(substr_count($source, 'php_value post_max_size 210M') === 1, 'POST limit leaves upload overhead');
shippingLimitAssert(substr_count($source, 'php_value memory_limit 1024M') === 1, 'parser memory is bounded at 1024M');
shippingLimitAssert(substr_count($source, 'php_value max_execution_time 900') === 1, 'execution time is bounded at 900 seconds');
shippingLimitAssert(substr_count($source, 'php_value max_input_time 900') === 1, 'input time is bounded at 900 seconds');
shippingLimitAssert(strpos($source, 'php_admin_value') === false, 'configuration contains no server-wide admin directive');

print 'Shipping prices upload limit tests passed'.PHP_EOL;
