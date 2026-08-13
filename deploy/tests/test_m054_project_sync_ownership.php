<?php
/** Source characterization for M054 project-sync ownership. */
$root = dirname(__DIR__, 2);
$read = function ($path) use ($root) {
    $data = file_get_contents($root.'/'.$path);
    if ($data === false) throw new RuntimeException('Unreadable '.$path);
    return $data;
};
$assert = function ($condition, $message) {
    if (!$condition) throw new RuntimeException($message);
};

try {
    $canonical = $read('custom/cpkanban/lib/projectsync.lib.php');
    $compatibility = $read('custom/chinaplanetcustomercenter/lib/projectsync.lib.php');
    $board = $read('custom/cpkanban/board.php');
    $projects = $read('custom/chinaplanetcustomercenter/projects.php');
    $trigger = $read('custom/chinaplanetcustomercenter/core/triggers/interface_99_modChinaPlanetCustomerCenter_CustomerProjectSync.class.php');

    foreach (array(
        'cpcc_project_status_to_column_code',
        'cpcc_project_fetch_kanban_card_row',
        'cpcc_project_default_column_id',
        'cpcc_project_sync_kanban_card',
        'cpcc_project_sync_existing_cards',
    ) as $function) {
        $assert(strpos($canonical, 'function '.$function) !== false, 'CANONICAL_FUNCTION_MISSING_'.$function);
        $assert(strpos($compatibility, 'function '.$function) === false, 'LEGACY_PATH_STILL_OWNS_'.$function);
    }

    $assert(strpos($compatibility, "require_once DOL_DOCUMENT_ROOT.'/custom/cpkanban/lib/projectsync.lib.php'") !== false, 'COMPATIBILITY_INCLUDE_MISSING');
    $assert(strpos($canonical, "MAIN_DB_PREFIX.\"cpkanban_card") !== false, 'CARD_OWNERSHIP_QUERY_MISSING');
    $assert(strpos($canonical, "MAIN_DB_PREFIX.\"cpkanban_column") !== false, 'COLUMN_OWNERSHIP_QUERY_MISSING');
    $assert(strpos($canonical, "getEntity('project')") !== false, 'PROJECT_ENTITY_SCOPE_MISSING');
    $assert(strpos($board, "require_once __DIR__.'/lib/projectsync.lib.php'") !== false, 'CPKANBAN_BOARD_CANONICAL_INCLUDE_MISSING');
    $assert(strpos($board, 'chinaplanetcustomercenter/lib/projectsync.lib.php') === false, 'CPKANBAN_BOARD_STILL_LOADS_CUSTOMER_CENTER_SYNC');
    $assert(strpos($projects, "custom/cpkanban/lib/projectsync.lib.php") !== false, 'CUSTOMER_CENTER_PROJECTS_CANONICAL_INCLUDE_MISSING');
    $assert(strpos($trigger, "custom/cpkanban/lib/projectsync.lib.php") !== false, 'CUSTOMER_CENTER_TRIGGER_CANONICAL_INCLUDE_MISSING');

    echo "M054_PROJECT_SYNC_OWNERSHIP=PASS owner=CPKANBAN compatibility=RETAINED behavior=CHARACTERIZED\n";
} catch (Throwable $e) {
    fwrite(STDERR, "M054_PROJECT_SYNC_OWNERSHIP=FAIL REASON=".$e->getMessage()."\n");
    exit(1);
}
