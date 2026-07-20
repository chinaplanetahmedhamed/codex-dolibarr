<?php
$documentRoot = getenv('M024_DOL_DOCUMENT_ROOT');
if (is_string($documentRoot) && $documentRoot !== '') {
    define('CPCC_PORTAL_GALLERY_TESTING', true);
    putenv('CPCC_PORTAL_GALLERY_TEST_SECRET=m024-local-collaboration-test-secret-0123456789abcdef');
}
$root = dirname(__DIR__, 2);
$failures = array();
function m024Assert($condition, $message) { global $failures; if (!$condition) $failures[] = $message; }

$schema = file_get_contents($root.'/custom/chinaplanetlogistics/sql/llx_cpl_shipment_customer_event.sql');
$documentSchema = file_get_contents($root.'/custom/chinaplanetlogistics/sql/llx_cpl_shipment_public_document.sql');
$migration = file_get_contents($root.'/deploy/migrations/m024_shipment_customer_collaboration_schema.php');
$portal = file_get_contents($root.'/custom/chinaplanetcustomercenter/lib/portal.lib.php');
$card = file_get_contents($root.'/custom/chinaplanetlogistics/card.php');
$renderer = file_get_contents($root.'/custom/chinaplanetlogistics/lib/shipment_customer.lib.php');

m024Assert(strpos((string)$schema, 'llx_cpl_shipment_customer_event') !== false, 'append-only customer event table exists');
m024Assert(strpos((string)$documentSchema, 'response_status') !== false && strpos((string)$documentSchema, 'source_origin') !== false && strpos((string)$documentSchema, 'customer_upload_status') !== false, 'document registry stores origin, customer response, and customer-upload processing status');
m024Assert(strpos((string)$migration, "PHP_SAPI !== 'cli'") !== false && strpos((string)$migration, "array('check', 'apply')") !== false, 'migration is CLI-only and bounded');
m024Assert(strpos((string)$migration, 'POST_APPLY_VERIFY_FAILED') !== false, 'migration verifies its result');
m024Assert(!preg_match('/[\"\']\s*(DROP|TRUNCATE|DELETE)\b/i', (string)$migration), 'migration has no destructive SQL');
m024Assert(strpos((string)$portal, 'cpcc_portal_customer_document_response') !== false, 'customer response writer exists');
m024Assert(strpos((string)$portal, 'cpcc_portal_customer_container_received') !== false, 'container receipt writer exists');
m024Assert(strpos((string)$portal, 'cpcc_portal_staff_customer_upload_status') !== false, 'staff customer-upload status writer exists');
m024Assert(strpos((string)$portal, 'cpcc_portal_archive_shipment_file') !== false, 'safe shipment-file archive writer exists');
m024Assert(strpos((string)$portal, 'class="cpl-file-archive"') !== false && strpos((string)$portal, 'archive_shipment_file') !== false, 'staff file table exposes the explicit safe archive control');
m024Assert(strpos((string)$portal, 'CPLCustomerUploadedFilesTitle') !== false && strpos((string)$portal, 'CPLCustomerUploadStatusWaitingReceive') !== false, 'customer upload section and processing status are rendered');
m024Assert(strpos((string)$card, 'customer_document_confirm') !== false && strpos((string)$card, 'customer_document_amend') !== false, 'customer document actions are routed');
m024Assert(strpos((string)$card, 'customer_container_received') !== false, 'customer container receipt is routed');
m024Assert(strpos((string)$renderer, 'CPLRequiredAction') === false, 'legacy Required Action section is absent');

if (is_string($documentRoot) && $documentRoot !== '') {
    define('NOLOGIN', 1);
    define('NOREQUIREMENU', 1);
    define('NOREQUIREHTML', 1);
    define('NOREQUIREAJAX', 1);
    define('NOCSRFCHECK', 1);
    $_SERVER['DOCUMENT_ROOT'] = rtrim($documentRoot, '/');
    $_SERVER['SERVER_NAME'] = 'localhost';
    $_SERVER['SERVER_PORT'] = '80';
    require rtrim($documentRoot, '/').'/master.inc.php';
    require_once $root.'/custom/chinaplanetcustomercenter/lib/portal.lib.php';
    global $db, $conf;

    m024Assert(cpcc_portal_collaboration_schema_ready($db), 'local collaboration schema is installed');
    $users = array();
    foreach (array('A'=>'M017_SYNTH_E1_A','B'=>'M017_SYNTH_E1_B') as $key=>$refExt) {
        $result = $db->query("SELECT rowid,entity,fk_soc FROM ".MAIN_DB_PREFIX."user WHERE ref_ext='".$db->escape($refExt)."' LIMIT 1");
        $row = $result ? $db->fetch_object($result) : null;
        m024Assert($row !== null, 'synthetic customer context exists: '.$key);
        if ($row) $users[$key] = $row;
    }

    if (count($users) === 2) {
        $originalEntity = isset($conf->entity) ? $conf->entity : 1;
        $conf->entity = (int)$users['A']->entity;
        $result = $db->query("SELECT rowid,ref,entity,fk_soc,fk_statut FROM ".MAIN_DB_PREFIX."chinaplanetlogistics WHERE entity=".(int)$users['A']->entity." AND fk_soc=".(int)$users['A']->fk_soc." ORDER BY rowid LIMIT 1");
        $row = $result ? $db->fetch_object($result) : null;
        m024Assert($row !== null, 'synthetic owned shipment exists');
        if ($row) {
            $shipment = (object)array('id'=>(int)$row->rowid,'rowid'=>(int)$row->rowid,'ref'=>(string)$row->ref,'entity'=>(int)$row->entity,'fk_soc'=>(int)$row->fk_soc,'fk_statut'=>(int)$row->fk_statut);
            $staff = (object)array('id'=>(int)$users['A']->rowid);
            $customerA = (object)array('id'=>(int)$users['A']->rowid,'socid'=>(int)$users['A']->fk_soc,'admin'=>0);
            $customerB = (object)array('id'=>(int)$users['B']->rowid,'socid'=>(int)$users['B']->fk_soc,'admin'=>0);
            $maxEventResult = $db->query("SELECT COALESCE(MAX(rowid),0) AS maxid FROM ".MAIN_DB_PREFIX."cpl_shipment_customer_event");
            $maxEventRow = $maxEventResult ? $db->fetch_object($maxEventResult) : null;
            $baselineEventId = $maxEventRow ? (int)$maxEventRow->maxid : 0;
            $publicationIds = array();
            $files = array();
            $docRoot = cpcc_portal_shipment_doc_root($shipment->id);
            if (!is_dir($docRoot)) dol_mkdir($docRoot);

            $staffFile = 'M024-STAFF-DOCUMENT.pdf';
            $staffPath = $docRoot.'/'.$staffFile;
            file_put_contents($staffPath, "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\n%%EOF\n");
            @chmod($staffPath, 0640); $files[] = $staffPath;
            $published = cpcc_portal_gallery_publish($db, $shipment, $staff, 'ship-'.$shipment->id.'/'.$staffFile);
            m024Assert(!empty($published['ok']), 'staff shipment document can be published');
            if (!empty($published['id'])) $publicationIds[] = (int)$published['id'];
            $items = cpcc_portal_customer_gallery_items($db, $customerA, $shipment);
            $document = null;
            foreach ($items as $item) if ((string)$item->original_name === $staffFile) $document = $item;
            m024Assert($document !== null && (string)$document->response_status === 'awaiting_confirmation', 'published staff document awaits customer confirmation');
            if ($document) {
                $crossCustomer = cpcc_portal_customer_document_response($db, $customerB, $shipment, $document->public_token, 'confirmed');
                m024Assert(empty($crossCustomer['ok']), 'another customer cannot respond to the document');
                $confirmed = cpcc_portal_customer_document_response($db, $customerA, $shipment, $document->public_token, 'confirmed');
                m024Assert(!empty($confirmed['ok']), 'owning customer can confirm the document');
                $missingNote = cpcc_portal_customer_document_response($db, $customerA, $shipment, $document->public_token, 'amendment_requested', '');
                m024Assert(empty($missingNote['ok']), 'amendment request requires a note');
                $amended = cpcc_portal_customer_document_response($db, $customerA, $shipment, $document->public_token, 'amendment_requested', 'Please correct consignee name.');
                m024Assert(!empty($amended['ok']), 'owning customer can request an amendment with a note');
                $commented = cpcc_portal_customer_document_response($db, $customerA, $shipment, $document->public_token, 'comment', 'Customer review note.');
                m024Assert(!empty($commented['ok']), 'owning customer can add a document comment');
            }

            $customerFile = 'M024-CUSTOMER-UPLOAD.pdf';
            $customerPath = $docRoot.'/'.$customerFile;
            file_put_contents($customerPath, "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\n% customer\n%%EOF\n");
            @chmod($customerPath, 0640); $files[] = $customerPath;
            $customerUpload = cpcc_portal_publish_customer_upload($db, $shipment, $customerA, 'ship-'.$shipment->id.'/'.$customerFile, 'Supporting customer file.');
            m024Assert(!empty($customerUpload['ok']), 'customer upload is published and recorded');
            if (!empty($customerUpload['id'])) $publicationIds[] = (int)$customerUpload['id'];
            $customerItems = cpcc_portal_customer_gallery_items($db, $customerA, $shipment);
            $customerDocument = null;
            foreach ($customerItems as $item) if ((string)$item->original_name === $customerFile) $customerDocument = $item;
            m024Assert($customerDocument !== null && (string)$customerDocument->source_origin === 'customer' && (string)$customerDocument->customer_upload_status === 'waiting_receive', 'new customer upload starts waiting to be received');
            if ($customerDocument) {
                $customerResponse = cpcc_portal_customer_document_response($db, $customerA, $shipment, $customerDocument->public_token, 'confirmed');
                m024Assert(empty($customerResponse['ok']), 'customer-origin file cannot be confirmed as a staff document');
            }
            foreach (array('received','handling','done') as $uploadStatus) {
                $statusResult = cpcc_portal_staff_customer_upload_status($db, $shipment, $staff, (int)$customerUpload['id'], $uploadStatus, 'M024-C01 '.$uploadStatus);
                m024Assert(!empty($statusResult['ok']), 'customer upload status advances to '.$uploadStatus);
            }
            $backwardUpload = cpcc_portal_staff_customer_upload_status($db, $shipment, $staff, (int)$customerUpload['id'], 'received', 'invalid regression');
            m024Assert(empty($backwardUpload['ok']), 'customer upload status cannot move backwards');
            $forgedUpload = cpcc_portal_staff_customer_upload_status($db, $shipment, $staff, 2147483647, 'received');
            m024Assert(empty($forgedUpload['ok']), 'forged customer upload publication is rejected');

            $customerImage = 'M024-CUSTOMER-UPLOAD.png';
            $customerImagePath = $docRoot.'/'.$customerImage;
            file_put_contents($customerImagePath, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='));
            @chmod($customerImagePath, 0640); $files[] = $customerImagePath;
            $customerImageUpload = cpcc_portal_publish_customer_upload($db, $shipment, $customerA, 'ship-'.$shipment->id.'/'.$customerImage, 'Customer photo evidence.');
            m024Assert(!empty($customerImageUpload['ok']), 'customer image upload is published and recorded');
            if (!empty($customerImageUpload['id'])) $publicationIds[] = (int)$customerImageUpload['id'];
            $groups = cpcc_portal_customer_shipment_file_groups(cpcc_portal_customer_gallery_items($db, $customerA, $shipment));
            $customerUploadNames = array_map(function ($item) { return (string)$item->original_name; }, $groups['customer_uploads']);
            $galleryNames = array_map(function ($item) { return (string)$item->original_name; }, $groups['gallery']);
            m024Assert(in_array($customerImage, $customerUploadNames, true) && !in_array($customerImage, $galleryNames, true), 'customer-uploaded image is a customer file and never gallery media');

            $archiveFile = 'M024-ARCHIVE-ME.pdf';
            $archivePath = $docRoot.'/'.$archiveFile;
            file_put_contents($archivePath, "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\n% archive\n%%EOF\n");
            @chmod($archivePath, 0640); $files[] = $archivePath;
            $archivePublication = cpcc_portal_gallery_publish($db, $shipment, $staff, 'ship-'.$shipment->id.'/'.$archiveFile);
            m024Assert(!empty($archivePublication['ok']), 'archive candidate can be published first');
            if (!empty($archivePublication['id'])) $publicationIds[] = (int)$archivePublication['id'];
            $archiveToken = '';
            foreach (cpcc_portal_customer_gallery_items($db, $customerA, $shipment) as $item) if ((string)$item->original_name === $archiveFile) $archiveToken = (string)$item->public_token;
            $archiveResult = cpcc_portal_archive_shipment_file($db, $shipment, $staff, 'ship-'.$shipment->id.'/'.$archiveFile);
            m024Assert(!empty($archiveResult['ok']) && !is_file($archivePath), 'safe removal moves active bytes out of the shipment root');
            if (!empty($archiveResult['archive_key'])) {
                $archivedFullPath = DOL_DATA_ROOT.'/chinaplanetlogistics/'.(string)$archiveResult['archive_key'];
                m024Assert(is_file($archivedFullPath), 'safe removal preserves recoverable bytes in the private archive');
                $files[] = $archivedFullPath;
            }
            if ($archiveToken !== '') m024Assert(cpcc_portal_resolve_customer_gallery_content($db, $customerA, $archiveToken) === null, 'archived publication is blocked from customer download');
            $archiveEventResult = $db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."cpl_shipment_customer_event WHERE entity=".(int)$shipment->entity." AND fk_shipment=".(int)$shipment->id." AND fk_soc=".(int)$shipment->fk_soc." AND event_type='shipment_file_archived' AND customer_visible=0 AND rowid>".$baselineEventId." LIMIT 1");
            m024Assert($archiveEventResult && $db->num_rows($archiveEventResult) === 1, 'safe removal records a private append-only archive event');

            $unavailableReceipt = cpcc_portal_customer_container_received($db, $customerA, $shipment);
            m024Assert(empty($unavailableReceipt['ok']) || in_array($shipment->fk_statut, array(6,7), true), 'container receipt is unavailable before ERP delivery');
            $db->query("UPDATE ".MAIN_DB_PREFIX."chinaplanetlogistics SET fk_statut=6 WHERE rowid=".(int)$shipment->id." AND entity=".(int)$shipment->entity." AND fk_soc=".(int)$shipment->fk_soc);
            $shipment->fk_statut = 6;
            $receipt = cpcc_portal_customer_container_received($db, $customerA, $shipment, 'Container received in good condition.');
            m024Assert(!empty($receipt['ok']), 'customer can acknowledge a delivered container');
            $receiptAgain = cpcc_portal_customer_container_received($db, $customerA, $shipment);
            m024Assert(!empty($receiptAgain['ok']), 'container receipt is idempotent');

            foreach (array('preparing','finished','government_uploaded','sent_dhl','in_transit','delivered') as $status) {
                $statusResult = cpcc_portal_staff_original_document_status($db, $staff, $shipment, $status, 'M024 test '.$status);
                m024Assert(!empty($statusResult['ok']), 'original document status advances to '.$status);
            }
            $backwards = cpcc_portal_staff_original_document_status($db, $staff, $shipment, 'preparing', 'invalid regression');
            m024Assert(empty($backwards['ok']), 'original document timeline cannot move backwards');
            $wrongCustomerReceipt = cpcc_portal_customer_original_documents_received($db, $customerB, $shipment);
            m024Assert(empty($wrongCustomerReceipt['ok']), 'another customer cannot acknowledge original documents');
            $originalReceipt = cpcc_portal_customer_original_documents_received($db, $customerA, $shipment, 'Originals received.');
            m024Assert(!empty($originalReceipt['ok']), 'owning customer can acknowledge delivered original documents');

            $events = cpcc_portal_shipment_collaboration_events($db, $shipment);
            $eventTypes = array(); foreach ($events as $event) if ((int)$event->rowid > $baselineEventId) $eventTypes[] = (string)$event->event_type;
            foreach (array('document_confirmed','document_amendment_requested','document_comment','customer_upload','customer_upload_status','container_received','original_document_status','original_documents_received') as $eventType) m024Assert(in_array($eventType, $eventTypes, true), 'append-only history records '.$eventType);

            $db->query("UPDATE ".MAIN_DB_PREFIX."chinaplanetlogistics SET fk_statut=".(int)$row->fk_statut." WHERE rowid=".(int)$shipment->id." AND entity=".(int)$shipment->entity." AND fk_soc=".(int)$shipment->fk_soc);
            $db->query("DELETE FROM ".MAIN_DB_PREFIX."cpl_shipment_customer_event WHERE rowid>".$baselineEventId." AND entity=".(int)$shipment->entity." AND fk_shipment=".(int)$shipment->id." AND fk_soc=".(int)$shipment->fk_soc);
            if ($publicationIds) $db->query("DELETE FROM ".MAIN_DB_PREFIX."cpl_shipment_public_document WHERE rowid IN (".implode(',', array_map('intval', $publicationIds)).") AND entity=".(int)$shipment->entity." AND fk_shipment=".(int)$shipment->id);
            foreach ($files as $path) @unlink($path);
        }
        $conf->entity = $originalEntity;
    }
}

if ($failures) { fwrite(STDERR, "M024_TEST=FAIL\n- ".implode("\n- ", $failures)."\n"); exit(1); }
echo 'M024_TEST=PASS'.($documentRoot ? ' DB_INTEGRATION=PASS' : '')."\n";
