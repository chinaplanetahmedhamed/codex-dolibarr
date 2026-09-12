<?php
$root = dirname(__DIR__, 2);
$workspace = file_get_contents($root.'/custom/chinaplanetoperations/workspace.php');
$dashboard = file_get_contents($root.'/custom/chinaplanetcustomercenter/admin_dashboard.php');
$portal = file_get_contents($root.'/custom/chinaplanetcustomercenter/lib/portal.lib.php');
$mission = file_get_contents($root.'/docs/MISSIONS/M079_STAFF_FIRST_OPERATIONS_DASHBOARD.md');

$fail = function ($message) { fwrite(STDERR, "M079_SOURCE=FAIL ".$message."\n"); exit(1); };
$types = array('order', 'sourcing', 'purchasing', 'quality_control', 'shipping_booking', 'customs');
foreach ($types as $type) {
    $needle = 'operations/edit.php?service_type='.$type;
    if (strpos($workspace, $needle) === false) $fail('workspace action missing: '.$type);
    if (strpos($dashboard, $needle) === false) $fail('dashboard action missing: '.$type);
}
if (strpos($workspace, 'if ($canCreateOperation') === false) $fail('workspace creation permission gate missing');
if (strpos($dashboard, '/chinaplanetoperations/workspace.php') === false) $fail('daily workspace link missing');
if (strpos($portal, "'/chinaplanetcustomercenter/orders.php'") === false) $fail('customer portal requests remain view-only contract missing');
if (strpos($portal, 'requests.php') !== false) $fail('customer write route unexpectedly added');
if (strpos($mission, 'Customer Center view-only') === false) $fail('view-only customer boundary not documented');
echo "M079_SOURCE=PASS actions=6 staff_first=YES customer_portal=VIEW_ONLY\n";
