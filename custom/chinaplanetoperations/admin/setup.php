<?php
define('CSRFCHECK_WITH_TOKEN', 1);
require $_SERVER['DOCUMENT_ROOT'].'/main.inc.php';
require_once __DIR__.'/../lib/main.lib.php';
require_once __DIR__.'/../class/OperationsSopService.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/custom/chinaplanetcustomercenter/lib/admin_shell.lib.php';
cpo_require($user, 'translation');
$langs->load('chinaplanetoperations@chinaplanetoperations');
$action = GETPOST('action', 'aZ09');
if ($action === 'save') {
    $default = GETPOST('default_lang', 'alphanohtml');
    $required = GETPOST('required_langs', 'array');
    $supported = array_keys(OperationsSopService::supportedLanguages());
    if (!in_array($default, $supported, true)) $default = 'en_US';
    $required = array_values(array_intersect($supported, (array)$required));
    if (!$required) $required = array('en_US');
    dolibarr_set_const($db, 'CHINAPLANETOPERATIONS_DEFAULT_CONTENT_LANG', $default, 'chaine', 0, '', $conf->entity);
    dolibarr_set_const($db, 'CHINAPLANETOPERATIONS_REQUIRED_LANGS', implode(',', $required), 'chaine', 0, '', $conf->entity);
    setEventMessages($langs->trans('SettingsSaved'), null, 'mesgs');
}
$default = !empty($conf->global->CHINAPLANETOPERATIONS_DEFAULT_CONTENT_LANG) ? $conf->global->CHINAPLANETOPERATIONS_DEFAULT_CONTENT_LANG : 'en_US';
$required = explode(',', !empty($conf->global->CHINAPLANETOPERATIONS_REQUIRED_LANGS) ? $conf->global->CHINAPLANETOPERATIONS_REQUIRED_LANGS : 'en_US,ar_SA,zh_CN');
$morecss = array(cpo_asset('/custom/chinaplanetoperations/css/operations.css'),cpcc_admin_shell_asset('/custom/chinaplanetcustomercenter/css/admin_dashboard.css'));
llxHeader('', $langs->trans('LanguageSettings'), '', '', 0, 0, array(), $morecss);
cpcc_admin_shell_open('sop',$langs->trans('LanguageSettings'),'Arabic · English · 中文','fa-language');
?>
<main class="cpo-page"><section class="cpo-hero"><div><span class="cpo-eyebrow">OPERATIONS CONFIGURATION</span><h1><?php echo $langs->trans('LanguageSettings'); ?></h1><p>Arabic · English · 中文</p></div><div class="cpo-actions"><a class="button" href="../index.php"><?php echo $langs->trans('OperationsCenter'); ?></a></div></section>
<section class="cpo-form-section"><form method="post"><input type="hidden" name="token" value="<?php echo newToken(); ?>"><input type="hidden" name="action" value="save"><div class="cpo-context-grid"><label class="cpo-field"><span><?php echo $langs->trans('DefaultContentLanguage'); ?></span><select name="default_lang"><?php foreach(OperationsSopService::supportedLanguages() as $code=>$meta){ ?><option value="<?php echo $code; ?>"<?php echo $default===$code?' selected':''; ?>><?php echo dol_escape_htmltag($meta['label']); ?></option><?php } ?></select></label><div class="cpo-field"><span><?php echo $langs->trans('RequiredPublicationLanguages'); ?></span><?php foreach(OperationsSopService::supportedLanguages() as $code=>$meta){ ?><label><input type="checkbox" name="required_langs[]" value="<?php echo $code; ?>"<?php echo in_array($code,$required,true)?' checked':''; ?>> <?php echo dol_escape_htmltag($meta['label']); ?></label><?php } ?></div></div><div class="cpo-form-actions"><button class="button button-save"><?php echo $langs->trans('Save'); ?></button></div></form></section></main>
<?php cpcc_admin_shell_close();llxFooter(); $db->close(); ?>
