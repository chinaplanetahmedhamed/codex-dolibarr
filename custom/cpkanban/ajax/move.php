<?php
/**
 * China Planet Kanban - AJAX endpoint: move a card to another column.
 * Only internal staff with write permission may move cards.
 * Customers (external users) are rejected.
 */
@error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);

if (!defined('NOREQUIREMENU')) define('NOREQUIREMENU', '1');
if (!defined('NOREQUIREHTML')) define('NOREQUIREHTML', '1');

$res = 0;
if (!$res && file_exists("../../../main.inc.php")) $res = @include "../../../main.inc.php";
if (!$res && file_exists("../../../../main.inc.php")) $res = @include "../../../../main.inc.php";
if (!$res) die("Include of main fails");

require_once __DIR__.'/../class/cpkanbancard.class.php';

header('Content-Type: application/json; charset=utf-8');

function cpk_json($ok, $msg = '', $extra = array())
{
    echo json_encode(array_merge(array('success' => $ok, 'message' => $msg), $extra));
    exit;
}

// Must be logged in
if (empty($user->id)) cpk_json(false, 'not_authenticated');

// Customers cannot move cards
if (!empty($user->socid)) cpk_json(false, 'forbidden_external');

// Write permission required
if (!$user->hasRight('cpkanban', 'write') && empty($user->admin)) cpk_json(false, 'forbidden_perm');

// CSRF token check
$token = GETPOST('token', 'alpha');
if (!$token || $token != $_SESSION['newtoken']) {
    // Dolibarr stores valid tokens; fall back to checkToken if available
    if (function_exists('checkToken') && !checkToken($token)) cpk_json(false, 'bad_token');
}

$card_id = GETPOST('card_id', 'int');
$col_id  = GETPOST('col_id', 'int');
if ($card_id <= 0 || $col_id <= 0) cpk_json(false, 'bad_params');

$card = new CpKanbanCard($db);
$r = $card->fetch($card_id); // internal fetch (socid=0)
if ($r <= 0) cpk_json(false, 'card_not_found');

$r = $card->moveToColumn($user, $col_id);
if ($r <= 0) cpk_json(false, 'move_failed: '.$card->error);

cpk_json(true, 'moved', array('card_id' => $card_id, 'col_id' => $col_id, 'progress' => (int) $card->progress));
