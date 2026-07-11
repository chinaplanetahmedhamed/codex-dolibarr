<?php

function cpcc_portal_user_scope($user)
{
    $socid = !empty($user->socid) ? (int) $user->socid : 0;
    $is_external = (!empty($user->socid) && empty($user->admin));
    if (!empty($user->admin)) {
        $is_external = false;
        $socid = 0;
    }
    return array('is_external' => $is_external, 'socid' => $socid);
}

function cpcc_portal_require_access($user)
{
    if (empty($user->id)) {
        accessforbidden();
    }
}

function cpcc_portal_navigation($user, $active = '')
{
    if (empty($user->id) || empty($user->socid) || !empty($user->admin)) return;

    $items = array(
        'dashboard' => array('لوحة التحكم', '/chinaplanetcustomercenter/board.php'),
        'projects' => array('المشاريع', '/chinaplanetcustomercenter/projects.php'),
        'shipments' => array('الشحنات', '/chinaplanetlogistics/list.php'),
        'invoices' => array('الفواتير', '/chinaplanetcustomercenter/invoices.php'),
        'orders' => array('الطلبات', '/chinaplanetcustomercenter/orders.php'),
        'balance' => array('الرصيد', '/chinaplanetcustomercenter/balance.php'),
        'documents' => array('المستندات', '/chinaplanetcustomercenter/documents.php'),
        'profile' => array('الملف الشخصي', '/chinaplanetcustomercenter/profile.php'),
    );

    print '<nav class="cpcc-portal-nav" dir="rtl" aria-label="تنقل بوابة العميل">';
    foreach ($items as $key => $item) {
        $class = $key === $active ? ' class="active" aria-current="page"' : '';
        print '<a'.$class.' href="'.dol_escape_htmltag(dol_buildpath($item[1], 1)).'">'.dol_escape_htmltag($item[0]).'</a>';
    }
    print '<a class="cpcc-logout-link" data-cpcc-logout href="'.dol_escape_htmltag(DOL_URL_ROOT.'/user/logout.php').'">تسجيل الخروج</a>';
    print '</nav>';
    print '<dialog class="cpcc-logout-dialog" data-cpcc-logout-dialog aria-labelledby="cpcc-logout-title" aria-describedby="cpcc-logout-message">';
    print '<form method="dialog" dir="rtl">';
    print '<h2 id="cpcc-logout-title">تأكيد تسجيل الخروج</h2>';
    print '<p id="cpcc-logout-message">هل تريد حقًا تسجيل الخروج؟<br><span lang="en" dir="ltr">Are you sure you want to log out?</span></p>';
    print '<div class="cpcc-logout-actions">';
    print '<button type="submit" value="cancel" data-cpcc-logout-cancel>إلغاء / Cancel</button>';
    print '<button type="button" class="cpcc-logout-confirm" data-cpcc-logout-confirm>تسجيل الخروج / Logout</button>';
    print '</div></form></dialog>';
    print '<script src="'.dol_escape_htmltag(dol_buildpath('/chinaplanetcustomercenter/js/logout-confirm.js', 1)).'" defer></script>';
    print '<style>#id-top,#id-left{display:none!important}#id-container{display:block!important}#id-right{margin:0!important;width:100%!important}.cpcc-portal-nav{display:flex;gap:.4rem;align-items:center;overflow-x:auto;padding:.65rem;margin:0 0 1rem;background:#17324d;border-radius:8px}.cpcc-portal-nav a{color:#fff;text-decoration:none;white-space:nowrap;padding:.55rem .7rem;border-radius:6px}.cpcc-portal-nav a:hover,.cpcc-portal-nav a.active{background:#c68b2c;color:#111}.cpcc-logout-dialog{max-width:min(92vw,28rem);border:0;border-radius:10px;padding:1.25rem;box-shadow:0 12px 40px #0005}.cpcc-logout-dialog::backdrop{background:#0008}.cpcc-logout-dialog h2{margin-top:0}.cpcc-logout-actions{display:flex;gap:.65rem;justify-content:flex-start;margin-top:1rem}.cpcc-logout-actions button{min-height:2.5rem;padding:.5rem .9rem;cursor:pointer}.cpcc-logout-confirm{background:#17324d;color:#fff;border:1px solid #17324d;border-radius:5px}@media(max-width:600px){.cpcc-portal-nav{margin-inline:-.5rem;border-radius:0}}</style>';
}

/**
 * Render a customer-safe denial page after the existing ownership check fails.
 * Internal users keep Dolibarr's standard access-denied handling.
 */
function cpcc_portal_access_denied($user, $active = '')
{
    $scope = cpcc_portal_user_scope($user);
    if (empty($scope['is_external'])) {
        accessforbidden();
    }

    http_response_code(403);
    llxHeader('', 'Access denied');
    cpcc_portal_navigation($user, $active);
    print '<div class="error" role="alert">لا يمكنك الوصول إلى هذا السجل. / You cannot access this record.</div>';
    llxFooter();
    exit;
}

function cpcc_portal_customer_name($db, $socid)
{
    $socid = (int) $socid;
    if ($socid <= 0) return '';
    $resql = $db->query("SELECT nom FROM ".MAIN_DB_PREFIX."societe WHERE rowid = ".$socid);
    if ($resql && ($obj = $db->fetch_object($resql))) return (string) $obj->nom;
    return '';
}

function cpcc_portal_money($amount)
{
    return number_format((float) $amount, 2);
}

function cpcc_portal_shipment_doc_root($shipid)
{
    return DOL_DATA_ROOT.'/chinaplanetlogistics/ship-'.(int) $shipid;
}

function cpcc_portal_shipment_doc_legacy_root($shipref)
{
    $shipref = dol_sanitizeFileName((string) $shipref);
    if (empty($shipref)) return '';
    return DOL_DATA_ROOT.'/chinaplanetlogistics/'.$shipref;
}

function cpcc_portal_shipment_doc_roots($shipid, $shipref = '')
{
    $roots = array();
    $primary = cpcc_portal_shipment_doc_root($shipid);
    if (!empty($primary)) $roots[] = $primary;
    $legacy = cpcc_portal_shipment_doc_legacy_root($shipref);
    if (!empty($legacy) && $legacy !== $primary) $roots[] = $legacy;
    return $roots;
}

/**
 * Test whether an existing path is inside an existing directory.
 * The directory separator check prevents sibling-prefix matches such as
 * ship-2-other being accepted as a child of ship-2.
 */
function cpcc_portal_path_is_within($path, $directory)
{
    $path_real = realpath($path);
    $directory_real = realpath($directory);
    if ($path_real === false || $directory_real === false) return false;

    $directory_prefix = rtrim($directory_real, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
    return $path_real === $directory_real || strpos($path_real, $directory_prefix) === 0;
}

/**
 * Resolve a customer-supplied shipment document path without trusting it.
 *
 * Portal pages historically emit either:
 * - chinaplanetlogistics/ship-N/file.ext (relative to DOL_DATA_ROOT), or
 * - ship-N/file.ext (relative to the module document root).
 * Both formats are accepted only when the resolved file belongs to one of
 * this shipment's explicitly allowed document directories.
 */
function cpcc_portal_resolve_shipment_doc_path($shipid, $shipref, $file)
{
    $shipid = (int) $shipid;
    $file = ltrim(str_replace('\\', '/', (string) $file), '/');
    if ($shipid <= 0 || $file === '' || strpos($file, "\0") !== false) return '';

    $module_prefix = 'chinaplanetlogistics/';
    $relative_to_module = strpos($file, $module_prefix) === 0 ? substr($file, strlen($module_prefix)) : $file;
    $candidate = realpath(DOL_DATA_ROOT.'/chinaplanetlogistics/'.$relative_to_module);
    if ($candidate === false || !is_file($candidate) || !is_readable($candidate)) return '';

    foreach (cpcc_portal_shipment_doc_roots($shipid, $shipref) as $docroot) {
        if (cpcc_portal_path_is_within($candidate, $docroot)) return $candidate;
    }
    return '';
}

function cpcc_portal_store_uploaded_shipment_file($shipid, $upload_dir, $original_name, $tmp_name)
{
    $shipid = (int) $shipid;
    if ($shipid <= 0 || empty($upload_dir) || empty($original_name) || empty($tmp_name)) {
        return array('ok' => false, 'error' => 'Invalid upload parameters.');
    }
    if (!is_dir($upload_dir) && !dol_mkdir($upload_dir)) {
        return array('ok' => false, 'error' => 'Cannot create shipment folder.');
    }
    if (!is_uploaded_file($tmp_name)) {
        return array('ok' => false, 'error' => 'Upload source is not valid.');
    }

    $safe_name = dol_sanitizeFileName($original_name);
    if (empty($safe_name)) {
        return array('ok' => false, 'error' => 'Invalid file name.');
    }

    $shipment_prefix = 'SHIP-'.$shipid;
    $stored_name = $shipment_prefix.'-'.$safe_name;
    $pathinfo = pathinfo($safe_name);
    $base_name = !empty($pathinfo['filename']) ? $pathinfo['filename'] : $safe_name;
    $extension = !empty($pathinfo['extension']) ? '.'.$pathinfo['extension'] : '';
    $counter = 2;
    while (file_exists($upload_dir.'/'.$stored_name)) {
        $stored_name = $shipment_prefix.'-'.$base_name.'-'.$counter.$extension;
        $counter++;
    }

    $target = $upload_dir.'/'.$stored_name;
    if (!move_uploaded_file($tmp_name, $target)) {
        return array('ok' => false, 'error' => 'Failed to move uploaded file.');
    }

    return array('ok' => true, 'file' => $stored_name, 'fullpath' => $target);
}
