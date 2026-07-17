<?php
define('NOLOGIN', 1);
define('NOREQUIREMENU', 1);
define('NOREQUIREHTML', 1);
define('NOCSRFCHECK', 1);
$_SERVER['DOCUMENT_ROOT'] = '/Users/mac/Documents/dev/dolibarr/dolibarr-Dev/dolibarr-23.0.3/htdocs';
$_SERVER['SERVER_NAME'] = '127.0.0.1';
$_SERVER['SERVER_PORT'] = '8088';
require $_SERVER['DOCUMENT_ROOT'].'/master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/custom/chinaplanetshippingprices/lib/main.lib.php';
require_once DOL_DOCUMENT_ROOT.'/custom/chinaplanetshippingprices/class/ShippingSopService.class.php';

function sopAssert($condition, $message) { if (!$condition) throw new RuntimeException($message); }
class SopTestUser {
    public $id; public $socid; public $admin; private $rights;
    public function __construct($id, $socid, $admin, array $rights) { $this->id=$id; $this->socid=$socid; $this->admin=$admin; $this->rights=$rights; }
    public function hasRight($module, $right) { return $module === 'chinaplanetshippingprices' && in_array($right, $this->rights, true); }
}

$admin = new SopTestUser(1, 0, 1, array());
$internal = new SopTestUser(2, 0, 0, array('sopread'));
$external = new SopTestUser(3, 99, 0, array('sopread','sopwrite','soppublish','sopdelete','sopcategory'));
sopAssert(chinaplanetsp_can_sop($admin, 'sopdelete'), 'Admin should pass the internal SOP gate.');
sopAssert(chinaplanetsp_can_sop($internal, 'sopread'), 'Authorized internal user should read SOPs.');
sopAssert(!chinaplanetsp_can_sop($internal, 'sopwrite'), 'Read-only internal user should not edit SOPs.');
sopAssert(!chinaplanetsp_can_sop($external, 'sopread'), 'External users must fail SOP access even when a right is assigned.');

$tables = array('chinaplanetsp_sop','chinaplanetsp_sop_revision','chinaplanetsp_sop_category');
foreach ($tables as $table) {
    $res = $db->query("SHOW TABLES LIKE '".MAIN_DB_PREFIX.$table."'");
    sopAssert($res && $db->num_rows($res) === 1, 'Missing SOP table '.$table);
}
$rights = $db->query("SELECT id FROM ".MAIN_DB_PREFIX."rights_def WHERE id IN (500406,500407,500408,500409,500410)");
sopAssert($rights && $db->num_rows($rights) === 5, 'The five SOP permissions were not registered.');
$leaks = $db->query("SELECT COUNT(*) total FROM ".MAIN_DB_PREFIX."user_rights ur JOIN ".MAIN_DB_PREFIX."user u ON u.rowid=ur.fk_user WHERE ur.fk_id IN (500406,500407,500408,500409,500410) AND u.fk_soc>0");
$leakCount = $leaks ? $db->fetch_object($leaks) : null;
sopAssert($leakCount && (int)$leakCount->total === 0, 'An external user has an internal SOP permission.');

$service = new ShippingSopService($db);
$stale = $db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."chinaplanetsp_sop WHERE entity=".(int)$conf->entity." AND ref LIKE 'UI-SOP-TEST-%'");
while ($stale && ($staleSop = $db->fetch_object($stale))) {
    $db->query("DELETE FROM ".MAIN_DB_PREFIX."chinaplanetsp_sop_revision WHERE entity=".(int)$conf->entity." AND fk_sop=".(int)$staleSop->rowid);
    $db->query("DELETE FROM ".MAIN_DB_PREFIX."chinaplanetsp_sop WHERE entity=".(int)$conf->entity." AND rowid=".(int)$staleSop->rowid);
}
$categoryResult = $db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."chinaplanetsp_sop_category WHERE entity=".(int)$conf->entity." AND code='cargox_egypt'");
$category = $categoryResult ? $db->fetch_object($categoryResult) : null;
sopAssert($category, 'Suggested default SOP categories were not seeded.');
$ref = 'TEST-SOP-'.date('YmdHis').'-'.mt_rand(100,999);
$base = array(
    'ref'=>$ref, 'title'=>'CargoX handling for Egypt', 'title_ar'=>'إجراء كارجو إكس لمصر',
    'fk_category'=>(int)$category->rowid, 'country_code'=>'EG', 'pol'=>'', 'pod'=>'SOKHNA',
    'shipping_line'=>'', 'service_type'=>'all', 'container_type'=>'', 'incoterm'=>'',
    'cargo_type'=>'', 'document_codes'=>'cargox', 'applicability'=>'Egypt shipments requiring CargoX.',
    'required_information'=>"Customer tax number\nShipment reference", 'procedure_steps'=>"Verify customer data\nUpload documents\nRecord CargoX number",
    'required_documents'=>"Commercial invoice\nPacking list", 'warnings'=>'Do not publish an incorrect ACID number.',
    'escalation_instructions'=>'Escalate mismatched ACID data to the shipping supervisor.', 'internal_notes'=>'Integration test record.',
    'responsible_department'=>'Shipping Operations', 'effective_date'=>date('Y-m-d'), 'change_summary'=>'Initial test revision',
);
$id = 0;
try {
    $id = $service->create($base, 1);
    sopAssert($id > 0, 'Draft SOP was not created.');
    sopAssert($service->get($id, false) === null, 'Draft SOP leaked into the active reader query.');
    $service->publish($id, 1);
    $published = $service->get($id, false);
    sopAssert($published && (int)$published['revision_no'] === 1 && (int)$published['revision_status'] === 1, 'Initial SOP revision was not published.');

    $revision2 = $base; $revision2['title'] = 'CargoX handling for Egypt — updated'; $revision2['change_summary'] = 'Updated operating steps';
    sopAssert($service->saveDraft($id, $revision2, 1) === 2, 'Editing an active SOP did not create revision 2.');
    $readerCopy = $service->get($id, false);
    $editorCopy = $service->get($id, true);
    sopAssert((int)$readerCopy['revision_no'] === 1, 'Published revision was silently overwritten by a draft edit.');
    sopAssert((int)$editorCopy['revision_no'] === 2 && (int)$editorCopy['revision_status'] === 0, 'Editor did not receive the latest draft revision.');
    $service->publish($id, 1);
    $revisions = $service->getRevisions($id);
    sopAssert(count($revisions) === 2 && (int)$revisions[0]['revision_status'] === 1 && (int)$revisions[1]['revision_status'] === 9, 'Publication did not preserve the superseded revision.');

    sopAssert($service->restoreRevision($id, 1, 1, 'Restored for integration test') === 3, 'Restoring an old revision did not create revision 3.');
    $restored = $service->get($id, false);
    sopAssert((int)$restored['revision_no'] === 3 && $restored['title'] === $base['title'], 'Restored revision was not published as a new copy.');
    $suggestions = $service->suggest(array('country_code'=>'EG','pod'=>'SOKHNA','documents'=>array('cargox'),'incoterm'=>'FOB','cargo_type'=>'normal'), 10);
    sopAssert(in_array($id, array_map(function($row){return (int)$row['sop_id'];}, $suggestions), true), 'CargoX context did not suggest the matching Egypt SOP.');
    $service->archive($id, 1); sopAssert($service->get($id, false) === null, 'Archived SOP remained visible to active readers.');
    $service->restore($id, 1); sopAssert($service->get($id, false) !== null, 'Archived SOP could not be restored.');
    $service->delete($id, 1); sopAssert($service->get($id, false) === null, 'Deleted SOP remained visible to readers.');
    print "OK SOP permissions, entity isolation, revisions, contextual suggestions, archive/restore and soft delete\n";
} finally {
    if ($id > 0) {
        $db->query("DELETE FROM ".MAIN_DB_PREFIX."chinaplanetsp_sop_revision WHERE entity=".(int)$conf->entity." AND fk_sop=".(int)$id);
        $db->query("DELETE FROM ".MAIN_DB_PREFIX."chinaplanetsp_sop WHERE entity=".(int)$conf->entity." AND rowid=".(int)$id);
    }
}
