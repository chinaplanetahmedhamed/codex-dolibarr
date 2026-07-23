<?php
/** Authenticated route rendering for M037 canonical staging. */

if (PHP_SAPI !== 'cli') { fwrite(STDERR, "M037_AUTH_ROUTE=FAIL REASON=CLI_ONLY\n"); exit(1); }
const M037_AUTH_POSITIVE_ROUTE_COUNT = 14;

$routes = array(
    'admin' => array(
        'admin_dashboard' => '/custom/chinaplanetcustomercenter/admin_dashboard.php',
        'financial_portfolio' => '/custom/chinaplanetcustomercenter/admin/financial_portfolio.php',
        'operations' => '/custom/chinaplanetcustomercenter/admin/operations.php',
        'logistics_index' => '/custom/chinaplanetlogistics/index.php',
        'shipping_orders' => '/custom/chinaplanetoperations/operations/shipping_orders.php',
        'shipping_sop' => '/custom/chinaplanetshippingprices/sop/index.php',
    ),
    'external' => array(
        'board' => '/custom/chinaplanetcustomercenter/board.php',
        'documents' => '/custom/chinaplanetcustomercenter/documents.php',
        'operation_reports' => '/custom/chinaplanetcustomercenter/operation_reports.php',
        'projects' => '/custom/chinaplanetcustomercenter/projects.php',
        'orders' => '/custom/chinaplanetcustomercenter/orders.php',
        'balance' => '/custom/chinaplanetcustomercenter/balance.php',
        'logistics_list' => '/custom/chinaplanetlogistics/list.php',
        'shipping_prices' => '/custom/chinaplanetshippingprices/index.php',
    ),
    'external_denied' => array(
        'admin_dashboard' => '/custom/chinaplanetcustomercenter/admin_dashboard.php',
    ),
);
$routeAnchors = array(
    'admin' => array(
        'admin_dashboard' => 'id="cpad-customize"',
        'financial_portfolio' => 'class="cpad-finance-portfolio-theme"',
        'operations' => 'class="cpad-context-form"',
        'logistics_index' => 'id="tracking-overview"',
        'shipping_orders' => 'SHIPPING BOOKING OPERATIONS',
        'shipping_sop' => 'CONTROLLED OPERATIONS LIBRARY',
    ),
    'external' => array(
        'board' => 'CHINAPLANET CUSTOMER CENTER',
        'documents' => 'cpcc-documents-page',
        'operation_reports' => 'class="cpcc-eyebrow">REPORTS',
        'projects' => 'cpcc-projects-page',
        'orders' => 'cpcc-orders-page',
        'balance' => 'FINANCIAL STATEMENT',
        'logistics_list' => 'cpcc-shipments-page',
        'shipping_prices' => 'class="cpsb"',
    ),
);
if (count($routes['admin']) + count($routes['external']) !== M037_AUTH_POSITIVE_ROUTE_COUNT
    || count($routeAnchors['admin']) + count($routeAnchors['external']) !== M037_AUTH_POSITIVE_ROUTE_COUNT) {
    fwrite(STDERR, "M037_AUTH_ROUTE=FAIL REASON=ROUTE_CONTRACT_INVALID\n");
    exit(1);
}

$isRenderChild = $argc === 4 && $argv[1] === '--render';
$role = $isRenderChild ? $argv[2] : ($argv[1] ?? '');
$routeKey = strtolower(trim($isRenderChild ? $argv[3] : ($argv[2] ?? '')));
if ((!$isRenderChild && $argc !== 3) || !in_array($role, array('admin', 'external', 'external_denied'), true)) {
    fwrite(STDERR, "M037_AUTH_ROUTE=FAIL REASON=COMMAND_NOT_ALLOWED\n");
    exit(1);
}
if (!isset($routes[$role][$routeKey])) {
    fwrite(STDERR, "M037_AUTH_ROUTE=FAIL REASON=ROUTE_NOT_ALLOWED\n");
    exit(1);
}
$route = $routes[$role][$routeKey];

$documentRoot = getenv('M037_DOL_DOCUMENT_ROOT');
if ($documentRoot === false || $documentRoot === '') $documentRoot = '/var/www/html';
$documentRoot = rtrim($documentRoot, '/');
if (!is_file($documentRoot.'/main.inc.php')) {
    fwrite(STDERR, "M037_AUTH_ROUTE=FAIL REASON=DOLIBARR_ROOT_INVALID\n");
    exit(1);
}

$_SERVER['DOCUMENT_ROOT'] = $documentRoot;
$_SERVER['SERVER_NAME'] = '127.0.0.1';
$_SERVER['SERVER_PORT'] = '80';
$_SERVER['HTTP_HOST'] = '127.0.0.1';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['QUERY_STRING'] = '';
$_SERVER['REQUEST_URI'] = $route;
$_SERVER['PHP_SELF'] = $route;
$_SERVER['SCRIPT_NAME'] = $route;
$_SERVER['SCRIPT_FILENAME'] = $documentRoot.$route;
$_GET = array();
$_POST = array();
$_REQUEST = array();

if ($isRenderChild) {
    $contextJson = file_get_contents('php://stdin', false, null, 0, 4097);
    $childContext = is_string($contextJson) && strlen($contextJson) <= 4096 ? json_decode($contextJson, true) : null;
    $childLogin = is_array($childContext) && isset($childContext['login']) ? (string) $childContext['login'] : '';
    $childEntity = is_array($childContext) && isset($childContext['entity']) ? (int) $childContext['entity'] : 0;
    if (!is_array($childContext) || count($childContext) !== 2 || strlen($childLogin) < 1 || strlen($childLogin) > 255 || $childEntity < 1) {
        fwrite(STDERR, "M037_AUTH_ROUTE=FAIL REASON=CHILD_CONTEXT_INVALID\n");
        exit(1);
    }

    define('NOSESSION', 1);
    define('NOREQUIREMENU', 1);
    define('NOREQUIREHTML', 1);
    define('NOREQUIREAJAX', 1);
    define('NOCSRFCHECK', 1);
    $_SESSION = array(
        'dol_login' => $childLogin,
        'dol_entity' => $childEntity,
        'dol_logindate' => time(),
        'dol_authmode' => 'm037-route-test',
        'dol_tz' => 0,
        'dol_dst' => 0,
        'dol_screenwidth' => 1440,
        'dol_screenheight' => 900,
    );

    $failure = '';
    $GLOBALS['m037_route_completed'] = false;
    ob_start();
    register_shutdown_function(function () use ($role, $routeKey) {
        if (!empty($GLOBALS['m037_route_completed'])) return;
        $body = ob_get_level() > 0 ? (string) ob_get_contents() : '';
        if (ob_get_level() > 0) ob_end_clean();
        $lastError = error_get_last();
        if ($lastError && in_array((int) $lastError['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR), true)) {
            $reason = 'FATAL_ERROR';
        } elseif ($role === 'external_denied' && strpos($body, 'class="error"') !== false && strpos($body, 'Administrator access required') !== false) {
            echo "M037_AUTH_ROUTE_EXTERNAL_ADMIN_DASHBOARD=DENIED\n";
            return;
        } elseif (http_response_code() === 403) {
            if ($role === 'external_denied') {
                echo "M037_AUTH_ROUTE_EXTERNAL_ADMIN_DASHBOARD=DENIED\n";
                return;
            }
            $reason = 'ACCESS_DENIED';
        } elseif (in_array(http_response_code(), array(301, 302, 303, 307, 308), true)) {
            $reason = 'UNEXPECTED_REDIRECT';
        } elseif (strpos($body, 'id="login"') !== false && strpos($body, 'name="username"') !== false && strpos($body, 'name="password"') !== false) {
            $reason = 'LOGIN_PAGE';
        } else {
            $reason = 'EARLY_EXIT';
        }
        fwrite(STDERR, "M037_AUTH_ROUTE=FAIL REASON=".$routeKey."_".$reason."\n");
    });
    try {
        include $documentRoot.$route;
        $body = ob_get_clean();
        $isLogin = strpos($body, 'id="login"') !== false && strpos($body, 'name="username"') !== false && strpos($body, 'name="password"') !== false;
        if ($isLogin) $failure = 'LOGIN_PAGE';
        elseif (stripos($body, 'Not Found') !== false) $failure = 'NOT_FOUND';
        elseif (stripos($body, 'Fatal error') !== false) $failure = 'FATAL_ERROR';
        elseif (stripos($body, 'Administrator access required') !== false) $failure = 'ACCESS_DENIED';
        elseif (in_array(http_response_code(), array(301, 302, 303, 307, 308), true)) $failure = 'UNEXPECTED_REDIRECT';
        elseif (trim($body) === '') $failure = 'EMPTY_RESPONSE';
        elseif ($role !== 'external_denied' && strpos($body, $routeAnchors[$role][$routeKey]) === false) $failure = 'ROUTE_ANCHOR_MISSING';
    } catch (Throwable $exception) {
        if (ob_get_level() > 0) ob_end_clean();
        $failure = 'ROUTE_EXCEPTION';
    }

    if ($failure === '' && (!isset($user) || !is_object($user) || empty($user->id))) {
        $failure = 'USER_NOT_AUTHENTICATED';
    } elseif ($failure === '' && $role === 'admin' && (empty($user->admin) || !empty($user->socid))) {
        $failure = 'ADMIN_SCOPE_INVALID';
    } elseif ($failure === '' && $role !== 'admin' && (!empty($user->admin) || empty($user->socid))) {
        $failure = 'EXTERNAL_SCOPE_INVALID';
    } elseif ($failure === '' && $role === 'external_denied') {
        $failure = 'ADMIN_ROUTE_RENDERED';
    }

    if ($failure !== '') {
        $GLOBALS['m037_route_completed'] = true;
        fwrite(STDERR, "M037_AUTH_ROUTE=FAIL REASON=".$routeKey."_".$failure."\n");
        exit(1);
    }
    $GLOBALS['m037_route_completed'] = true;
    echo "M037_AUTH_ROUTE_".strtoupper($role)."_".strtoupper($routeKey)."=PASS\n";
    exit(0);
}

define('NOLOGIN', 1);
define('NOREQUIREMENU', 1);
define('NOREQUIREHTML', 1);
define('NOREQUIREAJAX', 1);
define('NOCSRFCHECK', 1);

require $documentRoot.'/main.inc.php';
require_once $documentRoot.'/user/class/user.class.php';
global $db, $conf, $user;

$where = $role === 'admin'
    ? "u.admin=1 AND u.fk_soc IS NULL"
    : "u.admin=0 AND u.fk_soc IS NOT NULL AND u.fk_soc>0";
$res = $db->query(
    "SELECT u.rowid FROM ".MAIN_DB_PREFIX."user u".
    " WHERE u.entity=".(int) $conf->entity." AND u.statut=1 AND ".$where.
    " ORDER BY u.rowid LIMIT 100"
);
$routeUser = null;
while ($res && ($row = $db->fetch_object($res))) {
    $candidate = new User($db);
    if ($candidate->fetch((int) $row->rowid) <= 0 || $candidate->getrights('', 1) < 0) continue;
    if ($role !== 'admin'
        && (!$candidate->hasRight('chinaplanetcustomercenter', 'read')
            || !$candidate->hasRight('chinaplanetlogistics', 'read')
            || !$candidate->hasRight('chinaplanetshippingprices', 'read')
            || !$candidate->hasRight('chinaplanetshippingprices', 'booking_submit'))) continue;
    $routeUser = $candidate;
    break;
}
if (!$routeUser) {
    fwrite(STDERR, "M037_AUTH_ROUTE=FAIL REASON=ROLE_USER_ABSENT\n");
    exit(1);
}
if (($role === 'admin' && (empty($routeUser->admin) || !empty($routeUser->socid)))
    || ($role !== 'admin' && (!empty($routeUser->admin) || empty($routeUser->socid)))) {
    fwrite(STDERR, "M037_AUTH_ROUTE=FAIL REASON=ROLE_SCOPE_INVALID\n");
    exit(1);
}
$environment = array_merge($_ENV, array(
    'M037_DOL_DOCUMENT_ROOT' => $documentRoot,
));
$pipes = array();
$process = proc_open(
    array(PHP_BINARY, __FILE__, '--render', $role, $routeKey),
    array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w')),
    $pipes,
    null,
    $environment
);
if (!is_resource($process)) {
    fwrite(STDERR, "M037_AUTH_ROUTE=FAIL REASON=CHILD_START_FAILED\n");
    exit(1);
}
$contextJson = json_encode(array('login' => (string) $routeUser->login, 'entity' => (int) $conf->entity));
if (!is_string($contextJson) || strlen($contextJson) > 4096 || fwrite($pipes[0], $contextJson) !== strlen($contextJson)) {
    fclose($pipes[0]);
    proc_terminate($process);
    proc_close($process);
    fwrite(STDERR, "M037_AUTH_ROUTE=FAIL REASON=CHILD_CONTEXT_WRITE_FAILED\n");
    exit(1);
}
fclose($pipes[0]);
$stdout = stream_get_contents($pipes[1]);
$stderr = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
$exitCode = proc_close($process);
$expected = $role === 'external_denied'
    ? 'M037_AUTH_ROUTE_EXTERNAL_ADMIN_DASHBOARD=DENIED'
    : "M037_AUTH_ROUTE_".strtoupper($role)."_".strtoupper($routeKey)."=PASS";
if ($exitCode !== 0 || trim($stdout) !== $expected) {
    if (preg_match('/M037_AUTH_ROUTE=FAIL REASON=[A-Za-z0-9_]+/', $stderr, $match)) {
        $safeReason = $match[0];
    } elseif (strpos($stdout, 'id="login"') !== false && strpos($stdout, 'name="username"') !== false && strpos($stdout, 'name="password"') !== false) {
        $safeReason = 'M037_AUTH_ROUTE=FAIL REASON=CHILD_LOGIN_PAGE';
    } elseif (stripos($stdout, 'Access forbidden') !== false || stripos($stdout, 'Administrator access required') !== false) {
        $safeReason = 'M037_AUTH_ROUTE=FAIL REASON=CHILD_ACCESS_DENIED';
    } elseif (stripos($stdout, 'Not Found') !== false) {
        $safeReason = 'M037_AUTH_ROUTE=FAIL REASON=CHILD_NOT_FOUND';
    } elseif (stripos($stdout, 'Fatal error') !== false) {
        $safeReason = 'M037_AUTH_ROUTE=FAIL REASON=CHILD_FATAL_ERROR';
    } elseif (preg_match('/PHP (Fatal error|Parse error|Warning):/', $stderr)) {
        $safeReason = 'M037_AUTH_ROUTE=FAIL REASON=CHILD_PHP_RUNTIME_ERROR';
    } elseif (strpos($stdout, $expected) !== false) {
        $safeReason = 'M037_AUTH_ROUTE=FAIL REASON=CHILD_OUTPUT_CONTAMINATED';
    } else {
        $safeReason = 'M037_AUTH_ROUTE=FAIL REASON=CHILD_RENDER_FAILED EXIT='.(int) $exitCode.' STDOUT_BYTES='.strlen($stdout).' STDERR_BYTES='.strlen($stderr);
    }
    fwrite(STDERR, $safeReason."\n");
    exit(1);
}
echo $expected."\n";
