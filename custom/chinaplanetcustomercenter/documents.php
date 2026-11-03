<?php
require_once __DIR__.'/lib/ui.lib.php';
define('CSRFCHECK_WITH_TOKEN', 1);
if (!isset($_SERVER['QUERY_STRING'])) $_SERVER['QUERY_STRING'] = '';

require_once __DIR__.'/lib/main.lib.php';
require_once __DIR__.'/lib/admin_shell.lib.php';

require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once __DIR__.'/lib/portal.lib.php';
require_once __DIR__.'/lib/export.lib.php';
require_once __DIR__.'/../chinaplanetlogistics/lib/chinaplanetlogistics.lib.php';

$langs->loadLangs(array('chinaplanetcustomercenter@chinaplanetcustomercenter', 'chinaplanetlogistics@chinaplanetlogistics'));

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
$commercial_documents = $socid > 0 ? cpcc_export_documents($db, $socid, 0, 0, $is_external) : array();
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
    . ($is_external ? " AND t.customer_visible=1" : "")
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
        $archived = cpcc_portal_archive_shipment_file($db, $shipment, $user, $file);
        if (empty($archived['ok'])) setEventMessages($archived['error'] ?? 'Document operation failed.', null, 'errors');
        else setEventMessages($langs->trans('CPLShipmentFileArchived'), null, 'mesgs');
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

$pageCss = array(
    cpcc_admin_shell_asset('/custom/chinaplanetcustomercenter/css/customer_dashboard.css'),
    cpcc_admin_shell_asset('/custom/chinaplanetcustomercenter/css/export.css'),
    cpcc_admin_shell_asset('/custom/chinaplanetcustomercenter/css/documents.css'),
);
if (!$is_external) $pageCss[] = cpcc_admin_shell_asset('/custom/chinaplanetcustomercenter/css/admin_dashboard.css');
llxHeader('', cpcc_ui_english_label('Documents', 'Documents / المستندات'), '', '', 0, 0, array(), $pageCss);
if ($is_external) cpcc_portal_navigation($user, 'documents');
else cpcc_admin_shell_open('documents', cpcc_ui_english_label('Documents', 'Documents / المستندات'), cpcc_ui_english_label('Manage commercial documents and shipment files published for customers.', 'إدارة المستندات التجارية وملفات الشحن المنشورة للعملاء'), 'fa-folder-open', array('socid'=>$socid));

print '<main class="cpcc-dashboard cpcc-documents-page" dir="'.cpcc_ui_english_label('ltr', 'rtl').'">';
print '<header class="cpcc-page-head"><div>';
print '<span class="cpcc-eyebrow">CUSTOMER DOCUMENTS</span>';
print cpcc_ui_english_label('<h1>Documents</h1>', '<h1>المستندات / Documents</h1>');
print '<p>'.($is_external ? cpcc_ui_english_label('Published shipment documents and gallery files in one place.', 'مستندات وملفات الشحن المنشورة للعميل في مكان واحد. / Published shipment documents and gallery files in one place.') : cpcc_ui_english_label('Published commercial and shipment documents in one place.', 'المستندات التجارية وملفات الشحن المنشورة للعميل في مكان واحد. / Published commercial and shipment documents in one place.')).'</p>';
print '</div></header>';
if ($socid > 0) {
    print cpcc_ui_english_label('<section class="cpcc-commercial-documents"><div class="cpcc-section-head"><div><h2>Commercial Documents</h2><p>Commercial invoices, packing lists, and other approved customer documents.</p></div><div class="cpcc-document-count"><span>Published</span><strong>', '<section class="cpcc-commercial-documents"><div class="cpcc-section-head"><div><h2>المستندات التجارية / Commercial Documents</h2><p>Commercial invoices, packing lists, and other approved customer documents.</p></div><div class="cpcc-document-count"><span>Published</span><strong>').count($commercial_documents).'</strong></div></div>';
    if (empty($commercial_documents)) {
        print cpcc_ui_english_label('<div class="cpcc-empty">No published commercial documents.</div>', '<div class="cpcc-empty">لا توجد مستندات تجارية منشورة لهذا العميل. / No published commercial documents.</div>');
    } else {
        print '<div class="cpcc-document-grid">';
        foreach ($commercial_documents as $commercial_document) {
            $commercial_url = cpcc_portal_scoped_url('/chinaplanetcustomercenter/export_document.php', $is_external, $socid, array('id'=>(int) $commercial_document->rowid));
            print '<a class="cpcc-document-card" href="'.dol_escape_htmltag($commercial_url).'">';
            print '<strong>'.dol_escape_htmltag($commercial_document->ref).'</strong>';
            print '<span class="cpcc-document-type"><i class="fas fa-file-alt" aria-hidden="true"></i>'.dol_escape_htmltag(cpcc_export_type_label($commercial_document->document_type)).'</span>';
            print '<small><i class="fas fa-ship" aria-hidden="true"></i>'.dol_escape_htmltag($commercial_document->shipment_ref ?: cpcc_ui_english_label('No related shipment', 'غير مرتبط بشحنة / No related shipment')).'</small>';
            print '</a>';
        }
        print '</div>';
    }
    print '</section>';
}
print '<div class="div-table-responsive-no-min">';

if ($is_external && $socid <= 0) {
    print '<div class="warning">No linked customer account was found for this portal user.</div>';
} else {
    $sql = "SELECT t.rowid, t.ref, t.container_no, t.bl_no, t.date_eta, t.fk_statut, t.entity, t.fk_soc, s.nom as customer_name"
         . " FROM ".MAIN_DB_PREFIX."chinaplanetlogistics t"
         . " LEFT JOIN ".MAIN_DB_PREFIX."societe s ON s.rowid = t.fk_soc"
         . " WHERE t.entity=".(int) $conf->entity
         . ($socid > 0 ? " AND t.fk_soc = ".((int) $socid) : "")
         . ($is_external ? " AND t.customer_visible=1" : "")
         . (!empty($search) ? " AND (t.ref LIKE '%".$db->escape($search)."%' OR t.container_no LIKE '%".$db->escape($search)."%' OR t.bl_no LIKE '%".$db->escape($search)."%')" : "")
         . " ORDER BY t.date_eta DESC, t.rowid DESC";
    $resql = $db->query($sql);

    print '<form method="GET" class="fright cpcc-documents-search">';
    if (!$is_external) print Form::selectarray('socid',$customer_options,$socid,cpcc_ui_english_label('All customers', 'كل العملاء / All customers'),0,0,cpcc_ui_english_label('aria-label="Customer"', 'aria-label="Customer / العميل"'),0,0,0,'','minwidth250',1).' ';
    print cpcc_ui_english_label('<input type="text" name="search" class="flat" placeholder="Shipment / container / BL" aria-label="Shipment, container or BL" value="', '<input type="text" name="search" class="flat" placeholder="الشحنة / الحاوية / البوليصة" aria-label="Shipment, container or BL / الشحنة أو الحاوية أو البوليصة" value="').dol_escape_htmltag($search).'"> ';
    print cpcc_ui_english_label('<input type="submit" class="button" value="Search">', '<input type="submit" class="button" value="بحث / Search">');
    print '</form>';

    if ($canwrite && !$is_external) {
        print '<form method="POST" enctype="multipart/form-data" style="clear:both;margin:0 0 12px 0;padding:10px;border:1px solid #ddd;background:#fafafa">';
        print '<input type="hidden" name="token" value="'.newToken().'">';
        print '<input type="hidden" name="action" value="upload">';
        print '<div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">';
        print cpcc_ui_english_label('<label>Shipment <span class="opacitymedium">(Search by reference, container or BL)</span><br>', '<label>الشحنة / Shipment <span class="opacitymedium">(بحث بالمرجع أو الحاوية أو البوليصة)</span><br>');
        print Form::selectarray('shipid', $ship_options, $shipid, 1, 0, 0, 'id="shipid" style="min-width:360px;max-width:100%"', 0, 0, 0, '', 'minwidth300 widthcentpercentminusx', 1);
        print '</label>';
        print '<input type="file" name="userfile" class="minwidth200">';
        print cpcc_ui_english_label('<input type="submit" class="button" value="Upload to Shipment">', '<input type="submit" class="button" value="رفع إلى الشحنة / Upload to Shipment">');
        print '</div>';
        print '<div class="opacitymedium" style="margin-top:6px">Upload the file to the shipment folder used by the customer portal.</div>';
        print '</form>';
    }

    print '<table class="noborder centpercent'.($is_external ? ' cpcc-mobile-cards cpcc-gallery-table' : '').'">';
    if ($is_external) {
        print '<thead><tr class="liste_titre cpcc-mobile-header-row"><th>'.$langs->trans('CPLGalleryShipment').'</th><th>'.$langs->trans('CPLGalleryContainer').'</th><th>'.$langs->trans('CPLGalleryBillOfLading').'</th><th>'.$langs->trans('CPLGalleryEta').'</th><th>'.$langs->trans('CPLShipmentFilesColumn').'</th><th>'.$langs->trans('CPLGalleryAction').'</th></tr></thead><tbody>';
    } else {
        print cpcc_ui_english_label('<tr class="liste_titre"><th>ID</th><th>Shipment</th><th>Customer</th><th>Container</th><th>BL</th><th>ETA</th><th>Status</th><th>Documents</th><th>Actions</th></tr>', '<tr class="liste_titre"><th>رقم / ID</th><th>الشحنة / Shipment</th><th>العميل / Customer</th><th>الحاوية / Container</th><th>البوليصة / BL</th><th>الوصول / ETA</th><th>الحالة / Status</th><th>المستندات / Documents</th><th>الإجراءات / Actions</th></tr>');
    }
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
            if ($is_external) {
                $publishedGroups = cpcc_portal_customer_shipment_file_groups(cpcc_portal_customer_gallery_items($db, $user, $obj));
                print '<tr>';
                print '<td data-label="'.dol_escape_htmltag($langs->trans('CPLGalleryShipment')).'"><a href="'.dol_buildpath('/chinaplanetlogistics/card.php', 1).'?id='.(int) $obj->rowid.'">'.dol_escape_htmltag($obj->ref).'</a></td>';
                print '<td data-label="'.dol_escape_htmltag($langs->trans('CPLGalleryContainer')).'">'.dol_escape_htmltag($obj->container_no ?: '-').'</td>';
                print '<td data-label="'.dol_escape_htmltag($langs->trans('CPLGalleryBillOfLading')).'">'.dol_escape_htmltag($obj->bl_no ?: '-').'</td>';
                print '<td data-label="'.dol_escape_htmltag($langs->trans('CPLGalleryEta')).'">'.(!empty($obj->date_eta) ? dol_print_date($obj->date_eta, 'day') : '-').'</td>';
                print '<td data-label="'.dol_escape_htmltag($langs->trans('CPLShipmentFilesColumn')).'"><div class="cpcc-files-summary"><strong>'.dol_escape_htmltag($langs->trans('CPLShipmentDocumentsTitle')).'</strong>'.cpcc_portal_customer_document_items_html($publishedGroups['documents']).'<strong>'.dol_escape_htmltag($langs->trans('CPLCustomerUploadedFilesTitle')).'</strong>'.cpcc_portal_customer_upload_items_html($publishedGroups['customer_uploads']).'<strong>'.dol_escape_htmltag($langs->trans('CPLGalleryCustomerTitle')).'</strong>'.cpcc_portal_customer_gallery_items_html($publishedGroups['gallery']).'</div></td>';
                print '<td data-label="'.dol_escape_htmltag($langs->trans('CPLGalleryAction')).'"><a class="button button-small" href="'.dol_buildpath('/chinaplanetlogistics/card.php', 1).'?id='.(int) $obj->rowid.'">'.dol_escape_htmltag($langs->trans('CPLGalleryOpenShipment')).'</a></td>';
                print '</tr>';
                continue;
            }
            print '<tr>';
            print '<td><b>'.(int) $obj->rowid.'</b></td>';
            print '<td><a href="'.dol_buildpath('/chinaplanetlogistics/card.php', 1).'?id='.(int) $obj->rowid.'">'.dol_escape_htmltag($obj->ref).'</a></td>';
            print '<td>'.dol_escape_htmltag($obj->customer_name ?: '-').'</td>';
            print '<td>'.dol_escape_htmltag($obj->container_no).'</td>';
            print '<td>'.dol_escape_htmltag($obj->bl_no).'</td>';
            print '<td>'.(!empty($obj->date_eta) ? dol_print_date($obj->date_eta, 'day') : '-').'</td>';
            print '<td>'.dol_escape_htmltag(chinaplanetlogistics_status_label($obj->fk_statut)).'</td>';
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
                        if ($candelete) print '<button class="button button-small" type="submit" name="action" value="deletefile" onclick="return confirm(\'Remove this file from active use and move it to the private archive?\')">Delete / Archive file</button>';
                        print '</form>';
                    }
                    print '</div>';
                }
            }
            print '</td>';
            print '</tr>';
        }
    } else {
        print '<tr><td colspan="'.($is_external ? '6' : '9').'" class="opacitymedium center" data-cpcc-empty-row="1">'.dol_escape_htmltag($is_external ? $langs->trans('CPLGalleryNoShipments') : cpcc_ui_english_label('No shipments with documents found.', 'لا توجد شحنات أو مستندات / No shipments with documents found.')).'</td></tr>';
    }
    if ($is_external) print '</tbody>';
    print '</table>';
}

print '</div>';
print '</main>';
if (!$is_external) cpcc_admin_shell_close();
llxFooter();
