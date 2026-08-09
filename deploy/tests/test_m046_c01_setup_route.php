<?php
/** Static route-boundary proof for setup; positive save is exercised only in an isolated local route fixture. */

if (($argv[1] ?? '') === 'worker') {
    $case = json_decode((string) getenv('M046_ROUTE_CASE'), true);
    if (!is_array($case)) throw new RuntimeException('worker case invalid');
    $_GET = $case['get'] ?? array();
    $_POST = $case['post'] ?? array();
    $_REQUEST = array_merge($_GET, $_POST);
    $_SERVER['REQUEST_METHOD'] = (string) ($case['method'] ?? 'GET');
    $_SERVER['DOCUMENT_ROOT'] = (string) getenv('M046_ROUTE_DOCUMENT_ROOT');
    $status = 'rendered';
    try {
        require (string) getenv('M046_ROUTE_SETUP_PATH');
    } catch (Throwable $e) {
        $status = 'denied:'.$e->getMessage();
    }
    echo "\nM046_ROUTE_RESULT=".json_encode(array('status'=>$status, 'writes'=>$GLOBALS['m046_route_writes'] ?? array()), JSON_UNESCAPED_SLASHES)."\n";
    exit(0);
}

$root = dirname(__DIR__, 2);
$source = file_get_contents($root.'/custom/chinaplanetshippingprices/admin/setup.php');
if ($source === false) throw new RuntimeException('setup source unreadable');
function m046SetupAssert($condition, $message) { if (!$condition) throw new RuntimeException($message); }

$actionOffset = strpos($source, "\$m046SetupActionPresent = array_key_exists('action', \$_POST)");
$bootstrapOffset = strpos($source, "require \$_SERVER['DOCUMENT_ROOT'].'/main.inc.php';");
m046SetupAssert($actionOffset !== false && $bootstrapOffset !== false && $actionOffset < $bootstrapOffset, 'POST action is not captured before bootstrap');
m046SetupAssert(strpos($source, "is_string(\$_POST['action'])") !== false && strpos($source, "if (\$m046SetupActionPresent) define('CSRFCHECK_WITH_TOKEN', 1);") !== false, 'typed action or explicit token enforcement absent');
m046SetupAssert(strpos($source, "REQUEST_METHOD") !== false && strpos($source, "\$m046SetupAction !== 'save'") !== false, 'POST and action allowlist absent');
m046SetupAssert(strpos($source, "array_key_exists(\$requiredPostField, \$_POST)") !== false, 'complete required payload gate absent');
m046SetupAssert(strpos($source, "GETPOST('action'") === false, 'query action can still select setup mutation');
m046SetupAssert(strpos($source, "CHINAPLANETSP_AUTO_TRANSLATION_ENABLED") === false && strpos($source, 'auto_translation_enabled') === false, 'translation constant remains writable or rendered');
m046SetupAssert(strpos($source, "chinaplanetsp_require_internal(\$user, 'admin')") !== false, 'internal admin permission absent');

$postOnlyFields = array('ignore_below','default_pol','margin_20gp','margin_40gp','margin_40hq','margin_45hq','currency');
foreach ($postOnlyFields as $field) m046SetupAssert(strpos($source, "GETPOST('".$field."',") !== false && preg_match("/GETPOST\\('".preg_quote($field, '/')."'[^;\\n]*, 2\\)/", $source) === 1, 'field is not POST-only: '.$field);
foreach (array('customer_history','history_months') as $field) m046SetupAssert(strpos($source, "GETPOSTINT('".$field."', 2)") !== false, 'integer field is not POST-only: '.$field);

// Truth table for the frozen request-origin contract. Query data never supplies action or values.
$route = function ($method, array $query, array $post) {
    $action = isset($post['action']) ? (string) $post['action'] : '';
    if ($action === '') return 'render';
    if (strtoupper($method) !== 'POST' || $action !== 'save') return 'deny';
    foreach (array('ignore_below','default_pol','margin_20gp','margin_40gp','margin_40hq','margin_45hq','currency','history_months') as $field) if (!array_key_exists($field, $post)) return 'deny';
    return 'token_then_save_post_only';
};
m046SetupAssert($route('GET', array('action'=>'save'), array()) === 'render', 'GET action selected mutation');
m046SetupAssert($route('POST', array('ignore_below'=>'9999'), array('action'=>'save')) === 'deny', 'query-only settings accepted with POST action');
m046SetupAssert($route('POST', array(), array('action'=>'delete')) === 'deny', 'unsupported POST action accepted');
m046SetupAssert($route('POST', array('action'=>'save'), array()) === 'render', 'query action overrode empty POST');

$fixtureRoot = sys_get_temp_dir().'/m046-c01-setup-'.bin2hex(random_bytes(6));
$fixtureDirs = array(
    $fixtureRoot,
    $fixtureRoot.'/core/lib',
    $fixtureRoot.'/custom/chinaplanetcustomercenter/lib',
);
foreach ($fixtureDirs as $dir) if (!mkdir($dir, 0700, true) && !is_dir($dir)) throw new RuntimeException('fixture directory failed');
$bootstrap = <<<'PHP'
<?php
$GLOBALS['m046_route_writes'] = array();
if (defined('CSRFCHECK_WITH_TOKEN') && (string) ($_POST['token'] ?? '') !== 'valid-token') throw new RuntimeException('csrf_denied');
if (!defined('DOL_DOCUMENT_ROOT')) define('DOL_DOCUMENT_ROOT', $_SERVER['DOCUMENT_ROOT']);
class M046RouteUser {
    public $id; public $admin; public $socid;
    public function __construct($case) { $this->id=7; $this->admin=!empty($case['admin'])?1:0; $this->socid=!empty($case['external'])?99:0; }
    public function hasRight($module,$right) { return false; }
}
class M046RouteLangs { public function loadLangs($values) {} public function trans($key) { return $key; } }
class M046RouteDb { public function close() {} }
$case = json_decode((string) getenv('M046_ROUTE_CASE'), true);
$user = new M046RouteUser($case);
$langs = new M046RouteLangs();
$db = new M046RouteDb();
$conf = new stdClass(); $conf->entity=1; $conf->global=new stdClass();
function GETPOST($name,$filter='',$method=0) { if ($method===2) return $_POST[$name] ?? ''; return $_POST[$name] ?? ($_GET[$name] ?? ''); }
function GETPOSTINT($name,$method=0) { return (int) GETPOST($name,'int',$method); }
function dolibarr_set_const($db,$name,$value,$type,$visible,$note,$entity) { $GLOBALS['m046_route_writes'][$name]=(string)$value; return 1; }
function accessforbidden($message='') { throw new RuntimeException($message!==''?$message:'access_denied'); }
function setEventMessages($message,$unused,$type) {}
function cpcc_admin_shell_asset($path) { return $path; }
function cpcc_admin_shell_open($active,$title,$subtitle='',$icon='') {}
function cpcc_admin_shell_close() {}
function llxHeader() {}
function llxFooter() {}
function newToken() { return 'fixture-token'; }
function dol_escape_htmltag($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
PHP;
file_put_contents($fixtureRoot.'/main.inc.php', $bootstrap);
file_put_contents($fixtureRoot.'/core/lib/admin.lib.php', "<?php\n");
file_put_contents($fixtureRoot.'/custom/chinaplanetcustomercenter/lib/admin_shell.lib.php', "<?php\n");

$runCase = function (array $case) use ($fixtureRoot, $root) {
    $environment = array_merge($_ENV, array(
        'M046_ROUTE_CASE'=>json_encode($case, JSON_UNESCAPED_SLASHES),
        'M046_ROUTE_DOCUMENT_ROOT'=>$fixtureRoot,
        'M046_ROUTE_SETUP_PATH'=>$root.'/custom/chinaplanetshippingprices/admin/setup.php',
    ));
    $pipes = array();
    $process = proc_open(array(PHP_BINARY, __FILE__, 'worker'), array(1=>array('pipe','w'), 2=>array('pipe','w')), $pipes, null, $environment);
    if (!is_resource($process)) throw new RuntimeException('route worker failed to start');
    $stdout = stream_get_contents($pipes[1]); fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]); fclose($pipes[2]);
    $exit = proc_close($process);
    if ($exit !== 0) throw new RuntimeException('route worker failed: '.$stderr);
    if (!preg_match('/M046_ROUTE_RESULT=(\{[^\n]+\})/', $stdout, $match)) throw new RuntimeException('route worker result absent');
    $result = json_decode($match[1], true);
    if (!is_array($result)) throw new RuntimeException('route worker result invalid');
    return $result;
};

$validPost = array(
    'action'=>'save', 'token'=>'valid-token', 'ignore_below'=>'1100', 'default_pol'=>'Nansha',
    'margin_20gp'=>'210', 'margin_40gp'=>'410', 'margin_40hq'=>'420', 'margin_45hq'=>'510',
    'currency'=>'usd', 'customer_history'=>'1', 'history_months'=>'18',
);
$valid = $runCase(array('method'=>'POST','admin'=>true,'get'=>array('ignore_below'=>'9999','currency'=>'eur','auto_translation_enabled'=>'1'),'post'=>$validPost));
m046SetupAssert($valid['status'] === 'rendered', 'authorized valid-token POST save failed');
m046SetupAssert(($valid['writes']['CHINAPLANETSP_IGNORE_BELOW'] ?? '') === '1100' && ($valid['writes']['CHINAPLANETSP_CURRENCY'] ?? '') === 'USD', 'query values overrode POST payload');
m046SetupAssert(count($valid['writes']) === 9 && !isset($valid['writes']['CHINAPLANETSP_AUTO_TRANSLATION_ENABLED']), 'save wrote outside exact allowlist');

$negativeCases = array(
    array('method'=>'GET','admin'=>true,'get'=>array('action'=>'save','ignore_below'=>'9999'),'post'=>array()),
    array('method'=>'POST','admin'=>true,'get'=>array('ignore_below'=>'9999','default_pol'=>'QueryPort','margin_20gp'=>'1','margin_40gp'=>'1','margin_40hq'=>'1','margin_45hq'=>'1','currency'=>'eur','history_months'=>'9'),'post'=>array('action'=>'save','token'=>'valid-token')),
    array('method'=>'POST','admin'=>true,'get'=>array(),'post'=>array_merge($validPost,array('token'=>''))),
    array('method'=>'POST','admin'=>true,'get'=>array(),'post'=>array_merge($validPost,array('token'=>'invalid'))),
    array('method'=>'POST','admin'=>true,'get'=>array(),'post'=>array('action'=>'delete','token'=>'valid-token')),
    array('method'=>'POST','admin'=>true,'get'=>array(),'post'=>array('action'=>array('save'),'token'=>'valid-token')),
    array('method'=>'POST','admin'=>false,'get'=>array(),'post'=>$validPost),
    array('method'=>'POST','admin'=>true,'external'=>true,'get'=>array(),'post'=>$validPost),
);
foreach ($negativeCases as $index=>$case) {
    $result = $runCase($case);
    if ($index === 0) m046SetupAssert($result['status'] === 'rendered', 'query-only save did not remain read-only');
    else m046SetupAssert(strpos($result['status'], 'denied:') === 0, 'negative route case was not denied: '.$index);
    m046SetupAssert($result['writes'] === array(), 'negative route case wrote configuration: '.$index);
}

@unlink($fixtureRoot.'/custom/chinaplanetcustomercenter/lib/admin_shell.lib.php');
@unlink($fixtureRoot.'/core/lib/admin.lib.php');
@unlink($fixtureRoot.'/main.inc.php');
@rmdir($fixtureRoot.'/custom/chinaplanetcustomercenter/lib');
@rmdir($fixtureRoot.'/custom/chinaplanetcustomercenter');
@rmdir($fixtureRoot.'/custom');
@rmdir($fixtureRoot.'/core/lib');
@rmdir($fixtureRoot.'/core');
@rmdir($fixtureRoot);

echo "M046_C01_SETUP_ROUTE=PASS fixture=FAST_STUB action=POST_ONLY payload=POST_ONLY csrf=BEFORE_BOOTSTRAP tokens=VALID_AND_NEGATIVE admin=INTERNAL_ONLY toggle=ABSENT real_route=SEPARATE_TEST\n";
