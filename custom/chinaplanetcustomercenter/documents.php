<?php
define('CSRFCHECK_WITH_TOKEN', 1);
if (!isset($_SERVER['QUERY_STRING'])) $_SERVER['QUERY_STRING'] = '';

require_once __DIR__.'/lib/main.lib.php';
require_once __DIR__.'/lib/admin_shell.lib.php';

require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once __DIR__.'/lib/portal.lib.php';

cpcc_portal_require_access($user, array(
    array('chinaplanetcustomercenter', 'read'),
    array('chinaplanetlogistics', 'read'),
));

$scope = cpcc_portal_user_scope($user);
$is_external = !empty($scope['is_external']);
$socid = cpcc_portal_customer_scope_id($db, $user, GETPOSTINT('socid'));
$search = GETPOST('search', 'alpha');
$action = GETPOST('action', 'alpha');
$shipid = GETPOSTINT('shipid');
$shipid_post = GETPOSTINT('shipid', 2);
$file = GETPOST('file', 'restricthtml');
$newname = GETPOST('newname', 'alphanohtml');
$canwrite = (!$is_external && ($user->admin || $user->hasRight('chinaplanetcustomercenter', 'write') || $user->hasRight('chinaplanetlogistics', 'write')));
$candelete = (!$is_external && ($user->admin || $user->hasRight('chinaplanetcustomercenter', 'delete') || $user->hasRight('chinaplanetlogistics', 'delete')));
$is_post = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST';
$form = new Form($db);
$customer_options = array();
if (!$is_external) {
    $res_customers=$db->query("SELECT rowid,nom FROM ".MAIN_DB_PREFIX."societe WHERE entity=".(int)$conf->entity." AND status=1 AND client IN (1,3) ORDER BY nom");
    if($res_customers) while($customer=$db->fetch_object($res_customers)) $customer_options[(int)$customer->rowid]=$customer->nom;
}

$ship_options = array();
$sql_ship_options = "SELECT t.rowid, t.ref, t.container_no, t.bl_no, t.fk_soc, s.nom as customer_name"
    . " FROM ".MAIN_DB_PREFIX."chinaplanetlogistics t"
    . " LEFT JOIN ".MAIN_DB_PREFIX."societe s ON s.rowid = t.fk_soc"
    . " WHERE t.entity=".(int) $conf->entity
    . ($socid > 0 ? " AND t.fk_soc = ".((int) $socid) : "")
    . " ORDER BY t.rowid DESC";
$res_ship_options = $db->query($sql_ship_options);
if ($res_ship_options) {
    while ($opt = $db->fetch_object($res_ship_options)) {
        $label = 'ID '.$opt->rowid;
        if (!empty($opt->ref)) $label .= ' | '.$opt->ref;
        if (!empty($opt->container_no)) $label .= ' | '.$opt->container_no;
        if (!empty($opt->bl_no)) $label .= ' | BL '.$opt->bl_no;
        if (!empty($opt->customer_name)) $label .= ' | '.$opt->customer_name;
        $ship_options[(int) $opt->rowid] = $label;
    }
}

if (in_array($action, array('upload', 'rename', 'deletefile'), true) && !$is_post) accessforbidden();

if ($action === 'upload') {
    if (!$canwrite || $shipid_post <= 0) accessforbidden();
    $shipment = cpcc_portal_authorized_shipment($db, $user, $shipid_post, $socid);
    if (!$shipment) accessforbidden();

    $upload = $_FILES['userfile'] ?? array();
    $upload_dir = cpcc_portal_shipment_doc_root($shipid_post);
    $uploadres = cpcc_portal_store_uploaded_shipment_file($shipid_post, $upload_dir, $upload['name'] ?? '', $upload['tmp_name'] ?? '', $upload['size'] ?? 0, $upload['error'] ?? UPLOAD_ERR_NO_FILE);
    if (!empty($uploadres['ok'])) {
        setEventMessages('Document uploaded successfully.', null, 'mesgs');
    } else {
        setEventMessages(!empty($uploadres['error']) ? $uploadres['error'] : 'Upload was rejected.', null, 'errors');
    }
}

if ($action === 'rename' || $action === 'deletefile') {
    if (($action === 'rename' && !$canwrite) || ($action === 'deletefile' && !$candelete) || $shipid_post <= 0 || $file === '') accessforbidden();
    $shipment = cpcc_portal_authorized_shipment($db, $user, $shipid_post, $socid);
    if (!$shipment) accessforbidden();

    $old_full = cpcc_portal_resolve_shipment_doc_path($shipid_post, $shipment->ref, $file);
    if ($old_full === '') accessforbidden();

    if ($action === 'deletefile') {
        if (!@unlink($old_full)) setEventMessages('Document operation failed.', null, 'errors');
        else setEventMessages('Document deleted.', null, 'mesgs');
    } else {
        $safe_new = cpcc_portal_validate_shipment_filename($newname);
        $base_dir = realpath(dirname($old_full));
        $target = $base_dir !== false && $safe_new !== '' ? $base_dir.DIRECTORY_SEPARATOR.$safe_new : '';
        if ($target === '' || file_exists($target) || !cpcc_portal_path_is_within($old_full, $base_dir)) {
            setEventMessages('Document operation failed.', null, 'errors');
        } elseif (!@rename($old_full, $target)) {
            setEventMessages('Document operation failed.', null, 'errors');
        } else {
            setEventMessages('Document renamed.', null, 'mesgs');
        }
    }
}

llxHeader('', 'Documents / المستندات', '', '', 0, 0, array(), $is_external ? array() : array(cpcc_admin_shell_asset('/custom/chinaplanetcustomercenter/css/admin_dashboard.css')));
if ($is_external) cpcc_portal_navigation($user, 'documents');
else cpcc_admin_shell_open('documents', 'Shipment Documents / مستندات الشحن', 'رفع وإدارة ملفات الشحنات ومستندات العملاء', 'fa-folder-open', array('socid'=>$socid));

print '<div class="div-table-responsive-no-min">';
print '<h2>Documents / المستندات</h2>';
print '<p>هذه الصفحة تجمع المستندات المرفوعة لكل شحنة. الملفات تُفتح عبر رابط تنزيل آمن داخل البوابة.</p>';
print '<div class="info"><strong>Commercial documents / مستندات التصدير:</strong> الفواتير التجارية وقوائم التعبئة موجودة في صفحة مستقلة. <a class="button button-small" href="'.dol_buildpath('/chinaplanetcustomercenter/commercial_documents.php', 1).($socid > 0 && !$is_external ? '?socid='.(int) $socid : '').'">Open commercial documents</a></div>';

if ($is_external && $socid <= 0) {
    print '<div class="warning">No linked customer account was found for this portal user.</div>';
} else {
    $sql = "SELECT t.rowid, t.ref, t.container_no, t.bl_no, t.date_eta, t.fk_statut, s.nom as customer_name"
         . " FROM ".MAIN_DB_PREFIX."chinaplanetlogistics t"
         . " LEFT JOIN ".MAIN_DB_PREFIX."societe s ON s.rowid = t.fk_soc"
         . " WHERE t.entity=".(int) $conf->entity
         . ($socid > 0 ? " AND t.fk_soc = ".((int) $socid) : "")
         . (!empty($search) ? " AND (t.ref LIKE '%".$db->escape($search)."%' OR t.container_no LIKE '%".$db->escape($search)."%' OR t.bl_no LIKE '%".$db->escape($search)."%')" : "")
         . " ORDER BY t.date_eta DESC, t.rowid DESC";
    $resql = $db->query($sql);

    print '<form method="GET" class="fright" style="margin-bottom:10px">';
    if (!$is_external) print Form::selectarray('socid',$customer_options,$socid,'كل العملاء / All customers',0,0,'aria-label="Customer / العميل"',0,0,0,'','minwidth250',1).' ';
    print '<input type="text" name="search" class="flat" placeholder="الشحنة / الحاوية / البوليصة" aria-label="Shipment, container or BL / الشحنة أو الحاوية أو البوليصة" value="'.dol_escape_htmltag($search).'"> ';
    print '<input type="submit" class="button" value="بحث / Search">';
    print '</form>';

    if ($canwrite && !$is_external) {
        print '<form method="POST" enctype="multipart/form-data" style="clear:both;margin:0 0 12px 0;padding:10px;border:1px solid #ddd;background:#fafafa">';
        print '<input type="hidden" name="token" value="'.newToken().'">';
        print '<input type="hidden" name="action" value="upload">';
        print '<div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">';
        print '<label>الشحنة / Shipment <span class="opacitymedium">(بحث بالمرجع أو الحاوية أو البوليصة)</span><br>';
        print Form::selectarray('shipid', $ship_options, $shipid, 1, 0, 0, 'id="shipid" style="min-width:360px;max-width:100%"', 0, 0, 0, '', 'minwidth300 widthcentpercentminusx', 1);
        print '</label>';
        print '<input type="file" name="userfile" class="minwidth200">';
        print '<input type="submit" class="button" value="رفع إلى الشحنة / Upload to Shipment">';
        print '</div>';
        print '<div class="opacitymedium" style="margin-top:6px">Upload the file to the shipment folder used by the customer portal.</div>';
        print '</form>';
    }

    print '<table class="noborder centpercent">';
    print '<tr class="liste_titre"><th>رقم / ID</th><th>الشحنة / Shipment</th><th>العميل / Customer</th><th>الحاوية / Container</th><th>البوليصة / BL</th><th>الوصول / ETA</th><th>الحالة / Status</th><th>المستندات / Documents</th><th>الإجراءات / Actions</th></tr>';
    if ($resql && $db->num_rows($resql) > 0) {
        while ($obj = $db->fetch_object($resql)) {
            $docs = array();
            if (!$is_external) {
                foreach (cpcc_portal_shipment_doc_roots($obj->rowid, $obj->ref) as $dir) {
                    if (!is_dir($dir)) continue;
                    $dir_docs = dol_dir_list($dir, 'files', 0, '', '(\\.meta|_preview.*\\.png)$', 'date', SORT_DESC);
                    if (!empty($dir_docs)) {
                        foreach ($dir_docs as $doc) {
                            $doc['docroot'] = $dir;
                            $docs[] = $doc;
                        }
                    }
                }
            }
            print '<tr>';
            print '<td><b>'.(int) $obj->rowid.'</b></td>';
            print '<td><a href="'.dol_buildpath('/chinaplanetlogistics/card.php', 1).'?id='.(int) $obj->rowid.'">'.dol_escape_htmltag($obj->ref).'</a></td>';
            print '<td>'.dol_escape_htmltag($obj->customer_name ?: '-').'</td>';
            print '<td>'.dol_escape_htmltag($obj->container_no).'</td>';
            print '<td>'.dol_escape_htmltag($obj->bl_no).'</td>';
            print '<td>'.(!empty($obj->date_eta) ? dol_print_date($obj->date_eta, 'day') : '-').'</td>';
            print '<td>'.dol_escape_htmltag((string) $obj->fk_statut).'</td>';
            print '<td>';
            if (empty($docs)) {
                $emptyMessage = $is_external ? 'No approved customer document is available.' : 'No files';
                print '<span class="opacitymedium">'.dol_escape_htmltag($emptyMessage).'</span>';
            } else {
                print '<ul class="nobullet margin0 padding0">';
                foreach ($docs as $doc) {
                    $relative = str_replace(DOL_DATA_ROOT.'/', '', $doc['fullname']);
                    $url = dol_buildpath('/chinaplanetcustomercenter/download.php', 1).'?type=shipment&id='.(int) $obj->rowid.'&file='.urlencode($relative);
                    print '<li><a href="'.dol_escape_htmltag($url).'">'.dol_escape_htmltag($doc['name']).'</a></li>';
                }
                print '</ul>';
            }
            print '</td>';
            print '<td>';
            print '<a class="button button-small" href="'.dol_buildpath('/chinaplanetlogistics/card.php', 1).'?id='.(int) $obj->rowid.'">Open</a>';
            if (($canwrite || $candelete) && !$is_external) {
                if ($canwrite) {
                print ' <a class="button button-small" href="'.dol_buildpath('/chinaplanetlogistics/card.php', 1).'?id='.(int) $obj->rowid.'&action=edit">Edit</a>';
                }
                if ($candelete) print ' <a class="button button-small" href="'.dol_buildpath('/chinaplanetlogistics/card.php', 1).'?id='.(int) $obj->rowid.'&action=confirm_delete">Delete</a>';
                if (!empty($docs)) {
                    print '<div style="margin-top:8px">';
                    foreach ($docs as $doc) {
                        $relative = str_replace(DOL_DATA_ROOT.'/', '', $doc['fullname']);
                        print '<form method="POST" style="margin:4px 0;display:flex;gap:4px;flex-wrap:wrap;align-items:center">';
                        print '<input type="hidden" name="token" value="'.newToken().'">';
                        print '<input type="hidden" name="shipid" value="'.(int) $obj->rowid.'">';
                        print '<input type="hidden" name="file" value="'.dol_escape_htmltag($relative).'">';
                        if ($canwrite) {
                            print '<input type="text" class="flat minwidth150" name="newname" value="'.dol_escape_htmltag($doc['name']).'">';
                            print '<button class="button button-small" type="submit" name="action" value="rename">Rename</button>';
                        }
                        if ($candelete) print '<button class="button button-small" type="submit" name="action" value="deletefile" onclick="return confirm(\'Delete this document?\')">Delete file</button>';
                        print '</form>';
                    }
                    print '</div>';
                }
            }
            print '</td>';
            print '</tr>';
        }
    } else {
        print '<tr><td colspan="9" class="opacitymedium center">لا توجد شحنات أو مستندات / No shipments with documents found.</td></tr>';
    }
    print '</table>';
}

print '</div>';
if (!$is_external) cpcc_admin_shell_close();
llxFooter();
