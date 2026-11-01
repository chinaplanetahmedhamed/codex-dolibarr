<?php
declare(strict_types=1);
// Private, pinned backup-ERP activation worker. Never served by HTTP.
if(PHP_SAPI!=='cli'||$argc!==2||!in_array($argv[1],['inspect','prepare','activate','verify','disable'],true))exit(1);
$mode=$argv[1];$phase='identity';ob_start();
function must($ok,string $why): void {if(!$ok)throw new RuntimeException($why);}
function rows(string $sql): array {global$db;$q=$db->query($sql);must((bool)$q,'Native query failed');$out=[];while($r=$db->fetch_array($q)){$out[]=$r;must(count($out)<=100000,'Native snapshot exceeds bound');}return $out;}
function denied(callable $f): void {try{$f();}catch(DomainException $e){return;}throw new RuntimeException('Expected branch denial');}
function subjects(): array {return [[5,'moaz',4,'egypt','branch'],[6,'rina',5,'china','branch'],[7,'omar',6,'egypt','own'],[8,'shanshan',7,'china','own']];}
function oldGrants(): array {return ['expenses'=>['read','create'],'advances'=>['read','create']];}
function desired(string $scope): array {return $scope==='branch'?OfficeExpenseStaffAccess::ACTIONS:['expenses'=>['read','create'],'advances'=>['read','create'],'categories'=>['read'],'reports'=>['read'],'employees'=>['read'],'payroll'=>['read']];}
function constantFlag(string $v): void {global$db,$conf;must(dolibarr_set_const($db,'CPO_OE_WORKSPACE_ENABLED',$v,'chaine',0,'',1)>0,'Workspace flag update failed');$conf->global->CPO_OE_WORKSPACE_ENABLED=$v;}
function fingerprint(): string {
    global$db;$hash=hash_init('sha256');
    // Hash complete native financial rows privately; neither amounts nor bank details leave this worker.
    foreach(['bank_account','bank','expensereport','expensereport_det','payment_expensereport','salary','payment_salary','user','user_rights','usergroup','usergroup_user','usergroup_rights']as$name){
        $table=MAIN_DB_PREFIX.$name;$exists=rows("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='".$db->escape($table)."'");
        hash_update($hash,$name.'\n');if(!$exists)continue;
        foreach(rows('SELECT * FROM '.$table.' ORDER BY rowid')as$row)hash_update($hash,serialize($row).'\n');
    }return hash_final($hash);
}
try{
    must(function_exists('posix_geteuid')&&posix_geteuid()===33&&posix_getegid()===33,'Service identity differs');
    require '/var/www/html/master.inc.php';require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
    must(DOL_VERSION==='24.0.1'&&PHP_VERSION==='8.2.32'&&$db->type==='mysqli'&&$db->database_name==='dolibarr_db'&&$conf->entity===1&&$conf->currency==='USD'&&DOL_DATA_ROOT==='/var/www/documents'&&extension_loaded('bcmath')&&extension_loaded('gd'),'Runtime differs');
    must(preg_match('/^[A-Za-z0-9_]{1,40}$/D',MAIN_DB_PREFIX)&&strpos(rows('SELECT VERSION() AS v')[0]['v'],'10.6.27-MariaDB')===0,'Database differs');
    $user=new User($db);must($user->fetch(1)===1&&!empty($user->admin)&&(int)$user->status===1&&empty($user->socid)&&!$user->isNotIntoValidityDateRange(),'Bootstrap differs');$administrator=$user;
    must(!empty($conf->chinaplanetofficeexpenses->enabled)&&!empty($conf->expensereport->enabled)&&(string)($conf->global->CPO_OE_STAFF_ACCESS_ENABLED??'')==='1','Existing module differs');
    $module=DOL_DOCUMENT_ROOT.'/custom/chinaplanetofficeexpenses';require_once $module.'/class/OfficeExpenseStaffAccess.php';require_once $module.'/class/OfficeExpenseNativeSchemaMigration.php';
    $store=new OfficeExpenseNativeAccessStore($db);$phase='membership';
    must(count(rows('SELECT rowid FROM '.MAIN_DB_PREFIX.'cpo_oe_membership'))===4,'Membership population differs');
    foreach(subjects()as[$id,$login,$group,$office,$scope]){
        $staff=new User($db);must($staff->fetch($id)===1&&$staff->login===$login&&(int)$staff->entity===1&&(int)$staff->status===1&&empty($staff->admin)&&empty($staff->socid)&&!$staff->isNotIntoValidityDateRange(),'Approved staff differs');
        $rights=array_map(fn($r)=>(int)$r['fk_id'],rows('SELECT fk_id FROM '.MAIN_DB_PREFIX.'usergroup_rights WHERE entity=1 AND fk_usergroup='.$group.' ORDER BY fk_id'));
        must($rights===($scope==='branch'?[343,500601,500603,500604]:[343,500601,500603]),'Native group ceiling differs');
        $membership=rows('SELECT fk_user,entity FROM '.MAIN_DB_PREFIX.'usergroup_user WHERE fk_usergroup='.$group.' ORDER BY fk_user');must(count($membership)===1&&(int)$membership[0]['fk_user']===$id&&(int)$membership[0]['entity']===1,'Group identity differs');
        $m=$store->find(1,$id,$office);must($m&&$m['active']&&$m['record_scope']===$scope,'Branch differs');
        $wanted=in_array($mode,['inspect','prepare','activate'],true)?oldGrants():desired($scope);
        // A partially applied grant update is retained for reconciliation; never replayed automatically.
        if($mode!=='disable')must($m['grants']===$wanted&&$m['revision']===(in_array($mode,['inspect','prepare','activate'],true)?1:2),'Membership revision differs');
    }
    $financial=fingerprint();$schema=new OfficeExpenseMysqliSchemaBackend($db);(new OfficeExpenseNativeSchemaMigration($schema))->assertReady();OfficeExpenseNativeSchemaMigration::forReceipts($schema)->assertReady();OfficeExpenseNativeSchemaMigration::forAccess($schema)->assertReady();
    if($mode==='inspect'){
        must((string)($conf->global->CPO_OE_WORKSPACE_ENABLED??'0')==='0','Workspace already active');
        must(rows("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('".MAIN_DB_PREFIX."cpo_oe_workspace','".MAIN_DB_PREFIX."cpo_oe_workspace_audit')")===[],'Workspace schema already exists');
    }else{
        require_once $module.'/class/OfficeExpenseWorkspaceRuntime.php';require_once $module.'/class/OfficeExpenseWorkspaceService.php';
        $migration=OfficeExpenseNativeSchemaMigration::forWorkspace($schema);
        must(hash_file('sha256',$module.'/schema/workspace/manifest.json')==='bb5590e6b8a4ad033290b502fdedd5d8d510ce46f54ef95f10ef4b4d834c668a','Workspace schema manifest differs');
        if($mode==='prepare'){
            must(!OfficeExpenseWorkspaceRuntime::enabled($conf)&&$migration->plan()['state']==='empty','Migration replay denied');$migration->install($migration->plan()['manifest_hash']);$migration->assertReady();
        }elseif($mode==='activate'){
            // All four rev1 preconditions are checked before changing the first assignment.
            foreach(subjects()as[$id,$login,$group,$office,$scope]){$m=$store->find(1,$id,$office);must($m['grants']===oldGrants()&&$m['revision']===1,'Grant activation replay denied');}
            $migration->assertReady();must(!OfficeExpenseWorkspaceRuntime::enabled($conf),'Activation replay denied');
            $access=new OfficeExpenseStaffAccess($db,$administrator,$conf,1);
            foreach(subjects()as[$id,$login,$group,$office,$scope])$access->assign($id,$office,desired($scope),$scope,1);
            constantFlag('1');
        }elseif($mode==='disable'){
            // Compatible code/schema/grants and all records remain. No old parser or DB restoration.
            constantFlag('0');must(!OfficeExpenseWorkspaceRuntime::enabled($conf),'Disable readback differs');
        }else{
            must(OfficeExpenseWorkspaceRuntime::ready($db,$conf),'Workspace not ready');$checks=0;
            foreach(subjects()as[$id,$login,$group,$office,$scope]){
                $user=new User($db);must($user->fetch($id)===1,'Staff fetch failed');$access=new OfficeExpenseStaffAccess($db,$user,$conf,1);$service=new OfficeExpenseWorkspaceService($db,$user,1,$access);
                must(array_keys($access->offices())===[$office],'Cross-branch navigation');
                $other=$office==='egypt'?'china':'egypt';
                foreach(OfficeExpenseStaffAccess::ACTIONS as$section=>$actions){
                    foreach($actions as$action){must($access->allowed($office,$section,$action)===in_array($action,desired($scope)[$section]??[],true),'CRUD matrix differs');denied(fn()=>$access->assertAction($other,$section,$action));$checks+=2;}
                }
                if($scope==='own'){denied(fn()=>$service->cash($office));denied(fn()=>$access->directory());}
                else{must(count($service->cash($office)['accounts'])===2,'Native cash mapping differs');must(array_keys($access->managedOffices())===[$office],'Managed branch differs');}
                $report=$service->report($office,'2000-01-01','2099-12-31');must(($report['cash']!==null)===($scope==='branch'),'Cash report visibility differs');$checks+=4;
            }
        }
    }
    must(fingerprint()===$financial,'Native financial rows changed');
    ob_end_clean();echo json_encode(['status'=>$mode.'_verified','financial_posting'=>false,'financial_fingerprint'=>$financial,'native_branch_checks'=>$checks??0,'staff_password_login'=>'not_tested'],JSON_THROW_ON_ERROR);
}catch(Throwable $e){ob_end_clean();echo json_encode(['status'=>'STOP','phase'=>$phase,'error_class'=>get_class($e),'reason'=>'Workspace native step stopped; inspect retained private state.']);exit(1);}
