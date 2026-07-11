<?php
if (!isset($_SERVER['QUERY_STRING'])) $_SERVER['QUERY_STRING'] = '';

$res = 0;
if (!$res && file_exists("../../main.inc.php")) $res = @include "../../main.inc.php";
if (!$res && file_exists("../../../main.inc.php")) $res = @include "../../../main.inc.php";
if (!$res) die("Include of main fails");

require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once __DIR__.'/lib/portal.lib.php';

cpcc_portal_require_access($user);

$type = GETPOST('type', 'alpha');
$id = GETPOSTINT('id');
$file = GETPOST('file', 'restricthtml');
$file = ltrim($file, '/\\');

if ($id <= 0 || empty($file)) {
    accessforbidden('Bad download request.');
}

$allowed_root = '';
$allowed_ref = '';
$allowed_socid = 0;

if ($type === 'shipment') {
    $allowed_root = DOL_DATA_ROOT.'/chinaplanetlogistics';
    $sql = "SELECT rowid, ref, fk_soc FROM ".MAIN_DB_PREFIX."chinaplanetlogistics WHERE rowid = ".((int) $id);
    $resql = $db->query($sql);
    if (!$resql || !($obj = $db->fetch_object($resql))) {
        accessforbidden('Shipment not found.');
    }
    $allowed_ref = dol_sanitizeFileName($obj->ref);
    $allowed_socid = (int) $obj->fk_soc;
    if (!empty($user->socid) && empty($user->admin) && $allowed_socid > 0 && (int) $user->socid !== $allowed_socid) {
        accessforbidden();
    }
} else {
    accessforbidden('Unsupported download type.');
}

if ($type === 'shipment') {
    $full_path = cpcc_portal_resolve_shipment_doc_path($id, $allowed_ref, $file);
    if (empty($full_path)) accessforbidden('Invalid file path.');
}

while (ob_get_level()) ob_end_clean();
header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: inline; filename="'.basename($full_path).'"');
header('Content-Length: '.filesize($full_path));
header('X-Content-Type-Options: nosniff');
readfile($full_path);
exit;
