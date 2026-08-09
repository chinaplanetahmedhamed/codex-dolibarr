<?php
/** M044-C04 behavior retained behind the M046-C01 hard-disable boundary. */

$root = dirname(__DIR__, 2);
$read = function ($path) use ($root) {
    $value = file_get_contents($root.'/'.$path);
    if ($value === false) throw new RuntimeException('Unreadable '.$path);
    return $value;
};
$assert = function ($condition, $message) { if (!$condition) throw new RuntimeException($message); };

$translatorSource = $read('custom/chinaplanetshippingprices/class/ShippingNoteTranslationService.class.php');
$reviewSource = $read('custom/chinaplanetshippingprices/class/ShippingNoteReviewService.class.php');
$batchSource = $read('custom/chinaplanetshippingprices/admin/batch.php');
$setupSource = $read('custom/chinaplanetshippingprices/admin/setup.php');
$importerSource = $read('custom/chinaplanetshippingprices/class/ShippingPriceImporter.class.php');
$serviceSource = $read('custom/chinaplanetshippingprices/class/ShippingPriceService.class.php');

$assert(strpos($translatorSource, 'public static function automaticTranslationEnabled()') !== false && strpos($translatorSource, 'return false;') !== false, 'central hard-disable absent');
$assert(strpos($importerSource, 'ShippingNoteTranslationService::automaticTranslationEnabled()') !== false, 'importer bypasses central policy');
$assert(strpos($serviceSource, 'ShippingNoteTranslationService::automaticTranslationEnabled()') !== false, 'public projection bypasses central policy');
$assert(strpos($setupSource, 'ShippingNoteTranslationService::automaticTranslationEnabled()') !== false, 'setup bypasses central policy');
$assert(substr_count($reviewSource, 'Carrier-note translation is disabled by release policy.') >= 2, 'review mutations are not both guarded');
$assert(strpos($batchSource, '$canReviewTranslations ? $reviewService->listGroups') !== false, 'authorized read-only note review is hidden');
$assert(strpos($batchSource, 'Read-only source and provenance review') !== false && strpos($batchSource, 'name="notes_en"') === false && strpos($batchSource, 'chinaplanetsp-auto-translate') === false, 'disabled read-only rendering boundary absent');
$assert(strpos($setupSource, 'name="auto_translation_enabled"') === false && strpos($setupSource, "'CHINAPLANETSP_AUTO_TRANSLATION_ENABLED'") === false, 'setup can still write translation toggle');

if (!defined('MAIN_DB_PREFIX')) define('MAIN_DB_PREFIX', 'llx_');
require_once $root.'/custom/chinaplanetshippingprices/class/ShippingNoteReviewService.class.php';
class M044C04NoDb {
    public $queries = 0; public $begins = 0; public $updates = 0;
    public function query($sql) { $this->queries++; if (strpos($sql, 'UPDATE ') === 0) $this->updates++; throw new RuntimeException('database must not run'); }
    public function begin() { $this->begins++; throw new RuntimeException('transaction must not start'); }
}
class M044C04NoTranslator {
    public $calls = 0;
    public function translate($source, $remainingSeconds = null) { $this->calls++; throw new RuntimeException('translator must not run'); }
}
$staff = (object) array('socid'=>0, 'admin'=>1, 'id'=>7);
foreach (array('auto','manual') as $path) {
    $db = new M044C04NoDb();
    $translator = new M044C04NoTranslator();
    $review = new ShippingNoteReviewService($db, $staff, 1, $translator);
    try {
        if ($path === 'auto') $review->autoTranslatePending(21, 8, 18.0);
        else $review->mutate(21, 9, 'save_translation', 'Bounded English');
        throw new RuntimeException($path.' mutation unexpectedly succeeded');
    } catch (RuntimeException $e) {
        $assert($e->getMessage() === 'Carrier-note translation is disabled by release policy.', $path.' mutation returned wrong refusal');
    }
    $assert($db->queries === 0 && $db->begins === 0 && $db->updates === 0 && $translator->calls === 0, $path.' refusal touched DB or transport');
}

echo "M044_C04_FREE_AUTO_TRANSLATION=PASS effective=DISABLED review=READ_ONLY provider_calls=0 db_mutations=0\n";
