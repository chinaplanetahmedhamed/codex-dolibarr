<?php
/** Save/reset the personal internal dashboard layout. */
if (!defined('NOREQUIREMENU')) define('NOREQUIREMENU', '1');
if (!defined('NOREQUIREHTML')) define('NOREQUIREHTML', '1');
if (!defined('NOTOKENRENEWAL')) define('NOTOKENRENEWAL', '1');

require_once __DIR__.'/../lib/main.lib.php';

header('Content-Type: application/json; charset=utf-8');

function cpad_layout_response($success, $message)
{
    print json_encode(array('success'=>(bool) $success, 'message'=>(string) $message));
    exit;
}

if (empty($user->id) || !empty($user->socid) || empty($user->admin)) {
    cpad_layout_response(false, 'forbidden');
}

$token = GETPOST('token', 'alphanohtml');
if (empty($token) || !hash_equals((string) currentToken(), (string) $token)) {
    cpad_layout_response(false, 'invalid_token');
}

$tableCheck = $db->query("SHOW TABLES LIKE '".$db->escape(MAIN_DB_PREFIX.'cpcc_dashboard_layout')."'");
if (!$tableCheck || $db->num_rows($tableCheck) === 0) {
    cpad_layout_response(false, 'layout_schema_missing');
}

$dashboardKey = 'admin_operations';
$allowedWidgets = array(
    'kpi_customers','kpi_orders','kpi_revenue','kpi_shipments',
    'shipment_control','currency_mix','workflow','monthly_revenue',
    'live_shipments','top_customers','operations_snapshot','quick_access',
);
$allowedSizes = array('small','medium','large','full');
$action = GETPOST('action', 'aZ09');

if ($action === 'reset') {
    $sql = "DELETE FROM ".MAIN_DB_PREFIX."cpcc_dashboard_layout WHERE entity=".(int) $conf->entity." AND fk_user=".(int) $user->id." AND dashboard_key='".$db->escape($dashboardKey)."'";
    if (!$db->query($sql)) cpad_layout_response(false, $db->lasterror());
    cpad_layout_response(true, 'reset');
}

if ($action !== 'save') cpad_layout_response(false, 'invalid_action');

$rawLayout = isset($_POST['layout']) ? (string) $_POST['layout'] : '';
$layout = json_decode($rawLayout, true);
if (!is_array($layout) || count($layout) > count($allowedWidgets)) {
    cpad_layout_response(false, 'invalid_layout');
}

$clean = array();
$seen = array();
foreach ($layout as $position => $item) {
    if (!is_array($item)) cpad_layout_response(false, 'invalid_widget');
    $key = isset($item['key']) ? (string) $item['key'] : '';
    $size = isset($item['size']) ? (string) $item['size'] : 'medium';
    if (!in_array($key, $allowedWidgets, true) || isset($seen[$key])) cpad_layout_response(false, 'invalid_widget_key');
    if (!in_array($size, $allowedSizes, true)) $size = 'medium';
    $seen[$key] = true;
    $clean[] = array('key'=>$key, 'position'=>(int) $position, 'size'=>$size, 'visible'=>empty($item['visible']) ? 0 : 1);
}

foreach ($allowedWidgets as $key) {
    if (!isset($seen[$key])) $clean[] = array('key'=>$key, 'position'=>count($clean), 'size'=>'medium', 'visible'=>0);
}

$db->begin();
$deleteSql = "DELETE FROM ".MAIN_DB_PREFIX."cpcc_dashboard_layout WHERE entity=".(int) $conf->entity." AND fk_user=".(int) $user->id." AND dashboard_key='".$db->escape($dashboardKey)."'";
if (!$db->query($deleteSql)) {
    $db->rollback();
    cpad_layout_response(false, $db->lasterror());
}

foreach ($clean as $item) {
    $sql = "INSERT INTO ".MAIN_DB_PREFIX."cpcc_dashboard_layout(entity,fk_user,dashboard_key,widget_key,position,widget_size,visible,date_creation) VALUES(";
    $sql .= (int) $conf->entity.",".(int) $user->id.",'".$db->escape($dashboardKey)."','".$db->escape($item['key'])."',".(int) $item['position'].",'".$db->escape($item['size'])."',".(int) $item['visible'].",'".$db->idate(dol_now())."')";
    if (!$db->query($sql)) {
        $db->rollback();
        cpad_layout_response(false, $db->lasterror());
    }
}
$db->commit();
cpad_layout_response(true, 'saved');
