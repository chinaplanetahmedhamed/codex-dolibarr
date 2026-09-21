<?php
require_once __DIR__.'/ui.lib.php';

function cpcc_export_schema_ready($db)
{
    $header = $db->query("SHOW TABLES LIKE '".$db->escape(MAIN_DB_PREFIX."cpcc_export_document")."'");
    $line = $db->query("SHOW TABLES LIKE '".$db->escape(MAIN_DB_PREFIX."cpcc_export_document_line")."'");
    return $header && $line && $db->num_rows($header) > 0 && $db->num_rows($line) > 0;
}

function cpcc_export_type_label($type)
{
    $labels = array(
        'commercial_invoice' => cpcc_ui_english_label('Commercial Invoice', 'Commercial Invoice / فاتورة تجارية'),
        'packing_list' => cpcc_ui_english_label('Packing List', 'Packing List / قائمة تعبئة'),
        'bill_of_lading' => cpcc_ui_english_label('Bill of Lading', 'Bill of Lading / بوليصة شحن'),
        'cargox' => cpcc_ui_english_label('CargoX Document', 'CargoX Document / مستند CargoX'),
        'certificate_origin' => cpcc_ui_english_label('Certificate of Origin', 'Certificate of Origin / شهادة منشأ'),
        'inspection' => cpcc_ui_english_label('Inspection Document', 'Inspection Document / مستند فحص'),
        'other' => cpcc_ui_english_label('Other Export Document', 'Other Export Document / مستند آخر'),
    );
    return isset($labels[$type]) ? $labels[$type] : $type;
}

function cpcc_export_documents($db, $socid, $projectid = 0, $shipmentid = 0, $customerVisibleOnly = true)
{
    $rows = array();
    if (!cpcc_export_schema_ready($db) || (int) $socid <= 0) return $rows;
    $sql = "SELECT d.*,p.ref project_ref,p.title project_title,l.ref shipment_ref";
    $sql .= " FROM ".MAIN_DB_PREFIX."cpcc_export_document d";
    $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."projet p ON p.rowid=d.fk_project AND p.entity=d.entity AND p.fk_soc=d.fk_soc";
    $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."chinaplanetlogistics l ON l.rowid=d.fk_shipment AND l.entity=d.entity AND l.fk_soc=d.fk_soc";
    $sql .= " WHERE d.entity=".(int) $GLOBALS['conf']->entity." AND d.fk_soc=".(int) $socid." AND d.status=1";
    if ((int) $projectid > 0) $sql .= " AND d.fk_project=".(int) $projectid;
    if ((int) $shipmentid > 0) $sql .= " AND d.fk_shipment=".(int) $shipmentid;
    if ($customerVisibleOnly) $sql .= " AND d.customer_visible=1 AND (d.fk_shipment IS NULL OR l.customer_visible=1)";
    $sql .= " ORDER BY d.document_date DESC,d.rowid DESC";
    $resql = $db->query($sql);
    if ($resql) while ($obj = $db->fetch_object($resql)) $rows[] = $obj;
    return $rows;
}

function cpcc_export_fetch($db, $id, $socid, $customerVisibleOnly = true)
{
    if (!cpcc_export_schema_ready($db)) return null;
    $sql = "SELECT d.*,p.ref project_ref,p.title project_title,l.ref shipment_ref,l.container_no,l.bl_no";
    $sql .= " FROM ".MAIN_DB_PREFIX."cpcc_export_document d";
    $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."projet p ON p.rowid=d.fk_project AND p.entity=d.entity AND p.fk_soc=d.fk_soc";
    $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."chinaplanetlogistics l ON l.rowid=d.fk_shipment AND l.entity=d.entity AND l.fk_soc=d.fk_soc";
    $sql .= " WHERE d.rowid=".(int) $id." AND d.entity=".(int) $GLOBALS['conf']->entity." AND d.fk_soc=".(int) $socid." AND d.status=1";
    if ($customerVisibleOnly) $sql .= " AND d.customer_visible=1 AND (d.fk_shipment IS NULL OR l.customer_visible=1)";
    $resql = $db->query($sql);
    $document = $resql ? $db->fetch_object($resql) : null;
    if (!$document) return null;
    $document->lines = array();
    $lineSql = "SELECT * FROM ".MAIN_DB_PREFIX."cpcc_export_document_line WHERE fk_document=".(int) $document->rowid." ORDER BY position,rowid";
    $lineRes = $db->query($lineSql);
    if ($lineRes) while ($line = $db->fetch_object($lineRes)) $document->lines[] = $line;
    return $document;
}

function cpcc_export_totals($document)
{
    $result = array('amount'=>0.0,'cartons'=>0.0,'net_weight'=>0.0,'gross_weight'=>0.0);
    if (empty($document->lines)) return $result;
    foreach ($document->lines as $line) {
        $result['amount'] += (float) $line->total_amount;
        $result['cartons'] += (float) $line->cartons;
        $result['net_weight'] += (float) $line->net_weight;
        $result['gross_weight'] += (float) $line->gross_weight;
    }
    return $result;
}
