<?php
/** Shared presentation only: no access, data or saved account-language changes. */
/** English is the only enabled internal interface language in this release. */
function cpcc_ui_internal_english()
{
    global $user;
    return !empty($user->id) && empty($user->socid);
}
function cpcc_ui_english_label($english, $existing)
{
    return cpcc_ui_internal_english() ? $english : $existing;
}
function cpcc_ui_initialize_language()
{
    global $langs, $conf;
    if (!cpcc_ui_internal_english() || empty($langs) || $langs->defaultlang === 'en_US') return;
    // A new request-scoped translator prevents already-loaded Arabic strings
    // surviving setDefaultLang(). Do not write the user's saved language.
    $keyboardLabel = $langs->trans('KeyboardShortcut');
    $english = new Translate('', $conf);
    $english->setDefaultLang('en_US');
    // Match the normal bootstrap. Each page loads its own domains afterwards;
    // preloading unrelated modules changes the precedence of shared keys.
    $english->loadLangs(array('main', 'dict'));
    if (!empty($conf->browser->stringforfirstkey)) {
        $conf->browser->stringforfirstkey = str_replace($keyboardLabel,
            $english->trans('KeyboardShortcut'), $conf->browser->stringforfirstkey);
    }
    $langs = $english;
}
function cpcc_ui_language()
{
    global $langs;
    return !empty($langs->defaultlang) && preg_match('/^ar(?:_|$)/i', $langs->defaultlang) ? 'ar_SA' : 'en_US';
}
function cpcc_ui_t($key)
{
    global $langs;
    $langs->load('m084_ui@chinaplanetcustomercenter');
    return $langs->trans($key);
}
function cpcc_ui_language_parameter()
{
    if (cpcc_ui_internal_english()) return 'en_US';
    $selected = isset($_GET['lang']) && is_string($_GET['lang']) ? $_GET['lang'] : '';
    return in_array($selected, array('ar_SA', 'en_US'), true) ? $selected : '';
}
function cpcc_ui_assets()
{
    static $printed = false;
    if ($printed) return;
    $printed = true;
    foreach (array('css/ui_accessibility.css', 'js/ui_accessibility.js') as $asset) {
        $path = '/custom/chinaplanetcustomercenter/'.$asset;
        $version = substr(hash_file('sha256', __DIR__.'/../'.$asset), 0, 12);
        $url = dol_escape_htmltag(dol_buildpath($path, 1).'?v='.$version);
        print substr($asset, -4) === '.css' ? '<link rel="stylesheet" href="'.$url.'">' : '<script src="'.$url.'" defer></script>';
    }
}

function cpcc_ui_language_controls()
{
    if (!cpcc_ui_internal_english()) return;
    print '<div class="cpad-language-control" data-interface-language="en_US">';
    print '<select class="flat" aria-label="Interface language" autocomplete="off">';
    print '<option value="en_US" lang="en" selected>English</option>';
    print '<option value="ar_SA" lang="ar" disabled>العربية</option>';
    print '<option value="zh_CN" lang="zh" disabled>中文</option>';
    print '</select></div>';
}
