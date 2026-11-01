<?php
declare(strict_types=1);
// Fixed private staging release worker. Never installed beneath the web root.
if(PHP_SAPI!=='cli'||!in_array($argv[1]??'',['inspect','prepare','activate','accept','verify','disable'],true))exit(1);
$mode=$argv[1];if($argc!==($mode==='disable'?3:2))exit(1);$phase='identity';ob_start();
function must($condition,string $reason): void {if(!$condition)throw new RuntimeException($reason);}
function rows(string $sql): array {global$db;$result=$db->query($sql);must((bool)$result,'Query failed');$out=[];while($row=$db->fetch_array($result))$out[]=$row;return $out;}
function denied(callable $call): void {try{$call();}catch(DomainException $e){return;}throw new RuntimeException('Expected scope denial');}
function subjects(): array {return [
    [5,'moaz',4,'Office Expenses - Egypt Managers','egypt','branch',[500601,500603,500604]],
    [6,'rina',5,'Office Expenses - China Managers','china','branch',[500601,500603,500604]],
    [7,'omar',6,'Office Expenses - Egypt Workers','egypt','own',[500601,500603]],
    [8,'shanshan',7,'Office Expenses - China Workers','china','own',[500601,500603]],
];}
function setReleaseConstant(string $name,string $value): void {global$db,$conf;must(dolibarr_set_const($db,$name,$value,'chaine',0,'',1)>0,'Constant update failed');$conf->global->$name=$value;}
function bind(int $id): array {
    global$db,$conf,$user,$pilot;$user=new User($db);must($user->fetch($id)===1,'Native staff fetch failed');
    $access=new OfficeExpenseStaffAccess($db,$user,$conf,1);$factory=new OfficeExpenseNativeStorageFactory($db,$user,$conf,$access->offices(),$pilot->privateRoot(),DOL_DOCUMENT_ROOT,[],10,26214400,$pilot->precision(),$access);
    $drafts=$factory->draftFactory($db,$user,$conf);return [$access,$drafts,$factory->receiptFactory($db,$user,$conf,$drafts)];
}
function payload(string $office): array {return ['kind'=>'expense_claim','office'=>$office,'amount'=>'1.00','currency'=>$office==='egypt'?'EGP':'CNY','occurred_on'=>'2026-11-02','source_ref'=>'M119-SYNTHETIC-BRANCH-UAT-20261102','purpose'=>'SYNTHETIC deployment branch-isolation acceptance; not a real expense','quote'=>['rate'=>$office==='egypt'?'0.02000000':'0.14000000','date'=>'2026-11-02','source'=>'SYNTHETIC fixed UAT quote, not market FX']];}
try{
    must(function_exists('posix_geteuid')&&posix_geteuid()===33&&posix_getegid()===33,'Service identity differs');
    require '/var/www/html/master.inc.php';require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';require_once DOL_DOCUMENT_ROOT.'/user/class/usergroup.class.php';require_once DOL_DOCUMENT_ROOT.'/core/class/menubase.class.php';
    must(DOL_VERSION==='24.0.1'&&PHP_VERSION==='8.2.32'&&$db->type==='mysqli'&&$conf->entity===1&&$conf->currency==='USD'&&DOL_DATA_ROOT==='/var/www/documents'&&extension_loaded('bcmath')&&extension_loaded('gd'),'Native runtime differs');
    must(preg_match('/^[A-Za-z0-9_]{1,40}$/D',MAIN_DB_PREFIX)&&$db->database_name==='dolibarr_db','Native database differs');
    $version=rows('SELECT VERSION() AS v')[0]['v'];must(strpos($version,'10.6.27-MariaDB')===0,'Engine differs');
    $user=new User($db);must($user->fetch(1)===1&&!empty($user->admin)&&(int)$user->status===1&&empty($user->socid)&&!$user->isNotIntoValidityDateRange()&&!empty($conf->expensereport->enabled),'Bootstrap differs');$user->loadRights('expensereport');$administrator=$user;
    $module=DOL_DOCUMENT_ROOT.'/custom/chinaplanetofficeexpenses';$prefix=MAIN_DB_PREFIX;
    $menus=rows("SELECT rowid,module,url,enabled,perms FROM {$prefix}menu WHERE entity=1 AND url='/custom/chinaplanetoperations/office_expenses/index.php'");
    must(count($menus)<=1,'Ambiguous legacy menu');if($menus)must($menus[0]['module']==='chinaplanetoperations','Legacy menu owner differs');
    $phase='staff_identity';
    if($mode!=='disable')foreach(subjects()as[$id,$login,$groupId,$groupName,$office,$scope,$rights]){
        $staff=new User($db);must($staff->fetch($id)===1&&$staff->login===$login&&(int)$staff->entity===1&&(int)$staff->status===1&&empty($staff->admin)&&empty($staff->socid)&&!$staff->isNotIntoValidityDateRange(),'Approved staff differs');
        $group=rows("SELECT nom,entity FROM {$prefix}usergroup WHERE rowid=$groupId");must(count($group)===1&&$group[0]['nom']===$groupName&&(int)$group[0]['entity']===1,'Approved group differs');
        $members=rows("SELECT fk_user,entity FROM {$prefix}usergroup_user WHERE fk_usergroup=$groupId ORDER BY fk_user");must(count($members)===1&&(int)$members[0]['fk_user']===$id&&(int)$members[0]['entity']===1,'Group membership differs');
        $actual=array_map(fn($r)=>(int)$r['fk_id'],rows("SELECT fk_id FROM {$prefix}usergroup_rights WHERE entity=1 AND fk_usergroup=$groupId ORDER BY fk_id"));$expected=in_array($mode,['inspect','prepare','activate'],true)?[343]:array_merge([343],$rights);sort($expected);
        if($mode!=='disable')must($actual===$expected,'Approved capability baseline differs');
    }
    $snapshot=['legacy_menu'=>$menus,'module_enabled'=>(int)($conf->global->MAIN_MODULE_CHINAPLANETOFFICEEXPENSES??0),'staff_enabled'=>(int)($conf->global->CPO_OE_STAFF_ACCESS_ENABLED??0)];
    if($mode==='inspect'){
        must($snapshot['module_enabled']===0&&$snapshot['staff_enabled']===0,'Standalone already enabled');
        must(rows("SELECT id FROM {$prefix}rights_def WHERE id BETWEEN 500601 AND 500604 OR module='chinaplanetofficeexpenses'")===[],'Native capability collision');
        must(rows("SELECT name FROM {$prefix}const WHERE entity IN (0,1) AND name IN ('CPO_OE_STAFF_ACCESS_ENABLED','MAIN_MODULE_CHINAPLANETOFFICEEXPENSES')")===[],'Pre-existing standalone constants');
        must(rows("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('{$prefix}cpo_oe_membership','{$prefix}cpo_oe_access_audit')")===[],'Existing access schema');
        $out=['status'=>'inspect_verified','snapshot'=>$snapshot,'entity'=>1,'base_currency'=>'USD','accounts_verified'=>4,'financial_posting'=>false];
    }elseif($mode==='disable'){
        setReleaseConstant('CPO_OE_STAFF_ACCESS_ENABLED','0');setReleaseConstant('MAIN_MODULE_CHINAPLANETOFFICEEXPENSES','0');
        // Restore only the known menu owned by this release; retain all data and native grants.
        must(strlen($argv[2])<=8192,'Restore snapshot exceeds bound');$restore=json_decode($argv[2],true,8,JSON_THROW_ON_ERROR);must(is_array($restore)&&isset($restore['legacy_menu'])&&is_array($restore['legacy_menu'])&&count($restore['legacy_menu'])<=1,'Restore snapshot differs');
        foreach($restore['legacy_menu']as$row){must($row['module']==='chinaplanetoperations'&&$row['url']==='/custom/chinaplanetoperations/office_expenses/index.php'&&count($menus)===1&&(int)$menus[0]['rowid']===(int)$row['rowid']&&$menus[0]['perms']===$row['perms'],'Legacy restore identity differs');$menu=new Menubase($db);must($menu->fetch((int)$row['rowid'])>0,'Legacy menu unavailable');must(in_array($menu->enabled,['0',$row['enabled']],true),'Legacy menu changed after deployment');$menu->enabled=$row['enabled'];must($menu->update($administrator)>0,'Legacy menu restore failed');}
        $out=['status'=>'disabled_verified','financial_posting'=>false,'data_retained'=>true];
    }else{
        $phase='installed_schema';require $module.'/class/OfficeExpenseStaffRuntime.php';require $module.'/class/OfficeExpenseNativeStorageFactory.php';require $module.'/class/OfficeExpenseEmployeeController.php';require $module.'/class/OfficeExpenseStaffController.php';
        $pilot=OfficeExpensePilotConfiguration::read(OfficeExpensePilotConfiguration::PATH,DOL_DOCUMENT_ROOT);must($pilot->scope($administrator,$conf)===['egypt'=>['EGP'],'china'=>['CNY']],'Private scope differs');
        $backend=new OfficeExpenseMysqliSchemaBackend($db);(new OfficeExpenseNativeSchemaMigration($backend))->assertReady();OfficeExpenseNativeSchemaMigration::forReceipts($backend)->assertReady();$migration=OfficeExpenseNativeSchemaMigration::forAccess($backend);
        must(hash_file('sha256',$module.'/schema/access/manifest.json')==='4a2940653ad3ac90aada067f25868517cb0ef74430d3ae7863d8481d57d232b6','Access manifest differs');
        if($mode==='prepare'){
            must($snapshot['module_enabled']===0&&$snapshot['staff_enabled']===0&&$migration->plan()['state']==='empty','Migration baseline differs');$migration->install($migration->plan()['manifest_hash']);$migration->assertReady();$out=['status'=>'prepared_verified','financial_posting'=>false];
        }else{
            $migration->assertReady();
            if($mode==='activate'){
                $phase='native_module';must($snapshot['module_enabled']===0&&$snapshot['staff_enabled']===0,'Activation replay denied');
                must(rows("SELECT rowid FROM {$prefix}cpo_oe_membership")===[]&&rows("SELECT id FROM {$prefix}rights_def WHERE id BETWEEN 500601 AND 500604")===[],'Activation baseline changed');
                $result=activateModule('modChinaPlanetOfficeExpenses',0);must(empty($result['errors'])&&(int)$result['nbmodules']===1,'Native module registration failed');
                $conf->chinaplanetofficeexpenses=(object)['enabled'=>1];$conf->global->MAIN_MODULE_CHINAPLANETOFFICEEXPENSES='1';
                $phase='native_group_rights';
                foreach(subjects()as[$id,$login,$groupId,$groupName,$office,$scope,$rights]){$group=new UserGroup($db);must($group->fetch($groupId)>0,'Native group fetch failed');foreach($rights as$right)must($group->addrights($right,'','',1)>0,'Native group capability failed');}
                $phase='branch_assignments';$owner=new OfficeExpenseStaffAccess($db,$administrator,$conf,1);foreach(subjects()as[$id,$login,$groupId,$groupName,$office,$scope,$rights])$owner->assign($id,$office,OfficeExpenseStaffAccess::ACTIONS,$scope,0);
                foreach($menus as$row){$menu=new Menubase($db);must($menu->fetch((int)$row['rowid'])>0&&$menu->enabled===$row['enabled'],'Legacy menu changed');$menu->enabled='0';must($menu->update($administrator)>0,'Legacy menu disable failed');}
                $phase='staff_enable';setReleaseConstant('CPO_OE_STAFF_ACCESS_ENABLED','1');$out=['status'=>'activated_verified','financial_posting'=>false];
            }else{
                must($snapshot['module_enabled']===1&&$snapshot['staff_enabled']===1,'Staff activation differs');
                $phase='actual_branch_policy';$store=new OfficeExpenseNativeAccessStore($db);$drafts=[];$checks=0;
                foreach(subjects()as[$id,$login,$groupId,$groupName,$office,$scope,$rights]){
                    [$access,$service,$receipts]=bind($id);must(array_keys($access->offices())===[$office]&&$access->recordScope($office,'expenses')===$scope,'Branch policy differs');
                    $member=$store->find(1,$id,$office);must($member!==null&&$member['active']&&$member['grants']===OfficeExpenseStaffAccess::ACTIONS&&$member['revision']===1,'Stored membership differs');
                    $access->assertAction($office,'expenses','create');denied(fn()=>$access->assertAction($office==='egypt'?'china':'egypt','expenses','read'));denied(fn()=>$access->assertAction($office,'expenses','delete'));
                    if($scope==='own'){denied(fn()=>$access->directory());}else must(array_keys($access->managedOffices())===[$office],'Manager scope differs');
                    $key='M119-STANDALONE-UAT-20261102-'.$id;
                    if($mode==='accept')$drafts[$id]=$service->submit(payload($office),$key);
                    else{$r=rows("SELECT draft_ref FROM {$prefix}cpo_oe_draft WHERE entity=1 AND fk_user_creat=$id AND submission_key='".$db->escape($key)."'");must(count($r)===1,'Retained UAT draft missing');$drafts[$id]=$service->getOwn($r[0]['draft_ref']);}
                    must($drafts[$id]['status']==='draft'&&$drafts[$id]['native_record']===null,'Draft posted unexpectedly');$checks+=5;
                }
                foreach(subjects()as[$id,$login,$groupId,$groupName,$office,$scope,$rights]){[$access,$service,$receipts]=bind($id);foreach($drafts as$ownerId=>$draft){$allowed=($draft['office']===$office&&($scope==='branch'||$id===$ownerId));if($allowed)must($service->getReadable($draft['id'])['id']===$draft['id'],'Allowed record denied');else denied(fn()=>$service->getReadable($draft['id']));$checks++;} $ids=array_column($service->listReadable(),'id');foreach($drafts as$ownerId=>$draft)must(in_array($draft['id'],$ids,true)===($draft['office']===$office&&($scope==='branch'||$id===$ownerId)),'SQL list scope differs');}
                $phase='private_receipt';[$a,$s,$receipts]=bind(7);if($mode==='accept'){$image=imagecreatetruecolor(3,2);ob_start();imagepng($image);$bytes=ob_get_clean();imagedestroy($image);$meta=$receipts->attach($drafts[7]['id'],'M119-STAFF-UAT-RECEIPT','SYNTHETIC-UAT.png',$bytes);}else{$list=$receipts->listOwn($drafts[7]['id']);must(count($list)===1,'Retained receipt missing');$meta=$list[0];}
                must(strlen($receipts->read($drafts[7]['id'],$meta['id'])['bytes'])>0,'Owner receipt unavailable');[$a,$s,$managerReceipts]=bind(5);must(strlen($managerReceipts->read($drafts[7]['id'],$meta['id'])['bytes'])>0,'Branch manager receipt unavailable');[$a,$s,$otherReceipts]=bind(6);denied(fn()=>$otherReceipts->read($drafts[7]['id'],$meta['id']));$checks+=3;
                $phase='controllers';[$a,$s,$receipts]=bind(7);$controller=new OfficeExpenseEmployeeController($s,'SYNTHETIC-CSRF','SYNTHETIC-NEXT','ar',$receipts);$bad=payload('egypt');$bad['action']='save';$bad['token']='invalid';must($controller->handle('POST',[],$bad)['status']===403,'CSRF guard differs');must((new OfficeExpenseStaffController($a,'SYNTHETIC-CSRF'))->handle('GET',[],[])['status']===403,'Worker directory exposed');$checks+=2;
                $out=['status'=>'verified','native_branch_checks'=>$checks,'retained_synthetic_drafts'=>4,'private_receipt_verified'=>true,'financial_posting'=>false,'staff_login_acceptance'=>'requires_native_credentials'];
            }
        }
    }
    ob_end_clean();echo json_encode($out,JSON_THROW_ON_ERROR);
}catch(Throwable $error){ob_end_clean();echo json_encode(['status'=>'STOP','phase'=>$phase,'error_class'=>get_class($error),'reason'=>'Pinned native release step did not verify; inspect private state.'],JSON_THROW_ON_ERROR);exit(1);}
