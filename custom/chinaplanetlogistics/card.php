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
require_once __DIR__.'/lib/shipment_customer.lib.php';

require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formprojet.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once __DIR__.'/../chinaplanetcustomercenter/lib/portal.lib.php';
require_once __DIR__.'/../chinaplanetcustomercenter/lib/admin_shell.lib.php';
require_once __DIR__.'/class/chinaplanetlogistics.class.php';
require_once __DIR__.'/class/ShipmentStatusEmailService.class.php';

$langs->loadLangs(array("companies", "orders", "projects", "sendings", "other", "chinaplanetlogistics@chinaplanetlogistics", "chinaplanetcustomercenter@chinaplanetcustomercenter"));

$id      = GETPOST('id', 'int');
$action  = GETPOST('action', 'alpha');
$confirm = GETPOST('confirm', 'alpha');

// Security
$canwrite = ($user->hasRight('chinaplanetlogistics', 'write') || !empty($user->admin));
$canread  = ($user->hasRight('chinaplanetlogistics', 'read')  || $user->hasRight('chinaplanetcustomercenter', 'read') || !empty($user->admin));
$candelete = ($user->hasRight('chinaplanetlogistics', 'delete') || !empty($user->admin));
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
$cansendshipmentemail = !$is_external_customer && (!empty($user->admin) || !getDolGlobalString('MAIN_USE_ADVANCED_PERMS') || $user->hasRight('expedition', 'shipping_advance', 'send'));
if ($is_external_customer) {
    $canwrite = false;
    $candelete = false;
    $cansendshipmentemail = false;
    if ($id > 0) {
        if (empty($object->id) || (int) $object->fk_soc !== (int) $user->socid) {
            if ($is_modal) {
                http_response_code(403);
                header('Content-Type: text/html; charset=UTF-8');
                $deniedRtl = ($langs->trans('DIRECTION') === 'rtl');
                print '<!doctype html><html lang="'.dol_escape_htmltag($langs->defaultlang).'" dir="'.($deniedRtl ? 'rtl' : 'ltr').'"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>body{margin:0;padding:24px;background:#fff;color:#172033;font-family:Arial,Tahoma,sans-serif}.warning{padding:16px;border:1px solid #f1c40f;border-radius:10px;background:#fff8dc}</style></head><body><div class="warning">'.dol_escape_htmltag($langs->trans('CPLShipmentAccessDenied')).'</div></body></html>';
                $db->close();
                exit;
            }
            cpcc_portal_access_denied($user, 'shipments');
        }
    }
}

$listurl = DOL_URL_ROOT.'/custom/chinaplanetlogistics/list.php';
$is_post = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST';

$parseDateTime = function ($value) {
    $value = trim((string) $value);
    if ($value === '') return null;
    $timestamp = strtotime($value);
    return $timestamp > 0 ? $timestamp : null;
};

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
    $object->date_atd         = dol_mktime(0,0,0, GETPOST('atd_month','int'), GETPOST('atd_day','int'), GETPOST('atd_year','int'));
    $object->date_eta         = dol_mktime(0,0,0, GETPOST('eta_month','int'), GETPOST('eta_day','int'), GETPOST('eta_year','int'));
    $object->date_ata         = dol_mktime(0,0,0, GETPOST('ata_month','int'), GETPOST('ata_day','int'), GETPOST('ata_year','int'));
    $object->freetime_days    = (int) GETPOST('freetime_days', 'int');
    $object->freetime_start   = dol_mktime(0,0,0, GETPOST('fts_month','int'), GETPOST('fts_day','int'), GETPOST('fts_year','int'));
    $object->freetime_end     = dol_mktime(0,0,0, GETPOST('fte_month','int'), GETPOST('fte_day','int'), GETPOST('fte_year','int'));
    $object->fk_statut        = (int) GETPOST('fk_statut', 'int');
    $object->notes            = GETPOST('notes', 'restricthtml');
    $object->customer_note    = GETPOST('customer_note', 'restricthtml');
    $object->audit_source_type = GETPOST('audit_source_type', 'alpha');
    $object->audit_source_label = GETPOST('audit_source_label', 'alphanohtml');
    $object->audit_reason_public = GETPOST('audit_reason_public', 'alphanohtml');
    $object->audit_customer_visible = GETPOSTINT('audit_customer_visible') ? 1 : 0;

    if (!$linksAreValid($object->fk_soc, $object->fk_project)) accessforbidden();

    // Auto-ref if empty
    if (empty($object->ref) || $object->ref == 'AUTO') {
        $object->ref = 'LOG-'.date('Ymd-His');
    }

    $newid = $object->create($user);
    if ($newid > 0) {
        if (!empty($object->normalization_changes)) setEventMessages($langs->trans('CPLShipmentFactsNormalized'), null, 'warnings');
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
    $object->date_atd         = dol_mktime(0,0,0, GETPOST('atd_month','int'), GETPOST('atd_day','int'), GETPOST('atd_year','int'));
    $object->date_eta         = dol_mktime(0,0,0, GETPOST('eta_month','int'), GETPOST('eta_day','int'), GETPOST('eta_year','int'));
    $object->date_ata         = dol_mktime(0,0,0, GETPOST('ata_month','int'), GETPOST('ata_day','int'), GETPOST('ata_year','int'));
    $object->freetime_days    = (int) GETPOST('freetime_days', 'int');
    $object->freetime_start   = dol_mktime(0,0,0, GETPOST('fts_month','int'), GETPOST('fts_day','int'), GETPOST('fts_year','int'));
    $object->freetime_end     = dol_mktime(0,0,0, GETPOST('fte_month','int'), GETPOST('fte_day','int'), GETPOST('fte_year','int'));
    $object->fk_statut        = (int) GETPOST('fk_statut', 'int');
    $object->notes            = GETPOST('notes', 'restricthtml');
    $object->customer_note    = GETPOST('customer_note', 'restricthtml');
    $object->audit_source_type = GETPOST('audit_source_type', 'alpha');
    $object->audit_source_label = GETPOST('audit_source_label', 'alphanohtml');
    $object->audit_reason_public = GETPOST('audit_reason_public', 'alphanohtml');
    $object->audit_customer_visible = GETPOSTINT('audit_customer_visible') ? 1 : 0;
    $sendStatusEmail = GETPOSTINT('send_status_email') ? 1 : 0;

    if (!$linksAreValid($object->fk_soc, $object->fk_project)) accessforbidden();

    $resu = $object->update($user);
    if ($resu >= 0) {
        setEventMessages($langs->trans('CPLShipmentUpdated'), null, 'mesgs');
        if (!empty($object->normalization_changes)) setEventMessages($langs->trans('CPLShipmentFactsNormalized'), null, 'warnings');
        if ($sendStatusEmail && !$cansendshipmentemail) {
            setEventMessages($langs->trans('CPLStatusEmailPermissionDenied'), null, 'warnings');
        } elseif ($sendStatusEmail && empty($object->audit_customer_visible)) {
            setEventMessages($langs->trans('CPLStatusEmailNotPublic'), null, 'warnings');
        } elseif ($sendStatusEmail && empty($object->last_status_change)) {
            setEventMessages($langs->trans('CPLStatusEmailNoStatusChange'), null, 'warnings');
        } elseif ($sendStatusEmail) {
            try {
                $emailService = new ShipmentStatusEmailService($db);
                $emailResult = $emailService->send($object, $object->last_status_change['from'], $object->last_status_change['to'], $object->audit_reason_public, $user);
                setEventMessages($langs->trans('CPLStatusEmailSent', $emailResult['recipient']), null, 'mesgs');
                if (empty($emailResult['history_recorded'])) setEventMessages($langs->trans('CPLStatusEmailHistoryWarning'), null, 'warnings');
            } catch (ShipmentStatusEmailException $e) {
                setEventMessages($langs->trans($e->getMessage()), null, 'warnings');
            } catch (Throwable $e) {
                dol_syslog('Unexpected shipment status email failure for shipment '.((int) $object->id).': '.$e->getMessage(), LOG_ERR);
                setEventMessages($langs->trans('CPLStatusEmailSendFailed'), null, 'warnings');
            }
        }
        header("Location: ".$_SERVER['PHP_SELF']."?id=".$id);
        exit;
    } else {
        setEventMessages($object->error, $object->errors, 'errors');
        $action = 'edit';
    }
}

// Retry the latest committed customer-visible status email without rewriting the shipment.
if ($action === 'resend_status_email' && $id > 0 && $canwrite && $is_post) {
    if (!$cansendshipmentemail || empty($object->id)) accessforbidden();
    try {
        $emailService = new ShipmentStatusEmailService($db);
        $emailResult = $emailService->resendLatest($object, $user);
        setEventMessages($langs->trans('CPLStatusEmailSent', $emailResult['recipient']), null, 'mesgs');
        if (empty($emailResult['history_recorded'])) setEventMessages($langs->trans('CPLStatusEmailHistoryWarning'), null, 'warnings');
    } catch (ShipmentStatusEmailException $e) {
        setEventMessages($langs->trans($e->getMessage()), null, 'warnings');
    } catch (Throwable $e) {
        dol_syslog('Unexpected shipment status email retry failure for shipment '.((int) $object->id).': '.$e->getMessage(), LOG_ERR);
        setEventMessages($langs->trans('CPLStatusEmailSendFailed'), null, 'warnings');
    }
    header('Location: '.$_SERVER['PHP_SELF'].'?id='.(int) $id);
    exit;
}

// ADD REPEATABLE TRANSSHIPMENT EVENT (internal logistics writers only)
if ($action === 'add_transshipment' && $id > 0 && $canwrite && $is_post) {
    if (empty($object->id)) accessforbidden();
    $result = cpl_add_transshipment_event($db, $object, $user, array(
        'actual_at' => $parseDateTime(GETPOST('transshipment_at', 'alphanohtml')),
        'location' => GETPOST('transshipment_location', 'alphanohtml'),
        'source_type' => GETPOST('transshipment_source_type', 'alpha'),
        'source_label_public' => GETPOST('transshipment_source_label', 'alphanohtml'),
        'note_public' => GETPOST('transshipment_note', 'alphanohtml'),
        'customer_visible' => GETPOSTINT('transshipment_customer_visible') ? 1 : 0,
    ));
    setEventMessages(!empty($result['ok']) ? $langs->trans('CPLTransshipmentSaved') : $result['error'], null, !empty($result['ok']) ? 'mesgs' : 'errors');
    header('Location: '.$_SERVER['PHP_SELF'].'?id='.(int)$id); exit;
}

// M023 retired the legacy checklist workflow. Keep old database rows for
// audit/rollback, but reject direct attempts to revive the obsolete writer.
if ($action === 'save_requirement' && $id > 0 && $canwrite && $is_post) {
    accessforbidden();
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
    $uploadres = cpcc_portal_store_uploaded_shipment_file($object->id, $upload_dir, $upload['name'] ?? '', $upload['tmp_name'] ?? '', $upload['size'] ?? 0, $upload['error'] ?? UPLOAD_ERR_NO_FILE, cpcc_portal_staff_upload_max_bytes());
    if (!empty($uploadres['ok'])) {
        setEventMessages('Document uploaded successfully.', null, 'mesgs');
    } else {
        setEventMessages(!empty($uploadres['error']) ? $uploadres['error'] : 'Upload failed.', null, 'errors');
    }
}

if ($action === 'publish_gallery' && $id > 0 && $canwrite && $is_post) {
    $publication = cpcc_portal_gallery_publish($db, $object, $user, GETPOST('file', 'none'));
    if (!empty($publication['ok'])) setEventMessages($langs->trans('CPLGalleryPublished'), null, 'mesgs');
    else setEventMessages($publication['error'], null, 'errors');
}

if ($action === 'unpublish_gallery' && $id > 0 && $canwrite && $is_post) {
    $publication = cpcc_portal_gallery_unpublish($db, $object, $user, GETPOSTINT('publication_id'));
    if (!empty($publication['ok'])) setEventMessages($langs->trans('CPLGalleryUnpublished'), null, 'mesgs');
    else setEventMessages($publication['error'], null, 'errors');
}

if ($action === 'staff_customer_upload_status' && $id > 0 && $canwrite && $is_post) {
    $status = cpcc_portal_staff_customer_upload_status($db, $object, $user, GETPOSTINT('publication_id'), GETPOST('customer_upload_status', 'alpha'), GETPOST('customer_upload_note', 'alphanohtml'));
    setEventMessages(!empty($status['ok']) ? $langs->trans('CPLCustomerUploadStatusSaved') : $status['error'], null, !empty($status['ok']) ? 'mesgs' : 'errors');
    header('Location: '.$_SERVER['PHP_SELF'].'?id='.(int)$id.'#shipment-files-manager'); exit;
}

if ($action === 'archive_shipment_file' && $id > 0 && $candelete && $is_post) {
    $archived = cpcc_portal_archive_shipment_file($db, $object, $user, GETPOST('file', 'none'));
    setEventMessages(!empty($archived['ok']) ? $langs->trans('CPLShipmentFileArchived') : $archived['error'], null, !empty($archived['ok']) ? 'mesgs' : 'errors');
    header('Location: '.$_SERVER['PHP_SELF'].'?id='.(int)$id.'#shipment-files-manager'); exit;
}

if (in_array($action, array('customer_document_confirm','customer_document_amend','customer_document_comment'), true) && $id > 0 && $is_external_customer && $is_post) {
    $responseMap = array('customer_document_confirm'=>'confirmed','customer_document_amend'=>'amendment_requested','customer_document_comment'=>'comment');
    $response = cpcc_portal_customer_document_response($db, $user, $object, GETPOST('publication', 'alphanohtml'), $responseMap[$action], GETPOST('response_note', 'alphanohtml'));
    setEventMessages(!empty($response['ok']) ? $langs->trans('CPLCustomerResponseSaved') : $response['error'], null, !empty($response['ok']) ? 'mesgs' : 'errors');
    header('Location: '.$_SERVER['PHP_SELF'].'?id='.(int)$id.'#shipment-documents'); exit;
}

if ($action === 'customer_document_upload' && $id > 0 && $is_external_customer && $is_post) {
    if (!cpcc_portal_collaboration_schema_ready($db)) accessforbidden();
    $upload = $_FILES['customer_file'] ?? array();
    $uploadDir = cpcc_portal_shipment_doc_root($object->id);
    $stored = cpcc_portal_store_uploaded_shipment_file($object->id, $uploadDir, $upload['name'] ?? '', $upload['tmp_name'] ?? '', $upload['size'] ?? 0, $upload['error'] ?? UPLOAD_ERR_NO_FILE);
    if (!empty($stored['ok'])) {
        $relativePath = 'ship-'.(int)$object->id.'/'.(string)$stored['file'];
        $stored = cpcc_portal_publish_customer_upload($db, $object, $user, $relativePath, GETPOST('response_note', 'alphanohtml'));
    }
    setEventMessages(!empty($stored['ok']) ? $langs->trans('CPLCustomerUploadSaved') : ($stored['error'] ?? $langs->trans('Error')), null, !empty($stored['ok']) ? 'mesgs' : 'errors');
    header('Location: '.$_SERVER['PHP_SELF'].'?id='.(int)$id.'#shipment-documents'); exit;
}

if ($action === 'customer_container_received' && $id > 0 && $is_external_customer && $is_post) {
    $receipt = cpcc_portal_customer_container_received($db, $user, $object, GETPOST('response_note', 'alphanohtml'));
    setEventMessages(!empty($receipt['ok']) ? $langs->trans('CPLContainerReceiptSaved') : $receipt['error'], null, !empty($receipt['ok']) ? 'mesgs' : 'errors');
    header('Location: '.$_SERVER['PHP_SELF'].'?id='.(int)$id.'#shipment-timeline'); exit;
}

if ($action === 'staff_original_document_status' && $id > 0 && $canwrite && $is_post) {
    $status = cpcc_portal_staff_original_document_status($db, $user, $object, GETPOST('original_document_status', 'alpha'), GETPOST('original_document_note', 'alphanohtml'));
    setEventMessages(!empty($status['ok']) ? $langs->trans('CPLOriginalDocumentStatusSaved') : $status['error'], null, !empty($status['ok']) ? 'mesgs' : 'errors');
    header('Location: '.$_SERVER['PHP_SELF'].'?id='.(int)$id.'#shipment-files-manager'); exit;
}

if ($action === 'customer_original_documents_received' && $id > 0 && $is_external_customer && $is_post) {
    $receipt = cpcc_portal_customer_original_documents_received($db, $user, $object, GETPOST('response_note', 'alphanohtml'));
    setEventMessages(!empty($receipt['ok']) ? $langs->trans('CPLOriginalDocumentsReceiptSaved') : $receipt['error'], null, !empty($receipt['ok']) ? 'mesgs' : 'errors');
    header('Location: '.$_SERVER['PHP_SELF'].'?id='.(int)$id.'#shipment-documents'); exit;
}

/*
 * VIEW
 */
if ($is_modal) {
    $isRtl = ($langs->trans('DIRECTION') === 'rtl');
    header('Content-Type: text/html; charset=UTF-8');
    print '<!doctype html><html lang="'.dol_escape_htmltag($langs->defaultlang).'" dir="'.($isRtl?'rtl':'ltr').'"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    print '<link rel="stylesheet" href="'.dol_buildpath('/chinaplanetlogistics/css/shipment-command-center.css', 1).'?v=20260720-m024c01">';
    print '<style>body{margin:0;padding:20px;background:#fff;color:#172033;font-family:Arial,Tahoma,sans-serif;line-height:1.45}h1,h2,h3{margin-top:0}.fichecenter,.fichehalfleft,.fichehalfright{width:100%}.fiche{margin:0}.fiche-title{margin-bottom:18px}.border{width:100%;border-collapse:collapse;background:#fff}.border td{padding:10px 12px;border-bottom:1px solid #e5e7eb;text-align:start}.border tr:nth-child(even){background:#f8fafc}.titlefield{width:220px}.tabsAction{display:none}.opacitymedium{color:#6b7280}@media(max-width:640px){body{padding:12px}.border td{padding:8px;display:block;width:auto!important}.border tr{display:block;padding:6px 0}}</style>';
    print '</head><body class="cpl-modal-body">';
} else {
    $cardCss=array(cpcc_admin_shell_asset('/custom/chinaplanetcustomercenter/css/ux_fixes.css'), dol_buildpath('/chinaplanetlogistics/css/shipment-command-center.css', 1).'?v=20260720-m024c01');
    if(!$is_external_customer) $cardCss[]=cpcc_admin_shell_asset('/custom/chinaplanetcustomercenter/css/admin_dashboard.css');
    llxHeader('', $langs->trans('CPLShipmentCardTitle'), '', '', 0, 0, array(), $cardCss);
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

    print '<tr><td>ATD (Actual Departure) / المغادرة الفعلية</td><td>';
    print $form->selectDate($object->date_atd ? $object->date_atd : -1, 'atd_', 0, 0, 1, "card", 1, 0);
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

    print '<tr><td>'.$langs->trans('CPLPublicUpdateSource').'</td><td>';
    $publicSourceOptions = array(); foreach (cpl_public_source_types() as $sourceCode=>$unusedLabel) $publicSourceOptions[$sourceCode] = cpl_public_source_label($langs, $sourceCode);
    print $form->selectarray('audit_source_type', $publicSourceOptions, 'manual_team', 0);
    print '</td></tr>';
    print '<tr><td>'.$langs->trans('CPLPublicUpdateReason').'</td><td><input type="text" name="audit_reason_public" maxlength="1000" style="width:95%"></td></tr>';
    print '<tr><td>'.$langs->trans('CPLPublishUpdate').'</td><td><input type="checkbox" name="audit_customer_visible" value="1" checked></td></tr>';
    if ($action === 'edit') {
        print '<tr><td>'.$langs->trans('CPLSendStatusEmail').'</td><td>';
        if ($cansendshipmentemail) print '<label><input type="checkbox" name="send_status_email" value="1"> '.$langs->trans('CPLSendStatusEmailHelp').'</label>';
        else print '<span class="opacitymedium">'.$langs->trans('CPLSendStatusEmailUnavailable').'</span>';
        print '</td></tr>';
    }

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
    if ($action === 'edit' && $id > 0 && !$is_external_customer) {
        print cpcc_portal_staff_shipment_files_html($db, $object, $canwrite, $_SERVER['PHP_SELF'].'?id='.(int) $id.'&action=edit', $candelete);
    } elseif ($action === 'create') {
        print '<div class="info">'.$langs->trans('CPLShipmentFilesAfterSave').'</div>';
    }

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

    if ($is_external_customer) ob_start();
    if (!$is_external_customer && ($user->admin || $user->hasRight('chinaplanetcustomercenter','email_write'))) {
        print '<div class="cpl-section-email-action"><a class="button button-save" href="'.dol_escape_htmltag(dol_buildpath('/chinaplanetcustomercenter/section_email.php',1).'?section=shipment&amp;socid='.(int)$object->fk_soc.'&amp;contextid='.(int)$object->id).'"><span class="fas fa-envelope" aria-hidden="true"></span> إرسال ملخص الشحنة / Send shipment summary</a></div>';
    }
    cpl_render_shipment_command_center($db, $object, array(
        'external'=>$is_external_customer, 'modal'=>$is_modal, 'canwrite'=>$canwrite, 'candelete'=>$candelete, 'cansendshipmentemail'=>$cansendshipmentemail,
        'customer_name'=>$customer_name, 'project_label'=>$project_label, 'listurl'=>$listurl,
        'projecturl'=>$object->fk_project > 0 ? dol_buildpath('/chinaplanetcustomercenter/project.php', 1).'?id='.(int)$object->fk_project : dol_buildpath('/chinaplanetcustomercenter/projects.php', 1),
    ));
    if ($is_external_customer) {
        $commandCenterHtml = ob_get_clean();
        $galleryHtml = cpcc_portal_customer_shipment_files_html($db, $user, $object);
        $insertBefore = '<!--CPL_SHIPMENT_FILES-->';
        $markerPosition = strpos($commandCenterHtml, $insertBefore);
        if ($markerPosition !== false) {
            $commandCenterHtml = str_replace($insertBefore, $galleryHtml, $commandCenterHtml);
        } else {
            $commandCenterHtml = str_replace('</main>', $galleryHtml.'</main>', $commandCenterHtml);
        }
        print $commandCenterHtml;
    }

    // The private file store remains internal until an explicit allowlist row
    // projects a document or media item into the owning customer portal.
    if (!$is_external_customer) {
        print cpcc_portal_staff_shipment_files_html($db, $object, $canwrite, $_SERVER['PHP_SELF'].'?id='.(int) $id, $candelete);
    }

    print '<script>(function(){var root=document.querySelector(".cpl-command-center");if(!root)return;var title=root.getAttribute("data-cpl-shipment-title")||document.title;document.title=title;try{if(window.parent!==window){var d=window.parent.document,t=d.querySelector("#cpk-card-dialog .cpk-dialog-title, #cpk-card-dialog h2");if(t)t.textContent=title;}}catch(e){}var b=document.querySelector("[data-cpl-back-project]");if(b)b.addEventListener("click",function(){var fallback=b.getAttribute("data-cpl-back-url");if(window.history.length>1)window.history.back();else if(fallback)window.location.href=fallback;});})();</script>';
}

if ($is_modal) print '</body></html>'; else { if(!$is_external_customer) cpcc_admin_shell_close(); llxFooter(); }
$db->close();
?>
