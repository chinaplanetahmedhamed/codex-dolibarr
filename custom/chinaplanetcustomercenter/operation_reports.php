<?php
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
$rtl=strpos((string)$langs->getDefaultLang(),'ar_')===0;$pageDir=$rtl?'rtl':'ltr';
$qcResultLabels=$rtl?array('pass'=>'ناجح','fail'=>'غير ناجح','pending_corrective_action'=>'بانتظار الإجراء التصحيحي','reinspection_required'=>'إعادة فحص مطلوبة'):array('pass'=>'Pass','fail'=>'Fail','pending_corrective_action'=>'Pending corrective action','reinspection_required'=>'Reinspection required');
$serviceLabels=$rtl?array('quality_control'=>'فحص الجودة','factory_audit'=>'تدقيق المصنع','order'=>'الطلب','loading'=>'التحميل','customs'=>'الجمارك','claim'=>'المطالبة'):array('quality_control'=>'Quality control','factory_audit'=>'Factory audit','order'=>'Order','loading'=>'Loading','customs'=>'Customs','claim'=>'Claim');
$customerOptions=array();
if(!$isExternal){
    $resql=$db->query("SELECT rowid,nom FROM ".MAIN_DB_PREFIX."societe WHERE entity=".(int)$conf->entity." AND status=1 AND client IN (1,3) ORDER BY nom");
    if($resql)while($obj=$db->fetch_object($resql))$customerOptions[(int)$obj->rowid]=(string)$obj->nom;
}
$pageCss=array('/custom/chinaplanetcustomercenter/css/customer_dashboard.css','/custom/chinaplanetcustomercenter/css/operations.css');
if(!$isExternal)$pageCss[]=cpcc_admin_shell_asset('/custom/chinaplanetcustomercenter/css/admin_dashboard.css');
llxHeader('','Reports / التقارير','','',0,0,array(),$pageCss);
if($isExternal)cpcc_portal_navigation($user,'reports');else cpcc_admin_shell_open('reports','Reports / التقارير','تقارير QC وتدقيق المصنع والعمليات المنشورة للعميل فقط','fa-clipboard-check',array('socid'=>$socid,'projectid'=>$projectid));
?>
<main class="cpcc-dashboard" dir="<?php echo $pageDir; ?>"><header class="cpcc-page-head"><div><span class="cpcc-eyebrow"><?php echo $rtl?'التقارير':'REPORTS'; ?></span><h1><?php echo $rtl?'التقارير':'Reports'; ?></h1><p><?php echo $rtl?'تقارير فحص الجودة وتدقيق المصنع والتقارير المعتمدة للعميل':'Quality inspection, factory audit and approved customer reports'; ?></p></div>
<?php if(!$isExternal){ ?><form method="get" class="cpcc-customer-filter"><label><span>اختر العميل / Customer</span><?php echo Form::selectarray('socid',$customerOptions,$socid,'ابحث باسم العميل',0,0,'',0,0,0,'','minwidth300 cpcc-customer-search',1); ?></label><button type="submit">عرض / View</button></form><?php } ?></header>
<section class="cpcc-document-grid"><?php if($socid<=0){ ?><div class="cpcc-empty"><?php echo $rtl?'اختر العميل لعرض تقاريره المنشورة.':'Select a customer to view published reports.'; ?></div><?php }elseif(!$reports){ ?><div class="cpcc-empty"><?php echo $rtl?'لا توجد تقارير منشورة للعميل بعد.':'No customer reports have been published yet.'; ?></div><?php } foreach($reports as $report){$url=cpcc_portal_scoped_url('/chinaplanetcustomercenter/operation_report.php',$isExternal,$socid,array('id'=>(int)$report['rowid']));$isQc=$report['service_type']==='quality_control';$resultCode=(string)($report['overall_result']??'');$resultLabel=$qcResultLabels[$resultCode]??str_replace('_',' ',$resultCode);$serviceLabel=$serviceLabels[$report['service_type']]??str_replace('_',' ',ucwords($report['service_type'],'_')); ?><a class="cpcc-document-card" href="<?php echo dol_escape_htmltag($url); ?>"><span><?php echo dol_escape_htmltag($serviceLabel); ?></span><strong><?php echo dol_escape_htmltag($report['title']); ?></strong><?php if($isQc){ ?><small><?php echo dol_escape_htmltag($report['ref']); ?> · <?php echo dol_escape_htmltag($resultLabel); ?> · v<?php echo (int)$report['publication_version']; ?> · <?php echo dol_escape_htmltag(dol_print_date($report['date_publication'],'day')); ?></small><?php }else{ ?><small><?php echo dol_escape_htmltag($report['ref']); ?> · <?php echo (int)$report['progress']; ?>%</small><?php } ?></a><?php } ?></section></main>
<?php if(!$isExternal)cpcc_admin_shell_close();llxFooter();$db->close();
