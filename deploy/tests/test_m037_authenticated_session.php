<?php
/** Authenticated route rendering for M037 canonical staging. */

if (PHP_SAPI !== 'cli') { fwrite(STDERR, "M037_AUTH_ROUTE=FAIL REASON=CLI_ONLY\n"); exit(1); }

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
);

$isRenderChild = $argc === 5 && $argv[1] === '--render';
$role = $isRenderChild ? $argv[2] : ($argv[1] ?? '');
$routeKey = strtolower(trim($isRenderChild ? $argv[3] : ($argv[2] ?? '')));
if ((!$isRenderChild && $argc !== 3) || !in_array($role, array('admin', 'external'), true)) {
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
    $childToken = (string) getenv('M037_AUTH_CHILD_TOKEN');
    $childLogin = (string) getenv('M037_AUTH_CHILD_LOGIN');
    $childEntity = (int) getenv('M037_AUTH_CHILD_ENTITY');
    if (strlen($childToken) < 32 || !hash_equals($childToken, (string) $argv[4]) || strlen($childLogin) < 1 || $childEntity < 1) {
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
    register_shutdown_function(function () use ($routeKey) {
        if (!empty($GLOBALS['m037_route_completed'])) return;
        $body = ob_get_level() > 0 ? (string) ob_get_contents() : '';
        if (ob_get_level() > 0) ob_end_clean();
        $lastError = error_get_last();
        if ($lastError && in_array((int) $lastError['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR), true)) {
            $reason = 'FATAL_ERROR';
        } elseif (http_response_code() === 403) {
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
    } catch (Throwable $exception) {
        if (ob_get_level() > 0) ob_end_clean();
        $failure = 'ROUTE_EXCEPTION';
    }

    if ($failure === '' && (!isset($user) || !is_object($user) || empty($user->id))) {
        $failure = 'USER_NOT_AUTHENTICATED';
    } elseif ($failure === '' && $role === 'admin' && (empty($user->admin) || !empty($user->socid))) {
        $failure = 'ADMIN_SCOPE_INVALID';
    } elseif ($failure === '' && $role === 'external' && (!empty($user->admin) || empty($user->socid))) {
        $failure = 'EXTERNAL_SCOPE_INVALID';
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
    if ($role === 'external'
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
    || ($role === 'external' && (!empty($routeUser->admin) || empty($routeUser->socid)))) {
    fwrite(STDERR, "M037_AUTH_ROUTE=FAIL REASON=ROLE_SCOPE_INVALID\n");
    exit(1);
}
$childToken = bin2hex(random_bytes(24));
$environment = array_merge($_ENV, array(
    'M037_DOL_DOCUMENT_ROOT' => $documentRoot,
    'M037_AUTH_CHILD_TOKEN' => $childToken,
    'M037_AUTH_CHILD_LOGIN' => (string) $routeUser->login,
    'M037_AUTH_CHILD_ENTITY' => (string) (int) $conf->entity,
));
$pipes = array();
$process = proc_open(
    array(PHP_BINARY, __FILE__, '--render', $role, $routeKey, $childToken),
    array(0 => array('file', '/dev/null', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w')),
    $pipes,
    null,
    $environment
);
if (!is_resource($process)) {
    fwrite(STDERR, "M037_AUTH_ROUTE=FAIL REASON=CHILD_START_FAILED\n");
    exit(1);
}
$stdout = stream_get_contents($pipes[1]);
$stderr = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
$exitCode = proc_close($process);
$expected = "M037_AUTH_ROUTE_".strtoupper($role)."_".strtoupper($routeKey)."=PASS";
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
