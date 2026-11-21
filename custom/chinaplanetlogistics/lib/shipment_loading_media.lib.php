<?php
require_once __DIR__.'/../class/ShipmentDocumentService.class.php';

function cpl_loading_media_max_bytes()
{
    $phpLimits = getMaxFileSizeArray();
    $limit = (int) $phpLimits['maxphptoshow'] * 1024;
    if ($limit <= 0) return cpcc_portal_staff_upload_max_bytes();
    if ($phpLimits['maxphptoshowparam'] === 'post_max_size') $limit = max(1, $limit - 65536);
    return min($limit, cpcc_portal_staff_upload_max_bytes());
}

function cpl_store_loading_media($db, $shipment, $user, array $file, $publish)
{
    global $langs;

    $name = $file['name'] ?? '';
    $extension = is_string($name) ? strtolower(pathinfo($name, PATHINFO_EXTENSION)) : '';
    if (!in_array($extension, cpcc_portal_shipment_media_extensions(), true)
        || !is_string($file['tmp_name'] ?? null) || !is_int($file['size'] ?? null) || !is_int($file['error'] ?? null)) {
        return array('ok'=>false, 'message'=>$langs->trans('CPLLoadingMediaTypes'));
    }

    $limit = cpl_loading_media_max_bytes();
    $sharingAvailable = !empty($shipment->customer_visible) && cpcc_portal_gallery_token_secret() !== '';
    if (ShipmentDocumentService::enabled()) {
        $service = new ShipmentDocumentService($db, $user);
        try {
            $stored = $service->upload($shipment->id, $file, 0, 0, $limit);
        } catch (ShipmentDocumentRecoveryException $error) {
            error_log('Loading media reconciliation: '.json_encode($error->recoveryInfo()));
            return array('ok'=>false, 'uncertain'=>true, 'message'=>$langs->trans('CPLLoadingMediaOutcomeUnknown'));
        } catch (RuntimeException | LengthException $error) {
            return array('ok'=>false, 'message'=>$langs->trans('CPLLoadingMediaRejected'));
        }
        if (!$publish) return array('ok'=>true, 'message'=>$langs->trans('CPLLoadingMediaPrivate'));
        try {
            $publication = $sharingAvailable ? $service->publishDocument($stored['id'], $stored['revision']) : array();
        } catch (RuntimeException $error) {
            $publication = array();
        }
    } else {
        $directory = cpcc_portal_shipment_doc_root($shipment->id);
        $stored = cpcc_portal_store_uploaded_shipment_file($shipment->id, $directory, $name, $file['tmp_name'], $file['size'], $file['error'], $limit);
        if (empty($stored['ok'])) return array('ok'=>false, 'message'=>$stored['error']);
        if (!$publish) return array('ok'=>true, 'message'=>$langs->trans('CPLLoadingMediaPrivate'));
        $publication = $sharingAvailable ? cpcc_portal_gallery_publish($db, $shipment, $user, basename($directory).'/'.$stored['file']) : array();
    }

    if (empty($publication['ok'])) {
        return array('ok'=>true, 'warning'=>true, 'message'=>$langs->trans('CPLLoadingMediaPublishFailed'));
    }
    return array('ok'=>true, 'message'=>$langs->trans('CPLGalleryPublished'));
}

function cpl_loading_media_form($db, $shipment)
{
    global $langs;
    $shipmentId = (int) ($shipment->id ?? $shipment->rowid ?? 0);
    if ($shipmentId <= 0) return '';

    $t = static function ($key) use ($langs) { return dol_escape_htmltag($langs->trans($key)); };
    $uploadUrl = dol_buildpath('/chinaplanetlogistics/card.php', 1).'?id='.$shipmentId.'&action=upload_loading_media';
    $viewUrl = dol_buildpath('/chinaplanetlogistics/card.php', 1).'?id='.$shipmentId.'#shipment-files-manager';
    $limit = cpl_loading_media_max_bytes();
    $extensions = '.'.implode(',.', cpcc_portal_shipment_media_extensions());
    $html = '<section class="cpl-loading-media"><h3>'.$t('CPLLoadingMediaTitle').'</h3>';
    $html .= '<p id="cpl-loading-media-limit">'.dol_escape_htmltag($langs->trans('CPLLoadingMediaLimit', round($limit / 1048576, 2).' MiB')).'</p>';
    $html .= '<form class="cpl-file-upload-form" data-cpl-loading-media hidden method="post" enctype="multipart/form-data" action="'.dol_escape_htmltag($uploadUrl).'" data-limit="'.$limit.'" data-size-error="'.$t('CPLLoadingMediaSizeError').'" data-network-error="'.$t('CPLLoadingMediaNetworkError').'" data-server-limit="'.$t('CPLLoadingMediaServerLimit').'" data-complete="'.$t('CPLLoadingMediaComplete').'">';
    $html .= '<input type="hidden" name="token" value="'.dol_escape_htmltag(newToken()).'">';
    $html .= '<label for="cpl-loading-media-files">'.$t('CPLLoadingMediaChoose').'</label><input type="file" id="cpl-loading-media-files" name="loading_media" accept="'.$extensions.'" aria-describedby="cpl-loading-media-limit" multiple required>';
    if (!empty($shipment->customer_visible) && cpcc_portal_gallery_token_secret() !== '' && cpcc_portal_gallery_schema_ready($db)) {
        $html .= '<label><input type="checkbox" name="publish_media" value="1"> '.$t('CPLLoadingMediaShare').'</label>';
    } else {
        $html .= '<span class="opacitymedium">'.$t('CPLLoadingMediaPrivateOnly').'</span>';
    }
    $html .= '<button type="submit" class="button button-save">'.$t('CPLLoadingMediaUpload').'</button>';
    $html .= '<progress max="100" aria-label="'.$t('CPLLoadingMediaProgress').'" hidden></progress>';
    $html .= '<span role="status"></span><a class="button" hidden href="'.dol_escape_htmltag($viewUrl).'">'.$t('CPLLoadingMediaRefresh').'</a></form>';
    $html .= '<ul data-cpl-loading-media-results aria-live="polite"></ul>';
    $html .= '<script defer src="'.dol_escape_htmltag(dol_buildpath('/chinaplanetlogistics/js/shipment-loading-media.js', 1)).'?v=m131"></script></section>';
    return $html;
}
