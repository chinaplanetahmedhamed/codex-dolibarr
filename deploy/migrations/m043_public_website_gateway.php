<?php
/** Staging-only additive schema and least-privilege API gateway provisioning for M043. */

if (PHP_SAPI !== 'cli') { fwrite(STDERR, "M043_GATEWAY=FAIL REASON=CLI_ONLY\n"); exit(1); }
$commands = array('check', 'apply', 'disable');
if ($argc !== 2 || !in_array($argv[1], $commands, true)) { fwrite(STDERR, "M043_GATEWAY=FAIL REASON=COMMAND_NOT_ALLOWED\n"); exit(1); }

define('NOLOGIN', 1);
define('NOREQUIREMENU', 1);
define('NOREQUIREHTML', 1);
define('NOREQUIREAJAX', 1);
define('NOCSRFCHECK', 1);

$documentRoot = getenv('M043_DOL_DOCUMENT_ROOT');
if ($documentRoot === false || $documentRoot === '') $documentRoot = '/var/www/html';
$documentRoot = rtrim($documentRoot, '/');
if ($documentRoot === '' || !is_file($documentRoot.'/master.inc.php')) { fwrite(STDERR, "M043_GATEWAY=FAIL REASON=DOLIBARR_ROOT_INVALID\n"); exit(1); }
$_SERVER['DOCUMENT_ROOT'] = $documentRoot;
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['SERVER_PORT'] = '80';
require $documentRoot.'/master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/security2.lib.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
global $db, $conf;

$entity = (int) $conf->entity;
$table = MAIN_DB_PREFIX.'cpo_public_request';
$login = 'website_staging_gateway';
$marker = 'M043 staging-only website API gateway';
$rightIds = array(500412, 500511, 500512, 500513, 500514);
$apiRightIds = array(500412, 500511);

$fail = function ($reason) { fwrite(STDERR, 'M043_GATEWAY=FAIL REASON='.$reason."\n"); exit(1); };
$query = function ($sql, $reason) use ($db, $fail) {
    $result = $db->query($sql);
    if (!$result) $fail($reason);
    return $result;
};
$tableExists = function () use ($query, $db, $table) {
    $pattern = str_replace(array('\\', '_', '%'), array('\\\\', '\\_', '\\%'), $table);
    return $db->num_rows($query("SHOW TABLES LIKE '".$db->escape($pattern)."'", 'TABLE_CHECK_FAILED')) === 1;
};
$gatewayUser = function () use ($query, $db, $entity, $login) {
    $res = $query("SELECT rowid,entity,admin,statut,fk_soc,note_private,api_key FROM ".MAIN_DB_PREFIX."user WHERE entity=".$entity." AND login='".$db->escape($login)."'", 'USER_QUERY_FAILED');
    if ($db->num_rows($res) > 1) return false;
    return $db->fetch_object($res) ?: null;
};
$readSecret = function ($name) use ($fail) {
    $value = '';
    $secretDir = (string) getenv('M043_SECRET_DIR');
    if ($secretDir !== '') {
        if (!preg_match('#^/tmp/m043-secrets-[0-9]+$#D', $secretDir)) $fail('SECRET_DIR_INVALID');
        $files = array(
            'M043_DOLIBARR_API_KEY' => 'api.key',
            'M043_RATE_TOKEN_KEY' => 'rate-token.key',
            'M043_REQUEST_HMAC_KEY' => 'request-hmac.key',
        );
        if (!isset($files[$name])) $fail('SECRET_NAME_INVALID');
        $path = $secretDir.'/'.$files[$name];
        if (!is_file($path) || is_link($path)) $fail($name.'_FILE_INVALID');
        $value = trim((string) file_get_contents($path));
    } else {
        $value = trim((string) getenv($name));
    }
    if (strlen($value) < 32 || strlen($value) > 255) $fail($name.'_INVALID');
    return $value;
};

if ($argv[1] === 'check') {
    if (!$tableExists()) $fail('TABLE_ABSENT');
    $res = $query("SHOW INDEX FROM `".$table."` WHERE Key_name IN ('uk_cpo_public_request_ref','uk_cpo_public_request_idempotency','idx_cpo_public_request_queue')", 'INDEX_CHECK_FAILED');
    if ($db->num_rows($res) < 3) $fail('INDEX_INVALID');
    foreach ($rightIds as $rightId) {
        $res = $query("SELECT id FROM ".MAIN_DB_PREFIX."rights_def WHERE entity=".$entity." AND id=".(int)$rightId, 'RIGHT_QUERY_FAILED');
        if ($db->num_rows($res) !== 1) $fail('RIGHT_ABSENT_'.(int)$rightId);
    }
    $gateway = $gatewayUser();
    if (!$gateway || (int)$gateway->entity !== $entity || (int)$gateway->admin !== 0 || (int)$gateway->statut !== 1 || (int)$gateway->fk_soc !== 0 || (string)$gateway->note_private !== $marker || empty($gateway->api_key)) $fail('GATEWAY_USER_INVALID');
    $actualRights = array();
    $res = $query("SELECT fk_id FROM ".MAIN_DB_PREFIX."user_rights WHERE entity=".$entity." AND fk_user=".(int)$gateway->rowid." ORDER BY fk_id", 'GATEWAY_RIGHTS_QUERY_FAILED');
    while ($row = $db->fetch_object($res)) $actualRights[] = (int) $row->fk_id;
    if ($actualRights !== $apiRightIds) $fail('GATEWAY_RIGHTS_INVALID');
    foreach (array('CHINAPLANET_PUBLIC_RATE_TOKEN_KEY','CHINAPLANET_PUBLIC_REQUEST_HMAC_KEY') as $constant) {
        $res = $query("SELECT value FROM ".MAIN_DB_PREFIX."const WHERE entity=".$entity." AND name='".$db->escape($constant)."'", 'CONSTANT_QUERY_FAILED');
        $row = $db->fetch_object($res);
        if (!$row || strlen((string)$row->value) < 32) $fail('CONSTANT_INVALID');
    }
    echo "M043_GATEWAY_CHECK=PASS TABLE=YES API_USER=LEAST_PRIVILEGE SECRETS=SERVER_SIDE\n";
    exit(0);
}

if ($argv[1] === 'disable') {
    $gateway = $gatewayUser();
    if (!$gateway) { echo "M043_GATEWAY_DISABLE=PASS USER=ABSENT\n"; exit(0); }
    if ((string)$gateway->note_private !== $marker || (int)$gateway->admin !== 0 || (int)$gateway->fk_soc !== 0) $fail('UNEXPECTED_EXISTING_USER');
    $db->begin();
    if (!$db->query("DELETE FROM ".MAIN_DB_PREFIX."user_rights WHERE entity=".$entity." AND fk_user=".(int)$gateway->rowid)) { $db->rollback(); $fail('RIGHTS_DISABLE_FAILED'); }
    if (!$db->query("UPDATE ".MAIN_DB_PREFIX."user SET statut=0,api_key=NULL WHERE entity=".$entity." AND rowid=".(int)$gateway->rowid)) { $db->rollback(); $fail('USER_DISABLE_FAILED'); }
    $db->commit();
    echo "M043_GATEWAY_DISABLE=PASS\n";
    exit(0);
}

$apiKey = $readSecret('M043_DOLIBARR_API_KEY');
$tokenKey = $readSecret('M043_RATE_TOKEN_KEY');
$hmacKey = $readSecret('M043_REQUEST_HMAC_KEY');

$schema = file_get_contents(dirname(__DIR__, 2).'/custom/chinaplanetoperations/sql/llx_cpo_public_request.sql');
if ($schema === false) $fail('SCHEMA_SOURCE_UNREADABLE');
$schema = preg_replace('/^CREATE TABLE llx_/i', 'CREATE TABLE IF NOT EXISTS '.MAIN_DB_PREFIX, $schema);
if (!$db->query($schema)) $fail('TABLE_CREATE_FAILED');

$rights = array(
    500412 => array('chinaplanetshippingprices', 'Search public website FCL rates', 'website_rate_search'),
    500511 => array('chinaplanetoperations', 'Create public website intake requests', 'website_intake_create'),
    500512 => array('chinaplanetoperations', 'Read public website intake requests', 'publicrequest_read'),
    500513 => array('chinaplanetoperations', 'Update public website intake requests', 'publicrequest_update'),
    500514 => array('chinaplanetoperations', 'Promote public website requests to operations', 'publicrequest_promote'),
);
foreach ($rights as $id => $definition) {
    $sql = "INSERT INTO ".MAIN_DB_PREFIX."rights_def(id,entity,libelle,module,module_origin,module_position,family,family_position,perms,subperms,type,bydefault,enabled) VALUES(".(int)$id.",".$entity.",'".$db->escape($definition[1])."','".$db->escape($definition[0])."','',100099,'tools',0,'".$db->escape($definition[2])."','','r',0,'1') ON DUPLICATE KEY UPDATE libelle=VALUES(libelle),module=VALUES(module),perms=VALUES(perms),subperms=VALUES(subperms),bydefault=VALUES(bydefault),enabled=VALUES(enabled)";
    if (!$db->query($sql)) $fail('RIGHT_UPSERT_FAILED_'.(int)$id);
}

$gateway = $gatewayUser();
if ($gateway === false) $fail('DUPLICATE_GATEWAY_USER');
if ($gateway && ((string)$gateway->note_private !== $marker || (int)$gateway->admin !== 0 || (int)$gateway->fk_soc !== 0)) $fail('UNEXPECTED_EXISTING_USER');
if (!$gateway) {
    $res = $query("SELECT rowid FROM ".MAIN_DB_PREFIX."user WHERE entity=".$entity." AND admin=1 AND statut=1 ORDER BY rowid LIMIT 1", 'ADMIN_QUERY_FAILED');
    $adminRow = $db->fetch_object($res);
    if (!$adminRow) $fail('ADMIN_ABSENT');
    $actor = new User($db);
    if ($actor->fetch((int)$adminRow->rowid) <= 0) $fail('ADMIN_FETCH_FAILED');
    $created = new User($db);
    $created->entity = $entity;
    $created->login = $login;
    $created->lastname = 'Website Gateway';
    $created->firstname = 'M043 Staging';
    $created->statut = 1;
    $created->admin = 0;
    $created->note_private = $marker;
    $created->api_key = $apiKey;
    if ($created->create($actor, 1) <= 0) $fail('USER_CREATE_FAILED');
    $gateway = $gatewayUser();
}
if (!$gateway) $fail('USER_ABSENT_AFTER_CREATE');

$encryptedApiKey = dolEncrypt($apiKey, '', '', 'dolibarr');
$db->begin();
if (!$db->query("UPDATE ".MAIN_DB_PREFIX."user SET admin=0,statut=1,fk_soc=NULL,note_private='".$db->escape($marker)."',api_key='".$db->escape($encryptedApiKey)."' WHERE entity=".$entity." AND rowid=".(int)$gateway->rowid)) { $db->rollback(); $fail('USER_UPDATE_FAILED'); }
if (!$db->query("DELETE FROM ".MAIN_DB_PREFIX."user_rights WHERE entity=".$entity." AND fk_user=".(int)$gateway->rowid)) { $db->rollback(); $fail('RIGHTS_CLEAR_FAILED'); }
foreach ($apiRightIds as $rightId) {
    if (!$db->query("INSERT INTO ".MAIN_DB_PREFIX."user_rights(entity,fk_user,fk_id) VALUES(".$entity.",".(int)$gateway->rowid.",".(int)$rightId.")")) { $db->rollback(); $fail('RIGHT_GRANT_FAILED'); }
}
$db->commit();

if (dolibarr_set_const($db, 'CHINAPLANET_PUBLIC_RATE_TOKEN_KEY', $tokenKey, 'chaine', 0, 'M043 server-side public rate token key', $entity) <= 0) $fail('RATE_TOKEN_CONSTANT_FAILED');
if (dolibarr_set_const($db, 'CHINAPLANET_PUBLIC_REQUEST_HMAC_KEY', $hmacKey, 'chaine', 0, 'M043 server-side request HMAC key', $entity) <= 0) $fail('REQUEST_HMAC_CONSTANT_FAILED');

echo "M043_GATEWAY_APPLY=PASS TABLE=YES API_USER=LEAST_PRIVILEGE SECRETS=SERVER_SIDE\n";
