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
$publication = GETPOST('publication', 'alphanohtml');
$mode = GETPOST('mode', 'alpha');

/** Return one uniform response without disclosing whether a publication exists. */
$galleryNotFound = static function () {
    while (ob_get_level()) ob_end_clean();
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    header('Cache-Control: private, no-store, max-age=0');
    header('Pragma: no-cache');
    header('X-Content-Type-Options: nosniff');
    print 'Not found';
    exit;
};

if ($type === 'gallery') {
    $scope = cpcc_portal_user_scope($user);
    if (empty($scope['is_external']) || $publication === '') $galleryNotFound();

    $content = cpcc_portal_resolve_customer_gallery_content($db, $user, $publication);
    if (!$content) $galleryNotFound();

    $videoPreview = $mode === 'preview' && $content['kind'] === 'video';
    $inline = $videoPreview || ($mode === 'preview' && $content['kind'] === 'image');
    $safeName = dol_sanitizeFileName(basename((string) $content['name']));
    if ($safeName === '') $galleryNotFound();
    $asciiName = preg_replace('/[^A-Za-z0-9._-]+/', '_', $safeName);
    if ($asciiName === '') $asciiName = 'shipment-file';

    $size = (int) $content['size'];
    $range = null;
    if ($videoPreview && !empty($_SERVER['HTTP_RANGE'])) {
        $range = cpcc_portal_gallery_video_range($_SERVER['HTTP_RANGE'], $size);
    }
    $handle = fopen($content['full_path'], 'rb');
    if ($handle === false) $galleryNotFound();

    while (ob_get_level()) ob_end_clean();
    header('Cache-Control: private, no-store, max-age=0');
    if ($videoPreview) header('Accept-Ranges: bytes');
    if ($videoPreview && !empty($_SERVER['HTTP_RANGE']) && $range === null) {
        fclose($handle);
        http_response_code(416);
        header('Content-Range: bytes */'.$size);
        exit;
    }
    $length = $size;
    if ($range !== null) {
        list($start, $end) = $range;
        if (fseek($handle, $start) !== 0) {
            fclose($handle);
            $galleryNotFound();
        }
        $length = $end - $start + 1;
        http_response_code(206);
        header('Content-Range: bytes '.$start.'-'.$end.'/'.$size);
    }
    header('Content-Type: '.$content['mime']);
    header('Content-Length: '.$length);
    header('Content-Disposition: '.($inline ? 'inline' : 'attachment').'; filename="'.$asciiName.'"; filename*=UTF-8\'\''.rawurlencode($safeName));
    header('Pragma: no-cache');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'HEAD') {
        if ($range === null) {
            fpassthru($handle);
        } else {
            while ($length > 0 && !feof($handle)) {
                $chunk = fread($handle, min($length, 65536));
                if ($chunk === false || $chunk === '') break;
                echo $chunk;
                $length -= strlen($chunk);
            }
        }
    }
    fclose($handle);
    exit;
}

if ($type === 'shipment') {
    if ($id <= 0 || empty($file)) accessforbidden();
    $shipment = cpcc_portal_authorized_shipment($db, $user, $id);
    if (!$shipment) accessforbidden();

    // Legacy shipment folders have no per-file visibility/approval metadata.
    // Deny external delivery until a separate approved document model exists.
    $scope = cpcc_portal_user_scope($user);
    if (!empty($scope['is_external'])) accessforbidden();
} else {
    accessforbidden();
}

if(cpcc_shared_documents_enabled()){
    try{$service=new ShipmentDocumentService($db,$user);$document=$service->byStorage($id,$file);$content=$service->content($document['rowid']);}
    catch(Throwable $e){accessforbidden();}
    $full_path=$content['path'];
}else $full_path = cpcc_portal_resolve_shipment_doc_path($id, $shipment->ref, $file);
if (empty($full_path)) accessforbidden();

while (ob_get_level()) ob_end_clean();
$downloadName = dol_sanitizeFileName(isset($content)?$content['name']:basename($full_path));
header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="'.rawurlencode($downloadName).'"');
header('Content-Length: '.filesize($full_path));
header('Cache-Control: private, no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
readfile($full_path);
exit;
