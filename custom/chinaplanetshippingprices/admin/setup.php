<?php
require $_SERVER['DOCUMENT_ROOT'].'/main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once __DIR__.'/../lib/main.lib.php';
require_once DOL_DOCUMENT_ROOT.'/custom/chinaplanetcustomercenter/lib/admin_shell.lib.php';
chinaplanetsp_require_internal($user, 'admin');
$langs->loadLangs(array('admin', 'chinaplanetshippingprices@chinaplanetshippingprices'));

if (GETPOST('action', 'aZ09') === 'save') {
    $values = array(
        'CHINAPLANETSP_IGNORE_BELOW' => max(0, (float) GETPOST('ignore_below', 'alphanohtml')),
        'CHINAPLANETSP_DEFAULT_POL' => trim(GETPOST('default_pol', 'alphanohtml')) ?: 'Guangzhou',
        'CHINAPLANETSP_MARGIN_20GP' => (float) GETPOST('margin_20gp', 'alphanohtml'),
        'CHINAPLANETSP_MARGIN_40GP' => (float) GETPOST('margin_40gp', 'alphanohtml'),
        'CHINAPLANETSP_MARGIN_40HQ' => (float) GETPOST('margin_40hq', 'alphanohtml'),
        'CHINAPLANETSP_MARGIN_45HQ' => (float) GETPOST('margin_45hq', 'alphanohtml'),
        'CHINAPLANETSP_CURRENCY' => strtoupper(GETPOST('currency', 'alpha')),
        'CHINAPLANETSP_CUSTOMER_HISTORY' => GETPOSTINT('customer_history') ? 1 : 0,
        'CHINAPLANETSP_HISTORY_MONTHS' => max(1, min(60, GETPOSTINT('history_months'))),
    );
    foreach ($values as $key => $value) dolibarr_set_const($db, $key, (string) $value, 'chaine', 0, '', $conf->entity);
    setEventMessages($langs->trans('SetupSaved'), null, 'mesgs');
}

$css=array(cpcc_admin_shell_asset('/custom/chinaplanetcustomercenter/css/admin_dashboard.css'),cpcc_admin_shell_asset('/custom/chinaplanetshippingprices/css/shippingprices.css'));
llxHeader('', 'Shipping Price Settings / إعدادات أسعار الشحن', '', '', 0, 0, array(), $css);
cpcc_admin_shell_open('shippingprices', 'Shipping Price Settings / إعدادات أسعار الشحن', 'إدارة حدود الاستيراد وهوامش الحاويات وخيارات عرض الأسعار للعملاء', 'fa-cogs');
print '<main class="chinaplanetsp-rules-admin chinaplanetsp-settings-page"><div class="chinaplanetsp-admin-toolbar"><a class="button" href="dashboard.php"><span class="fas fa-sliders-h"></span> Control Center / مركز الإدارة</a><a class="button" href="import.php"><span class="fas fa-file-upload"></span> Import Rates / استيراد الأسعار</a></div>';
print '<div class="chinaplanetsp-mapping-help"><span class="fas fa-info-circle"></span><div><strong>Global pricing controls / إعدادات عامة</strong><p>These values affect future imports and customer-visible price history. Existing published prices are not recalculated automatically. / هذه القيم تؤثر على الاستيراد القادم ولا تعيد حساب الأسعار المنشورة تلقائيًا.</p></div></div>';
print '<form method="post"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="save"><section class="chinaplanetsp-rule-editor"><div class="chinaplanetsp-list-head"><h2><span class="fas fa-coins"></span> Pricing defaults / الإعدادات الافتراضية</h2></div><div class="chinaplanetsp-settings-grid">';
$fields = array(
    array('ignore_below','CHINAPLANETSP_IGNORE_BELOW','1000','Import threshold / حد تجاهل السعر','Ignore numeric prices below this amount. / تجاهل أي سعر أقل من هذه القيمة.'),
    array('default_pol','CHINAPLANETSP_DEFAULT_POL','Guangzhou','Default POL / ميناء التحميل الافتراضي','Used when the workbook does not identify Guangzhou or Shenzhen. / يستخدم عند غياب ميناء التحميل.'),
    array('margin_20gp','CHINAPLANETSP_MARGIN_20GP','200','20GP margin / هامش 20GP','Base margin added during import. / الهامش الأساسي عند الاستيراد.'),
    array('margin_40gp','CHINAPLANETSP_MARGIN_40GP','400','40GP margin / هامش 40GP','Base margin added during import. / الهامش الأساسي عند الاستيراد.'),
    array('margin_40hq','CHINAPLANETSP_MARGIN_40HQ','400','40HQ margin / هامش 40HQ','Base margin added during import. / الهامش الأساسي عند الاستيراد.'),
    array('margin_45hq','CHINAPLANETSP_MARGIN_45HQ','400','45HQ margin / هامش 45HQ','Base margin added during import. / الهامش الأساسي عند الاستيراد.'),
    array('currency','CHINAPLANETSP_CURRENCY','USD','Workbook currency / عملة الملف','Default currency for imported workbooks. / العملة الافتراضية للملفات.'),
    array('history_months','CHINAPLANETSP_HISTORY_MONTHS','12','History months / أشهر السجل','Default customer history period from 1 to 60 months. / مدة سجل العميل من شهر إلى 60.'),
);
foreach ($fields as $f) {
    $value = isset($conf->global->{$f[1]}) ? $conf->global->{$f[1]} : $f[2];
    print '<label><span>'.dol_escape_htmltag($f[3]).'</span><input name="'.$f[0].'" value="'.dol_escape_htmltag($value).'" autocomplete="off"><small>'.dol_escape_htmltag($f[4]).'</small></label>';
}
$history = !empty($conf->global->CHINAPLANETSP_CUSTOMER_HISTORY);
$privateTranslationReady = getenv('CHINAPLANETSP_TRANSLATION_ENDPOINT') !== false && getenv('CHINAPLANETSP_TRANSLATION_ENDPOINT') !== '' && getenv('CHINAPLANETSP_TRANSLATION_MODEL') !== false && getenv('CHINAPLANETSP_TRANSLATION_MODEL') !== '';
$translationProviderLabel = $privateTranslationReady ? 'Automatic private provider' : 'Automatic free fallback (MyMemory)';
print '</div></section><section class="chinaplanetsp-rule-editor"><h2><span class="fas fa-eye"></span> Customer visibility / ما يظهر للعميل</h2><div class="chinaplanetsp-setting-switches"><label><input type="checkbox" name="customer_history" value="1"'.($history?' checked':'').'><span><strong>Show sale-price history / إظهار سجل سعر البيع</strong><small>External customers can see public price history for the configured period.</small></span></label><div class="chinaplanetsp-mapping-help"><span class="fas fa-language"></span><div><strong>Carrier-note translation: '.$translationProviderLabel.'</strong><p>Valid English output is accepted automatically and remains editable by authorized staff. The free fallback sends only the carrier-note text to MyMemory and stops safely if its usage limit is reached.</p></div></div></div></section><div class="chinaplanetsp-rule-actions"><button class="button button-save chinaplanetsp-submit" type="submit"><span class="fas fa-save"></span> Save settings / حفظ الإعدادات</button></div></form></main>';
cpcc_admin_shell_close(); llxFooter(); $db->close();
