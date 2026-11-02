<?php
/** Read-only native metadata/fingerprints. Config stays server-side; no ERP bootstrap. */
if (PHP_SAPI !== 'cli') exit(1);
ini_set('display_errors', '0');
error_reporting(0);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$stage = 'configuration';
try {
    require '/var/www/html/conf/conf.php';
    require '/var/www/html/version.inc.php';
    $p = $dolibarr_main_db_prefix;
    if (!preg_match('/^[A-Za-z0-9_]+$/D', $p) || DOL_VERSION !== '24.0.1') throw new RuntimeException();
    $stage = 'database_identity';
    $expectedIp = getenv('CPO_BOARDS_EXPECTED_DB_IP');
    if ($dolibarr_main_db_host !== 'mariadb' || $dolibarr_main_db_name !== 'dolibarr_db'
        || (int)($dolibarr_main_db_port ?: 3306) !== 3306 || $p !== 'llx_'
        || !filter_var($expectedIp,FILTER_VALIDATE_IP,FILTER_FLAG_IPV4)
        || gethostbyname($dolibarr_main_db_host) !== $expectedIp) throw new RuntimeException();
    $stage = 'connection';
    $c = new mysqli($dolibarr_main_db_host, $dolibarr_main_db_user, $dolibarr_main_db_pass, $dolibarr_main_db_name, (int) ($dolibarr_main_db_port ?: 3306));
    $c->set_charset('utf8mb4');
    $c->query('SET SESSION TRANSACTION READ ONLY');
    $c->query('START TRANSACTION WITH CONSISTENT SNAPSHOT');
    $out = ['result'=>'BOARDS_NATIVE_READONLY', 'dolibarr'=>DOL_VERSION, 'php'=>PHP_VERSION, 'database_engine'=>$c->server_info, 'flags'=>[], 'schema'=>[], 'protected'=>[]];
    $names = ['MAIN_MODULE_CHINAPLANETOPERATIONS','CPO_BOARDS_ENABLED','CPO_SHIPMENT_CARDS_ENABLED','CPO_CUSTOMER_WORKSPACES_ENABLED','CPO_PROJECT_TASK_BRIDGE_ENABLED','CPO_WORKSPACE_GALLERY_ENABLED','CPO_BOARD_DELETE_ENABLED','CPO_BOARD_COMPLETION_ENABLED','CPO_BOARDS_SHORTCUT_ENABLED','CPO_ACCOUNT_STATEMENTS_ENABLED','MAIN_MODULE_CHINAPLANETOPERATIONS_HOOKS'];
    $quoted = implode(',', array_map(fn($v)=>"'".$c->real_escape_string($v)."'", $names));
    $r = $c->query('SELECT name,value,entity FROM `'.$p.'const` WHERE entity=1 AND name IN ('.$quoted.') ORDER BY name');
    while ($v = $r->fetch_assoc()) $out['flags'][$v['name']] = $v['value'];
    foreach (['cpo_project_board','cpo_project_task','cpo_customer_workspace_meta','cpo_customer_workspace_pref','cpo_board_deleted','cpo_board_completion','cpo_customer_workspace_appearance','societe_extrafields'] as $name) {
        $stage = 'schema_'.$name;
        $r = $c->query("SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='".$c->real_escape_string($p.$name)."'");
        $table = $r->fetch_assoc();
        if (!$table) { $out['schema'][$name] = null; continue; }
        $columns=[]; $r=$c->query('SHOW COLUMNS FROM `'.$p.$name.'`');
        while ($v=$r->fetch_assoc()) $columns[$v['Field']] = ['type'=>$v['Type'],'null'=>$v['Null'],'default'=>$v['Default']];
        $keys=[]; $r=$c->query('SHOW INDEX FROM `'.$p.$name.'`');
        while ($v=$r->fetch_assoc()) if (!(int)$v['Non_unique']) $keys[$v['Key_name']][(int)$v['Seq_in_index']]=$v['Column_name'];
        $out['schema'][$name]=['engine'=>$table['ENGINE'],'columns'=>$columns,'unique'=>$keys];
    }
    // Values never leave this process. Include all preexisting business/access rows.
    $tables=['societe','projet','projet_task','projet_task_extrafields','element_contact','cpo_board','cpo_board_list','cpo_board_card','cpo_board_member','cpo_card_grant','cpo_board_pref','cpo_task','cpo_task_context','cpo_task_comment','cpo_task_asset','cpo_task_details','cpo_activity','cpo_board_request','user','user_rights','rights_def','usergroup','usergroup_user','usergroup_rights'];
    foreach ($tables as $name) {
        $stage = 'fingerprint_'.$name;
        $exists=$c->query("SELECT COUNT(*) n FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='".$c->real_escape_string($p.$name)."'")->fetch_assoc();
        if (!(int)$exists['n']) { $out['protected'][$name]=null; continue; }
        $columns='*';
        if ($name==='user') $columns='rowid,entity,login,admin,statut,fk_soc,pass_crypted';
        $order = $name === 'rights_def' ? 'id' : 'rowid';
        $r=$c->query('SELECT '.$columns.' FROM `'.$p.$name.'` ORDER BY '.$order);
        $hash=hash_init('sha256'); $count=0;
        while ($v=$r->fetch_assoc()) { hash_update($hash,json_encode($v,JSON_THROW_ON_ERROR)."\n"); $count++; }
        $out['protected'][$name]=['rows'=>$count,'sha256'=>hash_final($hash)];
    }
    $stage = 'workspace_option';
    $r=$c->query("SELECT type FROM `".$p."extrafields` WHERE name='cpo_board_workspace' AND elementtype='societe' AND entity IN (0,1)");
    $out['workspace_option_types']=array_column($r->fetch_all(MYSQLI_ASSOC),'type');
    $c->rollback();
    echo json_encode($out,JSON_THROW_ON_ERROR)."\n";
} catch (Throwable $error) {
    if (isset($c)) { try { $c->rollback(); } catch (Throwable $ignored) {} }
    fwrite(STDERR,'BOARDS_NATIVE_READONLY_FAILED phase='.$stage.' code='.(int)$error->getCode()."\n");
    exit(2);
}
