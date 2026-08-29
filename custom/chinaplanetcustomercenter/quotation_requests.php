<?php
require '../main.inc.php';
require_once __DIR__.'/lib/portal.lib.php';
require_once DOL_DOCUMENT_ROOT.'/custom/chinaplanetoperations/class/PublicRequestPublicationService.class.php';
require_once DOL_DOCUMENT_ROOT.'/comm/propal/class/propal.class.php';

cpcc_portal_require_access($user);
$scope = cpcc_portal_user_scope($user);
if (empty($scope['is_external']) || (int) $scope['socid'] <= 0) accessforbidden();
$socid = (int) $scope['socid'];
$publicRef = trim((string) GETPOST('ref', 'alphanohtml'));
$sql = "SELECT rowid,ref,status,request_json,fk_propal,proposal_customer_visible,date_creation FROM ".MAIN_DB_PREFIX."cpo_public_request WHERE entity=".(int) $conf->entity." AND fk_soc=".$socid." AND request_customer_visible=1";
if ($publicRef !== '') $sql .= " AND ref='".$db->escape($publicRef)."'";
$sql .= " ORDER BY date_creation DESC,rowid DESC LIMIT 100";
$res = $db->query($sql);
$rows = array();
if ($res) while ($obj = $db->fetch_object($res)) $rows[] = (array) $obj;
if ($publicRef !== '' && empty($rows)) cpcc_portal_access_denied($user, 'quotation_requests');

$langs->load('chinaplanetcustomercenter@chinaplanetcustomercenter');
$activeLanguage = strtolower((string) ($langs->defaultlang ?? 'en_US'));
$pageDirection = strpos($activeLanguage, 'ar') === 0 ? 'rtl' : 'ltr';
$statusLabels = array('new'=>'QuotationStatusNew','qualified'=>'QuotationStatusQualified','waiting_information'=>'QuotationStatusWaitingInformation','converted'=>'QuotationStatusConverted','disqualified'=>'QuotationStatusDisqualified','closed'=>'QuotationStatusClosed');
$missingFieldLabels = array('unitsPerCarton'=>'MissingUnitsPerCarton','cbmPerCarton'=>'MissingCbmPerCarton','grossWeightKg'=>'MissingGrossWeightKg','packingSource'=>'MissingPackingSource','packingComplete'=>'MissingPackingComplete');
$morecss = array(cpcc_portal_asset('/custom/chinaplanetcustomercenter/css/quotation_requests.css'));
llxHeader('', $langs->trans('QuotationRequests'), '', '', 0, 0, array(), $morecss);
cpcc_portal_navigation($user, 'quotation_requests');
print '<main class="cpcc-quotes" dir="'.$pageDirection.'"><header><h1>'.$langs->trans('QuotationRequests').'</h1><p>'.$langs->trans('QuotationRequestsIntro').'</p></header>';
if ($publicRef === '') {
    print '<div class="cpcc-quote-grid">';
    foreach ($rows as $row) { $statusKey=$statusLabels[$row['status']] ?? 'QuotationStatusReceived'; print '<a class="cpcc-quote-card" href="?ref='.rawurlencode($row['ref']).'"><strong>'.dol_escape_htmltag($row['ref']).'</strong><span>'.dol_escape_htmltag($langs->trans($statusKey)).'</span><time>'.dol_escape_htmltag($row['date_creation']).'</time></a>'; }
    if (empty($rows)) print '<p>'.$langs->trans('QuotationRequestsEmpty').'</p>';
    print '</div>';
} else {
    $row = $rows[0];
    $payload = json_decode($row['request_json'], true);
    $details = is_array($payload) && isset($payload['details']) && is_array($payload['details']) ? $payload['details'] : array();
    $backArrow = $pageDirection === 'rtl' ? '&rarr;' : '&larr;';
    print '<a class="cpcc-back-link" href="quotation_requests.php"><span aria-hidden="true">'.$backArrow.'</span> '.$langs->trans('BackToList').'</a><article class="cpcc-quote-detail"><h2>'.dol_escape_htmltag($row['ref']).'</h2><p>'.$langs->trans('QuotationSubmittedSnapshot').'</p><div class="cpcc-quote-lines">';
    foreach (($details['lines'] ?? array()) as $line) {
        if (!is_array($line)) continue;
        print '<section><h3>'.dol_escape_htmltag($line['name'] ?? $line['sku'] ?? $line['lineId'] ?? 'Product').'</h3><dl>';
        print '<dt>'.$langs->trans('Cartons').'</dt><dd>'.(int) ($line['cartons'] ?? 0).'</dd>';
        print '<dt>'.$langs->trans('CalculationReady').'</dt><dd>'.(!empty($line['calculationReady']) ? $langs->trans('Yes') : $langs->trans('No')).'</dd>';
        if (!empty($line['missingFields']) && is_array($line['missingFields'])) {
            $safeMissingLabels = array();
            foreach ($line['missingFields'] as $missingField) $safeMissingLabels[] = $langs->trans($missingFieldLabels[$missingField] ?? 'MissingFieldOther');
            print '<dt>'.$langs->trans('MissingFields').'</dt><dd>'.dol_escape_htmltag(implode(', ', $safeMissingLabels)).'</dd>';
        }
        $priceSnapshot = isset($line['price']) && is_array($line['price']) ? $line['price'] : array();
        $visiblePrice = '';
        if (($priceSnapshot['visibility'] ?? '') === 'public' && ($priceSnapshot['mode'] ?? '') === 'fixed' && isset($priceSnapshot['unitPrice'])) $visiblePrice = price($priceSnapshot['unitPrice']).' '.($priceSnapshot['currency'] ?? '').' / '.$langs->trans('PublicPiece');
        elseif (($priceSnapshot['visibility'] ?? '') === 'public' && ($priceSnapshot['mode'] ?? '') === 'range' && isset($priceSnapshot['minUnitPrice'], $priceSnapshot['maxUnitPrice'])) $visiblePrice = price($priceSnapshot['minUnitPrice']).' - '.price($priceSnapshot['maxUnitPrice']).' '.($priceSnapshot['currency'] ?? '').' / '.$langs->trans('PublicPiece');
        if ($visiblePrice !== '') print '<dt>'.$langs->trans('VisiblePriceSnapshot').'</dt><dd>'.dol_escape_htmltag($visiblePrice).'</dd>';
        print '</dl></section>';
    }
    print '</div>';
    $publications = new PublicRequestPublicationService($db);
    foreach ($publications->customerItems((int) $row['rowid'], $socid) as $item) {
        if ($item['item_type'] === 'reply') print '<div class="cpcc-public-reply">'.nl2br(dol_escape_htmltag($item['body_public'])).'</div>';
        else print '<a class="button" href="quotation_download.php?token='.rawurlencode($item['public_token']).'">'.dol_escape_htmltag($item['original_name']).'</a>';
    }
    if (!empty($row['proposal_customer_visible']) && !empty($row['fk_propal'])) {
        $proposal = new Propal($db);
        if ($proposal->fetch((int) $row['fk_propal']) > 0 && (int) $proposal->socid === $socid && (int) $proposal->entity === (int) $conf->entity) {
            $proposalMoney = cpcc_portal_proposal_money($proposal, $proposal->total_ht, $proposal->multicurrency_total_ht);
            $proposalCurrency = $proposalMoney['currency'];
            $proposalStatusLabels = array(Propal::STATUS_DRAFT=>'ProposalStatusDraft',Propal::STATUS_VALIDATED=>'ProposalStatusValidated',Propal::STATUS_SIGNED=>'ProposalStatusAccepted',Propal::STATUS_NOTSIGNED=>'ProposalStatusRefused',Propal::STATUS_BILLED=>'ProposalStatusBilled');
            $proposalStatus = $langs->trans($proposalStatusLabels[(int)$proposal->status] ?? 'ProposalStatusAvailable');
            print '<section class="cpcc-proposal"><h3>'.$langs->trans('CommercialProposal').' '.dol_escape_htmltag($proposal->ref).'</h3><p><strong>'.dol_escape_htmltag($proposalStatus).'</strong></p><p>'.dol_escape_htmltag($proposal->note_public).'</p><table><thead><tr><th>'.$langs->trans('Description').'</th><th>'.$langs->trans('Qty').'</th><th>'.$langs->trans('Amount').'</th></tr></thead><tbody>';
            foreach ($proposal->lines as $proposalLine) { $lineMoney=cpcc_portal_proposal_money($proposal,$proposalLine->total_ht,$proposalLine->multicurrency_total_ht); print '<tr><td>'.dol_escape_htmltag($proposalLine->desc).'</td><td>'.price($proposalLine->qty).'</td><td>'.price($lineMoney['amount']).' '.dol_escape_htmltag($lineMoney['currency']).'</td></tr>'; }
            $vatMoney=cpcc_portal_proposal_money($proposal,$proposal->total_tva,$proposal->multicurrency_total_tva);$totalMoney=cpcc_portal_proposal_money($proposal,$proposal->total_ttc,$proposal->multicurrency_total_ttc);
            print '</tbody></table><dl class="cpcc-proposal-totals"><dt>'.$langs->trans('ProposalSubtotal').'</dt><dd>'.price($proposalMoney['amount']).' '.dol_escape_htmltag($proposalCurrency).'</dd><dt>'.$langs->trans('ProposalVAT').'</dt><dd>'.price($vatMoney['amount']).' '.dol_escape_htmltag($vatMoney['currency']).'</dd><dt>'.$langs->trans('ProposalTotal').'</dt><dd>'.price($totalMoney['amount']).' '.dol_escape_htmltag($totalMoney['currency']).'</dd></dl></section>';
        }
    }
    print '</article>';
}
print '</main>';
llxFooter();
