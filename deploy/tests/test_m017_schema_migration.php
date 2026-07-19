<?php
$root = dirname(__DIR__, 2);
$files = array(
	'custom/chinaplanetcustomercenter/sql/llx_cpcc_mobile_token_family.sql',
	'custom/chinaplanetcustomercenter/sql/llx_cpcc_mobile_access_token.sql',
	'custom/chinaplanetcustomercenter/sql/llx_cpcc_mobile_refresh_token.sql',
	'custom/chinaplanetcustomercenter/sql/llx_cpcc_mobile_public_id.sql',
	'custom/chinaplanetcustomercenter/sql/llx_cpcc_mobile_rate_limit.sql',
);
$failures = array();
function m017_schema_assert($condition, $message) { global $failures; if (!$condition) $failures[] = $message; }
foreach ($files as $relative) {
	$sql = file_get_contents($root.'/'.$relative);
	m017_schema_assert($sql !== false, 'Readable schema: '.$relative);
	m017_schema_assert((bool) preg_match('/^CREATE TABLE IF NOT EXISTS llx_cpcc_mobile_/m', (string) $sql), 'Additive table creation: '.$relative);
	m017_schema_assert(!preg_match('/^\s*(DROP|TRUNCATE|DELETE|UPDATE|ALTER)\b/im', (string) $sql), 'No destructive SQL statements: '.$relative);
	m017_schema_assert(strpos((string) $sql, 'ENGINE=innodb') !== false, 'InnoDB table: '.$relative);
}
$migration = file_get_contents($root.'/deploy/migrations/m017_customer_mobile_api_schema.php');
m017_schema_assert(strpos((string) $migration, "PHP_SAPI !== 'cli'") !== false, 'Migration is CLI-only');
m017_schema_assert(strpos((string) $migration, "array('check', 'apply')") !== false, 'Migration exposes only check/apply');
m017_schema_assert(strpos((string) $migration, 'DROP|TRUNCATE|DELETE|UPDATE|ALTER') !== false && strpos((string) $migration, 'SQL_SOURCE_FORBIDDEN') !== false, 'Migration guards destructive SQL statements');
if ($failures) { fwrite(STDERR, "M017_SCHEMA=FAIL\n- ".implode("\n- ", $failures)."\n"); exit(1); }
echo "M017_SCHEMA=PASS TABLES=".count($files)."\n";
