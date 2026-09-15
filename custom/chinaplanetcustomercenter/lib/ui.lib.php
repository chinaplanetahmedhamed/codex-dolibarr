<?php
/** Shared presentation only: no access, data or saved account-language changes. */
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
