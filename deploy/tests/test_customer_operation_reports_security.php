<?php

$root = dirname(__DIR__, 2);

function reportSecurityAssert($condition, $message)
{
    if (!$condition) throw new RuntimeException('FAIL: '.$message);
    print 'PASS '.$message.PHP_EOL;
}

$library = file_get_contents($root.'/custom/chinaplanetcustomercenter/lib/operation_reports.lib.php');
$download = file_get_contents($root.'/custom/chinaplanetcustomercenter/operation_document.php');
$report = file_get_contents($root.'/custom/chinaplanetcustomercenter/operation_report.php');
$portal = file_get_contents($root.'/custom/chinaplanetcustomercenter/lib/portal.lib.php');

reportSecurityAssert(strpos($library, 'o.fk_soc=') !== false, 'report list and detail are customer scoped');
reportSecurityAssert(substr_count($library, 'customer_visible=1') >= 2, 'only customer-visible operations are selected');
reportSecurityAssert(strpos($library, "visibility='customer_after_approval'") !== false, 'only explicitly customer-approved documents are listed');
reportSecurityAssert(strpos($library, "review_status IN ('approved','final_received')") !== false, 'only reviewed documents are listed');
reportSecurityAssert(strpos($library, 'is_current=1') !== false, 'only current document revisions are listed');
reportSecurityAssert(strpos($download, 'o.fk_soc=') !== false, 'download rechecks customer ownership server-side');
reportSecurityAssert(strpos($download, "d.visibility='customer_after_approval'") !== false, 'download rejects internal files');
reportSecurityAssert(strpos($download, "d.review_status IN ('approved','final_received')") !== false, 'download rejects unapproved files');
reportSecurityAssert(strpos($report, 'note_private') === false, 'customer report never renders internal operation notes');
reportSecurityAssert(strpos($report, 'supplier cost') === false, 'customer report contains no supplier-cost value');
reportSecurityAssert(strpos($portal, "'reports' =>") !== false, 'customer navigation exposes approved service reports');

print 'Customer operation report security tests passed'.PHP_EOL;
