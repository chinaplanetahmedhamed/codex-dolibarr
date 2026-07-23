<?php

function m030_source($relativePath)
{
    $path = __DIR__.'/../../'.$relativePath;
    $source = @file_get_contents($path);
    if ($source === false) {
        fwrite(STDERR, 'Unable to read '.$relativePath."\n");
        exit(1);
    }
    return $source;
}

function m030_assert_contains($source, $needle, $label)
{
    if (strpos($source, $needle) === false) {
        fwrite(STDERR, $label.' is missing'."\n");
        exit(1);
    }
}

function m030_assert_not_contains($source, $needle, $label)
{
    if (strpos($source, $needle) !== false) {
        fwrite(STDERR, $label.' is still present'."\n");
        exit(1);
    }
}

$adminShell = m030_source('custom/chinaplanetcustomercenter/lib/admin_shell.lib.php');
m030_assert_not_contains($adminShell, "'export'=>array", 'Export Documents navigation item');
m030_assert_contains($adminShell, '/admin/financial_portfolio.php', 'Financial Center navigation route');
m030_assert_contains($adminShell, 'Financial Center / المركز المالي', 'Financial Center navigation label');

$descriptor = m030_source('custom/chinaplanetcustomercenter/core/modules/modChinaPlanetCustomerCenter.class.php');
m030_assert_contains($descriptor, "'url' => '/custom/chinaplanetcustomercenter/admin/financial_portfolio.php'", 'Dolibarr Financial Center menu route');
m030_assert_contains($descriptor, "'leftmenu' => 'export_documents'", 'Retained Export Documents feature descriptor');
if (!preg_match("/'leftmenu'\s*=>\s*'export_documents'.*?'enabled'\s*=>\s*'0'/s", $descriptor)) {
    fwrite(STDERR, "Export Documents Dolibarr menu is not disabled\n");
    exit(1);
}

$board = m030_source('custom/chinaplanetcustomercenter/board.php');
m030_assert_contains($board, '/chinaplanetcustomercenter/documents.php', 'Customer Documents route');
m030_assert_contains($board, 'cpcc_portal_customer_document_count($db, $user, $socid)', 'Role-aware customer Documents count');
$portal = m030_source('custom/chinaplanetcustomercenter/lib/portal.lib.php');
m030_assert_contains($portal, 'p.entity=', 'Document publication entity scope');
m030_assert_contains($portal, 'p.fk_soc=', 'Document publication customer scope');
m030_assert_contains($portal, 'p.customer_visible=1', 'Customer-visible document restriction');
m030_assert_contains($board, 'الفواتير / Invoices', 'Generic customer invoice label');
m030_assert_contains($board, 'المستندات / Documents', 'Generic customer document label');
m030_assert_contains($board, 'التقارير / Reports', 'Generic customer report label');
m030_assert_not_contains($board, 'فواتير خدمات Vision', 'Legacy customer invoice label');
m030_assert_not_contains($board, 'تقارير الخدمات', 'Legacy customer report label');

$operations = m030_source('custom/chinaplanetcustomercenter/lib/operations.lib.php');
m030_assert_contains($operations, "'label'=>'صدرت الفاتورة','en'=>'Invoice issued'", 'Generic project invoice milestone');
m030_assert_contains($operations, "'label'=>'اكتملت المستندات','en'=>'Documents complete'", 'Generic project document milestone');
m030_assert_contains($operations, "'label'=>'تمت العملية','en'=>'Operation finished'", 'Operation finished milestone');

$invoices = m030_source('custom/chinaplanetcustomercenter/invoices.php');
m030_assert_contains($invoices, 'Invoices / الفواتير', 'Generic invoices page title');
m030_assert_not_contains($invoices, 'Vision Service Invoices', 'Legacy invoices page title');

$reports = m030_source('custom/chinaplanetcustomercenter/operation_reports.php');
m030_assert_contains($reports, 'Reports / التقارير', 'Generic reports page title');
m030_assert_not_contains($reports, 'Service Reports', 'Legacy reports page title');
m030_assert_contains($reports, '/custom/chinaplanetcustomercenter/css/admin_dashboard.css', 'Shared admin design on reports list');

$report = m030_source('custom/chinaplanetcustomercenter/operation_report.php');
m030_assert_contains($report, '/custom/chinaplanetcustomercenter/css/admin_dashboard.css', 'Shared admin design on report details');

$portfolio = m030_source('custom/chinaplanetcustomercenter/admin/financial_portfolio.php');
m030_assert_contains($portfolio, 'Finance Allocation Center', 'Allocation Center action on Financial Center');
m030_assert_contains($portfolio, 'cpad-primary-action', 'Prominent Financial Center action style');

$sop = m030_source('custom/chinaplanetshippingprices/sop/index.php');
m030_assert_contains($sop, '/custom/chinaplanetcustomercenter/css/admin_dashboard.css', 'Shared admin design on SOP page');
m030_assert_contains($sop, "cpcc_admin_shell_open('sop'", 'Shared admin shell on SOP page');
foreach (array('categories.php', 'edit.php', 'view.php') as $sopPage) {
    $sopSource = m030_source('custom/chinaplanetshippingprices/sop/'.$sopPage);
    m030_assert_contains($sopSource, '/custom/chinaplanetcustomercenter/css/admin_dashboard.css', 'Shared admin design on SOP '.$sopPage);
}

echo "M030 generic labels and navigation tests passed\n";
