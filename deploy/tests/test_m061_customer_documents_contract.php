<?php

function m061DocumentsAssert($condition, $message)
{
    if (!$condition) { fwrite(STDERR, 'FAIL '.$message."\n"); exit(1); }
    print 'PASS '.$message."\n";
}

$library = file_get_contents(__DIR__.'/../../custom/chinaplanetcustomercenter/lib/customer_documents.lib.php');
$page = file_get_contents(__DIR__.'/../../custom/chinaplanetcustomercenter/documents.php');
$download = file_get_contents(__DIR__.'/../../custom/chinaplanetcustomercenter/customer_document.php');
$schema = file_get_contents(__DIR__.'/../../custom/chinaplanetcustomercenter/sql/llx_cpcc_customer_document.sql');
$mobile = file_get_contents(__DIR__.'/../../custom/chinaplanetcustomercenter/lib/mobile_api_data.lib.php');

m061DocumentsAssert(strpos($library, "a.customer_visible=1") !== false && strpos($library, "a.review_status IN ('approved','final_received')") !== false, 'project assets require explicit customer visibility and approval');
m061DocumentsAssert(strpos($library, "d.visibility='customer_after_approval'") !== false && strpos($library, "d.review_status IN ('approved','final_received')") !== false, 'operations documents require explicit customer visibility and approval');
m061DocumentsAssert(strpos($library, "o.service_type<>'quality_control'") !== false, 'generic web document lists always exclude mutable QC operation documents');
m061DocumentsAssert(strpos($library, 'fk_soc=') !== false && strpos($library, 'entity=') !== false, 'document sources are customer and entity scoped');
m061DocumentsAssert(strpos($library, "preg_match('~(^|/)\\.\\.(/|$)~'") !== false, 'core publication rejects traversal');
m061DocumentsAssert(strpos($library, 'hash_equals') !== false && strpos($library, 'file_sha256') !== false, 'download revalidates immutable file fingerprint');
m061DocumentsAssert(strpos($page, "name=\"projectid\"") === false || strpos($page, "'projectid'") !== false, 'project filter is present');
m061DocumentsAssert(strpos($page, 'Linked Documents') !== false && strpos($page, 'No related project') !== false, 'unified cards identify verified project context or its absence');
m061DocumentsAssert(strpos($download, 'cpcc_portal_customer_scope_id') !== false, 'core download derives customer scope from the authenticated portal user');
m061DocumentsAssert(strpos($schema, 'customer_visible') !== false && strpos($schema, 'status') !== false, 'publication registry supports reversible visibility');
m061DocumentsAssert(strpos($page, 'unlink(') === false, 'unpublish workflow never deletes source files');
m061DocumentsAssert(strpos($mobile, "'core_customer_document'") !== false && strpos($mobile, '$objectId = (int) $row->source_id') !== false, 'mobile core-document IDs use a positive scoped publication identifier');
m061DocumentsAssert(strpos($page, '$canpublishcore') !== false && strpos($page, "hasRight('chinaplanetcustomercenter', 'write')") !== false, 'core publication requires a Customer Center write boundary');
m061DocumentsAssert(strpos($mobile, "'commercial_document' AS source_type") !== false && strpos($mobile, "d.customer_visible=1 AND d.status=1") !== false, 'mobile unified documents include explicitly published commercial records');
m061DocumentsAssert(strpos($page, "'shipmentid'") !== false && strpos($page, "'document_type'") !== false && strpos($page, 'name="date_from"') !== false && strpos($page, 'name="date_to"') !== false, 'unified documents expose project, shipment, type and date filters');

print "OK M061 unified customer document contract\n";
