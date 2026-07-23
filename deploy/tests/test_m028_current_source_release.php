<?php

$root = dirname(__DIR__, 2);

$checks = array(
    'custom/chinaplanetcustomercenter/admin/financial.php' => array('financial_portfolio.php', 'cpcc-statement-button'),
    'custom/chinaplanetcustomercenter/admin/financial_portfolio.php' => array('cpad-finance-portfolio-theme', "GETPOST('asof'"),
    'custom/chinaplanetcustomercenter/admin_dashboard.php' => array('cpad-widget-link', 'search_datelimit_end'),
    'custom/chinaplanetcustomercenter/css/admin_dashboard.css' => array('Command-center dashboard', 'a.cpad-bar:hover', '.cpad-nav .cpad-nav-links>a', 'color:#9fc8df!important'),
    'custom/chinaplanetcustomercenter/css/ux_fixes.css' => array('.cpad-finance-portfolio-theme', 'color:#ffffff!important'),
    'custom/chinaplanetcustomercenter/lib/admin_shell.lib.php' => array("hash_file('sha256'", 'file timestamps cannot leave every admin page'),
    'custom/chinaplanetcustomercenter/lib/financial.lib.php' => array('cpcc_financial_summary', '$asOf'),
    'custom/chinaplanetlogistics/list.php' => array("'missingdocs'", 'cpcc_export_document'),
    'custom/chinaplanetoperations/css/operations.css' => array('.cpo-page', '.cpo-hero'),
    'custom/chinaplanetoperations/sop/index.php' => array('cpcc_admin_shell_open', 'cpo-page'),
    'custom/cpkanban/board.php' => array('Admin-side filters', 'CpKanbanCard::getBoard($db, $socid, $filters)'),
    'custom/cpkanban/css/board.css' => array('.cpad-section-content .cpk-wrap', '.cpk-toolbar'),
);

foreach ($checks as $relative => $needles) {
    $path = $root.'/'.$relative;
    if (!is_file($path)) {
        fwrite(STDERR, "M028_RELEASE_TEST=FAIL FILE_MISSING={$relative}\n");
        exit(1);
    }
    $contents = file_get_contents($path);
    foreach ($needles as $needle) {
        if (strpos($contents, $needle) === false) {
            fwrite(STDERR, "M028_RELEASE_TEST=FAIL TOKEN_MISSING={$relative}:{$needle}\n");
            exit(1);
        }
    }
}

echo "M028_RELEASE_TEST=PASS FILES=".count($checks)."\n";
