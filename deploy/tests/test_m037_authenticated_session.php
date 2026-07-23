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

$mode = $argv[1] ?? '';
$isIssue = $argc === 5 && $mode === '--issue';
$isRender = $argc === 5 && $mode === '--render';
$role = ($isIssue || $isRender) ? $argv[2] : '';
$routeKey = strtolower(trim(($isIssue || $isRender) ? $argv[3] : ''));
$contextToken = ($isIssue || $isRender) ? strtolower(trim($argv[4])) : '';
if ((!$isIssue && !$isRender) || !in_array($role, array('admin', 'external', 'external_denied'), true)) {
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

function m037ContextPath($token)
{
    $contextDir = getenv('M037_AUTH_CONTEXT_DIR');
    if (!is_string($contextDir)
        || !preg_match('#^/(?:private/)?tmp/m037-auth-context-[A-Za-z0-9]{1,32}$#D', $contextDir)
        || !preg_match('/^[a-f0-9]{64}$/D', $token)
        || !function_exists('posix_geteuid')) {
        fwrite(STDERR, "M037_AUTH_ROUTE=FAIL REASON=CONTEXT_CONTRACT_INVALID\n");
        exit(1);
    }
    $dirStat = @lstat($contextDir);
    if (!is_array($dirStat)
        || ($dirStat['mode'] & 0170000) !== 0040000
        || ($dirStat['mode'] & 0777) !== 0700
        || (int) $dirStat['uid'] !== (int) posix_geteuid()
        || realpath($contextDir) !== $contextDir) {
        fwrite(STDERR, "M037_AUTH_ROUTE=FAIL REASON=CONTEXT_DIRECTORY_INVALID\n");
        exit(1);
    }
    return $contextDir.'/'.$token.'.json';
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

if ($isRender) {
    $contextPath = m037ContextPath($contextToken);
    $contextStat = @lstat($contextPath);
    if (!is_array($contextStat)
        || ($contextStat['mode'] & 0170000) !== 0100000
        || ($contextStat['mode'] & 0777) !== 0600
        || (int) $contextStat['uid'] !== (int) posix_geteuid()
        || $contextStat['size'] < 1
        || $contextStat['size'] > 4096
        || realpath($contextPath) !== $contextPath) {
        fwrite(STDERR, "M037_AUTH_ROUTE=FAIL REASON=CHILD_CONTEXT_FILE_INVALID\n");
        exit(1);
    }
    $contextHandle = @fopen($contextPath, 'rb');
    if (!is_resource($contextHandle) || !flock($contextHandle, LOCK_EX)) {
        if (is_resource($contextHandle)) fclose($contextHandle);
        fwrite(STDERR, "M037_AUTH_ROUTE=FAIL REASON=CHILD_CONTEXT_OPEN_FAILED\n");
        exit(1);
    }
    $openStat = fstat($contextHandle);
    $contextJson = stream_get_contents($contextHandle, 4097);
    $contextConsumed = is_array($openStat)
        && (int) $openStat['dev'] === (int) $contextStat['dev']
        && (int) $openStat['ino'] === (int) $contextStat['ino']
        && is_string($contextJson)
        && strlen($contextJson) <= 4096
        && @unlink($contextPath);
    flock($contextHandle, LOCK_UN);
    fclose($contextHandle);
    if (!$contextConsumed) {
        fwrite(STDERR, "M037_AUTH_ROUTE=FAIL REASON=CHILD_CONTEXT_CONSUME_FAILED\n");
        exit(1);
    }
    $childContext = json_decode($contextJson, true);
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

$bootstrapBaseLevel = ob_get_level();
$GLOBALS['m037_parent_bootstrap_completed'] = false;
ob_start();
register_shutdown_function(function () use ($bootstrapBaseLevel) {
    if (!empty($GLOBALS['m037_parent_bootstrap_completed'])) return;
    $body = '';
    while (ob_get_level() > $bootstrapBaseLevel) {
        $chunk = (string) ob_get_contents();
        ob_end_clean();
        $body = $chunk.$body;
    }
    $lastError = error_get_last();
    if ($lastError && in_array((int) $lastError['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR), true)) {
        $reason = 'PARENT_BOOTSTRAP_FATAL_ERROR';
    } elseif (stripos($body, 'Dolibarr has detected a technical error') !== false) {
        $reason = 'PARENT_BOOTSTRAP_DOLIBARR_ERROR';
    } elseif (strpos($body, 'id="login"') !== false && strpos($body, 'name="username"') !== false && strpos($body, 'name="password"') !== false) {
        $reason = 'PARENT_BOOTSTRAP_LOGIN_PAGE';
    } else {
        $reason = 'PARENT_BOOTSTRAP_EARLY_EXIT';
    }
    fwrite(
        STDERR,
        "M037_AUTH_ROUTE=FAIL REASON=".$reason.
        " OUTPUT_BYTES=".strlen($body).
        " OUTPUT_SHA256=".hash('sha256', $body)."\n"
    );
});
require $documentRoot.'/main.inc.php';
$bootstrapOutput = '';
while (ob_get_level() > $bootstrapBaseLevel) {
    $chunk = (string) ob_get_contents();
    ob_end_clean();
    $bootstrapOutput = $chunk.$bootstrapOutput;
}
$GLOBALS['m037_parent_bootstrap_completed'] = true;
if (trim($bootstrapOutput) !== '') {
    if (stripos($bootstrapOutput, 'Dolibarr has detected a technical error') !== false) {
        $bootstrapReason = 'PARENT_BOOTSTRAP_DOLIBARR_ERROR';
    } elseif (preg_match('/PHP (Fatal error|Parse error|Warning|Notice|Deprecated):/i', $bootstrapOutput)) {
        $bootstrapReason = 'PARENT_BOOTSTRAP_PHP_OUTPUT';
    } elseif (strpos($bootstrapOutput, 'id="login"') !== false
        && strpos($bootstrapOutput, 'name="username"') !== false
        && strpos($bootstrapOutput, 'name="password"') !== false) {
        $bootstrapReason = 'PARENT_BOOTSTRAP_LOGIN_PAGE';
    } else {
        $bootstrapReason = 'PARENT_BOOTSTRAP_OUTPUT';
    }
    fwrite(
        STDERR,
        "M037_AUTH_ROUTE=FAIL REASON=".$bootstrapReason.
        " OUTPUT_BYTES=".strlen($bootstrapOutput).
        " OUTPUT_SHA256=".hash('sha256', $bootstrapOutput)."\n"
    );
    exit(1);
}
require_once $documentRoot.'/user/class/user.class.php';
global $db, $conf, $user;

$where = $role === 'admin'
    ? "u.admin=1 AND COALESCE(u.fk_soc,0)=0"
    : "u.admin=0 AND COALESCE(u.fk_soc,0)>0";
$res = $db->query(
    "SELECT u.rowid FROM ".MAIN_DB_PREFIX."user u".
    " WHERE u.entity IN (".getEntity('user').") AND u.statut=1 AND ".$where.
    " ORDER BY u.rowid LIMIT 100"
);
if (!$res) {
    fwrite(STDERR, "M037_AUTH_ROUTE=FAIL REASON=ROLE_QUERY_FAILED\n");
    exit(1);
}
$routeUser = null;
$candidateCount = 0;
$loadableCount = 0;
$rightsCount = 0;
while ($row = $db->fetch_object($res)) {
    $candidateCount++;
    $candidate = new User($db);
    if ($candidate->fetch((int) $row->rowid) <= 0 || $candidate->getrights('', 1) < 0) continue;
    $loadableCount++;
    if ($role !== 'admin'
        && (!$candidate->hasRight('chinaplanetcustomercenter', 'read')
            || !$candidate->hasRight('chinaplanetlogistics', 'read')
            || !$candidate->hasRight('chinaplanetshippingprices', 'read')
            || !$candidate->hasRight('chinaplanetshippingprices', 'booking_submit'))) continue;
    $rightsCount++;
    $routeUser = $candidate;
    break;
}
if (!$routeUser) {
    fwrite(
        STDERR,
        "M037_AUTH_ROUTE=FAIL REASON=ROLE_USER_ABSENT".
        " CANDIDATES=".$candidateCount.
        " LOADABLE=".$loadableCount.
        " RIGHTS_MATCH=".$rightsCount."\n"
    );
    exit(1);
}
if (($role === 'admin' && (empty($routeUser->admin) || !empty($routeUser->socid)))
    || ($role !== 'admin' && (!empty($routeUser->admin) || empty($routeUser->socid)))) {
    fwrite(STDERR, "M037_AUTH_ROUTE=FAIL REASON=ROLE_SCOPE_INVALID\n");
    exit(1);
}
$contextPath = m037ContextPath($contextToken);
$contextJson = json_encode(array('login' => (string) $routeUser->login, 'entity' => (int) $conf->entity));
if (!is_string($contextJson) || strlen($contextJson) < 1 || strlen($contextJson) > 4096) {
    fwrite(STDERR, "M037_AUTH_ROUTE=FAIL REASON=CONTEXT_ENCODING_FAILED\n");
    exit(1);
}
$contextHandle = @fopen($contextPath, 'x+b');
if (!is_resource($contextHandle) || !@chmod($contextPath, 0600) || !flock($contextHandle, LOCK_EX)) {
    if (is_resource($contextHandle)) fclose($contextHandle);
    @unlink($contextPath);
    fwrite(STDERR, "M037_AUTH_ROUTE=FAIL REASON=CONTEXT_CREATE_FAILED\n");
    exit(1);
}
$written = fwrite($contextHandle, $contextJson);
$issued = $written === strlen($contextJson) && fflush($contextHandle);
flock($contextHandle, LOCK_UN);
fclose($contextHandle);
$issuedStat = @lstat($contextPath);
if (!$issued
    || !is_array($issuedStat)
    || ($issuedStat['mode'] & 0170000) !== 0100000
    || ($issuedStat['mode'] & 0777) !== 0600
    || (int) $issuedStat['uid'] !== (int) posix_geteuid()
    || (int) $issuedStat['size'] !== strlen($contextJson)) {
    @unlink($contextPath);
    fwrite(STDERR, "M037_AUTH_ROUTE=FAIL REASON=CONTEXT_WRITE_FAILED\n");
    exit(1);
}
echo "M037_AUTH_CONTEXT_ISSUED=PASS\n";
