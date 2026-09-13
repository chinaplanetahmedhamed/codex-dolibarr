<?php

function m031_source($relativePath)
{
    $path = __DIR__.'/../../'.$relativePath;
    $source = @file_get_contents($path);
    if ($source === false) {
        fwrite(STDERR, 'Unable to read '.$relativePath."\n");
        exit(1);
    }
    return $source;
}

function m031_assert_contains($source, $needle, $label)
{
    if (strpos($source, $needle) === false) {
        fwrite(STDERR, $label.' is missing'."\n");
        exit(1);
    }
}

$portal = m031_source('custom/chinaplanetcustomercenter/lib/portal.lib.php');
m031_assert_contains($portal, 'function cpcc_portal_customer_document_count', 'Shared customer document counter');
m031_assert_contains($portal, '$socid = $isExternal ? (int) $scope[\'socid\'] : (int) $requestedSocid', 'Authenticated external customer scope');
m031_assert_contains($portal, 'p.entity=', 'External publication entity scope');
m031_assert_contains($portal, 'p.fk_soc=', 'External publication customer scope');
m031_assert_contains($portal, 'p.status=1 AND p.customer_visible=1', 'External publication visibility scope');
m031_assert_contains($portal, 'cpcc_export_document', 'Published commercial document source');
m031_assert_contains($portal, 'AND status=1 AND customer_visible=1', 'Commercial document visibility scope');
m031_assert_contains($portal, '$shipmentId = !empty($shipment->id) ? (int) $shipment->id : (int) ($shipment->rowid ?? 0);', 'Shipment event ID fallback');
m031_assert_contains($portal, 'if ($shipmentId <= 0) return array();', 'Invalid shipment event guard');

$board = m031_source('custom/chinaplanetcustomercenter/board.php');
m031_assert_contains($board, "lib/export.lib.php", 'Commercial document helper loading');
m031_assert_contains($board, 'cpcc_portal_customer_document_count($db, $user, $socid)', 'Dashboard shared document count');

$documents = m031_source('custom/chinaplanetcustomercenter/documents.php');
m031_assert_contains($documents, '$socid > 0 ? cpcc_export_documents($db, $socid, 0, 0, $is_external)', 'Scope-aware commercial document query');
m031_assert_contains($documents, 'if ($socid > 0)', 'Commercial documents section for scoped customer');
m031_assert_contains($documents, 'المستندات التجارية / Commercial Documents', 'Combined Documents page section');
m031_assert_contains($documents, "llxHeader('', 'Documents / المستندات'", 'Generic customer Documents browser title');
m031_assert_contains($documents, 'cpcc-document-type', 'Compact customer document type');
m031_assert_contains($documents, "commercial_document->shipment_ref ?: 'غير مرتبط بشحنة / No related shipment'", 'Compact related-shipment field');
m031_assert_contains($documents, '$canwrite = (!$is_external', 'External upload/write restriction');
m031_assert_contains($documents, 'if ($canwrite && !$is_external)', 'Admin-only shipment upload form');
m031_assert_contains($documents, 'cpcc-documents-search', 'Responsive Documents search controls');

echo "M031 customer document count tests passed\n";
