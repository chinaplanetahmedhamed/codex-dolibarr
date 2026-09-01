<?php

if (PHP_SAPI !== 'cli') { http_response_code(403); exit(1); }
$root = dirname(__DIR__, 2);

function m070Assert($condition, $message)
{
    if (!$condition) throw new RuntimeException($message);
}

foreach (file($root.'/docs/REPORTS/M069_EVIDENCE_FREEZE.sha256', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: array() as $line) {
    m070Assert((bool) preg_match('/^([a-f0-9]{64})  ([^\\0]+)$/D', $line, $match), 'Invalid M069 freeze row.');
    $file = $root.'/'.trim($match[2]);
    m070Assert(is_file($file), 'Frozen M069 evidence is absent: '.$match[2]);
    m070Assert(hash_file('sha256', $file) === $match[1], 'Frozen M069 evidence changed: '.$match[2]);
}

$m070SecurityCorrections = array(
    'custom/chinaplanetcustomercenter/invoice.php' => '3276a410394ac693515cc82319e0c11ab60e625982e083d50c01ae000dbfdc0c',
    'custom/chinaplanetcustomercenter/lib/mobile_api_data.lib.php' => 'e6c837c299af4b018d85686b3d0bb37e0d0fccf5c4b4d0c53a0b8bf086113607',
);
$m071ApprovedSuccessors = array(
    'custom/chinaplanetcustomercenter/core/modules/modChinaPlanetCustomerCenter.class.php',
    'custom/chinaplanetcustomercenter/lib/portal.lib.php',
);
$m061Exact = 0;
$m061Corrected = 0;
$m071Succeeded = 0;
foreach (file($root.'/docs/REPORTS/M069_M061_FINAL_EFFECTIVE_RUNTIME.sha256', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: array() as $line) {
    m070Assert((bool) preg_match('/^([a-f0-9]{64})  ([^\\0]+)$/D', $line, $match), 'Invalid M061 hash row.');
    $file = $root.'/'.$match[2];
    m070Assert(is_file($file), 'M061 runtime source is absent: '.$match[2]);
    if (in_array($match[2], $m071ApprovedSuccessors, true)) {
        $m071Succeeded++;
    } elseif (isset($m070SecurityCorrections[$match[2]])) {
        m070Assert(hash_file('sha256', $file) === $m070SecurityCorrections[$match[2]], 'M070 customer-unit security correction changed: '.$match[2]);
        $m061Corrected++;
    } else {
        m070Assert(hash_file('sha256', $file) === $match[1], 'M061 runtime source does not match the accepted Staging layer: '.$match[2]);
        $m061Exact++;
    }
}
m070Assert($m061Exact === 35 && $m061Corrected === 2 && $m071Succeeded === 2, 'M061 overlay/correction/successor count changed.');

foreach (array(
    'custom/chinaplanetcustomercenter/admin/acceptance_demo.php',
    'custom/chinaplanetcustomercenter/admin/demo/seed_customer_portal_demo.php',
    'custom/chinaplanetcustomercenter/admin/demo/seed_full_order_cycles.php',
    'custom/chinaplanetcustomercenter/admin/demo/seed_operations_sops.php',
) as $residue) {
    m070Assert(!file_exists($root.'/'.$residue), 'Excluded M069-C04 residue exists: '.$residue);
}

m070Assert(
    hash_file('sha256', $root.'/custom/chinaplanetlogistics/.htaccess') === '45b9e1fbfdb9279beeba83af81ebfc881f3edad9706d54e7ee50806afa169daf',
    'Accepted Staging Logistics upload boundary changed.'
);

m070Assert(is_link($root.'/custom/chinaplanetcustomercenter/chinaplanetlogistics'), 'Logistics compatibility path is not a symlink.');
m070Assert(readlink($root.'/custom/chinaplanetcustomercenter/chinaplanetlogistics') === '../chinaplanetlogistics', 'Logistics symlink target changed.');
m070Assert(is_link($root.'/custom/chinaplanetcustomercenter/cpkanban'), 'CP Kanban compatibility path is not a symlink.');
m070Assert(readlink($root.'/custom/chinaplanetcustomercenter/cpkanban') === '../cpkanban', 'CP Kanban symlink target changed.');

$compatibility = file_get_contents($root.'/custom/chinaplanetcustomercenter/lib/projectsync.lib.php');
m070Assert(strpos($compatibility, "DOL_DOCUMENT_ROOT.'/custom/cpkanban/lib/projectsync.lib.php'") !== false, 'Customer Center project sync is not a compatibility include.');
m070Assert(hash_file('sha256', $root.'/custom/cpkanban/lib/projectsync.lib.php') === '86522f6cf9c7e1bcd35982958e8a425f3e5eb824556614973c4fbff413507e4e', 'CP Kanban project-sync owner changed.');

foreach (array(
    'custom/chinaplanetcustomercenter/activate.php',
    'custom/chinaplanetcustomercenter/class/PortalInvitationService.class.php',
    'custom/chinaplanetcustomercenter/css/activation.css',
    'custom/chinaplanetcustomercenter/css/quotation_requests.css',
    'custom/chinaplanetcustomercenter/quotation_download.php',
    'custom/chinaplanetcustomercenter/reset_password.php',
    'custom/chinaplanetcustomercenter/sql/llx_cpcc_portal_invitation.sql',
    'custom/chinaplanetcustomercenter/sql/llx_cpcc_portal_invitation_attempt.sql',
    'custom/chinaplanetoperations/class/PublicRequestPublicationService.class.php',
    'custom/chinaplanetoperations/sql/llx_cpo_public_request_publication.sql',
    'deploy/migrations/m068_website_lead_quotation_portal_lifecycle.php',
    'deploy/tests/test_m068_lifecycle_runtime.php',
    'deploy/tests/test_m068_lifecycle_source.php',
    'deploy/tests/test_m068_portal_currency_unit.php',
    'deploy/tests/test_m068_portal_invitation_security_source.php',
    'docs/MISSIONS/M068_WEBSITE_LEAD_QUOTATION_CUSTOMER_CENTER_LIFECYCLE.md',
    'docs/REPORTS/M068_LOCAL_COMPLETION_REPORT.md',
) as $m068Only) {
    m070Assert(!file_exists($root.'/'.$m068Only), 'Rejected M068-R04-only source exists: '.$m068Only);
}

foreach (array(
    'custom/chinaplanetshippingprices/class/PortNameResolver.class.php',
    'custom/chinaplanetshippingprices/class/PublicShippingRateService.class.php',
    'custom/chinaplanetshippingprices/sql/llx_chinaplanetsp_port_alias.sql',
    'deploy/tests/test_m062_public_alexandria_group.php',
    'deploy/tests/test_m061_customer_documents_contract.php',
    'deploy/migrations/m061_r02_customer_center_schema.php',
    'mobile/chinaplanet-customer-app/app/(tabs)/more/service-invoice/[id].tsx',
) as $required) {
    m070Assert(is_file($root.'/'.$required), 'Required composite source is absent: '.$required);
}

foreach (array(
    'docs/REPORTS/M070_DOLIBARR_RUNTIME_SOURCE_MAP.tsv',
    'docs/REPORTS/M070_REACT_SOURCE_MANIFEST.tsv',
    'docs/REPORTS/M070_WORDPRESS_SOURCE_MANIFEST.tsv',
    'docs/REPORTS/M070_MOBILE_SOURCE_MANIFEST.tsv',
) as $manifest) {
    m070Assert(is_file($root.'/'.$manifest), 'M070 source manifest is absent: '.$manifest);
}

print "M070_COMPOSITE_SOURCE=PASS M061_RUNTIME=39 M061_EXACT=35 M070_SECURITY_CORRECTED=2 M071_SUCCESSORS=2 RESIDUE=ABSENT M068_FAILED_ONLY=ABSENT M062_ALEXANDRIA=PRESERVED PROJECT_SYNC=CPKANBAN\n";
