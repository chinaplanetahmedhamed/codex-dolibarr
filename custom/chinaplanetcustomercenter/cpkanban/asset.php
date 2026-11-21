<?php
/**
 * Scoped customer download/view endpoint for approved Kanban card assets.
 */
define('NOREQUIREHTML', 1);
define('NOREQUIREMENU', 1);

@error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);
require_once __DIR__.'/lib/main.lib.php';
require_once __DIR__.'/class/cpkanbancard.class.php';
require_once __DIR__.'/class/cpkanbanasset.class.php';

if (empty($user->id)) accessforbidden();
$id = GETPOSTINT('id');
$sql = "SELECT a.* FROM ".MAIN_DB_PREFIX."cpkanban_asset a";
$sql .= " WHERE a.rowid=".(int) $id." AND a.entity=".(int) $conf->entity;
$sql .= " AND a.asset_type IN ('image','document') AND a.status=1";
$resql = $db->query($sql);
$asset = $resql ? $db->fetch_object($resql) : null;
if (!$asset) { http_response_code(404); exit; }

$isExternal = (!empty($user->socid) && empty($user->admin));
if ($isExternal) {
    $card = new CpKanbanCard($db);
    $owned = $card->fetch((int) $asset->fk_card, (int) $user->socid) > 0;
    $approved = in_array((string) $asset->review_status, array('approved', 'final_received'), true);
    if (!$owned || empty($asset->customer_visible) || !$approved || (int) $card->entity !== (int) $asset->entity || (int) $card->fk_soc !== (int) $asset->fk_soc) {
        http_response_code(403); exit;
    }
} elseif (!($user->hasRight('cpkanban', 'read') || $user->hasRight('chinaplanetcustomercenter', 'read') || !empty($user->admin))) {
    accessforbidden();
}

$dir = CpKanbanAsset::coverDirectory($asset->fk_card);
$root = realpath($dir);
$path = realpath($dir.'/'.$asset->stored_name);
if ($root === false || $path === false || strpos($path, rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR) !== 0 || !is_file($path)) {
    http_response_code(404); exit;
}

$imageMimes = array('image/jpeg', 'image/png', 'image/webp');
$documentMimes = array(
    'application/pdf',
    'text/plain',
    'text/csv',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'application/vnd.ms-powerpoint',
    'application/vnd.openxmlformats-officedocument.presentationml.presentation',
);
$mime = (string) $asset->mime_type;
if ($asset->asset_type === 'image' && !in_array($mime, $imageMimes, true)) { http_response_code(415); exit; }
if ($asset->asset_type === 'document' && !in_array($mime, $documentMimes, true)) $mime = 'application/octet-stream';

$filename = basename((string) $asset->original_name);
header('Content-Type: '.$mime);
header('Content-Length: '.filesize($path));
header('Content-Disposition: '.($asset->asset_type === 'image' ? 'inline' : 'attachment').'; filename="'.rawurlencode($filename).'"');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('Cache-Control: private, max-age=300');
if ($asset->asset_type === 'image') header("Content-Security-Policy: default-src 'none'; img-src 'self'");
readfile($path);
exit;
