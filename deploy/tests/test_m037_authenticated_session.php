<?php
/** Short-lived authenticated HTTP route checks for M037 canonical staging. */

if (PHP_SAPI !== 'cli') { fwrite(STDERR, "M037_AUTH_HTTP=FAIL REASON=CLI_ONLY\n"); exit(1); }
if ($argc !== 2 || !in_array($argv[1], array('admin','external'), true)) { fwrite(STDERR, "M037_AUTH_HTTP=FAIL REASON=COMMAND_NOT_ALLOWED\n"); exit(1); }

define('NOLOGIN', 1);
define('NOREQUIREMENU', 1);
define('NOREQUIREHTML', 1);
define('NOREQUIREAJAX', 1);
define('NOCSRFCHECK', 1);

$documentRoot = getenv('M037_DOL_DOCUMENT_ROOT');
if ($documentRoot === false || $documentRoot === '') $documentRoot = '/var/www/html';
$documentRoot = rtrim($documentRoot, '/');
if (!is_file($documentRoot.'/main.inc.php')) { fwrite(STDERR, "M037_AUTH_HTTP=FAIL REASON=DOLIBARR_ROOT_INVALID\n"); exit(1); }
if (!function_exists('curl_init')) { fwrite(STDERR, "M037_AUTH_HTTP=FAIL REASON=CURL_EXTENSION_ABSENT\n"); exit(1); }
$_SERVER['DOCUMENT_ROOT'] = $documentRoot;
$_SERVER['SERVER_NAME'] = '127.0.0.1';
$_SERVER['SERVER_PORT'] = '80';
require $documentRoot.'/main.inc.php';
global $db, $conf;

$role = $argv[1];
$where = $role === 'admin'
    ? "u.admin=1 AND u.fk_soc IS NULL"
    : "u.admin=0 AND u.fk_soc IS NOT NULL AND u.fk_soc>0 AND EXISTS (SELECT 1 FROM ".MAIN_DB_PREFIX."user_rights ur WHERE ur.fk_user=u.rowid AND ur.fk_id=500411)";
$res = $db->query("SELECT u.login,u.fk_soc FROM ".MAIN_DB_PREFIX."user u WHERE u.entity=".(int)$conf->entity." AND u.statut=1 AND ".$where." ORDER BY u.rowid LIMIT 1");
$row = $res ? $db->fetch_object($res) : null;
if (!$row || $row->login === '') { fwrite(STDERR, "M037_AUTH_HTTP=FAIL REASON=ROLE_USER_ABSENT\n"); exit(1); }

$_SESSION['dol_login'] = $row->login;
$_SESSION['dol_logindate'] = time();
$_SESSION['dol_authmode'] = 'm037-synthetic-session';
$_SESSION['dol_entity'] = (int)$conf->entity;
$sessionName = session_name();
$sessionId = session_id();
$cookie = $sessionName.'='.$sessionId;
if (!session_write_close()) { fwrite(STDERR, "M037_AUTH_HTTP=FAIL REASON=SESSION_WRITE_FAILED\n"); exit(1); }

$baseUrl = getenv('M037_BASE_URL');
if ($baseUrl === false || $baseUrl === '') $baseUrl = 'http://127.0.0.1:18088';
$baseUrl = rtrim($baseUrl, '/');
$request = function ($route) use ($cookie, $baseUrl) {
    $handle = curl_init($baseUrl.$route);
    curl_setopt_array($handle, array(
        CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_FOLLOWLOCATION=>false,
        CURLOPT_TIMEOUT=>15,
        CURLOPT_HTTPHEADER=>array('Cookie: '.$cookie),
    ));
    $body = curl_exec($handle);
    $status = (int)curl_getinfo($handle, CURLINFO_HTTP_CODE);
    $error = curl_error($handle);
    curl_close($handle);
    if ($body === false || $error !== '') throw new RuntimeException('HTTP_TRANSPORT_FAILED');
    return array($status, $body);
};

$adminRoutes = array(
    '/custom/chinaplanetcustomercenter/admin_dashboard.php',
    '/custom/chinaplanetcustomercenter/admin/financial_portfolio.php',
    '/custom/chinaplanetcustomercenter/admin/operations.php',
    '/custom/chinaplanetlogistics/index.php',
    '/custom/chinaplanetoperations/operations/shipping_orders.php',
    '/custom/chinaplanetshippingprices/sop/index.php',
);
$customerRoutes = array(
    '/custom/chinaplanetcustomercenter/board.php',
    '/custom/chinaplanetcustomercenter/documents.php',
    '/custom/chinaplanetcustomercenter/operation_reports.php',
    '/custom/chinaplanetcustomercenter/projects.php',
    '/custom/chinaplanetcustomercenter/orders.php',
    '/custom/chinaplanetcustomercenter/balance.php',
    '/custom/chinaplanetlogistics/list.php',
    '/custom/chinaplanetshippingprices/index.php',
);

$failure = null;
try {
    foreach ($role === 'admin' ? $adminRoutes : $customerRoutes as $route) {
        list($status, $body) = $request($route);
        if ($status !== 200 || stripos($body, 'Not Found') !== false || stripos($body, 'Fatal error') !== false) throw new RuntimeException('ROUTE_FAILED');
    }
    if ($role === 'external') {
        list($status, $body) = $request('/custom/chinaplanetcustomercenter/admin_dashboard.php');
        $deniedBody = stripos($body, 'VISION TRADE OPERATIONS') === false && stripos($body, 'Shipping Orders / طلبات الشحن') === false;
        if (!in_array($status, array(302,401,403), true) && !($status === 200 && $deniedBody)) throw new RuntimeException('EXTERNAL_ADMIN_DENIAL_FAILED');
    }
} catch (Throwable $exception) {
    $failure = $exception->getMessage();
} finally {
    session_name($sessionName);
    session_id($sessionId);
    if (session_start()) {
        $_SESSION = array();
        if (!session_destroy() && $failure === null) $failure = 'SESSION_DESTROY_FAILED';
    } elseif ($failure === null) {
        $failure = 'SESSION_REOPEN_FAILED';
    }
}

if ($failure !== null) { fwrite(STDERR, "M037_AUTH_HTTP=FAIL REASON=".$failure."\n"); exit(1); }
echo "M037_AUTH_HTTP_".strtoupper($role)."=PASS\n";
