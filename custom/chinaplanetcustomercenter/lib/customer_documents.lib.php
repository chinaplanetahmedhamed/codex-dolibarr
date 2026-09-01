<?php

function cpcc_customer_document_table_ready($db, $table)
{
    $table = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $table);
    if ($table === '') return false;
    $resql = $db->query("SHOW TABLES LIKE '".$db->escape(MAIN_DB_PREFIX.$table)."'");
    return $resql && $db->num_rows($resql) > 0;
}

function cpcc_customer_document_source_label($source)
{
    $labels = array(
        'commercial_document'=>'Commercial document / مستند تجاري',
        'project_asset'=>'Project document / مستند مشروع',
        'operation_document'=>'Operations document / مستند عمليات',
        'shipment_document'=>'Shipment document / مستند شحنة',
        'core_document'=>'Dolibarr document / مستند Dolibarr',
    );
    return isset($labels[$source]) ? $labels[$source] : 'Document / مستند';
}

function cpcc_customer_document_allowed_source_elements()
{
    return array('invoice', 'order', 'project', 'customer');
}

function cpcc_customer_document_core_source($db, $socid, $sourceElement, $sourceId)
{
    global $conf;
    $sourceElement = (string) $sourceElement;
    $sourceId = (int) $sourceId;
    $socid = (int) $socid;
    if ($sourceId <= 0 || $socid <= 0 || !in_array($sourceElement, cpcc_customer_document_allowed_source_elements(), true)) return null;

    $map = array(
        'invoice'=>array('table'=>'facture','soc'=>'fk_soc','project'=>'fk_projet','ref'=>'ref','conf'=>'facture'),
        'order'=>array('table'=>'commande','soc'=>'fk_soc','project'=>'fk_projet','ref'=>'ref','conf'=>'commande'),
        'project'=>array('table'=>'projet','soc'=>'fk_soc','project'=>'rowid','ref'=>'ref','conf'=>'projet'),
        'customer'=>array('table'=>'societe','soc'=>'rowid','project'=>'NULL','ref'=>'nom','conf'=>'societe'),
    );
    $config = $map[$sourceElement];
    $sql = "SELECT rowid,".$config['soc']." fk_soc,".$config['project']." fk_project,".$config['ref']." source_ref FROM ".MAIN_DB_PREFIX.$config['table'];
    $sql .= " WHERE rowid=".$sourceId." AND entity=".(int)$conf->entity." AND ".$config['soc']."=".$socid." LIMIT 1";
    $resql = $db->query($sql);
    $row = $resql ? $db->fetch_object($resql) : null;
    if (!$row) return null;

    $moduleConf = isset($conf->{$config['conf']}) ? $conf->{$config['conf']} : null;
    $root = '';
    if ($moduleConf && !empty($moduleConf->multidir_output) && !empty($moduleConf->multidir_output[$conf->entity])) $root = (string) $moduleConf->multidir_output[$conf->entity];
    elseif ($moduleConf && !empty($moduleConf->dir_output)) $root = (string) $moduleConf->dir_output;
    if ($root === '') return null;
    $objectRoot = rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.dol_sanitizeFileName((string)$row->source_ref);
    return array(
        'element'=>$sourceElement,
        'id'=>$sourceId,
        'socid'=>$socid,
        'project_id'=>(int)$row->fk_project,
        'ref'=>(string)$row->source_ref,
        'root'=>$objectRoot,
    );
}

function cpcc_customer_document_safe_relative_path($source, $relativePath)
{
    $relativePath = str_replace('\\', '/', trim((string) $relativePath));
    $relativePath = ltrim($relativePath, '/');
    if ($relativePath === '' || strpos($relativePath, "\0") !== false || preg_match('~(^|/)\.\.(/|$)~', $relativePath)) return null;
    $root = realpath($source['root']);
    $path = realpath($source['root'].DIRECTORY_SEPARATOR.$relativePath);
    if ($root === false || $path === false || !is_file($path) || !is_readable($path) || is_link($path)) return null;
    if (strpos($path, rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR) !== 0) return null;
    return array('root'=>$root, 'path'=>$path, 'relative'=>str_replace(DIRECTORY_SEPARATOR, '/', substr($path, strlen(rtrim($root, DIRECTORY_SEPARATOR)) + 1)));
}

function cpcc_customer_document_publish_core($db, $socid, $sourceElement, $sourceId, $relativePath, $documentType, $userId)
{
    global $conf;
    if (!cpcc_customer_document_table_ready($db, 'cpcc_customer_document')) return array('ok'=>false, 'error'=>'Document publication schema is not ready.');
    $source = cpcc_customer_document_core_source($db, $socid, $sourceElement, $sourceId);
    $safe = $source ? cpcc_customer_document_safe_relative_path($source, $relativePath) : null;
    if (!$source || !$safe || (int)$userId <= 0) return array('ok'=>false, 'error'=>'The selected customer document was rejected.');
    $size = filesize($safe['path']);
    $hash = hash_file('sha256', $safe['path']);
    if ($size === false || !is_string($hash) || !preg_match('/^[a-f0-9]{64}$/', $hash)) return array('ok'=>false, 'error'=>'The selected customer document could not be fingerprinted.');
    $mime = function_exists('dol_mimetype') ? dol_mimetype($safe['path']) : 'application/octet-stream';
    $allowedTypes = array('invoice','order','project','customer','certificate','contract','other');
    $documentType = in_array($documentType, $allowedTypes, true) ? $documentType : 'other';
    $now = $db->idate(dol_now());
    $sql = "INSERT INTO ".MAIN_DB_PREFIX."cpcc_customer_document(entity,fk_soc,fk_project,source_element,source_id,source_ref,relative_path,original_name,document_type,mime_type,file_size,file_sha256,customer_visible,status,fk_user_publish,date_publication) VALUES(";
    $sql .= (int)$conf->entity.",".(int)$socid.",".($source['project_id'] > 0 ? (int)$source['project_id'] : 'NULL').",'".$db->escape($source['element'])."',".(int)$source['id'].",'".$db->escape($source['ref'])."','".$db->escape($safe['relative'])."','".$db->escape(basename($safe['path']))."','".$db->escape($documentType)."','".$db->escape($mime)."',".(int)$size.",'".$db->escape($hash)."',1,1,".(int)$userId.",'".$now."')";
    $sql .= " ON DUPLICATE KEY UPDATE fk_soc=VALUES(fk_soc),fk_project=VALUES(fk_project),source_ref=VALUES(source_ref),original_name=VALUES(original_name),document_type=VALUES(document_type),mime_type=VALUES(mime_type),file_size=VALUES(file_size),file_sha256=VALUES(file_sha256),customer_visible=1,status=1,fk_user_modif=".(int)$userId.",date_publication='".$now."',date_unpublication=NULL";
    return $db->query($sql) ? array('ok'=>true, 'error'=>'') : array('ok'=>false, 'error'=>$db->lasterror());
}

function cpcc_customer_document_unpublish_core($db, $socid, $publicationId, $userId)
{
    global $conf;
    $now = $db->idate(dol_now());
    $sql = "UPDATE ".MAIN_DB_PREFIX."cpcc_customer_document SET customer_visible=0,status=0,fk_user_modif=".(int)$userId.",date_unpublication='".$now."' WHERE rowid=".(int)$publicationId." AND entity=".(int)$conf->entity." AND fk_soc=".(int)$socid." AND customer_visible=1 AND status=1";
    $resql = $db->query($sql);
    return $resql && $db->affected_rows($resql) === 1;
}

/** Normalize all explicitly customer-visible non-shipment document sources. */
function cpcc_customer_documents($db, $socid, $projectId = 0)
{
    global $conf;
    $socid = (int) $socid;
    $projectId = (int) $projectId;
    $documents = array();
    if ($socid <= 0) return $documents;

    if (function_exists('cpcc_export_documents')) {
        foreach (cpcc_export_documents($db, $socid, $projectId, 0, true) as $row) {
            $documents[] = array('source'=>'commercial_document','source_id'=>(int)$row->rowid,'name'=>(string)$row->ref,'type'=>(string)$row->document_type,'project_id'=>(int)$row->fk_project,'project_ref'=>(string)$row->project_ref,'project_title'=>(string)$row->project_title,'shipment_id'=>(int)$row->fk_shipment,'shipment_ref'=>(string)$row->shipment_ref,'date'=>(string)$row->document_date,'mime'=>'text/html','size'=>0);
        }
    }

    if (cpcc_customer_document_table_ready($db, 'cpkanban_asset')) {
        $sql = "SELECT a.rowid,a.document_type,a.original_name,a.mime_type,a.file_size,a.version_no,a.date_creation,a.fk_shipment,p.rowid project_id,p.ref project_ref,p.title project_title,s.ref shipment_ref FROM ".MAIN_DB_PREFIX."cpkanban_asset a INNER JOIN ".MAIN_DB_PREFIX."cpkanban_card c ON c.rowid=a.fk_card AND c.entity=a.entity AND c.fk_soc=a.fk_soc INNER JOIN ".MAIN_DB_PREFIX."projet p ON p.rowid=c.fk_project AND p.entity=c.entity AND p.fk_soc=c.fk_soc LEFT JOIN ".MAIN_DB_PREFIX."chinaplanetlogistics s ON s.rowid=a.fk_shipment AND s.entity=a.entity AND s.fk_soc=a.fk_soc WHERE a.entity=".(int)$conf->entity." AND a.fk_soc=".$socid." AND c.status=1 AND c.cust_visible=1 AND a.status=1 AND a.customer_visible=1 AND a.review_status IN ('approved','final_received')";
        if ($projectId > 0) $sql .= " AND p.rowid=".$projectId;
        $resql = $db->query($sql." ORDER BY a.tms DESC,a.rowid DESC");
        while ($resql && ($row=$db->fetch_object($resql))) $documents[] = array('source'=>'project_asset','source_id'=>(int)$row->rowid,'name'=>basename((string)$row->original_name),'type'=>(string)($row->document_type?:'project'),'project_id'=>(int)$row->project_id,'project_ref'=>(string)$row->project_ref,'project_title'=>(string)$row->project_title,'shipment_id'=>(int)$row->fk_shipment,'shipment_ref'=>(string)$row->shipment_ref,'date'=>(string)$row->date_creation,'mime'=>(string)$row->mime_type,'size'=>(int)$row->file_size);
    }

    if (cpcc_customer_document_table_ready($db, 'cpo_document') && cpcc_customer_document_table_ready($db, 'cpo_operation')) {
        $sql = "SELECT d.rowid,d.document_type,d.original_name,d.mime_type,d.file_size,COALESCE(d.date_review,d.date_creation) publication_date,o.fk_project,o.fk_shipment,o.ref operation_ref,p.ref project_ref,p.title project_title,s.ref shipment_ref FROM ".MAIN_DB_PREFIX."cpo_document d INNER JOIN ".MAIN_DB_PREFIX."cpo_operation o ON o.rowid=d.fk_operation AND o.entity=d.entity LEFT JOIN ".MAIN_DB_PREFIX."projet p ON p.rowid=o.fk_project AND p.entity=o.entity AND p.fk_soc=o.fk_soc LEFT JOIN ".MAIN_DB_PREFIX."chinaplanetlogistics s ON s.rowid=o.fk_shipment AND s.entity=o.entity AND s.fk_soc=o.fk_soc WHERE d.entity=".(int)$conf->entity." AND o.fk_soc=".$socid." AND o.status=1 AND o.customer_visible=1 AND d.visibility='customer_after_approval' AND d.review_status IN ('approved','final_received') AND d.is_current=1";
        if ($projectId > 0) $sql .= " AND o.fk_project=".$projectId;
        $resql = $db->query($sql." ORDER BY d.tms DESC,d.rowid DESC");
        while ($resql && ($row=$db->fetch_object($resql))) $documents[] = array('source'=>'operation_document','source_id'=>(int)$row->rowid,'name'=>basename((string)$row->original_name),'type'=>(string)$row->document_type,'project_id'=>(int)$row->fk_project,'project_ref'=>(string)$row->project_ref,'project_title'=>(string)$row->project_title,'shipment_id'=>(int)$row->fk_shipment,'shipment_ref'=>(string)$row->shipment_ref,'date'=>(string)$row->publication_date,'mime'=>(string)$row->mime_type,'size'=>(int)$row->file_size);
    }

    if (cpcc_customer_document_table_ready($db, 'cpcc_customer_document')) {
        $sql = "SELECT d.*,p.ref project_ref,p.title project_title,s.ref shipment_ref FROM ".MAIN_DB_PREFIX."cpcc_customer_document d LEFT JOIN ".MAIN_DB_PREFIX."projet p ON p.rowid=d.fk_project AND p.entity=d.entity AND p.fk_soc=d.fk_soc LEFT JOIN ".MAIN_DB_PREFIX."chinaplanetlogistics s ON s.rowid=d.fk_shipment AND s.entity=d.entity AND s.fk_soc=d.fk_soc WHERE d.entity=".(int)$conf->entity." AND d.fk_soc=".$socid." AND d.customer_visible=1 AND d.status=1";
        if ($projectId > 0) $sql .= " AND d.fk_project=".$projectId;
        $resql = $db->query($sql." ORDER BY d.date_publication DESC,d.rowid DESC");
        while ($resql && ($row=$db->fetch_object($resql))) $documents[] = array('source'=>'core_document','source_id'=>(int)$row->rowid,'name'=>basename((string)$row->original_name),'type'=>(string)$row->document_type,'project_id'=>(int)$row->fk_project,'project_ref'=>(string)$row->project_ref,'project_title'=>(string)$row->project_title,'shipment_id'=>(int)$row->fk_shipment,'shipment_ref'=>(string)$row->shipment_ref,'date'=>(string)$row->date_publication,'mime'=>(string)$row->mime_type,'size'=>(int)$row->file_size);
    }

    usort($documents, function ($a, $b) { return strcmp((string)$b['date'], (string)$a['date']); });
    return $documents;
}

function cpcc_customer_document_core_file($db, $socid, $publicationId)
{
    global $conf;
    if (!cpcc_customer_document_table_ready($db, 'cpcc_customer_document')) return null;
    $sql = "SELECT * FROM ".MAIN_DB_PREFIX."cpcc_customer_document WHERE rowid=".(int)$publicationId." AND entity=".(int)$conf->entity." AND fk_soc=".(int)$socid." AND customer_visible=1 AND status=1";
    $resql = $db->query($sql); $row = $resql ? $db->fetch_object($resql) : null;
    if (!$row) return null;
    $source = cpcc_customer_document_core_source($db, $socid, $row->source_element, $row->source_id);
    $safe = $source ? cpcc_customer_document_safe_relative_path($source, $row->relative_path) : null;
    if (!$safe) return null;
    $size = filesize($safe['path']); $hash = hash_file('sha256', $safe['path']);
    if ($size === false || (int)$size !== (int)$row->file_size || !is_string($hash) || !hash_equals((string)$row->file_sha256, $hash)) return null;
    return array('path'=>$safe['path'],'name'=>basename((string)$row->original_name),'mime'=>(string)$row->mime_type,'size'=>(int)$size);
}
