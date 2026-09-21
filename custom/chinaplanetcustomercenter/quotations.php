<?php
require_once __DIR__.'/lib/ui.lib.php';
if (!isset($_SERVER['QUERY_STRING'])) $_SERVER['QUERY_STRING'] = '';
require_once __DIR__.'/lib/main.lib.php';
require_once __DIR__.'/lib/admin_shell.lib.php';
require_once __DIR__.'/lib/portal.lib.php';
require_once __DIR__.'/lib/financial.lib.php';
require_once __DIR__.'/lib/commercial_trace.lib.php';

cpcc_portal_require_access($user);
$scope=cpcc_portal_user_scope($user);$isExternal=!empty($scope['is_external']);$socid=cpcc_portal_customer_scope_id($db,$user,GETPOSTINT('socid'));$projectid=GETPOSTINT('projectid');
$currencyFallback=cpcc_financial_currency(!empty($conf->currency)?$conf->currency:'USD');
$quotations=$socid>0?cpcc_customer_quotations($db,$socid,$currencyFallback,$projectid):array();
$totals=array();foreach($quotations as $quotation){$code=$quotation['currency'];if(!isset($totals[$code]))$totals[$code]=array('count'=>0,'total'=>0);$totals[$code]['count']++;$totals[$code]['total']+=$quotation['total'];}
$css=array(cpcc_portal_asset('/custom/chinaplanetcustomercenter/css/customer_dashboard.css'),cpcc_portal_asset('/custom/chinaplanetcustomercenter/css/quotations.css'));
if(!$isExternal)$css[]=cpcc_admin_shell_asset('/custom/chinaplanetcustomercenter/css/admin_dashboard.css');
llxHeader('',cpcc_ui_english_label('Quotations', 'Quotations / عروض الأسعار'),'','',0,0,array(),$css);
if($isExternal)cpcc_portal_navigation($user,'quotations');else cpcc_admin_shell_open('portal',cpcc_ui_english_label('Quotations', 'Quotations / عروض الأسعار'),cpcc_ui_english_label('Validated customer quotations only.', 'عروض العميل المعتمدة فقط'),'fa-file-signature',array('socid'=>$socid));
?>
<main class="cpcc-dashboard cpcc-quotations-page" dir="<?php echo cpcc_ui_english_label('ltr', 'rtl'); ?>">
 <header class="cpcc-page-head"><div><span class="cpcc-eyebrow">CUSTOMER QUOTATIONS</span><h1><?php echo cpcc_ui_english_label('Quotations', 'عروض الأسعار / Quotations'); ?></h1><p><?php echo cpcc_ui_english_label('Validated quotations are read-only. Drafts and acceptance or sending actions are unavailable in the portal.', 'عروض الأسعار المعتمدة للعرض فقط؛ لا تظهر المسودات ولا توجد إجراءات قبول أو إرسال من البوابة.'); ?></p></div></header>
 <?php if($socid<=0){ ?><div class="warning"><?php echo cpcc_ui_english_label('No customer selected.', 'لا يوجد عميل محدد.'); ?></div><?php }else{ ?>
 <section class="cpcc-quotation-summary" aria-label="Quotation totals by original currency">
  <?php if(empty($totals)){ ?><div class="cpcc-empty"><?php echo cpcc_ui_english_label('No validated quotations.', 'لا توجد عروض أسعار معتمدة.'); ?></div><?php } ?>
  <?php foreach($totals as $code=>$values){ ?><article><span><?php echo dol_escape_htmltag($code); ?></span><strong><?php echo cpcc_portal_money($values['total']); ?> <?php echo dol_escape_htmltag($code); ?></strong><small><?php echo (int)$values['count']; ?> <?php echo cpcc_ui_english_label('quotations', 'عروض / quotations'); ?></small></article><?php } ?>
 </section>
 <div class="cpcc-table-wrap"><table class="cpcc-table cpcc-quotations-table"><thead><tr><th><?php echo cpcc_ui_english_label('Quotation', 'العرض'); ?></th><th><?php echo cpcc_ui_english_label('Project', 'المشروع'); ?></th><th><?php echo cpcc_ui_english_label('Date', 'التاريخ'); ?></th><th><?php echo cpcc_ui_english_label('Valid until', 'صالح حتى'); ?></th><th><?php echo cpcc_ui_english_label('Total', 'الإجمالي'); ?></th><th><?php echo cpcc_ui_english_label('Status', 'الحالة'); ?></th><th><?php echo cpcc_ui_english_label('Action', 'الإجراء'); ?></th></tr></thead><tbody>
 <?php if(empty($quotations)){ ?><tr><td colspan="7"><?php echo cpcc_ui_english_label('No validated quotations.', 'لا توجد عروض أسعار معتمدة.'); ?></td></tr><?php } ?>
 <?php foreach($quotations as $quotation){$url=cpcc_portal_scoped_url('/chinaplanetcustomercenter/quotation.php',$isExternal,$socid,array('id'=>$quotation['id']));?><tr><td><a href="<?php echo dol_escape_htmltag($url); ?>"><b><?php echo dol_escape_htmltag($quotation['ref']); ?></b></a></td><td><?php echo dol_escape_htmltag($quotation['project_ref']?:'—'); ?></td><td><?php echo !empty($quotation['date'])?dol_escape_htmltag(dol_print_date($quotation['date'],'day')):'—'; ?></td><td><?php echo !empty($quotation['valid_until'])?dol_escape_htmltag(dol_print_date($quotation['valid_until'],'day')):'—'; ?></td><td><?php echo cpcc_portal_money($quotation['total']).' '.dol_escape_htmltag($quotation['currency']); ?></td><td><?php echo dol_escape_htmltag(cpcc_customer_quotation_status($quotation['status'])); ?></td><td><a class="cpcc-quotation-open" href="<?php echo dol_escape_htmltag($url); ?><?php echo cpcc_ui_english_label('">Open', '">فتح / Open'); ?></a></td></tr><?php } ?>
 </tbody></table></div><?php } ?>
</main>
<?php if(!$isExternal)cpcc_admin_shell_close();llxFooter();
