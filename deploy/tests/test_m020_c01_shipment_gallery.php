<?php
define('CPCC_MOBILE_TESTING', true);
putenv('CPCC_MOBILE_TEST_PEPPER=m020-c01-local-test-pepper-0123456789abcdef');

$root = dirname(__DIR__, 2);
$failures = array();

function m020C01GalleryAssert($condition, $message)
{
	global $failures;
	if (!$condition) $failures[] = $message;
}

function m020C01GalleryThrows404($callback)
{
	try {
		$callback();
	} catch (\Throwable $exception) {
		return (int) $exception->getCode() === 404;
	}
	return false;
}

$portalSource = file_get_contents($root.'/custom/chinaplanetcustomercenter/lib/portal.lib.php');
$apiSource = file_get_contents($root.'/custom/chinaplanetcustomercenter/lib/mobile_api_data.lib.php');
$cardSource = file_get_contents($root.'/custom/chinaplanetlogistics/card.php');
$openapi = json_decode(file_get_contents($root.'/docs/API/mobile-v1.openapi.json'), true);
m020C01GalleryAssert(strpos((string) $portalSource, 'cpcc_portal_gallery_publish') !== false, 'Explicit publication helper exists');
m020C01GalleryAssert(strpos((string) $portalSource, 'cpcc_portal_gallery_unpublish') !== false, 'Reversible unpublish helper exists');
m020C01GalleryAssert(strpos((string) $portalSource, "hash_file('sha256'") !== false, 'Publication fingerprints the file');
m020C01GalleryAssert(strpos((string) $apiSource, "'shipment_gallery_item'") !== false, 'Gallery uses a separate opaque-ID resource');
m020C01GalleryAssert(strpos((string) $apiSource, 'hash_equals') !== false, 'Content streaming rechecks the stored hash');
m020C01GalleryAssert(strpos((string) $cardSource, "\$action === 'publish_gallery'") !== false && strpos((string) $cardSource, "\$action === 'unpublish_gallery'") !== false, 'Staff card exposes publish and unpublish POST actions');
m020C01GalleryAssert(strpos((string) $cardSource, "method=\"POST\"") !== false && strpos((string) $cardSource, "name=\"token\"") !== false, 'Publication controls remain POST and CSRF-token protected');
m020C01GalleryAssert(in_array('shipment_gallery', $openapi['components']['schemas']['ShipmentDetail']['required'], true), 'Shipment detail contract requires the gallery projection');
m020C01GalleryAssert(in_array('shipment_gallery', $openapi['components']['schemas']['Document']['properties']['source_type']['enum'], true), 'Document contract identifies Shipment Gallery items');
$documentParameters = $openapi['paths']['/documents']['get']['parameters'];
$hasShipmentScope = false;
foreach ($documentParameters as $parameter) if (isset($parameter['name']) && $parameter['name'] === 'scope' && in_array('shipment', $parameter['schema']['enum'], true)) $hasShipmentScope = true;
m020C01GalleryAssert($hasShipmentScope, 'Documents contract exposes only the frozen shipment scope');

$documentRoot = getenv('M020_C01_DOL_DOCUMENT_ROOT');
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
	require_once DOL_DOCUMENT_ROOT.'/includes/restler/framework/Luracast/Restler/AutoLoader.php';
	$restlerLoader = \Luracast\Restler\AutoLoader::instance();
	spl_autoload_register($restlerLoader);
	require_once DOL_DOCUMENT_ROOT.'/api/class/api.class.php';
	require_once $root.'/custom/chinaplanetcustomercenter/lib/mobile_api_data.lib.php';
	global $db;

	$userRows = array();
	foreach (array('A' => 'M017_SYNTH_E1_A', 'B' => 'M017_SYNTH_E1_B') as $key => $refExt) {
		$result = $db->query("SELECT rowid,entity,fk_soc FROM ".MAIN_DB_PREFIX."user WHERE ref_ext='".$db->escape($refExt)."' LIMIT 1");
		$row = $result ? $db->fetch_object($result) : null;
		m020C01GalleryAssert($row !== null, 'Synthetic customer context exists: '.$key);
		if ($row) $userRows[$key] = $row;
	}

	if (count($userRows) === 2) {
		$shipmentResult = $db->query("SELECT rowid,ref,entity,fk_soc FROM ".MAIN_DB_PREFIX."chinaplanetlogistics WHERE entity=".(int) $userRows['A']->entity." AND fk_soc=".(int) $userRows['A']->fk_soc." ORDER BY rowid ASC LIMIT 1");
		$shipmentRow = $shipmentResult ? $db->fetch_object($shipmentResult) : null;
		m020C01GalleryAssert($shipmentRow !== null, 'Synthetic owned shipment exists');

		if ($shipmentRow) {
			$shipment = (object) array('id' => (int) $shipmentRow->rowid, 'rowid' => (int) $shipmentRow->rowid, 'ref' => (string) $shipmentRow->ref, 'entity' => (int) $shipmentRow->entity, 'fk_soc' => (int) $shipmentRow->fk_soc);
			$staff = (object) array('id' => (int) $userRows['A']->rowid);
			$contextA = array('entity' => (int) $userRows['A']->entity, 'socid' => (int) $userRows['A']->fk_soc);
			$contextB = array('entity' => (int) $userRows['B']->entity, 'socid' => (int) $userRows['B']->fk_soc);
			$docRoot = cpcc_portal_shipment_doc_root($shipment->id);
			if (!is_dir($docRoot)) dol_mkdir($docRoot);
			$filename = 'M020-C01-SYNTHETIC.png';
			$path = $docRoot.'/'.$filename;
			$bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
			file_put_contents($path, $bytes);
			@chmod($path, 0640);

			$selectSynthetic = function ($items) use ($filename) {
				foreach ($items as $item) if ($item['source_type'] === 'shipment_gallery' && $item['name'] === $filename) return $item;
				return null;
			};
			$before = cpcc_mobile_shipment_gallery($db, $contextA, $shipment->id);
			m020C01GalleryAssert($selectSynthetic($before) === null, 'An internal shipment file is absent before explicit publication');

			$published = cpcc_portal_gallery_publish($db, $shipment, $staff, 'ship-'.$shipment->id.'/'.$filename);
			m020C01GalleryAssert(!empty($published['ok']), 'Synthetic image can be published explicitly');
			$galleryA = cpcc_mobile_shipment_gallery($db, $contextA, $shipment->id);
			$syntheticItem = $selectSynthetic($galleryA);
			m020C01GalleryAssert($syntheticItem !== null && $syntheticItem['kind'] === 'image', 'Owning customer receives the published image');
			$publicId = $syntheticItem && !empty($syntheticItem['id']) ? $syntheticItem['id'] : '';
			$galleryB = cpcc_mobile_documents($db, $contextB, 100, '', '', 'shipment');
			m020C01GalleryAssert(empty(array_filter($galleryB['data'], function ($item) use ($publicId) { return $item['id'] === $publicId; })), 'Second customer list cannot see the publication');
			m020C01GalleryAssert($publicId !== '' && m020C01GalleryThrows404(function () use ($db, $contextB, $publicId) { cpcc_mobile_safe_file_record($db, $contextB, $publicId); }), 'Second customer receives a non-revealing 404 for the opaque content ID');

			if ($publicId !== '') {
				$file = cpcc_mobile_safe_file_record($db, $contextA, $publicId);
				m020C01GalleryAssert($file['size'] === strlen($bytes) && $file['mime_type'] === 'image/png', 'Owning customer resolves the exact protected image');
				file_put_contents($path, $bytes.'tampered');
				m020C01GalleryAssert(m020C01GalleryThrows404(function () use ($db, $contextA, $publicId) { cpcc_mobile_safe_file_record($db, $contextA, $publicId); }), 'Changed file fails closed on size/hash verification');
				file_put_contents($path, $bytes);
			}

			$unpublished = !empty($published['id']) ? cpcc_portal_gallery_unpublish($db, $shipment, $staff, $published['id']) : array('ok' => false);
			m020C01GalleryAssert(!empty($unpublished['ok']), 'Publication can be revoked without deleting the file');
			m020C01GalleryAssert(is_file($path), 'Unpublish keeps the internal shipment file');
			m020C01GalleryAssert($selectSynthetic(cpcc_mobile_shipment_gallery($db, $contextA, $shipment->id)) === null, 'Unpublish removes the item immediately from the owner gallery');
			if ($publicId !== '') m020C01GalleryAssert(m020C01GalleryThrows404(function () use ($db, $contextA, $publicId) { cpcc_mobile_safe_file_record($db, $contextA, $publicId); }), 'Unpublished content returns 404 to its former owner');

			$db->query("DELETE FROM ".MAIN_DB_PREFIX."cpl_shipment_public_document WHERE entity=".(int) $shipment->entity." AND fk_shipment=".(int) $shipment->id." AND storage_key='".$db->escape('ship-'.$shipment->id.'/'.$filename)."'");
			if ($publicId !== '') $db->query("DELETE FROM ".MAIN_DB_PREFIX."cpcc_mobile_public_id WHERE public_id='".$db->escape($publicId)."'");
			@unlink($path);
		}
	}
}

if ($failures) {
	fwrite(STDERR, "M020_C01_GALLERY=FAIL\n- ".implode("\n- ", $failures)."\n");
	exit(1);
}
echo 'M020_C01_GALLERY=PASS'.($documentRoot ? ' DB_INTEGRATION=PASS' : '')."\n";
