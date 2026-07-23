<?php
$root = dirname(__DIR__, 2);

function m032_source($relative)
{
    global $root;
    $source = file_get_contents($root.'/'.$relative);
    if ($source === false) {
        throw new RuntimeException('Unable to read '.$relative);
    }
    return $source;
}

function m032_assert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, 'FAIL '.$message.PHP_EOL);
        exit(1);
    }
    print 'PASS '.$message.PHP_EOL;
}

$shell = m032_source('custom/chinaplanetcustomercenter/lib/admin_shell.lib.php');
m032_assert(strpos($shell, 'Customer Center / مركز العملاء') !== false, 'admin navigation identifies the customer center');
m032_assert(strpos($shell, 'Reports / التقارير') !== false, 'admin navigation contains reports');
m032_assert(strpos($shell, 'SOP Library / مكتبة الإجراءات') !== false, 'admin navigation contains SOP');
m032_assert(strpos($shell, 'Financial Center / المركز المالي') !== false, 'admin navigation opens the financial center');
m032_assert(strpos($shell, 'ENVIRONMENT NOT SET') !== false, 'admin shell does not claim an unknown environment is live');
m032_assert(strpos($shell, '<small>Generated ') !== false, 'admin shell labels the generated timestamp');
m032_assert(strpos($shell, "in_array(\$activeKey, array('dashboard', 'shipping'), true)") !== false, 'new shipment action is limited to dashboard and shipping center contexts');
m032_assert(strpos($shell, "'export'=>array(") === false, 'legacy export navigation is hidden');

$portal = m032_source('custom/chinaplanetcustomercenter/lib/portal.lib.php');
m032_assert(strpos($portal, 'الحساب المالي') !== false && strpos($portal, 'Financial Account') !== false, 'portal account terminology is explicit');
m032_assert(strpos($portal, 'cpcc_portal_asset') !== false && strpos($portal, "sha1_file") !== false, 'portal assets use content versioning');

$balance = m032_source('custom/chinaplanetcustomercenter/balance.php');
m032_assert(strpos($balance, 'cpcc-profile-shortcut') !== false && strpos($balance, 'الملف الشخصي') !== false && strpos($balance, '>Profile<') !== false, 'financial account exposes the customer profile shortcut');
m032_assert(strpos($balance, 'if($isExternal)') !== false, 'profile shortcut remains external-customer only');

$shipmentList = m032_source('custom/chinaplanetlogistics/list.php');
$shipmentCss = m032_source('custom/chinaplanetlogistics/css/shipment-list.css');
m032_assert(strpos($shipmentList, 'cpl-filter-grid') !== false, 'shipment filters use the responsive grid');
m032_assert(strpos($shipmentList, 'cpl-shipment-list-wrap') !== false, 'shipment results use a contained responsive region');
m032_assert(strpos($shipmentCss, 'repeat(auto-fit, minmax(min(220px, 100%), 1fr))') !== false, 'shipment filters adapt without device-specific widths');
m032_assert(strpos($shipmentCss, 'min-height: 44px') !== false, 'shipment controls preserve touch target size');
m032_assert(strpos($shipmentCss, '@media (max-width: 700px)') !== false, 'shipment list has a compact phone layout');

$adminCss = m032_source('custom/chinaplanetcustomercenter/css/admin_dashboard.css');
m032_assert(strpos($adminCss, 'min-height:44px') !== false, 'admin navigation has usable touch targets');
m032_assert(strpos($adminCss, '@media(max-width:760px)') !== false, 'admin shell adapts for phone widths');

$reports = m032_source('custom/chinaplanetcustomercenter/operation_reports.php');
m032_assert(strpos($reports, 'if($isExternal&&$socid<=0)cpcc_portal_access_denied') !== false, 'reports keep external users behind customer ownership access');
m032_assert(strpos($reports, 'if(!$isExternal){') !== false && strpos($reports, "Form::selectarray('socid'") !== false, 'reports let staff choose a customer when no scope is selected');
m032_assert(strpos($reports, '$reports=$socid>0?cpcc_operation_reports') !== false, 'reports do not query customer data before a valid customer is selected');
m032_assert(strpos($reports, 'Select a customer to view published reports') !== false, 'reports show staff a safe empty state instead of access denied');

print 'M032 admin and customer responsive tests passed'.PHP_EOL;
