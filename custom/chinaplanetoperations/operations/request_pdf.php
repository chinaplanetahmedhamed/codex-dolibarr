<?php
require $_SERVER['DOCUMENT_ROOT'].'/main.inc.php';
require_once __DIR__.'/../lib/main.lib.php';
require_once __DIR__.'/../class/PublicRequestPdfService.class.php';
cpo_require($user,'publicrequest_read');
$id=GETPOSTINT('id');
$service=new PublicRequestPdfService($db);
try {
    $record=$service->staffRecord($id);
    if (GETPOST('download','alpha')==='yes') {
        $file=$service->staffPdf($id);
        header('Content-Type: application/pdf');header('X-Content-Type-Options: nosniff');
        header('Content-Security-Policy: sandbox');header('Cache-Control: private, no-store');
        header('Content-Disposition: attachment; filename="China-Planet-Order-'.$file['reference'].'.pdf"');
        header('Content-Length: '.strlen($file['bytes']));echo $file['bytes'];exit;
    }
} catch(PublicWebsiteRequestException $e) { http_response_code($e->getHttpStatus());print dol_escape_htmltag($e->getPublicCode());exit; }
$langs->load('chinaplanetoperations@chinaplanetoperations');
llxHeader('',$langs->trans('PublicCataloguePdf'));
print '<h1>'.dol_escape_htmltag($record['request']['ref']).'</h1>';
print '<p><a href="public_requests.php?id='.(int)$id.'">'.$langs->trans('PublicPdfBackToEnquiry').'</a></p>';
$meta=$record['metadata'];
if (!$meta) print '<p>'.$langs->trans('PublicPdfNotAvailable').'</p>';
else {
    print '<div class="fichecenter"><table class="border centpercent" style="table-layout:fixed;overflow-wrap:anywhere">';
    foreach(array('PublicPdfCreated'=>$meta['created_at'],'Status'=>$meta['status'],'PublicPdfDownloadRequests'=>$meta['download_requests'],'PublicPdfLastRequest'=>$meta['last_download_requested_at'] ?: '—','PublicPdfProspectMatch'=>$meta['prospect_match'],'SHA-256'=>$meta['sha256'] ?: '—') as $label=>$value) print '<tr><td>'.dol_escape_htmltag($langs->trans($label)).'</td><td>'.dol_escape_htmltag((string)$value).'</td></tr>';
    print '</table><p>'.$langs->trans('PublicPdfCustomerCopyHelp').'</p>';
    if ($meta['status']==='archived') print '<a class="butAction" href="?id='.(int)$id.'&amp;download=yes">'.$langs->trans('PublicPdfDownloadCopy').'</a>';
    $linkedSoc=(int)($record['request']['fk_soc'] ?: ($meta['suggested_socid'] ?? 0));
    if ($linkedSoc) print '<p><a href="'.DOL_URL_ROOT.'/societe/card.php?socid='.$linkedSoc.'">'.$langs->trans('PublicPdfOpenProspect').'</a></p>';
    print '</div>';
}
llxFooter();$db->close();
