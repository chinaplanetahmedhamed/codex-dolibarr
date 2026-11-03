<?php
require_once __DIR__.'/lib/ui.lib.php';
if (!isset($_SERVER['QUERY_STRING'])) $_SERVER['QUERY_STRING'] = '';
require_once __DIR__.'/lib/main.lib.php';
require_once __DIR__.'/lib/admin_shell.lib.php';
require_once __DIR__.'/lib/portal.lib.php';
require_once __DIR__.'/lib/financial.lib.php';
cpcc_portal_require_access($user);
$scope=cpcc_portal_user_scope($user);
$isExternal=!empty($scope['is_external']);
$socid=cpcc_portal_customer_scope_id($db,$user,GETPOSTINT('socid'));
$currencyFallback=cpcc_financial_currency(!empty($conf->currency)?$conf->currency:'USD');
$currency=cpcc_financial_currency(GETPOST('currency','alpha'), '');
$view=GETPOST('view','alpha');
$rows=$socid>0?cpcc_financial_statement($db,$socid,$currencyFallback):array();
if ($currency!=='') $rows=array_values(array_filter($rows,function($row)use($currency){return $row['currency']===$currency;}));
if ($view==='received') $rows=array_values(array_filter($rows,function($row){return $row['direction']==='C'&&$row['type']!=='fx_in';}));
if ($view==='spent') $rows=array_values(array_filter($rows,function($row){return $row['direction']==='D'&&$row['type']!=='fx_out';}));
$summary=$socid>0?cpcc_financial_summary($db,$socid,$currencyFallback):array();
$usdTotal=cpcc_financial_total_usd($summary);
$usdRateDate=function_exists('getDolGlobalString')?getDolGlobalString('CHINAPLANETCUSTOMERCENTER_USD_RATE_DATE'):'';
llxHeader('', cpcc_ui_t('UICustomerStatement003b2'), '', '', 0, 0, array(), $isExternal?array('/custom/chinaplanetcustomercenter/css/customer_dashboard.css','/custom/chinaplanetcustomercenter/css/funding.css'):array('/custom/chinaplanetcustomercenter/css/customer_dashboard.css','/custom/chinaplanetcustomercenter/css/funding.css',cpcc_admin_shell_asset('/custom/chinaplanetcustomercenter/css/admin_dashboard.css'),cpcc_admin_shell_asset('/custom/chinaplanetcustomercenter/css/ux_fixes.css')));
if($isExternal) cpcc_portal_navigation($user,'balance'); else cpcc_admin_shell_open('portal',cpcc_ui_t('UICustomerStatement003b2'),cpcc_ui_english_label('Deposits, expenses and balances by currency.', 'كل الإيداعات والمصروفات والأرصدة حسب العملة'),'fa-file-invoice-dollar',array('socid'=>$socid));
?>
<main class="cpcc-dashboard" dir="<?php echo cpcc_ui_english_label('ltr', 'rtl'); ?>"><header class="cpcc-page-head"><div><span class="cpcc-eyebrow">FINANCIAL STATEMENT</span><h1><?php echo cpcc_ui_english_label('Customer statement', 'كشف حساب العميل'); ?></h1><p><?php echo cpcc_ui_english_label('Each balance is calculated in its original currency.', 'كل رصيد محسوب داخل عملته الأصلية.'); ?></p></div><?php if($isExternal){ ?><a class="cpcc-profile-shortcut" href="<?php echo dol_escape_htmltag(dol_buildpath('/chinaplanetcustomercenter/profile.php',1)); ?>"><span class="fas fa-user-circle" aria-hidden="true"></span><span><?php echo cpcc_ui_english_label('Profile', 'الملف الشخصي'); ?><small lang="en" dir="ltr">Profile</small></span></a><?php } ?></header>
<?php cpcc_portal_customer_selector($db,$user,$socid); ?>
<?php if ($socid<=0) { ?><div class="warning"><?php echo dol_escape_htmltag(cpcc_ui_t('UISelectACustomerToViewRecordse2001')); ?></div><?php } else { ?>
<section class="cpcc-usd-total" aria-label="<?php echo cpcc_ui_english_label('Total balance in USD', 'إجمالي الرصيد بالدولار'); ?>">
<div><span>Total Balance in USD</span><h2><?php echo cpcc_portal_money($usdTotal['total']); ?> <small>USD</small></h2><p><?php echo cpcc_ui_english_label('Total balance in US dollars', 'إجمالي الرصيد بالدولار الأمريكي'); ?></p></div>
<div class="cpcc-usd-breakdown">
<?php foreach($usdTotal['converted'] as $code=>$usdValue){ $available=$summary[$code]['available']; $rate=$usdTotal['rates'][$code]; ?>
<span><b><?php echo dol_escape_htmltag($code); ?></b> <?php echo cpcc_portal_money($available); ?> <em>→</em> <?php echo cpcc_portal_money($usdValue); ?> USD<?php if($code!=='USD'){ ?><small>1 USD = <?php echo dol_escape_htmltag((string)$rate).' '.dol_escape_htmltag($code); ?></small><?php } ?></span>
<?php } ?>
<?php if(!empty($usdTotal['missing'])){ ?><span class="cpcc-rate-warning"><?php echo cpcc_ui_english_label('Excluded because no valuation rate is available:', 'غير مشمول لعدم وجود سعر تقييم:'); ?> <?php echo dol_escape_htmltag(implode(', ',$usdTotal['missing'])); ?></span><?php } ?>
<?php if($usdRateDate!==''){ ?><span class="cpcc-rate-date"><?php echo cpcc_ui_english_label('Valuation rates dated:', 'تاريخ أسعار التقييم:'); ?> <?php echo dol_escape_htmltag(cpcc_portal_date($usdRateDate,'day')); ?></span><?php } ?>
</div>
</section>
<div class="cpcc-balance-pills"><?php $scopeQuery=empty($scope['is_external'])?'socid='.(int)$socid.'&':''; foreach($summary as $code=>$totals){ ?><a class="<?php echo $currency===$code?'active':''; ?>" href="?<?php echo $scopeQuery; ?>currency=<?php echo urlencode($code); ?>"><b><?php echo dol_escape_htmltag($code); ?></b><span><?php echo cpcc_portal_money($totals['available']); ?></span></a><?php } ?><a href="?<?php echo rtrim($scopeQuery,'&'); ?><?php echo cpcc_ui_english_label('">All currencies', '">كل العملات'); ?></a></div>
<section class="cpcc-section-head"><div><h2><?php echo cpcc_ui_english_label('Required funding by currency', 'التمويل المطلوب حسب العملة'); ?></h2><p><?php echo cpcc_ui_english_label('Supplier remaining + approved services + unpaid invoices − available balance.', 'المتبقي للموردين + الخدمات المعتمدة + الفواتير غير المدفوعة − الرصيد المتاح.'); ?></p></div><a href="<?php echo dol_escape_htmltag(cpcc_portal_scoped_url('/chinaplanetcustomercenter/funding.php',!empty($scope['is_external']),(int)$socid)); ?><?php echo cpcc_ui_english_label('">View details →', '">عرض التفاصيل ←'); ?></a></section>
<?php foreach($summary as $code=>$totals){ if($currency!==''&&$currency!==$code)continue; ?>
<section class="cpcc-currency-block"><h2><?php echo dol_escape_htmltag($code); ?></h2><div class="cpcc-financial-cards cpcc-funding-cards">
<div class="cpcc-card obligations"><span><?php echo cpcc_ui_english_label('Suppliers', 'الموردون'); ?></span><strong><?php echo cpcc_portal_money($totals['supplier_remaining']); ?> <em><?php echo dol_escape_htmltag($code); ?></em></strong><small>Supplier balances</small></div>
<div class="cpcc-card services"><span><?php echo cpcc_ui_english_label('Expected services', 'الخدمات المتوقعة'); ?></span><strong><?php echo cpcc_portal_money($totals['future_services']); ?> <em><?php echo dol_escape_htmltag($code); ?></em></strong><small>Approved future services</small></div>
<div class="cpcc-card invoices"><span><?php echo cpcc_ui_english_label('Unpaid invoices', 'فواتير غير مدفوعة'); ?></span><strong><?php echo cpcc_portal_money($totals['unpaid_invoices']); ?> <em><?php echo dol_escape_htmltag($code); ?></em></strong><small>Unpaid Vision invoices</small></div>
<div class="cpcc-card required"><span><?php echo cpcc_ui_english_label('Additional funding required', 'المبلغ الإضافي المطلوب'); ?></span><strong><?php echo cpcc_portal_money($totals['required_funding']); ?> <em><?php echo dol_escape_htmltag($code); ?></em></strong><small>Additional funds required</small></div>
</div></section>
<?php } ?>
<div class="cpcc-table-wrap"><table class="cpcc-table statement"><thead><tr><th><?php echo dol_escape_htmltag(cpcc_ui_t('UIDateeb9a4')); ?></th><th><?php echo cpcc_ui_english_label('Transaction type', 'نوع الحركة'); ?></th><th><?php echo cpcc_ui_english_label('Description / reference', 'الوصف / المرجع'); ?></th><th><?php echo cpcc_ui_english_label('Debit', 'مدين'); ?></th><th><?php echo cpcc_ui_english_label('Credit', 'دائن'); ?></th><th><?php echo cpcc_ui_english_label('Balance', 'الرصيد'); ?></th><th><?php echo cpcc_ui_english_label('Currency / conversion', 'العملة/التحويل'); ?></th></tr></thead><tbody>
<?php if(empty($rows)){ ?><tr><td colspan="7"><?php echo cpcc_ui_english_label('No matching transactions.', 'لا توجد حركات مطابقة.'); ?></td></tr><?php } ?>
<?php foreach($rows as $row){ ?><tr><td><?php echo dol_escape_htmltag(cpcc_portal_date($row['date'],'day')); ?></td><td><?php echo dol_escape_htmltag(cpcc_financial_type_label($row['type'])); ?></td><td><?php echo dol_escape_htmltag($row['description']); ?><?php if($row['reference']!==''){ ?><small><?php echo dol_escape_htmltag($row['reference']); ?></small><?php } ?></td><td class="money debit"><?php echo $row['direction']==='D'?cpcc_portal_money($row['amount']):'—'; ?></td><td class="money credit"><?php echo $row['direction']==='C'?cpcc_portal_money($row['amount']):'—'; ?></td><td class="money"><b><?php echo cpcc_portal_money($row['balance']); ?></b></td><td><b><?php echo dol_escape_htmltag($row['currency']); ?></b><?php if(!empty($row['exchange_rate'])){ ?><small>Rate: <?php echo dol_escape_htmltag((string)$row['exchange_rate']); ?></small><?php } ?><?php if(!empty($row['allocated_currency'])){ ?><small>Allocated: <?php echo cpcc_portal_money($row['allocated_amount']).' '.dol_escape_htmltag($row['allocated_currency']); ?></small><?php } ?></td></tr><?php } ?>
</tbody></table></div>
<p class="cpcc-note"><?php echo cpcc_ui_english_label('The USD total is a display valuation using the rates above. It does not change original currency balances or recorded conversions.', 'إجمالي USD هو قيمة عرض فقط حسب أسعار التقييم الموضحة أعلاه؛ ولا يغيّر أرصدة العملات الأصلية أو حركات التحويل المسجلة.'); ?></p>
<?php } ?></main><?php if(!$isExternal)cpcc_admin_shell_close();llxFooter();
