<?php
$m041PostedAction = isset($_POST['action']) ? (string) $_POST['action'] : '';
if (in_array($m041PostedAction, array('upload','publish','activate_current'), true)) define('CSRFCHECK_WITH_TOKEN', 1);
require $_SERVER['DOCUMENT_ROOT'].'/main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once __DIR__.'/../lib/main.lib.php';
require_once __DIR__.'/../class/ShippingPriceImporter.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/chinaplanetcustomercenter/lib/admin_shell.lib.php';
chinaplanetsp_require_internal($user, 'import');
$langs->loadLangs(array('chinaplanetshippingprices@chinaplanetshippingprices', 'admin'));
$importer = new ShippingPriceImporter($db, $user);
$translationEnabled = ShippingPriceImporter::automaticTranslationEnabled();
$action = GETPOST('action', 'aZ09');
$isPost = !empty($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST';

if ($isPost && $action === 'upload') {
    try {
        if (empty($_FILES['ratefile']['tmp_name']) || !is_uploaded_file($_FILES['ratefile']['tmp_name'])) throw new RuntimeException('Choose a workbook first.');
        $original = basename($_FILES['ratefile']['name']);
        $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        if (!in_array($ext, array('xls', 'xlsx', 'csv'), true)) throw new RuntimeException('Only .xls, .xlsx and .csv files are accepted.');
        $dir = $conf->chinaplanetshippingprices->dir_output.'/imports';
        if (dol_mkdir($dir) < 0) throw new RuntimeException('Unable to create the import storage directory.');
        $storedName = dol_print_date(dol_now(), '%Y%m%d_%H%M%S').'_'.dol_sanitizeFileName($original);
        $destination = $dir.'/'.$storedName;
        if (dol_move_uploaded_file($_FILES['ratefile']['tmp_name'], $destination, 0, 0, $_FILES['ratefile']['error']) <= 0) throw new RuntimeException('Unable to store uploaded workbook.');
        $sourceName=GETPOST('source_name','alphanohtml');if($sourceName==='__new__')$sourceName=trim(GETPOST('new_source_name','alphanohtml'));if($sourceName==='')throw new RuntimeException('Choose an existing shipping agent or enter a new agent name.');
        $ids = $importer->importFile($destination, $original, array(
            'mode' => GETPOST('import_mode', 'alpha'), 'source_name' => $sourceName, 'replacement_scope' => GETPOST('replacement_scope','alpha'),
            'ignore_below' => GETPOST('ignore_below', 'alphanohtml'), 'currency' => GETPOST('currency', 'alpha'),
            'stored_file' => 'imports/'.$storedName,
        ));
        $results = $importer->getLastImportResults();
        $duplicates = array_filter($results, function ($result) { return !empty($result['duplicate']); });
        if (count($results) === 1 && count($duplicates) === 1) {
            $state = $results[0]['publication'];
            if (!empty($state['is_current'])) {
                setEventMessages('This exact workbook and import settings were already published and are currently active. No duplicate draft was created. / هذا الملف بنفس الإعدادات منشور وحالي بالفعل؛ لم تُنشأ مسودة مكررة.', null, 'warnings');
            } elseif (!empty($state['is_published'])) {
                setEventMessages('This exact workbook and import settings already exist as published history. No duplicate draft was created. / هذا الملف بنفس الإعدادات موجود كسجل منشور سابق؛ لم تُنشأ مسودة مكررة.', null, 'warnings');
            } else {
                setEventMessages('This exact workbook and import settings already exist as a draft. The existing draft was reopened. / توجد مسودة سابقة لنفس الملف والإعدادات وتم فتحها بدلاً من إنشاء نسخة مكررة.', null, 'warnings');
            }
        } else {
            setEventMessages((count($results) - count($duplicates)).' new import batch(es) prepared for review'.(count($duplicates) ? '; '.count($duplicates).' duplicate(s) reused.' : '.').' / تم تجهيز الدفعات الجديدة للمراجعة.', null, 'mesgs');
        }
        if (count($ids) === 1) { header('Location: batch.php?id='.(int) $ids[0]); exit; }
    } catch (Throwable $e) { setEventMessages($e->getMessage(), null, 'errors'); }
}
if ($isPost && ($action === 'publish' || $action === 'activate_current')) {
    $batchId = GETPOSTINT('id');
    try {
        $state = $importer->publishBatch($batchId, $action === 'activate_current');
        if (!empty($state['is_current'])) {
            $message = 'Published and current: '.(int) $state['current_rates'].' active price(s), '.(int) $state['searchable_rates'].' available in customer search. / تم النشر والتفعيل.';
            setEventMessages($message, null, 'mesgs');
            if ((int) $state['searchable_rates'] === 0) setEventMessages('No prices from this batch are available in customer search. Review mappings, public visibility and the minimum-price threshold. / لا توجد أسعار من هذه الدفعة متاحة في بحث العميل؛ راجع الربط والظهور وحد السعر الأدنى.', null, 'warnings');
        } else {
            setEventMessages('Published as history only. A newer relevant batch remains current. / نُشرت الدفعة كسجل تاريخي فقط لأن هناك دفعة أحدث ما زالت حالية.', null, 'warnings');
        }
        header('Location: batch.php?id='.$batchId); exit;
    }
    catch (Throwable $e) { setEventMessages($e->getMessage(), null, 'errors'); }
}

$css=array(cpcc_admin_shell_asset('/custom/chinaplanetcustomercenter/css/admin_dashboard.css'),cpcc_admin_shell_asset('/custom/chinaplanetshippingprices/css/shippingprices.css'));
llxHeader('', 'Import Rates / استيراد الأسعار', '', '', 0, 0, array(), $css);
cpcc_admin_shell_open('shippingprices', 'Import Rates / استيراد أسعار الشحن', 'رفع ملف الوكيل وتجهيز مسودة آمنة للمراجعة قبل نشر الأسعار', 'fa-file-upload');
print '<main class="chinaplanetsp-rates-manager chinaplanetsp-import-page">';
print '<div class="chinaplanetsp-admin-toolbar"><a class="button" href="dashboard.php"><span class="fas fa-sliders-h"></span> Control Center / مركز الإدارة</a><a class="button" href="../index.php"><span class="fas fa-search-dollar"></span> Price Inquiry / استعلام الأسعار</a></div>';
if ($translationEnabled) {
    print '<div class="chinaplanetsp-mapping-help"><span class="fas fa-language"></span><div><strong>Automatic English translation / ترجمة إنجليزية تلقائية</strong><p>Carrier-note text is translated automatically and valid output is accepted without manual approval. You can edit English only when needed. Uploading still creates a safe draft until you publish it. / تُترجم الملاحظات تلقائيًا دون موافقة يدوية، ويمكن تعديل الإنجليزية عند الحاجة فقط.</p></div></div>';
} else {
    print '<div class="chinaplanetsp-mapping-help"><span class="fas fa-language"></span><div><strong>Carrier-note translation is disabled / ترجمة ملاحظات الوكيل متوقفة</strong><p>Source notes remain internal, are not sent externally, and do not block publishing the price batch. / تبقى الملاحظات داخلية ولا تُرسل للخارج ولا تمنع نشر دفعة الأسعار.</p></div></div>';
}
print '<section class="chinaplanetsp-rule-editor chinaplanetsp-import-card"><div class="chinaplanetsp-list-head"><h2><span class="fas fa-cloud-upload-alt"></span> Upload workbook / رفع ملف الأسعار</h2><span class="chinaplanetsp-format-badge">XLS · XLSX · CSV</span></div>';
print '<form method="post" action="?uploadform=1" enctype="multipart/form-data"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="upload"><div class="chinaplanetsp-rule-grid chinaplanetsp-import-grid">';
print '<label class="chinaplanetsp-file-field"><span>Workbook / ملف الأسعار *</span><input type="file" name="ratefile" accept=".xls,.xlsx,.csv" required><small>Choose the original agent workbook. / اختر ملف الأسعار الأصلي من الوكيل.</small></label>';
$agents=array();$ares=$db->query("SELECT DISTINCT source_name FROM ".MAIN_DB_PREFIX."chinaplanetsp_batch WHERE entity=".(int)$conf->entity." AND source_name<>'Manual entry' ORDER BY source_name");if($ares)while($a=$db->fetch_object($ares))$agents[]=$a->source_name;
print '<label><span>Shipping agent / وكيل الشحن *</span><select name="source_name" id="sp-agent" required><option value="">Choose agent / اختر الوكيل</option>';foreach($agents as$a)print'<option value="'.dol_escape_htmltag($a).'">'.dol_escape_htmltag($a).'</option>';print'<option value="__new__">+ Add new agent / إضافة وكيل</option></select><input name="new_source_name" id="sp-new-agent" placeholder="New agent name / اسم الوكيل الجديد" hidden></label>';
print '<label><span>Import mode / وضع الاستيراد</span><select name="import_mode"><option value="current">Latest/current sheet / أحدث قائمة حالية</option><option value="history">All recognized sheets / كل القوائم التاريخية</option></select><small>Use history only for backfilling old tariff sheets. / استخدم التاريخي للملفات القديمة فقط.</small></label>';
print '<label><span>Ignore below / تجاهل الأسعار أقل من</span><input name="ignore_below" inputmode="decimal" value="'.dol_escape_htmltag($conf->global->CHINAPLANETSP_IGNORE_BELOW ?? '1000').'"><small>Dummy-price threshold / حد تجاهل الأسعار الوهمية</small></label>';
print '<label><span>Currency / العملة</span><input name="currency" maxlength="3" value="'.dol_escape_htmltag($conf->global->CHINAPLANETSP_CURRENCY ?? 'USD').'" class="width75" autocomplete="off"></label>';
print '<fieldset class="chinaplanetsp-publish-scope"><legend>When published / عند النشر</legend><label><input type="radio" name="replacement_scope" value="agent" checked><span><strong>Replace this agent only / استبدال أسعار هذا الوكيل فقط</strong><small>Recommended for normal weekly updates. / الخيار المعتاد للتحديثات.</small></span></label><label><input type="radio" name="replacement_scope" value="all"><span><strong>Replace all imported prices / استبدال كل الأسعار المستوردة</strong><small>Use only for a full master-price replacement. / استخدمه فقط لملف شامل.</small></span></label><p>Old prices remain in history. Manual prices are never replaced. / الأسعار القديمة تبقى في السجل والأسعار اليدوية لا تُستبدل.</p></fieldset>';
print '</div><div class="chinaplanetsp-rule-actions"><button class="button button-save chinaplanetsp-submit" type="submit"><span class="fas fa-file-import"></span> Read & prepare draft / قراءة وتجهيز المسودة</button></div></form></section>';
print '<script>(function(){var s=document.getElementById("sp-agent"),n=document.getElementById("sp-new-agent");if(!s||!n)return;function x(){n.hidden=s.value!=="__new__";n.required=!n.hidden;}s.addEventListener("change",x);x();})();</script>';

print '<section class="chinaplanetsp-rule-list chinaplanetsp-import-history" id="batches"><div class="chinaplanetsp-list-head"><h2><span class="fas fa-history"></span> Import batches / سجل ملفات الأسعار</h2><small>Latest 100 batches / آخر 100 دفعة</small></div>';
$sql = "SELECT b.*,u.login,COALESCE(rs.current_rates,0) current_rates FROM ".MAIN_DB_PREFIX."chinaplanetsp_batch b LEFT JOIN ".MAIN_DB_PREFIX."user u ON u.rowid=b.fk_user_creat LEFT JOIN (SELECT fk_batch,entity,SUM(CASE WHEN status=1 AND is_current=1 THEN 1 ELSE 0 END) current_rates FROM ".MAIN_DB_PREFIX."chinaplanetsp_rate GROUP BY fk_batch,entity) rs ON rs.fk_batch=b.rowid AND rs.entity=b.entity WHERE b.entity=".(int)$conf->entity." ORDER BY b.rowid DESC LIMIT 100";
$res = $db->query($sql);
print '<div class="div-table-responsive"><table class="noborder centpercent"><tr class="liste_titre"><th>Reference / المرجع</th><th>Date & sheet / التاريخ والقائمة</th><th>Agent / الوكيل</th><th class="right">Accepted / مقبول</th><th class="right">Ignored / مستبعد</th><th class="right">Warnings / تحذيرات</th><th>Status / الحالة</th><th>Action / الإجراء</th></tr>';
if ($res) while ($b = $db->fetch_object($res)) {
    $report = json_decode((string)$b->report_json, true);
    if ((int)$b->status !== 2) $status = '<span class="badge badge-status1">Draft / مسودة</span>';
    elseif ((int)$b->current_rates > 0) $status = '<span class="badge badge-status4">Published &amp; current / منشور وحالي</span>';
    else $status = '<span class="badge badge-status6">Published history / سجل منشور</span>';
    print '<tr><td>'.dol_escape_htmltag($b->ref).'</td><td>'.dol_escape_htmltag($b->effective_date.' — '.$b->source_sheet).'</td><td>'.dol_escape_htmltag($b->source_name).'</td><td class="right">'.(int)$b->accepted_rows.'</td><td class="right">'.(int)$b->ignored_rows.'</td><td class="right">'.(int)$b->warning_count.'</td><td>'.$status.'</td><td class="right">';
    print '<div class="chinaplanetsp-row-actions"><a class="button smallpaddingimp" href="batch.php?id='.(int)$b->rowid.'">Review / مراجعة</a> ';
    if ((int)$b->status !== 2) print '<form method="post" action="import.php" onsubmit="return confirm(\'Publish this batch and update current prices?\')"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="publish"><input type="hidden" name="id" value="'.(int)$b->rowid.'"><button class="button button-save smallpaddingimp" type="submit">Publish / نشر</button></form>';
    print '</div>';
    print '</td></tr>';
}
print '</table></div></section></main>';
cpcc_admin_shell_close(); llxFooter(); $db->close();
