<?php
/** Executable M046-C01 policy, mutation, and public-projection contract. */

$root = dirname(__DIR__, 2);
if (!defined('MAIN_DB_PREFIX')) define('MAIN_DB_PREFIX', 'llx_');
require_once $root.'/custom/chinaplanetshippingprices/class/ShippingNoteTranslationService.class.php';
require_once $root.'/custom/chinaplanetshippingprices/class/ShippingNoteReviewService.class.php';
require_once $root.'/custom/chinaplanetshippingprices/class/ShippingPriceImporter.class.php';
require_once $root.'/custom/chinaplanetshippingprices/class/ShippingPriceService.class.php';

function m046Assert($condition, $message) { if (!$condition) throw new RuntimeException($message); }

$transportCalls = 0;
$resolverCalls = 0;
$translation = (new ShippingNoteTranslationService(
    function () use (&$transportCalls) { $transportCalls++; throw new RuntimeException('transport must not run'); },
    array(
        'CHINAPLANETSP_TRANSLATION_ENDPOINT'=>'https://api.deepseek.com/chat/completions',
        'CHINAPLANETSP_TRANSLATION_MODEL'=>'deepseek-v4-flash',
        'CHINAPLANETSP_TRANSLATION_API_KEY'=>'present-but-unused',
    ),
    function () use (&$resolverCalls) { $resolverCalls++; throw new RuntimeException('resolver must not run'); }
))->translate(" \0南沙 航次 AB12CD34 ");
m046Assert($translation['status'] === 'not_needed' && $translation['error'] === 'translation_disabled' && $translation['attempted'] === false, 'direct translation did not fail closed');
m046Assert($translation['source_hash'] === '' && $transportCalls === 0 && $resolverCalls === 0, 'disabled adapter processed source/provider state');

class M046NoDb {
    public $queries = 0; public $begins = 0; public $commits = 0; public $rollbacks = 0;
    public function query($sql) { $this->queries++; throw new RuntimeException('database must not run'); }
    public function begin() { $this->begins++; throw new RuntimeException('transaction must not start'); }
    public function commit() { $this->commits++; }
    public function rollback() { $this->rollbacks++; }
}
class M046NoTranslator {
    public $calls = 0;
    public function translate($source, $remainingSeconds = null) { $this->calls++; throw new RuntimeException('translator must not run'); }
}
$staff = (object) array('socid'=>0, 'admin'=>1, 'id'=>7);
foreach (array('auto','manual') as $path) {
    $db = new M046NoDb();
    $translator = new M046NoTranslator();
    $review = new ShippingNoteReviewService($db, $staff, 1, $translator);
    try {
        if ($path === 'auto') $review->autoTranslatePending(44, 20, 20.0);
        else $review->mutate(44, 8, 'approve_translation', 'English AB12CD34');
        throw new RuntimeException($path.' mutation unexpectedly succeeded');
    } catch (RuntimeException $e) {
        m046Assert($e->getMessage() === 'Carrier-note translation is disabled by release policy.', $path.' refusal is not policy-specific');
    }
    m046Assert($db->queries === 0 && $db->begins === 0 && $db->commits === 0 && $db->rollbacks === 0, $path.' refusal touched database');
    m046Assert($translator->calls === 0, $path.' refusal touched translation transport');
}

class M046ReviewUser {
    public $socid=0; public $admin=0; public $id=8; private $rights;
    public function __construct(array $rights) { $this->rights=$rights; }
    public function hasRight($module,$right) { return !empty($this->rights[$right]); }
}
class M046ReviewResult { public $rows; public $index=0; public function __construct(array $rows){$this->rows=$rows;} }
class M046ReviewDb {
    public $queries=array();
    public function query($sql) {
        $this->queries[]=$sql;
        if (strpos($sql,'COUNT(DISTINCT source_row)')!==false) return new M046ReviewResult(array((object)array('total'=>1)));
        return new M046ReviewResult(array((object)array('source_row'=>3,'source_notes_raw'=>'RAW','source_notes'=>'SOURCE','notes_en'=>'ENGLISH','translation_status'=>'approved','translation_provider'=>'legacy','translation_model'=>'model','translation_error'=>'','translation_source_hash'=>'hash','rate_count'=>1,'hash_count'=>1,'status_count'=>1)));
    }
    public function fetch_object($result) { return $result->index<count($result->rows)?$result->rows[$result->index++]:false; }
}
$reviewDb = new M046ReviewDb();
$reviewUser = new M046ReviewUser(array('import'=>true,'viewcost'=>true));
$groups = (new ShippingNoteReviewService($reviewDb,$reviewUser,7))->listGroups(44,0,25);
m046Assert($groups['total']===1 && count($groups['groups'])===1 && $groups['groups'][0]->source_notes==='SOURCE', 'authorized read-only review failed');
m046Assert(count($reviewDb->queries)===2, 'authorized review query count changed');
foreach ($reviewDb->queries as $sql) m046Assert(strpos($sql,'entity=7 AND fk_batch=44')!==false, 'review query lost entity/batch scope');
foreach (array(new M046ReviewUser(array('import'=>true)), (object)array('socid'=>99,'admin'=>0,'id'=>9)) as $deniedUser) {
    $deniedDb = new M046ReviewDb();
    $denied = (new ShippingNoteReviewService($deniedDb,$deniedUser,7))->listGroups(44,0,25);
    m046Assert($denied['total']===0 && $deniedDb->queries===array(), 'unauthorized read-only review queried restricted notes');
}

if (!isset($GLOBALS['conf'])) $GLOBALS['conf'] = new stdClass();
if (!isset($GLOBALS['conf']->global)) $GLOBALS['conf']->global = new stdClass();
$GLOBALS['conf']->global->CHINAPLANETSP_LEGACY_VALIDITY_DAYS = 30;
class M046ProjectionDb {}
$service = new ShippingPriceService(new M046ProjectionDb(), 1);
$reflector = new ReflectionClass($service);
$project = $reflector->getMethod('priceRate');
$project->setAccessible(true);
$baseRow = (object) array(
    'rowid'=>1, 'pol'=>'NANSHA', 'destination_country'=>'EG', 'pod'=>'SOKHNA',
    'shipping_line'=>'TEST', 'region_label'=>'Red Sea', 'service_type'=>'direct',
    'container_type'=>'40HQ', 'currency_code'=>'USD', 'sale_price'=>2200,
    'effective_date'=>'2026-08-01', 'valid_from'=>'2026-08-01', 'valid_to'=>'2026-09-01',
    'translation_status'=>'approved', 'public_notes'=>'APPROVED IMPORTED NOTE', 'source_notes'=>'内部来源',
);
$filters = array('incoterm'=>'FOB', 'cargo_type'=>'normal');
foreach (array(0, 1) as $stored) {
    $GLOBALS['conf']->global->CHINAPLANETSP_AUTO_TRANSLATION_ENABLED = $stored;
    m046Assert(ShippingPriceImporter::automaticTranslationEnabled() === false, 'stored constant enabled importer policy');
    $imported = $project->invoke($service, clone $baseRow, $filters, array(), false);
    m046Assert($imported['notes'] === '', 'imported provenance note leaked publicly for stored='.$stored);
    $manualRow = clone $baseRow;
    $manualRow->source_notes = null;
    $manualRow->public_notes = 'LEGITIMATE MANUAL NOTE';
    $manual = $project->invoke($service, $manualRow, $filters, array(), false);
    m046Assert($manual['notes'] === 'LEGITIMATE MANUAL NOTE', 'manual public note was suppressed for stored='.$stored);
}

echo "M046_C01_TRANSLATION_POLICY=PASS EFFECTIVE_ENABLED=0 PROVIDER_CALLS=0 DB_MUTATIONS=0 REVIEW=IMPORT_PLUS_VIEWCOST ENTITY_SCOPED=YES IMPORTED_NOTES=SUPPRESSED MANUAL_NOTES=PRESERVED\n";
