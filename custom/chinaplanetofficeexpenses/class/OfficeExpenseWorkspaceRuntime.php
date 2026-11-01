<?php
declare(strict_types=1);
require_once __DIR__.'/OfficeExpenseStaffRuntime.php';

/** Deployment and schema installation never silently activate additional sections. */
final class OfficeExpenseWorkspaceRuntime
{
    public static function enabled($conf): bool {return OfficeExpenseStaffRuntime::enabled($conf)&&(string)($conf->global->CPO_OE_WORKSPACE_ENABLED??'0')==='1';}
    public static function ready($db,$conf): bool
    {
        if(!self::enabled($conf))return false;
        OfficeExpenseNativeSchemaMigration::forWorkspace(new OfficeExpenseMysqliSchemaBackend($db))->assertReady();return true;
    }
}
