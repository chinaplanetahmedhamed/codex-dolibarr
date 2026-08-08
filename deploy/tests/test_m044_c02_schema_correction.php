<?php
/** Source contract for the staging-only M044-C02 schema correction. */

$root = dirname(__DIR__, 2);
$migration = file_get_contents($root.'/deploy/migrations/m041_shipping_note_translation_schema.php');
$schema = file_get_contents($root.'/custom/chinaplanetshippingprices/sql/llx_chinaplanetsp_rate.sql');
if ($migration === false || $schema === false) {
    throw new RuntimeException('M044-C02 schema sources are unreadable.');
}

$assert = static function ($condition, $message) {
    if (!$condition) throw new RuntimeException('M044-C02: '.$message);
};

$columns = array(
    'source_notes_raw',
    'source_notes',
    'notes_en',
    'translation_status',
    'translation_source_hash',
    'translation_provider',
    'translation_model',
    'translation_error',
    'fk_user_translation_review',
    'date_translation_review',
);
foreach ($columns as $column) {
    $assert(strpos($migration, "'".$column."'") !== false, 'migration column absent: '.$column);
    $assert(strpos($schema, $column) !== false, 'fresh-install column absent: '.$column);
}

$assert(strpos($migration, "array('check','snapshot','apply','rollback','verify-prestate')") !== false, 'guarded command allowlist absent');
$assert(strpos($migration, "'idx_chinaplanetsp_rate_translation'") !== false, 'translation index absent');
$assert(strpos($migration, "array('entity','fk_batch','translation_status')") !== false, 'translation index order absent');
$assert(strpos($migration, 'RATE_TABLE_ABSENT') !== false, 'rate-table guard absent');
$assert(strpos($migration, 'M041_SCHEMA_PRESTATE_B64') !== false, 'prestate contract absent');
$assert(strpos($migration, 'ROLLBACK_DATA_PRESENT') !== false, 'data-safe rollback guard absent');
$assert(strpos($migration, 'POST_APPLY_METADATA_INVALID') !== false, 'exact metadata verification absent');

echo "M044_C02_SCHEMA_SOURCE=PASS COLUMNS=10 INDEX=1 PRESTATE=YES ROLLBACK_GUARD=YES\n";
