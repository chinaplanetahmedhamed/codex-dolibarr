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
m031_assert_contains($documents, 'cpcc_customer_documents($db, $socid, $projectid)', 'Unified customer-visible document query');
m031_assert_contains($documents, 'cpcc_portal_customer_gallery_items($db, $user)', 'Approved shipment publications join the unified results');
m031_assert_contains($documents, "'commercial_document'=>'Commercial / تجاري'", 'Commercial documents remain an explicit unified source');
m031_assert_contains($documents, 'المستندات المرتبطة / Linked Documents', 'Unified Documents page section');
m031_assert_contains($documents, "llxHeader('', 'Documents / المستندات'", 'Generic customer Documents browser title');
m031_assert_contains($documents, 'cpcc-document-type', 'Compact customer document type');
m031_assert_contains($documents, 'غير مرتبط بمشروع / No related project', 'Every document identifies its verified project context or absence');
m031_assert_contains($documents, '$canwrite = (!$is_external', 'External upload/write restriction');
m031_assert_contains($documents, 'if ($canpublishcore && $socid > 0)', 'Admin-only core publication form');
m031_assert_contains($documents, 'cpcc-documents-search', 'Responsive Documents search controls');

echo "M031 customer document count tests passed\n";
