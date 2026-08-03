<?php
/** Source-contract test for the guarded M041 schema migration. */
$root = dirname(__DIR__, 2);
$migration = file_get_contents($root.'/deploy/migrations/m041_shipping_note_translation_schema.php');
$schema = file_get_contents($root.'/custom/chinaplanetshippingprices/sql/llx_chinaplanetsp_rate.sql');
if ($migration === false || $schema === false) throw new RuntimeException('M041 schema sources are unreadable');
$assert = function ($condition, $message) { if(!$condition) throw new RuntimeException($message); };

foreach (array("'check'","'snapshot'","'apply'","'rollback'","'verify-prestate'") as $command) $assert(strpos($migration,$command)!==false,'migration command absent '.$command);
$assert(strpos($migration,'M041_SCHEMA_PRESTATE_B64')!==false,'prestate snapshot contract absent');
$assert(strpos($migration,'ROLLBACK_DATA_PRESENT')!==false,'populated-data rollback refusal absent');
$assert(strpos($migration,'$dataConditions')!==false&&strpos($migration,"`translation_status`<>'not_needed'")!==false&&strpos($migration,'IS NOT NULL')!==false,'rollback data-preservation check absent');
$assert(strpos($migration,'ADD COLUMN')!==false&&strpos($migration,'ADD INDEX')!==false,'additive migration operations absent');
$assert(strpos($migration,'DROP COLUMN')!==false&&strpos($migration,'DROP INDEX')!==false,'prestate rollback operations absent');
$assert(strpos($schema,'idx_chinaplanetsp_rate_translation (entity, fk_batch, translation_status)')!==false,'fresh-install translation index absent');
foreach (array('source_notes_raw mediumtext NULL','source_notes mediumtext NULL','notes_en mediumtext NULL',"translation_status varchar(16) NOT NULL DEFAULT 'not_needed'") as $definition) $assert(strpos($schema,$definition)!==false,'fresh-install definition absent '.$definition);

echo "M041_SCHEMA_SOURCE=PASS additive=YES prestate=YES rollback_guard=YES\n";
