<?php
define('CSRFCHECK_WITH_TOKEN', 1);
require $_SERVER['DOCUMENT_ROOT'].'/main.inc.php';
require_once __DIR__.'/../lib/main.lib.php';
require_once __DIR__.'/../class/OperationsSopService.class.php';
cpo_require($user, 'write');
$langs->load('chinaplanetoperations@chinaplanetoperations');
$contentLang = cpo_content_language();
$service = new OperationsSopService($db);
$id = GETPOSTINT('id');
$action = GETPOST('action','aZ09');
$contextFields = array('ref','fk_category','country_code','pol','pod','shipping_line','service_type','container_type','incoterm','cargo_type','document_codes','effective_date');
$translationFields = array('title','applicability','required_information','procedure_steps','required_documents','warnings','escalation_instructions','internal_notes','responsible_department','change_summary');
$data = array(); foreach($contextFields as $field) $data[$field] = GETPOST($field, $field==='fk_category'?'int':'alphanohtml');
$translations = array();
$rawTranslations = GETPOST('tr','array');
foreach(OperationsSopService::supportedLanguages() as $language=>$meta) {
    $translations[$language] = array();
    foreach($translationFields as $field) $translations[$language][$field] = trim(strip_tags((string)($rawTranslations[$language][$field] ?? '')));
}
if ($action === 'save') {
    try {
        if ($id > 0) $service->saveDraft($id, $data, $translations, (int)$user->id); else $id = $service->create($data, $translations, (int)$user->id);
        setEventMessages($langs->trans('SaveDraft').'. '.$langs->trans('TranslationPublishNotice'), null, 'mesgs');
        header('Location: view.php?id='.$id.'&content_lang='.urlencode($contentLang)); exit;
    } catch(Throwable $e) { setEventMessages($e->getMessage(), null, 'errors'); }
}
$sop = $id > 0 ? $service->get($id, $contentLang, true) : null;
if ($id > 0 && !$sop) accessforbidden();
if ($sop && $action !== 'save') {
    foreach($contextFields as $field) $data[$field] = $sop[$field] ?? '';
    $data['ref'] = $sop['ref'];
    foreach(OperationsSopService::supportedLanguages() as $language=>$meta) {
        $source = $sop['translations'][$language] ?? array();
        foreach($translationFields as $field) {
            $value = $source[$field] ?? '';
            if (in_array($field,array('required_information','procedure_steps','required_documents','warnings'),true)) $value = implode("\n",OperationsSopService::decodeList($value));
            $translations[$language][$field] = $value;
        }
    }
}
$categories = $service->getCategories($contentLang, true);
$value = function($field,$default='')use($data){return dol_escape_htmltag(isset($data[$field])&&$data[$field]!==''?$data[$field]:$default);};
$morecss = array(cpo_asset('/custom/chinaplanetoperations/css/operations.css'));
llxHeader('', $id ? $langs->trans('EditNewRevision') : $langs->trans('NewSop'), '', '', 0, 0, array(), $morecss);
?>
<main class="cpo-page"><section class="cpo-hero"><div><span class="cpo-eyebrow">CONTROLLED SOP AUTHORING</span><h1><?php echo $id?$langs->trans('EditNewRevision'):$langs->trans('NewSop'); ?></h1><p><?php echo $langs->trans('TranslationPublishNotice'); ?></p></div><div class="cpo-actions"><a class="button" href="<?php echo $id?'view.php?id='.$id.'&content_lang='.urlencode($contentLang):'index.php?content_lang='.urlencode($contentLang); ?>"><?php echo $langs->trans('BackToLibrary'); ?></a></div></section>
<form method="post"><input type="hidden" name="token" value="<?php echo newToken(); ?>"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?php echo (int)$id; ?>"><input type="hidden" name="content_lang" value="<?php echo dol_escape_htmltag($contentLang); ?>">
<section class="cpo-form-section"><h2><?php echo $langs->trans('Scope'); ?></h2><div class="cpo-context-grid">
<label class="cpo-field"><span><?php echo $langs->trans('Reference'); ?> *</span><input required maxlength="64" name="ref" value="<?php echo $value('ref'); ?>"<?php echo $id?' readonly':''; ?> placeholder="SOP-OPS-001"></label>
<label class="cpo-field"><span><?php echo $langs->trans('Category'); ?></span><select name="fk_category"><option value=""><?php echo $langs->trans('Uncategorized'); ?></option><?php foreach($categories as $category){ ?><option value="<?php echo (int)$category['rowid']; ?>"<?php echo (int)($data['fk_category']??0)===(int)$category['rowid']?' selected':''; ?>><?php echo dol_escape_htmltag($category['label']); ?><?php echo (int)$category['status']?'':' ('.$langs->trans('Archived').')'; ?></option><?php } ?></select></label>
<?php $simple=function($name,$label,$placeholder='')use($value){ ?><label class="cpo-field"><span><?php echo dol_escape_htmltag($label); ?></span><input name="<?php echo $name; ?>" value="<?php echo $value($name); ?>" placeholder="<?php echo dol_escape_htmltag($placeholder); ?>"></label><?php }; $simple('country_code',$langs->trans('Country'),'EG / SA / CN'); $simple('pol','POL'); $simple('pod','POD'); $simple('shipping_line',$langs->trans('ShippingLine')); ?>
<label class="cpo-field"><span><?php echo $langs->trans('ServiceType'); ?></span><select name="service_type"><?php foreach(array('all'=>'All','direct'=>'Direct','transit'=>'Transit','online'=>'Online','offline'=>'Offline','online_offline'=>'OnlineOffline') as $key=>$label){ ?><option value="<?php echo $key; ?>"<?php echo ($data['service_type']??'all')===$key?' selected':''; ?>><?php echo $langs->trans($label); ?></option><?php } ?></select></label>
<?php $simple('container_type',$langs->trans('ContainerType')); $simple('incoterm','Incoterm'); $simple('cargo_type',$langs->trans('CargoType')); $simple('document_codes','Document / service codes','cargox, saber'); ?>
<label class="cpo-field"><span><?php echo $langs->trans('EffectiveDate'); ?></span><input type="date" name="effective_date" value="<?php echo $value('effective_date'); ?>"></label>
</div></section>
<section class="cpo-form-section"><h2><?php echo $langs->trans('ContentLanguage'); ?> · AR / EN / ZH</h2><div class="cpo-translation-tabs">
<?php foreach(OperationsSopService::supportedLanguages() as $language=>$meta){ $tr=$translations[$language]; $dir=$meta['dir']; $tval=function($field)use($tr){return dol_escape_htmltag($tr[$field]??'');}; ?>
<section class="cpo-translation-pane" dir="<?php echo $dir; ?>"><h3><span><?php echo dol_escape_htmltag($meta['label']); ?></span><small><?php echo $meta['short']; ?></small></h3>
<label class="cpo-field"><span><?php echo $langs->trans('Title'); ?><?php echo $language==='en_US'?' *':''; ?></span><input name="tr[<?php echo $language; ?>][title]" value="<?php echo $tval('title'); ?>"<?php echo $language==='en_US'?' required':''; ?>></label>
<label class="cpo-field"><span><?php echo $langs->trans('WhenApplies'); ?></span><textarea name="tr[<?php echo $language; ?>][applicability]" rows="3"><?php echo $tval('applicability'); ?></textarea></label>
<?php foreach(array('required_information'=>'RequiredInformation','procedure_steps'=>'ProcedureSteps','required_documents'=>'RequiredDocuments','warnings'=>'ImportantWarnings') as $field=>$label){ ?><label class="cpo-field"><span><?php echo $langs->trans($label); ?></span><small><?php echo $langs->trans('OneItemPerLine'); ?></small><textarea name="tr[<?php echo $language; ?>][<?php echo $field; ?>]" rows="<?php echo $field==='procedure_steps'?8:4; ?>"><?php echo $tval($field); ?></textarea></label><?php } ?>
<label class="cpo-field"><span><?php echo $langs->trans('EscalationInstructions'); ?></span><textarea name="tr[<?php echo $language; ?>][escalation_instructions]" rows="4"><?php echo $tval('escalation_instructions'); ?></textarea></label>
<label class="cpo-field"><span><?php echo $langs->trans('InternalNotes'); ?></span><textarea name="tr[<?php echo $language; ?>][internal_notes]" rows="3"><?php echo $tval('internal_notes'); ?></textarea></label>
<label class="cpo-field"><span><?php echo $langs->trans('Department'); ?></span><input name="tr[<?php echo $language; ?>][responsible_department]" value="<?php echo $tval('responsible_department'); ?>"></label>
<label class="cpo-field"><span><?php echo $langs->trans('ChangeSummary'); ?></span><input name="tr[<?php echo $language; ?>][change_summary]" maxlength="512" value="<?php echo $tval('change_summary'); ?>"></label>
</section><?php } ?>
</div><div class="cpo-form-actions"><button class="button button-save"><?php echo $langs->trans('SaveDraft'); ?></button><a class="button" href="<?php echo $id?'view.php?id='.$id:'index.php'; ?>"><?php echo $langs->trans('BackToLibrary'); ?></a></div></section></form></main>
<?php llxFooter(); $db->close(); ?>
