<?php
/** Synthetic, database-free acceptance checks for M003. */

function m003Assert($condition, $message)
{
    if (!$condition) throw new RuntimeException('FAIL '.$message);
    print 'PASS '.$message.PHP_EOL;
}

function dol_sanitizeFileName($value)
{
    return preg_replace('/[^A-Za-z0-9._ -]/', '_', (string) $value);
}

class M003User
{
    public $id = 0;
    public $admin = 0;
    public $socid = 0;
    private $rights = array();

    public function __construct($id, $socid = 0, array $rights = array(), $admin = 0)
    {
        $this->id = $id;
        $this->socid = $socid;
        $this->rights = $rights;
        $this->admin = $admin;
    }

    public function hasRight($module, $right)
    {
        return !empty($this->rights[$module][$right]);
    }
}

class M003Db
{
    public $lastSql = '';

    public function query($sql)
    {
        $this->lastSql = $sql;
        return true;
    }

    public function fetch_object($result)
    {
        if (strpos($this->lastSql, 'entity=1') === false) return null;
        if (strpos($this->lastSql, 'fk_soc=10') !== false || strpos($this->lastSql, 'fk_soc=') === false) {
            return (object) array('rowid' => 101, 'ref' => 'SYNTHETIC-A', 'fk_soc' => 10, 'entity' => 1);
        }
        return null;
    }
}

function m003RemoveTree($path)
{
    if (!is_dir($path)) return;
    foreach (scandir($path) as $name) {
        if ($name === '.' || $name === '..') continue;
        $child = $path.DIRECTORY_SEPARATOR.$name;
        if (is_dir($child) && !is_link($child)) m003RemoveTree($child);
        else unlink($child);
    }
    rmdir($path);
}

function m003SyntheticProjectJoin(array $shipment, array $project)
{
    if ((int) $project['rowid'] !== (int) $shipment['fk_project']
        || (int) $project['entity'] !== (int) $shipment['entity']
        || (int) $project['fk_soc'] !== (int) $shipment['fk_soc']) {
        return array('project_ref' => null, 'project_title' => null);
    }

    return array('project_ref' => $project['ref'], 'project_title' => $project['title']);
}

$repo = dirname(__DIR__, 2);
$tmpRoot = sys_get_temp_dir().'/m003-'.bin2hex(random_bytes(6));
define('DOL_DATA_ROOT', $tmpRoot);
define('MAIN_DB_PREFIX', 'llx_');
$conf = (object) array('entity' => 1);

require_once $repo.'/custom/chinaplanetcustomercenter/lib/portal.lib.php';

try {
    $anonymous = new M003User(0);
    $customerA = new M003User(11, 10, array('chinaplanetcustomercenter' => array('read' => 1)));
    $customerB = new M003User(12, 20, array('chinaplanetcustomercenter' => array('read' => 1)));
    $internalNoRead = new M003User(21);
    $internalRead = new M003User(22, 0, array('chinaplanetlogistics' => array('read' => 1)));
    $writeNoDelete = new M003User(23, 0, array('chinaplanetlogistics' => array('read' => 1, 'write' => 1)));
    $deleteUser = new M003User(24, 0, array('chinaplanetlogistics' => array('read' => 1, 'delete' => 1)));

    $readRights = array(array('chinaplanetlogistics', 'read'), array('chinaplanetcustomercenter', 'read'));
    m003Assert(!cpcc_portal_has_any_right($anonymous, $readRights), 'anonymous has no portal read access');
    m003Assert(cpcc_portal_has_any_right($customerA, $readRights), 'Customer A has authenticated read access');
    m003Assert(!cpcc_portal_has_any_right($internalNoRead, $readRights), 'internal user without module read is denied');
    m003Assert(cpcc_portal_has_any_right($internalRead, $readRights), 'internal user with module read is allowed');
    m003Assert(!cpcc_portal_has_any_right($writeNoDelete, array(array('chinaplanetlogistics', 'delete'))), 'write permission alone does not grant delete');
    m003Assert(cpcc_portal_has_any_right($deleteUser, array(array('chinaplanetlogistics', 'delete'))), 'dedicated delete permission grants delete');

    $db = new M003Db();
    $own = cpcc_portal_authorized_shipment($db, $customerA, 101, 20);
    m003Assert($own !== null && strpos($db->lastSql, 'entity=1') !== false, 'Customer A shipment query is entity scoped');
    m003Assert(strpos($db->lastSql, 'fk_soc=10') !== false && strpos($db->lastSql, 'fk_soc=20') === false, 'forged Customer B scope is ignored in favor of authenticated Customer A');
    $other = cpcc_portal_authorized_shipment($db, $customerB, 101, 10);
    m003Assert($other === null && strpos($db->lastSql, 'fk_soc=20') !== false, 'Customer B cannot resolve Customer A synthetic shipment');

    $shipmentA = array('fk_project' => 501, 'entity' => 1, 'fk_soc' => 10);
    $ownedProject = array('rowid' => 501, 'entity' => 1, 'fk_soc' => 10, 'ref' => 'PROJECT-A', 'title' => 'Customer A Project');
    $otherCustomerProject = array('rowid' => 501, 'entity' => 1, 'fk_soc' => 20, 'ref' => 'PROJECT-B', 'title' => 'Customer B Project');
    $otherEntityProject = array('rowid' => 501, 'entity' => 2, 'fk_soc' => 10, 'ref' => 'PROJECT-E2', 'title' => 'Other Entity Project');
    $ownedProjection = m003SyntheticProjectJoin($shipmentA, $ownedProject);
    $otherCustomerProjection = m003SyntheticProjectJoin($shipmentA, $otherCustomerProject);
    $otherEntityProjection = m003SyntheticProjectJoin($shipmentA, $otherEntityProject);
    m003Assert($ownedProjection['project_ref'] === 'PROJECT-A' && $ownedProjection['project_title'] === 'Customer A Project', 'correctly owned project remains visible on the shipment');
    m003Assert($otherCustomerProjection['project_ref'] === null && $otherCustomerProjection['project_title'] === null, 'mismatched-customer project contributes no project fields');
    m003Assert($otherEntityProjection['project_ref'] === null && $otherEntityProjection['project_title'] === null, 'mismatched-entity project contributes no project fields');

    mkdir($tmpRoot.'/chinaplanetlogistics/ship-1', 0700, true);
    mkdir($tmpRoot.'/chinaplanetlogistics/ship-1-other', 0700, true);
    file_put_contents($tmpRoot.'/chinaplanetlogistics/ship-1/report.pdf', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF\n");
    file_put_contents($tmpRoot.'/chinaplanetlogistics/ship-1-other/secret.pdf', "%PDF-1.4\n%%EOF\n");
    file_put_contents($tmpRoot.'/mismatch.pdf', "plain text, not a pdf\n");

    $resolved = cpcc_portal_resolve_shipment_doc_path(1, '', 'ship-1/report.pdf');
    m003Assert($resolved !== '', 'authorized shipment file resolves inside its canonical directory');
    m003Assert(cpcc_portal_resolve_shipment_doc_path(1, '', 'ship-1/../ship-1-other/secret.pdf') === '', 'directory traversal into sibling shipment is rejected');
    m003Assert(cpcc_portal_resolve_shipment_doc_path(1, '', 'ship-1/%2e%2e/ship-1-other/secret.pdf') === '', 'encoded traversal is rejected');
    m003Assert(!cpcc_portal_path_is_within($tmpRoot.'/chinaplanetlogistics/ship-1-other/secret.pdf', $tmpRoot.'/chinaplanetlogistics/ship-1'), 'sibling-prefix path is rejected');

    m003Assert(cpcc_portal_validate_shipment_filename('invoice.php.pdf') === '', 'dangerous double extension is rejected');
    m003Assert(cpcc_portal_validate_shipment_filename('invoice.pdf.exe') === '', 'disallowed executable extension is rejected');
    m003Assert(cpcc_portal_validate_shipment_filename('invoice.pdf') === 'invoice.pdf', 'allowed document filename is accepted');

    $validPdf = cpcc_portal_validate_shipment_upload('report.pdf', $tmpRoot.'/chinaplanetlogistics/ship-1/report.pdf', filesize($tmpRoot.'/chinaplanetlogistics/ship-1/report.pdf'), 1024);
    m003Assert(!empty($validPdf['ok']), 'valid PDF content passes MIME and size policy');
    $mismatch = cpcc_portal_validate_shipment_upload('mismatch.pdf', $tmpRoot.'/mismatch.pdf', filesize($tmpRoot.'/mismatch.pdf'), 1024);
    m003Assert(empty($mismatch['ok']), 'extension and MIME mismatch is rejected');
    $oversized = cpcc_portal_validate_shipment_upload('report.pdf', $tmpRoot.'/chinaplanetlogistics/ship-1/report.pdf', filesize($tmpRoot.'/chinaplanetlogistics/ship-1/report.pdf'), 2);
    m003Assert(empty($oversized['ok']), 'oversized upload is rejected');

    $track = file_get_contents($repo.'/custom/chinaplanetlogistics/public/track.php');
    $card = file_get_contents($repo.'/custom/chinaplanetlogistics/card.php');
    $documents = file_get_contents($repo.'/custom/chinaplanetcustomercenter/documents.php');
    $download = file_get_contents($repo.'/custom/chinaplanetcustomercenter/download.php');
    $object = file_get_contents($repo.'/custom/chinaplanetlogistics/class/chinaplanetlogistics.class.php');
    $list = file_get_contents($repo.'/custom/chinaplanetlogistics/list.php');

    m003Assert(strpos($track, "define(\"NOLOGIN\"") === false && strpos($track, 'cpcc_portal_require_access') !== false, 'legacy tracking route requires Dolibarr authentication and rights');
    m003Assert(strpos($card, 'if (empty($user->id) || !$canread) accessforbidden();') !== false, 'direct shipment card denies internal users without read permission');
    m003Assert(strpos($card, "define('CSRFCHECK_WITH_TOKEN', 1)") !== false && strpos($card, '$is_post') !== false, 'shipment state changes require POST and framework CSRF');
    m003Assert(strpos($card, "array('create', 'edit', 'confirm_delete')") !== false && strpos($card, 'csrfRequestMethod') !== false && strpos($card, "!== 'GET'") !== false, 'shipment read-only GET forms remain reachable while mutations require framework CSRF');
    m003Assert(strpos($card, "hasRight('chinaplanetlogistics', 'delete')") !== false, 'shipment deletion checks dedicated delete right');
    m003Assert(strpos($documents, "define('CSRFCHECK_WITH_TOKEN', 1)") !== false && preg_match("/in_array\\(\\\$action, array\\(\s*'upload',\s*'rename',\s*'deletefile'[^)]*\\), true\\) && !\\\$is_post/", $documents) === 1, 'document mutations require POST and framework CSRF');
    m003Assert(substr_count($documents, 't.entity=') >= 2 && strpos($download, 'cpcc_portal_authorized_shipment') !== false, 'document list and direct download enforce entity and shipment authorization');
    m003Assert(strpos($download, "if (!empty(\$scope['is_external'])) accessforbidden();") !== false, 'legacy files without customer-visibility evidence are denied externally');
    m003Assert(substr_count($object, 'AND entity = ".$this->activeEntity()') >= 2 && strpos($object, 'WHERE entity=".$this->activeEntity()') !== false, 'Logistics fetch/update/delete include active entity');
    m003Assert(strpos($list, 'p.rowid=t.fk_project AND p.entity=t.entity AND p.fk_soc=t.fk_soc') !== false, 'Logistics list project join requires matching project id, entity and customer');

    print 'M003 synthetic portal hardening tests passed'.PHP_EOL;
} finally {
    m003RemoveTree($tmpRoot);
}
