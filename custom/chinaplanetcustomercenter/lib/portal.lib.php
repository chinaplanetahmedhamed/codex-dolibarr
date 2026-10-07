<?php

/** Canonical storage remains active when the card UI is rolled back; never scan immutable versions as legacy files. */
function cpcc_shared_documents_enabled()
{
    global $conf;
    if(empty($conf->global->CPO_SHIPMENT_CARDS_ENABLED)&&empty($conf->global->CPL_SHARED_DOCUMENTS_ENABLED))return false;
    require_once __DIR__.'/../../chinaplanetlogistics/class/ShipmentDocumentService.class.php';
    return true;
}


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

/** Return a customer-portal asset URL with a content-based cache key. */
function cpcc_portal_asset($path)
{
    $absolutePath = DOL_DOCUMENT_ROOT.$path;
    $version = is_file($absolutePath) ? substr(sha1_file($absolutePath), 0, 12) : 'missing';
    return dol_buildpath($path, 1).'?v='.$version;
}

function cpcc_portal_navigation($user, $active = '')
{
    if (empty($user->id) || empty($user->socid) || !empty($user->admin)) return;

    $activeGroups = array(
        'dashboard' => 'home',
        'projects' => 'projects',
        'shipments' => 'shipments',
        'shippingprices' => 'shippingprices',
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
        'shippingprices' => array('أسعار الشحن', 'Shipping Prices', 'fa-search-dollar', '/chinaplanetshippingprices/index.php'),
        'account' => array('الحساب المالي', 'Financial Account', 'fa-wallet', '/chinaplanetcustomercenter/balance.php'),
        'documents' => array('المستندات', 'Documents', 'fa-file-alt', '/chinaplanetcustomercenter/documents.php'),
        'requests' => array('الطلبات والدعم', 'Orders & Support', 'fa-headset', '/chinaplanetcustomercenter/orders.php'),
    );

    $homeUrl = dol_buildpath('/chinaplanetcustomercenter/board.php', 1);
    print '<link rel="stylesheet" href="'.dol_escape_htmltag(cpcc_portal_asset('/custom/chinaplanetcustomercenter/css/portal.css')).'">';
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
    print '<script src="'.dol_escape_htmltag(cpcc_portal_asset('/custom/chinaplanetcustomercenter/js/portal.js')).'" defer></script>';
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

/** Count every active document published to one customer. */
function cpcc_portal_customer_document_count($db, $user, $requestedSocid = 0)
{
    global $conf;

    $scope = cpcc_portal_user_scope($user);
    $isExternal = !empty($scope['is_external']);
    $socid = $isExternal ? (int) $scope['socid'] : (int) $requestedSocid;
    $entity = !empty($conf->entity) ? (int) $conf->entity : 0;
    if ($entity <= 0 || $socid <= 0) return 0;

    $total = 0;
    if (cpcc_portal_gallery_schema_ready($db)) {
        $sql = "SELECT COUNT(*) AS nb";
        $sql .= " FROM ".MAIN_DB_PREFIX."cpl_shipment_public_document p";
        $sql .= " INNER JOIN ".MAIN_DB_PREFIX."chinaplanetlogistics s";
        $sql .= " ON s.rowid=p.fk_shipment AND s.entity=p.entity AND s.fk_soc=p.fk_soc";
        $sql .= " WHERE p.entity=".$entity." AND p.fk_soc=".$socid;
        $sql .= " AND p.status=1 AND p.customer_visible=1";
        $resql = $db->query($sql);
        if ($resql && ($obj = $db->fetch_object($resql))) $total += (int) $obj->nb;
    }

    if (function_exists('cpcc_export_schema_ready') && cpcc_export_schema_ready($db)) {
        $sql = "SELECT COUNT(*) AS nb FROM ".MAIN_DB_PREFIX."cpcc_export_document";
        $sql .= " WHERE entity=".$entity." AND fk_soc=".$socid;
        $sql .= " AND status=1 AND customer_visible=1";
        $resql = $db->query($sql);
        if ($resql && ($obj = $db->fetch_object($resql))) $total += (int) $obj->nb;
    }
    return $total;
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

/** Exact staff allowance for the module-scoped shipment upload route. */
function cpcc_portal_staff_upload_max_bytes()
{
    return 200 * 1024 * 1024;
}

/** Return a customer-safe explanation for PHP upload failures. */
function cpcc_portal_upload_error_message($uploadError, $maxBytes = 0)
{
    $uploadError = (int) $uploadError;
    if (in_array($uploadError, array(UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE), true)) {
        $limitMb = (int) $maxBytes > 0 ? (int) floor(((int) $maxBytes) / 1024 / 1024) : 0;
        return $limitMb > 0 ? 'File exceeds the '.$limitMb.' MB upload limit.' : 'File exceeds the allowed upload size.';
    }
    if ($uploadError === UPLOAD_ERR_PARTIAL) return 'File upload was incomplete.';
    if ($uploadError === UPLOAD_ERR_NO_FILE) return 'No file was uploaded.';
    return 'Upload was rejected.';
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

function cpcc_portal_store_uploaded_shipment_file($shipid, $upload_dir, $original_name, $tmp_name, $file_size = 0, $upload_error = UPLOAD_ERR_OK, $maxBytes = 0)
{
    if(cpcc_shared_documents_enabled()){
        global $db,$user;
        if($upload_dir!==cpcc_portal_shipment_doc_root($shipid))return array('ok'=>false,'error'=>'Upload was rejected.');
        try{$r=(new ShipmentDocumentService($db,$user))->upload($shipid,array('name'=>$original_name,'tmp_name'=>$tmp_name,'size'=>$file_size,'error'=>$upload_error),0,0,$maxBytes);return array('ok'=>true,'file'=>$r['file'],'document_id'=>$r['id'],'version_id'=>$r['versionId']);}
        catch(Throwable $e){return array('ok'=>false,'error'=>'Upload was rejected. Check the file and permissions.');}
    }

    $shipid = (int) $shipid;
    $expectedDir = cpcc_portal_shipment_doc_root($shipid);
    if ($shipid <= 0 || $upload_dir !== $expectedDir) return array('ok' => false, 'error' => 'Upload was rejected.');
    if ((int) $upload_error !== UPLOAD_ERR_OK) return array('ok' => false, 'error' => cpcc_portal_upload_error_message($upload_error, $maxBytes));
    if (!is_uploaded_file($tmp_name)) return array('ok' => false, 'error' => 'Upload was rejected.');

    $validation = cpcc_portal_validate_shipment_upload($original_name, $tmp_name, $file_size, $maxBytes);
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

/** Return whether the explicit customer shipment-gallery allowlist is installed. */
function cpcc_portal_gallery_schema_ready($db)
{
    $pattern = str_replace(array('\\', '_', '%'), array('\\\\', '\\_', '\\%'), MAIN_DB_PREFIX.'cpl_shipment_public_document');
    $result = $db->query("SHOW TABLES LIKE '".$db->escape($pattern)."'");
    return $result && $db->num_rows($result) === 1;
}

function cpcc_portal_collaboration_schema_ready($db)
{
    if (!cpcc_portal_gallery_schema_ready($db)) return false;
    $tablePattern = str_replace(array('\\', '_', '%'), array('\\\\', '\\_', '\\%'), MAIN_DB_PREFIX.'cpl_shipment_customer_event');
    $table = $db->query("SHOW TABLES LIKE '".$db->escape($tablePattern)."'");
    if (!$table || $db->num_rows($table) !== 1) return false;
    foreach (array('source_origin','response_status','customer_upload_status','response_note','fk_user_response','date_response') as $column) {
        $result = $db->query("SHOW COLUMNS FROM ".MAIN_DB_PREFIX."cpl_shipment_public_document LIKE '".$db->escape($column)."'");
        if (!$result || $db->num_rows($result) !== 1) return false;
    }
    return true;
}

/** Classify a validated shipment file without trusting its filename alone. */
function cpcc_portal_gallery_item_kind($mime)
{
    $mime = strtolower(trim((string) $mime));
    if (strpos($mime, 'image/') === 0) return 'image';
    if (strpos($mime, 'video/') === 0) return 'video';
    return 'document';
}

/**
 * Convert an already-confined shipment file to a private module-relative key.
 * The key is stored server-side only and is never returned to a customer.
 */
function cpcc_portal_gallery_storage_key($resolvedPath)
{
    $moduleRoot = realpath(DOL_DATA_ROOT.'/chinaplanetlogistics');
    $resolvedPath = realpath((string) $resolvedPath);
    if ($moduleRoot === false || $resolvedPath === false || !is_file($resolvedPath)) return '';
    $prefix = rtrim($moduleRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
    if (strpos($resolvedPath, $prefix) !== 0) return '';
    $key = str_replace(DIRECTORY_SEPARATOR, '/', substr($resolvedPath, strlen($prefix)));
    if ($key === '' || strlen($key) > 512 || strpos($key, "\0") !== false) return '';
    return $key;
}

/** Resolve and fingerprint one internal shipment file before publication. */
function cpcc_portal_gallery_file_metadata($shipment, $file)
{
    $shipmentId = !empty($shipment->id) ? (int) $shipment->id : (int) $shipment->rowid;
    $resolved = cpcc_portal_resolve_shipment_doc_path($shipmentId, (string) $shipment->ref, $file);
    if ($resolved === '') return array('ok' => false, 'error' => 'Shipment file was not found.');

    $size = filesize($resolved);
    if ($size === false) return array('ok' => false, 'error' => 'Shipment file was not found.');
    $validation = cpcc_portal_validate_shipment_upload(basename($resolved), $resolved, (int) $size);
    if (empty($validation['ok'])) return $validation;

    $storageKey = cpcc_portal_gallery_storage_key($resolved);
    $sha256 = hash_file('sha256', $resolved);
    if ($storageKey === '' || !is_string($sha256) || !preg_match('/^[a-f0-9]{64}$/', $sha256)) {
        return array('ok' => false, 'error' => 'Shipment file could not be fingerprinted.');
    }

    return array(
        'ok' => true,
        'storage_key' => $storageKey,
        'original_name' => basename((string) $validation['name']),
        'mime_type' => strtolower((string) $validation['mime']),
        'file_size' => (int) $validation['size'],
        'file_sha256' => $sha256,
        'item_kind' => cpcc_portal_gallery_item_kind($validation['mime']),
    );
}

/** Publish exactly one validated shipment file to its owning customer. */
function cpcc_portal_gallery_publish($db, $shipment, $user, $file, $sourceOrigin = 'staff')
{
    if(cpcc_shared_documents_enabled()){
        try{return (new ShipmentDocumentService($db,$user))->publishStorage(!empty($shipment->id)?$shipment->id:$shipment->rowid,$file,$sourceOrigin);}
        catch(Throwable $e){return array('ok'=>false,'error'=>'Document publication was rejected.');}
    }
    return cpcc_portal_gallery_publish_legacy($db,$shipment,$user,$file,$sourceOrigin);
}

function cpcc_portal_gallery_publish_legacy($db, $shipment, $user, $file, $sourceOrigin = 'staff')
{
    if (!cpcc_portal_gallery_schema_ready($db)) return array('ok' => false, 'error' => 'Shipment Gallery schema is not installed.');
    $shipmentId = !empty($shipment->id) ? (int) $shipment->id : (int) $shipment->rowid;
    if ($shipmentId <= 0 || (int) $shipment->entity <= 0 || (int) $shipment->fk_soc <= 0 || empty($user->id)) {
        return array('ok' => false, 'error' => 'Shipment Gallery publication was rejected.');
    }
    $metadata = cpcc_portal_gallery_file_metadata($shipment, $file);
    if (empty($metadata['ok'])) return $metadata;

    $sourceOrigin = $sourceOrigin === 'customer' ? 'customer' : 'staff';
    $collaborationReady = cpcc_portal_collaboration_schema_ready($db);
    $db->begin();
    $lock = $db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."chinaplanetlogistics WHERE rowid=".$shipmentId." AND entity=".(int) $shipment->entity." AND fk_soc=".(int) $shipment->fk_soc." FOR UPDATE");
    if (!$lock || $db->num_rows($lock) !== 1) {
        $db->rollback();
        return array('ok' => false, 'error' => 'Shipment Gallery publication was rejected.');
    }

    $existingSql = "SELECT rowid".($collaborationReady ? ",source_origin,customer_upload_status" : '')." FROM ".MAIN_DB_PREFIX."cpl_shipment_public_document WHERE entity=".(int) $shipment->entity." AND fk_shipment=".$shipmentId." AND storage_key='".$db->escape($metadata['storage_key'])."' FOR UPDATE";
    $existing = $db->query($existingSql);
    $row = $existing ? $db->fetch_object($existing) : null;
    if ($row && isset($row->source_origin) && (string) $row->source_origin === 'customer') $sourceOrigin = 'customer';
    $responseStatus = $sourceOrigin === 'customer' ? 'customer_upload' : 'awaiting_confirmation';
    $customerUploadStatus = $sourceOrigin === 'customer' ? (($row && !empty($row->customer_upload_status)) ? (string) $row->customer_upload_status : 'waiting_receive') : '';
    $now = $db->idate(dol_now());
    if ($row) {
        $sql = "UPDATE ".MAIN_DB_PREFIX."cpl_shipment_public_document SET fk_soc=".(int) $shipment->fk_soc.",original_name='".$db->escape($metadata['original_name'])."',mime_type='".$db->escape($metadata['mime_type'])."',file_size=".(int) $metadata['file_size'].",file_sha256='".$db->escape($metadata['file_sha256'])."',item_kind='".$db->escape($metadata['item_kind'])."',customer_visible=1,status=1,fk_user_modif=".(int) $user->id.",date_publication='".$now."'";
        if ($collaborationReady) $sql .= ",source_origin='".$sourceOrigin."',response_status='".$responseStatus."',customer_upload_status=".($customerUploadStatus !== '' ? "'".$db->escape($customerUploadStatus)."'" : 'NULL').",response_note=NULL,fk_user_response=NULL,date_response=NULL";
        $sql .= " WHERE rowid=".(int) $row->rowid;
        $publicationId = (int) $row->rowid;
    } else {
        if ($collaborationReady) {
            $sql = "INSERT INTO ".MAIN_DB_PREFIX."cpl_shipment_public_document(entity,fk_shipment,fk_soc,storage_key,original_name,mime_type,file_size,file_sha256,item_kind,source_origin,response_status,customer_upload_status,customer_visible,status,fk_user_publish,date_publication,date_creation) VALUES(".(int) $shipment->entity.",".$shipmentId.",".(int) $shipment->fk_soc.",'".$db->escape($metadata['storage_key'])."','".$db->escape($metadata['original_name'])."','".$db->escape($metadata['mime_type'])."',".(int) $metadata['file_size'].",'".$db->escape($metadata['file_sha256'])."','".$db->escape($metadata['item_kind'])."','".$sourceOrigin."','".$responseStatus."',".($customerUploadStatus !== '' ? "'".$db->escape($customerUploadStatus)."'" : 'NULL').",1,1,".(int) $user->id.",'".$now."','".$now."')";
        } else {
            $sql = "INSERT INTO ".MAIN_DB_PREFIX."cpl_shipment_public_document(entity,fk_shipment,fk_soc,storage_key,original_name,mime_type,file_size,file_sha256,item_kind,customer_visible,status,fk_user_publish,date_publication,date_creation) VALUES(".(int) $shipment->entity.",".$shipmentId.",".(int) $shipment->fk_soc.",'".$db->escape($metadata['storage_key'])."','".$db->escape($metadata['original_name'])."','".$db->escape($metadata['mime_type'])."',".(int) $metadata['file_size'].",'".$db->escape($metadata['file_sha256'])."','".$db->escape($metadata['item_kind'])."',1,1,".(int) $user->id.",'".$now."','".$now."')";
        }
        $publicationId = 0;
    }
    if (!$db->query($sql)) {
        $error = $db->lasterror();
        $db->rollback();
        return array('ok' => false, 'error' => $error);
    }
    if ($publicationId <= 0) $publicationId = (int) $db->last_insert_id(MAIN_DB_PREFIX.'cpl_shipment_public_document');
    $db->commit();
    return array('ok' => true, 'id' => $publicationId, 'error' => '');
}

/** Unpublish one owned gallery row without deleting or moving the source file. */
function cpcc_portal_gallery_unpublish($db, $shipment, $user, $publicationId)
{
    if(cpcc_shared_documents_enabled()){
        try{return (new ShipmentDocumentService($db,$user))->unpublishPublication(!empty($shipment->id)?$shipment->id:$shipment->rowid,$publicationId);}
        catch(Throwable $e){return array('ok'=>false,'error'=>'Document publication was rejected.');}
    }

    if (!cpcc_portal_gallery_schema_ready($db)) return array('ok' => false, 'error' => 'Shipment Gallery schema is not installed.');
    $shipmentId = !empty($shipment->id) ? (int) $shipment->id : (int) $shipment->rowid;
    $publicationId = (int) $publicationId;
    if ($shipmentId <= 0 || $publicationId <= 0 || empty($user->id)) return array('ok' => false, 'error' => 'Shipment Gallery publication was rejected.');
    $db->begin();
    $where = "rowid=".$publicationId." AND entity=".(int) $shipment->entity." AND fk_shipment=".$shipmentId." AND fk_soc=".(int) $shipment->fk_soc." AND customer_visible=1 AND status=1";
    $lock = $db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."cpl_shipment_public_document WHERE ".$where." FOR UPDATE");
    if (!$lock || $db->num_rows($lock) !== 1) {
        $db->rollback();
        return array('ok' => false, 'error' => 'Shipment Gallery publication was not found.');
    }
    $sql = "UPDATE ".MAIN_DB_PREFIX."cpl_shipment_public_document SET customer_visible=0,status=0,fk_user_modif=".(int) $user->id." WHERE ".$where;
    if (!$db->query($sql)) {
        $error = $db->lasterror();
        $db->rollback();
        return array('ok' => false, 'error' => $error);
    }
    $db->commit();
    return array('ok' => true, 'error' => '');
}

/** Return publication rows for staff rendering, keyed by private storage key. */
function cpcc_portal_gallery_publications($db, $shipment, $activeOnly = false)
{
    if (!cpcc_portal_gallery_schema_ready($db)) return array();
    $shipmentId = !empty($shipment->id) ? (int) $shipment->id : (int) $shipment->rowid;
    $sql = "SELECT rowid,storage_key,item_kind,customer_visible,status,date_publication,tms";
    if (cpcc_portal_collaboration_schema_ready($db)) $sql .= ",source_origin,response_status,customer_upload_status,response_note,fk_user_response,date_response";
    $sql .= " FROM ".MAIN_DB_PREFIX."cpl_shipment_public_document WHERE entity=".(int) $shipment->entity." AND fk_shipment=".$shipmentId." AND fk_soc=".(int) $shipment->fk_soc;
    if ($activeOnly) $sql .= " AND customer_visible=1 AND status=1";
    $result = $db->query($sql);
    $rows = array();
    while ($result && ($row = $db->fetch_object($result))) $rows[(string) $row->storage_key] = $row;
    return $rows;
}

function cpcc_portal_shipment_customer_event_types()
{
    return array('document_confirmed','document_amendment_requested','document_comment','customer_upload','customer_upload_status','shipment_file_archived','container_received','original_document_status','original_documents_received');
}

function cpcc_portal_shipment_event_insert($db, $shipment, $user, $eventType, $statusCode = '', $note = '', $publicationId = 0, $actorType = 'staff', $customerVisible = 1)
{
    if (!cpcc_portal_collaboration_schema_ready($db) || !in_array($eventType, cpcc_portal_shipment_customer_event_types(), true)) return false;
    $shipmentId = !empty($shipment->id) ? (int)$shipment->id : (int)($shipment->rowid ?? 0);
    if ($shipmentId <= 0) return false;
    $actorType = $actorType === 'customer' ? 'customer' : 'staff';
    $note = trim((string) $note);
    if (function_exists('mb_substr')) $note = mb_substr($note, 0, 2000, 'UTF-8'); else $note = substr($note, 0, 2000);
    $now = $db->idate(dol_now());
    $sql = "INSERT INTO ".MAIN_DB_PREFIX."cpl_shipment_customer_event(entity,fk_shipment,fk_soc,fk_public_document,event_type,status_code,note_public,actor_type,fk_user,customer_visible,event_at,date_creation) VALUES(".(int)$shipment->entity.",".$shipmentId.",".(int)$shipment->fk_soc.",".((int)$publicationId > 0 ? (int)$publicationId : 'NULL').",'".$db->escape($eventType)."',".($statusCode !== '' ? "'".$db->escape($statusCode)."'" : 'NULL').",".($note !== '' ? "'".$db->escape($note)."'" : 'NULL').",'".$actorType."',".(int)$user->id.",".(!empty($customerVisible) ? 1 : 0).",'".$now."','".$now."')";
    return (bool) $db->query($sql);
}

function cpcc_portal_shipment_collaboration_events($db, $shipment, $publicationId = 0)
{
    if (!cpcc_portal_collaboration_schema_ready($db)) return array();
    $shipmentId = !empty($shipment->id) ? (int) $shipment->id : (int) ($shipment->rowid ?? 0);
    if ($shipmentId <= 0) return array();
    $sql = "SELECT rowid,fk_public_document,event_type,status_code,note_public,actor_type,event_at FROM ".MAIN_DB_PREFIX."cpl_shipment_customer_event WHERE entity=".(int)$shipment->entity." AND fk_shipment=".$shipmentId." AND fk_soc=".(int)$shipment->fk_soc." AND customer_visible=1";
    if ((int)$publicationId > 0) $sql .= " AND fk_public_document=".(int)$publicationId;
    $sql .= " ORDER BY event_at,rowid";
    $result = $db->query($sql); $rows = array();
    while ($result && ($row = $db->fetch_object($result))) $rows[] = $row;
    return $rows;
}

function cpcc_portal_customer_document_response($db, $user, $shipment, $publicToken, $response, $note = '')
{
    $allowed = array('confirmed'=>'document_confirmed','amendment_requested'=>'document_amendment_requested','comment'=>'document_comment');
    if (!isset($allowed[$response]) || !cpcc_portal_collaboration_schema_ready($db)) return array('ok'=>false,'error'=>'Customer response is unavailable.');
    $scope = cpcc_portal_user_scope($user);
    if (empty($scope['is_external']) || (int)$scope['socid'] !== (int)$shipment->fk_soc) return array('ok'=>false,'error'=>'Customer response was rejected.');
    $row = cpcc_portal_customer_gallery_publication($db, $user, $publicToken);
    if (!$row || (int)$row->fk_shipment !== (int)$shipment->id || (string)$row->item_kind !== 'document' || (string)$row->source_origin !== 'staff') return array('ok'=>false,'error'=>'Shipment document was not found.');
    $note = trim((string)$note);
    if (($response === 'amendment_requested' || $response === 'comment') && $note === '') return array('ok'=>false,'error'=>'Please write your note.');

    $db->begin();
    $where = "rowid=".(int)$row->rowid." AND entity=".(int)$shipment->entity." AND fk_shipment=".(int)$shipment->id." AND fk_soc=".(int)$shipment->fk_soc." AND customer_visible=1 AND status=1";
    $lock = $db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."cpl_shipment_public_document WHERE ".$where." FOR UPDATE");
    if (!$lock || $db->num_rows($lock) !== 1) { $db->rollback(); return array('ok'=>false,'error'=>'Shipment document was not found.'); }
    if ($response !== 'comment') {
        $now = $db->idate(dol_now());
        $sql = "UPDATE ".MAIN_DB_PREFIX."cpl_shipment_public_document SET response_status='".$db->escape($response)."',response_note=".($note !== '' ? "'".$db->escape($note)."'" : 'NULL').",fk_user_response=".(int)$user->id.",date_response='".$now."' WHERE ".$where;
        if (!$db->query($sql)) { $db->rollback(); return array('ok'=>false,'error'=>$db->lasterror()); }
    }
    if (!cpcc_portal_shipment_event_insert($db, $shipment, $user, $allowed[$response], $response, $note, (int)$row->rowid, 'customer')) { $db->rollback(); return array('ok'=>false,'error'=>$db->lasterror()); }
    $db->commit(); return array('ok'=>true,'error'=>'');
}

function cpcc_portal_publish_customer_upload($db, $shipment, $user, $relativePath, $note = '')
{
    if (!cpcc_portal_collaboration_schema_ready($db)) return array('ok'=>false,'error'=>'Customer upload is unavailable.');
    $publication = cpcc_portal_gallery_publish($db, $shipment, $user, $relativePath, 'customer');
    if (empty($publication['ok'])) return $publication;
    if (!cpcc_portal_shipment_event_insert($db, $shipment, $user, 'customer_upload', 'customer_upload', $note, (int)$publication['id'], 'customer')) {
        cpcc_portal_gallery_unpublish($db, $shipment, $user, (int)$publication['id']);
        return array('ok'=>false,'error'=>'Customer upload history could not be recorded.');
    }
    return $publication;
}

function cpcc_portal_customer_upload_statuses()
{
    return array('waiting_receive','received','handling','done');
}

/** Advance one customer-originated upload through the staff handling workflow. */
function cpcc_portal_staff_customer_upload_status($db, $shipment, $user, $publicationId, $status, $note = '')
{
    if(cpcc_shared_documents_enabled()){try{(new ShipmentDocumentService($db,$user))->requireShipment(!empty($shipment->id)?$shipment->id:$shipment->rowid,'write');}catch(Throwable $e){return array('ok'=>false,'error'=>'Customer upload status was rejected.');}}
    $publicationId = (int) $publicationId;
    $statuses = cpcc_portal_customer_upload_statuses();
    if (!cpcc_portal_collaboration_schema_ready($db) || $publicationId <= 0 || empty($user->id) || !in_array($status, $statuses, true)) {
        return array('ok'=>false,'error'=>'Customer upload status was rejected.');
    }
    $shipmentId = !empty($shipment->id) ? (int) $shipment->id : (int) $shipment->rowid;
    $where = "rowid=".$publicationId." AND entity=".(int)$shipment->entity." AND fk_shipment=".$shipmentId." AND fk_soc=".(int)$shipment->fk_soc." AND source_origin='customer' AND customer_visible=1 AND status=1";
    $db->begin();
    $result = $db->query("SELECT customer_upload_status FROM ".MAIN_DB_PREFIX."cpl_shipment_public_document WHERE ".$where." FOR UPDATE");
    $row = $result ? $db->fetch_object($result) : null;
    if (!$row) { $db->rollback(); return array('ok'=>false,'error'=>'Customer upload was not found.'); }
    $current = !empty($row->customer_upload_status) ? (string)$row->customer_upload_status : 'waiting_receive';
    $currentIndex = array_search($current, $statuses, true);
    $nextIndex = array_search($status, $statuses, true);
    if ($currentIndex === false || $nextIndex === false || $nextIndex < $currentIndex) {
        $db->rollback(); return array('ok'=>false,'error'=>'Customer upload status cannot move backwards.');
    }
    if ($status === $current) { $db->commit(); return array('ok'=>true,'error'=>''); }
    if (!$db->query("UPDATE ".MAIN_DB_PREFIX."cpl_shipment_public_document SET customer_upload_status='".$db->escape($status)."',fk_user_modif=".(int)$user->id." WHERE ".$where)) {
        $error = $db->lasterror(); $db->rollback(); return array('ok'=>false,'error'=>$error);
    }
    if (!cpcc_portal_shipment_event_insert($db, $shipment, $user, 'customer_upload_status', $status, $note, $publicationId, 'staff')) {
        $error = $db->lasterror(); $db->rollback(); return array('ok'=>false,'error'=>$error);
    }
    $db->commit(); return array('ok'=>true,'error'=>'');
}

/**
 * Revoke and move one owned shipment file into the private reversible archive.
 * The source bytes are never hard-deleted by this customer-facing workflow.
 */
function cpcc_portal_archive_shipment_file($db, $shipment, $user, $relativePath)
{
    if(cpcc_shared_documents_enabled()){
        try{$svc=new ShipmentDocumentService($db,$user);$sid=!empty($shipment->id)?$shipment->id:$shipment->rowid;$d=$svc->byStorage($sid,$relativePath);$svc->archive($d['rowid'],$d['revision']);return array('ok'=>true,'archive_key'=>'document-'.$d['rowid']);}
        catch(Throwable $e){return array('ok'=>false,'error'=>'Document archive was rejected.');}
    }

    if (!cpcc_portal_collaboration_schema_ready($db) || empty($user->id)) return array('ok'=>false,'error'=>'Shipment file archive is unavailable.');
    $shipmentId = !empty($shipment->id) ? (int)$shipment->id : (int)$shipment->rowid;
    $metadata = cpcc_portal_gallery_file_metadata($shipment, $relativePath);
    if ($shipmentId <= 0 || empty($metadata['ok'])) return array('ok'=>false,'error'=>'Shipment file was not found.');
    $sourcePath = cpcc_portal_resolve_shipment_doc_path($shipmentId, (string)$shipment->ref, $relativePath);
    if ($sourcePath === '' || is_link($sourcePath)) return array('ok'=>false,'error'=>'Shipment file was not found.');

    $moduleRoot = DOL_DATA_ROOT.'/chinaplanetlogistics';
    $archiveDir = $moduleRoot.'/_archive/entity-'.(int)$shipment->entity.'/ship-'.$shipmentId;
    if (!is_dir($archiveDir) && !dol_mkdir($archiveDir)) return array('ok'=>false,'error'=>'Shipment archive could not be created.');
    if (!cpcc_portal_path_is_within($archiveDir, $moduleRoot)) return array('ok'=>false,'error'=>'Shipment archive was rejected.');
    $archiveName = gmdate('YmdHis').'--'.substr((string)$metadata['file_sha256'], 0, 12).'--'.basename($sourcePath);
    $archivePath = $archiveDir.'/'.$archiveName;
    if (file_exists($archivePath)) return array('ok'=>false,'error'=>'Shipment archive collision was rejected.');

    $db->begin();
    $shipmentLock = $db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."chinaplanetlogistics WHERE rowid=".$shipmentId." AND entity=".(int)$shipment->entity." AND fk_soc=".(int)$shipment->fk_soc." FOR UPDATE");
    if (!$shipmentLock || $db->num_rows($shipmentLock) !== 1) { $db->rollback(); return array('ok'=>false,'error'=>'Shipment file archive was rejected.'); }
    $publicationResult = $db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."cpl_shipment_public_document WHERE entity=".(int)$shipment->entity." AND fk_shipment=".$shipmentId." AND fk_soc=".(int)$shipment->fk_soc." AND storage_key='".$db->escape($metadata['storage_key'])."' FOR UPDATE");
    $publication = $publicationResult ? $db->fetch_object($publicationResult) : null;

    if (!@rename($sourcePath, $archivePath)) { $db->rollback(); return array('ok'=>false,'error'=>'Shipment file could not be archived.'); }
    @chmod($archivePath, 0640);
    if ($publication && !$db->query("UPDATE ".MAIN_DB_PREFIX."cpl_shipment_public_document SET customer_visible=0,status=0,fk_user_modif=".(int)$user->id." WHERE rowid=".(int)$publication->rowid." AND entity=".(int)$shipment->entity." AND fk_shipment=".$shipmentId." AND fk_soc=".(int)$shipment->fk_soc)) {
        $error = $db->lasterror(); @rename($archivePath, $sourcePath); $db->rollback(); return array('ok'=>false,'error'=>$error);
    }
    $archiveKey = substr(str_replace(DIRECTORY_SEPARATOR, '/', $archivePath), strlen(rtrim($moduleRoot, DIRECTORY_SEPARATOR))+1);
    if (!cpcc_portal_shipment_event_insert($db, $shipment, $user, 'shipment_file_archived', 'archived', $archiveKey, $publication ? (int)$publication->rowid : 0, 'staff', 0)) {
        $error = $db->lasterror(); @rename($archivePath, $sourcePath); $db->rollback(); return array('ok'=>false,'error'=>$error);
    }
    $db->commit();
    return array('ok'=>true,'archive_key'=>$archiveKey,'error'=>'');
}

function cpcc_portal_customer_container_received($db, $user, $shipment, $note = '')
{
    $scope = cpcc_portal_user_scope($user);
    if (empty($scope['is_external']) || (int)$scope['socid'] !== (int)$shipment->fk_soc || !cpcc_portal_collaboration_schema_ready($db)) return array('ok'=>false,'error'=>'Container receipt is not available yet.');
    $current = $db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."chinaplanetlogistics WHERE rowid=".(int)$shipment->id." AND entity=".(int)$shipment->entity." AND fk_soc=".(int)$shipment->fk_soc." AND fk_statut IN (6,7) LIMIT 1");
    if (!$current || $db->num_rows($current) !== 1) return array('ok'=>false,'error'=>'Container receipt is not available yet.');
    $existing = $db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."cpl_shipment_customer_event WHERE entity=".(int)$shipment->entity." AND fk_shipment=".(int)$shipment->id." AND fk_soc=".(int)$shipment->fk_soc." AND event_type='container_received' LIMIT 1");
    if ($existing && $db->num_rows($existing) > 0) return array('ok'=>true,'error'=>'');
    return cpcc_portal_shipment_event_insert($db, $shipment, $user, 'container_received', 'received', $note, 0, 'customer') ? array('ok'=>true,'error'=>'') : array('ok'=>false,'error'=>$db->lasterror());
}

function cpcc_portal_original_document_statuses()
{
    return array('preparing','finished','government_uploaded','sent_dhl','in_transit','delivered','customer_received');
}

function cpcc_portal_staff_original_document_status($db, $user, $shipment, $status, $note = '')
{
    if (!in_array($status, cpcc_portal_original_document_statuses(), true) || $status === 'customer_received') return array('ok'=>false,'error'=>'Original document status was rejected.');
    $events = cpcc_portal_shipment_collaboration_events($db, $shipment);
    $workflow = array('preparing','finished','government_uploaded','sent_dhl','in_transit','delivered');
    $latest = '';
    foreach ($events as $event) if ($event->event_type === 'original_document_status') $latest = (string)$event->status_code;
    if ($latest === $status) return array('ok'=>true,'error'=>'');
    if ($latest !== '' && array_search($status, $workflow, true) < array_search($latest, $workflow, true)) return array('ok'=>false,'error'=>'Original document status cannot move backwards.');
    return cpcc_portal_shipment_event_insert($db, $shipment, $user, 'original_document_status', $status, $note, 0, 'staff') ? array('ok'=>true,'error'=>'') : array('ok'=>false,'error'=>$db->lasterror());
}

function cpcc_portal_customer_original_documents_received($db, $user, $shipment, $note = '')
{
    $scope = cpcc_portal_user_scope($user);
    if (empty($scope['is_external']) || (int)$scope['socid'] !== (int)$shipment->fk_soc || !cpcc_portal_collaboration_schema_ready($db)) return array('ok'=>false,'error'=>'Original document receipt was rejected.');
    $events = cpcc_portal_shipment_collaboration_events($db, $shipment);
    $latest = ''; $received = false;
    foreach ($events as $event) {
        if ($event->event_type === 'original_document_status') $latest = (string)$event->status_code;
        if ($event->event_type === 'original_documents_received') $received = true;
    }
    if ($received) return array('ok'=>true,'error'=>'');
    if ($latest !== 'delivered') return array('ok'=>false,'error'=>'Original documents are not marked delivered yet.');
    return cpcc_portal_shipment_event_insert($db, $shipment, $user, 'original_documents_received', 'customer_received', $note, 0, 'customer') ? array('ok'=>true,'error'=>'') : array('ok'=>false,'error'=>$db->lasterror());
}

/**
 * Return the private key used to derive one-way customer gallery identifiers.
 *
 * The same installation-owned secret already protects Mobile API credentials.
 * It is never sent to the browser. Tests may provide an isolated synthetic
 * secret without changing Dolibarr configuration.
 */
function cpcc_portal_gallery_token_secret()
{
    $secret = function_exists('getDolGlobalString') ? (string) getDolGlobalString('CHINAPLANETCUSTOMERCENTER_MOBILE_TOKEN_SECRET', '') : '';
    if (defined('CPCC_PORTAL_GALLERY_TESTING') && CPCC_PORTAL_GALLERY_TESTING && $secret === '') {
        $testSecret = getenv('CPCC_PORTAL_GALLERY_TEST_SECRET');
        if (is_string($testSecret)) $secret = $testSecret;
    }
    return strlen($secret) >= 32 ? hash('sha256', "customer-web-gallery\0".$secret, true) : '';
}

function cpcc_portal_gallery_base64url($value)
{
    return rtrim(strtr(base64_encode((string) $value), '+/', '-_'), '=');
}

/** Build a non-reversible browser identifier without exposing a database ID. */
function cpcc_portal_gallery_public_token($publicationId, $entity, $socid)
{
    $secret = cpcc_portal_gallery_token_secret();
    $publicationId = (int) $publicationId;
    $entity = (int) $entity;
    $socid = (int) $socid;
    if ($secret === '' || $publicationId <= 0 || $entity <= 0 || $socid <= 0) return '';
    $digest = hash_hmac('sha256', "v1\0".$publicationId."\0".$entity."\0".$socid, $secret, true);
    return 'gal_'.cpcc_portal_gallery_base64url($digest);
}

/**
 * List active gallery publications for the authenticated external customer.
 * Authority is derived only from the session and active entity.
 */
function cpcc_portal_customer_gallery_items($db, $user, $shipment = null)
{
    global $conf;

    $scope = cpcc_portal_user_scope($user);
    $entity = !empty($conf->entity) ? (int) $conf->entity : 0;
    $socid = !empty($scope['is_external']) ? (int) $scope['socid'] : 0;
    if ($entity <= 0 || $socid <= 0 || !cpcc_portal_gallery_schema_ready($db) || cpcc_portal_gallery_token_secret() === '') return array();

    $shipmentId = 0;
    if ($shipment !== null) {
        $shipmentId = !empty($shipment->id) ? (int) $shipment->id : (int) ($shipment->rowid ?? 0);
        if ($shipmentId <= 0 || !cpcc_portal_authorized_shipment($db, $user, $shipmentId)) return array();
    }

    $sql = "SELECT p.rowid,p.fk_shipment,p.original_name,p.mime_type,p.file_size,p.item_kind,p.date_publication,";
    if (cpcc_portal_collaboration_schema_ready($db)) $sql .= "p.source_origin,p.response_status,p.customer_upload_status,p.response_note,p.date_response,";
    else $sql .= "'staff' AS source_origin,'published' AS response_status,NULL AS customer_upload_status,NULL AS response_note,NULL AS date_response,";
    $sql .= " t.ref AS shipment_ref,t.container_no,t.bl_no";
    $sql .= " FROM ".MAIN_DB_PREFIX."cpl_shipment_public_document p";
    $sql .= " INNER JOIN ".MAIN_DB_PREFIX."chinaplanetlogistics t ON t.rowid=p.fk_shipment AND t.entity=p.entity AND t.fk_soc=p.fk_soc";
    $sql .= " WHERE p.entity=".$entity." AND p.fk_soc=".$socid." AND p.customer_visible=1 AND p.status=1";
    if ($shipmentId > 0) $sql .= " AND p.fk_shipment=".$shipmentId;
    $sql .= " ORDER BY p.date_publication DESC,p.rowid DESC";

    $result = $db->query($sql);
    $items = array();
    while ($result && ($row = $db->fetch_object($result))) {
        $token = cpcc_portal_gallery_public_token($row->rowid, $entity, $socid);
        if ($token === '') continue;
        $row->public_token = $token;
        $row->events = cpcc_portal_collaboration_schema_ready($db) ? cpcc_portal_shipment_collaboration_events($db, $shipment !== null ? $shipment : (object) array('id'=>(int)$row->fk_shipment,'entity'=>$entity,'fk_soc'=>$socid), (int)$row->rowid) : array();
        unset($row->rowid);
        $items[] = $row;
    }
    return $items;
}

/** Resolve one opaque publication token inside the current customer scope. */
function cpcc_portal_customer_gallery_publication($db, $user, $publicToken)
{
    global $conf;

    if (!preg_match('/^gal_[A-Za-z0-9_-]{43}$/', (string) $publicToken)) return null;
    $scope = cpcc_portal_user_scope($user);
    $entity = !empty($conf->entity) ? (int) $conf->entity : 0;
    $socid = !empty($scope['is_external']) ? (int) $scope['socid'] : 0;
    if ($entity <= 0 || $socid <= 0 || !cpcc_portal_gallery_schema_ready($db) || cpcc_portal_gallery_token_secret() === '') return null;

    $sql = "SELECT p.rowid,p.fk_shipment,p.storage_key,p.original_name,p.mime_type,p.file_size,p.file_sha256,p.item_kind,";
    if (cpcc_portal_collaboration_schema_ready($db)) $sql .= "p.source_origin,p.response_status,p.customer_upload_status,p.response_note,p.date_response,";
    else $sql .= "'staff' AS source_origin,'published' AS response_status,NULL AS customer_upload_status,NULL AS response_note,NULL AS date_response,";
    $sql .= " t.ref AS shipment_ref";
    $sql .= " FROM ".MAIN_DB_PREFIX."cpl_shipment_public_document p";
    $sql .= " INNER JOIN ".MAIN_DB_PREFIX."chinaplanetlogistics t ON t.rowid=p.fk_shipment AND t.entity=p.entity AND t.fk_soc=p.fk_soc";
    $sql .= " WHERE p.entity=".$entity." AND p.fk_soc=".$socid." AND p.customer_visible=1 AND p.status=1";
    $result = $db->query($sql);
    while ($result && ($row = $db->fetch_object($result))) {
        $candidate = cpcc_portal_gallery_public_token($row->rowid, $entity, $socid);
        if ($candidate !== '' && hash_equals($candidate, (string) $publicToken)) return $row;
    }
    return null;
}

/**
 * Revalidate the immutable publication snapshot immediately before delivery.
 */
function cpcc_portal_resolve_customer_gallery_content($db, $user, $publicToken)
{
    $row = cpcc_portal_customer_gallery_publication($db, $user, $publicToken);
    if (!$row) return null;

    $fullPath = cpcc_portal_resolve_shipment_doc_path((int) $row->fk_shipment, (string) $row->shipment_ref, (string) $row->storage_key);
    if ($fullPath === '' || is_link($fullPath) || !is_file($fullPath) || !is_readable($fullPath)) return null;
    $actualSize = filesize($fullPath);
    if ($actualSize === false || (int) $actualSize <= 0 || (int) $actualSize !== (int) $row->file_size) return null;

    $validation = cpcc_portal_validate_shipment_upload((string) $row->original_name, $fullPath, (int) $actualSize);
    if (empty($validation['ok'])) return null;
    if (!hash_equals(strtolower((string) $row->mime_type), strtolower((string) $validation['mime']))) return null;
    if (!hash_equals((string) $row->item_kind, cpcc_portal_gallery_item_kind($validation['mime']))) return null;

    $actualHash = hash_file('sha256', $fullPath);
    if (!is_string($actualHash) || !preg_match('/^[a-f0-9]{64}$/', (string) $row->file_sha256) || !hash_equals((string) $row->file_sha256, $actualHash)) return null;

    return array(
        'full_path' => $fullPath,
        'name' => basename((string) $validation['name']),
        'mime' => strtolower((string) $validation['mime']),
        'size' => (int) $actualSize,
        'kind' => (string) $row->item_kind,
    );
}

function cpcc_portal_customer_gallery_content_url($publicToken, $preview = false)
{
    $parameters = array('type' => 'gallery', 'publication' => (string) $publicToken);
    if ($preview) $parameters['mode'] = 'preview';
    return dol_buildpath('/chinaplanetcustomercenter/download.php', 1).'?'.http_build_query($parameters);
}

/** Render safe gallery items shared by the shipment card and Documents page. */
function cpcc_portal_customer_gallery_items_html(array $items)
{
    global $langs;

    if (empty($items)) return '<div class="cpcc-gallery-empty"><span class="fas fa-photo-video" aria-hidden="true"></span><strong>'.dol_escape_htmltag($langs->trans('CPLGalleryCustomerEmpty')).'</strong><small>'.dol_escape_htmltag($langs->trans('CPLGalleryCustomerEmptyHint')).'</small></div>';

    $html = '<div class="cpcc-gallery-grid">';
    foreach ($items as $item) {
        $downloadUrl = cpcc_portal_customer_gallery_content_url($item->public_token, false);
        $isImage = (string) $item->item_kind === 'image';
        $html .= '<article class="cpcc-gallery-item'.($isImage ? ' is-image' : '').'">';
        if ($isImage) {
            $previewUrl = cpcc_portal_customer_gallery_content_url($item->public_token, true);
            $html .= '<a class="cpcc-gallery-preview" href="'.dol_escape_htmltag($downloadUrl).'" aria-label="'.dol_escape_htmltag($item->original_name).'">';
            $html .= '<img loading="lazy" src="'.dol_escape_htmltag($previewUrl).'" alt="'.dol_escape_htmltag($item->original_name).'">';
            $html .= '</a>';
        } else {
            $icon = (string) $item->item_kind === 'video' ? 'fa-file-video' : 'fa-file-download';
            $html .= '<span class="cpcc-gallery-file-icon fas '.dol_escape_htmltag($icon).'" aria-hidden="true"></span>';
        }
        $html .= '<div class="cpcc-gallery-item-copy"><strong>'.dol_escape_htmltag($item->original_name).'</strong>';
        $html .= '<small>'.dol_escape_htmltag(dol_print_size((int) $item->file_size)).'</small>';
        $html .= '<a class="cpcc-gallery-download" href="'.dol_escape_htmltag($downloadUrl).'">'.dol_escape_htmltag($langs->trans('CPLGalleryDownload')).'</a></div>';
        $html .= '</article>';
    }
    return $html.'</div>';
}

/** Split authorized publications by origin first, then by verified media type. */
function cpcc_portal_customer_shipment_file_groups(array $items)
{
    $groups = array('documents' => array(), 'customer_uploads' => array(), 'gallery' => array());
    foreach ($items as $item) {
        if ((string)($item->source_origin ?? 'staff') === 'customer') $groups['customer_uploads'][] = $item;
        elseif (in_array((string) $item->item_kind, array('image', 'video'), true)) $groups['gallery'][] = $item;
        else $groups['documents'][] = $item;
    }
    return $groups;
}

function cpcc_portal_customer_upload_items_html(array $items)
{
    global $langs;
    if (empty($items)) return '<div class="cpcc-gallery-empty cpcc-document-empty"><span class="fas fa-cloud-upload-alt" aria-hidden="true"></span><strong>'.dol_escape_htmltag($langs->trans('CPLCustomerUploadedFilesEmpty')).'</strong><small>'.dol_escape_htmltag($langs->trans('CPLCustomerUploadedFilesEmptyHint')).'</small></div>';
    $statusKeys = array(
        'waiting_receive'=>'CPLCustomerUploadStatusWaitingReceive',
        'received'=>'CPLCustomerUploadStatusReceived',
        'handling'=>'CPLCustomerUploadStatusHandling',
        'done'=>'CPLCustomerUploadStatusDone',
    );
    $html = '<div class="cpcc-shipment-documents-list cpcc-customer-uploaded-files">';
    foreach ($items as $item) {
        $status = !empty($item->customer_upload_status) ? (string)$item->customer_upload_status : 'waiting_receive';
        if (!isset($statusKeys[$status])) $status = 'waiting_receive';
        $downloadUrl = cpcc_portal_customer_gallery_content_url($item->public_token, false);
        $html .= '<article class="cpcc-shipment-document cpcc-customer-uploaded-file"><span class="fas fa-cloud-upload-alt" aria-hidden="true"></span><div><strong>'.dol_escape_htmltag($item->original_name).'</strong>';
        $html .= '<small>'.dol_escape_htmltag(dol_print_size((int)$item->file_size)).' · '.dol_escape_htmltag($langs->trans('CPLDocumentCustomerUpload')).'</small>';
        foreach ((array)($item->events ?? array()) as $event) {
            if (empty($event->note_public) || !in_array((string)$event->event_type, array('customer_upload','customer_upload_status'), true)) continue;
            $html .= '<p class="cpcc-document-event"><b>'.dol_escape_htmltag($event->actor_type === 'customer' ? $langs->trans('CPLCustomer') : $langs->trans('CPLChinaPlanetTeam')).':</b> '.nl2br(dol_escape_htmltag($event->note_public)).'</p>';
        }
        $html .= '</div><div class="cpcc-document-actions"><span class="cpcc-upload-processing-status status-'.dol_escape_htmltag($status).'">'.dol_escape_htmltag($langs->trans($statusKeys[$status])).'</span>';
        $html .= '<a class="cpcc-gallery-download" href="'.dol_escape_htmltag($downloadUrl).'">'.dol_escape_htmltag($langs->trans('CPLGalleryDownload')).'</a></div></article>';
    }
    return $html.'</div>';
}

/** Render customer-downloadable shipment documents, never raw paths or IDs. */
function cpcc_portal_customer_document_items_html(array $items, $shipmentId = 0)
{
    global $langs;
    if (empty($items)) {
        return '<div class="cpcc-gallery-empty cpcc-document-empty"><span class="fas fa-file-download" aria-hidden="true"></span><strong>'.dol_escape_htmltag($langs->trans('CPLShipmentDocumentsEmpty')).'</strong><small>'.dol_escape_htmltag($langs->trans('CPLShipmentDocumentsEmptyHint')).'</small></div>';
    }

    $html = '<div class="cpcc-shipment-documents-list">';
    foreach ($items as $item) {
        $downloadUrl = cpcc_portal_customer_gallery_content_url($item->public_token, false);
        $html .= '<article class="cpcc-shipment-document"><span class="fas fa-file-download" aria-hidden="true"></span><div><strong>'.dol_escape_htmltag($item->original_name).'</strong>';
        $statusKey = array('awaiting_confirmation'=>'CPLDocumentAwaitingConfirmation','confirmed'=>'CPLDocumentConfirmed','amendment_requested'=>'CPLDocumentAmendmentRequested','customer_upload'=>'CPLDocumentCustomerUpload');
        $status = isset($statusKey[(string)$item->response_status]) ? $statusKey[(string)$item->response_status] : 'CPLDocumentPublished';
        $html .= '<small>'.dol_escape_htmltag(dol_print_size((int) $item->file_size)).' · '.dol_escape_htmltag($langs->trans($status)).'</small>';
        $hasEventNote = false;
        foreach ((array)($item->events ?? array()) as $event) {
            if (empty($event->note_public)) continue;
            $hasEventNote = true;
            $html .= '<p class="cpcc-document-event"><b>'.dol_escape_htmltag($event->actor_type === 'customer' ? $langs->trans('CPLCustomer') : $langs->trans('CPLChinaPlanetTeam')).':</b> '.nl2br(dol_escape_htmltag($event->note_public)).'</p>';
        }
        if (!$hasEventNote && !empty($item->response_note)) $html .= '<p class="cpcc-document-note">'.nl2br(dol_escape_htmltag($item->response_note)).'</p>';
        $html .= '</div><div class="cpcc-document-actions"><a class="cpcc-gallery-download" href="'.dol_escape_htmltag($downloadUrl).'">'.dol_escape_htmltag($langs->trans('CPLGalleryDownload')).'</a>';
        if ((string)$item->source_origin === 'staff' && (string)$item->response_status !== 'confirmed' && (int)$shipmentId > 0) {
            $html .= '<form method="POST"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="id" value="'.(int)$shipmentId.'"><input type="hidden" name="action" value="customer_document_confirm"><input type="hidden" name="publication" value="'.dol_escape_htmltag($item->public_token).'"><button type="submit" class="button button-save">'.dol_escape_htmltag($langs->trans('CPLConfirmDocument')).'</button></form>';
        }
        if ((string)$item->source_origin === 'staff' && (int)$shipmentId > 0) {
            $html .= '<details class="cpcc-document-response"><summary>'.dol_escape_htmltag($langs->trans('CPLRequestAmendmentOrComment')).'</summary><form method="POST"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="id" value="'.(int)$shipmentId.'"><input type="hidden" name="publication" value="'.dol_escape_htmltag($item->public_token).'"><textarea required name="response_note" maxlength="2000" placeholder="'.dol_escape_htmltag($langs->trans('CPLWriteCustomerNote')).'"></textarea><button type="submit" name="action" value="customer_document_amend" class="button button-cancel">'.dol_escape_htmltag($langs->trans('CPLRequestAmendment')).'</button><button type="submit" name="action" value="customer_document_comment" class="button">'.dol_escape_htmltag($langs->trans('CPLAddComment')).'</button></form></details>';
        }
        $html .= '</div></article>';
    }
    return $html.'</div>';
}

/** Customer projection shared by shipment details and the Documents page. */
function cpcc_portal_customer_shipment_files_html($db, $user, $shipment)
{
    global $langs;
    $groups = cpcc_portal_customer_shipment_file_groups(cpcc_portal_customer_gallery_items($db, $user, $shipment));
    $html = '<section id="shipment-documents" class="cpl-panel cpcc-shipment-documents" aria-labelledby="cpcc-documents-title">';
    $html .= '<div class="cpl-section-title cpcc-gallery-title"><div><span>'.dol_escape_htmltag($langs->trans('CPLShipmentDocumentsEyebrow')).'</span><h2 id="cpcc-documents-title">'.dol_escape_htmltag($langs->trans('CPLShipmentDocumentsTitle')).'</h2></div><b>'.count($groups['documents']).'</b></div>';
    $html .= cpcc_portal_customer_document_items_html($groups['documents'], (int)$shipment->id);
    if (cpcc_portal_collaboration_schema_ready($db)) {
        $html .= '<div class="cpcc-customer-uploaded-section"><div class="cpcc-subsection-title"><div><span>'.dol_escape_htmltag($langs->trans('CPLCustomerUploadedFilesEyebrow')).'</span><h3>'.dol_escape_htmltag($langs->trans('CPLCustomerUploadedFilesTitle')).'</h3></div><b>'.count($groups['customer_uploads']).'</b></div>';
        $html .= cpcc_portal_customer_upload_items_html($groups['customer_uploads']).'</div>';
        $html .= '<div class="cpcc-customer-upload"><h3>'.dol_escape_htmltag($langs->trans('CPLUploadShipmentDocument')).'</h3><form method="POST" enctype="multipart/form-data"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="id" value="'.(int)$shipment->id.'"><input type="hidden" name="action" value="customer_document_upload"><input required type="file" name="customer_file"><textarea name="response_note" maxlength="2000" placeholder="'.dol_escape_htmltag($langs->trans('CPLWriteCustomerNote')).'"></textarea><button type="submit" class="button button-save">'.dol_escape_htmltag($langs->trans('CPLUpload')).'</button></form></div>';
        $events = cpcc_portal_shipment_collaboration_events($db, $shipment);
        $originalStatuses = cpcc_portal_original_document_statuses(); $latestOriginal = ''; $originalReceived = false;
        foreach ($events as $event) { if ($event->event_type === 'original_document_status') $latestOriginal = (string)$event->status_code; if ($event->event_type === 'original_documents_received') $originalReceived = true; }
        if ($latestOriginal !== '') {
            $currentIndex = array_search($originalReceived ? 'customer_received' : $latestOriginal, $originalStatuses, true);
            $html .= '<div class="cpcc-original-documents"><h3>'.dol_escape_htmltag($langs->trans('CPLOriginalDocumentsTimeline')).'</h3><ol>';
            foreach ($originalStatuses as $index=>$code) $html .= '<li class="'.($index <= $currentIndex ? 'is-complete' : '').'"><span>'.dol_escape_htmltag($langs->trans('CPLOriginalStatus'.str_replace(' ', '', ucwords(str_replace('_', ' ', $code))))).'</span></li>';
            $html .= '</ol>';
            if ($latestOriginal === 'delivered' && !$originalReceived) $html .= '<form method="POST"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="id" value="'.(int)$shipment->id.'"><input type="hidden" name="action" value="customer_original_documents_received"><button type="submit" class="button button-save">'.dol_escape_htmltag($langs->trans('CPLConfirmOriginalDocumentsReceived')).'</button></form>';
            $html .= '</div>';
        }
    }
    $html .= '</section>';
    $html .= '<section id="shipment-gallery" class="cpl-panel cpcc-shipment-gallery" aria-labelledby="cpcc-gallery-title">';
    $html .= '<div class="cpl-section-title cpcc-gallery-title"><div><span>'.dol_escape_htmltag($langs->trans('CPLGalleryEyebrow')).'</span><h2 id="cpcc-gallery-title">'.dol_escape_htmltag($langs->trans('CPLGalleryCustomerTitle')).'</h2></div><b>'.count($groups['gallery']).'</b></div>';
    return $html.cpcc_portal_customer_gallery_items_html($groups['gallery']).'</section>';
}

function cpcc_portal_customer_shipment_gallery_html($db, $user, $shipment, $includeHeading = true)
{
    global $langs;
    $items = cpcc_portal_customer_gallery_items($db, $user, $shipment);
    $html = '<section class="cpl-panel cpcc-shipment-gallery" aria-labelledby="cpcc-gallery-title">';
    if ($includeHeading) {
        $html .= '<div class="cpl-section-title cpcc-gallery-title"><div><span>'.dol_escape_htmltag($langs->trans('CPLGalleryEyebrow')).'</span>';
        $html .= '<h2 id="cpcc-gallery-title">'.dol_escape_htmltag($langs->trans('CPLGalleryCustomerTitle')).'</h2></div><b>'.count($items).'</b></div>';
    }
    return $html.cpcc_portal_customer_gallery_items_html($items).'</section>';
}

/** Staff-only manager embedded in shipment view and edit pages. */
function cpcc_portal_staff_shipment_files_html($db, $shipment, $canwrite, $actionUrl, $candelete = false)
{
    if(cpcc_shared_documents_enabled()){
        require_once __DIR__.'/../../chinaplanetlogistics/lib/shared_documents.lib.php';
        return cpl_shared_documents_html($db,$shipment);
    }

    global $langs;
    $shipmentId = !empty($shipment->id) ? (int) $shipment->id : (int) ($shipment->rowid ?? 0);
    if ($shipmentId <= 0) return '';

    $files = array();
    foreach (cpcc_portal_shipment_doc_roots($shipmentId, (string) $shipment->ref) as $docroot) {
        if (!is_dir($docroot)) continue;
        $rows = dol_dir_list($docroot, 'files', 0, '', '(\\.meta|_preview.*\\.png)$', 'date', SORT_DESC);
        foreach ((array) $rows as $row) {
            $row['docroot'] = $docroot;
            $files[] = $row;
        }
    }

    $ready = cpcc_portal_gallery_schema_ready($db);
    $publications = $ready ? cpcc_portal_gallery_publications($db, $shipment, false) : array();
    $html = '<section id="shipment-files-manager" class="cpl-doc-panel"><div class="cpl-section-title"><div><span>'.$langs->trans('CPLShipmentFilesManagerEyebrow').'</span><h2>'.$langs->trans('CPLShipmentFilesManagerTitle').'</h2></div></div>';
    $html .= '<p class="opacitymedium">'.$langs->trans('CPLShipmentFilesManagerHint').'</p>';
    if ($canwrite) {
        $html .= '<form action="'.dol_escape_htmltag($actionUrl).'" method="POST" enctype="multipart/form-data" class="cpl-file-upload-form"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="sendit" value="1"><input required type="file" name="userfile"><button type="submit" class="button button-save">'.$langs->trans('CPLShipmentFileUpload').'</button></form>';
    }
    if (!$ready) $html .= '<div class="warning">'.dol_escape_htmltag($langs->trans('CPLGallerySchemaMissing')).'</div>';
    $collaborationReady = cpcc_portal_collaboration_schema_ready($db);
    $collaborationEvents = $collaborationReady ? cpcc_portal_shipment_collaboration_events($db, $shipment) : array();
    $latestOriginal = ''; $originalReceived = false; $containerReceived = false; $containerReceiptNote = '';
    foreach ($collaborationEvents as $event) {
        if ($event->event_type === 'original_document_status') $latestOriginal = (string)$event->status_code;
        if ($event->event_type === 'original_documents_received') $originalReceived = true;
        if ($event->event_type === 'container_received') { $containerReceived = true; $containerReceiptNote = (string)$event->note_public; }
    }
    if ($containerReceived) $html .= '<div class="cpl-staff-customer-event"><strong>'.dol_escape_htmltag($langs->trans('CPLContainerReceivedByCustomer')).'</strong>'.($containerReceiptNote !== '' ? '<small>'.nl2br(dol_escape_htmltag($containerReceiptNote)).'</small>' : '').'</div>';
    if ($latestOriginal !== '') {
        $statuses = cpcc_portal_original_document_statuses();
        $currentIndex = array_search($originalReceived ? 'customer_received' : $latestOriginal, $statuses, true);
        $html .= '<div class="cpcc-original-documents"><h3>'.dol_escape_htmltag($langs->trans('CPLOriginalDocumentsTimeline')).'</h3><ol>';
        foreach ($statuses as $index=>$code) { $key = 'CPLOriginalStatus'.str_replace(' ', '', ucwords(str_replace('_', ' ', $code))); $html .= '<li class="'.($index <= $currentIndex ? 'is-complete' : '').'"><span>'.dol_escape_htmltag($langs->trans($key)).'</span></li>'; }
        $html .= '</ol></div>';
    }
    if ($canwrite && $collaborationReady) {
        $html .= '<form action="'.dol_escape_htmltag($actionUrl).'" method="POST" class="cpl-original-status-form"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="staff_original_document_status"><label>'.$langs->trans('CPLOriginalDocumentsTimeline').'<select name="original_document_status">';
        foreach (cpcc_portal_original_document_statuses() as $status) { if ($status === 'customer_received') continue; $key = 'CPLOriginalStatus'.str_replace(' ', '', ucwords(str_replace('_', ' ', $status))); $html .= '<option value="'.dol_escape_htmltag($status).'">'.dol_escape_htmltag($langs->trans($key)).'</option>'; }
        $html .= '</select></label><input type="text" name="original_document_note" maxlength="2000" placeholder="'.dol_escape_htmltag($langs->trans('CPLPublicNote')).'"><button type="submit" class="button">'.dol_escape_htmltag($langs->trans('CPLUpdateOriginalDocuments')).'</button></form>';
    }
    $html .= '<div class="div-table-responsive"><table class="border centpercent"><tr class="liste_titre"><td>'.$langs->trans('CPLGalleryFileName').'</td><td>'.$langs->trans('CPLShipmentFileType').'</td><td align="right">'.$langs->trans('CPLGallerySize').'</td><td align="center">'.$langs->trans('CPLGalleryDate').'</td><td align="center">'.$langs->trans('CPLGalleryVisibility').'</td><td align="center">'.$langs->trans('CPLDocumentCustomerActivity').'</td><td align="center">'.$langs->trans('CPLGalleryAction').'</td></tr>';
    if (empty($files)) $html .= '<tr><td colspan="7" class="center opacitymedium">'.$langs->trans('CPLGalleryNoFiles').'</td></tr>';
    foreach ($files as $file) {
        $relativePath = basename($file['docroot']).'/'.$file['name'];
        $publication = isset($publications[$relativePath]) ? $publications[$relativePath] : null;
        $metadata = cpcc_portal_gallery_file_metadata($shipment, $relativePath);
        $kind = !empty($metadata['ok']) ? (string) $metadata['item_kind'] : 'document';
        $isMedia = in_array($kind, array('image', 'video'), true);
        $sourceOrigin = $publication && isset($publication->source_origin) ? (string)$publication->source_origin : 'staff';
        $isCustomerUpload = $sourceOrigin === 'customer';
        $isPublished = $publication && !empty($publication->customer_visible) && !empty($publication->status);
        $downloadUrl = dol_buildpath('/chinaplanetcustomercenter/download.php', 1).'?type=shipment&id='.$shipmentId.'&file='.urlencode($relativePath);
        $html .= '<tr><td><a href="'.dol_escape_htmltag($downloadUrl).'" target="_blank" rel="noopener">'.dol_escape_htmltag($file['name']).'</a></td>';
        $sectionKey = $isCustomerUpload ? 'CPLShipmentFileTypeCustomerUpload' : ($isMedia ? 'CPLShipmentFileTypeGallery' : 'CPLShipmentFileTypeDocument');
        $actualSize = !empty($metadata['ok']) ? (int)$metadata['file_size'] : (int)($file['size'] ?? 0);
        $html .= '<td>'.dol_escape_htmltag($langs->trans($sectionKey)).'</td><td align="right">'.dol_print_size($actualSize).'</td><td align="center">'.dol_print_date($file['date'], 'dayhour').'</td>';
        $responseStatus = $publication && isset($publication->response_status) ? (string)$publication->response_status : '';
        $responseKey = array('awaiting_confirmation'=>'CPLDocumentAwaitingConfirmation','confirmed'=>'CPLDocumentConfirmed','amendment_requested'=>'CPLDocumentAmendmentRequested','customer_upload'=>'CPLDocumentCustomerUpload');
        $html .= '<td align="center"><span class="badge '.($isPublished ? 'badge-status4' : 'badge-status0').'">'.$langs->trans($isPublished ? 'CPLGalleryCustomerVisible' : 'CPLGalleryInternalOnly').'</span></td><td align="center">';
        if ($isCustomerUpload) {
            $processingStatus = !empty($publication->customer_upload_status) ? (string)$publication->customer_upload_status : 'waiting_receive';
            $processingKeys = array('waiting_receive'=>'CPLCustomerUploadStatusWaitingReceive','received'=>'CPLCustomerUploadStatusReceived','handling'=>'CPLCustomerUploadStatusHandling','done'=>'CPLCustomerUploadStatusDone');
            if (!isset($processingKeys[$processingStatus])) $processingStatus = 'waiting_receive';
            $html .= '<span class="cpcc-upload-processing-status status-'.dol_escape_htmltag($processingStatus).'">'.dol_escape_htmltag($langs->trans($processingKeys[$processingStatus])).'</span>';
            if ($canwrite && $isPublished && $collaborationReady) {
                $currentIndex = array_search($processingStatus, cpcc_portal_customer_upload_statuses(), true);
                $html .= '<form action="'.dol_escape_htmltag($actionUrl).'" method="POST" class="cpl-customer-upload-status-form"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="staff_customer_upload_status"><input type="hidden" name="publication_id" value="'.(int)$publication->rowid.'"><select name="customer_upload_status">';
                foreach (cpcc_portal_customer_upload_statuses() as $index=>$status) if ($index >= $currentIndex) $html .= '<option value="'.dol_escape_htmltag($status).'"'.($status === $processingStatus ? ' selected' : '').'>'.dol_escape_htmltag($langs->trans($processingKeys[$status])).'</option>';
                $html .= '</select><input type="text" name="customer_upload_note" maxlength="2000" placeholder="'.dol_escape_htmltag($langs->trans('CPLPublicNote')).'"><button type="submit" class="button button-small">'.dol_escape_htmltag($langs->trans('CPLUpdateCustomerUploadStatus')).'</button></form>';
            }
        } else {
            $html .= $responseStatus !== '' ? dol_escape_htmltag($langs->trans($responseKey[$responseStatus] ?? 'CPLDocumentPublished')) : '&mdash;';
        }
        $publicationEvents = ($publication && $collaborationReady) ? cpcc_portal_shipment_collaboration_events($db, $shipment, (int)$publication->rowid) : array();
        $hasPublicationEventNote = false;
        foreach ($publicationEvents as $event) {
            if (empty($event->note_public)) continue;
            $hasPublicationEventNote = true;
            $html .= '<br><small><b>'.dol_escape_htmltag($event->actor_type === 'customer' ? $langs->trans('CPLCustomer') : $langs->trans('CPLChinaPlanetTeam')).':</b> '.nl2br(dol_escape_htmltag($event->note_public)).'</small>';
        }
        if (!$hasPublicationEventNote && $publication && !empty($publication->response_note)) $html .= '<br><small>'.nl2br(dol_escape_htmltag($publication->response_note)).'</small>';
        $html .= '</td><td align="center">';
        if ($canwrite && $ready) {
            $html .= '<form action="'.dol_escape_htmltag($actionUrl).'" method="POST" style="display:inline"><input type="hidden" name="token" value="'.newToken().'">';
            if ($isPublished) $html .= '<input type="hidden" name="action" value="unpublish_gallery"><input type="hidden" name="publication_id" value="'.(int) $publication->rowid.'"><button type="submit" class="button button-cancel">'.$langs->trans('CPLGalleryUnpublish').'</button>';
            else $html .= '<input type="hidden" name="action" value="publish_gallery"><input type="hidden" name="file" value="'.dol_escape_htmltag($relativePath).'"><button type="submit" class="button button-save">'.$langs->trans('CPLGalleryPublish').'</button>';
            $html .= '</form>';
        } else $html .= '&mdash;';
        if ($candelete && $collaborationReady) {
            $html .= '<details class="cpl-file-archive"><summary>'.dol_escape_htmltag($langs->trans('CPLShipmentFileDelete')).'</summary><form action="'.dol_escape_htmltag($actionUrl).'" method="POST"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="archive_shipment_file"><input type="hidden" name="file" value="'.dol_escape_htmltag($relativePath).'"><label><input type="checkbox" required> '.dol_escape_htmltag($langs->trans('CPLShipmentFileArchiveConfirm')).'</label><button type="submit" class="button button-cancel">'.dol_escape_htmltag($langs->trans('CPLShipmentFileArchiveAction')).'</button></form></details>';
        }
        $html .= '</td></tr>';
    }
    return $html.'</table></div></section>';
}
