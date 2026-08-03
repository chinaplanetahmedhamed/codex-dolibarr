<?php
/** Executable M041 mutation, permission, and entity-isolation regression test. */

define('NOLOGIN', 1);
define('NOREQUIREMENU', 1);
define('NOREQUIREHTML', 1);
define('NOCSRFCHECK', 1);

$documentRoot = getenv('M041_DOL_DOCUMENT_ROOT');
if ($documentRoot === false || $documentRoot === '') {
    $documentRoot = '/Users/mac/Documents/dev/dolibarr/dolibarr-Dev/dolibarr-23.0.3/htdocs';
}
$documentRoot = rtrim($documentRoot, '/');
if (!is_file($documentRoot.'/master.inc.php')) {
    fwrite(STDERR, "M041_RUNTIME=FAIL REASON=DOLIBARR_ROOT_INVALID\n");
    exit(1);
}
$_SERVER['DOCUMENT_ROOT'] = $documentRoot;
$_SERVER['SERVER_NAME'] = '127.0.0.1';
$_SERVER['SERVER_PORT'] = '8088';
require $documentRoot.'/master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/custom/chinaplanetshippingprices/class/ShippingNoteReviewService.class.php';

class M041RuntimeUser
{
    public $id;
    public $admin;
    public $socid;
    private $rights;

    public function __construct($id, $admin, $socid, array $rights = array())
    {
        $this->id = (int) $id;
        $this->admin = (int) $admin;
        $this->socid = (int) $socid;
        $this->rights = $rights;
    }

    public function hasRight($module, $permission)
    {
        return !empty($this->rights[$module.'.'.$permission]);
    }
}

function m041Assert($condition, $message)
{
    if (!$condition) throw new RuntimeException($message);
}

function m041ExpectException($callback, $message)
{
    try {
        $callback();
    } catch (RuntimeException $exception) {
        return;
    }
    throw new RuntimeException($message);
}

function m041InsertBatch($db, $entity, $ref, $status)
{
    $sql = "INSERT INTO ".MAIN_DB_PREFIX."chinaplanetsp_batch SET entity=".(int) $entity;
    $sql .= ",ref='".$db->escape($ref)."',source_name='M041 runtime test',original_name='m041-runtime.xlsx'";
    $sql .= ",file_hash='".hash('sha256', $ref)."',source_sheet='M041',effective_date='2026-08-03',import_mode='current'";
    $sql .= ",ignore_below=1000,currency_code='USD',total_rows=2,accepted_rows=2,ignored_rows=0,warning_count=0";
    $sql .= ",status=".(int) $status.",report_json='{}',fk_user_creat=1,date_creation='".$db->idate(dol_now())."'";
    m041Assert($db->query($sql), 'Could not create M041 test batch: '.$db->lasterror());
    return (int) $db->last_insert_id(MAIN_DB_PREFIX.'chinaplanetsp_batch');
}

function m041InsertRate($db, $entity, $batchId, $sourceRow, $container, $sourceNote, $status = 'failed')
{
    $hash = hash('sha256', $sourceNote);
    $sql = "INSERT INTO ".MAIN_DB_PREFIX."chinaplanetsp_rate SET entity=".(int) $entity.",fk_batch=".(int) $batchId;
    $sql .= ",source_sheet='M041',source_row=".(int) $sourceRow.",source_cell='A".(int) $sourceRow."',region_label='M041'";
    $sql .= ",pol='NANSHA',destination_country='EG',pod='SOKHNA',shipping_line='TEST',service_type='direct',route_detail='DIRECT'";
    $sql .= ",container_type='".$db->escape($container)."',cost_price=2000,sale_price=2200,currency_code='USD'";
    $sql .= ",valid_from='2026-08-03',source_notes_raw='".$db->escape($sourceNote)."',source_notes='".$db->escape($sourceNote)."'";
    $sql .= ",translation_status='".$db->escape($status)."',translation_source_hash='".$hash."',translation_error='provider_not_configured'";
    $sql .= ",public_visible=1,is_current=0,status=0,fk_user_creat=1,date_creation='".$db->idate(dol_now())."'";
    m041Assert($db->query($sql), 'Could not create M041 test rate: '.$db->lasterror());
    return (int) $db->last_insert_id(MAIN_DB_PREFIX.'chinaplanetsp_rate');
}

$entity = (int) $conf->entity;
$foreignEntity = $entity + 9000;
$suffix = strtoupper(substr(hash('sha256', uniqid('m041', true)), 0, 10));
$batchIds = array();

try {
    $draftBatch = m041InsertBatch($db, $entity, 'M041-DRAFT-'.$suffix, 0);
    $foreignBatch = m041InsertBatch($db, $foreignEntity, 'M041-FOREIGN-'.$suffix, 0);
    $batchIds[] = $draftBatch;
    $batchIds[] = $foreignBatch;

    $sourceNote = '南沙 KOTA 0085W RMB180';
    $rateOne = m041InsertRate($db, $entity, $draftBatch, 29, '20GP', $sourceNote);
    $rateTwo = m041InsertRate($db, $entity, $draftBatch, 29, '40HQ', $sourceNote);
    $unrelatedRate = m041InsertRate($db, $entity, $draftBatch, 30, '40HQ', '蛇口 40HQ');
    m041InsertRate($db, $foreignEntity, $foreignBatch, 29, '20GP', $sourceNote);

    $external = new M041RuntimeUser(2, 0, 123, array(
        'chinaplanetshippingprices.import'=>true,
        'chinaplanetshippingprices.viewcost'=>true,
    ));
    $noRights = new M041RuntimeUser(3, 0, 0);
    $staff = new M041RuntimeUser(4, 0, 0, array(
        'chinaplanetshippingprices.import'=>true,
        'chinaplanetshippingprices.viewcost'=>true,
    ));

    m041ExpectException(function () use ($db, $external, $entity, $draftBatch) {
        (new ShippingNoteReviewService($db, $external, $entity))->mutate($draftBatch, 29, 'save_translation', 'Nansha KOTA 0085W RMB180.');
    }, 'External customer was allowed to mutate translations.');
    m041ExpectException(function () use ($db, $noRights, $entity, $draftBatch) {
        (new ShippingNoteReviewService($db, $noRights, $entity))->mutate($draftBatch, 29, 'save_translation', 'Nansha KOTA 0085W RMB180.');
    }, 'Internal user without both rights was allowed to mutate translations.');

    $service = new ShippingNoteReviewService($db, $staff, $entity);
    m041ExpectException(function () use ($service, $foreignBatch) {
        $service->mutate($foreignBatch, 29, 'save_translation', 'Nansha KOTA 0085W RMB180.');
    }, 'Cross-entity batch mutation was allowed.');
    m041ExpectException(function () use ($service, $draftBatch) {
        $service->mutate($draftBatch, 29, 'save_translation', '<b>Nansha</b> KOTA 0085W RMB180.');
    }, 'Markup translation was accepted.');
    m041ExpectException(function () use ($service, $draftBatch) {
        $service->mutate($draftBatch, 29, 'save_translation', 'Nansha KOTA 0086W RMB180.');
    }, 'Changed operational token was accepted.');

    $saved = $service->mutate($draftBatch, 29, 'save_translation', 'Nansha KOTA 0085W RMB180.');
    m041Assert((int) $saved['affected'] === 2 && $saved['status'] === 'approved', 'Grouped draft update did not report both rate rows.');
    $res = $db->query("SELECT COUNT(*) total,SUM(translation_status='approved') approved,SUM(notes_en='Nansha KOTA 0085W RMB180.') translated FROM ".MAIN_DB_PREFIX."chinaplanetsp_rate WHERE entity=".$entity." AND fk_batch=".$draftBatch." AND source_row=29");
    $row = $res ? $db->fetch_object($res) : null;
    m041Assert($row && (int) $row->total === 2 && (int) $row->approved === 2 && (int) $row->translated === 2, 'Grouped draft update was not atomic across the source row.');
    $res = $db->query("SELECT translation_status,public_notes FROM ".MAIN_DB_PREFIX."chinaplanetsp_rate WHERE rowid=".$unrelatedRate);
    $unrelated = $res ? $db->fetch_object($res) : null;
    m041Assert($unrelated && $unrelated->translation_status === 'failed' && $unrelated->public_notes === null, 'Mutation crossed into another source row.');

    $groups = $service->listGroups($draftBatch, 0, 1);
    m041Assert((int) $groups['total'] === 2 && count($groups['groups']) === 1 && (int) $groups['pages'] === 2, 'Review-group pagination is not deterministic.');

    m041Assert($db->query("UPDATE ".MAIN_DB_PREFIX."chinaplanetsp_batch SET status=2 WHERE rowid=".$draftBatch." AND entity=".$entity), 'Could not publish synthetic batch.');
    $corrected = $service->mutate($draftBatch, 29, 'save_translation', 'Nansha port KOTA 0085W RMB180.');
    m041Assert((int) $corrected['affected'] === 2, 'Published correction did not update its source-row group.');
    m041ExpectException(function () use ($service, $draftBatch) {
        $service->mutate($draftBatch, 29, 'reset_translation', '');
    }, 'Published translation was returned to an unpublished review state.');

    $res = $db->query("SELECT COUNT(*) total,SUM(public_notes='Nansha port KOTA 0085W RMB180.') corrected FROM ".MAIN_DB_PREFIX."chinaplanetsp_rate WHERE rowid IN (".$rateOne.",".$rateTwo.")");
    $row = $res ? $db->fetch_object($res) : null;
    m041Assert($row && (int) $row->total === 2 && (int) $row->corrected === 2, 'Published correction was not persisted for the complete source row.');

    echo "M041_RUNTIME=PASS permissions=PASS entity_isolation=PASS grouped_mutation=PASS published_edit=PASS\n";
} finally {
    if (!empty($batchIds)) {
        $idList = implode(',', array_map('intval', $batchIds));
        $db->query("DELETE FROM ".MAIN_DB_PREFIX."chinaplanetsp_rate WHERE fk_batch IN (".$idList.") AND entity IN (".$entity.",".$foreignEntity.")");
        $db->query("DELETE FROM ".MAIN_DB_PREFIX."chinaplanetsp_batch WHERE rowid IN (".$idList.") AND entity IN (".$entity.",".$foreignEntity.")");
    }
}
