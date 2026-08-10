<?php

if (PHP_SAPI !== 'cli') { http_response_code(403); exit(1); }
$root = dirname(__DIR__, 2);
$required = array(
    'custom/chinaplanetshippingprices/core/modules/modChinaPlanetShippingPrices.class.php',
    'custom/chinaplanetshippingprices/class/ShippingPriceImporter.class.php',
    'custom/chinaplanetshippingprices/class/ShippingPriceService.class.php',
    'custom/chinaplanetshippingprices/class/ShippingNoteTranslationService.class.php',
    'custom/chinaplanetshippingprices/class/ShippingNoteReviewService.class.php',
    'custom/chinaplanetshippingprices/index.php',
    'custom/chinaplanetshippingprices/admin/import.php',
    'custom/chinaplanetshippingprices/admin/dashboard.php',
    'custom/chinaplanetshippingprices/admin/rules.php',
    'custom/chinaplanetshippingprices/admin/disabled.php',
    'custom/chinaplanetshippingprices/sql/llx_chinaplanetsp_block.sql',
    'custom/chinaplanetshippingprices/api/class/api_chinaplanetshippingprices.class.php',
    'custom/chinaplanetshippingprices/class/ShippingSopService.class.php',
    'custom/chinaplanetshippingprices/sop/index.php',
    'custom/chinaplanetshippingprices/sop/edit.php',
    'custom/chinaplanetshippingprices/sop/view.php',
    'custom/chinaplanetshippingprices/sop/print.php',
    'custom/chinaplanetshippingprices/sop/categories.php',
    'custom/chinaplanetshippingprices/sql/llx_chinaplanetsp_sop.sql',
    'custom/chinaplanetshippingprices/sql/llx_chinaplanetsp_sop_revision.sql',
    'custom/chinaplanetshippingprices/sql/llx_chinaplanetsp_sop_category.sql',
);
foreach ($required as $file) if (!is_file($root.'/'.$file)) throw new RuntimeException('Missing '.$file);
$descriptor = file_get_contents($root.'/custom/chinaplanetshippingprices/core/modules/modChinaPlanetShippingPrices.class.php');
foreach (array('500400', "'viewcost'", "'import'", "'admin'", "'api'") as $needle) if (strpos($descriptor, $needle) === false) throw new RuntimeException('Descriptor missing '.$needle);
foreach (array('500406', "'sopread'", "'sopwrite'", "'soppublish'", "'sopdelete'", "'sopcategory'") as $needle) if (strpos($descriptor, $needle) === false) throw new RuntimeException('SOP permission missing '.$needle);
$service = file_get_contents($root.'/custom/chinaplanetshippingprices/class/ShippingPriceService.class.php');
if (strpos($service, "if (\$includeCost)") === false) throw new RuntimeException('Cost visibility boundary missing.');
if (strpos($service, 'is_current=1') === false) throw new RuntimeException('Current published rate boundary missing.');
if (strpos($service, 'chinaplanetsp_block') === false || strpos($service, 'NOT EXISTS') === false) throw new RuntimeException('Disabled-price inquiry boundary missing.');
$importer = file_get_contents($root.'/custom/chinaplanetshippingprices/class/ShippingPriceImporter.class.php');
if (strpos($importer, 'CHINAPLANETSP_DEFAULT_POL') === false || strpos($importer, "'中东'=>'Middle East'") === false || strpos($importer, "'HYUNDAI'=>'HMM'") === false) throw new RuntimeException('POL, trade-lane or shipping-line translation missing.');
if (strpos($importer, "replacement_scope !== 'all'") === false || strpos($importer, "source_name<>'Manual entry'") === false) throw new RuntimeException('Agent/all publication replacement scope missing.');
if (strpos($importer, "ignore_below=") === false || strpos($importer, "\$signature=") === false) throw new RuntimeException('Import-setting-aware duplicate boundary missing.');
if (strpos($importer, "translation_status<>'approved'") === false || strpos($importer, "'public_notes' => \$translation['status'] === 'approved' ? \$translation['notes_en'] : ''") === false) throw new RuntimeException('M041 automatic translation/manual-fallback boundary missing.');
$ports = file_get_contents($root.'/custom/chinaplanetshippingprices/admin/ports.php');
if (strpos($ports, "\$action==='assign_pol'") === false || strpos($ports, "(pol='UNKNOWN' OR pol='' OR pol IS NULL)") === false) throw new RuntimeException('Unknown POL assignment control missing.');
$disabled = file_get_contents($root.'/custom/chinaplanetshippingprices/admin/disabled.php');
if (strpos($disabled, "chinaplanetsp_require_internal(\$user, 'admin')") === false || strpos($disabled, "\$action==='restore'") === false) throw new RuntimeException('Disabled-price permission or restore control missing.');
$dashboard = file_get_contents($root.'/custom/chinaplanetshippingprices/admin/dashboard.php');
if (strpos($dashboard, 'empty($user->socid)') === false || strpos($dashboard, '$canImport') === false || strpos($dashboard, '$canAdmin') === false) throw new RuntimeException('Internal control-center permission boundary missing.');
$inquiry = file_get_contents($root.'/custom/chinaplanetshippingprices/index.php');
if (strpos($inquiry, '$canManage=!$isExternal') === false) throw new RuntimeException('External management-link boundary missing.');
if (strpos($inquiry, "chinaplanetsp_can_sop(\$user,'sopread')") === false || strpos($inquiry, '$sopService->suggest') === false) throw new RuntimeException('Contextual SOP suggestion boundary missing.');
$sopService = file_get_contents($root.'/custom/chinaplanetshippingprices/class/ShippingSopService.class.php');
foreach (array('published_revision', 'revision_status=9', 'restoreRevision', 'suggest(array $context') as $needle) if (strpos($sopService, $needle) === false) throw new RuntimeException('SOP revision/service boundary missing '.$needle);
foreach (array('index.php','edit.php','view.php','print.php','categories.php') as $page) {
    $source = file_get_contents($root.'/custom/chinaplanetshippingprices/sop/'.$page);
    if (strpos($source, 'chinaplanetsp_require_sop($user') === false) throw new RuntimeException('SOP internal permission gate missing on '.$page);
}
$sopEdit = file_get_contents($root.'/custom/chinaplanetshippingprices/sop/edit.php');
$sopView = file_get_contents($root.'/custom/chinaplanetshippingprices/sop/view.php');
$sopCategories = file_get_contents($root.'/custom/chinaplanetshippingprices/sop/categories.php');
if (strpos($sopEdit, "define('CSRFCHECK_WITH_TOKEN', 1)") === false || strpos($sopView, "define('CSRFCHECK_WITH_TOKEN', 1)") === false || strpos($sopCategories, "define('CSRFCHECK_WITH_TOKEN',1)") === false) throw new RuntimeException('SOP CSRF protection missing.');
$api = file_get_contents($root.'/custom/chinaplanetshippingprices/api/class/api_chinaplanetshippingprices.class.php');
if (strpos($api, '), false, 100)') === false) throw new RuntimeException('API must force customer-safe output.');
$translator = file_get_contents($root.'/custom/chinaplanetshippingprices/class/ShippingNoteTranslationService.class.php');
if (strpos($translator, 'return false;') === false || strpos($translator, 'curl_') !== false || strpos($translator, 'getenv(') !== false || strpos($translator, 'https://') !== false) throw new RuntimeException('M046 translation hard-disable/provider-removal boundary missing.');
print "OK shipping prices structure and privacy boundaries\n";
