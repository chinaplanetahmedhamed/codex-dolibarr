<?php
/** Static integration contract for the Vision operations command center. */

$root = dirname(__DIR__, 2);
$dashboard = file_get_contents($root.'/custom/chinaplanetcustomercenter/admin_dashboard.php');
$javascript = file_get_contents($root.'/custom/chinaplanetcustomercenter/js/admin_dashboard.js');
$stylesheet = file_get_contents($root.'/custom/chinaplanetcustomercenter/css/admin_dashboard.css');

function commandCenterAssert($condition, $message)
{
    if (!$condition) throw new RuntimeException('FAIL: '.$message);
    print 'PASS '.$message.PHP_EOL;
}

$widgets = array(
    'kpi_customers', 'kpi_orders', 'kpi_projects', 'kpi_shipments', 'kpi_alerts', 'kpi_tasks',
    'attention_center', 'customer_finance', 'workflow', 'shipment_control', 'monthly_revenue',
    'live_shipments', 'recent_orders', 'recent_finance', 'top_customers', 'documents_center',
    'shipping_prices', 'quick_access',
);
foreach ($widgets as $widget) {
    commandCenterAssert(strpos($dashboard, 'data-cpad-widget="'.$widget.'"') !== false, 'widget '.$widget.' is available');
}

$destinations = array(
    '/chinaplanetcustomercenter/orders.php?status=active',
    '/chinaplanetcustomercenter/projects.php?status=active',
    '/chinaplanetlogistics/list.php?status=critical',
    '/chinaplanetcustomercenter/admin/financial.php',
    '/chinaplanetcustomercenter/admin/operations.php',
    '/chinaplanetcustomercenter/admin/export_document.php',
    '/chinaplanetcustomercenter/commercial_documents.php',
    '/chinaplanetshippingprices/index.php',
    '/chinaplanetshippingprices/admin/import.php',
    '/cpkanban/board.php',
);
foreach ($destinations as $destination) {
    commandCenterAssert(strpos($dashboard, $destination) !== false, 'real destination '.$destination.' is linked');
}
commandCenterAssert(strpos($dashboard, '/chinaplanetoperations/index.php') !== false, 'internal operations center is linked');
commandCenterAssert(strpos($dashboard, 'service_type=shipping_booking') !== false, 'new shipping booking shortcut is linked');
commandCenterAssert(strpos($dashboard, '/chinaplanetoperations/tasks/board.php') !== false, 'internal task board is linked');

commandCenterAssert(strpos($dashboard, 'cpcc_financial_summary') !== false, 'financial totals use the customer finance business logic');
commandCenterAssert(strpos($dashboard, 'cpadTableExists') !== false, 'optional module tables are guarded');
commandCenterAssert(strpos($javascript, 'data-cpad-widget') !== false && strpos($javascript, "body.set('layout'") !== false, 'personal layout remains interactive and persistent');
commandCenterAssert(strpos($stylesheet, '.cpad-attention-grid') !== false && strpos($stylesheet, '.cpad-finance-grid') !== false, 'command center responsive presentation is installed');

print 'Admin dashboard command center tests passed'.PHP_EOL;
