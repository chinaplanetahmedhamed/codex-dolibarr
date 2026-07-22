<?php
$root = dirname(__DIR__, 2);

function m033_source($relative)
{
    global $root;
    $source = file_get_contents($root.'/'.$relative);
    if ($source === false) throw new RuntimeException('Unable to read '.$relative);
    return $source;
}

function m033_assert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, 'FAIL '.$message.PHP_EOL);
        exit(1);
    }
    print 'PASS '.$message.PHP_EOL;
}

$shell = m033_source('custom/chinaplanetcustomercenter/lib/admin_shell.lib.php');
m033_assert(substr_count($shell, "'shipping'=>array(") === 1, 'shared navigation exposes one Shipping Center item');
m033_assert(strpos($shell, 'Shipping Center / مركز الشحن') !== false, 'shipping navigation uses the approved bilingual label');
m033_assert(strpos($shell, 'Customer Center / مركز العملاء') !== false, 'customer navigation uses one approved bilingual label');
m033_assert(strpos($shell, "'tracking'=>'shipping'") !== false && strpos($shell, "'shipments'=>'shipping'") !== false, 'legacy page keys keep Shipping Center active');

$shipping = m033_source('custom/chinaplanetlogistics/index.php');
foreach (array('Tracking Dashboard / لوحة التتبع', 'Shipments / الشحنات', 'Shipping Prices / أسعار الشحن') as $label) {
    m033_assert(strpos($shipping, $label) !== false, 'shipping center contains '.$label);
}
m033_assert(strpos($shipping, 'repeat(auto-fit,minmax(min(220px,100%),1fr))') !== false, 'shipping actions use a fluid responsive grid');

$board = m033_source('custom/chinaplanetcustomercenter/board.php');
m033_assert(strpos($board, 'if (!$is_external && $socid <= 0)') !== false, 'all-customer summary is internal and only shown without a selected customer');
m033_assert(strpos($board, 'entity=".$entity') !== false, 'customer overview queries are entity scoped');
m033_assert(strpos($board, 'Finance by currency') !== false && strpos($board, 'Original currency only') !== false, 'customer center keeps finance separated by original currency');
m033_assert(strpos($board, 'if ($is_external) cpcc_portal_navigation') !== false, 'external customer portal navigation remains unchanged');
m033_assert(strpos($board, "function cpcc_board_customer_document_count") !== false, 'customer document count is self-contained in the deployed board');
m033_assert(strpos($board, 'cpcc_portal_customer_document_count($db') === false, 'deployed board does not depend on an undeployed portal-library helper');

$css = m033_source('custom/chinaplanetcustomercenter/css/admin_dashboard.css');
m033_assert(strpos($css, '.cpcc-admin-overview-grid') !== false, 'customer center overview has shared admin styling');
m033_assert(strpos($css, '@media(max-width:390px)') !== false, 'customer center has a 390px layout safeguard');

print 'M033 shipping and customer center tests passed'.PHP_EOL;
