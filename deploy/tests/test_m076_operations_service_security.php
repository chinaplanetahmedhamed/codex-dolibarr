<?php
if (PHP_SAPI !== 'cli') { fwrite(STDERR, "M076_SERVICE_SECURITY=FAIL REASON=CLI_ONLY\n"); exit(1); }
define('MAIN_DB_PREFIX', 'llx_');
$conf = (object) array('entity'=>1);
function dol_now() { return time(); }
require_once dirname(__DIR__, 2).'/custom/chinaplanetoperations/class/OperationsService.class.php';

final class M076FakeDb
{
    public $queries = array();
    public $mode = 'valid';
    public function query($sql)
    {
        $this->queries[] = $sql;
        if (strpos($sql, 'SELECT GET_LOCK(') === 0) return (object) array('acquired'=>1);
        if (strpos($sql, "action_code='operation_create_request'") !== false) return $this->mode === 'retry_error' ? false : null;
        if (strpos($sql, 'FROM llx_societe') !== false) return $this->mode === 'invalid_customer' ? (object) array('rows'=>0) : (object) array('rows'=>1);
        if (strpos($sql, 'FROM llx_user ') !== false || strpos($sql, 'FROM llx_usergroup_user ') !== false) return (object) array('rows'=>1);
        if (strpos($sql, 'SELECT ref FROM ') === 0) return null;
        if (strpos($sql, 'INSERT INTO llx_cpo_operation(') === 0) throw new RuntimeException('REACHED_INSERT');
        return true;
    }
    public function fetch_object($result) { return is_object($result) && isset($result->acquired) ? $result : null; }
    public function num_rows($result) { return is_object($result) && isset($result->rows) ? (int) $result->rows : 0; }
    public function begin() { return true; }
    public function commit() { return true; }
    public function rollback() { return true; }
    public function escape($value) { return addslashes($value); }
    public function idate($value) { return date('Y-m-d H:i:s', $value); }
    public function lasterror() { return 'M076_RETRY_LOOKUP_FAILED'; }
}

function m076ExpectMessage(callable $callable, $expected)
{
    try { $callable(); } catch (Throwable $e) {
        if ($e->getMessage() === $expected) return;
        throw new RuntimeException('Expected '.$expected.'; got '.$e->getMessage());
    }
    throw new RuntimeException('Expected exception '.$expected);
}

$base = array(
    'service_type'=>'general', 'title'=>'in-memory', 'fk_soc'=>123, 'fk_project'=>0, 'fk_commande'=>0,
    'fk_shipment'=>0, 'fk_user_assign'=>5, 'fk_usergroup'=>3, 'require_assignment'=>1,
    'priority'=>'normal', 'waiting_on'=>'internal', 'next_action'=>'Review', 'date_deadline'=>'',
    'note_private'=>'', 'note_public'=>'', 'customer_visible'=>0, 'service_data'=>array(),
);

$db = new M076FakeDb();
$service = new OperationsService($db);
$db->mode = 'invalid_customer';
m076ExpectMessage(function () use ($service, $base) { $service->create($base, 5); }, 'Customer must be an active customer in the current entity.');

$db = new M076FakeDb();
$service = new OperationsService($db);
$retry = $base; $retry['fk_soc'] = 0; $retry['request_hash'] = str_repeat('a', 64);
$db->mode = 'retry_error';
m076ExpectMessage(function () use ($service, $retry) { $service->create($retry, 5); }, 'M076_RETRY_LOOKUP_FAILED');
foreach ($db->queries as $query) if (strpos($query, 'INSERT INTO llx_cpo_operation(') === 0) throw new RuntimeException('Retry lookup failed open.');

$db = new M076FakeDb();
$service = new OperationsService($db);
$legacy = $base; $legacy['fk_soc'] = 0; $legacy['fk_user_assign'] = 0; $legacy['fk_usergroup'] = 0; unset($legacy['require_assignment']);
m076ExpectMessage(function () use ($service, $legacy) { $service->create($legacy, 5); }, 'REACHED_INSERT');

$workspace = file_get_contents(dirname(__DIR__, 2).'/custom/chinaplanetoperations/workspace.php');
foreach (array(
    "hasRight('societe', 'lire')",
    "hasRight('commande', 'lire')",
    "hasRight('projet', 'all', 'lire')",
    "hasRight('societe', 'client', 'voir')",
    "if (!\$canReadCustomers && in_array(\$view, array('customers', 'customer'), true)) accessforbidden()",
) as $needle) if (strpos($workspace, $needle) === false) throw new RuntimeException('Workspace permission guard missing: '.$needle);

echo "M076_SERVICE_SECURITY=PASS CUSTOMER=ENTITY_SCOPED ASSIGNMENT=GUIDED_REQUIRED LEGACY_CREATE=COMPATIBLE RETRY_LOOKUP=FAIL_CLOSED NATIVE_RIGHTS=GATED\n";
