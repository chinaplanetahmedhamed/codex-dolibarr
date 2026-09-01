<?php

function cpcc_operation_reports_schema_ready($db)
{
    foreach (array('cpo_operation', 'cpo_operation_data', 'cpo_document') as $table) {
        $res = $db->query("SHOW TABLES LIKE '".$db->escape(MAIN_DB_PREFIX.$table)."'");
        if (!$res || $db->num_rows($res) === 0) return false;
    }
    return true;
}

function cpcc_qc_reports_schema_ready($db)
{
    foreach (array('cpo_qc_checkpoint', 'cpo_qc_evidence', 'cpo_qc_publication') as $table) {
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
        'quality_control'=>array('product_name','model_no','product_details','customer_notes','customer_warnings','inspection_standard','aql_level','sample_size','critical_limit','major_limit','minor_limit','inspection_result','corrective_action'),
        'shipping_inquiry'=>array('pol','pod','destination_country','shipping_line','container_type','incoterm','cargo_type','cargo_weight','cargo_cbm','dangerous_goods','required_services'),
        'shipping_booking'=>array('shipper','consignee','notify_party','pol','pod','final_destination','etd','eta','incoterm','load_type','container_request','cargo_summary','cartons','net_weight','gross_weight','cbm','hs_code','dangerous_goods','free_time','shipping_order_no','carrier_booking_no','shipping_line','vessel','voyage','cutoff_si','cutoff_cy','bl_no','mbl_no','hbl_no','container_numbers','seal_numbers','booking_status','document_status','loading_status','original_document_status'),
        'loading'=>array('warehouse','loading_date','container_numbers','seal_numbers','cartons','gross_weight','cbm','loading_status','evidence_status'),
        'customs'=>array('country','platform_name','external_reference','submission_status','submission_date','completion_date','proof_reference'),
        'claim'=>array('claim_type','incident_date','evidence_summary','resolution'),
    );
    return array_values(array_unique(array_merge($common, isset($map[$serviceType]) ? $map[$serviceType] : array('details','result'))));
}

function cpcc_qc_customer_projection(array $source)
{
    $out=array('schema_version'=>1,'publication_id'=>isset($source['publication_id'])?(string)$source['publication_id']:'','version'=>isset($source['version'])?(int)$source['version']:0,'overall_result'=>isset($source['overall_result'])?(string)$source['overall_result']:'','operation'=>array('ref'=>isset($source['operation']['ref'])?(string)$source['operation']['ref']:'','title'=>isset($source['operation']['title'])?(string)$source['operation']['title']:''),'inspection'=>array(),'summary'=>array(),'public_note'=>isset($source['public_note'])&&is_string($source['public_note'])?$source['public_note']:null,'fields'=>array(),'documents'=>array(),'sections'=>array());
    foreach(array('inspected_at','reviewed_at','published_at','reviewed_by')as$key)$out['inspection'][$key]=isset($source['inspection'][$key])&&is_scalar($source['inspection'][$key])?(string)$source['inspection'][$key]:null;
    $out['inspection']['version']=isset($source['inspection']['version'])?(int)$source['inspection']['version']:$out['version'];
    $out['inspection']['aql']=cpcc_qc_public_aql(isset($source['inspection']['aql'])&&is_array($source['inspection']['aql'])?$source['inspection']['aql']:array());
    foreach(array('total','pass','fail','not_applicable','critical','major','minor')as$key)$out['summary'][$key]=isset($source['summary'][$key])?max(0,(int)$source['summary'][$key]):0;
    $allowedFields=array_flip(cpcc_operation_report_public_fields('quality_control'));foreach(isset($source['fields'])&&is_array($source['fields'])?$source['fields']:array()as$field)if(isset($field['code'],$field['value'])&&isset($allowedFields[$field['code']])&&is_scalar($field['value']))$out['fields'][]=array('code'=>(string)$field['code'],'value'=>substr((string)$field['value'],0,10000));
    foreach(isset($source['documents'])&&is_array($source['documents'])?$source['documents']:array()as$document)if(isset($document['id'],$document['name'])&&preg_match('/^[0-9a-f-]{36}$/',(string)$document['id']))$out['documents'][]=array('id'=>(string)$document['id'],'kind'=>'document','name'=>basename((string)$document['name']),'mime_type'=>'application/pdf','size_bytes'=>max(0,(int)($document['size_bytes']??0)),'version'=>max(1,(int)($document['version']??1)),'approved_at'=>isset($document['approved_at'])?(string)$document['approved_at']:null);
    foreach(isset($source['sections'])&&is_array($source['sections'])?$source['sections']:array()as$section){$clean=array('code'=>preg_replace('/[^a-z0-9_]/','',(string)($section['code']??'')),'title'=>substr((string)($section['title']??''),0,255),'checkpoints'=>array());foreach(isset($section['checkpoints'])&&is_array($section['checkpoints'])?$section['checkpoints']:array()as$checkpoint){if(!isset($checkpoint['id'],$checkpoint['requirement'])||!preg_match('/^[0-9a-f-]{36}$/',(string)$checkpoint['id']))continue;$cp=array('id'=>(string)$checkpoint['id'],'requirement'=>substr((string)$checkpoint['requirement'],0,20000),'expected_value'=>isset($checkpoint['expected_value'])&&is_scalar($checkpoint['expected_value'])?substr((string)$checkpoint['expected_value'],0,20000):null,'tolerance'=>isset($checkpoint['tolerance'])&&is_scalar($checkpoint['tolerance'])?substr((string)$checkpoint['tolerance'],0,20000):null,'observation'=>isset($checkpoint['observation'])&&is_scalar($checkpoint['observation'])?substr((string)$checkpoint['observation'],0,30000):null,'result'=>in_array(($checkpoint['result']??''),array('pass','fail','not_applicable'),true)?(string)$checkpoint['result']:'pending','severity'=>in_array(($checkpoint['severity']??''),array('none','critical','major','minor'),true)?(string)$checkpoint['severity']:'none','public_note'=>isset($checkpoint['public_note'])&&is_scalar($checkpoint['public_note'])?substr((string)$checkpoint['public_note'],0,20000):null,'evidence'=>array());foreach(isset($checkpoint['evidence'])&&is_array($checkpoint['evidence'])?$checkpoint['evidence']:array()as$evidence)if(isset($evidence['id'],$evidence['sha256'])&&preg_match('/^[0-9a-f-]{36}$/',(string)$evidence['id'])&&preg_match('/^[a-f0-9]{64}$/',(string)$evidence['sha256']))$cp['evidence'][]=array('id'=>(string)$evidence['id'],'kind'=>in_array(($evidence['kind']??''),array('image','video'),true)?(string)$evidence['kind']:'image','caption'=>isset($evidence['caption'])&&is_scalar($evidence['caption'])?substr((string)$evidence['caption'],0,500):null,'mime_type'=>in_array(($evidence['mime_type']??''),array('image/jpeg','image/png','image/webp','video/mp4'),true)?(string)$evidence['mime_type']:'application/octet-stream','size_bytes'=>max(0,(int)($evidence['size_bytes']??0)),'sha256'=>(string)$evidence['sha256']);$clean['checkpoints'][]=$cp;}if($clean['checkpoints'])$out['sections'][]=$clean;}
    return$out;
}

/** Keep only licensed-result facts that are safe and useful to the customer. */
function cpcc_qc_public_aql(array $source)
{
    if (!$source) return null;
    $out = array();
    foreach (array('standard','edition','sampling','inspection','level','outcome') as $key) {
        if (isset($source[$key]) && is_scalar($source[$key])) $out[$key] = substr((string) $source[$key], 0, 100);
    }
    if (isset($source['lot_size'])) $out['lot_size'] = max(0, (int) $source['lot_size']);
    $out['classes'] = array();
    foreach (isset($source['classes']) && is_array($source['classes']) ? $source['classes'] : array() as $code => $class) {
        if (!is_array($class) || !in_array((string) $code, array('critical','major','minor'), true)) continue;
        $clean = array();
        foreach (array('aql','code_letter') as $key) if (isset($class[$key]) && is_scalar($class[$key])) $clean[$key] = substr((string) $class[$key], 0, 30);
        foreach (array('sample_size','accept','reject') as $key) if (isset($class[$key])) $clean[$key] = max(0, (int) $class[$key]);
        if (isset($class['inspect_all'])) $clean['inspect_all'] = !empty($class['inspect_all']);
        $out['classes'][(string) $code] = $clean;
    }
    $out['observed'] = array();
    foreach (isset($source['observed']) && is_array($source['observed']) ? $source['observed'] : array() as $code => $observed) {
        if (!in_array((string) $code, array('sample_size','critical','major','minor'), true)) continue;
        $out['observed'][(string) $code] = max(0, (int) $observed);
    }
    return $out;
}

/** Resolve one current immutable publication inside the authenticated customer scope. */
function cpcc_qc_publication_by_uuid($db, $publicUuid, $socid)
{
    if (!cpcc_qc_reports_schema_ready($db) || !preg_match('/^[0-9a-f-]{36}$/', (string) $publicUuid) || (int) $socid <= 0) return null;
    $sql = "SELECT q.*,o.ref operation_ref,o.title operation_title,o.rowid operation_id,o.fk_project";
    $sql .= " FROM ".MAIN_DB_PREFIX."cpo_qc_publication q INNER JOIN ".MAIN_DB_PREFIX."cpo_operation o";
    $sql .= " ON o.rowid=q.fk_operation AND o.entity=q.entity AND o.fk_soc=q.fk_soc";
    $sql .= " WHERE q.entity=".(int)$GLOBALS['conf']->entity." AND q.public_uuid='".$db->escape((string)$publicUuid)."'";
    $sql .= " AND q.fk_soc=".(int)$socid." AND q.is_current=1 AND o.service_type='quality_control' AND o.status=1 AND o.customer_visible=1 LIMIT 1";
    $res = $db->query($sql); $row = $res ? $db->fetch_object($res) : null;
    if (!$row || !preg_match('/^[a-f0-9]{64}$/', (string)$row->publication_sha256) || !hash_equals((string)$row->publication_sha256, hash('sha256', (string)$row->publication_json))) return null;
    $decoded = json_decode((string)$row->publication_json, true);
    if (!is_array($decoded)) return null;
    $row->customer_projection = cpcc_qc_customer_projection($decoded);
    return $row;
}

function cpcc_qc_customer_evidence($db, $publication, $evidenceUuid)
{
    if (!$publication || !preg_match('/^[0-9a-f-]{36}$/', (string)$evidenceUuid)) return null;
    $allowed = null;
    foreach ($publication->customer_projection['sections'] as $section) foreach ($section['checkpoints'] as $checkpoint) foreach ($checkpoint['evidence'] as $evidence) {
        if (hash_equals((string)$evidence['id'], (string)$evidenceUuid)) { $allowed = $evidence; break 3; }
    }
    if (!$allowed) return null;
    $sql = "SELECT client_uuid,safe_stored_name,safe_mime,safe_size,safe_sha256,evidence_type FROM ".MAIN_DB_PREFIX."cpo_qc_evidence";
    $sql .= " WHERE entity=".(int)$GLOBALS['conf']->entity." AND fk_operation=".(int)$publication->fk_operation." AND client_uuid='".$db->escape((string)$evidenceUuid)."'";
    $sql .= " AND upload_status='finalized' LIMIT 1";
    $res = $db->query($sql); $row = $res ? $db->fetch_object($res) : null;
    if (!$row || empty($row->safe_stored_name) || !preg_match('/^[a-f0-9]{64}$/', (string)$row->safe_sha256) || !hash_equals((string)$allowed['sha256'], (string)$row->safe_sha256) || (int)$allowed['size_bytes'] !== (int)$row->safe_size || (string)$allowed['mime_type'] !== (string)$row->safe_mime) return null;
    return $row;
}

/** Resolve and integrity-check a QC publication file beneath its private storage root. */
function cpcc_qc_private_file($storedName, $subdir, $expectedSize, $expectedSha256)
{
    global $conf;
    if (basename((string)$storedName) !== (string)$storedName || !preg_match('/^[a-f0-9]{64}$/', (string)$expectedSha256)) return null;
    $base = rtrim((string)$conf->chinaplanetoperations->dir_output, '/').'/qc/'.(int)$conf->entity.'/'.trim((string)$subdir, '/');
    $root = realpath($base); $path = $root ? realpath($root.'/'.(string)$storedName) : false;
    if (!$root || !$path || !is_file($path) || is_link($path) || strpos($path, rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR) !== 0) return null;
    if ((int)filesize($path) !== (int)$expectedSize || !hash_equals((string)$expectedSha256, (string)hash_file('sha256', $path))) return null;
    return $path;
}

/** Stream one private customer file with a single validated byte range. */
function cpcc_qc_stream_file($path, $mime, $filename, $inline = true)
{
    $size = (int)filesize($path); $start = 0; $end = $size - 1; $status = 200;
    $range = isset($_SERVER['HTTP_RANGE']) ? trim((string)$_SERVER['HTTP_RANGE']) : '';
    if ($range !== '') {
        if (strpos($range, ',') !== false || !preg_match('/^bytes=(\d*)-(\d*)$/', $range, $matches) || ($matches[1] === '' && $matches[2] === '')) {
            header('Content-Range: bytes */'.$size); http_response_code(416); exit;
        }
        if ($matches[1] === '') { $suffix = (int)$matches[2]; if ($suffix <= 0) { header('Content-Range: bytes */'.$size); http_response_code(416); exit; } $start = max(0, $size - $suffix); }
        else { $start = (int)$matches[1]; $end = $matches[2] === '' ? $size - 1 : (int)$matches[2]; }
        if ($start < 0 || $start >= $size || $end < $start || $end >= $size) { header('Content-Range: bytes */'.$size); http_response_code(416); exit; }
        $status = 206;
    }
    $length = $end - $start + 1;
    http_response_code($status); header('Content-Type: '.$mime); header('Content-Length: '.$length); header('Accept-Ranges: bytes');
    header('Content-Disposition: '.($inline ? 'inline' : 'attachment')."; filename*=UTF-8''".rawurlencode(basename((string)$filename)));
    header('Cache-Control: private, no-store'); header('X-Content-Type-Options: nosniff'); header('Referrer-Policy: no-referrer');
    if ($status === 206) header('Content-Range: bytes '.$start.'-'.$end.'/'.$size);
    $handle = fopen($path, 'rb'); if (!$handle) { http_response_code(404); exit; } fseek($handle, $start); $remaining = $length;
    while ($remaining > 0 && !feof($handle)) { $chunk = fread($handle, min(1048576, $remaining)); if ($chunk === false || $chunk === '') break; echo $chunk; $remaining -= strlen($chunk); }
    fclose($handle); exit;
}

function cpcc_operation_reports($db, $socid, $projectid = 0)
{
    if (!cpcc_operation_reports_schema_ready($db) || (int)$socid <= 0) return array();
    $qcReady = cpcc_qc_reports_schema_ready($db);
    $sql = "SELECT o.rowid,o.ref,o.service_type,o.title,o.fk_project,o.fk_commande,";
    if ($qcReady) {
        $sql .= "CASE WHEN o.service_type='quality_control' THEN 'published' ELSE o.stage END stage,";
        $sql .= "CASE WHEN o.service_type='quality_control' THEN 100 ELSE o.progress END progress,";
        $sql .= "CASE WHEN o.service_type='quality_control' THEN NULL ELSE o.next_action END next_action,";
        $sql .= "CASE WHEN o.service_type='quality_control' THEN NULL ELSE o.date_deadline END date_deadline,";
        $sql .= "CASE WHEN o.service_type='quality_control' THEN NULL ELSE o.note_public END note_public,";
        $sql .= "COALESCE(q.date_publication,o.tms) tms,q.overall_result,q.version_no publication_version,q.inspection_at,q.date_publication,q.publication_json,q.publication_sha256";
    } else $sql .= "o.stage,o.progress,o.next_action,o.date_deadline,o.note_public,o.tms,NULL overall_result,NULL publication_version,NULL inspection_at,NULL date_publication,NULL publication_json,NULL publication_sha256";
    $sql .= " FROM ".MAIN_DB_PREFIX."cpo_operation o";
    if ($qcReady) $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."cpo_qc_publication q ON q.entity=o.entity AND q.fk_operation=o.rowid AND q.fk_soc=o.fk_soc AND q.is_current=1";
    $sql .= " WHERE o.entity=".(int)$GLOBALS['conf']->entity;
    $sql .= " AND o.fk_soc=".(int)$socid." AND o.status=1 AND o.customer_visible=1";
    $sql .= $qcReady ? " AND (o.service_type<>'quality_control' OR q.rowid IS NOT NULL)" : " AND o.service_type<>'quality_control'";
    if ((int)$projectid > 0) $sql .= " AND o.fk_project=".(int)$projectid;
    $sql .= " ORDER BY tms DESC,o.rowid DESC";
    $rows = array(); $res = $db->query($sql);
    while ($res && ($obj = $db->fetch_object($res))) {
        $row = get_object_vars($obj);
        if ($row['service_type'] === 'quality_control') {
            if (!preg_match('/^[a-f0-9]{64}$/', (string)$row['publication_sha256']) || !hash_equals((string)$row['publication_sha256'], hash('sha256', (string)$row['publication_json']))) continue;
            $decoded = json_decode((string)$row['publication_json'], true);
            if (!is_array($decoded)) continue;
            $projection = cpcc_qc_customer_projection($decoded);
            $row['ref'] = $projection['operation']['ref'];
            $row['title'] = $projection['operation']['title'];
            $row['note_public'] = $projection['public_note'];
        }
        unset($row['publication_json'], $row['publication_sha256']);
        $rows[] = $row;
    }
    return $rows;
}

function cpcc_operation_report($db, $id, $socid)
{
    if (!cpcc_operation_reports_schema_ready($db) || (int)$id <= 0 || (int)$socid <= 0) return null;
    $sql = "SELECT o.rowid,o.ref,o.service_type,o.title,o.fk_soc,o.fk_project,o.fk_commande,o.stage,o.progress,o.next_action,o.date_deadline,o.note_public,o.tms";
    $sql .= " FROM ".MAIN_DB_PREFIX."cpo_operation o";
    $sql .= " WHERE o.entity=".(int)$GLOBALS['conf']->entity." AND o.rowid=".(int)$id." AND o.fk_soc=".(int)$socid;
    $sql .= " AND o.status=1 AND o.customer_visible=1";
    $res = $db->query($sql); $obj = $res ? $db->fetch_object($res) : null;
    if (!$obj) return null;
    $row = get_object_vars($obj);
    if ($row['service_type'] === 'quality_control') {
        if (!cpcc_qc_reports_schema_ready($db)) return null;
        $publicationSql = "SELECT q.* FROM ".MAIN_DB_PREFIX."cpo_qc_publication q JOIN ".MAIN_DB_PREFIX."cpo_operation o ON o.rowid=q.fk_operation AND o.entity=q.entity AND o.fk_soc=q.fk_soc";
        $publicationSql .= " WHERE q.entity=".(int)$GLOBALS['conf']->entity." AND q.fk_operation=".(int)$id." AND q.fk_soc=".(int)$socid." AND q.is_current=1 AND o.status=1 AND o.customer_visible=1 LIMIT 1";
        $publicationRes = $db->query($publicationSql); $publication = $publicationRes ? $db->fetch_object($publicationRes) : null;
        if (!$publication || !preg_match('/^[a-f0-9]{64}$/', (string)$publication->publication_sha256) || !hash_equals((string)$publication->publication_sha256, hash('sha256', (string)$publication->publication_json))) return null;
        $decoded = json_decode((string)$publication->publication_json, true); if (!is_array($decoded)) return null; $projection = cpcc_qc_customer_projection($decoded);
        $row['ref']=$projection['operation']['ref']; $row['title']=$projection['operation']['title'];
        $row['stage']='published'; $row['progress']=100; $row['next_action']=null; $row['date_deadline']=null; $row['note_public']=$projection['public_note']; $row['tms']=$publication->date_publication;
        $row['service_data']=array(); foreach($projection['fields'] as $field) $row['service_data'][$field['code']]=$field['value'];
        $row['qc_publication']=$projection; $row['qc_public_uuid']=(string)$publication->public_uuid; $row['overall_result']=(string)$publication->overall_result; $row['publication_version']=(int)$publication->version_no;
        return $row;
    }
    $dataSql = "SELECT data_json FROM ".MAIN_DB_PREFIX."cpo_operation_data WHERE entity=".(int)$GLOBALS['conf']->entity." AND fk_operation=".(int)$id." AND section_code='service'";
    $dataRes = $db->query($dataSql); $data = $dataRes ? $db->fetch_object($dataRes) : null; $decoded = $data ? json_decode((string)$data->data_json, true) : array();
    $row['service_data'] = is_array($decoded) ? $decoded : array();
    return $row;
}

function cpcc_operation_report_documents($db, $operationId)
{
    if (!cpcc_operation_reports_schema_ready($db)) return array();
    $sql = "SELECT rowid,document_type,original_name,mime_type,file_size,version_no,date_review FROM ".MAIN_DB_PREFIX."cpo_document";
    $sql .= " WHERE entity=".(int)$GLOBALS['conf']->entity." AND fk_operation=".(int)$operationId;
    $sql .= " AND NOT EXISTS (SELECT 1 FROM ".MAIN_DB_PREFIX."cpo_operation o WHERE o.rowid=".(int)$operationId." AND o.entity=".(int)$GLOBALS['conf']->entity." AND o.service_type='quality_control')";
    $sql .= " AND visibility='customer_after_approval' AND review_status IN ('approved','final_received') AND is_current=1";
    $sql .= " ORDER BY document_type,version_no DESC";
    $rows = array(); $res = $db->query($sql);
    while ($res && ($obj = $db->fetch_object($res))) $rows[] = get_object_vars($obj);
    return $rows;
}
