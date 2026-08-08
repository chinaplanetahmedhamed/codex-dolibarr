<?php

/** M044-C03 source/runtime contract for the staging note-review UI. */

$root = getenv('M044_C03_DOL_DOCUMENT_ROOT');
if ($root === false || $root === '') {
    $root = dirname(__DIR__, 2);
}
$root = rtrim($root, '/');

$batchPath = $root.'/custom/chinaplanetshippingprices/admin/batch.php';
$servicePath = $root.'/custom/chinaplanetshippingprices/class/ShippingNoteReviewService.class.php';
$cssPath = $root.'/custom/chinaplanetshippingprices/css/shippingprices.css';

foreach (array($batchPath, $servicePath, $cssPath) as $path) {
    if (!is_file($path) || is_link($path)) {
        throw new RuntimeException('M044-C03 required file is missing or unsafe: '.$path);
    }
}

$batch = file_get_contents($batchPath);
$service = file_get_contents($servicePath);
$css = file_get_contents($cssPath);
if ($batch === false || $service === false || $css === false) {
    throw new RuntimeException('M044-C03 source is unreadable.');
}

$batchContracts = array(
    "require_once __DIR__.'/../class/ShippingNoteReviewService.class.php';",
    "array('save_translation','approve_translation','reset_translation')",
    'CSRFCHECK_WITH_TOKEN',
    'pending_rows',
    'Carrier-note English translation',
    'name="source_row"',
    'name="notes_en"',
    'value="save_translation"',
    'if ((int)$batch->status !== 2 && $canPublish)',
);
foreach ($batchContracts as $contract) {
    if (strpos($batch, $contract) === false) {
        throw new RuntimeException('Batch review contract is absent: '.$contract);
    }
}

$serviceContracts = array(
    'class ShippingNoteReviewService',
    "array('save_translation','approve_translation','reset_translation')",
    'FOR UPDATE',
    'translation_status',
    'fk_user_translation_review',
    'listGroups',
);
foreach ($serviceContracts as $contract) {
    if (strpos($service, $contract) === false) {
        throw new RuntimeException('Review service contract is absent: '.$contract);
    }
}

$cssContracts = array(
    '.chinaplanetsp-translation-review',
    '.chinaplanetsp-translation-card',
    '.chinaplanetsp-translation-grid',
    '.chinaplanetsp-translation-actions',
    '@media(max-width:700px)',
);
foreach ($cssContracts as $contract) {
    if (strpos($css, $contract) === false) {
        throw new RuntimeException('Review CSS contract is absent: '.$contract);
    }
}

echo "M044_C03_NOTE_REVIEW_UI=PASS page=PASS service=PASS css=PASS publish_gate=PASS\n";
