<?php
/** Customer-safe read models for the M017 mobile API. */

require_once __DIR__.'/mobile_api.lib.php';
require_once __DIR__.'/portal.lib.php';
require_once __DIR__.'/financial.lib.php';
require_once __DIR__.'/operation_reports.lib.php';
require_once __DIR__.'/operations.lib.php';
require_once __DIR__.'/customer_documents.lib.php';

function cpcc_mobile_envelope($data, $meta = null)
{
	$result = array('data' => $data);
	if ($meta !== null) $result['meta'] = $meta;
	$result['request_id'] = cpcc_mobile_request_id();
	return $result;
}

function cpcc_mobile_rows($db, $result)
{
	$rows = array();
	while ($result && ($row = $db->fetch_object($result))) $rows[] = $row;
	return $rows;
}

function cpcc_mobile_table_exists($db, $suffix)
{
	$result = $db->query("SHOW TABLES LIKE '".$db->escape(MAIN_DB_PREFIX.(string) $suffix)."'");
	return $result && $db->num_rows($result) === 1;
}

function cpcc_mobile_updated_since($value)
{
	if ($value === null || $value === '') return '';
	if (!is_string($value) || strpos($value, 'T') === false || !preg_match('/(?:Z|[+-]\d{2}:\d{2})$/', $value)) cpcc_mobile_throw(422, 'validation', array('updated_since'));
	$timestamp = strtotime($value);
	if ($timestamp === false) cpcc_mobile_throw(422, 'validation', array('updated_since'));
	return gmdate('Y-m-d H:i:s', $timestamp);
}

function cpcc_mobile_list_cursor_clause($db, $last, $dateField, $idField)
{
	if ($last === null) return '';
	if (!is_array($last) || empty($last['date']) || empty($last['id'])) cpcc_mobile_throw(404, 'not_found');
	return " AND ((".$dateField." < '".$db->escape((string) $last['date'])."') OR (".$dateField." = '".$db->escape((string) $last['date'])."' AND ".$idField." < ".(int) $last['id']."))";
}

function cpcc_mobile_project_status($status, $completed, $progress)
{
    $status = (int) $status; $progress = (int) $progress;
    if ($status <= 0) return array('code' => 'draft', 'label' => cpcc_mobile_label('مسودة', 'Draft'));
    if (!empty($completed)) return array('code' => 'completed', 'label' => cpcc_mobile_label('مكتمل', 'Completed'));
    if ($progress > 0) return array('code' => 'in_progress', 'label' => cpcc_mobile_label('قيد التنفيذ', 'In progress'));
    return array('code' => 'validated', 'label' => cpcc_mobile_label('معتمد', 'Validated'));
}

function cpcc_mobile_shipment_status($status)
{
	$map = array(
		0 => array('inquiry', 'استفسار', 'Inquiry'),
		1 => array('booking_confirmed', 'تم تأكيد الحجز', 'Booking confirmed'),
		8 => array('loaded_export_customs', 'تم التحميل ومتجهة لجمارك التصدير', 'Loaded & going to export customs'),
		9 => array('export_customs_cleared', 'اكتمل التخليص الجمركي للتصدير', 'Export customs cleared'),
		2 => array('on_vessel', 'على السفينة', 'On vessel'),
		3 => array('arrived_port', 'وصلت إلى الميناء', 'Arrived port'),
		4 => array('under_customs', 'تحت التخليص الجمركي', 'Under customs'),
		5 => array('customs_cleared', 'تم التخليص الجمركي', 'Customs cleared'),
		6 => array('delivered', 'تم التسليم', 'Delivered'),
		7 => array('closed', 'مغلقة', 'Closed'),
	);
	$value = isset($map[(int) $status]) ? $map[(int) $status] : $map[0];
	return array('code' => $value[0], 'label' => cpcc_mobile_label($value[1], $value[2]));
}

function cpcc_mobile_order_status($status)
{
	$map = array(0 => array('draft', 'مسودة', 'Draft'), 1 => array('validated', 'معتمد', 'Validated'), 2 => array('in_progress', 'قيد التنفيذ', 'In progress'), 3 => array('closed', 'مغلق', 'Closed'), -1 => array('canceled', 'ملغي', 'Canceled'));
	$value = isset($map[(int) $status]) ? $map[(int) $status] : $map[1];
	return array('code' => $value[0], 'label' => cpcc_mobile_label($value[1], $value[2]));
}

function cpcc_mobile_invoice_status($row, $remaining)
{
	if ((int) $row->fk_statut <= 0) return array('code' => 'draft', 'label' => cpcc_mobile_label('مسودة', 'Draft'));
	if ((int) $row->fk_statut === 3) return array('code' => 'canceled', 'label' => cpcc_mobile_label('ملغاة', 'Canceled'));
	if ((int) $row->paye === 1 || $remaining <= 0.005) return array('code' => 'paid', 'label' => cpcc_mobile_label('مدفوعة', 'Paid'));
	if (!empty($row->date_lim_reglement) && strtotime((string) $row->date_lim_reglement) < time()) return array('code' => 'overdue', 'label' => cpcc_mobile_label('متأخرة', 'Overdue'));
	if ($remaining + 0.005 < (float) $row->display_total) return array('code' => 'partially_paid', 'label' => cpcc_mobile_label('مدفوعة جزئيًا', 'Partially paid'));
	return array('code' => 'validated', 'label' => cpcc_mobile_label('معتمدة', 'Validated'));
}

function cpcc_mobile_me_data($db, $context)
{
	return array(
		'id' => cpcc_mobile_public_id($db, 'user', $context, (int) $context['user_id']),
		'display_name' => (string) $context['display_name'],
		'company_name' => (string) $context['company_name'],
		'preferred_language' => 'ar',
	);
}

function cpcc_mobile_project_query_base($context)
{
    $sql = "SELECT p.rowid,p.ref,p.title,p.description,p.datec,p.dateo,p.datee,p.fk_statut,p.tms";
    $sql .= " FROM ".MAIN_DB_PREFIX."projet p";
    $sql .= " WHERE p.entity=".(int) $context['entity']." AND p.fk_soc=".(int) $context['socid'];
    return $sql;
}

function cpcc_mobile_project_projection($db, $context, $projectId)
{
    static $cache = array();
    $key = (int) $context['entity'].':'.(int) $context['socid'].':'.(int) $projectId;
    if (!isset($cache[$key])) {
        $currency = cpcc_financial_currency(!empty($GLOBALS['conf']->currency) ? $GLOBALS['conf']->currency : 'USD');
        $stages = cpcc_project_lifecycle($db, (int) $context['socid'], (int) $projectId, $currency, (int) $context['entity']);
        $cache[$key] = cpcc_project_lifecycle_projection($stages);
    }
    return $cache[$key];
}

function cpcc_mobile_project_summary($db, $context, $row)
{
    $projection = cpcc_mobile_project_projection($db, $context, (int) $row->rowid);
    $current = !empty($projection['current_stage']) ? $projection['current_stage'] : null;
    $next = !empty($projection['next_stage']) ? $projection['next_stage'] : null;
    return array(
		'id' => cpcc_mobile_public_id($db, 'project', $context, (int) $row->rowid),
		'ref' => (string) $row->ref,
		'title' => (string) $row->title,
        'status' => cpcc_mobile_project_status($row->fk_statut, $projection['completed'], $projection['progress_percent']),
        'progress_percent' => (int) $projection['progress_percent'],
        'current_step' => $current ? cpcc_mobile_label((string) $current['label'], (string) $current['en']) : cpcc_mobile_label('بانتظار بيانات التشغيل', 'Awaiting operational data'),
        'next_step' => $next ? cpcc_mobile_label((string) $next['label'], (string) $next['en']) : cpcc_mobile_label('اكتملت المراحل التشغيلية', 'Operational stages complete'),
        'expected_next_at' => null,
		'latest_update_at' => cpcc_mobile_iso(!empty($row->tms) ? $row->tms : $row->datec),
		'latest_update_by' => cpcc_mobile_label('فريق كوكب الصين', 'China Planet Team'),
		'updated_at' => cpcc_mobile_iso(!empty($row->tms) ? $row->tms : $row->datec),
	);
}

function cpcc_mobile_projects($db, $context, $limit, $cursor, $updatedSince)
{
	$limit = cpcc_mobile_list_limit($limit); $updated = cpcc_mobile_updated_since($updatedSince);
	$filters = array('updated_since' => $updated); $filterHash = cpcc_mobile_filter_hash('projects', $filters);
	$last = cpcc_mobile_cursor_decode($cursor, 'projects', $context, $filterHash, $limit);
	$sql = cpcc_mobile_project_query_base($context);
	if ($updated !== '') $sql .= " AND p.tms>='".$db->escape($updated)."'";
	$sql .= cpcc_mobile_list_cursor_clause($db, $last, 'p.tms', 'p.rowid');
	$sql .= " ORDER BY p.tms DESC,p.rowid DESC LIMIT ".($limit + 1);
	$raw = cpcc_mobile_rows($db, cpcc_mobile_query_or_throw($db, $sql));
	$page = cpcc_mobile_page_meta($db, 'projects', $context, $filters, $limit, array_map(function ($row) { return array('_row' => $row, 'tms' => (string) $row->tms, 'rowid' => (int) $row->rowid); }, $raw), 'tms', 'rowid');
	$data = array(); foreach ($page['rows'] as $wrapped) $data[] = cpcc_mobile_project_summary($db, $context, $wrapped['_row']);
	return cpcc_mobile_envelope($data, $page['meta']);
}

function cpcc_mobile_project_detail($db, $context, $publicId)
{
	$id = cpcc_mobile_resolve_public_id($db, 'project', $context, $publicId);
	if (!$id) cpcc_mobile_throw(404, 'not_found');
	$result = cpcc_mobile_query_or_throw($db, cpcc_mobile_project_query_base($context)." AND p.rowid=".(int) $id." LIMIT 1");
	$row = $db->fetch_object($result); if (!$row) cpcc_mobile_throw(404, 'not_found');
	$data = cpcc_mobile_project_summary($db, $context, $row);
	$data['description'] = (string) $row->description;
	$data['start_at'] = cpcc_mobile_iso($row->dateo);
	$data['end_at'] = cpcc_mobile_iso($row->datee);
    $data['timeline'] = cpcc_mobile_project_timeline($db, $context, (int) $row->rowid);
	$data['shipment_ids'] = cpcc_mobile_related_ids($db, 'shipment', $context, "SELECT rowid FROM ".MAIN_DB_PREFIX."chinaplanetlogistics WHERE entity=".(int) $context['entity']." AND fk_soc=".(int) $context['socid']." AND fk_project=".(int) $id." ORDER BY rowid");
	$data['document_ids'] = cpcc_mobile_project_document_ids($db, $context, (int) $id);
	$data['report_ids'] = cpcc_mobile_project_report_ids($db, $context, $id);
	$data['balances'] = cpcc_mobile_balances_data($db, $context, (int) $id);
	return cpcc_mobile_envelope($data);
}

function cpcc_mobile_project_report_ids($db, $context, $projectId)
{
	$qcReady=cpcc_qc_reports_schema_ready($db);$sql="SELECT o.rowid FROM ".MAIN_DB_PREFIX."cpo_operation o";if($qcReady)$sql.=" LEFT JOIN ".MAIN_DB_PREFIX."cpo_qc_publication q ON q.entity=o.entity AND q.fk_operation=o.rowid AND q.fk_soc=o.fk_soc AND q.is_current=1";$sql.=" WHERE o.entity=".(int)$context['entity']." AND o.fk_soc=".(int)$context['socid']." AND o.fk_project=".(int)$projectId." AND o.status=1 AND o.customer_visible=1";$sql.=$qcReady?" AND (o.service_type<>'quality_control' OR q.rowid IS NOT NULL)":" AND o.service_type<>'quality_control'";$sql.=" ORDER BY o.rowid";return cpcc_mobile_related_ids($db,'report',$context,$sql);
}

function cpcc_mobile_project_timeline($db, $context, $projectId)
{
    $projection = cpcc_mobile_project_projection($db, $context, (int) $projectId);
    $result = array();
    foreach ($projection['timeline'] as $stage) {
        $state = (string) $stage['state'];
        $result[] = array('code' => (string) $stage['key'], 'label' => cpcc_mobile_label((string) $stage['label'], (string) $stage['en']), 'state' => $state, 'state_label' => cpcc_mobile_timeline_state_label($state), 'occurred_at' => null, 'source' => null, 'location' => null, 'public_note' => null, 'public_reason' => null);
    }
	return $result;
}

function cpcc_mobile_timeline_state_label($state)
{
	$map = array('completed' => array('مكتمل', 'Completed'), 'current' => array('الحالية', 'Current'), 'next' => array('التالية', 'Next'), 'planned' => array('مخططة', 'Planned'), 'unconfirmed' => array('غير مؤكدة', 'Unconfirmed'));
	$value = isset($map[$state]) ? $map[$state] : $map['planned'];
	return cpcc_mobile_label($value[0], $value[1]);
}

function cpcc_mobile_related_ids($db, $resource, $context, $sql)
{
	$ids = array(); $result = $db->query($sql);
	while ($result && ($row = $db->fetch_object($result))) $ids[] = cpcc_mobile_public_id($db, $resource, $context, (int) $row->rowid);
	return $ids;
}

function cpcc_mobile_shipment_query_base($context)
{
	return "SELECT s.rowid,s.ref,s.fk_project,s.container_no,s.container_type,s.nb_containers,s.booking_no,s.bl_no,s.shipping_line,s.vessel_name,s.voyage_no,s.port_loading,s.port_destination,s.date_etd,s.date_atd,s.date_eta,s.date_ata,s.freetime_days,s.freetime_start,s.freetime_end,s.fk_statut,s.customer_note,s.tms FROM ".MAIN_DB_PREFIX."chinaplanetlogistics s WHERE s.entity=".(int) $context['entity']." AND s.fk_soc=".(int) $context['socid'];
}

function cpcc_mobile_shipment_summary($db, $context, $row)
{
	return array(
		'id' => cpcc_mobile_public_id($db, 'shipment', $context, (int) $row->rowid),
		'project_id' => !empty($row->fk_project) ? cpcc_mobile_public_id($db, 'project', $context, (int) $row->fk_project) : null,
		'ref' => (string) $row->ref,
		'status' => cpcc_mobile_shipment_status($row->fk_statut),
		'container_number' => $row->container_no !== null && $row->container_no !== '' ? (string) $row->container_no : null,
		'container_type' => $row->container_type !== null && $row->container_type !== '' ? (string) $row->container_type : null,
		'container_count' => max(0, (int) $row->nb_containers),
		'bl_number' => $row->bl_no !== null && $row->bl_no !== '' ? (string) $row->bl_no : null,
		'eta' => cpcc_mobile_iso($row->date_eta), 'ata' => cpcc_mobile_iso($row->date_ata), 'free_time_end' => cpcc_mobile_iso($row->freetime_end),
		'updated_at' => cpcc_mobile_iso($row->tms),
	);
}

function cpcc_mobile_shipments($db, $context, $limit, $cursor, $updatedSince)
{
	$limit = cpcc_mobile_list_limit($limit); $updated = cpcc_mobile_updated_since($updatedSince); $filters = array('updated_since' => $updated); $hash = cpcc_mobile_filter_hash('shipments', $filters);
	$last = cpcc_mobile_cursor_decode($cursor, 'shipments', $context, $hash, $limit);
	$sql = cpcc_mobile_shipment_query_base($context); if ($updated !== '') $sql .= " AND s.tms>='".$db->escape($updated)."'";
	$sql .= cpcc_mobile_list_cursor_clause($db, $last, 's.tms', 's.rowid')." ORDER BY s.tms DESC,s.rowid DESC LIMIT ".($limit + 1);
	$raw = cpcc_mobile_rows($db, cpcc_mobile_query_or_throw($db, $sql)); $wrapped = array(); foreach ($raw as $row) $wrapped[] = array('_row' => $row, 'tms' => (string) $row->tms, 'rowid' => (int) $row->rowid);
	$page = cpcc_mobile_page_meta($db, 'shipments', $context, $filters, $limit, $wrapped, 'tms', 'rowid'); $data = array(); foreach ($page['rows'] as $item) $data[] = cpcc_mobile_shipment_summary($db, $context, $item['_row']);
	return cpcc_mobile_envelope($data, $page['meta']);
}

function cpcc_mobile_shipment_detail($db, $context, $publicId)
{
	$id = cpcc_mobile_resolve_public_id($db, 'shipment', $context, $publicId); if (!$id) cpcc_mobile_throw(404, 'not_found');
	$result = cpcc_mobile_query_or_throw($db, cpcc_mobile_shipment_query_base($context)." AND s.rowid=".(int) $id." LIMIT 1"); $row = $db->fetch_object($result); if (!$row) cpcc_mobile_throw(404, 'not_found');
	$data = cpcc_mobile_shipment_summary($db, $context, $row);
	$data += array(
		'booking_number' => $row->booking_no !== null && $row->booking_no !== '' ? (string) $row->booking_no : null,
		'shipping_line' => $row->shipping_line !== null && $row->shipping_line !== '' ? (string) $row->shipping_line : null,
		'vessel_name' => $row->vessel_name !== null && $row->vessel_name !== '' ? (string) $row->vessel_name : null,
		'voyage_number' => $row->voyage_no !== null && $row->voyage_no !== '' ? (string) $row->voyage_no : null,
		'port_loading' => $row->port_loading !== null && $row->port_loading !== '' ? (string) $row->port_loading : null,
		'port_destination' => $row->port_destination !== null && $row->port_destination !== '' ? (string) $row->port_destination : null,
		'etd' => cpcc_mobile_iso($row->date_etd), 'atd' => cpcc_mobile_iso($row->date_atd), 'free_time_days' => $row->freetime_days !== null ? max(0, (int) $row->freetime_days) : null, 'free_time_start' => cpcc_mobile_iso($row->freetime_start),
		'customer_notes' => $row->customer_note !== null && $row->customer_note !== '' ? (string) $row->customer_note : null,
	);
	$events = cpcc_mobile_shipment_events($db, $context, $id); $latest = !empty($events) ? $events[0] : null; $etaChange = null;
	foreach ($events as $event) if ($event->event_type === 'eta_changed') { $etaChange = $event; break; }
	$data['eta_changed'] = $etaChange !== null;
	$data['previous_eta'] = $etaChange ? cpcc_mobile_iso($etaChange->date_value_old) : null;
	$data['eta_change_days'] = $etaChange && $etaChange->date_value_old && $etaChange->date_value_new ? (int) round((strtotime($etaChange->date_value_new)-strtotime($etaChange->date_value_old))/86400) : null;
	$data['latest_update_at'] = $latest ? cpcc_mobile_iso($latest->event_at) : cpcc_mobile_iso($row->tms);
	$data['latest_update_source'] = cpcc_mobile_public_source($latest ? $latest->source_type : 'manual_team');
	$data['latest_update'] = $latest ? array('title' => cpcc_mobile_event_title($latest->event_type), 'public_note' => $latest->note_public !== '' ? (string) $latest->note_public : null, 'occurred_at' => cpcc_mobile_iso($latest->event_at), 'source' => cpcc_mobile_public_source($latest->source_type)) : null;
	$data['eta_change'] = $etaChange ? array('changed_at' => cpcc_mobile_iso($etaChange->event_at), 'previous_eta' => cpcc_mobile_iso($etaChange->date_value_old), 'current_eta' => cpcc_mobile_iso($etaChange->date_value_new), 'change_days' => (int) $data['eta_change_days'], 'source' => cpcc_mobile_public_source($etaChange->source_type), 'public_reason' => $etaChange->reason_public !== '' ? (string) $etaChange->reason_public : null) : null;
	$data['customer_action'] = cpcc_mobile_shipment_action($db, $context, $id);
	$data['timeline'] = cpcc_mobile_shipment_timeline($row, $events);
	$data['shipment_gallery'] = cpcc_mobile_shipment_gallery($db, $context, $id);
	$data['document_requirements'] = cpcc_mobile_shipment_requirements($db, $context, $id);
	return cpcc_mobile_envelope($data);
}

function cpcc_mobile_shipment_events($db, $context, $shipmentId)
{
	if (!cpcc_mobile_table_exists($db, 'cpl_shipment_event')) return array();
	$sql = "SELECT event_type,milestone_code,event_at,planned_at,actual_at,date_value_old,date_value_new,source_type,note_public,reason_public FROM ".MAIN_DB_PREFIX."cpl_shipment_event WHERE entity=".(int) $context['entity']." AND fk_soc=".(int) $context['socid']." AND fk_shipment=".(int) $shipmentId." AND customer_visible=1 ORDER BY event_at DESC,rowid DESC";
	return cpcc_mobile_rows($db, $db->query($sql));
}

function cpcc_mobile_public_source($source)
{
	$map = array('manual_team' => array('فريق كوكب الصين', 'China Planet Team'), 'customer' => array('العميل', 'Customer'), 'carrier' => array('شركة الشحن', 'Carrier'), 'logistics_partner' => array('شريك لوجستي', 'Logistics partner'), 'customs' => array('الجمارك', 'Customs'), 'system' => array('النظام', 'System'));
	$value = isset($map[$source]) ? $map[$source] : $map['manual_team']; return cpcc_mobile_label($value[0], $value[1]);
}

function cpcc_mobile_event_title($type)
{
	$map = array('baseline' => array('تم إنشاء سجل الشحنة', 'Shipment record created'), 'status_changed' => array('تم تحديث حالة الشحنة', 'Shipment status updated'), 'eta_set' => array('تم تحديد موعد الوصول المتوقع', 'ETA set'), 'eta_changed' => array('تغير موعد الوصول المتوقع', 'ETA changed'), 'atd_set' => array('تم تأكيد المغادرة', 'Departure confirmed'), 'atd_changed' => array('تم تحديث المغادرة', 'Departure updated'), 'ata_set' => array('تم تأكيد الوصول', 'Arrival confirmed'), 'ata_changed' => array('تم تحديث الوصول', 'Arrival updated'), 'transshipment' => array('تحديث الترانزيت', 'Transit update'));
	$value = isset($map[$type]) ? $map[$type] : array('تم تحديث الشحنة', 'Shipment updated'); return cpcc_mobile_label($value[0], $value[1]);
}

function cpcc_mobile_shipment_timeline($shipment, $events)
{
	$steps = array(
		array('booking', 'الحجز', 'Booking', array(1,8,9,2,3,4,5,6,7), $shipment->date_etd),
		array('loaded', 'تم التحميل', 'Loaded', array(8,9,2,3,4,5,6,7), null),
		array('departed', 'غادرت', 'Departed', array(2,3,4,5,6,7), $shipment->date_atd),
		array('arrived', 'وصلت', 'Arrived', array(3,4,5,6,7), $shipment->date_ata),
		array('customs', 'الجمارك', 'Customs', array(4,5,6,7), null),
		array('delivered', 'تم التسليم', 'Delivered', array(6,7), null),
	);
	$result = array(); $currentStatus = (int) $shipment->fk_statut; $currentAssigned = false;
	foreach ($steps as $index => $step) {
		$complete = in_array($currentStatus, $step[3], true); $state = 'planned';
		if ($complete) $state = 'completed';
		elseif (!$currentAssigned) { $state = $index === 0 ? 'current' : 'next'; $currentAssigned = true; }
		if ($step[0] === 'arrived' && in_array($currentStatus, array(3,4,5), true) && empty($shipment->date_ata)) $state = 'unconfirmed';
		$result[] = array('code' => $step[0], 'label' => cpcc_mobile_label($step[1], $step[2]), 'state' => $state, 'state_label' => cpcc_mobile_timeline_state_label($state), 'occurred_at' => cpcc_mobile_iso($step[4]), 'source' => null, 'location' => null, 'public_note' => null, 'public_reason' => null);
		if ($step[0] === 'departed') {
			$transits = array(); foreach ($events as $event) if ($event->event_type === 'transshipment') $transits[] = $event; $transits = array_reverse($transits);
			foreach ($transits as $event) {
				$note = trim((string) $event->note_public); $location = $note; $publicNote = null; $parts = explode(' — ', $note, 2); if (count($parts) === 2) { $location = $parts[0]; $publicNote = $parts[1]; }
				$result[] = array('code' => 'transit', 'label' => cpcc_mobile_label('ترانزيت', 'Transit'), 'state' => 'completed', 'state_label' => cpcc_mobile_timeline_state_label('completed'), 'occurred_at' => cpcc_mobile_iso(!empty($event->actual_at) ? $event->actual_at : $event->event_at), 'source' => cpcc_mobile_public_source($event->source_type), 'location' => $location !== '' ? $location : null, 'public_note' => $publicNote, 'public_reason' => $event->reason_public !== '' ? (string) $event->reason_public : null);
			}
		}
	}
	return $result;
}

function cpcc_mobile_shipment_action($db, $context, $shipmentId)
{
	if (!cpcc_mobile_table_exists($db, 'cpo_operation')) return null;
	$sql = "SELECT title,waiting_on,next_action,date_deadline,note_public FROM ".MAIN_DB_PREFIX."cpo_operation WHERE entity=".(int) $context['entity']." AND fk_soc=".(int) $context['socid']." AND fk_shipment=".(int) $shipmentId." AND status=1 AND customer_visible=1 AND stage NOT IN ('completed','cancelled') ORDER BY (waiting_on='customer') DESC,tms DESC,rowid DESC LIMIT 1";
	$result = $db->query($sql); $row = $result ? $db->fetch_object($result) : null; if (!$row) return null;
	$responsibility = $row->waiting_on === 'customer' ? 'customer' : ($row->waiting_on === 'internal' || $row->waiting_on === 'china_planet' ? 'china_planet' : ($row->waiting_on === '' || $row->waiting_on === 'none' ? 'none' : 'external_party'));
	return array('label' => cpcc_mobile_label((string) ($row->next_action ?: $row->title), (string) ($row->next_action ?: $row->title)), 'responsibility' => $responsibility, 'required_from_customer' => $responsibility === 'customer', 'due_at' => cpcc_mobile_iso($row->date_deadline), 'public_note' => $row->note_public !== '' ? (string) $row->note_public : null);
}

function cpcc_mobile_requirement_status_label($status)
{
	$map = array('required' => array('مطلوب', 'Required'), 'requested' => array('تم الطلب', 'Requested'), 'received' => array('تم الاستلام', 'Received'), 'under_review' => array('قيد المراجعة', 'Under review'), 'correction_required' => array('يحتاج تصحيحًا', 'Correction required'), 'approved' => array('معتمد', 'Approved'), 'published' => array('منشور', 'Published'));
	$value = isset($map[$status]) ? $map[$status] : $map['required']; return cpcc_mobile_label($value[0], $value[1]);
}

function cpcc_mobile_shipment_requirements($db, $context, $shipmentId)
{
	if (!cpcc_mobile_table_exists($db, 'cpl_document_requirement')) return array();
	$sql = "SELECT document_code,label_public,requirement_status,requested_from,due_at,action_required,source_type,source_id,note_public FROM ".MAIN_DB_PREFIX."cpl_document_requirement WHERE entity=".(int) $context['entity']." AND fk_soc=".(int) $context['socid']." AND fk_shipment=".(int) $shipmentId." AND customer_visible=1 ORDER BY due_at IS NULL,due_at,rowid";
	$rows = cpcc_mobile_rows($db, $db->query($sql)); $result = array();
	foreach ($rows as $row) {
		$documentId = null;
		if ($row->requirement_status === 'published' && (int) $row->source_id > 0 && $row->source_type === 'operation_document') $documentId = cpcc_mobile_public_id($db, 'document', $context, ((int) $row->source_id * 2) + 1);
		$result[] = array('code' => (string) $row->document_code, 'label' => cpcc_mobile_label((string) $row->label_public, (string) $row->label_public), 'status' => (string) $row->requirement_status, 'status_label' => cpcc_mobile_requirement_status_label((string) $row->requirement_status), 'requested_from' => (string) $row->requested_from, 'action_required' => !empty($row->action_required), 'due_at' => cpcc_mobile_iso($row->due_at), 'public_note' => $row->note_public !== '' ? (string) $row->note_public : null, 'document_id' => $documentId);
	}
	return $result;
}

function cpcc_mobile_transaction_rows($db, $context)
{
	$rows = array();
	$baseCurrency = cpcc_mobile_base_currency();
	foreach (cpcc_financial_statement($db, (int) $context['socid'], $baseCurrency) as $item) {
		$logicalId = 0;
		if (preg_match('/^entry-(\d+)$/', $item['id'], $m)) $logicalId = ((int) $m[1] * 4);
		elseif (preg_match('/^credit-allocation-(\d+)$/', $item['id'], $m)) $logicalId = ((int) $m[1] * 4) + 2;
		elseif (preg_match('/^payment-(\d+)-invoice-(\d+)$/', $item['id'], $m)) $logicalId = ((((int) $m[1] * 1000000) + (int) $m[2]) * 4) + 1;
		elseif (preg_match('/^payment-allocation-(\d+)-invoice-(\d+)$/', $item['id'], $m)) $logicalId = ((((int) $m[1] * 1000000) + (int) $m[2]) * 4) + 3;
		if ($logicalId <= 0) continue;
		$row = new stdClass();
		$row->logical_id = $logicalId; $row->occurred_at = $item['date']; $row->direction = $item['direction'];
		$row->description = $item['description']; $row->amount = $item['amount']; $row->currency_code = $item['currency'];
			$row->reference = $item['reference']; $row->fk_project = $item['project_id']; $row->tms = $item['date'];
			$row->public_note = !empty($item['public_note']) ? (string)$item['public_note'] : null;
		$rows[] = $row;
	}
	usort($rows, function ($a, $b) { $cmp = strcmp((string) $b->occurred_at, (string) $a->occurred_at); return $cmp !== 0 ? $cmp : ((int) $b->logical_id <=> (int) $a->logical_id); });
	return $rows;
}

function cpcc_mobile_transaction_data($db, $context, $row)
{
	return array(
		'id' => cpcc_mobile_public_id($db, 'transaction', $context, (int) $row->logical_id),
		'project_id' => !empty($row->fk_project) ? cpcc_mobile_public_id($db, 'project', $context, (int) $row->fk_project) : null,
		'occurred_at' => cpcc_mobile_iso($row->occurred_at),
		'description' => (string) $row->description,
		'direction' => strtoupper((string) $row->direction) === 'C' ? 'credit' : 'debit',
		'amount' => cpcc_mobile_money($row->amount, $row->currency_code),
			'reference' => $row->reference !== null && $row->reference !== '' ? (string) $row->reference : null,
			'public_note' => !empty($row->public_note) ? (string)$row->public_note : null,
		'updated_at' => cpcc_mobile_iso(!empty($row->tms) ? $row->tms : $row->occurred_at),
	);
}

function cpcc_mobile_transactions($db, $context, $limit, $cursor, $updatedSince)
{
	$limit = cpcc_mobile_list_limit($limit); $updated = cpcc_mobile_updated_since($updatedSince); $filters = array('updated_since' => $updated); $filterHash = cpcc_mobile_filter_hash('transactions', $filters);
	$last = cpcc_mobile_cursor_decode($cursor, 'transactions', $context, $filterHash, $limit); $rows = array();
	foreach (cpcc_mobile_transaction_rows($db, $context) as $row) {
		$date = !empty($row->tms) ? (string) $row->tms : (string) $row->occurred_at;
		if ($updated !== '' && strcmp($date, $updated) < 0) continue;
		if ($last !== null && !(strcmp((string) $row->occurred_at, (string) $last['date']) < 0 || ((string) $row->occurred_at === (string) $last['date'] && (int) $row->logical_id < (int) $last['id']))) continue;
		$rows[] = array('_row' => $row, 'occurred_at' => (string) $row->occurred_at, 'logical_id' => (int) $row->logical_id); if (count($rows) >= $limit + 1) break;
	}
	$page = cpcc_mobile_page_meta($db, 'transactions', $context, $filters, $limit, $rows, 'occurred_at', 'logical_id'); $data = array(); foreach ($page['rows'] as $item) $data[] = cpcc_mobile_transaction_data($db, $context, $item['_row']);
	return cpcc_mobile_envelope($data, $page['meta']);
}

function cpcc_mobile_balances_data($db, $context, $projectId = null)
{
	$currencies = array(); $latest = '1970-01-01 00:00:00';
	$invoiceTotals = cpcc_financial_invoice_totals(cpcc_financial_customer_invoices($db, (int)$context['socid'], cpcc_mobile_base_currency(), $projectId !== null ? (int)$projectId : 0));
	foreach (cpcc_mobile_transaction_rows($db, $context) as $row) {
		if ($projectId !== null && (int) $row->fk_project !== (int) $projectId) continue;
		$currency = cpcc_mobile_currency($row->currency_code); if (!isset($currencies[$currency])) $currencies[$currency] = array('debit' => 0.0, 'credit' => 0.0, 'balance' => 0.0);
		$amount = (float) $row->amount; if (strtoupper((string) $row->direction) === 'C') { $currencies[$currency]['credit'] += $amount; $currencies[$currency]['balance'] += $amount; } else { $currencies[$currency]['debit'] += $amount; $currencies[$currency]['balance'] -= $amount; }
		$timestamp = !empty($row->tms) ? (string) $row->tms : (string) $row->occurred_at; if (strcmp($timestamp, $latest) > 0) $latest = $timestamp;
	}
	foreach($invoiceTotals as $currency=>$values) if(!isset($currencies[$currency])) $currencies[$currency]=array('debit'=>0.0,'credit'=>0.0,'balance'=>0.0);
	ksort($currencies); $result = array(); foreach ($currencies as $currency => $values) { $invoice=isset($invoiceTotals[$currency])?$invoiceTotals[$currency]:array('total'=>0.0,'paid'=>0.0,'remaining'=>0.0); $result[] = array('currency' => $currency, 'total_invoiced'=>cpcc_mobile_decimal($invoice['total']),'paid_or_funded'=>cpcc_mobile_decimal($invoice['paid']),'outstanding'=>cpcc_mobile_decimal($invoice['remaining']),'debit' => cpcc_mobile_decimal($values['debit']), 'credit' => cpcc_mobile_decimal($values['credit']), 'balance' => cpcc_mobile_decimal($values['balance']), 'updated_at' => cpcc_mobile_iso($latest)); }
	return $result;
}

function cpcc_mobile_balances($db, $context)
{
	return cpcc_mobile_envelope(cpcc_mobile_balances_data($db, $context));
}

function cpcc_mobile_invoice_rows($db, $context)
{
	$sql = "SELECT f.rowid,f.ref,f.datef,f.date_lim_reglement,f.fk_projet,f.fk_statut,f.paye,f.multicurrency_code,f.total_ht,f.total_tva,f.total_ttc,f.multicurrency_total_ht,f.multicurrency_total_tva,f.multicurrency_total_ttc,f.note_public,f.tms,COALESCE(pay.amount,0) AS paid_base,COALESCE(pay.multicurrency_amount,0) AS paid_multi,COALESCE(discount.amount,0) AS discount_base,COALESCE(discount.multicurrency_amount,0) AS discount_multi";
	$sql .= " FROM ".MAIN_DB_PREFIX."facture f LEFT JOIN (SELECT pf.fk_facture,SUM(pf.amount) amount,SUM(pf.multicurrency_amount) multicurrency_amount FROM ".MAIN_DB_PREFIX."paiement_facture pf INNER JOIN ".MAIN_DB_PREFIX."paiement ppay ON ppay.rowid=pf.fk_paiement WHERE ".cpcc_financial_payment_status_sql('ppay')." GROUP BY pf.fk_facture) pay ON pay.fk_facture=f.rowid";
	$sql .= " LEFT JOIN (".cpcc_financial_applied_credit_sql().") discount ON discount.fk_facture=f.rowid";
	$sql .= " WHERE f.entity=".(int) $context['entity']." AND f.fk_soc=".(int) $context['socid']." AND f.fk_statut>0 AND f.type IN (0,1,5) ORDER BY f.datef DESC,f.rowid DESC";
	$rows = cpcc_mobile_rows($db, cpcc_mobile_query_or_throw($db, $sql));
	foreach ($rows as $row) { $multi = trim((string) $row->multicurrency_code) !== ''; $row->display_currency = $multi ? cpcc_mobile_currency($row->multicurrency_code) : cpcc_mobile_base_currency(); $amounts=cpcc_financial_invoice_amounts($multi?(float)$row->multicurrency_total_ttc:(float)$row->total_ttc,$multi?(float)$row->paid_multi:(float)$row->paid_base,$multi?(float)$row->discount_multi:(float)$row->discount_base); $row->display_total=$amounts['total'];$row->display_paid=$amounts['paid'];$row->display_remaining=$amounts['remaining'];$row->display_direct_paid=$amounts['direct_paid'];$row->display_applied_credit=$amounts['applied_credit'];$row->display_subtotal=$multi?(float)$row->multicurrency_total_ht:(float)$row->total_ht;$row->display_tax=$multi?(float)$row->multicurrency_total_tva:(float)$row->total_tva; }
	return $rows;
}

function cpcc_mobile_invoice_data($db, $context, $row)
{
	return array('id' => cpcc_mobile_public_id($db, 'invoice', $context, (int) $row->rowid), 'project_id' => !empty($row->fk_projet) ? cpcc_mobile_public_id($db, 'project', $context, (int) $row->fk_projet) : null, 'ref' => (string) $row->ref, 'issued_at' => cpcc_mobile_iso($row->datef), 'due_at' => cpcc_mobile_iso($row->date_lim_reglement), 'status' => cpcc_mobile_invoice_status($row, $row->display_remaining), 'total' => cpcc_mobile_money($row->display_total, $row->display_currency), 'paid' => cpcc_mobile_money($row->display_paid, $row->display_currency), 'remaining' => cpcc_mobile_money($row->display_remaining, $row->display_currency), 'updated_at' => cpcc_mobile_iso($row->tms));
}

function cpcc_mobile_service_invoices($db, $context, $limit, $cursor, $updatedSince)
{
	$limit = cpcc_mobile_list_limit($limit); $updated = cpcc_mobile_updated_since($updatedSince); $filters = array('updated_since' => $updated); $hash = cpcc_mobile_filter_hash('service_invoices', $filters); $last = cpcc_mobile_cursor_decode($cursor, 'service_invoices', $context, $hash, $limit); $wrapped = array();
	foreach (cpcc_mobile_invoice_rows($db, $context) as $row) { if ($updated !== '' && strcmp((string) $row->tms, $updated) < 0) continue; if ($last !== null && !(strcmp((string) $row->datef, (string) $last['date']) < 0 || ((string) $row->datef === (string) $last['date'] && (int) $row->rowid < (int) $last['id']))) continue; $wrapped[] = array('_row' => $row, 'datef' => (string) $row->datef, 'rowid' => (int) $row->rowid); if (count($wrapped) >= $limit + 1) break; }
	$page = cpcc_mobile_page_meta($db, 'service_invoices', $context, $filters, $limit, $wrapped, 'datef', 'rowid'); $data = array(); foreach ($page['rows'] as $item) $data[] = cpcc_mobile_invoice_data($db, $context, $item['_row']); return cpcc_mobile_envelope($data, $page['meta']);
}

function cpcc_mobile_service_invoice_detail($db, $context, $publicId)
{
	$invoiceId = cpcc_mobile_resolve_public_id($db, 'invoice', $context, $publicId);
	if (!$invoiceId) cpcc_mobile_throw(404, 'not_found');
	$invoice = null;
	foreach (cpcc_mobile_invoice_rows($db, $context) as $row) if ((int)$row->rowid === (int)$invoiceId) { $invoice = $row; break; }
	if (!$invoice) cpcc_mobile_throw(404, 'not_found');
	$data = cpcc_mobile_invoice_data($db, $context, $invoice);
	$data['subtotal'] = cpcc_mobile_money($invoice->display_subtotal, $invoice->display_currency);
	$data['tax'] = cpcc_mobile_money($invoice->display_tax, $invoice->display_currency);
	$data['direct_paid'] = cpcc_mobile_money($invoice->display_direct_paid, $invoice->display_currency);
	$data['applied_credit'] = cpcc_mobile_money($invoice->display_applied_credit, $invoice->display_currency);
	$data['public_note'] = trim((string)$invoice->note_public) !== '' ? str_replace('\\n', "\n", (string)$invoice->note_public) : null;
	$data['order_ref'] = null;
	$orderSql = "SELECT c.ref FROM ".MAIN_DB_PREFIX."element_element ee INNER JOIN ".MAIN_DB_PREFIX."commande c ON c.rowid=ee.fk_source WHERE ee.sourcetype='commande' AND ee.targettype='facture' AND ee.fk_target=".(int)$invoiceId." AND c.entity=".(int)$context['entity']." AND c.fk_soc=".(int)$context['socid']." ORDER BY c.rowid LIMIT 1";
	$orderResult=$db->query($orderSql);$order=$orderResult?$db->fetch_object($orderResult):null;if($order)$data['order_ref']=(string)$order->ref;
	$credits=cpcc_financial_applied_credits($db,(int)$context['socid'],(int)$invoiceId,cpcc_mobile_base_currency());$creditLineIds=array();foreach($credits as $credit)if(!empty($credit['line_id']))$creditLineIds[]=(int)$credit['line_id'];
	$lineSql="SELECT fd.rowid,fd.description,fd.label,fd.qty,fd.subprice,fd.remise_percent,fd.tva_tx,fd.total_ht,fd.multicurrency_subprice,fd.multicurrency_total_ht,COALESCE(NULLIF(TRIM(u.short_label),''),NULLIF(TRIM(u.label),''),NULLIF(TRIM(u.code),'')) AS unit_public FROM ".MAIN_DB_PREFIX."facturedet fd LEFT JOIN ".MAIN_DB_PREFIX."c_units u ON u.rowid=fd.fk_unit WHERE fd.fk_facture=".(int)$invoiceId;
	if($creditLineIds)$lineSql.=" AND fd.rowid NOT IN (".implode(',',$creditLineIds).")";$lineSql.=" ORDER BY fd.rang,fd.rowid";
	$data['lines']=array();$discount=0.0;$lineResult=$db->query($lineSql);while($lineResult&&($line=$db->fetch_object($lineResult))){$multi=trim((string)$invoice->multicurrency_code)!=='';$price=$multi&&(float)$line->multicurrency_subprice!=0.0?(float)$line->multicurrency_subprice:(float)$line->subprice;$total=$multi&&(float)$line->multicurrency_total_ht!=0.0?(float)$line->multicurrency_total_ht:(float)$line->total_ht;$discount+=max(0.0,((float)$line->qty*$price)-$total);$data['lines'][]=array('description'=>str_replace('\\n',"\n",trim((string)($line->description?:$line->label))),'quantity'=>(string)$line->qty,'unit'=>trim((string)$line->unit_public)!==''?(string)$line->unit_public:null,'unit_price'=>cpcc_mobile_money($price,$invoice->display_currency),'tax_rate'=>(string)$line->tva_tx,'discount_percent'=>(string)$line->remise_percent,'total'=>cpcc_mobile_money($total,$invoice->display_currency));}
	$data['discount']=cpcc_mobile_money($discount,$invoice->display_currency);
	$publicNoteSql=cpcc_financial_payment_public_note_sql($db,'p','payment_extra');$paymentSql="SELECT p.datep,p.ref,p.num_paiement,pf.amount,pf.multicurrency_amount,".$publicNoteSql['select']." FROM ".MAIN_DB_PREFIX."paiement p INNER JOIN ".MAIN_DB_PREFIX."paiement_facture pf ON pf.fk_paiement=p.rowid".$publicNoteSql['join']." WHERE pf.fk_facture=".(int)$invoiceId." AND ".cpcc_financial_payment_status_sql('p')." ORDER BY p.datep,p.rowid";
	$data['payments']=array();$paymentResult=$db->query($paymentSql);while($paymentResult&&($payment=$db->fetch_object($paymentResult))){$amount=trim((string)$invoice->multicurrency_code)!==''&&(float)$payment->multicurrency_amount!=0.0?(float)$payment->multicurrency_amount:(float)$payment->amount;$data['payments'][]=array('date'=>cpcc_mobile_iso($payment->datep),'reference'=>(string)($payment->num_paiement?:$payment->ref),'amount'=>cpcc_mobile_money($amount,$invoice->display_currency),'public_note'=>trim((string)$payment->public_note)!==''?(string)$payment->public_note:null);}
	return cpcc_mobile_envelope($data);
}

function cpcc_mobile_orders($db, $context, $limit, $cursor, $updatedSince)
{
	$limit = cpcc_mobile_list_limit($limit); $updated = cpcc_mobile_updated_since($updatedSince); $filters = array('updated_since' => $updated); $hash = cpcc_mobile_filter_hash('orders', $filters); $last = cpcc_mobile_cursor_decode($cursor, 'orders', $context, $hash, $limit);
	$sql = "SELECT c.rowid,c.ref,c.date_commande,c.fk_projet,c.fk_statut,c.total_ttc,c.multicurrency_code,c.multicurrency_total_ttc,c.tms,COALESCE(ordpay.paid_base,0) AS paid_base,COALESCE(ordpay.paid_multi,0) AS paid_multi FROM ".MAIN_DB_PREFIX."commande c";
	$sql .= " LEFT JOIN (SELECT ee.fk_source AS fk_order,f.entity AS invoice_entity,f.fk_soc AS invoice_socid,SUM(pf.amount) AS paid_base,SUM(pf.multicurrency_amount) AS paid_multi FROM ".MAIN_DB_PREFIX."element_element ee INNER JOIN ".MAIN_DB_PREFIX."facture f ON f.rowid=ee.fk_target INNER JOIN ".MAIN_DB_PREFIX."paiement_facture pf ON pf.fk_facture=f.rowid WHERE ee.sourcetype='commande' AND ee.targettype='facture' GROUP BY ee.fk_source,f.entity,f.fk_soc) ordpay ON ordpay.fk_order=c.rowid AND ordpay.invoice_entity=c.entity AND ordpay.invoice_socid=c.fk_soc";
	$sql .= " WHERE c.entity=".(int) $context['entity']." AND c.fk_soc=".(int) $context['socid']; if ($updated !== '') $sql .= " AND c.tms>='".$db->escape($updated)."'"; $sql .= cpcc_mobile_list_cursor_clause($db, $last, 'c.date_commande', 'c.rowid')." ORDER BY c.date_commande DESC,c.rowid DESC LIMIT ".($limit + 1);
	$raw = cpcc_mobile_rows($db, cpcc_mobile_query_or_throw($db, $sql)); $wrapped = array(); foreach ($raw as $row) $wrapped[] = array('_row' => $row, 'date_commande' => (string) $row->date_commande, 'rowid' => (int) $row->rowid); $page = cpcc_mobile_page_meta($db, 'orders', $context, $filters, $limit, $wrapped, 'date_commande', 'rowid'); $data = array();
	foreach ($page['rows'] as $item) { $row = $item['_row']; $multi = trim((string) $row->multicurrency_code) !== ''; $currency = $multi ? cpcc_mobile_currency($row->multicurrency_code) : cpcc_mobile_base_currency(); $total = $multi ? (float) $row->multicurrency_total_ttc : (float) $row->total_ttc; $paid = $multi ? (float) $row->paid_multi : (float) $row->paid_base; $paid = max(0, min($total, $paid)); $data[] = array('id' => cpcc_mobile_public_id($db, 'order', $context, (int) $row->rowid), 'project_id' => !empty($row->fk_projet) ? cpcc_mobile_public_id($db, 'project', $context, (int) $row->fk_projet) : null, 'ref' => (string) $row->ref, 'ordered_at' => cpcc_mobile_iso($row->date_commande), 'status' => cpcc_mobile_order_status($row->fk_statut), 'total' => cpcc_mobile_money($total, $currency), 'paid' => cpcc_mobile_money($paid, $currency), 'remaining' => cpcc_mobile_money(max(0, $total - $paid), $currency), 'updated_at' => cpcc_mobile_iso($row->tms)); }
	return cpcc_mobile_envelope($data, $page['meta']);
}

function cpcc_mobile_project_document_ids($db, $context, $projectId)
{
	$ids = array();
	$sql = "SELECT a.rowid FROM ".MAIN_DB_PREFIX."cpkanban_asset a INNER JOIN ".MAIN_DB_PREFIX."cpkanban_card c ON c.rowid=a.fk_card AND c.entity=a.entity AND c.fk_soc=a.fk_soc INNER JOIN ".MAIN_DB_PREFIX."projet p ON p.rowid=c.fk_project AND p.entity=c.entity AND p.fk_soc=c.fk_soc WHERE a.entity=".(int) $context['entity']." AND a.fk_soc=".(int) $context['socid']." AND p.rowid=".(int) $projectId." AND c.status=1 AND c.cust_visible=1 AND a.status=1 AND a.customer_visible=1 AND a.review_status IN ('approved','final_received')";
	$result = $db->query($sql); while ($result && ($row = $db->fetch_object($result))) $ids[] = cpcc_mobile_public_id($db, 'document', $context, (int) $row->rowid * 2);
	return $ids;
}

function cpcc_mobile_document_rows($db, $context, $scope = '')
{
	$scope = (string) $scope;
	if (!in_array($scope, array('', 'shipment'), true)) cpcc_mobile_throw(422, 'validation', array('scope'));
	$queries = array();
	$queries[] = "SELECT a.rowid AS source_id,(a.rowid * 2) AS logical_id,'project_asset' AS source_type,p.rowid AS project_id,p.ref AS project_ref,p.title AS project_title,a.fk_shipment AS shipment_id,s.ref AS shipment_ref,NULL AS report_id,a.original_name,a.stored_name,a.mime_type,a.file_size,a.version_no,a.date_creation AS approved_at,a.tms,a.fk_card,NULL AS operation_ref,NULL AS file_sha256,NULL AS item_kind FROM ".MAIN_DB_PREFIX."cpkanban_asset a INNER JOIN ".MAIN_DB_PREFIX."cpkanban_card c ON c.rowid=a.fk_card AND c.entity=a.entity AND c.fk_soc=a.fk_soc INNER JOIN ".MAIN_DB_PREFIX."projet p ON p.rowid=c.fk_project AND p.entity=c.entity AND p.fk_soc=c.fk_soc LEFT JOIN ".MAIN_DB_PREFIX."chinaplanetlogistics s ON s.rowid=a.fk_shipment AND s.entity=a.entity AND s.fk_soc=a.fk_soc WHERE a.entity=".(int) $context['entity']." AND a.fk_soc=".(int) $context['socid']." AND c.status=1 AND c.cust_visible=1 AND a.status=1 AND a.customer_visible=1 AND a.review_status IN ('approved','final_received')";
	$queries[] = "SELECT d.rowid AS source_id,(d.rowid * 2) + 1 AS logical_id,'operation_report_attachment' AS source_type,o.fk_project AS project_id,p.ref AS project_ref,p.title AS project_title,o.fk_shipment AS shipment_id,s.ref AS shipment_ref,o.rowid AS report_id,d.original_name,d.stored_name,d.mime_type,d.file_size,d.version_no,COALESCE(d.date_review,d.date_creation) AS approved_at,d.tms,NULL AS fk_card,o.ref AS operation_ref,NULL AS file_sha256,NULL AS item_kind FROM ".MAIN_DB_PREFIX."cpo_document d INNER JOIN ".MAIN_DB_PREFIX."cpo_operation o ON o.rowid=d.fk_operation AND o.entity=d.entity LEFT JOIN ".MAIN_DB_PREFIX."projet p ON p.rowid=o.fk_project AND p.entity=o.entity AND p.fk_soc=o.fk_soc LEFT JOIN ".MAIN_DB_PREFIX."chinaplanetlogistics s ON s.rowid=o.fk_shipment AND s.entity=o.entity AND s.fk_soc=o.fk_soc WHERE d.entity=".(int) $context['entity']." AND o.fk_soc=".(int) $context['socid']." AND o.status=1 AND o.customer_visible=1 AND o.service_type<>'quality_control' AND d.visibility='customer_after_approval' AND d.review_status IN ('approved','final_received') AND d.is_current=1";
	if (cpcc_mobile_table_exists($db, 'cpl_shipment_public_document')) {
			$queries[] = "SELECT g.rowid AS source_id,(0 - g.rowid) AS logical_id,'shipment_gallery' AS source_type,s.fk_project AS project_id,p.ref AS project_ref,p.title AS project_title,g.fk_shipment AS shipment_id,s.ref AS shipment_ref,NULL AS report_id,g.original_name,g.storage_key AS stored_name,g.mime_type,g.file_size,1 AS version_no,g.date_publication AS approved_at,g.tms,NULL AS fk_card,NULL AS operation_ref,g.file_sha256,g.item_kind FROM ".MAIN_DB_PREFIX."cpl_shipment_public_document g INNER JOIN ".MAIN_DB_PREFIX."chinaplanetlogistics s ON s.rowid=g.fk_shipment AND s.entity=g.entity AND s.fk_soc=g.fk_soc LEFT JOIN ".MAIN_DB_PREFIX."projet p ON p.rowid=s.fk_project AND p.entity=s.entity AND p.fk_soc=s.fk_soc WHERE g.entity=".(int) $context['entity']." AND g.fk_soc=".(int) $context['socid']." AND g.customer_visible=1 AND g.status=1";
		}
	if (cpcc_mobile_table_exists($db, 'cpcc_customer_document')) {
			$queries[] = "SELECT d.rowid AS source_id,(0 - 1000000000 - d.rowid) AS logical_id,'core_document' AS source_type,d.fk_project AS project_id,p.ref AS project_ref,p.title AS project_title,d.fk_shipment AS shipment_id,s.ref AS shipment_ref,NULL AS report_id,d.original_name,d.relative_path AS stored_name,d.mime_type,d.file_size,1 AS version_no,d.date_publication AS approved_at,d.tms,NULL AS fk_card,d.source_ref AS operation_ref,d.file_sha256,NULL AS item_kind FROM ".MAIN_DB_PREFIX."cpcc_customer_document d LEFT JOIN ".MAIN_DB_PREFIX."projet p ON p.rowid=d.fk_project AND p.entity=d.entity AND p.fk_soc=d.fk_soc LEFT JOIN ".MAIN_DB_PREFIX."chinaplanetlogistics s ON s.rowid=d.fk_shipment AND s.entity=d.entity AND s.fk_soc=d.fk_soc WHERE d.entity=".(int)$context['entity']." AND d.fk_soc=".(int)$context['socid']." AND d.customer_visible=1 AND d.status=1";
		}
	if (cpcc_mobile_table_exists($db, 'cpcc_export_document')) {
		$queries[] = "SELECT d.rowid AS source_id,(0 - 2000000000 - d.rowid) AS logical_id,'commercial_document' AS source_type,d.fk_project AS project_id,p.ref AS project_ref,p.title AS project_title,d.fk_shipment AS shipment_id,s.ref AS shipment_ref,NULL AS report_id,d.ref AS original_name,'' AS stored_name,'text/html' AS mime_type,0 AS file_size,1 AS version_no,d.document_date AS approved_at,d.tms,NULL AS fk_card,d.document_type AS operation_ref,NULL AS file_sha256,'record' AS item_kind FROM ".MAIN_DB_PREFIX."cpcc_export_document d LEFT JOIN ".MAIN_DB_PREFIX."projet p ON p.rowid=d.fk_project AND p.entity=d.entity AND p.fk_soc=d.fk_soc LEFT JOIN ".MAIN_DB_PREFIX."chinaplanetlogistics s ON s.rowid=d.fk_shipment AND s.entity=d.entity AND s.fk_soc=d.fk_soc WHERE d.entity=".(int)$context['entity']." AND d.fk_soc=".(int)$context['socid']." AND d.customer_visible=1 AND d.status=1";
	}
	$sql = "SELECT * FROM (".implode(' UNION ALL ', $queries).") mobile_docs";
	if ($scope === 'shipment') $sql .= " WHERE shipment_id IS NOT NULL AND source_type<>'commercial_document'";
	$sql .= " ORDER BY tms DESC,logical_id DESC";
	return cpcc_mobile_rows($db, cpcc_mobile_query_or_throw($db, $sql));
}

function cpcc_mobile_document_data($db, $context, $row)
{
	$resource = 'document';
	$objectId = (int) $row->logical_id;
	if ($row->source_type === 'shipment_gallery') {
		$resource = 'shipment_gallery_item';
		$objectId = (int) $row->source_id;
	} elseif ($row->source_type === 'core_document') {
		// Core publications use their positive registry ID; negative logical IDs are list-sort keys only.
		$resource = 'core_customer_document';
		$objectId = (int) $row->source_id;
	} elseif ($row->source_type === 'commercial_document') {
		$resource = 'commercial_document';
		$objectId = (int) $row->source_id;
	}
	$kind = !empty($row->item_kind) ? (string) $row->item_kind : cpcc_portal_gallery_item_kind($row->mime_type);
	return array('id' => cpcc_mobile_public_id($db, $resource, $context, $objectId), 'source_type' => (string) $row->source_type, 'kind' => $kind, 'project_id' => !empty($row->project_id) ? cpcc_mobile_public_id($db, 'project', $context, (int) $row->project_id) : null, 'project_ref' => !empty($row->project_ref) ? (string) $row->project_ref : null, 'project_title' => !empty($row->project_title) ? (string) $row->project_title : null, 'shipment_id' => !empty($row->shipment_id) ? cpcc_mobile_public_id($db, 'shipment', $context, (int) $row->shipment_id) : null, 'shipment_ref' => !empty($row->shipment_ref) ? (string) $row->shipment_ref : null, 'report_id' => !empty($row->report_id) ? cpcc_mobile_public_id($db, 'report', $context, (int) $row->report_id) : null, 'name' => basename((string) $row->original_name), 'mime_type' => (string) $row->mime_type, 'size_bytes' => max(0, (int) $row->file_size), 'version' => max(1, (int) $row->version_no), 'approved_at' => cpcc_mobile_iso($row->approved_at), 'updated_at' => cpcc_mobile_iso($row->tms));
}

function cpcc_mobile_documents($db, $context, $limit, $cursor, $updatedSince, $scope = '')
{
	$scope = (string) $scope;
	if ($scope === '' && function_exists('GETPOST')) $scope = (string) GETPOST('scope', 'alpha');
	if (!in_array($scope, array('', 'shipment'), true)) cpcc_mobile_throw(422, 'validation', array('scope'));
	$limit = cpcc_mobile_list_limit($limit); $updated = cpcc_mobile_updated_since($updatedSince); $filters = array('updated_since' => $updated, 'scope' => $scope); $hash = cpcc_mobile_filter_hash('documents', $filters); $last = cpcc_mobile_cursor_decode($cursor, 'documents', $context, $hash, $limit); $wrapped = array();
	foreach (cpcc_mobile_document_rows($db, $context, $scope) as $row) { if ($updated !== '' && strcmp((string) $row->tms, $updated) < 0) continue; if ($last !== null && !(strcmp((string) $row->tms, (string) $last['date']) < 0 || ((string) $row->tms === (string) $last['date'] && (int) $row->logical_id < (int) $last['id']))) continue; $wrapped[] = array('_row' => $row, 'tms' => (string) $row->tms, 'logical_id' => (int) $row->logical_id); if (count($wrapped) >= $limit + 1) break; }
	$page = cpcc_mobile_page_meta($db, 'documents', $context, $filters, $limit, $wrapped, 'tms', 'logical_id'); $data = array(); foreach ($page['rows'] as $item) $data[] = cpcc_mobile_document_data($db, $context, $item['_row']); return cpcc_mobile_envelope($data, $page['meta']);
}

function cpcc_mobile_shipment_gallery($db, $context, $shipmentId)
{
	$data = array();
	foreach (cpcc_mobile_document_rows($db, $context, 'shipment') as $row) if ((int) $row->shipment_id === (int) $shipmentId) $data[] = cpcc_mobile_document_data($db, $context, $row);
	return $data;
}

function cpcc_mobile_document_record($db, $context, $publicId)
{
	$logicalId = cpcc_mobile_resolve_public_id($db, 'document', $context, $publicId);
	if ($logicalId !== null && $logicalId > 0) foreach (cpcc_mobile_document_rows($db, $context) as $row) if ($row->source_type !== 'shipment_gallery' && $row->source_type !== 'core_document' && (int) $row->logical_id === (int) $logicalId) return $row;
	$galleryId = cpcc_mobile_resolve_public_id($db, 'shipment_gallery_item', $context, $publicId);
	if ($galleryId !== null && $galleryId > 0) foreach (cpcc_mobile_document_rows($db, $context, 'shipment') as $row) if ($row->source_type === 'shipment_gallery' && (int) $row->source_id === (int) $galleryId) return $row;
	$coreId = cpcc_mobile_resolve_public_id($db, 'core_customer_document', $context, $publicId);
	if ($coreId !== null && $coreId > 0) foreach (cpcc_mobile_document_rows($db, $context) as $row) if ($row->source_type === 'core_document' && (int) $row->source_id === (int) $coreId) return $row;
	return null;
}

function cpcc_mobile_safe_file_record($db, $context, $publicId, $requiredReportId = 0)
{
	global $conf;
	$row = cpcc_mobile_document_record($db, $context, $publicId); if (!$row) cpcc_mobile_throw(404, 'not_found');
	if ((int) $requiredReportId > 0 && (int) $row->report_id !== (int) $requiredReportId) cpcc_mobile_throw(404, 'not_found');
	if ($row->source_type === 'shipment_gallery') {
		$path = cpcc_portal_resolve_shipment_doc_path((int) $row->shipment_id, (string) $row->shipment_ref, (string) $row->stored_name);
		if ($path === '' || !preg_match('/^[a-f0-9]{64}$/', (string) $row->file_sha256)) cpcc_mobile_throw(404, 'not_found');
		$actualHash = hash_file('sha256', $path); if (!is_string($actualHash) || !hash_equals((string) $row->file_sha256, $actualHash)) cpcc_mobile_throw(404, 'not_found');
	} elseif ($row->source_type === 'core_document') {
		$coreFile = cpcc_customer_document_core_file($db, (int)$context['socid'], (int)$row->source_id);
		if (!$coreFile) cpcc_mobile_throw(404, 'not_found');
		$path = $coreFile['path'];
	} elseif ($row->source_type === 'project_asset') {
		$root = DOL_DATA_ROOT.'/cpkanban/card-'.(int) $row->fk_card;
		$rootReal = realpath($root); $path = realpath($root.'/'.(string) $row->stored_name);
		if ($rootReal === false || $path === false || !is_file($path) || !is_readable($path) || strpos($path, rtrim($rootReal, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR) !== 0) cpcc_mobile_throw(404, 'not_found');
	} else {
		$operationRoot = !empty($conf->chinaplanetoperations->dir_output) ? (string) $conf->chinaplanetoperations->dir_output : DOL_DATA_ROOT.'/chinaplanetoperations';
		$root = rtrim($operationRoot, '/').'/documents/'.(int) $context['entity'].'/'.(string) $row->operation_ref;
		$rootReal = realpath($root); $path = realpath($root.'/'.(string) $row->stored_name);
		if ($rootReal === false || $path === false || !is_file($path) || !is_readable($path) || strpos($path, rtrim($rootReal, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR) !== 0) cpcc_mobile_throw(404, 'not_found');
	}
	$size = filesize($path); if ($size === false || (int) $size !== (int) $row->file_size) cpcc_mobile_throw(404, 'not_found');
	$filename = basename(str_replace(array("\r", "\n", '"'), '', (string) $row->original_name)); if ($filename === '') $filename = 'document';
	$allowedMimes = array('application/pdf','image/jpeg','image/png','image/webp','video/mp4','video/quicktime','text/plain','text/csv','application/msword','application/vnd.openxmlformats-officedocument.wordprocessingml.document','application/vnd.ms-excel','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','application/vnd.ms-powerpoint','application/vnd.openxmlformats-officedocument.presentationml.presentation');
	$mime = in_array((string) $row->mime_type, $allowedMimes, true) ? (string) $row->mime_type : 'application/octet-stream';
	return array('path' => $path, 'size' => (int) $size, 'filename' => $filename, 'mime_type' => $mime);
}

function cpcc_mobile_stream_file($file)
{
	$start = 0; $end = (int) $file['size'] - 1; $status = 200; $headers = cpcc_mobile_request_headers();
	if (isset($headers['range']) && trim($headers['range']) !== '') {
		$range = trim($headers['range']);
		if (strpos($range, ',') !== false || !preg_match('/^bytes=(\d*)-(\d*)$/', $range, $matches)) { header('Content-Range: bytes */'.(int) $file['size']); cpcc_mobile_throw(416, 'validation', array('range')); }
		if ($matches[1] === '' && $matches[2] === '') { header('Content-Range: bytes */'.(int) $file['size']); cpcc_mobile_throw(416, 'validation', array('range')); }
		if ($matches[1] === '') { $suffix = (int) $matches[2]; if ($suffix <= 0) cpcc_mobile_throw(416, 'validation', array('range')); $start = max(0, (int) $file['size'] - $suffix); }
		else { $start = (int) $matches[1]; if ($matches[2] !== '') $end = (int) $matches[2]; }
		if ($start < 0 || $start >= (int) $file['size'] || $end < $start || $end >= (int) $file['size']) { header('Content-Range: bytes */'.(int) $file['size']); cpcc_mobile_throw(416, 'validation', array('range')); }
		$status = 206;
	}
	$length = $end - $start + 1; http_response_code($status);
	header('Content-Type: '.(string) $file['mime_type']); header('Content-Length: '.$length); header("Content-Disposition: attachment; filename*=UTF-8''".rawurlencode((string) $file['filename'])); header('X-Content-Type-Options: nosniff'); header('Cache-Control: private, no-store'); header('Referrer-Policy: no-referrer'); header('Accept-Ranges: bytes');
	if ($status === 206) header('Content-Range: bytes '.$start.'-'.$end.'/'.(int) $file['size']);
	$handle = fopen($file['path'], 'rb'); if (!$handle) cpcc_mobile_throw(404, 'not_found'); fseek($handle, $start); $remaining = $length;
	while ($remaining > 0 && !feof($handle)) { $chunk = fread($handle, min(8192, $remaining)); if ($chunk === false) break; echo $chunk; $remaining -= strlen($chunk); }
	fclose($handle); exit;
}

function cpcc_mobile_commercial_summary($db, $context, $row)
{
	return array('id' => cpcc_mobile_public_id($db, 'commercial_document', $context, (int) $row->rowid), 'project_id' => !empty($row->fk_project) ? cpcc_mobile_public_id($db, 'project', $context, (int) $row->fk_project) : null, 'shipment_id' => !empty($row->fk_shipment) ? cpcc_mobile_public_id($db, 'shipment', $context, (int) $row->fk_shipment) : null, 'ref' => (string) $row->ref, 'document_type' => cpcc_mobile_commercial_type($row->document_type), 'document_date' => substr((string) $row->document_date, 0, 10), 'currency' => trim((string) $row->currency_code) !== '' ? cpcc_mobile_currency($row->currency_code) : null, 'updated_at' => cpcc_mobile_iso($row->tms));
}

function cpcc_mobile_commercial_type($type)
{
	$allowed = array('commercial_invoice','packing_list','bill_of_lading','cargox','certificate_origin','inspection','other'); return in_array((string) $type, $allowed, true) ? (string) $type : 'other';
}

function cpcc_mobile_commercial_documents($db, $context, $limit, $cursor, $updatedSince)
{
	$limit = cpcc_mobile_list_limit($limit); $updated = cpcc_mobile_updated_since($updatedSince); $filters = array('updated_since' => $updated); $hash = cpcc_mobile_filter_hash('commercial_documents', $filters); $last = cpcc_mobile_cursor_decode($cursor, 'commercial_documents', $context, $hash, $limit);
	$sql = "SELECT d.rowid,d.fk_project,d.fk_shipment,d.ref,d.document_type,d.document_date,d.currency_code,d.tms FROM ".MAIN_DB_PREFIX."cpcc_export_document d WHERE d.entity=".(int) $context['entity']." AND d.fk_soc=".(int) $context['socid']." AND d.status=1 AND d.customer_visible=1"; if ($updated !== '') $sql .= " AND d.tms>='".$db->escape($updated)."'"; $sql .= cpcc_mobile_list_cursor_clause($db, $last, 'd.tms', 'd.rowid')." ORDER BY d.tms DESC,d.rowid DESC LIMIT ".($limit + 1);
	$raw = cpcc_mobile_rows($db, cpcc_mobile_query_or_throw($db, $sql)); $wrapped = array(); foreach ($raw as $row) $wrapped[] = array('_row' => $row, 'tms' => (string) $row->tms, 'rowid' => (int) $row->rowid); $page = cpcc_mobile_page_meta($db, 'commercial_documents', $context, $filters, $limit, $wrapped, 'tms', 'rowid'); $data = array(); foreach ($page['rows'] as $item) $data[] = cpcc_mobile_commercial_summary($db, $context, $item['_row']); return cpcc_mobile_envelope($data, $page['meta']);
}

function cpcc_mobile_commercial_document_detail($db, $context, $publicId)
{
	$id = cpcc_mobile_resolve_public_id($db, 'commercial_document', $context, $publicId); if (!$id) cpcc_mobile_throw(404, 'not_found');
	$sql = "SELECT d.rowid,d.fk_project,d.fk_shipment,d.ref,d.document_type,d.document_date,d.currency_code,d.consignee_name,d.port_loading,d.port_destination,d.incoterm,d.notes_public,d.tms FROM ".MAIN_DB_PREFIX."cpcc_export_document d WHERE d.rowid=".(int) $id." AND d.entity=".(int) $context['entity']." AND d.fk_soc=".(int) $context['socid']." AND d.status=1 AND d.customer_visible=1 LIMIT 1";
	$result = cpcc_mobile_query_or_throw($db, $sql); $row = $db->fetch_object($result); if (!$row) cpcc_mobile_throw(404, 'not_found'); $data = cpcc_mobile_commercial_summary($db, $context, $row);
	$data['consignee'] = $row->consignee_name !== null && $row->consignee_name !== '' ? (string) $row->consignee_name : null; $data['port_loading'] = $row->port_loading !== null && $row->port_loading !== '' ? (string) $row->port_loading : null; $data['port_destination'] = $row->port_destination !== null && $row->port_destination !== '' ? (string) $row->port_destination : null; $data['incoterm'] = $row->incoterm !== null && $row->incoterm !== '' ? (string) $row->incoterm : null; $data['public_notes'] = $row->notes_public !== null && $row->notes_public !== '' ? (string) $row->notes_public : null; $data['lines'] = array();
	$lineSql = "SELECT position,description,hs_code,quantity,unit,unit_price,total_amount,cartons,net_weight,gross_weight,country_origin FROM ".MAIN_DB_PREFIX."cpcc_export_document_line WHERE fk_document=".(int) $id." ORDER BY position,rowid LIMIT 1000"; $lines = $db->query($lineSql);
	$lineCurrency = $data['currency'] !== null ? $data['currency'] : cpcc_mobile_base_currency();
	while ($lines && ($line = $db->fetch_object($lines))) $data['lines'][] = array('position' => max(1, (int) $line->position), 'description' => (string) $line->description, 'hs_code' => $line->hs_code !== null && $line->hs_code !== '' ? (string) $line->hs_code : null, 'quantity' => cpcc_mobile_decimal($line->quantity), 'unit' => $line->unit !== null && $line->unit !== '' ? (string) $line->unit : null, 'unit_price' => cpcc_mobile_money($line->unit_price, $lineCurrency), 'total' => cpcc_mobile_money($line->total_amount, $lineCurrency), 'cartons' => $line->cartons !== null ? cpcc_mobile_decimal($line->cartons) : null, 'net_weight' => $line->net_weight !== null ? cpcc_mobile_decimal($line->net_weight) : null, 'gross_weight' => $line->gross_weight !== null ? cpcc_mobile_decimal($line->gross_weight) : null, 'country_of_origin' => $line->country_origin !== null && $line->country_origin !== '' ? (string) $line->country_origin : null);
	return cpcc_mobile_envelope($data);
}

function cpcc_mobile_report_type($type)
{
	$allowed = array('order','factory_audit','quality_control','shipping_inquiry','shipping_booking','loading','customs','claim','other'); return in_array((string) $type, $allowed, true) ? (string) $type : 'other';
}

function cpcc_mobile_report_stage($stage)
{
	$stage = strtolower(trim((string) $stage)); $map = array('new' => array('جديد', 'New'), 'planned' => array('مخطط', 'Planned'), 'in_progress' => array('قيد التنفيذ', 'In progress'), 'waiting' => array('بانتظار إجراء', 'Waiting'), 'under_review' => array('قيد المراجعة', 'Under review'), 'completed' => array('مكتمل', 'Completed'), 'cancelled' => array('ملغي', 'Cancelled'));
	if (!isset($map[$stage])) { $stage = 'in_progress'; } return array('code' => $stage, 'label' => cpcc_mobile_label($map[$stage][0], $map[$stage][1]));
}

function cpcc_mobile_report_summary($db, $context, $row)
{
	$isQc = (string) $row->service_type === 'quality_control' && !empty($row->publication_uuid);
	$projection = null;
	if ($isQc && isset($row->publication_json, $row->publication_sha256) && preg_match('/^[a-f0-9]{64}$/', (string)$row->publication_sha256) && hash_equals((string)$row->publication_sha256, hash('sha256', (string)$row->publication_json))) {
		$decoded = json_decode((string)$row->publication_json, true);
		if (is_array($decoded)) $projection = cpcc_qc_customer_projection($decoded);
	}
	$ref = $projection ? $projection['operation']['ref'] : (string)$row->ref;
	$title = $projection ? $projection['operation']['title'] : (string)$row->title;
	$data = array('id' => cpcc_mobile_public_id($db, 'report', $context, (int) $row->rowid), 'project_id' => !empty($row->fk_project) ? cpcc_mobile_public_id($db, 'project', $context, (int) $row->fk_project) : null, 'ref' => $ref, 'report_type' => cpcc_mobile_report_type($row->service_type), 'title' => $title, 'stage' => $isQc ? array('code'=>'published','label'=>cpcc_mobile_label('منشور','Published')) : cpcc_mobile_report_stage($row->stage), 'progress_percent' => $isQc ? 100 : max(0, min(100, (int) $row->progress)), 'next_action' => $isQc ? null : ($row->next_action !== null && $row->next_action !== '' ? (string) $row->next_action : null), 'due_at' => $isQc ? null : cpcc_mobile_iso($row->date_deadline), 'updated_at' => cpcc_mobile_iso($row->tms));
	if ($isQc) { $data['overall_result'] = (string) $row->overall_result; $data['publication_version'] = (int) $row->publication_version; $data['inspection_at'] = cpcc_mobile_iso($row->inspection_at); $data['published_at'] = cpcc_mobile_iso($row->date_publication); }
	return $data;
}

function cpcc_mobile_reports($db, $context, $limit, $cursor, $updatedSince)
{
	$limit = cpcc_mobile_list_limit($limit); $updated = cpcc_mobile_updated_since($updatedSince); $filters = array('updated_since' => $updated); $hash = cpcc_mobile_filter_hash('reports', $filters); $last = cpcc_mobile_cursor_decode($cursor, 'reports', $context, $hash, $limit);
	$qcReady = cpcc_qc_reports_schema_ready($db); $sql = "SELECT o.rowid,o.ref,o.service_type,o.title,o.fk_project,o.stage,o.progress,o.next_action,o.date_deadline,";
	if ($qcReady) $sql .= "CASE WHEN o.service_type='quality_control' THEN q.date_publication ELSE o.tms END tms,q.public_uuid publication_uuid,q.overall_result,q.version_no publication_version,q.inspection_at,q.date_publication,q.publication_json,q.publication_sha256";
	else $sql .= "o.tms,NULL publication_uuid,NULL overall_result,NULL publication_version,NULL inspection_at,NULL date_publication,NULL publication_json,NULL publication_sha256";
	$sql .= " FROM ".MAIN_DB_PREFIX."cpo_operation o"; if ($qcReady) $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."cpo_qc_publication q ON q.entity=o.entity AND q.fk_operation=o.rowid AND q.fk_soc=o.fk_soc AND q.is_current=1";
	$sql .= " WHERE o.entity=".(int) $context['entity']." AND o.fk_soc=".(int) $context['socid']." AND o.status=1 AND o.customer_visible=1"; $sql .= $qcReady ? " AND (o.service_type<>'quality_control' OR q.rowid IS NOT NULL)" : " AND o.service_type<>'quality_control'"; if ($updated !== '') $sql .= " AND ".($qcReady ? "(CASE WHEN o.service_type='quality_control' THEN q.date_publication ELSE o.tms END)" : "o.tms").">='".$db->escape($updated)."'"; $sortField=$qcReady?"(CASE WHEN o.service_type='quality_control' THEN q.date_publication ELSE o.tms END)":"o.tms"; $sql .= cpcc_mobile_list_cursor_clause($db, $last, $sortField, 'o.rowid')." ORDER BY tms DESC,o.rowid DESC LIMIT ".($limit + 1);
	$raw = cpcc_mobile_rows($db, cpcc_mobile_query_or_throw($db, $sql)); $wrapped = array(); foreach ($raw as $row) { if ((string)$row->service_type === 'quality_control' && (!preg_match('/^[a-f0-9]{64}$/', (string)$row->publication_sha256) || !hash_equals((string)$row->publication_sha256, hash('sha256', (string)$row->publication_json)) || !is_array(json_decode((string)$row->publication_json, true)))) continue; $wrapped[] = array('_row' => $row, 'tms' => (string) $row->tms, 'rowid' => (int) $row->rowid); } $page = cpcc_mobile_page_meta($db, 'reports', $context, $filters, $limit, $wrapped, 'tms', 'rowid'); $data = array(); foreach ($page['rows'] as $item) $data[] = cpcc_mobile_report_summary($db, $context, $item['_row']); return cpcc_mobile_envelope($data, $page['meta']);
}

function cpcc_mobile_report_field_allowlist()
{
	return array('customer_requirements','product_summary','quantity','production_deadline','audit_mode','audit_scope','audit_date','business_license','certificates','production_capacity','quality_system','social_compliance','findings','advice','report_status','product_name','model_no','product_details','customer_notes','customer_warnings','inspection_standard','aql_level','sample_size','critical_limit','major_limit','minor_limit','inspection_result','corrective_action','pol','pod','destination_country','shipping_line','container_type','incoterm','cargo_type','cargo_weight','cargo_cbm','dangerous_goods','required_services','final_destination','etd','eta','load_type','container_request','cargo_summary','cartons','net_weight','gross_weight','cbm','hs_code','free_time','shipping_order_no','carrier_booking_no','vessel','voyage','cutoff_si','cutoff_cy','bl_no','mbl_no','hbl_no','container_numbers','seal_numbers','booking_status','document_status','loading_status','original_document_status','loading_date','evidence_status','country','submission_status','submission_date','completion_date','claim_type','incident_date','evidence_summary','resolution','details','result');
}

function cpcc_mobile_report_field_label($code)
{
	$labels = array(
		'customer_requirements'=>array('متطلبات العميل','Customer requirements'), 'product_summary'=>array('ملخص المنتج','Product summary'), 'quantity'=>array('الكمية','Quantity'), 'production_deadline'=>array('موعد انتهاء الإنتاج','Production deadline'),
		'audit_mode'=>array('طريقة التدقيق','Audit mode'), 'audit_scope'=>array('نطاق التدقيق','Audit scope'), 'audit_date'=>array('تاريخ التدقيق','Audit date'), 'business_license'=>array('الرخصة التجارية','Business license'), 'certificates'=>array('الشهادات','Certificates'), 'production_capacity'=>array('الطاقة الإنتاجية','Production capacity'), 'quality_system'=>array('نظام الجودة','Quality system'), 'social_compliance'=>array('الامتثال الاجتماعي','Social compliance'),
		'findings'=>array('النتائج','Findings'), 'advice'=>array('التوصيات','Advice'), 'report_status'=>array('حالة التقرير','Report status'), 'product_name'=>array('اسم المنتج','Product name'), 'model_no'=>array('رقم الموديل','Model number'), 'product_details'=>array('تفاصيل المنتج','Product details'), 'customer_notes'=>array('ملاحظات العميل','Customer notes'), 'customer_warnings'=>array('تنبيهات العميل','Customer warnings'),
		'inspection_standard'=>array('معيار الفحص','Inspection standard'), 'aql_level'=>array('مستوى AQL','AQL level'), 'sample_size'=>array('حجم العينة','Sample size'), 'critical_limit'=>array('حد العيوب الحرجة','Critical defect limit'), 'major_limit'=>array('حد العيوب الرئيسية','Major defect limit'), 'minor_limit'=>array('حد العيوب البسيطة','Minor defect limit'), 'inspection_result'=>array('نتيجة الفحص','Inspection result'), 'corrective_action'=>array('الإجراء التصحيحي','Corrective action'),
		'pol'=>array('ميناء الشحن','Port of loading'), 'pod'=>array('ميناء الوصول','Port of destination'), 'destination_country'=>array('بلد الوجهة','Destination country'), 'shipping_line'=>array('الخط الملاحي','Shipping line'), 'container_type'=>array('نوع الحاوية','Container type'), 'incoterm'=>array('شرط التجارة','Incoterm'), 'cargo_type'=>array('نوع البضاعة','Cargo type'), 'cargo_weight'=>array('وزن البضاعة','Cargo weight'), 'cargo_cbm'=>array('حجم البضاعة','Cargo CBM'), 'dangerous_goods'=>array('بضاعة خطرة','Dangerous goods'), 'required_services'=>array('الخدمات المطلوبة','Required services'), 'final_destination'=>array('الوجهة النهائية','Final destination'),
		'etd'=>array('موعد المغادرة المتوقع','ETD'), 'eta'=>array('موعد الوصول المتوقع','ETA'), 'load_type'=>array('نوع التحميل','Load type'), 'container_request'=>array('طلب الحاوية','Container request'), 'cargo_summary'=>array('ملخص البضاعة','Cargo summary'), 'cartons'=>array('عدد الكراتين','Cartons'), 'net_weight'=>array('الوزن الصافي','Net weight'), 'gross_weight'=>array('الوزن الإجمالي','Gross weight'), 'cbm'=>array('الحجم بالمتر المكعب','CBM'), 'hs_code'=>array('الرمز الجمركي','HS code'), 'free_time'=>array('الوقت الحر','Free time'),
		'shipping_order_no'=>array('رقم أمر الشحن','Shipping order number'), 'carrier_booking_no'=>array('رقم حجز الناقل','Carrier booking number'), 'vessel'=>array('السفينة','Vessel'), 'voyage'=>array('الرحلة','Voyage'), 'cutoff_si'=>array('آخر موعد لتعليمات الشحن','Shipping instructions cutoff'), 'cutoff_cy'=>array('آخر موعد لدخول الحاوية','Container yard cutoff'), 'bl_no'=>array('رقم البوليصة','Bill of lading number'), 'mbl_no'=>array('رقم البوليصة الرئيسية','Master bill number'), 'hbl_no'=>array('رقم البوليصة الفرعية','House bill number'), 'container_numbers'=>array('أرقام الحاويات','Container numbers'), 'seal_numbers'=>array('أرقام الأختام','Seal numbers'),
		'booking_status'=>array('حالة الحجز','Booking status'), 'document_status'=>array('حالة المستندات','Document status'), 'loading_status'=>array('حالة التحميل','Loading status'), 'original_document_status'=>array('حالة المستندات الأصلية','Original document status'), 'loading_date'=>array('تاريخ التحميل','Loading date'), 'evidence_status'=>array('حالة الأدلة','Evidence status'), 'country'=>array('الدولة','Country'), 'submission_status'=>array('حالة التقديم','Submission status'), 'submission_date'=>array('تاريخ التقديم','Submission date'), 'completion_date'=>array('تاريخ الإكمال','Completion date'), 'claim_type'=>array('نوع المطالبة','Claim type'), 'incident_date'=>array('تاريخ الواقعة','Incident date'), 'evidence_summary'=>array('ملخص الأدلة','Evidence summary'), 'resolution'=>array('الحل','Resolution'), 'details'=>array('التفاصيل','Details'), 'result'=>array('النتيجة','Result'),
	);
	if (isset($labels[$code])) return cpcc_mobile_label($labels[$code][0], $labels[$code][1]);
	return cpcc_mobile_label('بيان التقرير', ucwords(str_replace('_', ' ', (string) $code)));
}

function cpcc_mobile_report_shipping_line_visible($db, $context, $shipmentId, $value)
{
	if ((int) $shipmentId <= 0 || trim((string) $value) === '') return false;
	$sql = "SELECT shipping_line FROM ".MAIN_DB_PREFIX."chinaplanetlogistics WHERE rowid=".(int) $shipmentId." AND entity=".(int) $context['entity']." AND fk_soc=".(int) $context['socid']." LIMIT 1";
	$result = $db->query($sql); $row = $result ? $db->fetch_object($result) : null;
	return $row && trim((string) $row->shipping_line) !== '' && hash_equals(trim((string) $row->shipping_line), trim((string) $value));
}

function cpcc_mobile_report_detail($db, $context, $publicId)
{
	$id = cpcc_mobile_resolve_public_id($db, 'report', $context, $publicId); if (!$id) cpcc_mobile_throw(404, 'not_found');
	$qcReady = cpcc_qc_reports_schema_ready($db); $sql = "SELECT o.rowid,o.ref,o.service_type,o.title,o.fk_project,o.fk_shipment,o.stage,o.progress,o.next_action,o.date_deadline,o.note_public,";
	if ($qcReady) $sql .= "CASE WHEN o.service_type='quality_control' THEN q.date_publication ELSE o.tms END tms,q.public_uuid publication_uuid,q.overall_result,q.version_no publication_version,q.inspection_at,q.date_publication,q.publication_json,q.publication_sha256";
	else $sql .= "o.tms,NULL publication_uuid,NULL overall_result,NULL publication_version,NULL inspection_at,NULL date_publication,NULL publication_json,NULL publication_sha256";
	$sql .= ",d.data_json FROM ".MAIN_DB_PREFIX."cpo_operation o LEFT JOIN ".MAIN_DB_PREFIX."cpo_operation_data d ON d.entity=o.entity AND d.fk_operation=o.rowid AND d.section_code='service'";
	if ($qcReady) $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."cpo_qc_publication q ON q.entity=o.entity AND q.fk_operation=o.rowid AND q.fk_soc=o.fk_soc AND q.is_current=1";
	$sql .= " WHERE o.rowid=".(int) $id." AND o.entity=".(int) $context['entity']." AND o.fk_soc=".(int) $context['socid']." AND o.status=1 AND o.customer_visible=1"; $sql .= $qcReady ? " AND (o.service_type<>'quality_control' OR q.rowid IS NOT NULL)" : " AND o.service_type<>'quality_control'"; $sql .= " LIMIT 1";
	$result = cpcc_mobile_query_or_throw($db, $sql); $row = $db->fetch_object($result); if (!$row) cpcc_mobile_throw(404, 'not_found'); $data = cpcc_mobile_report_summary($db, $context, $row); $data['public_note'] = $row->note_public !== null && $row->note_public !== '' ? (string) $row->note_public : null; $data['fields'] = array();
	if ((string)$row->service_type === 'quality_control') {
		if (!preg_match('/^[a-f0-9]{64}$/', (string)$row->publication_sha256) || !hash_equals((string)$row->publication_sha256, hash('sha256', (string)$row->publication_json))) cpcc_mobile_throw(404, 'not_found');
		$publication = json_decode((string)$row->publication_json, true); if (!is_array($publication)) cpcc_mobile_throw(404, 'not_found'); $projection = cpcc_qc_customer_projection($publication);
		$data['ref']=$projection['operation']['ref']; $data['title']=$projection['operation']['title'];
		$data['public_note'] = $projection['public_note']; foreach ($projection['fields'] as $field) $data['fields'][] = array('code'=>$field['code'],'label'=>cpcc_mobile_report_field_label($field['code']),'value'=>$field['value']);
		$pdfDocument = !empty($projection['documents'][0]) ? $projection['documents'][0] : array('id'=>(string)$row->publication_uuid,'name'=>'QC-'.$data['ref'].'-v'.(int)$row->publication_version.'.pdf','size_bytes'=>0,'version'=>(int)$row->publication_version,'approved_at'=>$row->date_publication);
		$data['overall_result'] = $projection['overall_result']; $data['inspection'] = $projection['inspection']; $data['summary'] = $projection['summary']; $data['sections'] = $projection['sections']; $data['publication_id'] = (string)$row->publication_uuid; $data['pdf'] = array('id'=>(string)$pdfDocument['id'],'name'=>(string)$pdfDocument['name']); $data['documents'] = array(array('id'=>(string)$pdfDocument['id'],'source_type'=>'operation_report_attachment','kind'=>'document','project_id'=>!empty($row->fk_project)?cpcc_mobile_public_id($db,'project',$context,(int)$row->fk_project):null,'shipment_id'=>null,'shipment_ref'=>null,'report_id'=>$data['id'],'name'=>(string)$pdfDocument['name'],'mime_type'=>'application/pdf','size_bytes'=>max(0,(int)$pdfDocument['size_bytes']),'version'=>max(1,(int)$pdfDocument['version']),'approved_at'=>cpcc_mobile_iso($pdfDocument['approved_at']),'updated_at'=>cpcc_mobile_iso($row->date_publication)));
		return cpcc_mobile_envelope($data);
	}
	$decoded = json_decode((string) $row->data_json, true); if (!is_array($decoded)) $decoded = array(); $allowed = array_flip(cpcc_mobile_report_field_allowlist());
	foreach ($decoded as $code => $value) { if (!isset($allowed[$code]) || is_array($value) || is_object($value) || $value === null || $value === '') continue; if ($code === 'shipping_line' && !cpcc_mobile_report_shipping_line_visible($db, $context, (int) $row->fk_shipment, (string) $value)) continue; $data['fields'][] = array('code' => (string) $code, 'label' => cpcc_mobile_report_field_label((string) $code), 'value' => substr((string) $value, 0, 10000)); }
	$data['documents'] = array(); foreach (cpcc_mobile_document_rows($db, $context) as $document) if ((int) $document->report_id === (int) $id) $data['documents'][] = cpcc_mobile_document_data($db, $context, $document);
	return cpcc_mobile_envelope($data);
}

function cpcc_mobile_qc_publication($db, $context, $reportPublicId)
{
	$id = cpcc_mobile_resolve_public_id($db, 'report', $context, $reportPublicId); if (!$id || !cpcc_qc_reports_schema_ready($db)) cpcc_mobile_throw(404, 'not_found');
	$sql = "SELECT q.*,o.ref operation_ref FROM ".MAIN_DB_PREFIX."cpo_qc_publication q INNER JOIN ".MAIN_DB_PREFIX."cpo_operation o ON o.rowid=q.fk_operation AND o.entity=q.entity AND o.fk_soc=q.fk_soc WHERE q.entity=".(int)$context['entity']." AND q.fk_soc=".(int)$context['socid']." AND q.fk_operation=".(int)$id." AND q.is_current=1 AND o.status=1 AND o.customer_visible=1 AND o.service_type='quality_control' LIMIT 1";
	$res=$db->query($sql);$row=$res?$db->fetch_object($res):null;if(!$row||!preg_match('/^[a-f0-9]{64}$/',(string)$row->publication_sha256)||!hash_equals((string)$row->publication_sha256,hash('sha256',(string)$row->publication_json)))cpcc_mobile_throw(404,'not_found');$decoded=json_decode((string)$row->publication_json,true);if(!is_array($decoded))cpcc_mobile_throw(404,'not_found');$row->customer_projection=cpcc_qc_customer_projection($decoded);return$row;
}

function cpcc_mobile_qc_evidence_file($db, $context, $reportPublicId, $evidenceUuid)
{
	$publication=cpcc_mobile_qc_publication($db,$context,$reportPublicId);$evidence=cpcc_qc_customer_evidence($db,$publication,$evidenceUuid);if(!$evidence)cpcc_mobile_throw(404,'not_found');$path=cpcc_qc_private_file($evidence->safe_stored_name,'evidence',$evidence->safe_size,$evidence->safe_sha256);if(!$path)cpcc_mobile_throw(404,'not_found');return array('path'=>$path,'size'=>(int)$evidence->safe_size,'filename'=>'QC-'.$publication->operation_ref.'-'.$evidence->client_uuid.($evidence->evidence_type==='video'?'.mp4':'.jpg'),'mime_type'=>(string)$evidence->safe_mime);
}

function cpcc_mobile_qc_pdf_file($db, $context, $reportPublicId)
{
	$publication=cpcc_mobile_qc_publication($db,$context,$reportPublicId);$path=cpcc_qc_private_file($publication->pdf_stored_name,'publications',$publication->pdf_size,$publication->pdf_sha256);if(!$path)cpcc_mobile_throw(404,'not_found');return array('path'=>$path,'size'=>(int)$publication->pdf_size,'filename'=>'QC-'.$publication->operation_ref.'-v'.(int)$publication->version_no.'.pdf','mime_type'=>'application/pdf');
}

function cpcc_mobile_dashboard($db, $context)
{
	$counts = array('active_projects' => 0, 'active_shipments' => 0, 'open_orders' => 0);
	$queries = array('active_projects' => "SELECT COUNT(*) AS total FROM ".MAIN_DB_PREFIX."projet WHERE entity=".(int) $context['entity']." AND fk_soc=".(int) $context['socid']." AND fk_statut=1", 'active_shipments' => "SELECT COUNT(*) AS total FROM ".MAIN_DB_PREFIX."chinaplanetlogistics WHERE entity=".(int) $context['entity']." AND fk_soc=".(int) $context['socid']." AND fk_statut IN (0,1,8,9,2,3,4,5)", 'open_orders' => "SELECT COUNT(*) AS total FROM ".MAIN_DB_PREFIX."commande WHERE entity=".(int) $context['entity']." AND fk_soc=".(int) $context['socid']." AND fk_statut IN (1,2)");
	foreach ($queries as $key => $sql) { $result = $db->query($sql); $row = $result ? $db->fetch_object($result) : null; if ($row) $counts[$key] = (int) $row->total; }
	$currentProject = null; $projectResult = $db->query(cpcc_mobile_project_query_base($context)." ORDER BY p.tms DESC,p.rowid DESC LIMIT 1"); if ($projectResult && ($row = $db->fetch_object($projectResult))) $currentProject = cpcc_mobile_project_summary($db, $context, $row);
	$currentShipment = null; $shipmentResult = $db->query(cpcc_mobile_shipment_query_base($context)." ORDER BY s.tms DESC,s.rowid DESC LIMIT 1"); if ($shipmentResult && ($row = $db->fetch_object($shipmentResult))) $currentShipment = cpcc_mobile_shipment_summary($db, $context, $row);
	return cpcc_mobile_envelope(array('active_projects' => $counts['active_projects'], 'active_shipments' => $counts['active_shipments'], 'open_orders' => $counts['open_orders'], 'current_project' => $currentProject, 'current_shipment' => $currentShipment, 'balances' => cpcc_mobile_balances_data($db, $context), 'updated_at' => cpcc_mobile_iso(cpcc_mobile_now_sql())));
}

function cpcc_mobile_apply_etag($etag)
{
	header('ETag: '.$etag); header('Cache-Control: private, no-store'); $headers = cpcc_mobile_request_headers();
	if (isset($headers['if-none-match']) && trim($headers['if-none-match']) === $etag) { http_response_code(304); exit; }
}
