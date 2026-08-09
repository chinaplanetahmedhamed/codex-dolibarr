<?php
/**
 * Real Dolibarr + disposable MariaDB acceptance for M046-C01.
 *
 * This LOCAL-only test creates a schema-only database, synthetic users/rates,
 * starts a loopback PHP server, exercises the real Dolibarr bootstrap/routes,
 * restores the exact setup-row prestate, drops the database, and removes all
 * fixture files. It never copies business/customer rows from the source DB.
 */

if (getenv('M046_C01_REAL_LOCAL') !== '1') {
    fwrite(STDERR, "M046_C01_REAL_LOCAL=FAIL REASON=EXPLICIT_LOCAL_GATE_REQUIRED\n");
    exit(1);
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$repoRoot = dirname(__DIR__, 2);
$dolRoot = rtrim((string) (getenv('M046_C01_CORE_ROOT') ?: '/Users/mac/Documents/dev/dolibarr/dolibarr-Dev/dolibarr-23.0.3/htdocs'), '/');
$sourceConf = (string) (getenv('M046_C01_SOURCE_CONF') ?: $dolRoot.'/conf/conf.php');
if (!is_file($dolRoot.'/main.inc.php') || !is_file($sourceConf)) throw new RuntimeException('Local Dolibarr core/config unavailable');

function m046RealAssert($condition, $message) { if (!$condition) throw new RuntimeException($message); }
function m046RealLocalDbHost($host) {
    $normalized = strtolower(trim((string) $host, " \t\n\r\0\x0B[]"));
    return in_array($normalized, array('localhost', '127.0.0.1', '::1'), true);
}
function m046RealConfig($path) {
    return (static function ($path) {
        include $path;
        return array(
            'host'=>$dolibarr_main_db_host, 'port'=>(int)$dolibarr_main_db_port,
            'name'=>$dolibarr_main_db_name, 'user'=>$dolibarr_main_db_user,
            'pass'=>$dolibarr_main_db_pass, 'type'=>$dolibarr_main_db_type,
        );
    })($path);
}
function m046RealRows(mysqli $db, array $names) {
    $quoted = array_map(function ($name) use ($db) { return "'".$db->real_escape_string($name)."'"; }, $names);
    $res = $db->query("SELECT rowid,name,entity,value,type,visible,note,tms FROM llx_const WHERE entity=1 AND name IN (".implode(',', $quoted).") ORDER BY name");
    $rows = array();
    while ($row = $res->fetch_assoc()) $rows[] = $row;
    return $rows;
}
function m046RealHash(array $rows) { return hash('sha256', json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); }
function m046RealWrite($path, $content, $mode = 0600) {
    if (file_put_contents($path, $content) === false) throw new RuntimeException('Fixture write failed');
    chmod($path, $mode);
}
function m046RealRemoveTree($root) {
    if (!is_dir($root) || is_link($root)) return;
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $item) {
        if ($item->isLink() || $item->isFile()) { if (!unlink($item->getPathname())) throw new RuntimeException('Fixture file cleanup failed'); }
        elseif (!rmdir($item->getPathname())) throw new RuntimeException('Fixture directory cleanup failed');
    }
    if (!rmdir($root)) throw new RuntimeException('Fixture root cleanup failed');
}
function m046RealPort() {
    $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
    if (!$socket) throw new RuntimeException('Loopback port allocation failed');
    $name = stream_socket_get_name($socket, false);
    fclose($socket);
    return (int) substr(strrchr($name, ':'), 1);
}
function m046RealRequest($url, $cookie, $method = 'GET', array $data = array()) {
    $handle = curl_init($url);
    curl_setopt_array($handle, array(
        CURLOPT_RETURNTRANSFER=>true, CURLOPT_HEADER=>true, CURLOPT_FOLLOWLOCATION=>false,
        CURLOPT_CONNECTTIMEOUT=>3, CURLOPT_TIMEOUT=>15, CURLOPT_COOKIEJAR=>$cookie, CURLOPT_COOKIEFILE=>$cookie,
    ));
    if ($method === 'POST') {
        curl_setopt($handle, CURLOPT_POST, true);
        curl_setopt($handle, CURLOPT_POSTFIELDS, http_build_query($data, '', '&', PHP_QUERY_RFC3986));
        curl_setopt($handle, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));
    }
    $raw = curl_exec($handle);
    $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
    $headerSize = (int) curl_getinfo($handle, CURLINFO_HEADER_SIZE);
    $error = curl_error($handle);
    curl_close($handle);
    if ($raw === false) throw new RuntimeException('HTTP request failed: '.$error);
    return array('status'=>$status, 'body'=>substr($raw, $headerSize));
}
function m046RealToken($base, $cookie) {
    $response = m046RealRequest($base.'/m046-token.php', $cookie);
    $valid = $response['status'] === 200 && preg_match('/^[a-f0-9]{32,128}$/', trim($response['body'])) === 1;
    if (!$valid) {
        $summary = preg_replace('/\s+/', ' ', strip_tags(substr($response['body'], 0, 500)));
        throw new RuntimeException('Real Dolibarr token route failed status='.$response['status'].' body='.$summary);
    }
    return trim($response['body']);
}
function m046RealStartServer($fixtureRoot, $dolRoot, $router, $forceUser) {
    $port = m046RealPort();
    $log = $fixtureRoot.'/server-'.$forceUser.'.log';
    $environment = array_merge($_ENV, array('M046_FORCE_USER'=>$forceUser, 'M046_REPO_ROOT'=>(string) getenv('M046_REPO_ROOT')));
    $pipes = array();
    $process = proc_open(array(PHP_BINARY, '-d', 'display_errors=0', '-S', '127.0.0.1:'.$port, '-t', $dolRoot, $router), array(0=>array('pipe','r'), 1=>array('file',$log,'a'), 2=>array('file',$log,'a')), $pipes, $fixtureRoot, $environment);
    if (!is_resource($process)) throw new RuntimeException('Local PHP server failed to start');
    fclose($pipes[0]);
    $ready = false;
    for ($i=0; $i<60; $i++) {
        $socket = @stream_socket_client('tcp://127.0.0.1:'.$port, $errno, $error, 0.1);
        if ($socket) { fclose($socket); $ready = true; break; }
        usleep(50000);
    }
    if (!$ready) { proc_terminate($process); proc_close($process); throw new RuntimeException('Local PHP server did not become ready'); }
    return array('process'=>$process, 'base'=>'http://127.0.0.1:'.$port, 'log'=>$log);
}
function m046RealStopServer(array $server) {
    proc_terminate($server['process']);
    for ($i=0; $i<30; $i++) { $status=proc_get_status($server['process']); if (!$status['running']) break; usleep(50000); }
    proc_close($server['process']);
}

$source = m046RealConfig($sourceConf);
m046RealAssert(m046RealLocalDbHost($source['host']), 'Remote database host forbidden for disposable LOCAL-only test');
$sourceDb = new mysqli($source['host'], $source['user'], $source['pass'], $source['name'], $source['port']);
$adminCandidates = array(
    array((string) (getenv('M046_C01_DB_ADMIN_USER') ?: 'root'), (string) (getenv('M046_C01_DB_ADMIN_PASS') ?: 'root')),
    array('root', ''),
);
$serverDb = null; $fixtureDbUser = ''; $fixtureDbPass = '';
foreach ($adminCandidates as $candidate) {
    try {
        $serverDb = new mysqli($source['host'], $candidate[0], $candidate[1], '', $source['port']);
        $fixtureDbUser = $candidate[0]; $fixtureDbPass = $candidate[1];
        break;
    } catch (mysqli_sql_exception $e) {
        $serverDb = null;
    }
}
if (!$serverDb instanceof mysqli) throw new RuntimeException('Disposable database admin connection unavailable');
$database = 'm046_c01_'.bin2hex(random_bytes(6));
$fixtureRoot = sys_get_temp_dir().'/m046-c01-real-'.bin2hex(random_bytes(6));
$targetDb = null;
$servers = array();
$databaseCreated = false;
$databaseAbsenceVerified = false;

try {
    m046RealAssert(preg_match('/^m046_c01_[a-f0-9]{12}$/', $database) === 1, 'Disposable database name invalid');
    m046RealAssert(mkdir($fixtureRoot, 0700, true), 'Fixture root creation failed');
    m046RealAssert(mkdir($fixtureRoot.'/conf', 0700, true), 'Fixture conf creation failed');
    m046RealAssert(mkdir($fixtureRoot.'/data', 0700, true), 'Fixture data creation failed');
    $serverDb->query("CREATE DATABASE `".$database."` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $databaseCreated = true;
    $targetDb = new mysqli($source['host'], $fixtureDbUser, $fixtureDbPass, $database, $source['port']);
    $targetDb->query('SET FOREIGN_KEY_CHECKS=0');
    $tables = $sourceDb->query("SHOW FULL TABLES WHERE Table_type='BASE TABLE'");
    while ($table = $tables->fetch_row()) {
        $name = $table[0];
        $createResult = $sourceDb->query("SHOW CREATE TABLE `".$sourceDb->real_escape_string($name)."`");
        $create = $createResult->fetch_row();
        $targetDb->query($create[1]);
    }
    $targetDb->query('SET FOREIGN_KEY_CHECKS=1');

    foreach (array(
        array(1,'m046admin',1,null), array(2,'m046review',0,null),
        array(3,'m046import',0,null), array(4,'m046external',0,99),
    ) as $user) {
        $soc = $user[3] === null ? 'NULL' : (string)(int)$user[3];
        $targetDb->query("INSERT INTO llx_user(rowid,entity,admin,datec,login,lastname,firstname,fk_soc,statut) VALUES(".(int)$user[0].",1,".(int)$user[2].",NOW(),'".$targetDb->real_escape_string($user[1])."','Fixture','M046',".$soc.",1)");
    }
    foreach (array(array(500402,'viewcost'),array(500403,'import')) as $right) {
        $targetDb->query("INSERT INTO llx_rights_def(id,entity,libelle,module,module_origin,perms,bydefault) VALUES(".(int)$right[0].",1,'M046 fixture','chinaplanetshippingprices','chinaplanetshippingprices','".$right[1]."',0)");
    }
    foreach (array(array(2,500402),array(2,500403),array(3,500403),array(4,500402),array(4,500403)) as $right) {
        $targetDb->query("INSERT INTO llx_user_rights(entity,fk_user,fk_id) VALUES(1,".(int)$right[0].",".(int)$right[1].")");
    }

    $setupNames = array(
        'CHINAPLANETSP_IGNORE_BELOW','CHINAPLANETSP_DEFAULT_POL','CHINAPLANETSP_MARGIN_20GP','CHINAPLANETSP_MARGIN_40GP',
        'CHINAPLANETSP_MARGIN_40HQ','CHINAPLANETSP_MARGIN_45HQ','CHINAPLANETSP_CURRENCY','CHINAPLANETSP_CUSTOMER_HISTORY',
        'CHINAPLANETSP_HISTORY_MONTHS','CHINAPLANETSP_AUTO_TRANSLATION_ENABLED',
    );
    $seedConstants = array(
        'MAIN_VERSION_LAST_INSTALL'=>'23.0.3','MAIN_LANG_DEFAULT'=>'en_US','MAIN_THEME'=>'eldy','MAIN_MENU_STANDARD'=>'eldy_menu.php',
        'MAIN_MODULE_CHINAPLANETSHIPPINGPRICES'=>'1','MAIN_MODULE_CHINAPLANETCUSTOMERCENTER'=>'1',
        'CHINAPLANETSP_IGNORE_BELOW'=>'1000','CHINAPLANETSP_DEFAULT_POL'=>'Guangzhou','CHINAPLANETSP_MARGIN_20GP'=>'200',
        'CHINAPLANETSP_MARGIN_40GP'=>'400','CHINAPLANETSP_MARGIN_40HQ'=>'400','CHINAPLANETSP_MARGIN_45HQ'=>'500',
        'CHINAPLANETSP_CURRENCY'=>'USD','CHINAPLANETSP_CUSTOMER_HISTORY'=>'0','CHINAPLANETSP_HISTORY_MONTHS'=>'12',
        'CHINAPLANETSP_AUTO_TRANSLATION_ENABLED'=>'1',
    );
    foreach ($seedConstants as $name=>$value) $targetDb->query("INSERT INTO llx_const(name,entity,value,type,visible,note) VALUES('".$targetDb->real_escape_string($name)."',1,'".$targetDb->real_escape_string($value)."','chaine',0,'M046 synthetic fixture')");

    $config = "<?php\n";
    $config .= '$dolibarr_main_url_root='.var_export('http://127.0.0.1', true).";\n";
    $config .= '$dolibarr_main_document_root='.var_export($dolRoot, true).";\n";
    $config .= '$dolibarr_main_url_root_alt='.var_export('/custom', true).";\n";
    $config .= '$dolibarr_main_document_root_alt='.var_export($repoRoot.'/custom', true).";\n";
    $config .= '$dolibarr_main_data_root='.var_export($fixtureRoot.'/data', true).";\n";
    foreach (array('host','port','type') as $key) $config .= '$dolibarr_main_db_'.$key.'='.var_export($source[$key], true).";\n";
    $config .= '$dolibarr_main_db_user='.var_export($fixtureDbUser, true).";\n";
    $config .= '$dolibarr_main_db_pass='.var_export($fixtureDbPass, true).";\n";
    $config .= '$dolibarr_main_db_name='.var_export($database, true).";\n";
    $config .= '$dolibarr_main_db_prefix='.var_export('llx_', true).";\n";
    $config .= '$dolibarr_main_db_character_set='.var_export('utf8mb4', true).";\n";
    $config .= '$dolibarr_main_db_collation='.var_export('utf8mb4_unicode_ci', true).";\n";
    $config .= '$dolibarr_main_authentication='.var_export('forceuser', true).";\n";
    $config .= '$dolibarr_auto_user=(string) getenv('.var_export('M046_FORCE_USER', true).');' ."\n";
    $config .= '$dolibarr_main_prod='.var_export('0', true).";\n";
    $config .= '$dolibarr_main_force_https='.var_export('0', true).";\n";
    $config .= '$dolibarr_nocsrfcheck='.var_export('0', true).";\n";
    $config .= '$dolibarr_main_instance_unique_id='.var_export('m046c01fixture'.bin2hex(random_bytes(8)), true).";\n";
    m046RealWrite($fixtureRoot.'/conf/conf.php', $config);

    $router = <<<'PHP'
<?php
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path === '/custom/chinaplanetshippingprices/admin/setup.php') { require getenv('M046_REPO_ROOT').'/custom/chinaplanetshippingprices/admin/setup.php'; return true; }
if ($path === '/custom/chinaplanetshippingprices/admin/batch.php') { require getenv('M046_REPO_ROOT').'/custom/chinaplanetshippingprices/admin/batch.php'; return true; }
if ($path === '/m046-token.php') { require $_SERVER['DOCUMENT_ROOT'].'/main.inc.php'; header('Content-Type: text/plain'); echo newToken(); return true; }
if ($path === '/m046-publication.php') {
    try {
    require $_SERVER['DOCUMENT_ROOT'].'/main.inc.php';
    require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
    require_once getenv('M046_REPO_ROOT').'/custom/chinaplanetshippingprices/class/ShippingPriceImporter.class.php';
    $stored = (int) ($_GET['stored'] ?? -1);
    if (!in_array($stored, array(0,1), true)) { http_response_code(400); return true; }
    dolibarr_set_const($db, 'CHINAPLANETSP_AUTO_TRANSLATION_ENABLED', (string)$stored, 'chaine', 0, 'M046 synthetic fixture', 1);
    $conf->global->CHINAPLANETSP_AUTO_TRANSLATION_ENABLED = $stored;
    $importer = new ShippingPriceImporter($db, $user, 1);
    $reflection = new ReflectionClass($importer);
    $store = $reflection->getMethod('storeBatch'); $store->setAccessible(true);
    $source = 'M046 SOURCE '.$stored.' AB12CD34';
    $parsed = array('sheet'=>'M046-'.$stored,'effective_date'=>'2026-08-10','price_group'=>'tariff','ignored'=>array(),'warnings'=>array(),'total_rows'=>1,'rows'=>array(array(
        'source_row'=>3,'source_cell'=>'D3','region'=>'Red Sea','pol'=>'NANSHA','country'=>'EG','pod'=>'SOKHNA','line'=>'TESTLINE','service'=>'direct','route'=>'DIRECT','container'=>'40HQ',
        'cost'=>2000,'sale'=>2400,'currency'=>'USD','public_visible'=>1,'source_notes_raw'=>$source,'source_notes'=>$source,'notes_en'=>'','translation_status'=>'not_needed',
        'translation_source_hash'=>hash('sha256',$source),'translation_provider'=>'','translation_model'=>'','translation_error'=>'translation_disabled','public_notes'=>'','internal_notes'=>$source,
    )));
    $context = array('file_hash'=>hash('sha256','m046-'.$stored),'source_name'=>'M046-Agent-'.$stored,'original_name'=>'m046-'.$stored.'.csv','stored_file'=>'','ignore_below'=>1000,'currency'=>'USD','mode'=>'current','replacement_scope'=>'agent');
    $batchId = $store->invoke($importer, $parsed, $context);
    $draft = $importer->getBatchPublicationState($batchId);
    $published = $importer->publishBatch($batchId, false);
    $before = $db->query("SELECT sale_price,public_visible,is_current,status,source_notes,notes_en,translation_provider,translation_error FROM ".MAIN_DB_PREFIX."chinaplanetsp_rate WHERE entity=1 AND fk_batch=".$batchId);
    $before = $db->fetch_object($before);
    $db->query("UPDATE ".MAIN_DB_PREFIX."chinaplanetsp_rate SET is_current=0 WHERE entity=1 AND fk_batch=".$batchId);
    $reactivated = $importer->publishBatch($batchId, true);
    $after = $db->query("SELECT sale_price,public_visible,is_current,status,source_notes,notes_en,translation_provider,translation_error FROM ".MAIN_DB_PREFIX."chinaplanetsp_rate WHERE entity=1 AND fk_batch=".$batchId);
    $after = $db->fetch_object($after);
    header('Content-Type: application/json');
    echo json_encode(array('batch'=>$batchId,'stored'=>$stored,'effective'=>ShippingPriceImporter::automaticTranslationEnabled()?1:0,'draft'=>$draft,'published'=>$published,'reactivated'=>$reactivated,'before'=>$before,'after'=>$after), JSON_UNESCAPED_SLASHES);
    return true;
    } catch (Throwable $e) {
        http_response_code(500); header('Content-Type: text/plain');
        echo get_class($e).':'.$e->getMessage().' at '.basename($e->getFile()).':'.$e->getLine();
        return true;
    }
}
return false;
PHP;
    m046RealWrite($fixtureRoot.'/router.php', $router);
    putenv('M046_REPO_ROOT='.$repoRoot);

    $prestate = m046RealRows($targetDb, $setupNames);
    $prestateHash = m046RealHash($prestate);
    $admin = m046RealStartServer($fixtureRoot, $dolRoot, $fixtureRoot.'/router.php', 'm046admin');
    $servers[] = $admin;
    $cookie = $fixtureRoot.'/admin.cookie';
    $token = m046RealToken($admin['base'], $cookie);
    $baseline = m046RealRequest($admin['base'].'/custom/chinaplanetshippingprices/admin/setup.php', $cookie);
    m046RealAssert($baseline['status'] === 200 && strpos($baseline['body'], 'Disabled by release policy') !== false, 'Real setup GET failed');
    m046RealAssert(m046RealHash(m046RealRows($targetDb, $setupNames)) === $prestateHash, 'Setup GET changed configuration');
    $querySave = m046RealRequest($admin['base'].'/custom/chinaplanetshippingprices/admin/setup.php?action=save&ignore_below=9999', $cookie);
    $queryRows = m046RealRows($targetDb, $setupNames);
    if (m046RealHash($queryRows) !== $prestateHash) throw new RuntimeException('Query save changed configuration status='.$querySave['status'].' rows='.json_encode($queryRows, JSON_UNESCAPED_SLASHES));

    $validPost = array('action'=>'save','token'=>$token,'ignore_below'=>'1100','default_pol'=>'Nansha','margin_20gp'=>'210','margin_40gp'=>'410','margin_40hq'=>'420','margin_45hq'=>'510','currency'=>'usd','customer_history'=>'1','history_months'=>'18');
    foreach (array(
        array_merge($validPost,array('token'=>'')), array_merge($validPost,array('token'=>'invalid')),
        array('action'=>'delete','token'=>$token), array('action'=>array('save'),'token'=>$token), array('action'=>'save','token'=>$token),
    ) as $negative) {
        m046RealRequest($admin['base'].'/custom/chinaplanetshippingprices/admin/setup.php?ignore_below=9999&currency=eur', $cookie, 'POST', $negative);
        m046RealAssert(m046RealHash(m046RealRows($targetDb, $setupNames)) === $prestateHash, 'Negative real setup case changed configuration');
    }
    $token = m046RealToken($admin['base'], $cookie);
    $validPost['token'] = $token;
    $saved = m046RealRequest($admin['base'].'/custom/chinaplanetshippingprices/admin/setup.php?ignore_below=9999&currency=eur&auto_translation_enabled=1', $cookie, 'POST', $validPost);
    m046RealAssert($saved['status'] === 200, 'Valid real setup POST failed');
    $savedRows = m046RealRows($targetDb, $setupNames);
    $savedMap = array(); foreach ($savedRows as $row) $savedMap[$row['name']]=$row['value'];
    m046RealAssert(($savedMap['CHINAPLANETSP_IGNORE_BELOW'] ?? '') === '1100' && ($savedMap['CHINAPLANETSP_CURRENCY'] ?? '') === 'USD', 'Real setup did not use POST values');
    m046RealAssert(($savedMap['CHINAPLANETSP_AUTO_TRANSLATION_ENABLED'] ?? '') === '1', 'Real setup changed stored translation constant');
    $targetDb->query('SET FOREIGN_KEY_CHECKS=0');
    $targetDb->query("DELETE FROM llx_const WHERE entity=1 AND name IN ('".implode("','", array_map(array($targetDb,'real_escape_string'), $setupNames))."')");
    foreach ($prestate as $row) {
        $note = $row['note'] === null ? 'NULL' : "'".$targetDb->real_escape_string($row['note'])."'";
        $targetDb->query("INSERT INTO llx_const(rowid,name,entity,value,type,visible,note,tms) VALUES(".(int)$row['rowid'].",'".$targetDb->real_escape_string($row['name'])."',".(int)$row['entity'].",'".$targetDb->real_escape_string($row['value'])."','".$targetDb->real_escape_string((string)$row['type'])."',".(int)$row['visible'].",".$note.",'".$targetDb->real_escape_string($row['tms'])."')");
    }
    $targetDb->query('SET FOREIGN_KEY_CHECKS=1');
    m046RealAssert(m046RealHash(m046RealRows($targetDb, $setupNames)) === $prestateHash, 'Real setup prestate restoration failed');

    foreach (array('m046review','m046import','m046external') as $login) {
        $server = m046RealStartServer($fixtureRoot, $dolRoot, $fixtureRoot.'/router.php', $login);
        $servers[] = $server;
        $roleCookie = $fixtureRoot.'/'.$login.'.cookie';
        $roleToken = m046RealToken($server['base'], $roleCookie);
        $rolePost = $validPost; $rolePost['token']=$roleToken;
        m046RealRequest($server['base'].'/custom/chinaplanetshippingprices/admin/setup.php', $roleCookie, 'POST', $rolePost);
        m046RealAssert(m046RealHash(m046RealRows($targetDb, $setupNames)) === $prestateHash, 'Unauthorized real setup role changed configuration: '.$login);
    }

    // Synthetic draft for real batch rendering; no customer/business record exists.
    $targetDb->query("INSERT INTO llx_chinaplanetsp_batch(rowid,entity,ref,source_name,original_name,file_hash,source_sheet,effective_date,import_mode,replacement_scope,ignore_below,currency_code,total_rows,accepted_rows,ignored_rows,warning_count,status,report_json,fk_user_creat,date_creation) VALUES(91,1,'M046-RENDER','M046 Synthetic','m046.csv','".hash('sha256','m046-render')."','M046','2026-08-10','current','agent',1000,'USD',1,1,0,0,0,'{}',1,NOW())");
    $sourceNote = 'M046_SOURCE_SECRET AB12CD34';
    $targetDb->query("INSERT INTO llx_chinaplanetsp_rate(entity,fk_batch,source_sheet,source_row,source_cell,region_label,pol,destination_country,pod,shipping_line,service_type,route_detail,container_type,cost_price,sale_price,currency_code,valid_from,source_notes_raw,source_notes,notes_en,translation_status,translation_source_hash,translation_provider,translation_model,translation_error,public_notes,internal_notes,public_visible,is_current,status,fk_user_creat,date_creation) VALUES(1,91,'M046',3,'D3','Red Sea','NANSHA','EG','SOKHNA','TESTLINE','direct','DIRECT','40HQ',2000,2400,'USD','2026-08-10','".$sourceNote."','".$sourceNote."','Stored English AB12CD34','approved','".hash('sha256',$sourceNote)."','legacy-provider','legacy-model','legacy-error','SHOULD_NOT_RENDER_PUBLIC','".$sourceNote."',1,0,0,1,NOW())");
    foreach ($servers as $server) m046RealStopServer($server);
    $servers = array();
    foreach (array('m046admin'=>true,'m046review'=>true,'m046import'=>false,'m046external'=>false) as $login=>$canSee) {
        $server = m046RealStartServer($fixtureRoot, $dolRoot, $fixtureRoot.'/router.php', $login);
        $servers[] = $server;
        $response = m046RealRequest($server['base'].'/custom/chinaplanetshippingprices/admin/batch.php?id=91', $fixtureRoot.'/'.$login.'-batch.cookie');
        $visible = strpos($response['body'], $sourceNote) !== false && strpos($response['body'], 'legacy-provider') !== false;
        m046RealAssert($visible === $canSee, 'Real batch restricted-note visibility failed for '.$login);
        if ($canSee) {
            foreach (array('name="notes_en"','value="save_translation"','chinaplanetsp-auto-translate','action="auto_translate"','<textarea') as $forbidden) m046RealAssert(strpos($response['body'], $forbidden) === false, 'Disabled real batch rendered mutation control '.$forbidden);
        } elseif ($login === 'm046import') {
            m046RealAssert(strpos($response['body'], 'Restricted') !== false && strpos($response['body'], 'Translation changes are disabled for every user') !== false, 'Import-only real batch warning is inconsistent');
        }
        m046RealStopServer(array_pop($servers));
    }

    $admin = m046RealStartServer($fixtureRoot, $dolRoot, $fixtureRoot.'/router.php', 'm046admin');
    $servers[] = $admin;
    foreach (array(0,1) as $stored) {
        $result = m046RealRequest($admin['base'].'/m046-publication.php?stored='.$stored, $fixtureRoot.'/publication.cookie');
        if ($result['status'] !== 200) {
            $logTail = is_file($admin['log']) ? substr((string) file_get_contents($admin['log']), -3000) : '';
            throw new RuntimeException('Real publication fixture failed status='.$result['status'].' body='.preg_replace('/\s+/', ' ', strip_tags(substr($result['body'], 0, 1000))).' log='.preg_replace('/\s+/', ' ', $logTail));
        }
        $cycle = json_decode($result['body'], true);
        m046RealAssert(is_array($cycle) && $cycle['effective'] === 0 && empty($cycle['draft']['is_published']), 'Draft/effective state failed for stored='.$stored);
        m046RealAssert(!empty($cycle['published']['is_published']) && !empty($cycle['published']['is_current']) && (int)$cycle['published']['searchable_rates'] === 1, 'Publish/searchability failed for stored='.$stored);
        m046RealAssert(!empty($cycle['reactivated']['is_current']) && (int)$cycle['after']['is_current'] === 1 && (int)$cycle['after']['status'] === 1, 'Reactivate/is_current failed for stored='.$stored);
        m046RealAssert((float)$cycle['before']['sale_price'] === 2400.0 && (float)$cycle['after']['sale_price'] === 2400.0 && (int)$cycle['after']['public_visible'] === 1, 'Sale price/eligibility changed for stored='.$stored);
        foreach (array('source_notes','notes_en','translation_provider','translation_error') as $field) m046RealAssert($cycle['before'][$field] === $cycle['after'][$field], 'Private translation field changed: '.$field.' stored='.$stored);
    }

    echo "M046_C01_REAL_LOCAL=PASS dolibarr_bootstrap=REAL db=DISPOSABLE setup_route=REAL csrf=PASS permissions=PASS prestate_restored=YES render_matrix=PASS publication_0_1=PASS cleanup=PENDING\n";
} finally {
    foreach ($servers as $server) m046RealStopServer($server);
    if ($targetDb instanceof mysqli) $targetDb->close();
    if ($databaseCreated && preg_match('/^m046_c01_[a-f0-9]{12}$/', $database)) {
        $serverDb->query("DROP DATABASE `".$database."`");
        $absence = $serverDb->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME='".$serverDb->real_escape_string($database)."'");
        m046RealAssert($absence->num_rows === 0, 'Disposable database still exists after DROP DATABASE');
        $databaseAbsenceVerified = true;
    }
    $sourceDb->close();
    $serverDb->close();
    if (is_dir($fixtureRoot)) m046RealRemoveTree($fixtureRoot);
}

m046RealAssert($databaseAbsenceVerified, 'Disposable database absence was not verified');
echo "M046_C01_REAL_LOCAL_CLEANUP=PASS database=ABSENT_VERIFIED fixture=REMOVED business_rows_copied=0 synthetic_fixture=YES\n";
