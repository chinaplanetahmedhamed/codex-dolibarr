<?php
/** Read-only runtime proof for M041/M046-C01 translation mutation refusal. */

$documentRoot = getenv('M041_DOL_DOCUMENT_ROOT');
if ($documentRoot === false || $documentRoot === '') $documentRoot = dirname(__DIR__, 2);
$documentRoot = rtrim($documentRoot, '/');
$servicePath = $documentRoot.'/custom/chinaplanetshippingprices/class/ShippingNoteReviewService.class.php';
if (!is_file($servicePath) || is_link($servicePath)) throw new RuntimeException('M041 runtime service path invalid');
if (!defined('MAIN_DB_PREFIX')) define('MAIN_DB_PREFIX', 'llx_');
require_once $servicePath;

class M041RuntimeNoDb { public $queries=0; public $begins=0; public function query($sql){$this->queries++; throw new RuntimeException('DB must not run');} public function begin(){$this->begins++; throw new RuntimeException('DB must not run');} }
class M041RuntimeNoTransport { public $calls=0; public function translate($source,$remainingSeconds=null){$this->calls++; throw new RuntimeException('transport must not run');} }
$db = new M041RuntimeNoDb();
$transport = new M041RuntimeNoTransport();
$staff = (object) array('id'=>7,'admin'=>1,'socid'=>0);
$service = new ShippingNoteReviewService($db, $staff, 1, $transport);
foreach (array('auto','manual') as $path) {
    try {
        if ($path === 'auto') $service->autoTranslatePending(1);
        else $service->mutate(1, 1, 'save_translation', 'English');
        throw new RuntimeException($path.' unexpectedly succeeded');
    } catch (RuntimeException $e) {
        if ($e->getMessage() !== 'Carrier-note translation is disabled by release policy.') throw $e;
    }
}
if ($db->queries !== 0 || $db->begins !== 0 || $transport->calls !== 0) throw new RuntimeException('disabled runtime touched DB or transport');
echo "M041_RUNTIME=PASS effective=DISABLED provider_calls=0 db_mutations=0\n";
