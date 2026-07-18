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

function cpcc_portal_has_any_right($user, array $rights)
{
    if (empty($user->id)) return false;
    if (!empty($user->admin)) return true;

    foreach ($rights as $right) {
        if (count($right) === 2 && $user->hasRight($right[0], $right[1])) return true;
    }
    return false;
}

function cpcc_portal_require_access($user, array $rights = array(array('chinaplanetcustomercenter', 'read')))
{
    if (!cpcc_portal_has_any_right($user, $rights)) accessforbidden();

    $scope = cpcc_portal_user_scope($user);
    if (!empty($scope['is_external']) && (int) $scope['socid'] <= 0) accessforbidden();
}

/**
 * Resolve a customer scope without trusting a socid supplied in the URL.
 * External users are always locked to their linked third party.
 */
function cpcc_portal_customer_scope_id($db, $user, $requestedSocid = 0)
{
    $scope = cpcc_portal_user_scope($user);
    if (!empty($scope['is_external'])) return (int) $scope['socid'];

    $requestedSocid = (int) $requestedSocid;
    if ($requestedSocid <= 0) return 0;

    $sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."societe";
    $sql .= " WHERE rowid=".$requestedSocid." AND entity IN (".getEntity('societe').") AND status=1 AND client IN (1,3)";
    $resql = $db->query($sql);
    return ($resql && $db->num_rows($resql) > 0) ? $requestedSocid : 0;
}

/** Build a portal URL that retains the selected customer for internal users. */
function cpcc_portal_scoped_url($path, $isExternal, $socid, $parameters = array())
{
    if (!$isExternal && (int) $socid > 0) $parameters['socid'] = (int) $socid;
    $url = dol_buildpath($path, 1);
    return !empty($parameters) ? $url.'?'.http_build_query($parameters) : $url;
}

function cpcc_portal_navigation($user, $active = '')
{
    if (empty($user->id) || empty($user->socid) || !empty($user->admin)) return;

    $activeGroups = array(
        'dashboard' => 'home',
        'projects' => 'projects',
        'shipments' => 'shipments',
        'shippingprices' => 'shipments',
        'invoices' => 'account',
        'balance' => 'account',
        'funding' => 'account',
        'documents' => 'documents',
        'commercial_documents' => 'documents',
        'reports' => 'documents',
        'orders' => 'requests',
        'profile' => 'account',
    );
    $activeGroup = isset($activeGroups[$active]) ? $activeGroups[$active] : 'home';

    $items = array(
        'home' => array('الرئيسية', 'Home', 'fa-home', '/chinaplanetcustomercenter/board.php'),
        'projects' => array('مشاريعي', 'My Projects', 'fa-folder-open', '/chinaplanetcustomercenter/projects.php'),
        'shipments' => array('الشحنات', 'Shipments', 'fa-ship', '/chinaplanetlogistics/list.php'),
        'account' => array('الحساب', 'Account', 'fa-wallet', '/chinaplanetcustomercenter/balance.php'),
        'documents' => array('المستندات', 'Documents', 'fa-file-alt', '/chinaplanetcustomercenter/documents.php'),
        'requests' => array('الطلبات والدعم', 'Orders & Support', 'fa-headset', '/chinaplanetcustomercenter/orders.php'),
    );

    $homeUrl = dol_buildpath('/chinaplanetcustomercenter/board.php', 1);
    print '<link rel="stylesheet" href="'.dol_escape_htmltag(dol_buildpath('/chinaplanetcustomercenter/css/portal.css', 1).'?v=20260719m009c02').'">';
    print '<header class="cpcc-portal-shell" dir="rtl">';
    print '<a class="cpcc-portal-brand" href="'.dol_escape_htmltag($homeUrl).'" aria-label="الرئيسية / Customer Home"><span class="cpcc-portal-brand-mark" aria-hidden="true">V</span><span><strong>Vision Customer Center</strong><small>مركز العميل</small></span></a>';
    print '<button type="button" class="cpcc-portal-menu-toggle" aria-expanded="false" aria-controls="cpcc-portal-menu"><span class="fas fa-bars" aria-hidden="true"></span><span>القائمة / Menu</span></button>';
    print '<nav id="cpcc-portal-menu" class="cpcc-portal-nav" aria-label="تنقل بوابة العميل">';
    foreach ($items as $key => $item) {
        $class = $key === $activeGroup ? ' class="active" aria-current="page"' : '';
        print '<a'.$class.' href="'.dol_escape_htmltag(dol_buildpath($item[3], 1)).'">';
        print '<span class="fas '.dol_escape_htmltag($item[2]).'" aria-hidden="true"></span>';
        print '<span><b>'.dol_escape_htmltag($item[0]).'</b><small lang="en" dir="ltr">'.dol_escape_htmltag($item[1]).'</small></span></a>';
    }
    print '<a class="cpcc-logout-link" data-cpcc-logout href="'.dol_escape_htmltag(DOL_URL_ROOT.'/user/logout.php').'"><span class="fas fa-sign-out-alt" aria-hidden="true"></span><span><b>تسجيل الخروج</b><small lang="en" dir="ltr">Logout</small></span></a>';
    print '</nav></header>';
    print '<dialog class="cpcc-logout-dialog" data-cpcc-logout-dialog aria-labelledby="cpcc-logout-title" aria-describedby="cpcc-logout-message">';
    print '<form method="dialog" dir="rtl">';
    print '<h2 id="cpcc-logout-title">تأكيد تسجيل الخروج</h2>';
    print '<p id="cpcc-logout-message">هل تريد حقًا تسجيل الخروج؟<br><span lang="en" dir="ltr">Are you sure you want to log out?</span></p>';
    print '<div class="cpcc-logout-actions">';
    print '<button type="submit" value="cancel" data-cpcc-logout-cancel>إلغاء / Cancel</button>';
    print '<button type="button" class="cpcc-logout-confirm" data-cpcc-logout-confirm>تسجيل الخروج / Logout</button>';
    print '</div></form></dialog>';
    print '<script src="'.dol_escape_htmltag(dol_buildpath('/chinaplanetcustomercenter/js/portal.js', 1).'?v=20260719m009c02').'" defer></script>';
    print '<script src="'.dol_escape_htmltag(dol_buildpath('/chinaplanetcustomercenter/js/logout-confirm.js', 1)).'" defer></script>';
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
    global $conf;
    $socid = (int) $socid;
    if ($socid <= 0) return '';
    $resql = $db->query("SELECT nom FROM ".MAIN_DB_PREFIX."societe WHERE rowid=".$socid." AND entity IN (0,".(int) $conf->entity.")");
    if ($resql && ($obj = $db->fetch_object($resql))) return (string) $obj->nom;
    return '';
}

/**
 * Fetch a shipment only when it belongs to the active entity and actor scope.
 * External ownership always comes from the authenticated user's third party.
 */
function cpcc_portal_authorized_shipment($db, $user, $shipid, $requestedSocid = 0)
{
    global $conf;

    $shipid = (int) $shipid;
    if ($shipid <= 0 || empty($user->id)) return null;

    $scope = cpcc_portal_user_scope($user);
    $sql = "SELECT rowid,ref,fk_soc,entity FROM ".MAIN_DB_PREFIX."chinaplanetlogistics";
    $sql .= " WHERE rowid=".$shipid." AND entity=".(int) $conf->entity;
    if (!empty($scope['is_external'])) {
        if ((int) $scope['socid'] <= 0) return null;
        $sql .= " AND fk_soc=".(int) $scope['socid'];
    } elseif ((int) $requestedSocid > 0) {
        $sql .= " AND fk_soc=".(int) $requestedSocid;
    }

    $resql = $db->query($sql);
    return $resql ? $db->fetch_object($resql) : null;
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

function cpcc_portal_shipment_file_policy()
{
    return array(
        'pdf' => array('application/pdf'),
        'jpg' => array('image/jpeg'),
        'jpeg' => array('image/jpeg'),
        'png' => array('image/png'),
        'webp' => array('image/webp'),
        'txt' => array('text/plain'),
        'csv' => array('text/plain', 'text/csv', 'application/csv', 'application/vnd.ms-excel'),
        'doc' => array('application/msword', 'application/x-ole-storage', 'application/CDFV2'),
        'docx' => array('application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'),
        'xls' => array('application/vnd.ms-excel', 'application/x-ole-storage', 'application/CDFV2'),
        'xlsx' => array('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'),
        'mp4' => array('video/mp4'),
        'mov' => array('video/quicktime'),
    );
}

function cpcc_portal_upload_max_bytes()
{
    $configuredKb = function_exists('getDolGlobalInt') ? getDolGlobalInt('MAIN_UPLOAD_DOC') : 0;
    return $configuredKb > 0 ? $configuredKb * 1000 : 10 * 1024 * 1024;
}

function cpcc_portal_validate_shipment_filename($originalName)
{
    $basename = basename(str_replace('\\', '/', trim((string) $originalName)));
    if ($basename === '' || $basename === '.' || $basename === '..' || strpos($basename, "\0") !== false) return '';

    $segments = explode('.', strtolower($basename));
    $extension = count($segments) > 1 ? array_pop($segments) : '';
    $forbidden = array('php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar', 'cgi', 'pl', 'py', 'sh', 'js', 'html', 'htm', 'svg', 'exe', 'com', 'bat', 'cmd');
    foreach ($segments as $segment) {
        if (in_array($segment, $forbidden, true)) return '';
    }
    if (!array_key_exists($extension, cpcc_portal_shipment_file_policy())) return '';

    $safeName = dol_sanitizeFileName($basename);
    if ($safeName === '' || strtolower(pathinfo($safeName, PATHINFO_EXTENSION)) !== $extension) return '';
    return $safeName;
}

/** Validate a synthetic or uploaded file without moving it. */
function cpcc_portal_validate_shipment_upload($originalName, $tmpName, $size = 0, $maxBytes = 0)
{
    $safeName = cpcc_portal_validate_shipment_filename($originalName);
    if ($safeName === '' || !is_file($tmpName) || !is_readable($tmpName)) return array('ok' => false, 'error' => 'File is not allowed.');

    $actualSize = (int) filesize($tmpName);
    $declaredSize = (int) $size;
    $limit = (int) $maxBytes > 0 ? (int) $maxBytes : cpcc_portal_upload_max_bytes();
    if ($actualSize <= 0 || $actualSize > $limit || ($declaredSize > 0 && $declaredSize !== $actualSize)) {
        return array('ok' => false, 'error' => 'File size is not allowed.');
    }

    $extension = strtolower(pathinfo($safeName, PATHINFO_EXTENSION));
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = strtolower((string) $finfo->file($tmpName));
    $allowedMimes = cpcc_portal_shipment_file_policy()[$extension];
    if ($mime === '' || !in_array($mime, array_map('strtolower', $allowedMimes), true)) {
        return array('ok' => false, 'error' => 'File content does not match its type.');
    }

    return array('ok' => true, 'name' => $safeName, 'mime' => $mime, 'size' => $actualSize);
}

function cpcc_portal_store_uploaded_shipment_file($shipid, $upload_dir, $original_name, $tmp_name, $file_size = 0, $upload_error = UPLOAD_ERR_OK)
{
    $shipid = (int) $shipid;
    $expectedDir = cpcc_portal_shipment_doc_root($shipid);
    if ($shipid <= 0 || $upload_error !== UPLOAD_ERR_OK || $upload_dir !== $expectedDir || !is_uploaded_file($tmp_name)) return array('ok' => false, 'error' => 'Upload was rejected.');

    $validation = cpcc_portal_validate_shipment_upload($original_name, $tmp_name, $file_size);
    if (empty($validation['ok'])) return $validation;

    $moduleRoot = DOL_DATA_ROOT.'/chinaplanetlogistics';
    if (!is_dir($moduleRoot) && !dol_mkdir($moduleRoot)) return array('ok' => false, 'error' => 'Upload was rejected.');
    if (!is_dir($upload_dir) && !dol_mkdir($upload_dir)) return array('ok' => false, 'error' => 'Upload was rejected.');
    if (!cpcc_portal_path_is_within($upload_dir, $moduleRoot)) return array('ok' => false, 'error' => 'Upload was rejected.');

    $safe_name = $validation['name'];

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
        return array('ok' => false, 'error' => 'Upload was rejected.');
    }
    @chmod($target, 0640);

    return array('ok' => true, 'file' => $stored_name);
}
