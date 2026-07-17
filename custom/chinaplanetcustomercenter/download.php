<?php
if (!isset($_SERVER['QUERY_STRING'])) $_SERVER['QUERY_STRING'] = '';

require_once __DIR__.'/lib/main.lib.php';

require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once __DIR__.'/lib/portal.lib.php';

cpcc_portal_require_access($user, array(
    array('chinaplanetcustomercenter', 'read'),
    array('chinaplanetlogistics', 'read'),
));

$type = GETPOST('type', 'alpha');
$id = GETPOSTINT('id');
$file = GETPOST('file', 'restricthtml');
$file = ltrim($file, '/\\');

if ($id <= 0 || empty($file)) {
    accessforbidden();
}

if ($type === 'shipment') {
    $shipment = cpcc_portal_authorized_shipment($db, $user, $id);
    if (!$shipment) accessforbidden();

    // Legacy shipment folders have no per-file visibility/approval metadata.
    // Deny external delivery until a separate approved document model exists.
    $scope = cpcc_portal_user_scope($user);
    if (!empty($scope['is_external'])) accessforbidden();
} else {
    accessforbidden();
}

$full_path = cpcc_portal_resolve_shipment_doc_path($id, $shipment->ref, $file);
if (empty($full_path)) accessforbidden();

while (ob_get_level()) ob_end_clean();
$downloadName = dol_sanitizeFileName(basename($full_path));
header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="'.rawurlencode($downloadName).'"');
header('Content-Length: '.filesize($full_path));
header('X-Content-Type-Options: nosniff');
readfile($full_path);
exit;
