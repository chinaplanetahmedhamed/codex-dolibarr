<?php
define('CPCC_MOBILE_TESTING', true);
putenv('CPCC_MOBILE_TEST_PEPPER=m017-local-test-pepper-0123456789abcdef');
require_once dirname(__DIR__, 2).'/custom/chinaplanetcustomercenter/lib/mobile_api_data.lib.php';
$failures = array();
function m017_data_assert($condition, $message) { global $failures; if (!$condition) $failures[] = $message; }
function m017_data_throws_status($callback, $status)
{
	try { $callback(); } catch (\Throwable $exception) { return (int) $exception->getCode() === (int) $status; }
	return false;
}
function m017_schema_errors($value, $schema, $api, $path = '$')
{
	if (isset($schema['$ref'])) { $name = basename(str_replace('\\', '/', $schema['$ref'])); return m017_schema_errors($value, $api['components']['schemas'][$name], $api, $path); }
	if (isset($schema['oneOf'])) { foreach ($schema['oneOf'] as $candidate) if (!m017_schema_errors($value, $candidate, $api, $path)) return array(); return array($path.' does not match oneOf'); }
	$types = isset($schema['type']) ? (array) $schema['type'] : array();
	if ($value === null) return in_array('null', $types, true) ? array() : array($path.' is unexpectedly null');
	if (in_array('object', $types, true)) {
		if (!is_array($value) || array_values($value) === $value) return array($path.' is not an object');
		$errors = array(); foreach (isset($schema['required']) ? $schema['required'] : array() as $required) if (!array_key_exists($required, $value)) $errors[] = $path.'.'.$required.' is missing';
		$properties = isset($schema['properties']) ? $schema['properties'] : array(); foreach ($value as $key => $child) { if (!isset($properties[$key])) { if (isset($schema['additionalProperties']) && $schema['additionalProperties'] === false) $errors[] = $path.'.'.$key.' is not allowed'; continue; } $errors = array_merge($errors, m017_schema_errors($child, $properties[$key], $api, $path.'.'.$key)); }
		return $errors;
	}
	if (in_array('array', $types, true)) {
		if (!is_array($value)) return array($path.' is not an array'); $errors = array(); if (isset($schema['minItems']) && count($value) < $schema['minItems']) $errors[] = $path.' has too few items'; if (isset($schema['maxItems']) && count($value) > $schema['maxItems']) $errors[] = $path.' has too many items'; foreach ($value as $index => $child) $errors = array_merge($errors, m017_schema_errors($child, $schema['items'], $api, $path.'['.$index.']')); return $errors;
	}
	if (in_array('string', $types, true) && !is_string($value)) return array($path.' is not a string');
	if (in_array('integer', $types, true) && !is_int($value)) return array($path.' is not an integer');
	if (in_array('number', $types, true) && !is_int($value) && !is_float($value)) return array($path.' is not a number');
	if (in_array('boolean', $types, true) && !is_bool($value)) return array($path.' is not a boolean');
	if (isset($schema['const']) && $value !== $schema['const']) return array($path.' does not match const');
	if (isset($schema['enum']) && !in_array($value, $schema['enum'], true)) return array($path.' is outside enum');
	if (is_string($value) && isset($schema['minLength']) && mb_strlen($value, 'UTF-8') < $schema['minLength']) return array($path.' is too short');
	if (is_string($value) && isset($schema['maxLength']) && mb_strlen($value, 'UTF-8') > $schema['maxLength']) return array($path.' is too long');
	if (is_string($value) && isset($schema['pattern']) && !preg_match('/'.$schema['pattern'].'/', $value)) return array($path.' does not match pattern');
	if ((is_int($value) || is_float($value)) && isset($schema['minimum']) && $value < $schema['minimum']) return array($path.' is below minimum');
	if ((is_int($value) || is_float($value)) && isset($schema['maximum']) && $value > $schema['maximum']) return array($path.' is above maximum');
	return array();
}
$money = cpcc_mobile_money('120.50000000', 'RMB');
m017_data_assert($money === array('amount' => '120.5', 'currency' => 'CNY'), 'Money uses decimal strings and ISO currency normalization');
$status = cpcc_mobile_shipment_status(8);
m017_data_assert($status['code'] === 'loaded_export_customs' && $status['label']['ar'] !== '' && $status['label']['en'] !== '', 'Pre-sailing status is bilingual');
$transit = (object) array('event_type'=>'transshipment','actual_at'=>'2026-07-20 10:00:00','event_at'=>'2026-07-20 10:00:00','source_type'=>'carrier','note_public'=>'Singapore — Vessel change','reason_public'=>'Schedule');
$shipment = (object) array('fk_statut'=>2,'date_etd'=>'2026-07-18 00:00:00','date_atd'=>'2026-07-19 00:00:00','date_ata'=>null);
$timeline = cpcc_mobile_shipment_timeline($shipment, array($transit));
$codes = array_map(function ($item) { return $item['code']; }, $timeline);
m017_data_assert(in_array('transit', $codes, true), 'Shipment timeline inserts transit events');
$transitItem = $timeline[array_search('transit', $codes, true)];
m017_data_assert($transitItem['label']['ar'] === 'ترانزيت' && $transitItem['label']['en'] === 'Transit', 'Transit naming follows approved Arabic wording');
$inspectionLabel = cpcc_mobile_report_field_label('inspection_result');
m017_data_assert($inspectionLabel['ar'] === 'نتيجة الفحص' && $inspectionLabel['en'] === 'Inspection result', 'Report fields have genuine Arabic and English labels');
$context = array('entity'=>1,'socid'=>10);
$filter = cpcc_mobile_filter_hash('projects', array('updated_since'=>''));
$cursor = cpcc_mobile_cursor_encode(array('v'=>1,'resource'=>'projects','entity'=>1,'socid'=>10,'filter'=>$filter,'limit'=>25,'last'=>array('date'=>'2026-07-19 00:00:00','id'=>7)));
$decoded = cpcc_mobile_cursor_decode($cursor, 'projects', $context, $filter, 25);
m017_data_assert($decoded['id'] === 7, 'Opaque cursor round-trip is bound and encrypted');
m017_data_assert(strpos($cursor, 'projects') === false && strpos($cursor, '2026-07-19') === false, 'Opaque cursor does not expose its payload');

$documentRoot = getenv('M017_DOL_DOCUMENT_ROOT');
if (is_string($documentRoot) && $documentRoot !== '') {
	define('NOLOGIN', 1); define('NOREQUIREMENU', 1); define('NOREQUIREHTML', 1); define('NOREQUIREAJAX', 1); define('NOCSRFCHECK', 1);
	$_SERVER['DOCUMENT_ROOT'] = rtrim($documentRoot, '/'); $_SERVER['SERVER_NAME'] = 'localhost'; $_SERVER['SERVER_PORT'] = '80';
	require rtrim($documentRoot, '/').'/master.inc.php';
	require_once DOL_DOCUMENT_ROOT.'/includes/restler/framework/Luracast/Restler/AutoLoader.php';
	$restlerLoader = \Luracast\Restler\AutoLoader::instance(); spl_autoload_register($restlerLoader);
	require_once DOL_DOCUMENT_ROOT.'/api/class/api.class.php';
	global $db;
	$contexts = array();
	foreach (array('A'=>'M017_SYNTH_E1_A','B'=>'M017_SYNTH_E1_B','C'=>'M017_SYNTH_E2_C') as $key => $ref) {
		$result = $db->query("SELECT u.rowid user_id,u.login,u.entity,u.fk_soc,s.nom company_name FROM ".MAIN_DB_PREFIX."user u INNER JOIN ".MAIN_DB_PREFIX."societe s ON s.rowid=u.fk_soc AND s.entity=u.entity WHERE u.ref_ext='".$db->escape($ref)."' LIMIT 1");
		$row = $result ? $db->fetch_object($result) : null;
		m017_data_assert($row !== null, 'Synthetic context exists: '.$key);
		if ($row) $contexts[$key] = array('user_id'=>(int) $row->user_id,'login'=>(string) $row->login,'entity'=>(int) $row->entity,'socid'=>(int) $row->fk_soc,'display_name'=>'Synthetic Customer '.$key,'company_name'=>(string) $row->company_name);
	}
	if (count($contexts) === 3) {
		$openapi = json_decode(file_get_contents(dirname(__DIR__, 2).'/docs/API/mobile-v1.openapi.json'), true); $contractCases = array();
		$projectsA = cpcc_mobile_projects($db, $contexts['A'], 25, '', '');
		$projectsB = cpcc_mobile_projects($db, $contexts['B'], 25, '', '');
		$projectsC = cpcc_mobile_projects($db, $contexts['C'], 25, '', '');
		m017_data_assert(count($projectsA['data']) === 2 && count($projectsB['data']) === 1 && count($projectsC['data']) === 1, 'Project lists are isolated by entity and customer');
		$contractCases['ProjectListEnvelope'] = $projectsA;
		$foreignProject = $projectsA['data'][0]['id'];
		m017_data_assert(cpcc_mobile_resolve_public_id($db, 'project', $contexts['B'], $foreignProject) === null, 'A valid foreign opaque project ID is non-resolvable');
		m017_data_assert(cpcc_mobile_resolve_public_id($db, 'project', $contexts['C'], $foreignProject) === null, 'A valid cross-entity opaque project ID is non-resolvable');
		$shipmentsA = cpcc_mobile_shipments($db, $contexts['A'], 25, '', '');
		m017_data_assert(count($shipmentsA['data']) === 2, 'Multiple shipments for one customer are represented separately');
		$shipment = cpcc_mobile_shipment_detail($db, $contexts['A'], $shipmentsA['data'][0]['id']);
		$contractCases['ShipmentListEnvelope'] = $shipmentsA; $contractCases['ShipmentDetailEnvelope'] = $shipment;
		$shipmentCodes = array_map(function ($item) { return $item['code']; }, $shipment['data']['timeline']);
		m017_data_assert(in_array('transit', $shipmentCodes, true), 'Synthetic shipment detail includes customer-visible transit');
		$documentsA = cpcc_mobile_documents($db, $contexts['A'], 25, '', '');
		$contractCases['DocumentListEnvelope'] = $documentsA;
		m017_data_assert(count($documentsA['data']) === 2, 'Only approved/current synthetic customer documents are listed');
		$documentsB = cpcc_mobile_documents($db, $contexts['B'], 25, '', '');
		m017_data_assert(cpcc_mobile_resolve_public_id($db, 'document', $contexts['B'], $documentsA['data'][0]['id']) === null, 'A valid foreign opaque document ID is non-resolvable');
		m017_data_assert(count($documentsB['data']) === 2, 'Customer B receives only its own approved documents');
		$file = cpcc_mobile_safe_file_record($db, $contexts['A'], $documentsA['data'][0]['id']);
		m017_data_assert(is_file($file['path']) && $file['size'] > 0 && strpos(realpath($file['path']), realpath(DOL_DATA_ROOT).DIRECTORY_SEPARATOR) === 0 && strpos(basename($file['path']), 'm017-') === 0, 'Protected content resolves only to a confined synthetic file');

		$resourceCases = array(
			'project'=>array('call'=>'cpcc_mobile_projects','counts'=>array('A'=>2,'B'=>1,'C'=>1)),
			'shipment'=>array('call'=>'cpcc_mobile_shipments','counts'=>array('A'=>2,'B'=>1,'C'=>1)),
			'order'=>array('call'=>'cpcc_mobile_orders','counts'=>array('A'=>2,'B'=>1,'C'=>1)),
			'transaction'=>array('call'=>'cpcc_mobile_transactions','counts'=>array('A'=>3,'B'=>1,'C'=>1)),
			'invoice'=>array('call'=>'cpcc_mobile_service_invoices','counts'=>array('A'=>1,'B'=>1,'C'=>1)),
			'document'=>array('call'=>'cpcc_mobile_documents','counts'=>array('A'=>2,'B'=>2,'C'=>2)),
			'commercial_document'=>array('call'=>'cpcc_mobile_commercial_documents','counts'=>array('A'=>1,'B'=>1,'C'=>1)),
			'report'=>array('call'=>'cpcc_mobile_reports','counts'=>array('A'=>1,'B'=>1,'C'=>1)),
		);
		foreach ($resourceCases as $resource => $case) {
			$idsByContext = array();
			foreach ($contexts as $key => $ownedContext) {
				$list = call_user_func($case['call'], $db, $ownedContext, 25, '', '');
				$idsByContext[$key] = array_map(function ($item) { return $item['id']; }, $list['data']);
				m017_data_assert(count($list['data']) === $case['counts'][$key], $resource.' list/count is isolated for customer '.$key);
				$future = call_user_func($case['call'], $db, $ownedContext, 25, '', '2100-01-01T00:00:00Z');
				m017_data_assert(empty($future['data']), $resource.' updated_since cannot reveal older or foreign rows for customer '.$key);
			}
			m017_data_assert(!array_intersect($idsByContext['A'], $idsByContext['B']) && !array_intersect($idsByContext['A'], $idsByContext['C']) && !array_intersect($idsByContext['B'], $idsByContext['C']), $resource.' opaque IDs do not overlap across customers/entities');
			if (!empty($idsByContext['A'][0])) {
				m017_data_assert(cpcc_mobile_resolve_public_id($db, $resource, $contexts['B'], $idsByContext['A'][0]) === null, $resource.' valid foreign ID is non-resolvable for customer B');
				m017_data_assert(cpcc_mobile_resolve_public_id($db, $resource, $contexts['C'], $idsByContext['A'][0]) === null, $resource.' valid cross-entity ID is non-resolvable for customer C');
			}
			foreach (array('1', cpcc_mobile_public_prefix($resource).'_forged12345678901234567890') as $invalidId) m017_data_assert(cpcc_mobile_resolve_public_id($db, $resource, $contexts['A'], $invalidId) === null, $resource.' raw/malformed/forged ID is non-resolvable');
		}
		$staleProjectId = cpcc_mobile_public_id($db, 'project', $contexts['A'], 2147480000);
		m017_data_assert(m017_data_throws_status(function () use ($db, $contexts, $staleProjectId) { cpcc_mobile_project_detail($db, $contexts['A'], $staleProjectId); }, 404), 'Stale opaque mapping to a missing owned object returns non-revealing 404');
		$db->query("DELETE FROM ".MAIN_DB_PREFIX."cpcc_mobile_public_id WHERE public_id='".$db->escape($staleProjectId)."'");

		$dashboardA = cpcc_mobile_dashboard($db, $contexts['A']);
		m017_data_assert($dashboardA['data']['active_projects'] === 2 && $dashboardA['data']['active_shipments'] === 2 && $dashboardA['data']['open_orders'] === 2, 'Dashboard aggregates equal customer A owned list counts');

		$assetRows = cpcc_mobile_document_rows($db, $contexts['A']); $ownedAsset = null;
		foreach ($assetRows as $assetRow) if ($assetRow->source_type === 'project_asset') { $ownedAsset = $assetRow; break; }
		m017_data_assert($ownedAsset !== null, 'Synthetic project asset exists for negative file tests');
		if ($ownedAsset) {
			$insertAsset = function ($originalName, $storedName, $mimeType, $fileSize, $reviewStatus, $customerVisible, $status) use ($db, $contexts, $ownedAsset) {
				$now = cpcc_mobile_now_sql();
				$sql = "INSERT INTO ".MAIN_DB_PREFIX."cpkanban_asset (entity,fk_soc,fk_card,asset_type,document_type,original_name,stored_name,mime_type,file_size,caption,version_no,review_status,customer_visible,status,fk_user_creat,date_creation) VALUES (".(int) $contexts['A']['entity'].",".(int) $contexts['A']['socid'].",".(int) $ownedAsset->fk_card.",'document','final','".$db->escape($originalName)."','".$db->escape($storedName)."','".$db->escape($mimeType)."',".(int) $fileSize.",'M017 negative file',1,'".$db->escape($reviewStatus)."',".(int) $customerVisible.",".(int) $status.",".(int) $contexts['A']['user_id'].",'".$now."')";
				m017_data_assert((bool) $db->query($sql), 'Synthetic negative file row can be created');
				return (int) $db->last_insert_id(MAIN_DB_PREFIX.'cpkanban_asset');
			};
			$negativeVisibilityIds = array(
				$insertAsset('m017-draft.txt', $ownedAsset->stored_name, 'text/plain', $ownedAsset->file_size, 'pending', 1, 1),
				$insertAsset('m017-private.txt', $ownedAsset->stored_name, 'text/plain', $ownedAsset->file_size, 'approved', 0, 1),
				$insertAsset('m017-replaced.txt', $ownedAsset->stored_name, 'text/plain', $ownedAsset->file_size, 'approved', 1, 0),
			);
			$documentsAfterHidden = cpcc_mobile_documents($db, $contexts['A'], 25, '', '');
			m017_data_assert(count($documentsAfterHidden['data']) === 2, 'Draft, private, and replaced files remain absent from customer lists');
			$db->query("DELETE FROM ".MAIN_DB_PREFIX."cpkanban_asset WHERE rowid IN (".implode(',', array_map('intval', $negativeVisibilityIds)).")");

			$missingId = $insertAsset('m017-missing.txt', 'm017-missing-does-not-exist.txt', 'text/plain', 10, 'approved', 1, 1);
			$missingPublic = cpcc_mobile_public_id($db, 'document', $contexts['A'], $missingId * 2);
			m017_data_assert(m017_data_throws_status(function () use ($db, $contexts, $missingPublic) { cpcc_mobile_safe_file_record($db, $contexts['A'], $missingPublic); }, 404), 'Missing approved file fails closed without path disclosure');
			$db->query("DELETE FROM ".MAIN_DB_PREFIX."cpcc_mobile_public_id WHERE public_id='".$db->escape($missingPublic)."'"); $db->query("DELETE FROM ".MAIN_DB_PREFIX."cpkanban_asset WHERE rowid=".(int) $missingId);

			$traversalId = $insertAsset('m017-traversal.txt', '../../conf/conf.php', 'text/plain', 1, 'approved', 1, 1);
			$traversalPublic = cpcc_mobile_public_id($db, 'document', $contexts['A'], $traversalId * 2);
			m017_data_assert(m017_data_throws_status(function () use ($db, $contexts, $traversalPublic) { cpcc_mobile_safe_file_record($db, $contexts['A'], $traversalPublic); }, 404), 'Traversal stored name fails closed outside the authorized root');
			$db->query("DELETE FROM ".MAIN_DB_PREFIX."cpcc_mobile_public_id WHERE public_id='".$db->escape($traversalPublic)."'"); $db->query("DELETE FROM ".MAIN_DB_PREFIX."cpkanban_asset WHERE rowid=".(int) $traversalId);

			$mimeId = $insertAsset("m017-evil\r\nX-Test: injected.html", $ownedAsset->stored_name, 'text/html', $ownedAsset->file_size, 'approved', 1, 1);
			$mimePublic = cpcc_mobile_public_id($db, 'document', $contexts['A'], $mimeId * 2); $mimeRecord = cpcc_mobile_safe_file_record($db, $contexts['A'], $mimePublic);
			m017_data_assert($mimeRecord['mime_type'] === 'application/octet-stream' && strpos($mimeRecord['filename'], "\r") === false && strpos($mimeRecord['filename'], "\n") === false && strpos($mimeRecord['filename'], '"') === false, 'Unallowlisted MIME is downgraded and download filename is header-safe');
			$db->query("DELETE FROM ".MAIN_DB_PREFIX."cpcc_mobile_public_id WHERE public_id='".$db->escape($mimePublic)."'"); $db->query("DELETE FROM ".MAIN_DB_PREFIX."cpkanban_asset WHERE rowid=".(int) $mimeId);
		}
		$balances = cpcc_mobile_balances($db, $contexts['A']);
		$contractCases['BalanceListEnvelope'] = $balances;
		m017_data_assert(count($balances['data']) === 3, 'Balances remain separated across USD, CNY, and EGP');
		$reports = cpcc_mobile_reports($db, $contexts['A'], 25, '', '');
		$report = cpcc_mobile_report_detail($db, $contexts['A'], $reports['data'][0]['id']);
		$contractCases['ReportListEnvelope'] = $reports; $contractCases['ReportDetailEnvelope'] = $report;
		$fieldCodes = array_map(function ($item) { return $item['code']; }, $report['data']['fields']);
		m017_data_assert(!in_array('factory_name', $fieldCodes, true), 'Forbidden factory identity is removed from report fields');
		m017_data_assert(in_array('inspection_result', $fieldCodes, true), 'Allowed quality result remains customer-visible');
		if (in_array('shipping_line', $fieldCodes, true)) {
			$shippingField = $report['data']['fields'][array_search('shipping_line', $fieldCodes, true)];
			$reportId = cpcc_mobile_resolve_public_id($db, 'report', $contexts['A'], $report['data']['id']);
			$ownedShipment = $db->query("SELECT o.fk_shipment FROM ".MAIN_DB_PREFIX."cpo_operation o INNER JOIN ".MAIN_DB_PREFIX."chinaplanetlogistics s ON s.rowid=o.fk_shipment AND s.entity=o.entity AND s.fk_soc=o.fk_soc WHERE o.rowid=".(int) $reportId." AND o.entity=".(int) $contexts['A']['entity']." AND o.fk_soc=".(int) $contexts['A']['socid']." LIMIT 1");
			$ownedShipmentRow = $ownedShipment ? $db->fetch_object($ownedShipment) : null;
			m017_data_assert($ownedShipmentRow && cpcc_mobile_report_shipping_line_visible($db, $contexts['A'], $ownedShipmentRow->fk_shipment, $shippingField['value']), 'Report shipping line is verified against the owned shipment');
		}
		$contractCases['MeEnvelope'] = cpcc_mobile_envelope(cpcc_mobile_me_data($db, $contexts['A']));
		$contractCases['DashboardEnvelope'] = cpcc_mobile_dashboard($db, $contexts['A']);
		$projectDetail = cpcc_mobile_project_detail($db, $contexts['A'], $projectsA['data'][0]['id']); $contractCases['ProjectDetailEnvelope'] = $projectDetail;
		$documentIds = array_map(function ($document) { return $document['id']; }, $documentsA['data']);
		m017_data_assert(!array_diff($projectDetail['data']['document_ids'], $documentIds), 'Project document IDs resolve to the same customer-visible document resources');
		m017_data_assert(count($projectDetail['data']['balances']) <= count($balances['data']), 'Project detail never substitutes the complete customer balance list');
		$contractCases['OrderListEnvelope'] = cpcc_mobile_orders($db, $contexts['A'], 25, '', '');
		$contractCases['TransactionListEnvelope'] = cpcc_mobile_transactions($db, $contexts['A'], 25, '', '');
		$contractCases['InvoiceListEnvelope'] = cpcc_mobile_service_invoices($db, $contexts['A'], 25, '', '');
		$commercialList = cpcc_mobile_commercial_documents($db, $contexts['A'], 25, '', ''); $contractCases['CommercialDocumentListEnvelope'] = $commercialList;
		$contractCases['CommercialDocumentDetailEnvelope'] = cpcc_mobile_commercial_document_detail($db, $contexts['A'], $commercialList['data'][0]['id']);
		foreach ($contractCases as $schemaName => $response) { $schemaErrors = m017_schema_errors($response, $openapi['components']['schemas'][$schemaName], $openapi, '$'.$schemaName); m017_data_assert(!$schemaErrors, $schemaName.' matches frozen OpenAPI: '.implode('; ', array_slice($schemaErrors, 0, 4))); }
	}
}
if ($failures) { fwrite(STDERR, "M017_DATA=FAIL\n- ".implode("\n- ", $failures)."\n"); exit(1); }
echo "M017_DATA=PASS TIMELINE_STEPS=".count($timeline).($documentRoot ? ' DB_INTEGRATION=PASS' : '')."\n";
