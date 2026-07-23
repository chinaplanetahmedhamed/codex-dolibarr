<?php
$root = dirname(__DIR__, 2).'/custom';

function shellAssert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, 'FAIL '.$message.PHP_EOL);
        exit(1);
    }
    print 'PASS '.$message.PHP_EOL;
}

$helper = file_get_contents($root.'/chinaplanetcustomercenter/lib/admin_shell.lib.php');
foreach (array('dashboard','portal','shipping','documents','reports','sop','operations','financial') as $key) {
    shellAssert(strpos($helper, "'".$key."'=>array(") !== false, 'shared navigation contains '.$key);
}
foreach (array('tracking','shippingprices','shipments') as $legacyShippingKey) {
    shellAssert(strpos($helper, "'".$legacyShippingKey."'=>array(") === false, 'shared navigation hides legacy shipping item '.$legacyShippingKey);
}
shellAssert(strpos($helper, "'tracking'=>'shipping'") !== false, 'legacy shipping page keys resolve to the Shipping Center');
shellAssert(strpos($helper, "'export'=>array(") === false, 'legacy Export Documents navigation stays hidden');
shellAssert(strpos($helper, "'/chinaplanetlogistics/card.php'") !== false, 'shared navigation contains new shipment action');
shellAssert(strpos($helper, 'class="cpad-nav-toggle"') !== false, 'shared navigation has an accessible mobile menu button');
shellAssert(strpos($helper, 'aria-expanded="false"') !== false, 'mobile menu exposes its collapsed state');
shellAssert(strpos($helper, 'class="cpad-nav-links"') !== false, 'shared navigation groups links for the responsive menu');
shellAssert(strpos($helper, 'aria-current="page"') !== false, 'shared navigation identifies the current page');

$pages = array(
    'chinaplanetcustomercenter/board.php'=>"cpcc_admin_shell_open('portal'",
    'chinaplanetlogistics/index.php'=>"cpcc_admin_shell_open('shipping'",
    'chinaplanetlogistics/list.php'=>"cpcc_admin_shell_open('shipments'",
    'chinaplanetoperations/operations/shipping_orders.php'=>"cpcc_admin_shell_open('shipping'",
    'chinaplanetcustomercenter/documents.php'=>"cpcc_admin_shell_open('documents'",
    'chinaplanetcustomercenter/balance.php'=>"cpcc_admin_shell_open('portal'",
    'chinaplanetcustomercenter/funding.php'=>"cpcc_admin_shell_open('portal'",
    'chinaplanetcustomercenter/projects.php'=>"cpcc_admin_shell_open('portal'",
    'chinaplanetcustomercenter/project.php'=>"cpcc_admin_shell_open('portal'",
    'chinaplanetcustomercenter/orders.php'=>"cpcc_admin_shell_open('portal'",
    'chinaplanetcustomercenter/order.php'=>"cpcc_admin_shell_open('portal'",
    'chinaplanetcustomercenter/invoices.php'=>"cpcc_admin_shell_open('portal'",
    'chinaplanetcustomercenter/invoice.php'=>"cpcc_admin_shell_open('portal'",
    'chinaplanetcustomercenter/commercial_documents.php'=>"cpcc_admin_shell_open('export'",
    'chinaplanetcustomercenter/export_document.php'=>"cpcc_admin_shell_open('export'",
    'chinaplanetlogistics/reports.php'=>"cpcc_admin_shell_open('financial'",
    'chinaplanetcustomercenter/admin/operations.php'=>"cpcc_admin_shell_open('operations'",
    'chinaplanetcustomercenter/admin/financial.php'=>"cpcc_admin_shell_open('financial'",
    'chinaplanetcustomercenter/admin/export_document.php'=>"cpcc_admin_shell_open('export'",
    'chinaplanetcustomercenter/admin/setup.php'=>"cpcc_admin_shell_open('dashboard'",
    'chinaplanetlogistics/admin/setup.php'=>"cpcc_admin_shell_open('tracking'",
    'chinaplanetcustomercenter/cpkanban/card.php'=>"cpcc_admin_shell_open('operations'",
    'chinaplanetcustomercenter/cpkanban/admin/columns.php'=>"cpcc_admin_shell_open('operations'",
    'chinaplanetcustomercenter/cpkanban/admin/onboarding.php'=>"cpcc_admin_shell_open('portal'",
);
foreach ($pages as $relative=>$needle) {
    $source = file_get_contents($root.'/'.$relative);
    shellAssert(strpos($source, $needle) !== false, $relative.' uses the shared shell');
    shellAssert(strpos($source, 'cpcc_admin_shell_close()') !== false, $relative.' closes the shared shell');
}

$board = file_get_contents($root.'/chinaplanetcustomercenter/board.php');
$documents = file_get_contents($root.'/chinaplanetcustomercenter/documents.php');
$shipmentList = file_get_contents($root.'/chinaplanetlogistics/list.php');
shellAssert(strpos($board, 'if ($is_external) cpcc_portal_navigation') !== false, 'portal home preserves external navigation');
shellAssert(strpos($documents, 'if ($is_external) cpcc_portal_navigation') !== false, 'documents preserve external navigation');
shellAssert(strpos($shipmentList, 'if ($is_external) cpcc_portal_navigation') !== false, 'shipment list preserves external navigation');

$adminCss = file_get_contents($root.'/chinaplanetcustomercenter/css/admin_dashboard.css');
shellAssert(strpos($adminCss, '#id-left{display:none!important') !== false, 'shared shell hides the legacy Dolibarr left menu');
shellAssert(strpos($adminCss, '.cpad-nav.is-open .cpad-nav-links{display:grid}') !== false, 'mobile navigation opens as a touch-friendly grid');
shellAssert(strpos($adminCss, '@media(max-width:360px){.cpad-nav-links{grid-template-columns:1fr}') !== false, 'mobile navigation falls back to one column on narrow screens');
$orders = file_get_contents($root.'/chinaplanetcustomercenter/orders.php');
shellAssert(strpos($orders, "/chinaplanetcustomercenter/order.php") !== false, 'order rows stay inside the customer center');
$operations = file_get_contents($root.'/chinaplanetcustomercenter/admin/operations.php');
shellAssert(strpos($operations, 'target="_blank" rel="noopener"') !== false, 'advanced native Dolibarr links are explicitly isolated in a new tab');

print 'Unified admin shell tests passed'.PHP_EOL;
