<?php

$root = getenv('M056_DOL_DOCUMENT_ROOT') ?: dirname(__DIR__, 2);
$portal = file_get_contents($root.'/custom/chinaplanetcustomercenter/lib/portal.lib.php');
$card = file_get_contents($root.'/custom/chinaplanetlogistics/card.php');

$checks = array(
    'staff 200MB allowance is defined'=>strpos($portal, 'function cpcc_portal_staff_upload_max_bytes()') !== false
        && strpos($portal, 'return 200 * 1024 * 1024;') !== false,
    'storage accepts an explicit route limit'=>strpos($portal, 'function cpcc_portal_store_uploaded_shipment_file($shipid, $upload_dir, $original_name, $tmp_name, $file_size = 0, $upload_error = UPLOAD_ERR_OK, $maxBytes = 0)') !== false,
    'upload validation receives the explicit limit'=>strpos($portal, 'cpcc_portal_validate_shipment_upload($original_name, $tmp_name, $file_size, $maxBytes)') !== false,
    'staff route passes the staff allowance'=>strpos($card, "cpcc_portal_store_uploaded_shipment_file(\$object->id, \$upload_dir, \$upload['name'] ?? '', \$upload['tmp_name'] ?? '', \$upload['size'] ?? 0, \$upload['error'] ?? UPLOAD_ERR_NO_FILE, cpcc_portal_staff_upload_max_bytes())") !== false,
);

$failed = array();
foreach ($checks as $label=>$ok) {
    echo ($ok ? 'PASS ' : 'FAIL ').$label."\n";
    if (!$ok) $failed[] = $label;
}
if ($failed) {
    fwrite(STDERR, 'M056 shipment upload dependency verification failed: '.implode(', ', $failed)."\n");
    exit(1);
}
echo "M056_SHIPMENT_UPLOAD_DEPENDENCY=PASS\n";
