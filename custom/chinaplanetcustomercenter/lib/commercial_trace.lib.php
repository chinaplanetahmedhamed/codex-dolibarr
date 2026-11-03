<?php
require_once __DIR__.'/ui.lib.php';

/** Customer-safe native quotation rows, always scoped to one customer. */
function cpcc_customer_quotations($db, $socid, $currencyFallback, $projectid = 0)
{
    $socid = (int) $socid;
    $projectid = (int) $projectid;
    if ($socid <= 0) return array();

    $sql = "SELECT p.rowid,p.ref,p.ref_client,p.fk_projet,p.datep,p.fin_validite,p.fk_statut,p.total_ttc,p.multicurrency_code,p.multicurrency_total_ttc,pr.ref project_ref";
    $sql .= " FROM ".MAIN_DB_PREFIX."propal p";
    $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."projet pr ON pr.rowid=p.fk_projet AND pr.entity=p.entity AND pr.fk_soc=p.fk_soc";
    $sql .= " WHERE p.entity IN (".getEntity('propal').") AND p.fk_soc=".$socid." AND p.fk_statut>0";
    if ($projectid > 0) $sql .= " AND p.fk_projet=".$projectid;
    $sql .= " ORDER BY p.datep DESC,p.rowid DESC";

    $rows = array();
    $resql = $db->query($sql);
    if (!$resql) return $rows;
    while ($obj = $db->fetch_object($resql)) {
        $hasMulticurrency = trim((string) $obj->multicurrency_code) !== '';
        $rows[] = array(
            'id'=>(int) $obj->rowid,
            'ref'=>(string) $obj->ref,
            'ref_client'=>(string) $obj->ref_client,
            'project_id'=>(int) $obj->fk_projet,
            'project_ref'=>(string) $obj->project_ref,
            'date'=>$obj->datep,
            'valid_until'=>$obj->fin_validite,
            'status'=>(int) $obj->fk_statut,
            'currency'=>cpcc_financial_currency($hasMulticurrency ? $obj->multicurrency_code : $currencyFallback, $currencyFallback),
            'total'=>$hasMulticurrency ? (float) $obj->multicurrency_total_ttc : (float) $obj->total_ttc,
        );
    }
    return $rows;
}

function cpcc_customer_quotation_status($status)
{
    $labels = array(
        1=>cpcc_ui_english_label('Validated', 'معتمد / Validated'),
        2=>cpcc_ui_english_label('Signed', 'موقّع / Signed'),
        3=>cpcc_ui_english_label('Not signed', 'غير موقّع / Not signed'),
        4=>cpcc_ui_english_label('Billed', 'مفوّت / Billed'),
    );
    return isset($labels[(int) $status]) ? $labels[(int) $status] : cpcc_ui_english_label('Validated', 'معتمد / Validated');
}

/** Native links from one quotation to customer-visible orders. */
function cpcc_customer_quotation_orders($db, $quotationId, $socid)
{
    $sql = "SELECT c.rowid,c.ref,c.fk_projet,c.fk_statut,c.date_commande FROM ".MAIN_DB_PREFIX."element_element ee";
    $sql .= " INNER JOIN ".MAIN_DB_PREFIX."commande c ON c.rowid=ee.fk_target";
    $sql .= " WHERE ee.sourcetype='propal' AND ee.targettype='commande' AND ee.fk_source=".(int) $quotationId;
    $sql .= " AND c.entity IN (".getEntity('commande').") AND c.fk_soc=".(int) $socid." AND c.fk_statut>0 ORDER BY c.rowid";
    $rows = array();
    $resql = $db->query($sql);
    if ($resql) while ($obj = $db->fetch_object($resql)) $rows[] = $obj;
    return $rows;
}

/** Native links from one order to proposals, invoices and customer-visible shipments. */
function cpcc_customer_order_trace($db, $orderId, $socid, $isExternal)
{
    $trace = array('quotations'=>array(),'invoices'=>array(),'shipments'=>array());
    $orderId = (int) $orderId;
    $socid = (int) $socid;

    $sql = "SELECT p.rowid,p.ref,p.fk_statut FROM ".MAIN_DB_PREFIX."element_element ee INNER JOIN ".MAIN_DB_PREFIX."propal p ON p.rowid=ee.fk_source WHERE ee.sourcetype='propal' AND ee.targettype='commande' AND ee.fk_target=".$orderId." AND p.entity IN (".getEntity('propal').") AND p.fk_soc=".$socid." AND p.fk_statut>0 ORDER BY p.rowid";
    $resql = $db->query($sql);
    if ($resql) while ($obj=$db->fetch_object($resql)) $trace['quotations'][]=$obj;

    $sql = "SELECT f.rowid,f.ref,f.fk_statut FROM ".MAIN_DB_PREFIX."element_element ee INNER JOIN ".MAIN_DB_PREFIX."facture f ON f.rowid=ee.fk_target WHERE ee.sourcetype='commande' AND ee.targettype='facture' AND ee.fk_source=".$orderId." AND f.entity IN (".getEntity('invoice').") AND f.fk_soc=".$socid." AND f.fk_statut>0 ORDER BY f.rowid";
    $resql = $db->query($sql);
    if ($resql) while ($obj=$db->fetch_object($resql)) $trace['invoices'][]=$obj;

    $sql = "SELECT s.rowid,s.ref,s.fk_statut FROM ".MAIN_DB_PREFIX."chinaplanetlogistics s WHERE s.entity=".(int) $GLOBALS['conf']->entity." AND s.fk_soc=".$socid." AND s.fk_commande=".$orderId;
    if ($isExternal) $sql .= " AND s.customer_visible=1";
    $sql .= " ORDER BY s.rowid";
    $resql = $db->query($sql);
    if ($resql) while ($obj=$db->fetch_object($resql)) $trace['shipments'][]=$obj;
    return $trace;
}
