<?php
$root = dirname(__DIR__, 2);
$page = file_get_contents($root.'/custom/chinaplanetcustomercenter/admin/financial_portfolio.php');
$css = file_get_contents($root.'/custom/chinaplanetcustomercenter/css/ux_fixes.css');

$checks = array(
    'responsive table classes' => substr_count($page, 'cpad-responsive-table') === 2,
    'totals mobile labels' => strpos($page, 'data-label="Currency / العملة"') !== false && strpos($page, 'data-label="Required Funding / التمويل المطلوب"') !== false,
    'customer mobile labels' => strpos($page, 'data-label="Customer / العميل"') !== false && strpos($page, 'data-label="Action / إجراء"') !== false,
    'mobile card breakpoint' => strpos($css, '@media (max-width: 768px)') !== false,
    'mobile rows become cards' => strpos($css, '.cpad-responsive-table tbody tr{display:block') !== false,
    'mobile values do not truncate' => strpos($css, 'text-overflow:clip;white-space:normal') !== false,
    'desktop minimum remains' => strpos($css, '.cpad-finance-portfolio-theme .cpad-table{min-width:680px}') !== false,
);

foreach ($checks as $label => $passed) {
    if (!$passed) {
        fwrite(STDERR, "M028-C04 FAIL: {$label}\n");
        exit(1);
    }
}

echo "M028_C04_FINANCIAL_PORTFOLIO_MOBILE=PASS\n";
