<?php
require_once __DIR__.'/../chinaplanetcustomercenter/lib/ui.lib.php';
if (!isset($_SERVER['QUERY_STRING'])) $_SERVER['QUERY_STRING'] = '';
require_once __DIR__.'/lib/main.lib.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/chinaplanetcustomercenter/lib/admin_shell.lib.php';
require_once DOL_DOCUMENT_ROOT.'/custom/chinaplanetcustomercenter/lib/portal.lib.php';
require_once DOL_DOCUMENT_ROOT.'/custom/chinaplanetcustomercenter/lib/financial.lib.php';

cpcc_portal_require_access($user);
$scope=cpcc_portal_user_scope($user);$isExternal=!empty($scope['is_external']);$socid=cpcc_portal_customer_scope_id($db,$user,GETPOSTINT('socid'));
if($isExternal&&$socid<=0)cpcc_portal_access_denied($user,'invoices');
$currencyFallback=cpcc_financial_currency(!empty($conf->currency)?$conf->currency:'USD');
$soc=new Societe($db);if($socid>0)$soc->fetch($socid);

$invoices=array();
if($socid>0){
    $invoices=cpcc_financial_customer_invoices($db,$socid,$currencyFallback);
}elseif(!$isExternal){
    $customers=$db->query("SELECT rowid,nom FROM ".MAIN_DB_PREFIX."societe WHERE entity=".(int)$conf->entity." AND client IN (1,3) ORDER BY rowid");
    if($customers)while($customer=$db->fetch_object($customers)){
        foreach(cpcc_financial_customer_invoices($db,(int)$customer->rowid,$currencyFallback) as $invoice){$invoice['customer_name']=(string)$customer->nom;$invoice['customer_id']=(int)$customer->rowid;$invoices[]=$invoice;}
    }
}
$totals=array();foreach($invoices as $invoice){$code=$invoice['currency'];if(!isset($totals[$code]))$totals[$code]=array('total'=>0,'paid'=>0,'remaining'=>0,'count'=>0);$totals[$code]['total']+=(float)$invoice['total'];$totals[$code]['paid']+=(float)$invoice['paid'];$totals[$code]['remaining']+=(float)$invoice['remaining'];$totals[$code]['count']++;}
ksort($totals);

$css=$isExternal?array(cpcc_portal_asset('/custom/chinaplanetcustomercenter/css/customer_dashboard.css'),cpcc_portal_asset('/custom/chinaplanetcustomercenter/css/funding.css')):array(cpcc_admin_shell_asset('/custom/chinaplanetcustomercenter/css/admin_dashboard.css'),cpcc_admin_shell_asset('/custom/chinaplanetcustomercenter/css/ux_fixes.css'));
llxHeader('',cpcc_ui_english_label('Financial Reports', 'Financial Reports / التقارير المالية'),'','',0,0,array(),$css);
if($isExternal)cpcc_portal_navigation($user,'balance');else cpcc_admin_shell_open('financial',cpcc_ui_english_label('Financial Reports', 'Financial Reports / التقارير المالية'),cpcc_ui_english_label('Invoice and payment totals separated by original currency.', 'ملخص الفواتير والمدفوعات مفصولاً حسب العملة الأصلية'),'fa-chart-pie',array('socid'=>$socid));
?>
<main class="cpcc-dashboard" dir="<?php echo cpcc_ui_english_label('ltr', 'rtl'); ?>">
 <header class="cpcc-page-head"><div><span class="cpcc-eyebrow">FINANCIAL REPORT</span><h1><?php echo cpcc_ui_english_label('Financial Status', 'الحالة المالية / Financial Status'); ?></h1><p><?php echo dol_escape_htmltag($socid>0?$soc->name:cpcc_ui_english_label('All Customers', 'All Customers / كل العملاء')); ?></p></div></header>
 <?php if(empty($totals)){ ?><div class="cpcc-empty"><?php echo cpcc_ui_english_label('No validated invoices.', 'لا توجد فواتير معتمدة / No validated invoices.'); ?></div><?php } ?>
 <?php foreach($totals as $code=>$values){ ?><section class="cpcc-currency-block"><h2><?php echo dol_escape_htmltag($code); ?> <small><?php echo cpcc_ui_english_label('Original currency', 'Original currency / العملة الأصلية'); ?></small></h2><div class="cpcc-financial-cards"><div class="cpcc-card invoices"><span><?php echo cpcc_ui_english_label('Total invoiced', 'إجمالي الفواتير / Total invoiced'); ?></span><strong><?php echo cpcc_portal_money($values['total']).' '.dol_escape_htmltag($code); ?></strong><small><?php echo (int)$values['count']; ?> invoices</small></div><div class="cpcc-card received"><span><?php echo cpcc_ui_english_label('Total paid', 'إجمالي المدفوع / Total paid'); ?></span><strong><?php echo cpcc_portal_money($values['paid']).' '.dol_escape_htmltag($code); ?></strong></div><div class="cpcc-card required"><span><?php echo cpcc_ui_english_label('Balance due', 'الرصيد المستحق / Balance due'); ?></span><strong><?php echo cpcc_portal_money($values['remaining']).' '.dol_escape_htmltag($code); ?></strong></div></div></section><?php } ?>
 <section class="cpcc-section-head"><div><h2><?php echo cpcc_ui_english_label('Invoices', 'الفواتير / Invoices'); ?></h2><p><?php echo cpcc_ui_english_label('Different currencies are not combined into one total.', 'لا يتم جمع العملات المختلفة في إجمالي واحد.'); ?></p></div></section>
 <div class="cpcc-table-wrap"><table class="cpcc-table"><thead><tr><th><?php echo cpcc_ui_english_label('Ref', 'المرجع / Ref'); ?></th><?php if(!$isExternal&&$socid<=0){ ?><th><?php echo cpcc_ui_english_label('Customer', 'العميل / Customer'); ?></th><?php } ?><th><?php echo cpcc_ui_english_label('Date', 'التاريخ / Date'); ?></th><th><?php echo cpcc_ui_english_label('Total', 'الإجمالي / Total'); ?></th><th><?php echo cpcc_ui_english_label('Paid', 'المدفوع / Paid'); ?></th><th><?php echo cpcc_ui_english_label('Due', 'المتبقي / Due'); ?></th><th><?php echo cpcc_ui_english_label('Status', 'الحالة / Status'); ?></th><th><?php echo cpcc_ui_english_label('Details', 'التفاصيل / Details'); ?></th></tr></thead><tbody>
 <?php if(empty($invoices)){ ?><tr><td colspan="<?php echo !$isExternal&&$socid<=0?8:7; ?><?php echo cpcc_ui_english_label('">No invoices.', '">لا توجد فواتير / No invoices.'); ?></td></tr><?php }foreach($invoices as $invoice){$url=cpcc_portal_scoped_url('/chinaplanetcustomercenter/invoice.php',$isExternal,$socid>0?$socid:(int)$invoice['customer_id'],array('id'=>(int)$invoice['id']));$labels=array('paid'=>cpcc_ui_english_label('Paid', 'مدفوعة / Paid'),'partial'=>cpcc_ui_english_label('Partially paid', 'جزئية / Partially paid'),'open'=>cpcc_ui_english_label('Open', 'مفتوحة / Open'));?><tr><td><?php echo dol_escape_htmltag($invoice['ref']); ?></td><?php if(!$isExternal&&$socid<=0){ ?><td><?php echo dol_escape_htmltag($invoice['customer_name']??'—'); ?></td><?php } ?><td><?php echo dol_escape_htmltag(dol_print_date($invoice['date'],'day')); ?></td><td><?php echo cpcc_portal_money($invoice['total']).' '.dol_escape_htmltag($invoice['currency']); ?></td><td><?php echo cpcc_portal_money($invoice['paid']).' '.dol_escape_htmltag($invoice['currency']); ?></td><td><?php echo cpcc_portal_money($invoice['remaining']).' '.dol_escape_htmltag($invoice['currency']); ?></td><td><?php echo dol_escape_htmltag($labels[$invoice['status']]??$invoice['status']); ?></td><td><a href="<?php echo dol_escape_htmltag($url); ?><?php echo cpcc_ui_english_label('">View', '">عرض / View'); ?></a></td></tr><?php } ?>
 </tbody></table></div>
</main>
<?php if(!$isExternal)cpcc_admin_shell_close();llxFooter();
