<?php
$m041PostedAction = isset($_POST['action']) ? (string) $_POST['action'] : '';
if (in_array($m041PostedAction, array('save_translation','reset_translation','auto_translate'), true)) define('CSRFCHECK_WITH_TOKEN', 1);
require $_SERVER['DOCUMENT_ROOT'].'/main.inc.php';
require_once __DIR__.'/../lib/main.lib.php';
require_once __DIR__.'/../class/ShippingPriceImporter.class.php';
require_once __DIR__.'/../class/ShippingNoteReviewService.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/chinaplanetcustomercenter/lib/admin_shell.lib.php';

chinaplanetsp_require_internal($user, 'import');
$id = GETPOSTINT('id');
$notePage = max(0, GETPOSTINT('note_page'));
$autoMode = GETPOST('auto', 'alpha');
$reviewService = new ShippingNoteReviewService($db, $user);
$translationEnabled = ShippingPriceImporter::automaticTranslationEnabled();

if ($m041PostedAction !== '') {
    if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') accessforbidden();
    try {
        if ($m041PostedAction === 'auto_translate') {
            if (!$translationEnabled) throw new RuntimeException('Carrier-note translation is disabled.');
            $result = $reviewService->autoTranslatePending($id, 8, 18.0);
            if ((int) $result['approved'] > 0) {
                setEventMessages('Automatically translated '.(int)$result['approved'].' carrier note(s). / تمت الترجمة التلقائية.', null, 'mesgs');
            } elseif ((int) $result['remaining'] > 0) {
                setEventMessages('The free translation service could not make progress now. The notes remain private and can retry later. / لم تتقدم خدمة الترجمة المجانية الآن وستبقى الملاحظات خاصة.', null, 'warnings');
            }
            $nextAuto = (int) $result['approved'] > 0 && (int) $result['remaining'] > 0 && empty($result['stalled']) ? 'continue' : 'stopped';
            header('Location: batch.php?id='.$id.'&note_page='.$notePage.'&auto='.$nextAuto);
            exit;
        }
        $result = $reviewService->mutate($id, GETPOSTINT('source_row'), $m041PostedAction, GETPOST('notes_en', 'restricthtml'));
        setEventMessages('Translation correction saved for '.(int)$result['affected'].' price row(s). / تم حفظ تعديل الترجمة.', null, 'mesgs');
    } catch (Throwable $e) {
        setEventMessages($e->getMessage(), null, 'errors');
    }
    header('Location: batch.php?id='.$id.'&note_page='.$notePage);
    exit;
}

$res = $db->query("SELECT * FROM ".MAIN_DB_PREFIX."chinaplanetsp_batch WHERE rowid=".$id." AND entity=".(int)$conf->entity);
$batch = $res ? $db->fetch_object($res) : null;
if (!$batch) accessforbidden('Import batch not found.');
$report = json_decode((string)$batch->report_json, true) ?: array();
$importer = new ShippingPriceImporter($db, $user);
$publication = $importer->getBatchPublicationState($id);

$rates = array();
$res = $db->query("SELECT * FROM ".MAIN_DB_PREFIX."chinaplanetsp_rate WHERE fk_batch=".$id." AND entity=".(int)$conf->entity." ORDER BY source_row,container_type LIMIT 1000");
if ($res) while ($rate = $db->fetch_object($res)) $rates[] = $rate;

$translationSummary = (object) array('note_rows'=>0, 'pending_rows'=>0, 'approved_rows'=>0);
$summarySql = "SELECT COUNT(DISTINCT CASE WHEN source_notes IS NOT NULL AND source_notes<>'' THEN source_row END) note_rows,";
$summarySql .= "COUNT(DISTINCT CASE WHEN source_notes IS NOT NULL AND source_notes<>'' AND translation_status<>'approved' THEN source_row END) pending_rows,";
$summarySql .= "COUNT(DISTINCT CASE WHEN translation_status='approved' THEN source_row END) approved_rows";
$summarySql .= " FROM ".MAIN_DB_PREFIX."chinaplanetsp_rate WHERE entity=".(int)$conf->entity." AND fk_batch=".$id;
$res = $db->query($summarySql);
if ($res && ($row = $db->fetch_object($res))) $translationSummary = $row;
$canPublish = !$translationEnabled || (int)$translationSummary->pending_rows === 0;
$canReviewTranslations = $reviewService->canReview();
$translationPage = $translationEnabled && $canReviewTranslations ? $reviewService->listGroups($id, $notePage, 25) : array('groups'=>array(),'total'=>0,'page'=>0,'pages'=>0);

$css = array(
    cpcc_admin_shell_asset('/custom/chinaplanetcustomercenter/css/admin_dashboard.css'),
    cpcc_admin_shell_asset('/custom/chinaplanetshippingprices/css/shippingprices.css'),
);
llxHeader('', 'Review '.$batch->ref, '', '', 0, 0, array(), $css);
cpcc_admin_shell_open('shippingprices', 'Review import / مراجعة ملف الأسعار', 'تُترجم ملاحظات الوكيل تلقائيًا ويمكنك تعديل الإنجليزية عند الحاجة', 'fa-search');
print '<main class="chinaplanetsp-rates-manager">';
print '<div class="chinaplanetsp-admin-toolbar"><a class="button" href="import.php">Back to imports</a><a class="button" href="dashboard.php">Control center</a>';
if ((int)$batch->status !== 2 && $canPublish) {
    print '<form method="post" action="import.php" onsubmit="return confirm(\'Publish this batch and update current prices?\')"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="publish"><input type="hidden" name="id" value="'.$id.'"><button class="button button-save" type="submit">Publish this batch / نشر الدفعة</button></form>';
} elseif ((int)$batch->status === 2 && !empty($publication['can_reactivate'])) {
    print '<form method="post" action="import.php" onsubmit="return confirm(\'Reactivate this published batch as the current price list?\')"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="activate_current"><input type="hidden" name="id" value="'.$id.'"><button class="button button-save" type="submit">Repair &amp; activate current / إصلاح وتفعيل الحالية</button></form>';
}
print '</div>';

if ($translationEnabled && (int)$batch->status !== 2 && (int)$translationSummary->pending_rows > 0 && $canReviewTranslations) {
    print '<form id="chinaplanetsp-auto-translate" method="post" hidden><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="auto_translate"><input type="hidden" name="id" value="'.$id.'"><input type="hidden" name="note_page" value="'.(int)$notePage.'"></form>';
    if ($autoMode !== 'stopped') {
        print '<div class="info"><strong>Automatic translation is running.</strong> This page will continue in small safe batches; no manual approval is required. / الترجمة التلقائية تعمل على دفعات آمنة ولا تحتاج موافقة يدوية.</div>';
        print '<script>window.addEventListener("load",function(){window.setTimeout(function(){var form=document.getElementById("chinaplanetsp-auto-translate");if(form)form.submit();},500);});</script>';
    } else {
        print '<div class="warning"><strong>Automatic translation paused safely.</strong> The free service may be temporarily unavailable or at its usage limit. Untranslated notes remain private. <button class="button" type="submit" form="chinaplanetsp-auto-translate">Retry automatic translation / إعادة المحاولة</button></div>';
    }
}

if ($translationEnabled && (int)$batch->status !== 2 && !$canPublish) print '<div class="warning"><strong>Automatic translation pending:</strong> '.(int)$translationSummary->pending_rows.' carrier note(s) are still untranslated or invalid. They will retry automatically; manual editing is optional. / ما زالت بعض الملاحظات بانتظار الترجمة التلقائية، والتعديل اليدوي اختياري.</div>';
if (!$translationEnabled) print '<div class="info"><strong>Carrier-note translation is disabled.</strong> Source notes remain internal, are not sent to an external service, and do not block price publication. / ترجمة ملاحظات الوكيل متوقفة، وتبقى الملاحظات داخلية ولا تمنع نشر الأسعار.</div>';
if ((int)$batch->status === 2 && !$publication['is_current'] && !$publication['is_latest_relevant']) print '<div class="info">This batch is published as history. A newer relevant batch supplies the current prices. / هذه الدفعة محفوظة كسجل تاريخي.</div>';
if (!empty($publication['can_reactivate'])) print '<div class="warning">This is the latest published batch, but none of its prices are current. Use repair only if it must be active. / استخدم الإصلاح فقط إذا كانت هذه القائمة المطلوبة.</div>';

$statusText = (int)$batch->status !== 2 ? 'Draft — not visible yet / مسودة غير ظاهرة' : ($publication['is_current'] ? 'Published & current / منشور وحالي' : 'Published history / سجل منشور');
$statusClass = (int)$batch->status !== 2 ? '1' : ($publication['is_current'] ? '4' : '6');
print '<section class="chinaplanetsp-rule-editor"><div class="chinaplanetsp-list-head"><h2>'.dol_escape_htmltag($batch->ref).'</h2><span class="badge badge-status'.$statusClass.'">'.$statusText.'</span></div><div class="chinaplanetsp-review-meta">';
$scope = $batch->replacement_scope === 'all' ? 'Replace all current imported prices' : 'Replace this agent only';
foreach (array('Price agent'=>$batch->source_name, 'File'=>$batch->original_name, 'Sheet / effective date'=>$batch->source_sheet.' · '.$batch->effective_date, 'Publishing action'=>$scope, 'Threshold / currency'=>price($batch->ignore_below).' '.$batch->currency_code) as $label=>$value) {
    print '<div><span>'.dol_escape_htmltag($label).'</span><strong>'.dol_escape_htmltag($value).'</strong></div>';
}
print '</div><div class="chinaplanetsp-admin-stats">';
$stats = array(
    array('Accepted prices',(int)$batch->accepted_rows,'Parsed from workbook / مقبول',''),
    array('Current prices',(int)$publication['current_rates'],'Active now / الحالية الآن',''),
    array('Customer search',(int)$publication['searchable_rates'],'Visible in inquiry / ظاهرة في البحث',''),
    array('Carrier notes',(int)$translationSummary->note_rows,'Automatically translated on upload',''),
    array($translationEnabled ? 'Need attention' : 'Translation',$translationEnabled ? (int)$translationSummary->pending_rows : 'Off',$translationEnabled ? 'Only failed or unconfigured translations' : 'Source notes stay internal',$translationEnabled && (int)$translationSummary->pending_rows ? 'warn' : ''),
);
foreach ($stats as $stat) print '<div class="'.$stat[3].'"><span>'.$stat[0].'</span><strong>'.$stat[1].'</strong><small>'.$stat[2].'</small></div>';
print '</div></section>';

if ($translationEnabled && (int)$translationSummary->note_rows > 0) {
    print '<section class="chinaplanetsp-rule-list chinaplanetsp-translation-review"><div class="chinaplanetsp-list-head"><h2>Carrier-note English translation / ترجمة ملاحظات الوكيل <span>'.(int)$translationPage['total'].'</span></h2><small>Successful translations are accepted automatically. Edit and save only when correction is needed.</small></div>';
    if (!$canReviewTranslations) {
        print '<div class="warning">Viewing the source or editing its translation requires both Import and View Cost rights, or administrator access.</div>';
    } else {
        foreach ($translationPage['groups'] as $group) {
            $inconsistent = (int)$group->hash_count !== 1 || (int)$group->status_count !== 1;
            $status = $inconsistent ? 'inconsistent' : (string)$group->translation_status;
            $statusLabel = $status === 'approved' ? ((string)$group->translation_provider === 'staff' ? 'Staff corrected' : 'Automatically translated') : ($status === 'pending' || $status === 'failed' ? 'Needs attention' : str_replace('_',' ',ucfirst($status)));
            print '<article class="chinaplanetsp-translation-card status-'.dol_escape_htmltag($status).'">';
            print '<header><div><strong>Source row '.(int)$group->source_row.'</strong><small>'.(int)$group->rate_count.' container-price row(s)</small></div><span class="chinaplanetsp-translation-status">'.dol_escape_htmltag($statusLabel).'</span></header>';
            if ($inconsistent) print '<div class="error">Grouped source evidence is inconsistent. Do not publish this batch.</div>';
            print '<div class="chinaplanetsp-translation-grid"><section><h3>Original source / النص الأصلي</h3><div class="chinaplanetsp-source-note" lang="zh" dir="ltr">'.nl2br(dol_escape_htmltag($group->source_notes)).'</div>';
            if ((string)$group->source_notes_raw !== (string)$group->source_notes) print '<details><summary>Exact workbook cell / النص الخام</summary><pre>'.dol_escape_htmltag($group->source_notes_raw).'</pre></details>';
            print '</section><form method="post"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="id" value="'.$id.'"><input type="hidden" name="note_page" value="'.(int)$translationPage['page'].'"><input type="hidden" name="source_row" value="'.(int)$group->source_row.'"><label><span>English translation / الترجمة الإنجليزية</span><textarea name="notes_en" rows="7" lang="en" dir="ltr" maxlength="12000">'.dol_escape_htmltag($group->notes_en).'</textarea></label>';
            if (!empty($group->translation_error)) print '<small class="chinaplanetsp-translation-error">Translation service: '.dol_escape_htmltag($group->translation_error).'</small>';
            if (!empty($group->translation_provider)) print '<small>Translation source: '.dol_escape_htmltag($group->translation_provider).(!empty($group->translation_model)?' · '.dol_escape_htmltag($group->translation_model):'').'</small>';
            if (!$inconsistent) {
                print '<div class="chinaplanetsp-translation-actions"><button class="button button-save" name="action" value="save_translation">Save English translation / حفظ الترجمة</button></div>';
            }
            print '</form></div></article>';
        }
        if ((int)$translationPage['pages'] > 1) {
            print '<nav class="chinaplanetsp-translation-pages" aria-label="Translation pages">';
            for ($page=0; $page<(int)$translationPage['pages']; $page++) print '<a class="button'.($page===(int)$translationPage['page']?' button-save':'').'" href="?id='.$id.'&note_page='.$page.'">'.($page+1).'</a>';
            print '</nav>';
        }
    }
    print '</section>';
}

print '<section class="chinaplanetsp-rule-list"><div class="chinaplanetsp-list-head"><h2>Accepted prices preview <span>'.count($rates).'</span></h2>';
if ((int)$batch->status !== 2 && $canPublish) print '<form method="post" action="import.php" onsubmit="return confirm(\'Publish this batch and update current prices?\')"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="publish"><input type="hidden" name="id" value="'.$id.'"><button class="button button-save" type="submit">Publish this batch / نشر الدفعة</button></form>';
print '</div>';
if (!$rates) {
    print '<div class="warning">No accepted prices were found in this batch. Do not publish it.</div>';
} else {
    print '<div class="div-table-responsive chinaplanetsp-review-table"><table class="noborder centpercent"><tr class="liste_titre"><th>Source row</th><th>POL</th><th>Country</th><th>POD</th><th>Shipping line / lane</th><th>Service</th><th>Container</th><th class="right">Supplier cost</th><th class="right">Sale price</th></tr>';
    foreach ($rates as $rate) print '<tr><td>'.(int)$rate->source_row.'</td><td>'.dol_escape_htmltag($rate->pol).'</td><td>'.dol_escape_htmltag($rate->destination_country ?: 'UNMAPPED').'</td><td>'.dol_escape_htmltag($rate->pod).'</td><td><strong>'.dol_escape_htmltag($rate->shipping_line).'</strong><small>'.dol_escape_htmltag($rate->region_label).'</small></td><td>'.dol_escape_htmltag(ucfirst($rate->service_type)).'</td><td>'.dol_escape_htmltag($rate->container_type).'</td><td class="right">'.price($rate->cost_price).'</td><td class="right"><strong>'.price($rate->sale_price).'</strong></td></tr>';
    print '</table></div>';
}
print '</section>';

$warnings = $report['warnings'] ?? array();
$ignored = $report['ignored'] ?? array();
print '<section class="chinaplanetsp-review-details"><details'.(!$rates?' open':'').'><summary>Warnings / التحذيرات <span>'.count($warnings).'</span></summary><div>';
if (!$warnings) print '<p>No mapping warnings.</p>'; else { print '<ul>'; foreach ($warnings as $warning) print '<li>'.dol_escape_htmltag($warning).'</li>'; print '</ul>'; }
print '</div></details><details><summary>Ignored examples / المستبعدات <span>'.count($ignored).'</span></summary><div class="div-table-responsive"><table class="noborder centpercent"><tr class="liste_titre"><th>Row</th><th>Cell</th><th>Reason</th><th>Value</th></tr>';
foreach (array_slice($ignored, 0, 150) as $ignoredRow) print '<tr><td>'.(int)($ignoredRow['row'] ?? 0).'</td><td>'.dol_escape_htmltag($ignoredRow['cell'] ?? '').'</td><td>'.dol_escape_htmltag($ignoredRow['reason'] ?? '').'</td><td>'.dol_escape_htmltag((string)($ignoredRow['value'] ?? '')).'</td></tr>';
print '</table></div></details></section></main>';
cpcc_admin_shell_close();
llxFooter();
$db->close();
