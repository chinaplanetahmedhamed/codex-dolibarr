<?php

$root = dirname(__DIR__, 2);
$migration = $root.'/deploy/migrations/m014_shipment_command_center_schema.php';

if (!is_file($migration)) {
	fwrite(STDERR, "M014 schema migration file is missing\n");
	exit(1);
}

$source = file_get_contents($migration);
$required = array(
	"PHP_SAPI !== 'cli'",
	"array('check', 'apply')",
	"SHOW TABLES LIKE",
	"SHOW COLUMNS FROM",
	"ADD COLUMN `date_atd` date NULL",
	"CREATE TABLE IF NOT EXISTS",
	"cpl_shipment_event",
	"cpl_document_requirement",
	"idx_cpl_event_owner",
	"uk_cpl_requirement_code",
	"M014_SCHEMA_APPLY=PASS",
);

foreach ($required as $needle) {
	if (strpos($source, $needle) === false) {
		fwrite(STDERR, 'Missing migration safeguard: '.$needle."\n");
		exit(1);
	}
}

$forbiddenPatterns = array(
	'/\bDROP\s+(TABLE|COLUMN|DATABASE)\b/i',
	'/\bDELETE\s+FROM\b/i',
	'/\bTRUNCATE\b/i',
	'/\bUPDATE\s+`?'.preg_quote('llx_chinaplanetlogistics', '/').'`?\b/i',
);
foreach ($forbiddenPatterns as $pattern) {
	if (preg_match($pattern, $source)) {
		fwrite(STDERR, 'Forbidden destructive SQL matched: '.$pattern."\n");
		exit(1);
	}
}

echo "M014 schema migration static checks passed\n";

