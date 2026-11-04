<?php
// Loopback-only synthetic view demonstration. No native authentication claim.
$module = dirname(__DIR__, 4).'/custom/chinaplanetdocuments';
require_once $module.'/class/DocumentsWorkspace.class.php';
require_once $module.'/lib/workspace.lib.php';
$arabic = ($_GET['lang'] ?? '') === 'ar';
$labels = array();
foreach (file($module.'/langs/'.($arabic ? 'ar_SA' : 'en_US').'/chinaplanetdocuments.lang', FILE_IGNORE_NEW_LINES) as $line) {
    if (strpos($line, '=') !== false) { list($key, $value) = explode('=', $line, 2); $labels[$key] = $value; }
}
$translate = function ($key) use ($labels) { return $labels[$key] ?? $key; };
$customer = (object) array('id'=>7, 'entity'=>1, 'name'=>$arabic ? 'عميل تجريبي' : 'Synthetic customer', 'ref'=>'TEST-C7');
$project = (object) array('id'=>9, 'entity'=>1, 'socid'=>7, 'title'=>$arabic ? 'مشروع تجريبي' : 'Synthetic project', 'ref'=>'TEST-P9');
header('Content-Type: text/html; charset=UTF-8');
?><!doctype html><html lang="<?php echo $arabic ? 'ar' : 'en'; ?>" dir="<?php echo $arabic ? 'rtl' : 'ltr'; ?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Documents local fixture</title><style>
body{font:16px Arial,sans-serif;margin:24px auto;padding:0 16px;max-width:1100px;background:#fafafa;color:#242b33}a{color:#245b8a}h1{font-size:24px}.warning{background:#fff2ca;padding:16px}.opacitymedium{opacity:.75}
<?php readfile($module.'/css/workspace.css'); ?>
</style></head><body><p>LOCAL SYNTHETIC VIEW — no ERP authentication or editor service</p><nav><a href="?lang=en">English</a> | <a href="?lang=ar">العربية</a></nav><h1><?php echo htmlspecialchars($translate('DocumentsWorkspace'), ENT_QUOTES, 'UTF-8'); ?></h1><?php
$shipment = (object) array('id'=>3, 'entity'=>1, 'fk_soc'=>7, 'fk_project'=>9, 'container_no'=>'TEST-CONT', 'vessel_name'=>'TEST-VESSEL', 'ref'=>'TEST-S3');
$context = DocumentsWorkspace::context(1, $customer, $project, $shipment);
echo cpd_documents_selectors_html($context, '<select name="socid" id="socid"><option value="7">'.htmlspecialchars($customer->name, ENT_QUOTES, 'UTF-8').'</option></select>', array(), array(), $translate, '', 'shipment');
echo cpd_documents_workspace_html($context, '', $translate, '', 'shipment');
?></body></html>
