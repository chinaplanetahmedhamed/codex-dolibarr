<?php
if (!isset($_SERVER['QUERY_STRING'])) $_SERVER['QUERY_STRING'] = '';
require_once __DIR__.'/lib/main.lib.php';
require_once __DIR__.'/lib/admin_shell.lib.php';
require_once __DIR__.'/lib/portal.lib.php';
require_once __DIR__.'/lib/operation_reports.lib.php';
cpcc_portal_require_access($user);
$scope=cpcc_portal_user_scope($user);$isExternal=!empty($scope['is_external']);$socid=cpcc_portal_customer_scope_id($db,$user,GETPOSTINT('socid'));if($socid<=0)cpcc_portal_access_denied($user,'reports');
$projectid=GETPOSTINT('projectid');$reports=cpcc_operation_reports($db,$socid,$projectid);
llxHeader('','Service reports / تقارير الخدمات','','',0,0,array(),array('/custom/chinaplanetcustomercenter/css/customer_dashboard.css','/custom/chinaplanetcustomercenter/css/operations.css'));
if($isExternal)cpcc_portal_navigation($user,'reports');else cpcc_admin_shell_open('portal','Service reports / تقارير الخدمات','تقارير QC وتدقيق المصنع والعمليات المنشورة للعميل فقط','fa-clipboard-check',array('socid'=>$socid,'projectid'=>$projectid));
?>
<main class="cpcc-dashboard" dir="rtl"><header class="cpcc-page-head"><div><span class="cpcc-eyebrow">CUSTOMER-SAFE OUTPUTS</span><h1>تقارير الخدمات</h1><p>Quality inspection, factory audit and approved operational reports</p></div></header>
<section class="cpcc-document-grid"><?php if(!$reports){ ?><div class="cpcc-empty">لا توجد تقارير منشورة للعميل بعد.</div><?php } foreach($reports as $report){$url=cpcc_portal_scoped_url('/chinaplanetcustomercenter/operation_report.php',$isExternal,$socid,array('id'=>(int)$report['rowid'])); ?><a class="cpcc-document-card" href="<?php echo dol_escape_htmltag($url); ?>"><span><?php echo dol_escape_htmltag(str_replace('_',' ',ucwords($report['service_type'],'_'))); ?></span><strong><?php echo dol_escape_htmltag($report['title']); ?></strong><small><?php echo dol_escape_htmltag($report['ref']); ?> · <?php echo (int)$report['progress']; ?>%</small></a><?php } ?></section></main>
<?php if(!$isExternal)cpcc_admin_shell_close();llxFooter();$db->close();
