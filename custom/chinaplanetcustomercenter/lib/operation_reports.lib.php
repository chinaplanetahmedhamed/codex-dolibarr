<?php

function cpcc_operation_reports_schema_ready($db)
{
    foreach (array('cpo_operation', 'cpo_operation_data', 'cpo_document') as $table) {
        $res = $db->query("SHOW TABLES LIKE '".$db->escape(MAIN_DB_PREFIX.$table)."'");
        if (!$res || $db->num_rows($res) === 0) return false;
    }
    return true;
}

function cpcc_operation_report_public_fields($serviceType)
{
    $common = array('customer_requirements');
    $map = array(
        'order'=>array('product_summary','quantity','production_deadline'),
        'factory_audit'=>array('factory_name','factory_address','audit_mode','audit_scope','audit_date','business_license','certificates','production_capacity','quality_system','social_compliance','findings','advice','report_status'),
        'quality_control'=>array('factory_name','product_name','model_no','product_details','customer_notes','customer_warnings','inspection_standard','aql_level','sample_size','critical_limit','major_limit','minor_limit','inspection_result','corrective_action'),
        'shipping_inquiry'=>array('pol','pod','destination_country','shipping_line','container_type','incoterm','cargo_type','cargo_weight','cargo_cbm','dangerous_goods','required_services'),
        'shipping_booking'=>array('shipper','consignee','notify_party','pol','pod','final_destination','etd','eta','incoterm','load_type','container_request','cargo_summary','cartons','net_weight','gross_weight','cbm','hs_code','dangerous_goods','free_time','shipping_order_no','carrier_booking_no','shipping_line','vessel','voyage','cutoff_si','cutoff_cy','bl_no','mbl_no','hbl_no','container_numbers','seal_numbers','booking_status','document_status','loading_status','original_document_status'),
        'loading'=>array('warehouse','loading_date','container_numbers','seal_numbers','cartons','gross_weight','cbm','loading_status','evidence_status'),
        'customs'=>array('country','platform_name','external_reference','submission_status','submission_date','completion_date','proof_reference'),
        'claim'=>array('claim_type','incident_date','evidence_summary','resolution'),
    );
    return array_values(array_unique(array_merge($common, isset($map[$serviceType]) ? $map[$serviceType] : array('details','result'))));
}

function cpcc_operation_reports($db, $socid, $projectid = 0)
{
    if (!cpcc_operation_reports_schema_ready($db) || (int)$socid <= 0) return array();
    $sql = "SELECT o.rowid,o.ref,o.service_type,o.title,o.fk_project,o.fk_commande,o.stage,o.progress,o.next_action,o.date_deadline,o.note_public,o.tms";
    $sql .= " FROM ".MAIN_DB_PREFIX."cpo_operation o WHERE o.entity=".(int)$GLOBALS['conf']->entity;
    $sql .= " AND o.fk_soc=".(int)$socid." AND o.status=1 AND o.customer_visible=1";
    if ((int)$projectid > 0) $sql .= " AND o.fk_project=".(int)$projectid;
    $sql .= " ORDER BY o.tms DESC,o.rowid DESC";
    $rows = array(); $res = $db->query($sql);
    while ($res && ($obj = $db->fetch_object($res))) $rows[] = get_object_vars($obj);
    return $rows;
}

function cpcc_operation_report($db, $id, $socid)
{
    if (!cpcc_operation_reports_schema_ready($db) || (int)$id <= 0 || (int)$socid <= 0) return null;
    $sql = "SELECT o.rowid,o.ref,o.service_type,o.title,o.fk_soc,o.fk_project,o.fk_commande,o.stage,o.progress,o.next_action,o.date_deadline,o.note_public,o.tms,d.data_json";
    $sql .= " FROM ".MAIN_DB_PREFIX."cpo_operation o";
    $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."cpo_operation_data d ON d.entity=o.entity AND d.fk_operation=o.rowid AND d.section_code='service'";
    $sql .= " WHERE o.entity=".(int)$GLOBALS['conf']->entity." AND o.rowid=".(int)$id." AND o.fk_soc=".(int)$socid;
    $sql .= " AND o.status=1 AND o.customer_visible=1";
    $res = $db->query($sql); $obj = $res ? $db->fetch_object($res) : null;
    if (!$obj) return null;
    $row = get_object_vars($obj); $decoded = json_decode((string)$row['data_json'], true);
    $row['service_data'] = is_array($decoded) ? $decoded : array(); unset($row['data_json']);
    return $row;
}

function cpcc_operation_report_documents($db, $operationId)
{
    if (!cpcc_operation_reports_schema_ready($db)) return array();
    $sql = "SELECT rowid,document_type,original_name,mime_type,file_size,version_no,date_review FROM ".MAIN_DB_PREFIX."cpo_document";
    $sql .= " WHERE entity=".(int)$GLOBALS['conf']->entity." AND fk_operation=".(int)$operationId;
    $sql .= " AND visibility='customer_after_approval' AND review_status IN ('approved','final_received') AND is_current=1";
    $sql .= " ORDER BY document_type,version_no DESC";
    $rows = array(); $res = $db->query($sql);
    while ($res && ($obj = $db->fetch_object($res))) $rows[] = get_object_vars($obj);
    return $rows;
}
