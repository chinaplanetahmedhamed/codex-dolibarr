<?php
require $_SERVER['DOCUMENT_ROOT'].'/main.inc.php';
require_once __DIR__.'/../lib/main.lib.php';
require_once __DIR__.'/../class/OperationsSopService.class.php';
require_once __DIR__.'/../../chinaplanetcustomercenter/lib/admin_shell.lib.php';
cpo_require($user, 'read');
$langs->load('chinaplanetoperations@chinaplanetoperations');
$contentLang = cpo_content_language();
$service = new OperationsSopService($db);
$canWrite = cpo_can($user, 'write');
$canPublish = cpo_can($user, 'publish');
$filters = array(
    'q'=>GETPOST('q','alphanohtml'), 'category'=>GETPOSTINT('category'),
    'country_code'=>strtoupper(GETPOST('country_code','alpha')), 'pol'=>GETPOST('pol','alphanohtml'),
    'pod'=>GETPOST('pod','alphanohtml'), 'shipping_line'=>GETPOST('shipping_line','alphanohtml'),
    'cargo_type'=>GETPOST('cargo_type','alphanohtml'), 'status'=>GETPOST('status','int'),
);
$includeNonActive = $canWrite || $canPublish;
$rows = $service->search($filters, $contentLang, $includeNonActive);
$categories = $service->getCategories($contentLang, $includeNonActive);
$stats = $service->getStats();
$statusLabels = array(-1=>'Deleted',0=>'Draft',1=>'Active',9=>'Archived');
$morecss = array(
    cpo_asset('/custom/chinaplanetoperations/css/operations.css'),
    cpcc_admin_shell_asset('/custom/chinaplanetcustomercenter/css/admin_dashboard.css'),
);
llxHeader('', $langs->trans('SopLibrary'), '', '', 0, 0, array(), $morecss);
cpcc_admin_shell_open('sop', 'Multilingual SOP Center / مكتبة SOP متعددة اللغات', 'تحكم مركزي بمحتوى التشغيل والعمليات المتعدد اللغات', 'fa-book-open');
?>
<main class="cpo-page">
<section class="cpo-hero"><div><span class="cpo-eyebrow">CONTROLLED MULTILINGUAL LIBRARY</span><h1><?php echo $langs->trans('MultilingualSopCenter'); ?></h1><p><?php echo $langs->trans('TranslationPublishNotice'); ?></p></div><div><?php echo cpo_language_switcher($contentLang, array_filter($filters, function($value){return $value!==''&&$value!==0;})); ?><div class="cpo-actions" style="margin-top:8px"><?php if($canWrite){ ?><a class="button" href="edit.php?content_lang=<?php echo urlencode($contentLang); ?>"><?php echo $langs->trans('NewSop'); ?></a><?php } ?><a class="button" href="../index.php"><?php echo $langs->trans('OperationsCenter'); ?></a></div></div></section>
<section class="cpo-stats"><div class="cpo-stat"><span><?php echo $langs->trans('TotalSops'); ?></span><strong><?php echo (int)$stats['total']; ?></strong></div><div class="cpo-stat good"><span><?php echo $langs->trans('ActiveSops'); ?></span><strong><?php echo (int)$stats['active_total']; ?></strong></div><div class="cpo-stat warn"><span><?php echo $langs->trans('DraftSops'); ?></span><strong><?php echo (int)$stats['draft_total']; ?></strong></div><div class="cpo-stat"><span><?php echo $langs->trans('ArchivedSops'); ?></span><strong><?php echo (int)$stats['archived_total']; ?></strong></div></section>
<section class="cpo-panel"><div class="cpo-panel-head"><h2><?php echo $langs->trans('SearchSops'); ?></h2><?php if(cpo_can($user,'category')){ ?><a href="categories.php?content_lang=<?php echo urlencode($contentLang); ?>"><?php echo $langs->trans('ManageCategories'); ?></a><?php } ?></div><form method="get"><input type="hidden" name="content_lang" value="<?php echo dol_escape_htmltag($contentLang); ?>"><div class="cpo-filters">
<?php $input=function($name,$label,$value,$placeholder=''){ ?><label class="cpo-field"><span><?php echo dol_escape_htmltag($label); ?></span><input name="<?php echo $name; ?>" value="<?php echo dol_escape_htmltag($value); ?>" placeholder="<?php echo dol_escape_htmltag($placeholder); ?>"></label><?php }; $input('q',$langs->trans('Search'),$filters['q'],'SOP, CargoX, QC...'); ?>
<label class="cpo-field"><span><?php echo $langs->trans('Category'); ?></span><select name="category"><option value=""><?php echo $langs->trans('All'); ?></option><?php foreach($categories as $category){ ?><option value="<?php echo (int)$category['rowid']; ?>"<?php echo (int)$filters['category']===(int)$category['rowid']?' selected':''; ?>><?php echo dol_escape_htmltag($category['label']); ?></option><?php } ?></select></label>
<?php $input('country_code',$langs->trans('Country'),$filters['country_code'],'EG / SA / CN'); $input('pol','POL',$filters['pol']); $input('pod','POD',$filters['pod']); $input('shipping_line',$langs->trans('ShippingLine'),$filters['shipping_line']); $input('cargo_type',$langs->trans('CargoType'),$filters['cargo_type']); ?>
<?php if($includeNonActive){ ?><label class="cpo-field"><span><?php echo $langs->trans('Status'); ?></span><select name="status"><option value=""><?php echo $langs->trans('All'); ?></option><?php foreach($statusLabels as $key=>$label){ ?><option value="<?php echo $key; ?>"<?php echo (string)$filters['status']===(string)$key?' selected':''; ?>><?php echo $langs->trans($label); ?></option><?php } ?></select></label><?php } ?>
</div><div class="cpo-form-actions"><button class="button button-save"><?php echo $langs->trans('Search'); ?></button><a class="button" href="index.php?content_lang=<?php echo urlencode($contentLang); ?>"><?php echo $langs->trans('Reset'); ?></a></div></form></section>
<section class="cpo-panel"><div class="cpo-panel-head"><h2><?php echo $langs->trans('SopLibrary'); ?> <span class="badge badge-status0"><?php echo count($rows); ?></span></h2></div>
<?php if(!$rows){ ?><div class="info"><?php echo $langs->trans('NoSopMatches'); ?></div><?php } ?>
<?php foreach($rows as $row){ $scope=array_filter(array($row['country_code']?:$langs->trans('All'),$row['pol']?'POL '.$row['pol']:'',$row['pod']?'POD '.$row['pod']:'',$row['shipping_line']?:'',$row['incoterm']?:'',$row['cargo_type']?:'')); ?>
<article class="cpo-card"><div class="cpo-card-main"><div class="cpo-card-meta"><span class="cpo-badge"><?php echo dol_escape_htmltag($row['ref']); ?></span><span class="cpo-badge status-<?php echo (int)$row['status']; ?>"><?php echo $langs->trans($statusLabels[(int)$row['status']]??'Status'); ?></span><span><?php echo $langs->trans('Revision').' '.(int)$row['revision_no']; ?></span></div><h3><a href="view.php?id=<?php echo (int)$row['sop_id']; ?>&content_lang=<?php echo urlencode($contentLang); ?>"><?php echo dol_escape_htmltag($row['title']); ?></a></h3><p><?php echo dol_escape_htmltag($row['applicability']?:$langs->trans('GeneralProcedure')); ?></p><small><?php echo dol_escape_htmltag(implode(' · ',$scope)); ?></small></div><aside class="cpo-card-side"><strong><?php echo dol_escape_htmltag($row['category_label']?:$langs->trans('Uncategorized')); ?></strong><small><?php echo dol_escape_htmltag($row['responsible_department']?:'—'); ?></small><div class="cpo-translation-badges"><span class="cpo-badge <?php echo (int)$row['approved_languages']>=3?'cpo-lang-ready':'cpo-lang-review'; ?>">AR · EN · ZH <?php echo (int)$row['approved_languages']; ?>/3</span></div><a class="button smallpaddingimp" href="view.php?id=<?php echo (int)$row['sop_id']; ?>&content_lang=<?php echo urlencode($contentLang); ?>"><?php echo $langs->trans('Open'); ?></a></aside></article>
<?php } ?></section></main>
<?php cpcc_admin_shell_close(); llxFooter(); $db->close(); ?>
