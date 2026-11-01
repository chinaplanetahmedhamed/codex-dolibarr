<?php
declare(strict_types=1);
require_once __DIR__.'/OfficeExpenseStaffAccess.php';
require_once __DIR__.'/OfficeExpensePilotConfiguration.php';
require_once __DIR__.'/OfficeExpenseNativeSchemaMigration.php';

/** Explicit activation boundary. Merely deploying this source never opens staff access. */
final class OfficeExpenseStaffRuntime
{
    public static function enabled($conf): bool {return (string)($conf->global->CPO_OE_STAFF_ACCESS_ENABLED??'0')==='1';}
    public static function access($db,$user,$conf,OfficeExpensePilotConfiguration $pilot): OfficeExpenseStaffAccess
    {
        if(!self::enabled($conf))throw new DomainException('Staff access is not activated.');
        OfficeExpenseNativeSchemaMigration::forAccess(new OfficeExpenseMysqliSchemaBackend($db))->assertReady();
        return new OfficeExpenseStaffAccess($db,$user,$conf,$pilot->staffBootstrap($conf));
    }
}
