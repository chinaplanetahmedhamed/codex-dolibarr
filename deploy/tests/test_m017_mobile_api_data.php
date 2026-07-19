<?php
define('CPCC_MOBILE_TESTING', true);
putenv('CPCC_MOBILE_TEST_PEPPER=m017-local-test-pepper-0123456789abcdef');
require_once dirname(__DIR__, 2).'/custom/chinaplanetcustomercenter/lib/mobile_api_data.lib.php';
$failures = array();
function m017_data_assert($condition, $message) { global $failures; if (!$condition) $failures[] = $message; }
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
		$balances = cpcc_mobile_balances($db, $contexts['A']);
		$contractCases['BalanceListEnvelope'] = $balances;
		m017_data_assert(count($balances['data']) === 3, 'Balances remain separated across USD, CNY, and EGP');
		$reports = cpcc_mobile_reports($db, $contexts['A'], 25, '', '');
		$report = cpcc_mobile_report_detail($db, $contexts['A'], $reports['data'][0]['id']);
		$contractCases['ReportListEnvelope'] = $reports; $contractCases['ReportDetailEnvelope'] = $report;
		$fieldCodes = array_map(function ($item) { return $item['code']; }, $report['data']['fields']);
		m017_data_assert(!in_array('factory_name', $fieldCodes, true), 'Forbidden factory identity is removed from report fields');
		m017_data_assert(in_array('inspection_result', $fieldCodes, true), 'Allowed quality result remains customer-visible');
		$contractCases['MeEnvelope'] = cpcc_mobile_envelope(cpcc_mobile_me_data($db, $contexts['A']));
		$contractCases['DashboardEnvelope'] = cpcc_mobile_dashboard($db, $contexts['A']);
		$projectDetail = cpcc_mobile_project_detail($db, $contexts['A'], $projectsA['data'][0]['id']); $contractCases['ProjectDetailEnvelope'] = $projectDetail;
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
