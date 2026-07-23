<?php
/** Customer-safe read models for the M017 mobile API. */

require_once __DIR__.'/mobile_api.lib.php';
require_once __DIR__.'/portal.lib.php';
require_once __DIR__.'/financial.lib.php';

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

function cpcc_mobile_project_status($status, $progress = 0)
{
	$status = (int) $status; $progress = (int) $progress;
	if ($status <= 0) return array('code' => 'draft', 'label' => cpcc_mobile_label('مسودة', 'Draft'));
	if ($status === 2 || $progress >= 100) return array('code' => 'completed', 'label' => cpcc_mobile_label('مكتمل', 'Completed'));
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
	$sql = "SELECT p.rowid,p.ref,p.title,p.description,p.datec,p.dateo,p.datee,p.fk_statut,p.tms,";
	$sql .= " c.rowid AS card_id,c.progress,c.date_deadline,c.note_public,c.fk_column,col.code AS column_code,col.label AS column_ar,col.label_en AS column_en";
	$sql .= " FROM ".MAIN_DB_PREFIX."projet p";
	$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."cpkanban_card c ON c.rowid=(SELECT MIN(cc.rowid) FROM ".MAIN_DB_PREFIX."cpkanban_card cc WHERE cc.fk_project=p.rowid AND cc.entity=p.entity AND cc.fk_soc=p.fk_soc AND cc.status=1 AND cc.cust_visible=1)";
	$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."cpkanban_column col ON col.rowid=c.fk_column AND col.entity=c.entity AND col.active=1";
	$sql .= " WHERE p.entity=".(int) $context['entity']." AND p.fk_soc=".(int) $context['socid'];
	return $sql;
}

function cpcc_mobile_project_summary($db, $context, $row)
{
	$progress = isset($row->progress) ? max(0, min(100, (int) $row->progress)) : ((int) $row->fk_statut === 2 ? 100 : ((int) $row->fk_statut === 1 ? 55 : 5));
	$currentAr = !empty($row->column_ar) ? (string) $row->column_ar : 'قيد المتابعة';
	$currentEn = !empty($row->column_en) ? (string) $row->column_en : 'In progress';
	return array(
		'id' => cpcc_mobile_public_id($db, 'project', $context, (int) $row->rowid),
		'ref' => (string) $row->ref,
		'title' => (string) $row->title,
		'status' => cpcc_mobile_project_status($row->fk_statut, $progress),
		'progress_percent' => $progress,
		'current_step' => cpcc_mobile_label($currentAr, $currentEn),
		'next_step' => cpcc_mobile_project_next_step($db, $context, $row),
		'expected_next_at' => cpcc_mobile_iso($row->date_deadline),
		'latest_update_at' => cpcc_mobile_iso(!empty($row->tms) ? $row->tms : $row->datec),
		'latest_update_by' => cpcc_mobile_label('فريق كوكب الصين', 'China Planet Team'),
		'updated_at' => cpcc_mobile_iso(!empty($row->tms) ? $row->tms : $row->datec),
	);
}

function cpcc_mobile_project_next_step($db, $context, $row)
{
	if (empty($row->fk_column)) return cpcc_mobile_label('سيتم التحديث قريبًا', 'To be updated');
	$sql = "SELECT label,label_en FROM ".MAIN_DB_PREFIX."cpkanban_column WHERE entity=".(int) $context['entity']." AND active=1 AND cust_visible=1 AND position>(SELECT position FROM ".MAIN_DB_PREFIX."cpkanban_column WHERE rowid=".(int) $row->fk_column." AND entity=".(int) $context['entity'].") ORDER BY position,rowid LIMIT 1";
	$result = $db->query($sql); $next = $result ? $db->fetch_object($result) : null;
	return $next ? cpcc_mobile_label((string) $next->label, (string) $next->label_en) : cpcc_mobile_label('اكتملت المراحل الحالية', 'Current stages completed');
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
	$data['timeline'] = cpcc_mobile_project_timeline($db, $context, (int) $row->fk_column);
	$data['shipment_ids'] = cpcc_mobile_related_ids($db, 'shipment', $context, "SELECT rowid FROM ".MAIN_DB_PREFIX."chinaplanetlogistics WHERE entity=".(int) $context['entity']." AND fk_soc=".(int) $context['socid']." AND fk_project=".(int) $id." ORDER BY rowid");
	$data['document_ids'] = cpcc_mobile_project_document_ids($db, $context, (int) $id);
	$data['report_ids'] = cpcc_mobile_related_ids($db, 'report', $context, "SELECT rowid FROM ".MAIN_DB_PREFIX."cpo_operation WHERE entity=".(int) $context['entity']." AND fk_soc=".(int) $context['socid']." AND fk_project=".(int) $id." AND status=1 AND customer_visible=1 ORDER BY rowid");
	$data['balances'] = cpcc_mobile_balances_data($db, $context, (int) $id);
	return cpcc_mobile_envelope($data);
}

function cpcc_mobile_project_timeline($db, $context, $currentColumnId)
{
	$sql = "SELECT rowid,code,label,label_en,position FROM ".MAIN_DB_PREFIX."cpkanban_column WHERE entity=".(int) $context['entity']." AND active=1 AND cust_visible=1 ORDER BY position,rowid";
	$rows = cpcc_mobile_rows($db, $db->query($sql)); $currentIndex = 0;
	foreach ($rows as $index => $row) if ((int) $row->rowid === (int) $currentColumnId) $currentIndex = $index;
	$result = array();
	foreach ($rows as $index => $row) {
		$state = $index < $currentIndex ? 'completed' : ($index === $currentIndex ? 'current' : ($index === $currentIndex + 1 ? 'next' : 'planned'));
		$result[] = array('code' => (string) $row->code, 'label' => cpcc_mobile_label((string) $row->label, (string) $row->label_en), 'state' => $state, 'state_label' => cpcc_mobile_timeline_state_label($state), 'occurred_at' => null, 'source' => null, 'location' => null, 'public_note' => null, 'public_reason' => null);
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
	foreach (cpcc_mobile_transaction_rows($db, $context) as $row) {
		if ($projectId !== null && (int) $row->fk_project !== (int) $projectId) continue;
		$currency = cpcc_mobile_currency($row->currency_code); if (!isset($currencies[$currency])) $currencies[$currency] = array('debit' => 0.0, 'credit' => 0.0, 'balance' => 0.0);
		$amount = (float) $row->amount; if (strtoupper((string) $row->direction) === 'C') { $currencies[$currency]['credit'] += $amount; $currencies[$currency]['balance'] += $amount; } else { $currencies[$currency]['debit'] += $amount; $currencies[$currency]['balance'] -= $amount; }
		$timestamp = !empty($row->tms) ? (string) $row->tms : (string) $row->occurred_at; if (strcmp($timestamp, $latest) > 0) $latest = $timestamp;
	}
	ksort($currencies); $result = array(); foreach ($currencies as $currency => $values) $result[] = array('currency' => $currency, 'debit' => cpcc_mobile_decimal($values['debit']), 'credit' => cpcc_mobile_decimal($values['credit']), 'balance' => cpcc_mobile_decimal($values['balance']), 'updated_at' => cpcc_mobile_iso($latest));
	return $result;
}

function cpcc_mobile_balances($db, $context)
{
	return cpcc_mobile_envelope(cpcc_mobile_balances_data($db, $context));
}

function cpcc_mobile_invoice_rows($db, $context)
{
	$sql = "SELECT f.rowid,f.ref,f.datef,f.date_lim_reglement,f.fk_projet,f.fk_statut,f.paye,f.multicurrency_code,f.total_ttc,f.multicurrency_total_ttc,f.tms,COALESCE(pay.amount,0) AS paid_base,COALESCE(pay.multicurrency_amount,0) AS paid_multi,COALESCE(discount.amount,0) AS discount_base,COALESCE(discount.multicurrency_amount,0) AS discount_multi";
	$sql .= " FROM ".MAIN_DB_PREFIX."facture f LEFT JOIN (SELECT pf.fk_facture,SUM(pf.amount) amount,SUM(pf.multicurrency_amount) multicurrency_amount FROM ".MAIN_DB_PREFIX."paiement_facture pf INNER JOIN ".MAIN_DB_PREFIX."paiement ppay ON ppay.rowid=pf.fk_paiement WHERE ".cpcc_financial_payment_status_sql('ppay')." GROUP BY pf.fk_facture) pay ON pay.fk_facture=f.rowid";
	$sql .= " LEFT JOIN (".cpcc_financial_applied_credit_sql().") discount ON discount.fk_facture=f.rowid";
	$sql .= " WHERE f.entity=".(int) $context['entity']." AND f.fk_soc=".(int) $context['socid']." AND f.fk_statut>0 AND f.type IN (0,1,5) ORDER BY f.datef DESC,f.rowid DESC";
	$rows = cpcc_mobile_rows($db, cpcc_mobile_query_or_throw($db, $sql));
	foreach ($rows as $row) { $multi = trim((string) $row->multicurrency_code) !== ''; $row->display_currency = $multi ? cpcc_mobile_currency($row->multicurrency_code) : cpcc_mobile_base_currency(); $amounts=cpcc_financial_invoice_amounts($multi?(float)$row->multicurrency_total_ttc:(float)$row->total_ttc,$multi?(float)$row->paid_multi:(float)$row->paid_base,$multi?(float)$row->discount_multi:(float)$row->discount_base); $row->display_total=$amounts['total'];$row->display_paid=$amounts['paid'];$row->display_remaining=$amounts['remaining']; }
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
	$queries[] = "SELECT a.rowid AS source_id,(a.rowid * 2) AS logical_id,'project_asset' AS source_type,p.rowid AS project_id,a.fk_shipment AS shipment_id,s.ref AS shipment_ref,NULL AS report_id,a.original_name,a.stored_name,a.mime_type,a.file_size,a.version_no,a.date_creation AS approved_at,a.tms,a.fk_card,NULL AS operation_ref,NULL AS file_sha256,NULL AS item_kind FROM ".MAIN_DB_PREFIX."cpkanban_asset a INNER JOIN ".MAIN_DB_PREFIX."cpkanban_card c ON c.rowid=a.fk_card AND c.entity=a.entity AND c.fk_soc=a.fk_soc INNER JOIN ".MAIN_DB_PREFIX."projet p ON p.rowid=c.fk_project AND p.entity=c.entity AND p.fk_soc=c.fk_soc LEFT JOIN ".MAIN_DB_PREFIX."chinaplanetlogistics s ON s.rowid=a.fk_shipment AND s.entity=a.entity AND s.fk_soc=a.fk_soc WHERE a.entity=".(int) $context['entity']." AND a.fk_soc=".(int) $context['socid']." AND c.status=1 AND c.cust_visible=1 AND a.status=1 AND a.customer_visible=1 AND a.review_status IN ('approved','final_received')";
	$queries[] = "SELECT d.rowid AS source_id,(d.rowid * 2) + 1 AS logical_id,'operation_report_attachment' AS source_type,o.fk_project AS project_id,o.fk_shipment AS shipment_id,s.ref AS shipment_ref,o.rowid AS report_id,d.original_name,d.stored_name,d.mime_type,d.file_size,d.version_no,COALESCE(d.date_review,d.date_creation) AS approved_at,d.tms,NULL AS fk_card,o.ref AS operation_ref,NULL AS file_sha256,NULL AS item_kind FROM ".MAIN_DB_PREFIX."cpo_document d INNER JOIN ".MAIN_DB_PREFIX."cpo_operation o ON o.rowid=d.fk_operation AND o.entity=d.entity LEFT JOIN ".MAIN_DB_PREFIX."chinaplanetlogistics s ON s.rowid=o.fk_shipment AND s.entity=o.entity AND s.fk_soc=o.fk_soc WHERE d.entity=".(int) $context['entity']." AND o.fk_soc=".(int) $context['socid']." AND o.status=1 AND o.customer_visible=1 AND d.visibility='customer_after_approval' AND d.review_status IN ('approved','final_received') AND d.is_current=1";
	if (cpcc_mobile_table_exists($db, 'cpl_shipment_public_document')) {
		$queries[] = "SELECT g.rowid AS source_id,(0 - g.rowid) AS logical_id,'shipment_gallery' AS source_type,s.fk_project AS project_id,g.fk_shipment AS shipment_id,s.ref AS shipment_ref,NULL AS report_id,g.original_name,g.storage_key AS stored_name,g.mime_type,g.file_size,1 AS version_no,g.date_publication AS approved_at,g.tms,NULL AS fk_card,NULL AS operation_ref,g.file_sha256,g.item_kind FROM ".MAIN_DB_PREFIX."cpl_shipment_public_document g INNER JOIN ".MAIN_DB_PREFIX."chinaplanetlogistics s ON s.rowid=g.fk_shipment AND s.entity=g.entity AND s.fk_soc=g.fk_soc WHERE g.entity=".(int) $context['entity']." AND g.fk_soc=".(int) $context['socid']." AND g.customer_visible=1 AND g.status=1";
	}
	$sql = "SELECT * FROM (".implode(' UNION ALL ', $queries).") mobile_docs";
	if ($scope === 'shipment') $sql .= " WHERE shipment_id IS NOT NULL";
	$sql .= " ORDER BY tms DESC,logical_id DESC";
	return cpcc_mobile_rows($db, cpcc_mobile_query_or_throw($db, $sql));
}

function cpcc_mobile_document_data($db, $context, $row)
{
	$resource = $row->source_type === 'shipment_gallery' ? 'shipment_gallery_item' : 'document';
	$objectId = $row->source_type === 'shipment_gallery' ? (int) $row->source_id : (int) $row->logical_id;
	$kind = !empty($row->item_kind) ? (string) $row->item_kind : cpcc_portal_gallery_item_kind($row->mime_type);
	return array('id' => cpcc_mobile_public_id($db, $resource, $context, $objectId), 'source_type' => (string) $row->source_type, 'kind' => $kind, 'project_id' => !empty($row->project_id) ? cpcc_mobile_public_id($db, 'project', $context, (int) $row->project_id) : null, 'shipment_id' => !empty($row->shipment_id) ? cpcc_mobile_public_id($db, 'shipment', $context, (int) $row->shipment_id) : null, 'shipment_ref' => !empty($row->shipment_ref) ? (string) $row->shipment_ref : null, 'report_id' => !empty($row->report_id) ? cpcc_mobile_public_id($db, 'report', $context, (int) $row->report_id) : null, 'name' => basename((string) $row->original_name), 'mime_type' => (string) $row->mime_type, 'size_bytes' => max(0, (int) $row->file_size), 'version' => max(1, (int) $row->version_no), 'approved_at' => cpcc_mobile_iso($row->approved_at), 'updated_at' => cpcc_mobile_iso($row->tms));
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
	if ($logicalId !== null && $logicalId !== 0) foreach (cpcc_mobile_document_rows($db, $context) as $row) if ($row->source_type !== 'shipment_gallery' && (int) $row->logical_id === (int) $logicalId) return $row;
	$galleryId = cpcc_mobile_resolve_public_id($db, 'shipment_gallery_item', $context, $publicId);
	if ($galleryId !== null && $galleryId > 0) foreach (cpcc_mobile_document_rows($db, $context, 'shipment') as $row) if ($row->source_type === 'shipment_gallery' && (int) $row->source_id === (int) $galleryId) return $row;
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
	return array('id' => cpcc_mobile_public_id($db, 'report', $context, (int) $row->rowid), 'project_id' => !empty($row->fk_project) ? cpcc_mobile_public_id($db, 'project', $context, (int) $row->fk_project) : null, 'ref' => (string) $row->ref, 'report_type' => cpcc_mobile_report_type($row->service_type), 'title' => (string) $row->title, 'stage' => cpcc_mobile_report_stage($row->stage), 'progress_percent' => max(0, min(100, (int) $row->progress)), 'next_action' => $row->next_action !== null && $row->next_action !== '' ? (string) $row->next_action : null, 'due_at' => cpcc_mobile_iso($row->date_deadline), 'updated_at' => cpcc_mobile_iso($row->tms));
}

function cpcc_mobile_reports($db, $context, $limit, $cursor, $updatedSince)
{
	$limit = cpcc_mobile_list_limit($limit); $updated = cpcc_mobile_updated_since($updatedSince); $filters = array('updated_since' => $updated); $hash = cpcc_mobile_filter_hash('reports', $filters); $last = cpcc_mobile_cursor_decode($cursor, 'reports', $context, $hash, $limit);
	$sql = "SELECT o.rowid,o.ref,o.service_type,o.title,o.fk_project,o.stage,o.progress,o.next_action,o.date_deadline,o.tms FROM ".MAIN_DB_PREFIX."cpo_operation o WHERE o.entity=".(int) $context['entity']." AND o.fk_soc=".(int) $context['socid']." AND o.status=1 AND o.customer_visible=1"; if ($updated !== '') $sql .= " AND o.tms>='".$db->escape($updated)."'"; $sql .= cpcc_mobile_list_cursor_clause($db, $last, 'o.tms', 'o.rowid')." ORDER BY o.tms DESC,o.rowid DESC LIMIT ".($limit + 1);
	$raw = cpcc_mobile_rows($db, cpcc_mobile_query_or_throw($db, $sql)); $wrapped = array(); foreach ($raw as $row) $wrapped[] = array('_row' => $row, 'tms' => (string) $row->tms, 'rowid' => (int) $row->rowid); $page = cpcc_mobile_page_meta($db, 'reports', $context, $filters, $limit, $wrapped, 'tms', 'rowid'); $data = array(); foreach ($page['rows'] as $item) $data[] = cpcc_mobile_report_summary($db, $context, $item['_row']); return cpcc_mobile_envelope($data, $page['meta']);
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
	$sql = "SELECT o.rowid,o.ref,o.service_type,o.title,o.fk_project,o.fk_shipment,o.stage,o.progress,o.next_action,o.date_deadline,o.note_public,o.tms,d.data_json FROM ".MAIN_DB_PREFIX."cpo_operation o LEFT JOIN ".MAIN_DB_PREFIX."cpo_operation_data d ON d.entity=o.entity AND d.fk_operation=o.rowid AND d.section_code='service' WHERE o.rowid=".(int) $id." AND o.entity=".(int) $context['entity']." AND o.fk_soc=".(int) $context['socid']." AND o.status=1 AND o.customer_visible=1 LIMIT 1";
	$result = cpcc_mobile_query_or_throw($db, $sql); $row = $db->fetch_object($result); if (!$row) cpcc_mobile_throw(404, 'not_found'); $data = cpcc_mobile_report_summary($db, $context, $row); $data['public_note'] = $row->note_public !== null && $row->note_public !== '' ? (string) $row->note_public : null; $data['fields'] = array();
	$decoded = json_decode((string) $row->data_json, true); if (!is_array($decoded)) $decoded = array(); $allowed = array_flip(cpcc_mobile_report_field_allowlist());
	foreach ($decoded as $code => $value) { if (!isset($allowed[$code]) || is_array($value) || is_object($value) || $value === null || $value === '') continue; if ($code === 'shipping_line' && !cpcc_mobile_report_shipping_line_visible($db, $context, (int) $row->fk_shipment, (string) $value)) continue; $data['fields'][] = array('code' => (string) $code, 'label' => cpcc_mobile_report_field_label((string) $code), 'value' => substr((string) $value, 0, 10000)); }
	$data['documents'] = array(); foreach (cpcc_mobile_document_rows($db, $context) as $document) if ((int) $document->report_id === (int) $id) $data['documents'][] = cpcc_mobile_document_data($db, $context, $document);
	return cpcc_mobile_envelope($data);
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
