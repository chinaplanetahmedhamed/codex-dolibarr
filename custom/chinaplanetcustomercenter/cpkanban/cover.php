<?php
@error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);
require_once __DIR__.'/lib/main.lib.php';

require_once __DIR__.'/class/cpkanbancard.class.php';
require_once __DIR__.'/class/cpkanbanasset.class.php';

if (empty($user->id)) accessforbidden();
$id = GETPOSTINT('id');
$sql = "SELECT a.* FROM ".MAIN_DB_PREFIX."cpkanban_asset a WHERE a.rowid=".$id." AND a.entity=".(int)$conf->entity." AND a.asset_type='image' AND a.is_cover=1 AND a.status=1";
$resql = $db->query($sql);
$asset = $resql ? $db->fetch_object($resql) : null;
if (!$asset) { http_response_code(404); exit; }

$isExternal = (!empty($user->socid) && empty($user->admin));
if ($isExternal) {
    $card = new CpKanbanCard($db);
    if (empty($asset->customer_visible) || $asset->review_status !== 'approved' || $card->fetch((int)$asset->fk_card, (int)$user->socid) <= 0 || (int)$card->fk_soc !== (int)$asset->fk_soc) {
        http_response_code(403); exit;
    }
} elseif (!($user->hasRight('cpkanban','read') || $user->hasRight('chinaplanetcustomercenter','read') || !empty($user->admin))) {
    accessforbidden();
}

$dir = CpKanbanAsset::coverDirectory($asset->fk_card);
$base = realpath($dir);
$path = realpath($dir.'/'.$asset->stored_name);
if ($base === false || $path === false || strpos($path, $base.DIRECTORY_SEPARATOR) !== 0 || !is_file($path)) { http_response_code(404); exit; }
$allowed = array('image/jpeg','image/png','image/webp');
if (!in_array($asset->mime_type, $allowed, true)) { http_response_code(415); exit; }
header('Content-Type: '.$asset->mime_type);
header('Content-Length: '.filesize($path));
header('Content-Disposition: inline; filename="cover"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=300');
readfile($path);
exit;
