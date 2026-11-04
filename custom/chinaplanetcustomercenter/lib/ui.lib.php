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

/** Escaped, allowlisted current-position presentation; never expose evidence IDs or private reasons. */
function cpcc_ui_funds_label($key)
{
    global $langs;
    $langs->load('chinaplanetcustomercenter@chinaplanetcustomercenter');
    return dol_escape_htmltag($langs->trans($key));
}
function cpcc_ui_managed_funds(array $position, array $options = array())
{
    $label = function ($key) { return cpcc_ui_funds_label($key); };
    if (!empty($options['asof']) && $options['asof'] !== date('Y-m-d')) {
        return '<p class="cpcc-note">'.$label('CPCCFundsCurrentOnly').'</p>';
    }
    $project = isset($position['scope']) && $position['scope'] === 'project_allocations_only';
    $fields = $project ? array('assigned'=>'CPCCFundsAssigned','reserved'=>'CPCCFundsReserved','paid_cash'=>'CPCCFundsPaid')
        : array('received_cash'=>'CPCCFundsReceived','paid_cash'=>'CPCCFundsPaid','reserved'=>'CPCCFundsReserved','available_to_allocate'=>'CPCCFundsAvailable');
    $currencies = isset($position['currencies']) && is_array($position['currencies']) ? $position['currencies'] : array();
    if (!empty($options['currency'])) $currencies = array_intersect_key($currencies, array($options['currency']=>true));
    ksort($currencies);
    foreach (array('credit_consumed'=>'CPCCFundsApplied','other_settled'=>'CPCCFundsOtherDebits') as $field=>$key) {
        foreach ($currencies as $bucket) if (isset($bucket[$field]) && (float) $bucket[$field] > 0.00000001) { $fields[$field]=$key; break; }
    }
    $html = '<section class="cpcc-managed-funds" dir="'.(cpcc_ui_language()==='ar_SA'?'rtl':'ltr').'" aria-label="'.$label('CPCCFundsCurrent').'">';
    $html .= '<h2>'.$label($project?'CPCCFundsProject':'CPCCFundsCurrent').'</h2><p class="cpcc-note">'.$label($project?'CPCCFundsProjectScope':'CPCCFundsSubset').'</p>';
    $coverage = isset($position['coverage']) ? $position['coverage'] : 'unavailable';
    if ($coverage !== 'verified_managed') $html .= '<p class="warning">'.$label($coverage==='not_managed'?'CPCCFundsNoVerified':'CPCCFundsReview').'</p>';
    if (!$currencies) $html .= '<p>'.$label('CPCCFundsUnknown').'</p>';
    else {
        $html .= '<div class="cpcc-table-wrap div-table-responsive"><table class="cpcc-table noborder centpercent"><thead><tr><th>'.$label('CPCCFundsCurrency').'</th>';
        foreach ($fields as $field=>$key) $html .= '<th data-fund-field="'.$field.'">'.$label($key).'</th>';
        $html .= '</tr></thead><tbody>';
        foreach ($currencies as $currency=>$bucket) {
            $html .= '<tr data-fund-currency="'.dol_escape_htmltag($currency).'"><th scope="row">'.dol_escape_htmltag($currency).'</th>';
            foreach ($fields as $field=>$key) {
                $value = array_key_exists($field, $bucket) ? $bucket[$field] : null;
                $known = $value !== null && is_numeric($value) && is_finite((float) $value);
                $text = $known ? dol_escape_htmltag(number_format((float)$value, 2, '.', ',')) : $label('CPCCFundsUnknown');
                $html .= '<td data-label="'.$label($key).'" data-fund-field="'.$field.'">'.$text.'</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table></div>';
    }
    if ($project && !empty($options['customer_url']) && strpos($options['customer_url'], '/') === 0 && strpos($options['customer_url'], '//') !== 0) {
        $html .= '<p><a href="'.dol_escape_htmltag($options['customer_url']).'">'.$label('CPCCFundsCustomerPool').'</a></p>';
    }
    return $html.'</section>';
}
