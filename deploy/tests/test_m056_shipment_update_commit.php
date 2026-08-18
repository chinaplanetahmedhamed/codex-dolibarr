<?php

$tmp = sys_get_temp_dir().'/m056-commit-'.bin2hex(random_bytes(4));
mkdir($tmp.'/lib', 0700, true);
file_put_contents($tmp.'/lib/shipment_customer.lib.php', <<<'PHP'
<?php
function cpl_shipment_schema_ready($db) { return true; }
function cpl_shipment_has_audit_rows($db, $id, $entity, $socid) { return false; }
function cpl_status_milestone($status) { return 'departed'; }
function cpl_shipment_event_insert($db, $shipment, $user, $event) { $db->event = $event; return true; }
PHP
);

define('MAIN_DB_PREFIX', 'llx_');
$conf = (object) array('entity'=>1);
$GLOBALS['conf'] = $conf;

#[AllowDynamicProperties]
class CommonObject {}
function dol_now() { return 1786665600; }

$source = getenv('M056_DOL_DOCUMENT_ROOT') ?: dirname(__DIR__, 2);
$classSource = file_get_contents($source.'/custom/chinaplanetlogistics/class/chinaplanetlogistics.class.php');
$classSource = preg_replace("~require_once __DIR__.'/../lib/shipment_customer.lib.php';~", "require_once '".$tmp."/lib/shipment_customer.lib.php';", $classSource, 1);
file_put_contents($tmp.'/chinaplanetlogistics.class.php', $classSource);
require $tmp.'/chinaplanetlogistics.class.php';

class M056CommitDb {
    public $commitResult = true;
    public $event;
    public function begin() { return 1; }
    public function rollback() { return 1; }
    public function escape($value) { return addslashes((string) $value); }
    public function idate($value) { return date('Y-m-d H:i:s', (int) $value); }
    public function jdate($value) { return is_numeric($value) ? (int) $value : strtotime((string) $value); }
    public function lasterror() { return 'synthetic DB failure'; }
    public function query($sql) {
        if (strpos($sql, 'FOR UPDATE') !== false) return (object) array('kind'=>'old');
        return (object) array('kind'=>'write');
    }
    public function fetch_object($result) {
        return (object) array('fk_soc'=>42, 'fk_statut'=>1, 'date_eta'=>null, 'date_atd'=>null, 'date_ata'=>null);
    }
    public function commit() { return $this->commitResult ? 1 : 0; }
}

function m056Shipment($db) {
    $shipment = new ChinaPlanetLogistics($db);
    $shipment->id=77; $shipment->entity=1; $shipment->fk_soc=42; $shipment->fk_project=0;
    $shipment->ref='LOG-77'; $shipment->nb_containers=1; $shipment->fk_statut=2;
    $shipment->freetime_days=0; $shipment->audit_customer_visible=1;
    return $shipment;
}

$db = new M056CommitDb();
$user = (object) array('id'=>9);
$shipment = m056Shipment($db);
$success = $shipment->update($user);
$successOk = $success === 1 && $shipment->last_status_change === array('from'=>1, 'to'=>2);
echo ($successOk ? 'PASS ' : 'FAIL ')."committed transition is exposed for email\n";

$db->commitResult = false;
$shipment = m056Shipment($db);
$failed = $shipment->update($user);
$failureOk = $failed === -1 && $shipment->last_status_change === null && $shipment->error === 'Shipment update could not be committed.';
echo ($failureOk ? 'PASS ' : 'FAIL ')."commit failure clears transition and blocks email\n";

@unlink($tmp.'/chinaplanetlogistics.class.php');
@unlink($tmp.'/lib/shipment_customer.lib.php');
@rmdir($tmp.'/lib');
@rmdir($tmp);

if (!$successOk || !$failureOk) exit(1);
echo "M056_SHIPMENT_UPDATE_COMMIT=PASS\n";
