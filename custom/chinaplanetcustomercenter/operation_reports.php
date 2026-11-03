<?php
require_once __DIR__.'/lib/ui.lib.php';
if (!isset($_SERVER['QUERY_STRING'])) $_SERVER['QUERY_STRING'] = '';
require_once __DIR__.'/lib/main.lib.php';
require_once __DIR__.'/lib/admin_shell.lib.php';
require_once __DIR__.'/lib/portal.lib.php';
require_once __DIR__.'/lib/operation_reports.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
cpcc_portal_require_access($user);
$scope=cpcc_portal_user_scope($user);$isExternal=!empty($scope['is_external']);$socid=cpcc_portal_customer_scope_id($db,$user,GETPOSTINT('socid'));
if($isExternal&&$socid<=0)cpcc_portal_access_denied($user,'reports');
$projectid=GETPOSTINT('projectid');$reports=$socid>0?cpcc_operation_reports($db,$socid,$projectid):array();
$customerOptions=array();
if(!$isExternal){
    $resql=$db->query("SELECT rowid,nom FROM ".MAIN_DB_PREFIX."societe WHERE entity=".(int)$conf->entity." AND status=1 AND client IN (1,3) ORDER BY nom");
    if($resql)while($obj=$db->fetch_object($resql))$customerOptions[(int)$obj->rowid]=(string)$obj->nom;
}
$pageCss=array(cpcc_portal_asset('/custom/chinaplanetcustomercenter/css/customer_dashboard.css'),cpcc_portal_asset('/custom/chinaplanetcustomercenter/css/documents.css'),cpcc_portal_asset('/custom/chinaplanetcustomercenter/css/operations.css'));
if(!$isExternal)$pageCss[]=cpcc_admin_shell_asset('/custom/chinaplanetcustomercenter/css/admin_dashboard.css');
llxHeader('',cpcc_ui_english_label('Reports', 'Reports / التقارير'),'','',0,0,array(),$pageCss);
if($isExternal)cpcc_portal_navigation($user,'reports');else cpcc_admin_shell_open('reports',cpcc_ui_english_label('Reports', 'Reports / التقارير'),cpcc_ui_english_label('QC, factory audit and operational reports published for the customer only.', 'تقارير QC وتدقيق المصنع والعمليات المنشورة للعميل فقط'),'fa-clipboard-check',array('socid'=>$socid,'projectid'=>$projectid));
?>
<main class="cpcc-dashboard" dir="<?php echo cpcc_ui_english_label('ltr', 'rtl'); ?>"><header class="cpcc-page-head"><div><span class="cpcc-eyebrow">REPORTS</span><h1><?php echo cpcc_ui_english_label('Reports', 'التقارير / Reports'); ?></h1><p>Quality inspection, factory audit and approved customer reports</p></div>
<?php if(!$isExternal){ ?><form method="get" class="cpcc-customer-filter"><label><span><?php echo cpcc_ui_english_label('Customer', 'اختر العميل / Customer'); ?></span><?php echo Form::selectarray('socid',$customerOptions,$socid,cpcc_ui_english_label('Search by customer name', 'ابحث باسم العميل'),0,0,'',0,0,0,'','minwidth300 cpcc-customer-search',1); ?></label><button type="submit<?php echo cpcc_ui_english_label('">View', '">عرض / View'); ?></button></form><?php } ?></header>
<section class="cpcc-document-grid cpcc-report-grid"><?php if($socid<=0){ ?><div class="cpcc-empty"><?php echo cpcc_ui_english_label('Select a customer to view published reports.', 'اختر العميل لعرض التقارير المنشورة الخاصة به. / Select a customer to view published reports.'); ?></div><?php }elseif(!$reports){ ?><div class="cpcc-empty"><?php echo cpcc_ui_english_label('No reports have been published for the customer yet.', 'لا توجد تقارير منشورة للعميل بعد.'); ?></div><?php } foreach($reports as $report){$url=cpcc_portal_scoped_url('/chinaplanetcustomercenter/operation_report.php',$isExternal,$socid,array('id'=>(int)$report['rowid'])); ?><a class="cpcc-document-card cpcc-report-card" href="<?php echo dol_escape_htmltag($url); ?>"><span><?php echo dol_escape_htmltag(str_replace('_',' ',ucwords($report['service_type'],'_'))); ?></span><strong><?php echo dol_escape_htmltag($report['title']); ?></strong><small><?php echo dol_escape_htmltag($report['ref']); ?></small><b><?php echo (int)$report['progress']; ?>%</b><em><?php echo cpcc_ui_english_label('Open report', 'فتح التقرير / Open report'); ?></em></a><?php } ?></section></main>
<?php if(!$isExternal)cpcc_admin_shell_close();llxFooter();$db->close();
