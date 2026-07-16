<?php

function cpo_can($user, $right = 'read')
{
    return !empty($user->id) && empty($user->socid) && (!empty($user->admin) || $user->hasRight('chinaplanetoperations', $right));
}

function cpo_require($user, $right = 'read')
{
    if (!cpo_can($user, $right)) accessforbidden();
}

function cpo_content_language()
{
    require_once __DIR__.'/../class/OperationsSopService.class.php';
    $requested = GETPOST('content_lang', 'alphanohtml');
    if ($requested === '' && !empty($_SESSION['cpo_content_lang'])) $requested = $_SESSION['cpo_content_lang'];
    if ($requested === '' && !empty($GLOBALS['langs']->defaultlang)) $requested = $GLOBALS['langs']->defaultlang;
    $language = OperationsSopService::normalizeLanguage($requested);
    $_SESSION['cpo_content_lang'] = $language;
    return $language;
}

function cpo_language_switcher($current, array $query = array())
{
    require_once __DIR__.'/../class/OperationsSopService.class.php';
    $out = '<nav class="cpo-language-switch" aria-label="Content language">';
    foreach (OperationsSopService::supportedLanguages() as $code=>$meta) {
        $parameters = array_merge($query, array('content_lang'=>$code));
        $class = $code === $current ? ' class="active" aria-current="true"' : '';
        $out .= '<a'.$class.' href="?'.dol_escape_htmltag(http_build_query($parameters)).'">'.dol_escape_htmltag($meta['short']).'<span>'.dol_escape_htmltag($meta['label']).'</span></a>';
    }
    return $out.'</nav>';
}

function cpo_asset($path)
{
    $full = DOL_DOCUMENT_ROOT.$path;
    return $path.'?v='.rawurlencode(is_file($full) ? (string) filemtime($full) : '1');
}
