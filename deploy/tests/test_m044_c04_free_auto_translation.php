<?php

/** M044-C04 keyless-provider and automatic-retry source contract. */

$root = dirname(__DIR__, 2);
$read = function ($path) use ($root) {
    $value = file_get_contents($root.'/'.$path);
    if ($value === false) throw new RuntimeException('Unreadable '.$path);
    return $value;
};
$assert = function ($condition, $message) {
    if (!$condition) throw new RuntimeException($message);
};

$translator = $read('custom/chinaplanetshippingprices/class/ShippingNoteTranslationService.class.php');
$review = $read('custom/chinaplanetshippingprices/class/ShippingNoteReviewService.class.php');
$batch = $read('custom/chinaplanetshippingprices/admin/batch.php');
$importer = $read('custom/chinaplanetshippingprices/class/ShippingPriceImporter.class.php');
$importPage = $read('custom/chinaplanetshippingprices/admin/import.php');

$assert(strpos($translator, "FREE_ENDPOINT = 'https://api.mymemory.translated.net/get'") !== false, 'fixed MyMemory endpoint absent');
$assert(strpos($translator, "FREE_LANGUAGE_PAIR = 'zh-CN|en'") !== false, 'fixed language pair absent');
$assert(strpos($translator, "array('q'=>\$providerSource, 'langpair'=>self::FREE_LANGUAGE_PAIR, 'mt'=>'1')") !== false, 'minimal free-provider payload absent');
$assert(strpos($translator, 'maskProtectedTokens') !== false && strpos($translator, 'restoreProtectedTokens') !== false, 'free-provider token preservation absent');
$assert(strpos($translator, "'method' => \$isFreeProvider ? 'GET' : 'POST'") !== false, 'provider method isolation absent');
$assert(strpos($translator, 'provider_quota_exhausted') !== false && strpos($translator, 'FREE_MAX_QUERY_BYTES = 500') !== false, 'quota or byte-limit fail-closed behavior absent');
$assert(strpos($translator, 'validateEnglishTranslation') !== false && strpos($translator, 'CURLOPT_FOLLOWLOCATION => false') !== false && strpos($translator, 'CURLOPT_RESOLVE') !== false, 'translation validation or SSRF controls absent');

$assert(strpos($review, 'public function autoTranslatePending') !== false, 'automatic retry service absent');
$assert(strpos($review, "AND translation_status<>'approved'") !== false, 'pending-only retry filter absent');
$assert(strpos($review, 'FOR UPDATE') !== false && strpos($review, 'hash_equals') !== false, 'retry locking or source-hash validation absent');
$assert(strpos($review, "translation_status='approved'") !== false && strpos($review, 'fk_user_translation_review=NULL') !== false, 'automatic acceptance provenance absent');
$assert(strpos($review, "hasRight('chinaplanetshippingprices', 'import')") !== false && strpos($review, "hasRight('chinaplanetshippingprices', 'viewcost')") !== false, 'private-note permissions absent');

$assert(strpos($batch, "array('save_translation','reset_translation','auto_translate')") !== false, 'CSRF action allowlist absent');
$assert(strpos($batch, "\$m041PostedAction === 'auto_translate'") !== false && strpos($batch, 'autoTranslatePending($id, 8, 18.0)') !== false, 'bounded automatic POST retry absent');
$assert(strpos($batch, 'form.submit()') !== false && strpos($batch, "\$autoMode !== 'stopped'") !== false, 'automatic continuation or no-progress stop absent');
$assert(strpos($batch, 'requires manual approval') === false && strpos($importer, 'Approve every imported carrier/source note') === false, 'stale manual-approval requirement remains');
$assert(strpos($importer, 'manual editing is optional') !== false && strpos($importPage, 'without manual approval') !== false, 'optional-edit wording absent');

if (!defined('MAIN_DB_PREFIX')) define('MAIN_DB_PREFIX', 'llx_');
require_once $root.'/custom/chinaplanetshippingprices/class/ShippingNoteReviewService.class.php';

class M044C04FakeResult
{
    public $rows;
    public $index = 0;
    public function __construct(array $rows) { $this->rows = $rows; }
}

class M044C04FakeDb
{
    public $source = '南沙 航次 AB12CD34 40HQ 加收 RMB180';
    public $approved = false;
    public $updates = array();
    public $begins = 0;
    public $commits = 0;
    public $rollbacks = 0;

    public function query($sql)
    {
        if (strpos($sql, 'SELECT status FROM llx_chinaplanetsp_batch') === 0) return new M044C04FakeResult(array((object) array('status'=>0)));
        if (strpos($sql, 'SELECT source_row,MIN(source_notes)') === 0) {
            if ($this->approved) return new M044C04FakeResult(array());
            return new M044C04FakeResult(array((object) array(
                'source_row'=>9,
                'source_notes'=>$this->source,
                'source_hash'=>hash('sha256', $this->source),
                'note_count'=>1,
                'hash_count'=>1,
            )));
        }
        if (strpos($sql, 'SELECT source_notes,translation_source_hash,translation_status') === 0) {
            return new M044C04FakeResult(array((object) array(
                'source_notes'=>$this->source,
                'translation_source_hash'=>hash('sha256', $this->source),
                'translation_status'=>$this->approved ? 'approved' : 'failed',
            )));
        }
        if (strpos($sql, 'UPDATE llx_chinaplanetsp_rate SET ') === 0) {
            $this->updates[] = $sql;
            if (strpos($sql, "translation_status='approved'") !== false) $this->approved = true;
            return true;
        }
        if (strpos($sql, 'SELECT COUNT(DISTINCT source_row) remaining') === 0) {
            return new M044C04FakeResult(array((object) array('remaining'=>$this->approved ? 0 : 1)));
        }
        throw new RuntimeException('Unexpected fake SQL: '.$sql);
    }
    public function fetch_object($result)
    {
        if ($result->index >= count($result->rows)) return false;
        return $result->rows[$result->index++];
    }
    public function begin() { $this->begins++; }
    public function commit() { $this->commits++; }
    public function rollback() { $this->rollbacks++; }
    public function escape($value) { return addslashes((string) $value); }
    public function lasterror() { return 'fake_db_error'; }
}

class M044C04FakeTranslator
{
    private $fail;
    public function __construct($fail = false) { $this->fail = (bool) $fail; }
    public function translate($source, $remainingSeconds = null)
    {
        if ($this->fail) return array('status'=>'failed','notes_en'=>'','source_hash'=>hash('sha256',$source),'provider'=>'api.mymemory.translated.net','model'=>'zh-CN|en','error'=>'provider_quota_exhausted','attempted'=>true);
        return array('status'=>'approved','notes_en'=>'Nansha voyage AB12CD34 40HQ surcharge RMB180','source_hash'=>hash('sha256',$source),'provider'=>'api.mymemory.translated.net','model'=>'zh-CN|en','error'=>'','attempted'=>true);
    }
}

$staff = (object) array('socid'=>0, 'admin'=>1, 'id'=>7);
$successDb = new M044C04FakeDb();
$success = (new ShippingNoteReviewService($successDb, $staff, 1, new M044C04FakeTranslator()))->autoTranslatePending(21, 8, 18.0);
$assert($success['approved'] === 1 && $success['remaining'] === 0 && $successDb->begins === 1 && $successDb->commits === 1 && $successDb->rollbacks === 0, 'successful automatic retry transaction failed');
$assert(count($successDb->updates) === 1 && strpos($successDb->updates[0], "public_notes='Nansha voyage AB12CD34 40HQ surcharge RMB180'") !== false && strpos($successDb->updates[0], 'fk_user_translation_review=NULL') !== false, 'automatic acceptance persistence failed');

$failedDb = new M044C04FakeDb();
$failed = (new ShippingNoteReviewService($failedDb, $staff, 1, new M044C04FakeTranslator(true)))->autoTranslatePending(21, 8, 18.0);
$assert($failed['approved'] === 0 && $failed['failed'] === 1 && $failed['remaining'] === 1 && $failed['stalled'] === true, 'failed provider did not stop safely');
$assert(count($failedDb->updates) === 1 && strpos($failedDb->updates[0], 'public_notes=NULL') !== false && strpos($failedDb->updates[0], 'provider_quota_exhausted') !== false, 'failed provider output was not kept private');

echo "M044_C04_FREE_AUTO_TRANSLATION=PASS provider=MYMEMORY key=NONE retry=BOUNDED db_flow=PASS approval=AUTOMATIC publication=BLOCKED_WHILE_PENDING\n";
