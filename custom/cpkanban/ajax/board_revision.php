<?php
/** Read-only revision for the authenticated internal project Kanban. */
if (!defined('NOREQUIREMENU')) define('NOREQUIREMENU', '1');
if (!defined('NOREQUIREHTML')) define('NOREQUIREHTML', '1');
require_once __DIR__.'/../lib/main.lib.php';
require_once __DIR__.'/../class/cpkanbancard.class.php';
require_once __DIR__.'/../class/cpkanbancolumn.class.php';

if (empty($user->id) || !empty($user->socid)) accessforbidden();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
$filters = array();
if (GETPOST('search_soc', 'int')) $filters['fk_soc'] = GETPOST('search_soc', 'int');
if (GETPOST('search_user', 'int')) $filters['fk_user_assign'] = GETPOST('search_user', 'int');
if (GETPOST('search_pri', 'alpha')) $filters['priority'] = GETPOST('search_pri', 'alpha');
if (GETPOST('search_text', 'alpha')) $filters['search'] = GETPOST('search_text', 'alpha');
$columns = CpKanbanColumn::getAll($db, false, true);
$board = CpKanbanCard::getBoard($db, 0, $filters);
echo json_encode(array('revision' => hash('sha256', json_encode(array($board, $columns), JSON_INVALID_UTF8_SUBSTITUTE))));
