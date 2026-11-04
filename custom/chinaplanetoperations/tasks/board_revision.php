<?php
/** Read-only revision for the authenticated task Kanban view. */
if (!defined('NOREQUIREMENU')) define('NOREQUIREMENU', '1');
if (!defined('NOREQUIREHTML')) define('NOREQUIREHTML', '1');
require $_SERVER['DOCUMENT_ROOT'].'/main.inc.php';
require_once __DIR__.'/../lib/main.lib.php';
require_once __DIR__.'/../class/InternalTaskService.class.php';
require_once __DIR__.'/../class/InternalTaskCollaborationService.class.php';
require_once __DIR__.'/../class/InternalTaskPersonalBoard.class.php';
require_once __DIR__.'/../class/InternalTaskDetailsService.class.php';
cpo_task_require($db, $user);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
try {
    $service = new InternalTaskService($db, $user);
    $auth = $service->authorization();
    $view = $auth->normalizeView(GETPOST('view', 'alphanohtml'));
    $postedLabels = isset($_GET['label_ids']) && is_array($_GET['label_ids']) ? $_GET['label_ids'] : array();
    $filters = array('view'=>$view, 'q'=>GETPOST('q','alphanohtml'), 'status'=>GETPOST('status','alphanohtml'), 'priority'=>GETPOST('priority','alphanohtml'), 'due'=>GETPOST('due','alphanohtml'), 'team_id'=>GETPOSTINT('team_id'), 'assignee_id'=>GETPOSTINT('assignee_id'), 'operation_id'=>GETPOSTINT('operation_id'), 'label_ids'=>array_values(array_filter(array_map('intval',$postedLabels))));
    $tasks = $service->listTasks($filters);
    $columns = $service->stageService()->listStages($view === 'archive');
    $collaboration = new InternalTaskCollaborationService($db, $user);
    $taskCounters = $collaboration->counters(array_column($tasks, 'rowid'));
    $personalState = null;
    if ($view === 'my') {
        $personalBoard = new InternalTaskPersonalBoard($db, $user);
        $personalState = $personalBoard->get();
        $tasks = $personalBoard->arrange($tasks, $personalState);
        $columns = $personalState['columns'];
    }
    $extraCards = (new InternalTaskDetailsService($db, $user))->forBoard($tasks);
    if ($view === 'archive' && $tasks) {
        $used = array_flip(array_map('intval', array_column($tasks, 'fk_stage')));
        $columns = array_values(array_filter($columns, function ($stage) use ($used) { return isset($used[(int)$stage['rowid']]); }));
    }
    $revision = hash('sha256', json_encode(array($tasks, $columns, $taskCounters, $extraCards, $personalState), JSON_INVALID_UTF8_SUBSTITUTE));
    echo json_encode(array('revision' => $revision));
} catch (Throwable $e) {
    http_response_code(503);
    echo json_encode(array('error'=>'unavailable'));
}
