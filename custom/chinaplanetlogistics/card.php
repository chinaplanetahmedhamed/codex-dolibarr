<?php
$csrfRequestMethod = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$csrfRequestedAction = isset($_GET['action']) && is_string($_GET['action']) ? $_GET['action'] : '';
$csrfReadOnlyActions = array('create', 'edit', 'confirm_delete');
if ($csrfRequestMethod !== 'GET' || !in_array($csrfRequestedAction, $csrfReadOnlyActions, true)) {
    define('CSRFCHECK_WITH_TOKEN', 1);
}
/**
 * China Planet Logistics - Shipment Card (Create / Edit / View / Delete)
 * FIXED: Merged stable CRUD logic + new fields (fk_project, customer_note, booking_no, voyage_no, nb_containers, freetime_start)
 */
@error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);

require_once __DIR__.'/lib/main.lib.php';
require_once __DIR__.'/lib/chinaplanetlogistics.lib.php';

require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formprojet.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once __DIR__.'/../chinaplanetcustomercenter/lib/portal.lib.php';
require_once __DIR__.'/../chinaplanetcustomercenter/lib/admin_shell.lib.php';
require_once __DIR__.'/class/chinaplanetlogistics.class.php';

$langs->loadLangs(array("companies", "orders", "projects", "sendings", "other"));

$id      = GETPOST('id', 'int');
$action  = GETPOST('action', 'alpha');
$confirm = GETPOST('confirm', 'alpha');

// Security
$canwrite = ($user->hasRight('chinaplanetlogistics', 'write') || $user->hasRight('chinaplanetcustomercenter', 'write') || !empty($user->admin));
$canread  = ($user->hasRight('chinaplanetlogistics', 'read')  || $user->hasRight('chinaplanetcustomercenter', 'read') || !empty($user->admin));
$candelete = ($user->hasRight('chinaplanetlogistics', 'delete') || $user->hasRight('chinaplanetcustomercenter', 'delete') || !empty($user->admin));
if (empty($user->id) || !$canread) accessforbidden();

$object = new ChinaPlanetLogistics($db);
if ($id > 0) {
    $object->fetch($id);
} elseif ($action === 'create') {
    $object->fk_soc = GETPOSTINT('socid');
    $object->fk_project = GETPOSTINT('projectid');
}

// Customer security boundary: external users can only view shipments linked to their own third party.
$is_external_customer = (!empty($user->socid) && empty($user->admin));
$is_modal = ($is_external_customer && GETPOSTINT('modal') === 1);
if ($is_external_customer) {
    $canwrite = false;
    $candelete = false;
    if ($id > 0) {
        if (empty($object->id) || (int) $object->fk_soc !== (int) $user->socid) {
            if ($is_modal) {
                http_response_code(403);
                header('Content-Type: text/html; charset=UTF-8');
                print '<!doctype html><html lang="ar" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>body{margin:0;padding:24px;background:#fff;color:#172033;font-family:Arial,Tahoma,sans-serif}.warning{padding:16px;border:1px solid #f1c40f;border-radius:10px;background:#fff8dc}</style></head><body><div class="warning">الشحنة غير موجودة أو لا تملك صلاحية عرضها.</div></body></html>';
                $db->close();
                exit;
            }
            cpcc_portal_access_denied($user, 'shipments');
        }
    }
}

$listurl = DOL_URL_ROOT.'/custom/chinaplanetlogistics/list.php';
$is_post = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST';

$linksAreValid = function ($socid, $projectid) use ($db, $conf) {
    $socid = (int) $socid;
    $projectid = (int) $projectid;
    if ($socid <= 0) return false;
    $res = $db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."societe WHERE rowid=".$socid." AND entity=".(int) $conf->entity." AND status=1");
    if (!$res || $db->num_rows($res) !== 1) return false;
    if ($projectid <= 0) return true;
    $res = $db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."projet WHERE rowid=".$projectid." AND entity=".(int) $conf->entity." AND fk_soc=".$socid);
    return $res && $db->num_rows($res) === 1;
};

/*
 * ACTIONS
 */

// CREATE
if ($action == 'add' && $canwrite && $is_post) {
    $object->ref              = GETPOST('ref', 'alpha');
    $object->fk_soc           = (int) GETPOST('fk_soc', 'int');
    $object->fk_project       = (int) GETPOST('fk_project', 'int');
    $object->container_no     = GETPOST('container_no', 'alpha');
    $object->container_type   = GETPOST('container_type', 'alpha');
    $object->nb_containers    = (int) GETPOST('nb_containers', 'int');
    $object->booking_no       = GETPOST('booking_no', 'alpha');
    $object->bl_no            = GETPOST('bl_no', 'alpha');
    $object->shipping_line    = GETPOST('shipping_line', 'alpha');
    $object->vessel_name      = GETPOST('vessel_name', 'alpha');
    $object->voyage_no        = GETPOST('voyage_no', 'alpha');
    $object->port_loading     = GETPOST('port_loading', 'alpha');
    $object->port_destination = GETPOST('port_destination', 'alpha');
    $object->date_etd         = dol_mktime(0,0,0, GETPOST('etd_month','int'), GETPOST('etd_day','int'), GETPOST('etd_year','int'));
    $object->date_eta         = dol_mktime(0,0,0, GETPOST('eta_month','int'), GETPOST('eta_day','int'), GETPOST('eta_year','int'));
    $object->date_ata         = dol_mktime(0,0,0, GETPOST('ata_month','int'), GETPOST('ata_day','int'), GETPOST('ata_year','int'));
    $object->freetime_days    = (int) GETPOST('freetime_days', 'int');
    $object->freetime_start   = dol_mktime(0,0,0, GETPOST('fts_month','int'), GETPOST('fts_day','int'), GETPOST('fts_year','int'));
    $object->freetime_end     = dol_mktime(0,0,0, GETPOST('fte_month','int'), GETPOST('fte_day','int'), GETPOST('fte_year','int'));
    $object->fk_statut        = (int) GETPOST('fk_statut', 'int');
    $object->notes            = GETPOST('notes', 'restricthtml');
    $object->customer_note    = GETPOST('customer_note', 'restricthtml');

    if (!$linksAreValid($object->fk_soc, $object->fk_project)) accessforbidden();

    // Auto-ref if empty
    if (empty($object->ref) || $object->ref == 'AUTO') {
        $object->ref = 'LOG-'.date('Ymd-His');
    }

    $newid = $object->create($user);
    if ($newid > 0) {
        header("Location: ".$_SERVER['PHP_SELF']."?id=".$newid);
        exit;
    } else {
        setEventMessages($object->error, $object->errors, 'errors');
        $action = 'create';
    }
}

// UPDATE
if ($action == 'update' && $canwrite && $is_post) {
    $object->ref              = GETPOST('ref', 'alpha');
    $object->fk_soc           = (int) GETPOST('fk_soc', 'int');
    $object->fk_project       = (int) GETPOST('fk_project', 'int');
    $object->container_no     = GETPOST('container_no', 'alpha');
    $object->container_type   = GETPOST('container_type', 'alpha');
    $object->nb_containers    = (int) GETPOST('nb_containers', 'int');
    $object->booking_no       = GETPOST('booking_no', 'alpha');
    $object->bl_no            = GETPOST('bl_no', 'alpha');
    $object->shipping_line    = GETPOST('shipping_line', 'alpha');
    $object->vessel_name      = GETPOST('vessel_name', 'alpha');
    $object->voyage_no        = GETPOST('voyage_no', 'alpha');
    $object->port_loading     = GETPOST('port_loading', 'alpha');
    $object->port_destination = GETPOST('port_destination', 'alpha');
    $object->date_etd         = dol_mktime(0,0,0, GETPOST('etd_month','int'), GETPOST('etd_day','int'), GETPOST('etd_year','int'));
    $object->date_eta         = dol_mktime(0,0,0, GETPOST('eta_month','int'), GETPOST('eta_day','int'), GETPOST('eta_year','int'));
    $object->date_ata         = dol_mktime(0,0,0, GETPOST('ata_month','int'), GETPOST('ata_day','int'), GETPOST('ata_year','int'));
    $object->freetime_days    = (int) GETPOST('freetime_days', 'int');
    $object->freetime_start   = dol_mktime(0,0,0, GETPOST('fts_month','int'), GETPOST('fts_day','int'), GETPOST('fts_year','int'));
    $object->freetime_end     = dol_mktime(0,0,0, GETPOST('fte_month','int'), GETPOST('fte_day','int'), GETPOST('fte_year','int'));
    $object->fk_statut        = (int) GETPOST('fk_statut', 'int');
    $object->notes            = GETPOST('notes', 'restricthtml');
    $object->customer_note    = GETPOST('customer_note', 'restricthtml');

    if (!$linksAreValid($object->fk_soc, $object->fk_project)) accessforbidden();

    $resu = $object->update($user);
    if ($resu >= 0) {
        setEventMessages('Shipment updated successfully.', null, 'mesgs');
        header("Location: ".$_SERVER['PHP_SELF']."?id=".$id);
        exit;
    } else {
        setEventMessages($object->error, $object->errors, 'errors');
        $action = 'edit';
    }
}

// DELETE (confirmed)
if ($action === 'confirm_delete' && !$candelete) accessforbidden();
if ($action == 'confirm_delete' && $confirm == 'yes' && $candelete && $is_post) {
    $result = $object->delete($user);
    if ($result > 0) {
        header("Location: ".$listurl);
        exit;
    } else {
        setEventMessages($object->error, $object->errors, 'errors');
        $action = '';
    }
}

// FILE UPLOAD
if (GETPOST('sendit') && $id > 0 && $canwrite && $is_post) {
    // DOL_DATA_ROOT = /var/www/documents — dir_output not defined in module descriptor
    $upload_dir = cpcc_portal_shipment_doc_root($object->id);
    $upload = $_FILES['userfile'] ?? array();
    $uploadres = cpcc_portal_store_uploaded_shipment_file($object->id, $upload_dir, $upload['name'] ?? '', $upload['tmp_name'] ?? '', $upload['size'] ?? 0, $upload['error'] ?? UPLOAD_ERR_NO_FILE);
    if (!empty($uploadres['ok'])) {
        setEventMessages('Document uploaded successfully.', null, 'mesgs');
    } else {
        setEventMessages(!empty($uploadres['error']) ? $uploadres['error'] : 'Upload failed.', null, 'errors');
    }
}

/*
 * VIEW
 */
if ($is_modal) {
    header('Content-Type: text/html; charset=UTF-8');
    print '<!doctype html><html lang="ar" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    print '<style>body{margin:0;padding:20px;background:#fff;color:#172033;font-family:Arial,Tahoma,sans-serif;line-height:1.45}h1,h2,h3{margin-top:0}.fichecenter,.fichehalfleft,.fichehalfright{width:100%}.fiche{margin:0}.fiche-title{margin-bottom:18px}.border{width:100%;border-collapse:collapse;background:#fff}.border td{padding:10px 12px;border-bottom:1px solid #e5e7eb;text-align:start}.border tr:nth-child(even){background:#f8fafc}.titlefield{width:220px}.tabsAction{display:none}.opacitymedium{color:#6b7280}@media(max-width:640px){body{padding:12px}.border td{padding:8px;display:block;width:auto!important}.border tr{display:block;padding:6px 0}}</style>';
    print '</head><body class="cpl-modal-body">';
} else {
    $cardCss=array(cpcc_admin_shell_asset('/custom/chinaplanetcustomercenter/css/ux_fixes.css'));
    if(!$is_external_customer) $cardCss[]=cpcc_admin_shell_asset('/custom/chinaplanetcustomercenter/css/admin_dashboard.css');
    llxHeader('', 'Shipment Card / بطاقة الشحنة', '', '', 0, 0, array(), $cardCss);
    if($is_external_customer) cpcc_portal_navigation($user, 'shipments');
    else cpcc_admin_shell_open('shipments','Shipment Details / تفاصيل الشحنة','بيانات الحاوية والمواعيد والوقت الحر والمستندات','fa-ship',array('socid'=>(int)$object->fk_soc,'projectid'=>(int)$object->fk_project));
}

$form = new Form($db);
$formproject = new FormProjets($db);

$statuses = chinaplanetlogistics_status_map();
$types = array('20GP'=>'20GP','40GP'=>'40GP','40HQ'=>'40HQ','45HQ'=>'45HQ','LCL'=>'LCL','Other'=>'Other');

// ─── CONFIRM DELETE PAGE ──────────────────────────────────────────────────
if ($action == 'confirm_delete' && $confirm != 'yes') {
    print load_fiche_titre('Delete Shipment / حذف الشحنة', '', 'delete');
    print '<br>';
    print '<div style="background:#fff3cd;border:1px solid #ffc107;border-radius:8px;padding:20px;margin:10px 0;">';
    print '<b style="font-size:16px;">⚠️ Are you sure you want to permanently delete this shipment?</b><br><br>';
    print '<table>';
    print '<tr><td style="padding:4px 12px 4px 0;color:#666;">Reference:</td><td><b>'.dol_escape_htmltag($object->ref).'</b></td></tr>';
    print '<tr><td style="padding:4px 12px 4px 0;color:#666;">Container:</td><td><b>'.dol_escape_htmltag($object->container_no).'</b></td></tr>';
    print '<tr><td style="padding:4px 12px 4px 0;color:#666;">BL No:</td><td><b>'.dol_escape_htmltag($object->bl_no).'</b></td></tr>';
    print '</table>';
    print '</div>';
    print '<form action="'.$_SERVER["PHP_SELF"].'" method="POST">';
    print '<input type="hidden" name="token" value="'.newToken().'">';
    print '<input type="hidden" name="id" value="'.$id.'">';
    print '<input type="hidden" name="action" value="confirm_delete">';
    print '<input type="hidden" name="confirm" value="yes">';
    print '<div class="center" style="margin-top:20px;">';
    print '<input type="submit" class="butActionDelete" value="Yes, Delete Permanently">';
    print ' &nbsp; ';
    print '<a class="butAction" href="'.$_SERVER['PHP_SELF'].'?id='.$id.'">Cancel / إلغاء</a>';
    print '</div>';
    print '</form>';
    if(!$is_external_customer) cpcc_admin_shell_close();
    llxFooter();
    $db->close();
    exit;
}

// ─── CREATE / EDIT FORM ──────────────────────────────────────────────────
if ($action == 'create' || $action == 'edit') {
    if (!$canwrite) accessforbidden();

    $title = ($action == 'create') ? 'New Shipment / شحنة جديدة' : 'Edit Shipment / تعديل الشحنة';
    print load_fiche_titre($title, '', 'generic');

    print '<form action="'.$_SERVER["PHP_SELF"].'" method="POST">';
    print '<input type="hidden" name="token" value="'.newToken().'">';
    print '<input type="hidden" name="action" value="'.($action == 'create' ? 'add' : 'update').'">';
    if ($id > 0) print '<input type="hidden" name="id" value="'.$id.'">';

    print dol_get_fiche_head(array(), '', '', 0, '');
    print '<table class="border centpercent">';

    // Reference
    print '<tr><td class="titlefieldcreate">Reference / المرجع</td><td>';
    print '<input type="text" name="ref" value="'.dol_escape_htmltag($action == 'create' ? 'AUTO' : $object->ref).'" style="width:300px">';
    print ' <span class="opacitymedium">(leave AUTO for auto-generate)</span></td></tr>';

    // Customer
    print '<tr><td class="titlefieldcreate fieldrequired">Customer / العميل</td><td>';
    print $form->select_company($object->fk_soc, 'fk_soc', '', '-- Select Customer --', 1);
    print '</td></tr>';

    // Project
    print '<tr><td>Project / المشروع</td><td>';
    print $formproject->select_projects(-1, $object->fk_project, 'fk_project', 0, 0, 1, 0, 0, 0, 0, '', 1);
    print '</td></tr>';

    // Container
    print '<tr><td class="fieldrequired">Container Number / رقم الحاوية</td><td>';
    print '<input type="text" name="container_no" value="'.dol_escape_htmltag($object->container_no).'" style="width:300px"></td></tr>';

    print '<tr><td>Container Type / نوع الحاوية</td><td>';
    print $form->selectarray('container_type', $types, $object->container_type, 1);
    print '</td></tr>';

    print '<tr><td>Nb Containers / عدد الحاويات</td><td>';
    print '<input type="number" name="nb_containers" value="'.dol_escape_htmltag($object->nb_containers ? $object->nb_containers : 1).'" style="width:100px"></td></tr>';

    print '<tr><td>Booking No / رقم الحجز</td><td>';
    print '<input type="text" name="booking_no" value="'.dol_escape_htmltag($object->booking_no).'" style="width:300px"></td></tr>';

    print '<tr><td class="fieldrequired">BL Number / رقم البوليصة</td><td>';
    print '<input type="text" name="bl_no" value="'.dol_escape_htmltag($object->bl_no).'" style="width:300px"></td></tr>';

    print '<tr><td>Shipping Line / خط الملاحة</td><td>';
    print '<input type="text" name="shipping_line" value="'.dol_escape_htmltag($object->shipping_line).'" style="width:300px"></td></tr>';

    print '<tr><td>Vessel Name / اسم السفينة</td><td>';
    print '<input type="text" name="vessel_name" value="'.dol_escape_htmltag($object->vessel_name).'" style="width:300px"></td></tr>';

    print '<tr><td>Voyage No / رقم الرحلة</td><td>';
    print '<input type="text" name="voyage_no" value="'.dol_escape_htmltag($object->voyage_no).'" style="width:300px"></td></tr>';

    print '<tr><td>Port of Loading / ميناء الشحن</td><td>';
    print '<input type="text" name="port_loading" value="'.dol_escape_htmltag($object->port_loading).'" style="width:300px"></td></tr>';

    print '<tr><td>Port of Destination / ميناء الوصول</td><td>';
    print '<input type="text" name="port_destination" value="'.dol_escape_htmltag($object->port_destination).'" style="width:300px"></td></tr>';

    print '<tr><td>ETD (Sailing) / تاريخ الإبحار</td><td>';
    print $form->selectDate($object->date_etd ? $object->date_etd : -1, 'etd_', 0, 0, 1, "card", 1, 0);
    print '</td></tr>';

    print '<tr><td class="fieldrequired">ETA (Arrival) / الوصول المتوقع</td><td>';
    print $form->selectDate($object->date_eta ? $object->date_eta : -1, 'eta_', 0, 0, 1, "card", 1, 0);
    print '</td></tr>';

    print '<tr><td>ATA (Actual Arrival) / الوصول الفعلي</td><td>';
    print $form->selectDate($object->date_ata ? $object->date_ata : -1, 'ata_', 0, 0, 1, "card", 1, 0);
    print '</td></tr>';

    print '<tr><td>Free Time (Days) / أيام الوقت الحر</td><td>';
    print '<input type="number" id="freetime_days" name="freetime_days" min="0" value="'.dol_escape_htmltag($object->freetime_days).'" style="width:100px">';
    print ' <span class="opacitymedium">Calendar days / أيام تقويمية</span></td></tr>';

    print '<tr><td>Free Time Start / بداية الوقت الحر</td><td>';
    print $form->selectDate($object->freetime_start ? $object->freetime_start : -1, 'fts_', 0, 0, 1, "card", 1, 0, 1);
    print ' <span class="opacitymedium">Automatic from ATA / تلقائي من الوصول الفعلي</span>';
    print '</td></tr>';

    print '<tr><td>Free Time End / نهاية الوقت الحر</td><td>';
    print $form->selectDate($object->freetime_end ? $object->freetime_end : -1, 'fte_', 0, 0, 1, "card", 1, 0, 1);
    print ' <span class="opacitymedium">ATA + Free Time Days / الوصول الفعلي + الأيام</span>';
    print '</td></tr>';

    print '<tr><td>Status / الحالة</td><td>';
    print $form->selectarray('fk_statut', $statuses, $object->fk_statut);
    print '</td></tr>';

    print '<tr><td>Internal Notes / ملاحظات داخلية</td><td>';
    print '<textarea name="notes" rows="3" style="width:95%;">'.dol_escape_htmltag($object->notes).'</textarea>';
    print '</td></tr>';

    print '<tr><td>Customer Notes / ملاحظات العميل</td><td>';
    print '<textarea name="customer_note" rows="3" style="width:95%;">'.dol_escape_htmltag($object->customer_note).'</textarea>';
    print '</td></tr>';

    print '</table>';
    print dol_get_fiche_end();

    print '<div class="center">';
    $btn_label = ($action == 'create') ? 'Save / حفظ' : 'Update / تحديث';
    print '<input type="submit" class="button button-save" value="'.$btn_label.'">';
    print ' &nbsp; ';
    $cancel_url = ($id > 0) ? $_SERVER['PHP_SELF'].'?id='.$id : $listurl;
    print '<a class="button button-cancel" href="'.$cancel_url.'">Cancel / إلغاء</a>';
    print '</div>';
    print '</form>';
    print '<script src="'.dol_buildpath('/chinaplanetlogistics/js/card-freetime.js', 1).'?v=20260714"></script>';

} else {
    // ─── VIEW MODE ────────────────────────────────────────────────────────
    if ($id <= 0 || empty($object->id)) {
        print '<div class="warning">Shipment not found. <a href="'.$listurl.'">Back to list</a></div>';
        if ($is_modal) print '</body></html>'; else { if(!$is_external_customer) cpcc_admin_shell_close(); llxFooter(); }
        $db->close();
        exit;
    }

    // Customer name
    $customer_name = '';
    if ($object->fk_soc > 0) {
        $r = $db->query("SELECT nom FROM ".MAIN_DB_PREFIX."societe WHERE rowid=".(int) $object->fk_soc." AND entity=".(int) $conf->entity);
        if ($r && ($o = $db->fetch_object($r))) $customer_name = $o->nom;
    }

    // Project name
    $project_label = '';
    if ($object->fk_project > 0) {
        $r = $db->query("SELECT ref,title FROM ".MAIN_DB_PREFIX."projet WHERE rowid=".(int) $object->fk_project." AND entity=".(int) $conf->entity." AND fk_soc=".(int) $object->fk_soc);
        if ($r && ($o = $db->fetch_object($r))) $project_label = $o->ref.' - '.$o->title;
    }

    // Countdown
    $now      = time();
    $eta_days = ($object->date_eta)     ? ceil(($object->date_eta - $now) / 86400)     : null;
    $ft_days  = ($object->freetime_end) ? ceil(($object->freetime_end - $now) / 86400) : null;

    function cpl_color($days) {
        if ($days === null) return '#95a5a6';
        if ($days < 0)   return '#2c3e50';
        if ($days <= 3)  return '#c0392b';
        if ($days <= 7)  return '#e74c3c';
        if ($days <= 10) return '#e67e22';
        if ($days <= 14) return '#f39c12';
        return '#27ae60';
    }

    // Countdown badges
    $eta_label = ($eta_days !== null) ? ($eta_days < 0 ? 'Arrived' : $eta_days.'d') : '-';
    $ft_label  = ($ft_days  !== null) ? ($ft_days  < 0 ? 'Expired' : $ft_days.'d')  : '-';

    print '<main class="cpl-detail">';
    print '<header class="cpl-detail-hero"><span class="fas fa-ship" aria-hidden="true"></span><h1>'.dol_escape_htmltag($object->ref).'</h1><p><strong>'.dol_escape_htmltag($customer_name).'</strong> · '.dol_escape_htmltag($object->container_no ?: 'No container').' · '.dol_escape_htmltag($project_label).'</p></header>';
    print '<div class="cpl-detail-grid"><div class="cpl-metric" style="background:'.cpl_color($eta_days).';"><strong>'.$eta_label.'</strong><span>أيام الوصول / Days to Arrival</span></div><div class="cpl-metric" style="background:'.cpl_color($ft_days).';"><strong>'.$ft_label.'</strong><span>الوقت الحر المتبقي / Free Time Left</span></div></div>';

    // Details table
    $status_colors = array(0=>'#95a5a6',1=>'#3498db',8=>'#d68910',9=>'#1f8a70',2=>'#2980b9',3=>'#e67e22',4=>'#8e44ad',5=>'#16a085',6=>'#27ae60',7=>'#7f8c8d');
    $sc = isset($status_colors[$object->fk_statut]) ? $status_colors[$object->fk_statut] : '#95a5a6';
    $sl = isset($statuses[$object->fk_statut]) ? $statuses[$object->fk_statut] : '-';

    $details=array('العميل / Customer'=>$customer_name,'المشروع / Project'=>$project_label,'الحاوية / Container'=>$object->container_no.' · '.$object->container_type,'عدد الحاويات / Quantity'=>$object->nb_containers,'رقم الحجز / Booking'=>$object->booking_no,'البوليصة / BL'=>$object->bl_no,'خط الملاحة / Shipping Line'=>$object->shipping_line,'السفينة / Vessel'=>$object->vessel_name,'الرحلة / Voyage'=>$object->voyage_no,'ميناء الشحن / Loading Port'=>$object->port_loading,'ميناء الوصول / Destination'=>$object->port_destination,'الإبحار / ETD'=>$object->date_etd?dol_print_date($object->date_etd,'day'):'-','الوصول المتوقع / ETA'=>$object->date_eta?dol_print_date($object->date_eta,'day'):'-','الوصول الفعلي / ATA'=>$object->date_ata?dol_print_date($object->date_ata,'day'):'-','أيام الوقت الحر / Free Time Days'=>$object->freetime_days?(int)$object->freetime_days:'-','بداية الوقت الحر / Start'=>$object->freetime_start?dol_print_date($object->freetime_start,'day'):'-','نهاية الوقت الحر / End'=>$object->freetime_end?dol_print_date($object->freetime_end,'day'):'-','الحالة / Status'=>$sl);
    print '<section class="cpl-info-grid">';foreach($details as $label=>$value)print '<div class="cpl-info"><span>'.dol_escape_htmltag($label).'</span><strong>'.dol_escape_htmltag($value?:'-').'</strong></div>';print '</section>';

    // Action buttons
    print '<div class="cpl-detail-actions">';
    if ($canwrite || $candelete) {
        if ($canwrite) {
        print '<a href="'.$_SERVER['PHP_SELF'].'?id='.$id.'&action=edit"><span class="fas fa-edit"></span> تعديل / Edit</a>';
        }
        if ($candelete) print '<a class="danger" href="'.$_SERVER['PHP_SELF'].'?id='.$id.'&action=confirm_delete"><span class="fas fa-trash"></span> حذف / Delete</a>';
    }
    print '<a href="'.dol_buildpath('/chinaplanetcustomercenter/documents.php', 1).'?shipid='.(int) $id.'"><span class="fas fa-folder-open"></span> المستندات / Documents</a>';
    print '<a href="'.$listurl.'"><span class="fas fa-arrow-left"></span> العودة للقائمة / Back</a>';
    print '</div>';

    // Documents Section
    print '<section class="cpl-doc-panel">';
    print '<h3>Documents / المستندات</h3>';
    if ($canwrite) {
        print '<form action="'.$_SERVER['PHP_SELF'].'?id='.$id.'" method="POST" enctype="multipart/form-data" style="margin-bottom:15px">';
        print '<input type="hidden" name="token" value="'.newToken().'">';
        print '<input type="hidden" name="sendit" value="1">';
        print '<input type="file" name="userfile"> <input type="submit" class="button" value="Upload / رفع">';
        print '</form>';
    }

    $filearray = array();
    if (!$is_external_customer) {
        foreach (cpcc_portal_shipment_doc_roots($object->id, $object->ref) as $docroot) {
            if (!is_dir($docroot)) continue;
            $docs = dol_dir_list($docroot, 'files', 0, '', '(\\.meta|_preview.*\\.png)$', 'date', SORT_DESC);
            if (!empty($docs)) {
                foreach ($docs as $doc) {
                    $doc['docroot'] = $docroot;
                    $filearray[] = $doc;
                }
            }
        }
    }

    print '<table class="border centpercent">';
    print '<tr class="liste_titre"><td>File Name</td><td align="right">Size</td><td align="center">Date</td></tr>';
    if (empty($filearray)) {
        $emptyMessage = $is_external_customer ? 'No approved customer document is available.' : 'No documents uploaded yet.';
        print '<tr><td colspan="3" class="center opacitymedium">'.dol_escape_htmltag($emptyMessage).'</td></tr>';
    } else {
        foreach ($filearray as $file) {
            $relativepath = basename($file['docroot']).'/'.$file['name'];
            $dl_url = dol_buildpath('/chinaplanetcustomercenter/download.php', 1).'?type=shipment&id='.(int) $object->id.'&file='.urlencode($relativepath);
            print '<tr><td><a href="'.$dl_url.'" target="_blank">'.dol_escape_htmltag($file['name']).'</a></td>';
            print '<td align="right">'.dol_print_size($file['size']).'</td>';
            print '<td align="center">'.dol_print_date($file['date'], 'dayhour').'</td></tr>';
        }
    }
    print '</table>';
    print '</section></main>';
}

if ($is_modal) print '</body></html>'; else { if(!$is_external_customer) cpcc_admin_shell_close(); llxFooter(); }
$db->close();
?>
