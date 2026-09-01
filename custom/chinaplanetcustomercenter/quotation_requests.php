<?php

require $_SERVER['DOCUMENT_ROOT'].'/main.inc.php';
require_once __DIR__.'/lib/portal.lib.php';

cpcc_portal_require_access($user);
$scope = cpcc_portal_user_scope($user);
if (empty($scope['is_external']) || (int)$scope['socid'] <= 0) accessforbidden();
$entity = (int)$conf->entity;
$socid = (int)$scope['socid'];
$requestRef = trim((string) GETPOST('ref', 'alphanohtml'));
$locale = strtolower(substr((string)$langs->getDefaultLang(), 0, 2));
$copy = array(
    'ar'=>array('title'=>'طلبات عروض السعر','empty'=>'لا توجد طلبات منشورة في مركز العميل.','product'=>'المنتج','cartons'=>'كراتين','pieces'=>'قطع','cbm'=>'الحجم م³','weight'=>'الوزن كجم','price'=>'السعر','pending'=>'بانتظار مراجعة بيانات التعبئة أو السعر','quotation'=>'عرض السعر المعتمد','received'=>'تم الاستلام','qty'=>'الكمية','unit_price'=>'سعر الوحدة','line_total'=>'الإجمالي','quotation_total'=>'إجمالي العرض'),
    'en'=>array('title'=>'Quotation requests','empty'=>'No quotation requests have been published to Customer Center.','product'=>'Product','cartons'=>'Cartons','pieces'=>'Pieces','cbm'=>'CBM','weight'=>'Weight kg','price'=>'Price','pending'=>'Packing or price review pending','quotation'=>'Approved quotation','received'=>'Received','qty'=>'Quantity','unit_price'=>'Unit price','line_total'=>'Total','quotation_total'=>'Quotation total'),
    'fr'=>array('title'=>'Demandes de devis','empty'=>'Aucune demande de devis n’est publiée dans le Centre Client.','product'=>'Produit','cartons'=>'Cartons','pieces'=>'Pièces','cbm'=>'Volume m³','weight'=>'Poids kg','price'=>'Prix','pending'=>'Vérification emballage ou prix en attente','quotation'=>'Devis approuvé','received'=>'Reçue','qty'=>'Quantité','unit_price'=>'Prix unitaire','line_total'=>'Total','quotation_total'=>'Total du devis'),
);
$text = $copy[isset($copy[$locale]) ? $locale : 'en'];

$sql = "SELECT r.ref,r.status,r.request_json,r.fk_propal,r.proposal_customer_visible,r.date_creation,p.rowid AS propal_id,p.ref AS propal_ref,p.fk_statut AS propal_status,p.total_ttc,p.multicurrency_code,p.multicurrency_total_ttc";
$sql .= " FROM ".MAIN_DB_PREFIX."cpo_public_request r";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."propal p ON p.rowid=r.fk_propal AND p.entity=r.entity AND p.fk_soc=r.fk_soc AND p.fk_statut IN (1,2,4)";
$sql .= " WHERE r.entity=".$entity." AND r.fk_soc=".$socid." AND r.request_type='catalog_order' AND r.schema_version=2 AND r.request_customer_visible=1";
if ($requestRef !== '') $sql .= " AND r.ref='".$db->escape($requestRef)."'";
$sql .= " ORDER BY r.date_creation DESC,r.rowid DESC LIMIT 100";
$res = $db->query($sql);
$requests = array();
if ($res) while ($obj = $db->fetch_object($res)) $requests[] = (array)$obj;

$morecss = array(cpcc_portal_asset('/custom/chinaplanetcustomercenter/css/orders.css'));
llxHeader('', $text['title'], '', '', 0, 0, array(), $morecss);
cpcc_portal_navigation($user, 'quotation_requests');
print '<main class="cpcc-orders" dir="'.($locale === 'ar' ? 'rtl' : 'ltr').'"><h1>'.dol_escape_htmltag($text['title']).'</h1>';
if (!$requests) print '<div class="info">'.dol_escape_htmltag($text['empty']).'</div>';
foreach ($requests as $request) {
    $payload = json_decode((string)$request['request_json'], true);
    $lines = is_array($payload['details']['lines'] ?? null) ? $payload['details']['lines'] : array();
    print '<section class="fichecenter"><h2>'.dol_escape_htmltag($request['ref']).'</h2><p>'.dol_escape_htmltag($text['received']).' · '.dol_escape_htmltag(cpcc_portal_date($request['date_creation'], true)).'</p>';
    print '<div class="div-table-responsive"><table class="liste centpercent"><thead><tr><th>'.dol_escape_htmltag($text['product']).'</th><th>'.dol_escape_htmltag($text['cartons']).'</th><th>'.dol_escape_htmltag($text['pieces']).'</th><th>'.dol_escape_htmltag($text['cbm']).'</th><th>'.dol_escape_htmltag($text['weight']).'</th><th>'.dol_escape_htmltag($text['price']).'</th></tr></thead><tbody>';
    foreach ($lines as $line) {
        if (!is_array($line)) continue;
        $pieces = isset($line['unitsPerCarton']) && $line['unitsPerCarton'] !== null ? (int)$line['unitsPerCarton'] * (int)$line['cartons'] : null;
        $cbm = !empty($line['calculationReady']) ? (float)$line['cbmPerCarton'] * (int)$line['cartons'] : null;
        $weight = !empty($line['calculationReady']) ? (float)$line['grossWeightKg'] * (int)$line['cartons'] : null;
        $price = is_array($line['price'] ?? null) ? $line['price'] : array();
        $total = ($price['visibility'] ?? '') === 'public' && isset($price['unitPrice']) && $price['unitPrice'] !== null && $pieces !== null ? (float)$price['unitPrice'] * $pieces : null;
        print '<tr><td><strong>'.dol_escape_htmltag($line['name'] ?? '').'</strong><br><small>'.dol_escape_htmltag($line['optionLabel'] ?? '').'</small>'.(empty($line['calculationReady']) ? '<br><span class="warning">'.dol_escape_htmltag($text['pending']).'</span>' : '').'</td><td>'.(int)($line['cartons'] ?? 0).'</td><td>'.($pieces === null ? '—' : (int)$pieces).'</td><td>'.($cbm === null ? '—' : price($cbm)).'</td><td>'.($weight === null ? '—' : price($weight)).'</td><td>'.($total === null ? dol_escape_htmltag($text['pending']) : price($total).' '.dol_escape_htmltag($price['currency'] ?? '')).'</td></tr>';
    }
    print '</tbody></table></div>';
    if (!empty($request['proposal_customer_visible']) && !empty($request['propal_id']) && !empty($request['propal_ref'])) {
        $currency = trim((string)$request['multicurrency_code']) !== '' ? (string)$request['multicurrency_code'] : (string)$conf->currency;
        $proposalTotal = trim((string)$request['multicurrency_code']) !== '' ? (float)$request['multicurrency_total_ttc'] : (float)$request['total_ttc'];
        print '<h3>'.dol_escape_htmltag($text['quotation']).': '.dol_escape_htmltag($request['propal_ref']).'</h3>';
        // Staff line notes are stored in propaldet.description and must never be
        // projected to Customer Center. Use only the product or explicit line label.
        $lineSql = "SELECT COALESCE(NULLIF(TRIM(p.label),''),NULLIF(TRIM(pd.label),'')) AS public_label,pd.qty,pd.subprice,pd.total_ht,pd.multicurrency_subprice,pd.multicurrency_total_ht FROM ".MAIN_DB_PREFIX."propaldet pd LEFT JOIN ".MAIN_DB_PREFIX."product p ON p.rowid=pd.fk_product WHERE pd.fk_propal=".(int)$request['propal_id']." ORDER BY pd.rang,pd.rowid";
        $lineRes = $db->query($lineSql);
        print '<div class="div-table-responsive"><table class="liste centpercent"><thead><tr><th>'.dol_escape_htmltag($text['product']).'</th><th>'.dol_escape_htmltag($text['qty']).'</th><th>'.dol_escape_htmltag($text['unit_price']).'</th><th>'.dol_escape_htmltag($text['line_total']).'</th></tr></thead><tbody>';
        if ($lineRes) while ($proposalLine = $db->fetch_object($lineRes)) {
            $multi = trim((string)$request['multicurrency_code']) !== '';
            $unitPrice = $multi ? (float)$proposalLine->multicurrency_subprice : (float)$proposalLine->subprice;
            $lineTotal = $multi ? (float)$proposalLine->multicurrency_total_ht : (float)$proposalLine->total_ht;
            print '<tr><td>'.dol_escape_htmltag($proposalLine->public_label ?: $text['product']).'</td><td>'.dol_escape_htmltag((string)$proposalLine->qty).'</td><td>'.price($unitPrice).' '.dol_escape_htmltag($currency).'</td><td>'.price($lineTotal).' '.dol_escape_htmltag($currency).'</td></tr>';
        }
        print '</tbody></table></div><p><strong>'.dol_escape_htmltag($text['quotation_total']).': '.price($proposalTotal).' '.dol_escape_htmltag($currency).'</strong></p>';
    }
    print '</section>';
}
print '</main>';
llxFooter();
$db->close();
