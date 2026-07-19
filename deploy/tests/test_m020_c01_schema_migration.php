<?php
$root = dirname(__DIR__, 2);
$failures = array();

function m020C01SchemaAssert($condition, $message)
{
	global $failures;
	if (!$condition) $failures[] = $message;
}

$schemaPath = $root.'/custom/chinaplanetlogistics/sql/llx_cpl_shipment_public_document.sql';
$schema = file_get_contents($schemaPath);
m020C01SchemaAssert($schema !== false, 'Shipment Gallery schema is readable');
m020C01SchemaAssert((bool) preg_match('/^CREATE TABLE IF NOT EXISTS llx_cpl_shipment_public_document/m', (string) $schema), 'Schema creates only the extension-owned publication table');
m020C01SchemaAssert(!preg_match('/^\s*(DROP|TRUNCATE|DELETE|UPDATE|ALTER)\b/im', (string) $schema), 'Schema contains no destructive SQL');
m020C01SchemaAssert(strpos((string) $schema, 'UNIQUE INDEX uk_cpl_gallery_file') !== false, 'Schema prevents duplicate publication rows for one stored shipment file');
m020C01SchemaAssert(strpos((string) $schema, 'INDEX idx_cpl_gallery_owner') !== false, 'Schema indexes entity/customer/shipment visibility checks');
m020C01SchemaAssert(strpos((string) $schema, 'file_sha256 char(64) NOT NULL') !== false, 'Schema stores an exact SHA-256 snapshot');
m020C01SchemaAssert(strpos((string) $schema, 'ENGINE=innodb') !== false, 'Schema uses InnoDB transactions');

$migration = file_get_contents($root.'/deploy/migrations/m020_c01_shipment_public_document_schema.php');
m020C01SchemaAssert($migration !== false, 'Migration is readable');
m020C01SchemaAssert(strpos((string) $migration, "PHP_SAPI !== 'cli'") !== false, 'Migration is CLI-only');
m020C01SchemaAssert(strpos((string) $migration, "array('check', 'apply')") !== false, 'Migration exposes only check/apply');
m020C01SchemaAssert(substr_count((string) $migration, 'CREATE TABLE IF NOT EXISTS') === 1, 'Migration creates one additive table');
m020C01SchemaAssert(!preg_match('/[\"\']\s*(DROP|TRUNCATE|DELETE|UPDATE|ALTER)\b/i', (string) $migration), 'Migration contains no destructive SQL command');
m020C01SchemaAssert(strpos((string) $migration, 'POST_APPLY_VERIFY_FAILED') !== false, 'Migration verifies the installed schema');

if ($failures) {
	fwrite(STDERR, "M020_C01_SCHEMA=FAIL\n- ".implode("\n- ", $failures)."\n");
	exit(1);
}
echo "M020_C01_SCHEMA=PASS TABLES=1\n";
