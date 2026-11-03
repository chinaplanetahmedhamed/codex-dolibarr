<?php
/**
 * Customer-safe shipment history and contextual shipment actions.
 *
 * This library deliberately keeps customer projections scoped by entity,
 * shipment and fk_soc. It never returns private operation fields or raw file
 * paths.
 */

/**
 * Small translation proxy used only by the customer shipment command center.
 *
 * China Planet customers commonly work in Arabic while staff and shipping
 * documents use English.  The rest of Dolibarr keeps the user's selected
 * language; this proxy renders the command-center labels in both languages.
 */
class CplBilingualTranslate
{
    private $primary;
    private $secondary;

    public function __construct($primary, $secondary)
    {
        $this->primary = $primary;
        $this->secondary = $secondary;
    }

    public function trans($key)
    {
        $args = func_get_args();
        $primary = (string) call_user_func_array(array($this->primary, 'trans'), $args);
        // Locale configuration is consumed by core formatters, not a bilingual label.
        // Combining US and Arabic date masks prints the same date twice with reversed month/day.
        if (strpos((string) $key, 'Format') === 0 || in_array($key, array('DIRECTION', 'SeparatorDecimal', 'SeparatorThousand'), true)) return $primary;
        $secondary = (string) call_user_func_array(array($this->secondary, 'trans'), $args);
        if ($secondary === '' || $secondary === (string) $key || $secondary === $primary) return $primary;
        return $primary.' / '.$secondary;
    }
}

function cpl_bilingual_langs($primary)
{
    // Presentation follows the chosen interface language; international acronyms
    // remain in the existing translations. Keep the proxy class for legacy callers.
    return $primary;
}

function cpl_text_limit($value, $length)
{
    $value = trim((string) $value);
    return function_exists('mb_substr') ? mb_substr($value, 0, (int) $length, 'UTF-8') : substr($value, 0, (int) $length);
}

function cpl_schema_has_table($db, $table)
{
    $pattern = str_replace(array('\\', '_', '%'), array('\\\\', '\\_', '\\%'), MAIN_DB_PREFIX.$table);
    $resql = $db->query("SHOW TABLES LIKE '".$db->escape($pattern)."'");
    return $resql && $db->num_rows($resql) === 1;
}

function cpl_schema_has_column($db, $table, $column)
{
    $resql = $db->query("SHOW COLUMNS FROM ".MAIN_DB_PREFIX.$table." LIKE '".$db->escape($column)."'");
    return $resql && $db->num_rows($resql) === 1;
}

function cpl_shipment_schema_ready($db)
{
    return cpl_schema_has_table($db, 'cpl_shipment_event')
        && cpl_schema_has_column($db, 'chinaplanetlogistics', 'date_atd');
}

function cpl_public_source_types()
{
    return array(
        'manual_team' => 'China Planet Team',
        'carrier_update' => 'Carrier update',
        'logistics_partner' => 'Logistics partner',
        'customs_authority' => 'Customs authority',
        'system' => 'System update',
    );
}

function cpl_public_source($type, $label = '')
{
    $sources = cpl_public_source_types();
    $type = isset($sources[$type]) ? $type : 'manual_team';
    // Customer attribution is restricted to reviewed categories. Arbitrary
    // staff/partner names can expose internal identities, so they are ignored.
    return array('type' => $type, 'label' => $sources[$type]);
}

function cpl_public_source_label($langs, $type)
{
    $keys = array('manual_team'=>'CPLSourceManualTeam','carrier_update'=>'CPLSourceCarrier','logistics_partner'=>'CPLSourceLogisticsPartner','customs_authority'=>'CPLSourceCustoms','system'=>'CPLSourceSystem');
    return $langs->trans(isset($keys[$type]) ? $keys[$type] : 'CPLSourceManualTeam');
}

function cpl_event_types()
{
    return array('baseline', 'status_changed', 'eta_set', 'eta_changed', 'atd_set', 'atd_changed', 'atd_cleared', 'ata_set', 'ata_changed', 'ata_cleared', 'transshipment');
}

function cpl_milestones()
{
    return array('inquiry', 'booking', 'loaded', 'export_customs', 'departed', 'transshipment', 'arrived', 'import_customs', 'delivered', 'closed');
}

function cpl_milestone_icon($code)
{
    $icons = array(
        'inquiry'=>'fa-search', 'booking'=>'fa-calendar-check', 'loaded'=>'fa-box',
        'export_customs'=>'fa-stamp', 'departed'=>'fa-ship', 'transshipment'=>'fa-exchange-alt',
        'arrived'=>'fa-anchor', 'import_customs'=>'fa-university', 'delivered'=>'fa-truck', 'closed'=>'fa-check-circle',
    );
    return isset($icons[$code]) ? $icons[$code] : 'fa-circle';
}

function cpl_status_milestone($status)
{
    $map = array(0 => 'inquiry', 1 => 'booking', 8 => 'loaded', 9 => 'export_customs', 2 => 'departed', 3 => 'arrived', 4 => 'import_customs', 5 => 'import_customs', 6 => 'delivered', 7 => 'closed');
    return isset($map[(int) $status]) ? $map[(int) $status] : 'inquiry';
}

function cpl_sql_datetime($db, $value)
{
    if (empty($value)) return 'NULL';
    $timestamp = is_numeric($value) ? (int) $value : strtotime((string) $value);
    return $timestamp > 0 ? "'".$db->idate($timestamp)."'" : 'NULL';
}

function cpl_shipment_event_insert($db, $shipment, $user, $data)
{
    $eventType = isset($data['event_type']) && in_array($data['event_type'], cpl_event_types(), true) ? $data['event_type'] : '';
    $milestone = isset($data['milestone_code']) && in_array($data['milestone_code'], cpl_milestones(), true) ? $data['milestone_code'] : '';
    if ($eventType === '' || $milestone === '' || empty($shipment->id) || empty($shipment->fk_soc)) return false;

    $source = cpl_public_source(isset($data['source_type']) ? $data['source_type'] : '', isset($data['source_label_public']) ? $data['source_label_public'] : '');
    $note = cpl_text_limit(isset($data['note_public']) ? $data['note_public'] : '', 2000);
    $reason = cpl_text_limit(isset($data['reason_public']) ? $data['reason_public'] : '', 1000);
    $eventAt = !empty($data['event_at']) ? $data['event_at'] : dol_now();
    $statusFrom = isset($data['status_from']) && $data['status_from'] !== '' ? (int) $data['status_from'] : 'NULL';
    $statusTo = isset($data['status_to']) && $data['status_to'] !== '' ? (int) $data['status_to'] : 'NULL';

    $sql = "INSERT INTO ".MAIN_DB_PREFIX."cpl_shipment_event(entity,fk_shipment,fk_soc,event_type,milestone_code,status_from,status_to,event_at,planned_at,actual_at,date_value_old,date_value_new,source_type,source_label_public,note_public,reason_public,customer_visible,fk_user_creat,date_creation) VALUES(";
    $sql .= (int) $shipment->entity.",".(int) $shipment->id.",".(int) $shipment->fk_soc.",'".$db->escape($eventType)."','".$db->escape($milestone)."',".$statusFrom.",".$statusTo.",";
    $sql .= cpl_sql_datetime($db, $eventAt).",".cpl_sql_datetime($db, isset($data['planned_at']) ? $data['planned_at'] : null).",".cpl_sql_datetime($db, isset($data['actual_at']) ? $data['actual_at'] : null).",";
    $sql .= cpl_sql_datetime($db, isset($data['date_value_old']) ? $data['date_value_old'] : null).",".cpl_sql_datetime($db, isset($data['date_value_new']) ? $data['date_value_new'] : null).",'".$db->escape($source['type'])."','".$db->escape($source['label'])."','".$db->escape($note)."','".$db->escape($reason)."',".(!empty($data['customer_visible']) ? 1 : 0).",".(int) $user->id.",'".$db->idate(dol_now())."')";
    return (bool) $db->query($sql);
}

function cpl_shipment_has_audit_rows($db, $shipmentId, $entity, $socid)
{
    foreach (array('cpl_shipment_event', 'cpl_document_requirement') as $table) {
        if (!cpl_schema_has_table($db, $table)) continue;
        $sql = "SELECT rowid FROM ".MAIN_DB_PREFIX.$table." WHERE entity=".(int) $entity." AND fk_shipment=".(int) $shipmentId." AND fk_soc=".(int) $socid." LIMIT 1";
        $resql = $db->query($sql);
        if (!$resql) return true;
        if ($resql && $db->num_rows($resql) > 0) return true;
    }
    return false;
}

function cpl_shipment_events($db, $shipment, $customerVisibleOnly = true)
{
    if (!cpl_schema_has_table($db, 'cpl_shipment_event') || empty($shipment->id) || empty($shipment->fk_soc)) return array();
    $sql = "SELECT rowid,event_type,milestone_code,status_from,status_to,event_at,planned_at,actual_at,date_value_old,date_value_new,source_type,source_label_public,note_public,reason_public,customer_visible,date_creation";
    $sql .= " FROM ".MAIN_DB_PREFIX."cpl_shipment_event WHERE entity=".(int) $shipment->entity." AND fk_shipment=".(int) $shipment->id." AND fk_soc=".(int) $shipment->fk_soc;
    if ($customerVisibleOnly) $sql .= " AND customer_visible=1";
    $sql .= " ORDER BY COALESCE(actual_at,event_at,date_creation) ASC,rowid ASC";
    $rows = array();
    $resql = $db->query($sql);
    while ($resql && ($row = $db->fetch_object($resql))) $rows[] = $row;
    return $rows;
}

function cpl_latest_customer_update($events)
{
    $latest = null;
    foreach ($events as $event) {
        if (empty($event->customer_visible)) continue;
        $timestamp = strtotime(!empty($event->event_at) ? $event->event_at : $event->date_creation);
        if ($latest === null || $timestamp >= $latest['timestamp']) $latest = array('event' => $event, 'timestamp' => $timestamp);
    }
    return $latest;
}

function cpl_latest_eta_change($events)
{
    $latest = null;
    foreach ($events as $event) {
        if ($event->event_type !== 'eta_changed' || empty($event->customer_visible)) continue;
        $timestamp = strtotime(!empty($event->event_at) ? $event->event_at : $event->date_creation);
        if ($latest === null || $timestamp >= $latest['timestamp']) $latest = array('event' => $event, 'timestamp' => $timestamp);
    }
    return $latest;
}

function cpl_requirement_statuses()
{
    return array('required', 'requested', 'received', 'under_review', 'correction_required', 'approved', 'published');
}

function cpl_requirement_parties()
{
    return array('customer', 'china_planet', 'carrier', 'customs', 'other');
}

function cpl_document_source_types()
{
    return array('', 'commercial_document', 'operation_document');
}

function cpl_validate_document_source($db, $shipment, $sourceType, $sourceId)
{
    $sourceType = (string) $sourceType;
    $sourceId = (int) $sourceId;
    if ($sourceType === '' && $sourceId === 0) return array('ok' => true, 'url' => '');
    if (!in_array($sourceType, cpl_document_source_types(), true) || $sourceId <= 0) return array('ok' => false, 'url' => '');

    if ($sourceType === 'commercial_document') {
        $sql = "SELECT d.rowid FROM ".MAIN_DB_PREFIX."cpcc_export_document d JOIN ".MAIN_DB_PREFIX."chinaplanetlogistics l ON l.rowid=d.fk_shipment AND l.entity=d.entity AND l.fk_soc=d.fk_soc";
        $sql .= " WHERE d.rowid=".$sourceId." AND d.entity=".(int) $shipment->entity." AND d.fk_soc=".(int) $shipment->fk_soc." AND d.fk_shipment=".(int) $shipment->id." AND COALESCE(d.fk_project,0)=COALESCE(l.fk_project,0) AND d.status=1 AND d.customer_visible=1";
        $resql = $db->query($sql);
        if ($resql && $db->num_rows($resql) === 1) return array('ok' => true, 'url' => dol_buildpath('/chinaplanetcustomercenter/export_document.php', 1).'?id='.$sourceId);
    }

    if ($sourceType === 'operation_document') {
        $sql = "SELECT d.rowid FROM ".MAIN_DB_PREFIX."cpo_document d JOIN ".MAIN_DB_PREFIX."cpo_operation o ON o.rowid=d.fk_operation AND o.entity=d.entity";
        $sql .= " JOIN ".MAIN_DB_PREFIX."chinaplanetlogistics l ON l.rowid=o.fk_shipment AND l.entity=o.entity AND l.fk_soc=o.fk_soc";
        $sql .= " WHERE d.rowid=".$sourceId." AND d.entity=".(int) $shipment->entity." AND o.fk_soc=".(int) $shipment->fk_soc." AND o.fk_shipment=".(int) $shipment->id." AND COALESCE(o.fk_project,0)=COALESCE(l.fk_project,0)";
        $sql .= " AND o.status=1 AND o.customer_visible=1 AND d.visibility='customer_after_approval' AND d.review_status IN ('approved','final_received') AND d.is_current=1";
        $resql = $db->query($sql);
        if ($resql && $db->num_rows($resql) === 1) return array('ok' => true, 'url' => dol_buildpath('/chinaplanetcustomercenter/operation_document.php', 1).'?id='.$sourceId);
    }

    return array('ok' => false, 'url' => '');
}

function cpl_save_document_requirement($db, $shipment, $user, $data)
{
    if (!cpl_shipment_schema_ready($db)) return array('ok' => false, 'error' => 'Shipment command-center schema is not installed.');
    $id = isset($data['id']) ? (int) $data['id'] : 0;
    $status = isset($data['requirement_status']) && in_array($data['requirement_status'], cpl_requirement_statuses(), true) ? $data['requirement_status'] : '';
    $party = isset($data['requested_from']) && in_array($data['requested_from'], cpl_requirement_parties(), true) ? $data['requested_from'] : '';
    $sourceType = isset($data['source_type']) ? $data['source_type'] : '';
    $sourceId = isset($data['source_id']) ? (int) $data['source_id'] : 0;
    $documentCode = cpl_text_limit(isset($data['document_code']) ? $data['document_code'] : '', 64);
    $label = cpl_text_limit(isset($data['label_public']) ? $data['label_public'] : '', 255);
    if ($status === '' || $party === '' || $documentCode === '' || $label === '') return array('ok' => false, 'error' => 'Document requirement fields are invalid.');
    $dueAt = isset($data['due_at']) ? $data['due_at'] : null;
    $note = cpl_text_limit(isset($data['note_public']) ? $data['note_public'] : '', 2000);
    $db->begin();
    $shipmentLock = $db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."chinaplanetlogistics WHERE rowid=".(int) $shipment->id." AND entity=".(int) $shipment->entity." AND fk_soc=".(int) $shipment->fk_soc." FOR UPDATE");
    if (!$shipmentLock || $db->num_rows($shipmentLock) !== 1) { $db->rollback(); return array('ok' => false, 'error' => 'Shipment was not found.'); }
    $source = cpl_validate_document_source($db, $shipment, $sourceType, $sourceId);
    if (empty($source['ok'])) { $db->rollback(); return array('ok' => false, 'error' => 'Document source is not authorized for this shipment.'); }
    if ($id > 0) {
        $lock = $db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."cpl_document_requirement WHERE rowid=".$id." AND entity=".(int) $shipment->entity." AND fk_shipment=".(int) $shipment->id." AND fk_soc=".(int) $shipment->fk_soc." FOR UPDATE");
        if (!$lock || $db->num_rows($lock) !== 1) { $db->rollback(); return array('ok' => false, 'error' => 'Document requirement was not found.'); }
        $sql = "UPDATE ".MAIN_DB_PREFIX."cpl_document_requirement SET document_code='".$db->escape($documentCode)."',label_public='".$db->escape($label)."',requirement_status='".$db->escape($status)."',requested_from='".$db->escape($party)."',due_at=".cpl_sql_datetime($db, $dueAt).",action_required=".(!empty($data['action_required']) ? 1 : 0).",source_type=".($sourceType !== '' ? "'".$db->escape($sourceType)."'" : 'NULL').",source_id=".($sourceId > 0 ? $sourceId : 'NULL').",note_public='".$db->escape($note)."',customer_visible=".(!empty($data['customer_visible']) ? 1 : 0).",fk_user_modif=".(int) $user->id." WHERE rowid=".$id;
    } else {
        $sql = "INSERT INTO ".MAIN_DB_PREFIX."cpl_document_requirement(entity,fk_shipment,fk_soc,document_code,label_public,requirement_status,requested_from,due_at,action_required,source_type,source_id,note_public,customer_visible,fk_user_creat,date_creation) VALUES(".(int) $shipment->entity.",".(int) $shipment->id.",".(int) $shipment->fk_soc.",'".$db->escape($documentCode)."','".$db->escape($label)."','".$db->escape($status)."','".$db->escape($party)."',".cpl_sql_datetime($db, $dueAt).",".(!empty($data['action_required']) ? 1 : 0).",".($sourceType !== '' ? "'".$db->escape($sourceType)."'" : 'NULL').",".($sourceId > 0 ? $sourceId : 'NULL').",'".$db->escape($note)."',".(!empty($data['customer_visible']) ? 1 : 0).",".(int) $user->id.",'".$db->idate(dol_now())."')";
    }
    if (!$db->query($sql)) { $error = $db->lasterror(); $db->rollback(); return array('ok' => false, 'error' => $error); }
    $db->commit();
    return array('ok' => true, 'error' => '');
}

function cpl_document_requirements($db, $shipment, $customerVisibleOnly = true)
{
    if (!cpl_schema_has_table($db, 'cpl_document_requirement')) return array();
    $sql = "SELECT rowid,document_code,label_public,requirement_status,requested_from,due_at,action_required,source_type,source_id,note_public,customer_visible,tms FROM ".MAIN_DB_PREFIX."cpl_document_requirement";
    $sql .= " WHERE entity=".(int) $shipment->entity." AND fk_shipment=".(int) $shipment->id." AND fk_soc=".(int) $shipment->fk_soc;
    if ($customerVisibleOnly) $sql .= " AND customer_visible=1";
    $sql .= " ORDER BY action_required DESC,due_at IS NULL,due_at,rowid";
    $rows = array(); $resql = $db->query($sql);
    while ($resql && ($row = $db->fetch_object($resql))) {
        $source = cpl_validate_document_source($db, $shipment, (string) $row->source_type, (int) $row->source_id);
        $row->document_url = ($row->requirement_status === 'published' && !empty($source['ok'])) ? $source['url'] : '';
        $rows[] = $row;
    }
    return $rows;
}

function cpl_customer_next_operation($db, $shipment)
{
    if (!cpl_schema_has_table($db, 'cpo_operation')) return null;
    $sql = "SELECT o.rowid,o.title,o.waiting_on,o.next_action,o.date_deadline,o.note_public,o.tms FROM ".MAIN_DB_PREFIX."cpo_operation o";
    $sql .= " WHERE o.entity=".(int) $shipment->entity." AND o.fk_soc=".(int) $shipment->fk_soc." AND o.fk_shipment=".(int) $shipment->id." AND o.status=1 AND o.customer_visible=1";
    $sql .= " AND COALESCE(o.fk_project,0)=".(int)$shipment->fk_project;
    $sql .= " AND o.stage NOT IN ('completed','cancelled') ORDER BY (o.waiting_on='customer') DESC,(CASE WHEN o.waiting_on='customer' THEN o.date_deadline ELSE NULL END) IS NULL,(CASE WHEN o.waiting_on='customer' THEN o.date_deadline ELSE NULL END),o.tms DESC,o.rowid DESC LIMIT 1";
    $resql = $db->query($sql);
    return $resql ? $db->fetch_object($resql) : null;
}

function cpl_customer_contextual_alert($db, $shipment, $nextOperation)
{
    if ($nextOperation && (string)$nextOperation->waiting_on === 'customer') {
        return array('code'=>'operation','title'=>(string)($nextOperation->next_action ?: $nextOperation->title),'note'=>(string)$nextOperation->note_public,'due'=>(string)$nextOperation->date_deadline);
    }
    if (!function_exists('cpcc_portal_collaboration_schema_ready') || !cpcc_portal_collaboration_schema_ready($db)) return null;
    $pending = $db->query("SELECT COUNT(*) AS total FROM ".MAIN_DB_PREFIX."cpl_shipment_public_document WHERE entity=".(int)$shipment->entity." AND fk_shipment=".(int)$shipment->id." AND fk_soc=".(int)$shipment->fk_soc." AND item_kind='document' AND source_origin='staff' AND customer_visible=1 AND status=1 AND response_status='awaiting_confirmation'");
    if ($pending && ($row = $db->fetch_object($pending)) && (int)$row->total > 0) return array('code'=>'documents','count'=>(int)$row->total);
    $events = cpcc_portal_shipment_collaboration_events($db, $shipment);
    $containerReceived = false; $originalStatus = ''; $originalReceived = false;
    foreach ($events as $event) {
        if ($event->event_type === 'container_received') $containerReceived = true;
        if ($event->event_type === 'original_document_status') $originalStatus = (string)$event->status_code;
        if ($event->event_type === 'original_documents_received') $originalReceived = true;
    }
    if (in_array((int)$shipment->fk_statut, array(6,7), true) && !$containerReceived) return array('code'=>'container_receipt');
    if ($originalStatus === 'delivered' && !$originalReceived) return array('code'=>'original_documents_receipt');
    return null;
}

function cpl_add_transshipment_event($db, $shipment, $user, $data)
{
    if (!cpl_shipment_schema_ready($db)) return array('ok' => false, 'error' => 'Shipment command-center schema is not installed.');
    $actualAt = isset($data['actual_at']) ? $data['actual_at'] : null;
    $timestamp = is_numeric($actualAt) ? (int) $actualAt : strtotime((string) $actualAt);
    if ($timestamp <= 0) return array('ok' => false, 'error' => 'Transshipment time is required.');
    $location = cpl_text_limit(isset($data['location']) ? $data['location'] : '', 255);
    if ($location === '') return array('ok' => false, 'error' => 'Transshipment location is required.');
    $db->begin();
    $lock = $db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."chinaplanetlogistics WHERE rowid=".(int) $shipment->id." AND entity=".(int) $shipment->entity." AND fk_soc=".(int) $shipment->fk_soc." FOR UPDATE");
    if (!$lock || $db->num_rows($lock) !== 1) { $db->rollback(); return array('ok' => false, 'error' => 'Shipment was not found.'); }
    $ok = cpl_shipment_event_insert($db, $shipment, $user, array(
        'event_type' => 'transshipment', 'milestone_code' => 'transshipment', 'actual_at' => $timestamp,
        'source_type' => isset($data['source_type']) ? $data['source_type'] : '', 'source_label_public' => isset($data['source_label_public']) ? $data['source_label_public'] : '',
        'note_public' => $location.(empty($data['note_public']) ? '' : ' — '.cpl_text_limit($data['note_public'], 1500)), 'reason_public' => '', 'customer_visible' => !empty($data['customer_visible']),
    ));
    if (!$ok) { $error = $db->lasterror(); $db->rollback(); return array('ok' => false, 'error' => $error); }
    $db->commit();
    return array('ok' => true, 'error' => '');
}

function cpl_event_label($langs, $event)
{
    $keys = array(
        'baseline' => 'CPLUpdateBaseline', 'status_changed' => 'CPLUpdateStatusChanged',
        'eta_set' => 'CPLUpdateEtaSet', 'eta_changed' => 'CPLUpdateEtaChanged',
        'atd_set' => 'CPLUpdateAtdSet', 'atd_changed' => 'CPLUpdateAtdChanged', 'atd_cleared' => 'CPLUpdateAtdCleared',
        'ata_set' => 'CPLUpdateAtaSet', 'ata_changed' => 'CPLUpdateAtaChanged', 'ata_cleared' => 'CPLUpdateAtaCleared',
        'transshipment' => 'CPLMilestoneTransshipment',
    );
    return $langs->trans(isset($keys[$event->event_type]) ? $keys[$event->event_type] : 'CPLShipmentUpdated');
}

function cpl_milestone_label($langs, $code)
{
    $keys = array(
        'inquiry'=>'CPLMilestoneInquiry', 'booking'=>'CPLMilestoneBooking', 'loaded'=>'CPLMilestoneLoaded',
        'export_customs'=>'CPLMilestoneExportCustoms', 'departed'=>'CPLMilestoneDeparted',
        'transshipment'=>'CPLMilestoneTransshipment', 'arrived'=>'CPLMilestoneArrived',
        'import_customs'=>'CPLMilestoneImportCustoms', 'delivered'=>'CPLMilestoneDelivered', 'closed'=>'CPLMilestoneClosed',
    );
    return $langs->trans(isset($keys[$code]) ? $keys[$code] : 'CPLShipmentUpdated');
}

function cpl_customer_status_label($langs, $status)
{
    $keys = array(0=>'CPLStatusInquiry', 1=>'CPLStatusBookingConfirmed', 8=>'CPLStatusLoadedExportCustoms', 9=>'CPLStatusExportCustomsCleared', 2=>'CPLStatusOnVessel', 3=>'CPLStatusArrivedPort', 4=>'CPLStatusUnderCustoms', 5=>'CPLStatusCustomsCleared', 6=>'CPLStatusDelivered', 7=>'CPLStatusClosed');
    return $langs->trans(isset($keys[(int) $status]) ? $keys[(int) $status] : 'CPLNotSet');
}

function cpl_public_datetime($value, $format = 'dayhour')
{
    if (empty($value)) return '';
    $timestamp = is_numeric($value) ? (int) $value : strtotime((string) $value);
    return $timestamp > 0 ? dol_print_date($timestamp, $format) : '';
}

function cpl_timeline_rows($shipment, $events)
{
    $actualByMilestone = array();
    $transshipments = array();
    foreach ($events as $event) {
        if ($event->event_type === 'transshipment') {
            $transshipments[] = $event;
        } elseif (!empty($event->actual_at)) {
            $actualByMilestone[$event->milestone_code] = $event->actual_at;
        }
    }

    // Transit is optional. Direct sailings go straight from Departed to
    // Arrived; the extra milestone appears only after a transit event exists.
    $order = array('inquiry','booking','loaded','export_customs','departed');
    if (!empty($transshipments)) $order[] = 'transshipment';
    $order = array_merge($order, array('arrived','import_customs','delivered','closed'));
    $current = cpl_status_milestone($shipment->fk_statut);
    $currentIndex = array_search($current, $order, true);
    if ($currentIndex === false) $currentIndex = 0;
    if ($current === 'departed' && !empty($transshipments)) $currentIndex = array_search('transshipment', $order, true);
    if (!empty($shipment->date_atd)) $actualByMilestone['departed'] = $shipment->date_atd;
    if (!empty($shipment->date_ata)) $actualByMilestone['arrived'] = $shipment->date_ata;
    $plannedByMilestone = array('departed'=>!empty($shipment->date_etd) ? $shipment->date_etd : '', 'arrived'=>!empty($shipment->date_eta) ? $shipment->date_eta : '');

    $rows = array();
    foreach ($order as $index => $code) {
        $state = $index < $currentIndex ? 'complete' : ($index === $currentIndex ? 'current' : ($index === $currentIndex + 1 ? 'next' : 'planned'));
        $actual = isset($actualByMilestone[$code]) ? $actualByMilestone[$code] : '';
        if (($code === 'departed' || $code === 'arrived') && $index <= $currentIndex && empty($actual)) $state = 'unconfirmed';
        $rows[] = array('code'=>$code, 'state'=>$state, 'current'=>$index === $currentIndex, 'actual'=>$actual, 'planned'=>isset($plannedByMilestone[$code]) ? $plannedByMilestone[$code] : '', 'events'=>$code === 'transshipment' ? $transshipments : array());
    }
    return $rows;
}

function cpl_render_shipment_command_center($db, $shipment, $context)
{
    global $langs;
    $external = !empty($context['external']);
    $canwrite = !empty($context['canwrite']);
    $candelete = !empty($context['candelete']);
    $schemaReady = cpl_shipment_schema_ready($db);
    $events = $schemaReady ? cpl_shipment_events($db, $shipment, $external) : array();
    $latest = cpl_latest_customer_update($events);
    $etaChange = cpl_latest_eta_change($events);
    $nextOperation = cpl_customer_next_operation($db, $shipment);
    $contextualAlert = $external ? cpl_customer_contextual_alert($db, $shipment, $nextOperation) : null;
    $timeline = cpl_timeline_rows($shipment, $events);
    $now = dol_now();
    $customerNote = trim((string) $shipment->customer_note);

    $baseLangs = $langs;
    $direction = $baseLangs->trans('DIRECTION') === 'rtl' ? 'rtl' : 'ltr';
    $langs = cpl_bilingual_langs($baseLangs);
    try {
        $statusLabel = cpl_customer_status_label($langs, $shipment->fk_statut);
    print '<main class="cpl-command-center" dir="'.$direction.'" data-cpl-shipment-title="'.dol_escape_htmltag($langs->trans('CPLShipmentDetails')).'">';
    print '<header class="cpl-command-hero"><div><span class="cpl-eyebrow">'.$langs->trans('CPLShipmentCommandCenter').'</span><h1>'.dol_escape_htmltag($shipment->ref).'</h1><p>'.dol_escape_htmltag(isset($context['customer_name']) ? $context['customer_name'] : '').' · '.dol_escape_htmltag(isset($context['project_label']) ? $context['project_label'] : '').'</p></div><div class="cpl-status-pill">'.dol_escape_htmltag($statusLabel).'</div></header>';

    if (!$schemaReady) print '<div class="cpl-alert cpl-alert-warning" role="status">'.$langs->trans('CPLLegacySnapshotNotice').'</div>';
    if ($etaChange) {
        $event = $etaChange['event'];
        $oldEta = strtotime((string)$event->date_value_old); $newEta = strtotime((string)$event->date_value_new);
        $deltaDays = ($oldEta > 0 && $newEta > 0) ? (int)round(($newEta - $oldEta) / 86400) : 0;
        $deltaLabel = $deltaDays > 0 ? $langs->trans('CPLEtaDelayed', abs($deltaDays)) : ($deltaDays < 0 ? $langs->trans('CPLEtaAdvanced', abs($deltaDays)) : $langs->trans('CPLEtaDateCorrected'));
        print '<section class="cpl-alert cpl-alert-eta" role="alert"><strong>'.$langs->trans('CPLEtaChangedAlert').'</strong><span>'.dol_escape_htmltag(cpl_public_datetime($event->date_value_old, 'day')).' → '.dol_escape_htmltag(cpl_public_datetime($event->date_value_new, 'day')).'</span>';
        print '<b>'.dol_escape_htmltag($deltaLabel).'</b><small>'.dol_escape_htmltag(cpl_public_datetime($event->event_at)).' · '.dol_escape_htmltag(cpl_public_source_label($langs, $event->source_type)).'</small>';
        if (!empty($event->reason_public)) print '<p>'.nl2br(dol_escape_htmltag($event->reason_public)).'</p>';
        print '</section>';
    }
    if ($contextualAlert) {
        $alertTitle = 'CPLCustomerActionNotice'; $alertText = '';
        if ($contextualAlert['code'] === 'operation') { $alertTitle = 'CPLCustomerActionNotice'; $alertText = (string)$contextualAlert['title']; }
        elseif ($contextualAlert['code'] === 'documents') $alertText = $langs->trans('CPLDocumentsAwaitingYourConfirmation', (int)$contextualAlert['count']);
        elseif ($contextualAlert['code'] === 'container_receipt') $alertText = $langs->trans('CPLConfirmContainerReceiptNotice');
        elseif ($contextualAlert['code'] === 'original_documents_receipt') $alertText = $langs->trans('CPLConfirmOriginalDocumentsReceiptNotice');
        print '<aside class="cpl-alert cpl-alert-action" role="alert"><strong>'.dol_escape_htmltag($langs->trans($alertTitle)).'</strong><span>'.dol_escape_htmltag($alertText).'</span>';
        if (!empty($contextualAlert['note'])) print '<p>'.nl2br(dol_escape_htmltag($contextualAlert['note'])).'</p>';
        if (!empty($contextualAlert['due'])) print '<small>'.dol_escape_htmltag($langs->trans('CPLDueDate')).' '.dol_escape_htmltag(cpl_public_datetime($contextualAlert['due'], 'day')).'</small>';
        print '</aside>';
    }

    print '<section class="cpl-overview" aria-label="'.$langs->trans('CPLCurrentShipmentSnapshot').'">';
    print '<article class="cpl-latest"><span>'.$langs->trans('CPLLatestUpdate').'</span>';
    if ($latest) {
        $event = $latest['event'];
        $latestNote = !empty($event->note_public) ? $event->note_public : $customerNote;
        print '<strong>'.dol_escape_htmltag(cpl_event_label($langs, $event)).'</strong><p>'.($latestNote !== '' ? nl2br(dol_escape_htmltag($latestNote)) : $langs->trans('CPLNoPublicNote')).'</p><small>'.dol_escape_htmltag(cpl_public_datetime($event->event_at)).' · '.dol_escape_htmltag(cpl_public_source_label($langs, $event->source_type)).'</small>';
    } else {
        print '<strong>'.$langs->trans('CPLNoPublishedUpdate').'</strong><p>'.($customerNote !== '' ? nl2br(dol_escape_htmltag($customerNote)) : $langs->trans('CPLSnapshotOnly')).'</p>';
    }
    print '</article>';

    $arrivalClass = 'pending';
    if (!empty($shipment->date_ata)) {
        $arrivalTitle = $langs->trans('CPLArrivalConfirmed'); $arrivalText = cpl_public_datetime($shipment->date_ata, 'day'); $arrivalClass = 'confirmed';
    } elseif (empty($shipment->date_eta)) {
        $arrivalTitle = $langs->trans('CPLEtaNotSet'); $arrivalText = $langs->trans('CPLAwaitingEta');
    } elseif ((int)$shipment->date_eta < $now) {
        $arrivalTitle = $langs->trans('CPLEtaPassedUnconfirmed'); $arrivalText = cpl_public_datetime($shipment->date_eta, 'day'); $arrivalClass = 'attention';
    } else {
        $arrivalTitle = $langs->trans('CPLExpectedArrival'); $arrivalText = cpl_public_datetime($shipment->date_eta, 'day');
    }
    print '<article class="cpl-snapshot cpl-'.$arrivalClass.'"><span>'.$langs->trans('CPLArrival').'</span><strong>'.dol_escape_htmltag($arrivalTitle).'</strong><small>'.dol_escape_htmltag($arrivalText).'</small></article>';

    if (empty($shipment->date_ata)) {
        $freeTitle = $langs->trans('CPLFreeTimeAfterArrival'); $freeText = $langs->trans('CPLNotStarted'); $freeClass = 'pending';
    } elseif ((int)$shipment->freetime_days <= 0 || empty($shipment->freetime_end)) {
        $freeTitle = $langs->trans('CPLFreeTimeNotConfigured'); $freeText = $langs->trans('CPLAwaitingFreeTime'); $freeClass = 'attention';
    } else {
        $days = (int)ceil(((int)$shipment->freetime_end - $now) / 86400);
        $freeTitle = $days < 0 ? $langs->trans('CPLFreeTimeExpired') : $langs->trans('CPLFreeTimeRemaining');
        $freeText = $langs->trans('CPLDaysCount', abs($days)).' · '.cpl_public_datetime($shipment->freetime_end, 'day');
        $freeClass = $days <= 3 ? 'attention' : 'confirmed';
    }
    print '<article class="cpl-snapshot cpl-'.$freeClass.'"><span>'.$langs->trans('CPLFreeTime').'</span><strong>'.dol_escape_htmltag($freeTitle).'</strong><small>'.dol_escape_htmltag($freeText).'</small></article></section>';

    print '<section id="shipment-timeline" class="cpl-panel" aria-labelledby="cpl-timeline-title"><div class="cpl-section-title"><div><span>'.$langs->trans('CPLShipmentJourney').'</span><h2 id="cpl-timeline-title">'.$langs->trans('CPLTimeline').'</h2></div>';
    print '</div><ol class="cpl-timeline">';
    foreach ($timeline as $step) {
        $stateKeys = array('complete'=>'CPLStateComplete','current'=>'CPLStateCurrent','next'=>'CPLStateNext','planned'=>'CPLStatePlanned','unconfirmed'=>'CPLStateUnconfirmed');
        $stateText = $langs->trans(isset($stateKeys[$step['state']]) ? $stateKeys[$step['state']] : 'CPLStatePlanned');
        $markerIcon = !empty($step['current']) ? 'fa-ship' : cpl_milestone_icon($step['code']);
        print '<li class="is-'.dol_escape_htmltag($step['state']).'"'.(!empty($step['current']) ? ' aria-current="step"' : '').'><span class="cpl-timeline-marker" aria-hidden="true"><i class="fas '.dol_escape_htmltag($markerIcon).'"></i></span><div><strong>'.dol_escape_htmltag(cpl_milestone_label($langs, $step['code'])).'</strong><span class="cpl-state-text">'.dol_escape_htmltag($stateText).'</span>';
        if (!empty($step['actual'])) print '<time>'.dol_escape_htmltag(cpl_public_datetime($step['actual'])).'</time>';
        elseif (!empty($step['planned'])) print '<time>'.$langs->trans('CPLPlanned').' · '.dol_escape_htmltag(cpl_public_datetime($step['planned'], 'day')).'</time>';
        foreach ($step['events'] as $event) print '<p class="cpl-transshipment-entry">'.dol_escape_htmltag(cpl_public_datetime($event->actual_at)).' · '.dol_escape_htmltag($event->note_public).'</p>';
        print '</div></li>';
    }
    print '</ol></section>';
    if ($external) print '<!--CPL_SHIPMENT_FILES-->';

    $details = array(
        'CPLContainer' => trim((string)$shipment->container_no.' · '.(string)$shipment->container_type, ' ·'),
        'CPLContainersQuantity' => (int)$shipment->nb_containers,
        'CPLBookingNumber' => $shipment->booking_no, 'CPLBillOfLading' => $shipment->bl_no,
        'CPLVessel' => $shipment->vessel_name, 'CPLVoyage' => $shipment->voyage_no,
        'CPLLoadingPort' => $shipment->port_loading, 'CPLDestinationPort' => $shipment->port_destination,
        'CPLEtd' => cpl_public_datetime($shipment->date_etd, 'day'), 'CPLAtd' => cpl_public_datetime($shipment->date_atd, 'day'),
        'CPLEta' => cpl_public_datetime($shipment->date_eta, 'day'), 'CPLAta' => cpl_public_datetime($shipment->date_ata, 'day'),
    );
    print '<section class="cpl-panel"><div class="cpl-section-title"><div><span>'.$langs->trans('CPLShipmentData').'</span><h2>'.$langs->trans('CPLShipmentDetails').'</h2></div></div><div class="cpl-info-grid">';
    foreach ($details as $key=>$value) print '<div><span>'.dol_escape_htmltag($langs->trans($key)).'</span><strong>'.dol_escape_htmltag($value !== '' ? $value : $langs->trans('CPLNotSet')).'</strong></div>';
    print '</div></section>';

    if ($canwrite && $schemaReady) {
        $sourceOptions = ''; foreach (cpl_public_source_types() as $key=>$label) $sourceOptions .= '<option value="'.dol_escape_htmltag($key).'">'.dol_escape_htmltag(cpl_public_source_label($langs, $key)).'</option>';
        print '<details class="cpl-panel cpl-internal-tools"><summary>'.$langs->trans('CPLInternalShipmentTools').'</summary><div class="cpl-internal-grid">';
        print '<form method="post" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="id" value="'.(int)$shipment->id.'"><input type="hidden" name="action" value="add_transshipment"><h3>'.$langs->trans('CPLAddTransshipment').'</h3><label>'.$langs->trans('CPLDateAndTime').'<input required type="datetime-local" name="transshipment_at"></label><label>'.$langs->trans('CPLLocation').'<input required maxlength="255" name="transshipment_location"></label><label>'.$langs->trans('CPLPublicSource').'<select name="transshipment_source_type">'.$sourceOptions.'</select></label><label>'.$langs->trans('CPLPublicNote').'<textarea name="transshipment_note" maxlength="1500"></textarea></label><label class="cpl-check"><input type="checkbox" name="transshipment_customer_visible" value="1" checked> '.$langs->trans('CPLPublishToCustomer').'</label><button type="submit">'.$langs->trans('CPLSave').'</button></form>';
        print '</div></details>';
    }

    print '<nav class="cpl-detail-actions" aria-label="'.$langs->trans('CPLShipmentActions').'">';
    if ($canwrite && !empty($context['cansendshipmentemail'])) print '<form method="post" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="id" value="'.(int)$shipment->id.'"><input type="hidden" name="action" value="resend_status_email"><button type="submit"><span class="fas fa-envelope" aria-hidden="true"></span> '.$langs->trans('CPLResendLatestStatusEmail').'</button></form>';
    if ($canwrite) print '<a href="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'?id='.(int)$shipment->id.'&amp;action=edit">'.$langs->trans('Modify').'</a>';
    if ($candelete) print '<a class="danger" href="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'?id='.(int)$shipment->id.'&amp;action=confirm_delete&amp;token='.urlencode(newToken()).'">'.$langs->trans('Delete').'</a>';
    if (!empty($context['modal'])) print '<button type="button" data-cpl-back-project data-cpl-back-url="'.dol_escape_htmltag(isset($context['projecturl']) ? $context['projecturl'] : '').'">'.$langs->trans('CPLBackToProject').'</button>';
    else print '<a href="'.dol_escape_htmltag(isset($context['listurl']) ? $context['listurl'] : '#').'">'.$langs->trans('BackToList').'</a>';
    print '</nav>';
    print '</main>';
    } finally {
        $langs = $baseLangs;
    }
}
?>
