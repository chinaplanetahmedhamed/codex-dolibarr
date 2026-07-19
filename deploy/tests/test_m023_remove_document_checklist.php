<?php
$root = dirname(__DIR__, 2);
$library = file_get_contents($root.'/custom/chinaplanetlogistics/lib/shipment_customer.lib.php');
$card = file_get_contents($root.'/custom/chinaplanetlogistics/card.php');

function m023Assert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "M023_TEST=FAIL REASON=".$message."\n");
        exit(1);
    }
}

m023Assert(strpos($library, "return cpl_schema_has_table(\$db, 'cpl_shipment_event')\n        && cpl_schema_has_table(\$db, 'cpl_document_requirement')") === false, 'CHECKLIST_STILL_BLOCKS_SCHEMA');
m023Assert(strpos($library, "langs->trans('CPLDocumentChecklist')") === false, 'CHECKLIST_PANEL_STILL_RENDERED');
m023Assert(strpos($library, 'name="action" value="save_requirement"') === false, 'CHECKLIST_FORM_STILL_RENDERED');
m023Assert(strpos($card, "if (\$action === 'save_requirement'") !== false && strpos($card, "if (\$action === 'save_requirement' && \$id > 0 && \$canwrite && \$is_post) {\n    accessforbidden();") !== false, 'LEGACY_WRITE_NOT_REJECTED');
m023Assert(is_file($root.'/custom/chinaplanetlogistics/sql/llx_cpl_document_requirement.sql'), 'LEGACY_SCHEMA_WAS_DELETED');

echo "M023_TEST=PASS\n";
