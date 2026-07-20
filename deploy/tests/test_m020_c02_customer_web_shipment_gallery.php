<?php
define('CPCC_PORTAL_GALLERY_TESTING', true);
putenv('CPCC_PORTAL_GALLERY_TEST_SECRET=m020-c02-local-gallery-test-secret-0123456789abcdef');

$root = dirname(__DIR__, 2);
$failures = array();

function m020C02Assert($condition, $message)
{
    global $failures;
    if (!$condition) $failures[] = $message;
}

$portalSource = file_get_contents($root.'/custom/chinaplanetcustomercenter/lib/portal.lib.php');
$downloadSource = file_get_contents($root.'/custom/chinaplanetcustomercenter/download.php');
$documentsSource = file_get_contents($root.'/custom/chinaplanetcustomercenter/documents.php');
$cardSource = file_get_contents($root.'/custom/chinaplanetlogistics/card.php');

m020C02Assert(strpos($portalSource, 'cpcc_portal_customer_gallery_items') !== false, 'Customer gallery list helper exists');
m020C02Assert(strpos($portalSource, 'cpcc_portal_resolve_customer_gallery_content') !== false, 'Protected content resolver exists');
m020C02Assert(strpos($portalSource, 'INNER JOIN ".MAIN_DB_PREFIX."chinaplanetlogistics') !== false, 'Publication queries join shipment ownership');
m020C02Assert(strpos($portalSource, 'p.entity=') !== false && strpos($portalSource, 'p.fk_soc=') !== false, 'Publication queries enforce entity and authenticated customer');
m020C02Assert(strpos($portalSource, "hash_file('sha256'") !== false && strpos($portalSource, 'hash_equals') !== false, 'Content resolver rechecks the immutable hash');
m020C02Assert(strpos($portalSource, 'cpcc_portal_validate_shipment_upload') !== false, 'Content resolver rechecks filename, size and MIME policy');
m020C02Assert(strpos($downloadSource, "\$type === 'gallery'") !== false && strpos($downloadSource, "GETPOST('publication'") !== false, 'Protected route accepts an opaque publication token');
m020C02Assert(strpos($downloadSource, "Cache-Control: private, no-store") !== false && strpos($downloadSource, 'X-Content-Type-Options: nosniff') !== false, 'Protected route emits private and nosniff headers');
m020C02Assert(strpos($downloadSource, "if (\$type === 'shipment')") !== false && strpos($downloadSource, "if (!empty(\$scope['is_external'])) accessforbidden()") !== false, 'Legacy raw-path downloads remain internal-only');
m020C02Assert(strpos($documentsSource, 'CPLShipmentFilesPageTitle') !== false && strpos($documentsSource, 'cpcc_portal_customer_shipment_file_groups') !== false, 'Customer Documents page projects Shipment Documents and Gallery from one allowlist');
m020C02Assert(strpos($cardSource, 'cpcc_portal_customer_shipment_files_html') !== false && strpos($cardSource, '<!--CPL_SHIPMENT_FILES-->') !== false, 'Shipment card inserts documents and gallery directly after the timeline');
m020C02Assert(strpos($cardSource, 'cpcc_portal_staff_shipment_files_html') !== false, 'Shipment view and edit modes expose the shared staff file manager');

m020C02Assert(strpos($portalSource, 'function cpcc_portal_customer_shipment_file_groups') !== false, 'Customer publication grouping helper exists');

$documentRoot = getenv('M020_C02_DOL_DOCUMENT_ROOT');
if (is_string($documentRoot) && $documentRoot !== '') {
    define('NOLOGIN', 1);
    define('NOREQUIREMENU', 1);
    define('NOREQUIREHTML', 1);
    define('NOREQUIREAJAX', 1);
    define('NOCSRFCHECK', 1);
    $_SERVER['DOCUMENT_ROOT'] = rtrim($documentRoot, '/');
    $_SERVER['SERVER_NAME'] = 'localhost';
    $_SERVER['SERVER_PORT'] = '80';
    require rtrim($documentRoot, '/').'/master.inc.php';
    require_once $root.'/custom/chinaplanetcustomercenter/lib/portal.lib.php';
    global $db, $conf;

    $grouped = cpcc_portal_customer_shipment_file_groups(array(
        (object) array('item_kind' => 'document'),
        (object) array('item_kind' => 'image'),
        (object) array('item_kind' => 'video'),
    ));
    m020C02Assert(count($grouped['documents']) === 1 && count($grouped['gallery']) === 2, 'Documents and visual media are rendered in separate customer sections');

    $userRows = array();
    foreach (array('A' => 'M017_SYNTH_E1_A', 'B' => 'M017_SYNTH_E1_B') as $key => $refExt) {
        $result = $db->query("SELECT rowid,entity,fk_soc FROM ".MAIN_DB_PREFIX."user WHERE ref_ext='".$db->escape($refExt)."' LIMIT 1");
        $row = $result ? $db->fetch_object($result) : null;
        m020C02Assert($row !== null, 'Synthetic customer context exists: '.$key);
        if ($row) $userRows[$key] = $row;
    }

    if (count($userRows) === 2) {
        $originalEntity = isset($conf->entity) ? $conf->entity : 1;
        $conf->entity = (int) $userRows['A']->entity;
        $shipmentResult = $db->query("SELECT rowid,ref,entity,fk_soc FROM ".MAIN_DB_PREFIX."chinaplanetlogistics WHERE entity=".(int) $userRows['A']->entity." AND fk_soc=".(int) $userRows['A']->fk_soc." ORDER BY rowid ASC LIMIT 1");
        $shipmentRow = $shipmentResult ? $db->fetch_object($shipmentResult) : null;
        m020C02Assert($shipmentRow !== null, 'Synthetic owned shipment exists');

        if ($shipmentRow) {
            $shipment = (object) array('id' => (int) $shipmentRow->rowid, 'rowid' => (int) $shipmentRow->rowid, 'ref' => (string) $shipmentRow->ref, 'entity' => (int) $shipmentRow->entity, 'fk_soc' => (int) $shipmentRow->fk_soc);
            $staff = (object) array('id' => (int) $userRows['A']->rowid);
            $customerA = (object) array('id' => (int) $userRows['A']->rowid, 'socid' => (int) $userRows['A']->fk_soc, 'admin' => 0);
            $customerB = (object) array('id' => (int) $userRows['B']->rowid, 'socid' => (int) $userRows['B']->fk_soc, 'admin' => 0);
            $docRoot = cpcc_portal_shipment_doc_root($shipment->id);
            if (!is_dir($docRoot)) dol_mkdir($docRoot);
            $filename = 'M020-C02-SYNTHETIC.png';
            $path = $docRoot.'/'.$filename;
            $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
            file_put_contents($path, $bytes);
            @chmod($path, 0640);

            m020C02Assert(cpcc_portal_customer_gallery_items($db, $customerA, $shipment) === array(), 'Internal file is absent before explicit publication');
            $published = cpcc_portal_gallery_publish($db, $shipment, $staff, 'ship-'.$shipment->id.'/'.$filename);
            m020C02Assert(!empty($published['ok']), 'Synthetic image can be explicitly published');

            $itemsA = cpcc_portal_customer_gallery_items($db, $customerA, $shipment);
            $itemsB = cpcc_portal_customer_gallery_items($db, $customerB, null);
            $item = !empty($itemsA) ? $itemsA[0] : null;
            m020C02Assert($item !== null && $item->original_name === $filename && $item->item_kind === 'image', 'Owning customer receives published metadata');
            m020C02Assert($item !== null && isset($item->public_token) && preg_match('/^gal_[A-Za-z0-9_-]{43}$/', $item->public_token), 'Browser receives an opaque one-way publication token');
            m020C02Assert($item !== null && !isset($item->rowid) && !isset($item->storage_key), 'Browser metadata excludes database ID and storage key');
            m020C02Assert(empty($itemsB), 'Second customer cannot list the publication');

            if ($item) {
                $contentA = cpcc_portal_resolve_customer_gallery_content($db, $customerA, $item->public_token);
                m020C02Assert($contentA !== null && $contentA['size'] === strlen($bytes) && $contentA['mime'] === 'image/png', 'Owning customer resolves the exact protected image');
                m020C02Assert(cpcc_portal_resolve_customer_gallery_content($db, $customerB, $item->public_token) === null, 'Cross-customer token reuse fails closed');
                m020C02Assert(cpcc_portal_resolve_customer_gallery_content($db, $customerA, 'gal_'.str_repeat('A', 43)) === null, 'Unknown opaque token returns no record');
                file_put_contents($path, $bytes.'tampered');
                m020C02Assert(cpcc_portal_resolve_customer_gallery_content($db, $customerA, $item->public_token) === null, 'Changed file fails size/hash validation');
                file_put_contents($path, $bytes);
            }

            $unpublished = !empty($published['id']) ? cpcc_portal_gallery_unpublish($db, $shipment, $staff, $published['id']) : array('ok' => false);
            m020C02Assert(!empty($unpublished['ok']), 'Publication can be revoked');
            m020C02Assert(is_file($path), 'Unpublish keeps the internal source file');
            m020C02Assert(cpcc_portal_customer_gallery_items($db, $customerA, $shipment) === array(), 'Unpublish removes customer visibility immediately');
            if ($item) m020C02Assert(cpcc_portal_resolve_customer_gallery_content($db, $customerA, $item->public_token) === null, 'Unpublished token is blocked immediately');

            $db->query("DELETE FROM ".MAIN_DB_PREFIX."cpl_shipment_public_document WHERE entity=".(int) $shipment->entity." AND fk_shipment=".(int) $shipment->id." AND storage_key='".$db->escape('ship-'.$shipment->id.'/'.$filename)."'");
            @unlink($path);
        }
        $conf->entity = $originalEntity;
    }
}

if ($failures) {
    fwrite(STDERR, "M020_C02_CUSTOMER_WEB_GALLERY=FAIL\n- ".implode("\n- ", $failures)."\n");
    exit(1);
}
echo 'M020_C02_CUSTOMER_WEB_GALLERY=PASS'.($documentRoot ? ' DB_INTEGRATION=PASS' : '')."\n";
