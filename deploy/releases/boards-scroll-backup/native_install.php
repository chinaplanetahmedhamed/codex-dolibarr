<?php
/** Fixed feature setup only; no module initialization, role grants or backfill. */
if (PHP_SAPI !== 'cli') exit(1);
ini_set('display_errors','0'); error_reporting(0);
try {
    require '/var/www/html/conf/conf.php';
    $expected=getenv('CPO_BOARDS_EXPECTED_DB_IP');
    if ($dolibarr_main_db_host!=='mariadb'||$dolibarr_main_db_name!=='dolibarr_db'||$dolibarr_main_db_prefix!=='llx_'
        ||(int)($dolibarr_main_db_port?:3306)!==3306||!filter_var($expected,FILTER_VALIDATE_IP,FILTER_FLAG_IPV4)
        ||gethostbyname($dolibarr_main_db_host)!==$expected) throw new RuntimeException();
    $_SERVER['DOCUMENT_ROOT']='/var/www/html'; $_SERVER['SERVER_NAME']='backup-erp.visiontrading.biz'; $_SERVER['HTTP_HOST']='backup-erp.visiontrading.biz';
    require '/var/www/html/master.inc.php';
    if (DOL_VERSION!=='24.0.1'||(int)$conf->entity!==1) throw new RuntimeException();
    $mode=getenv('CPO_BOARDS_INSTALL_ACTION');
    $flags=['CPO_CUSTOMER_WORKSPACES_ENABLED','CPO_PROJECT_TASK_BRIDGE_ENABLED','CPO_WORKSPACE_GALLERY_ENABLED','CPO_BOARD_DELETE_ENABLED','CPO_BOARD_COMPLETION_ENABLED','CPO_BOARDS_SHORTCUT_ENABLED'];
    if ($mode==='migrate') {
        foreach (['InternalTaskWorkspaceMigration','InternalTaskProjectTaskMigration','InternalTaskWorkspaceGalleryMigration','InternalTaskBoardLifecycleMigration','InternalTaskCompletionMigration','InternalTaskWorkspaceAppearanceMigration','InternalTaskBoardsShortcutMigration'] as $class) {
            require_once DOL_DOCUMENT_ROOT.'/custom/chinaplanetoperations/class/'.$class.'.class.php';
            $migration=new $class($db,1); $migration->apply(); $migration->apply();
        }
        echo json_encode(['result'=>'BOARDS_SCHEMA_IDEMPOTENT','migrations'=>7]);
    } elseif ($mode==='enable') {
        require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
        foreach ($flags as $name) if (dolibarr_set_const($db,$name,'1','chaine',0,'',1)<=0) throw new RuntimeException();
        echo json_encode(['result'=>'BOARDS_FLAGS_ENABLED','flags'=>6]);
    } elseif ($mode==='restore_before_exposure') {
        // Admission is exclusively controlled by the pinned root helper's empty-state guard.
        require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
        foreach ($flags as $name) if (dolibarr_del_const($db,$name,1)<0) throw new RuntimeException();
        if (!$db->query("UPDATE ".MAIN_DB_PREFIX."const SET value='login:agenda' WHERE entity=1 AND name='MAIN_MODULE_CHINAPLANETOPERATIONS_HOOKS' AND value='login:agenda:leftblock'")) throw new RuntimeException();
        echo json_encode(['result'=>'BOARDS_OWN_SETTINGS_RESTORED']);
    } else throw new RuntimeException();
} catch (Throwable $error) {
    fwrite(STDERR,"BOARDS_NATIVE_SETUP_FAILED\n"); exit(2);
}
