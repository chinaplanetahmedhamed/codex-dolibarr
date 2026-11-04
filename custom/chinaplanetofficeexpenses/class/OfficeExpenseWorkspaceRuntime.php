<?php
declare(strict_types=1);
require_once __DIR__.'/OfficeExpenseStaffRuntime.php';

/** Deployment and schema installation never silently activate additional sections. */
final class OfficeExpenseWorkspaceRuntime
{
    /** The module home must work for a read-only accountant too, without constructing a legacy draft writer. */
    public static function landing(OfficeExpenseStaffAccess $access,array $query,string $language): ?string
    {
        if(isset($query['view'])&&$query['view']!=='overview')return null;
        if(array_intersect(['draft','action','search','kind','from','to','saved'],array_keys($query)))return null;
        $language=$query['lang']??$language;if(!is_string($language)||!in_array($language,['ar','en'],true))return null;
        $offices=$access->offices();$office=$query['office']??array_key_first($offices);if(!is_string($office)||!isset($offices[$office]))throw new DomainException('Assigned branch unavailable.');
        $views=$access->navigationViews($office);if(!$views)throw new DomainException('No permitted workspace section.');
        $view=in_array('overview',$views,true)?'overview':$views[0];return 'workspace.php?'.http_build_query(['lang'=>$language,'office'=>$office,'view'=>$view]);
    }
    public static function enabled($conf): bool {return OfficeExpenseStaffRuntime::enabled($conf)&&(string)($conf->global->CPO_OE_WORKSPACE_ENABLED??'0')==='1';}
    public static function ready($db,$conf): bool
    {
        if(!self::enabled($conf))return false;
        OfficeExpenseNativeSchemaMigration::forWorkspace(new OfficeExpenseMysqliSchemaBackend($db))->assertReady();return true;
    }
}
