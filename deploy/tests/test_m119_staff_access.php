<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||$argc!==2)exit(2);
require __DIR__.'/fixtures/m119_native_bootstrap.php';
require __DIR__.'/fixtures/m119_native_identity.php';
require dirname(__DIR__,2).'/custom/chinaplanetofficeexpenses/class/OfficeExpenseStaffAccess.php';
require dirname(__DIR__,2).'/custom/chinaplanetofficeexpenses/class/OfficeExpenseNativeReceiptStore.php';
require dirname(__DIR__,2).'/custom/chinaplanetofficeexpenses/class/OfficeExpenseStaffController.php';
require dirname(__DIR__,2).'/custom/chinaplanetofficeexpenses/class/OfficeExpenseStaffPage.php';
require dirname(__DIR__,2).'/custom/chinaplanetofficeexpenses/class/OfficeExpenseEmployeeController.php';
require dirname(__DIR__,2).'/custom/chinaplanetofficeexpenses/class/OfficeExpenseEmployeePage.php';
require dirname(__DIR__,2).'/custom/chinaplanetofficeexpenses/class/OfficeExpenseStaffRuntime.php';
require __DIR__.'/fixtures/m119_native_migration.php';
require __DIR__.'/fixtures/m119_native_storage_faults.php';
require __DIR__.'/fixtures/m119_native_receipts.php';
[$db]=m119IdentityDatabase();
$db->db->createFunction('JSON_UNQUOTE',fn($v)=>$v,1);
$conf->chinaplanetofficeexpenses=(object)['enabled'=>1];$conf->modules['chinaplanetofficeexpenses']=1;
$module=dirname(__DIR__,2).'/custom/chinaplanetofficeexpenses';
$backend=new M119MigrationBackend($db);
$baseMigration=new OfficeExpenseNativeSchemaMigration($backend);$baseMigration->install($baseMigration->plan()['manifest_hash']);
$accessMigration=OfficeExpenseNativeSchemaMigration::forAccess($backend);$accessMigration->install($accessMigration->plan()['manifest_hash']);
$receiptMigration=OfficeExpenseNativeSchemaMigration::forReceipts($backend);$receiptMigration->install($receiptMigration->plan()['manifest_hash']);
$db->query('UPDATE llx_user SET admin=1 WHERE rowid=7');
foreach([500601=>'draft',500603=>'read',500604=>'manageaccess']as$id=>$right){
    $db->query("INSERT INTO llx_rights_def(id,entity,module,perms) VALUES($id,1,'chinaplanetofficeexpenses','$right')");
    $db->query("INSERT INTO llx_user_rights(entity,fk_user,fk_id) VALUES(1,8,$id)");
}
$db->query("INSERT INTO llx_user(rowid,entity,login,lastname,statut,fk_soc) VALUES(12,1,'synthetic-12','Rina',1,0)");
$db->query('INSERT INTO llx_user_rights(entity,fk_user,fk_id) VALUES(1,12,500603)');
$admin=m119FetchIdentity('synthetic-7');$moaz=m119FetchIdentity('synthetic-8');$rina=m119FetchIdentity('synthetic-12');
$store=new OfficeExpenseNativeAccessStore($db);
$owner=new OfficeExpenseStaffAccess($db,$admin,$conf,7,$store);
$egypt=new OfficeExpenseStaffAccess($db,$moaz,$conf,7,$store);
$china=new OfficeExpenseStaffAccess($db,$rina,$conf,7,$store);
$pass=0;$fail=0;
function testAccess($name,$call){global$pass,$fail;try{$call();$pass++;echo "PASS $name\n";}catch(Throwable$e){$fail++;fwrite(STDERR,"FAIL $name: ".$e->getMessage()."\n");}}
function aeq($a,$b){if($a!==$b)throw new RuntimeException('Values differ.');}
function denied($f){try{$f();}catch(DomainException|InvalidArgumentException$e){return;}throw new RuntimeException('Expected denial.');}
$reader=['expenses'=>['read'],'advances'=>['read']];$writer=['expenses'=>['read','create'],'advances'=>['read','create']];
testAccess('native rights without branch membership deny access',function()use($egypt){aeq($egypt->offices(),[]);denied(fn()=>$egypt->assertAction('egypt','expenses','read'));});
testAccess('bootstrap assigns existing native identities only',function()use($owner,$writer,$reader){
    $owner->assign(8,'egypt',$writer,'branch',0);$owner->assign(12,'china',$reader,'branch',0);
    denied(fn()=>$owner->assign(9,'egypt',$reader,'branch',0));denied(fn()=>$owner->assign(10,'egypt',$reader,'branch',0));denied(fn()=>$owner->assign(11,'egypt',$reader,'branch',0));
});
testAccess('branches isolate Moaz and Rina with independent native read ceiling',function()use($egypt,$china){
    aeq(array_keys($egypt->offices()),['egypt']);aeq(array_keys($china->offices()),['china']);
    $egypt->assertAction('egypt','expenses','create');$china->assertAction('china','expenses','read');
    denied(fn()=>$china->assertAction('china','expenses','create'));denied(fn()=>$egypt->assertAction('china','expenses','read'));
});
testAccess('different roles never union across branches',function()use($owner,$egypt,$reader){$owner->assign(8,'china',$reader,'own',0);$egypt->assertAction('egypt','expenses','create');denied(fn()=>$egypt->assertAction('china','expenses','create'));aeq($egypt->recordScope('china','expenses'),'own');});
testAccess('self promotion unsupported actions and stale edits rejected',function()use($owner,$egypt,$writer,$reader){
    denied(fn()=>$egypt->assign(8,'egypt',$writer,'branch',1));denied(fn()=>$owner->assign(8,'egypt',['expenses'=>['read','approve']],'branch',1));
    denied(fn()=>$owner->assign(8,'egypt',$reader,'branch',0));denied(fn()=>$owner->assign(8,'egypt',['expenses'=>['create']],'branch',1));
});
testAccess('delegated manager cannot exceed own branch scope or grant ceiling',function()use($egypt,$writer){denied(fn()=>$egypt->assign(12,'china',$writer,'branch',0));denied(fn()=>$egypt->assign(12,'china',['expenses'=>['read']],'branch',1));});
testAccess('directory projection contains no credentials and excludes other entity external inactive',function()use($owner){$rows=$owner->directory('synthetic');foreach($rows as$row)aeq(array_keys($row),['id','login','name','status']);aeq(in_array(9,array_column($rows,'id')),false);aeq(in_array(10,array_column($rows,'id')),false);aeq(in_array(11,array_column($rows,'id')),false);});
$sources=new OfficeExpenseNativeDraftStore($db);$money=new OfficeExpenseMoney();
$m=new OfficeExpenseDraftService($sources,$money,$moaz,1,['egypt'=>['EGP'],'china'=>['CNY']],'EGP',$egypt);
$r=new OfficeExpenseDraftService($sources,$money,$rina,1,['egypt'=>['EGP'],'china'=>['CNY']],'EGP',$china);
$payload=['kind'=>'expense_claim','office'=>'egypt','amount'=>'25.00','currency'=>'EGP','occurred_on'=>'2026-11-02','source_ref'=>'SYNTHETIC','purpose'=>'SYNTHETIC Egypt'];
$eg=null;$cn=null;
testAccess('draft create and branch readable queries enforce grants',function()use($m,$r,$owner,$sources,$admin,$money,$payload,&$eg,&$cn){
    $eg=$m->submit($payload,'synthetic-egypt');$a=new OfficeExpenseDraftService($sources,$money,$admin,1,['egypt'=>['EGP'],'china'=>['CNY']],'EGP',$owner);
    $p=$payload;$p['office']='china';$p['currency']='CNY';$p['quote']=['rate'=>'0.1','date'=>'2026-11-02','source'=>'SYNTHETIC'];$cn=$a->submit($p,'synthetic-china');
    aeq(array_column($r->listReadable(),'id'),[$cn['id']]);denied(fn()=>$r->getReadable($eg['id']));denied(fn()=>$m->submit($p,'forged-china'));denied(fn()=>$r->submit($p,'reader-create'));
    aeq(array_column($m->listReadable(),'id'),[$eg['id']]);aeq($r->getReadable($cn['id'])['id'],$cn['id']);denied(fn()=>$r->getOwn($cn['id']));
});
testAccess('assignment controller enforces CSRF forged fields branch and stale revision',function()use($owner,$china){
    $controller=new OfficeExpenseStaffController($owner,'synthetic-token');
    $post=['action'=>'assign','token'=>'synthetic-token','staff_id'=>'12','office'=>'china','revision'=>'1','permissions'=>['expenses'=>'read','advances'=>'read'],'record_scope'=>'branch'];
    foreach(['',[], 'expired']as$token){$bad=$post;$bad['token']=$token;aeq($controller->handle('POST',[],$bad)['status'],403);}
    $bad=$post;$bad['entity']='2';aeq($controller->handle('POST',[],$bad)['status'],422);
    $bad=$post;$bad['permissions']=['accounting'=>'read'];aeq($controller->handle('POST',[],$bad)['status'],422);
    $bad=$post;$bad['office']='other';aeq($controller->handle('POST',[],$bad)['status'],403);
    aeq($controller->handle('POST',[],$post,['receipt'=>[]])['status'],403);
    aeq((new OfficeExpenseStaffController($china,'synthetic-token'))->handle('GET',[],[])['status'],403);
    aeq($controller->handle('PUT',[],[])['status'],405);
    $bad=$post;$bad['revision']='0';aeq($controller->handle('POST',[],$bad)['status'],403);
});
testAccess('valid CSRF assignment edit revoke and reactivation persist through controller redirects',function()use($owner,$store){
    $controller=new OfficeExpenseStaffController($owner,'synthetic-token');
    $post=['action'=>'assign','token'=>'synthetic-token','staff_id'=>'12','office'=>'china','revision'=>'1','permissions'=>['expenses'=>'read','advances'=>'read'],'record_scope'=>'branch'];
    aeq($controller->handle('POST',[],$post)['status'],303);aeq($store->find(1,12,'china')['revision'],2);
    $revoke=['action'=>'revoke','token'=>'synthetic-token','staff_id'=>'12','office'=>'china','revision'=>'2'];aeq($controller->handle('POST',[],$revoke)['status'],303);aeq($store->find(1,12,'china')['active'],false);
    $post['revision']='3';aeq($controller->handle('POST',[],$post)['status'],303);aeq($store->find(1,12,'china')['revision'],4);
});
testAccess('native group ceilings and expiry are refreshed without replacing the policy',function()use($db,$china){
    $db->query('DELETE FROM llx_user_rights WHERE fk_user=12');
    $db->query('INSERT INTO llx_usergroup_user(entity,fk_user,fk_usergroup) VALUES(1,12,70)');
    $db->query('INSERT INTO llx_usergroup_rights(entity,fk_usergroup,fk_id) VALUES(2,70,500603)');
    denied(fn()=>$china->assertAction('china','expenses','read'));
    $db->query('UPDATE llx_usergroup_rights SET entity=1');$china->assertAction('china','expenses','read');
    $db->query('DELETE FROM llx_usergroup_rights');denied(fn()=>$china->assertAction('china','expenses','read'));
    $db->query('INSERT INTO llx_user_rights(entity,fk_user,fk_id) VALUES(1,12,500603)');
    $db->query("UPDATE llx_user SET dateendvalidity='2000-01-01' WHERE rowid=12");denied(fn()=>$china->offices());
    $db->query('UPDATE llx_user SET dateendvalidity=NULL WHERE rowid=12');
});
testAccess('branch reader downloads permitted private receipts but cannot attach or cross branches',function()use($db,$backend,$owner,$china,$egypt,$admin,$moaz,$rina,$money,$cn,$eg,$reader){
    global $nreceiptRoots;
    $base=DOL_DATA_ROOT.'/m119-native-receipts-'.bin2hex(random_bytes(8));mkdir($base,0700);$nreceiptRoots[]=$base;mkdir($base.'/private',0700);mkdir($base.'/web',0700);
    file_put_contents($base.'/private/.cpo-office-expenses-private','cpo-office-expenses-private-v1');chmod($base.'/private/.cpo-office-expenses-private',0600);
    $src=new OfficeExpenseNativeDraftStore($backend);$store=new OfficeExpenseNativeReceiptStore($backend,$src,$base.'/private',$base.'/web');
    $make=fn($user,$acl)=>new OfficeExpenseReceiptService(new OfficeExpenseDraftService($src,$money,$user,1,OfficeExpenseStaffAccess::OFFICES,'EGP',$acl),$store);
    $adminReceipts=$make($admin,$owner);$rinaReceipts=$make($rina,$china);$moazReceipts=$make($moaz,$egypt);$bytes=nimage();
    $meta=$adminReceipts->attach($cn['id'],'synthetic-branch-receipt','test.png',$bytes);
    aeq($rinaReceipts->read($cn['id'],$meta['id'])['bytes'],$bytes);aeq(count($rinaReceipts->listReadable($cn['id'])),1);
    denied(fn()=>$moazReceipts->read($cn['id'],$meta['id']));denied(fn()=>$rinaReceipts->read($eg['id'],$meta['id']));
    denied(fn()=>$rinaReceipts->attach($cn['id'],'synthetic-denied','test.png',$bytes));
    $owner->revoke(12,'china',4);denied(fn()=>$rinaReceipts->read($cn['id'],$meta['id']));
    $owner->assign(12,'china',$reader,'branch',5);
});
testAccess('read-only employee route rejects forged create and hides create controls',function()use($r,$cn,$eg){
    $controller=new OfficeExpenseEmployeeController($r,'synthetic-token','synthetic-next','ar');$state=$controller->handle('GET',[],[]);
    aeq($state['status'],200);aeq(array_column($state['drafts'],'id'),[$cn['id']]);aeq($state['create_offices'],[]);
    $html=(new OfficeExpenseEmployeePage())->render($state,'ar','synthetic-next');aeq(str_contains($html,'class="quick-add"'),false);aeq(str_contains($html,'data-open="expense-dialog"'),false);aeq(str_contains($html,'data-draft-form'),false);
    $post=['action'=>'save','token'=>'synthetic-token','office'=>'china','kind'=>'expense_claim','amount'=>'12.00','currency'=>'CNY','occurred_on'=>'2026-11-02','purpose'=>'FORGED','source_ref'=>'FORGED','submission_key'=>'synthetic-forged-reader'];
    aeq($controller->handle('POST',[],$post)['status'],403);aeq($controller->handle('GET',['draft'=>$eg['id']],[])['status'],404);
});
testAccess('native assignment page preserves safe fields Arabic English and escaped directory names',function()use($db,$owner){
    $db->query("UPDATE llx_user SET firstname='<script>SYNTHETIC</script>' WHERE rowid=12");
    foreach(['ar','en']as$lang){$state=(new OfficeExpenseStaffController($owner,'synthetic-token'))->handle('GET',['office'=>'china','staff_id'=>'12','lang'=>$lang],[]);aeq($state['status'],200);
        $html=(new OfficeExpenseStaffPage())->render($state,'synthetic-next');aeq(str_contains($html,'<script>SYNTHETIC</script>'),false);aeq(str_contains($html,'&lt;script&gt;SYNTHETIC&lt;/script&gt;'),true);aeq(str_contains($html,'type="password"'),false);
        // Optional deterministic local visual fixture; contains synthetic names only.
        if(getenv('M119_STAFF_VISUAL_DIR')){file_put_contents(getenv('M119_STAFF_VISUAL_DIR').'/'.$lang.'-users-native-test.html','<!doctype html><html lang="'.$lang.'" dir="'.($lang==='ar'?'rtl':'ltr').'"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="employee.css"><link rel="stylesheet" href="staff.css"></head><body>'.$html.'</body></html>');}
    }
    $db->query("UPDATE llx_user SET firstname=NULL WHERE rowid=12");
});
testAccess('revocation immediately affects existing service and keeps other branch history',function()use($owner,$egypt,$m,$store,$eg){$owner->revoke(8,'egypt',1);aeq(array_keys($egypt->offices()),['china']);denied(fn()=>$m->getReadable($eg['id']));aeq($store->find(1,8,'egypt')['active'],false);});
testAccess('native right removal status module entity changes fail on existing policy',function()use($db,$china,$conf){
    $db->query('DELETE FROM llx_user_rights WHERE fk_user=12');denied(fn()=>$china->assertAction('china','expenses','read'));
    $db->query('INSERT INTO llx_user_rights(entity,fk_user,fk_id) VALUES(1,12,500603)');$china->assertAction('china','expenses','read');
    $db->query('UPDATE llx_user SET statut=0 WHERE rowid=12');denied(fn()=>$china->offices());$db->query('UPDATE llx_user SET statut=1 WHERE rowid=12');
    $conf->entity=2;denied(fn()=>$china->offices());$conf->entity=1;$conf->chinaplanetofficeexpenses->enabled=0;denied(fn()=>$china->offices());$conf->chinaplanetofficeexpenses->enabled=1;
});
testAccess('membership and complete audit roll back atomically when audit insert fails',function()use($db,$owner,$store,$reader){
    $before=$store->find(1,8,'egypt');$db->db->exec("CREATE TRIGGER synthetic_fail_access BEFORE INSERT ON llx_cpo_oe_access_audit BEGIN SELECT RAISE(ABORT,'SYNTHETIC audit unavailable'); END");
    $failed=false;try{$owner->assign(8,'egypt',$reader,'own',2);}catch(RuntimeException$e){$failed=true;}finally{$db->db->exec('DROP TRIGGER synthetic_fail_access');}
    aeq($failed,true);aeq($store->find(1,8,'egypt'),$before);aeq($db->transaction_opened,0);
});
testAccess('false or lost commit acknowledgement never reports confirmed assignment',function()use($db,$admin,$conf,$reader,$store){
    foreach(['false_commit','lost_commit_response']as$mode){
        $fault=new M119NativeStorageFaults($db,$mode);$accessStore=new OfficeExpenseNativeAccessStore($fault);$policy=new OfficeExpenseStaffAccess($db,$admin,$conf,7,$accessStore);
        $before=$store->find(1,8,'egypt');$failed=false;try{$policy->assign(8,'egypt',$reader,'own',$before['revision']);}catch(RuntimeException$e){$failed=true;}aeq($failed,true);
        if($mode==='false_commit')aeq($store->find(1,8,'egypt'),$before);
        else{ $rejected=false;try{$accessStore->find(1,8,'egypt');}catch(RuntimeException$e){$rejected=true;}aeq($rejected,true);$fresh=new OfficeExpenseNativeAccessStore(new M119NativeSqlite($db->database_name));aeq($fresh->find(1,8,'egypt')['revision'],$before['revision']+1); }
    }
});
testAccess('staff activation defaults off and access migration keeps separate pinned state',function()use($conf,$accessMigration,$baseMigration,$receiptMigration,$backend){
    aeq(OfficeExpenseStaffRuntime::enabled($conf),false);aeq($accessMigration->plan()['state'],'installed');aeq($baseMigration->plan()['state'],'installed');aeq($receiptMigration->plan()['state'],'installed');
    $before=$backend->ddlCount;$accessMigration->install($accessMigration->plan()['manifest_hash']);aeq($before,$backend->ddlCount);aeq($accessMigration->reversalPlan()['destructive_sql'],[]);
    denied(fn()=>$accessMigration->install(str_repeat('0',64)));
});
testAccess('approved manager and worker roles isolate Egypt China and own records',function()use($db,$owner,$egypt,$china,$store,$writer,$money,$sources,$moaz,$rina,$payload,$eg,$cn){
    $db->query("INSERT INTO llx_user(rowid,entity,login,lastname,statut,fk_soc) VALUES(13,1,'synthetic-13','Omar',1,0),(14,1,'synthetic-14','Shan Shan',1,0)");
    $db->query('INSERT INTO llx_user_rights(entity,fk_user,fk_id) VALUES(1,12,500601),(1,12,500604),(1,13,500601),(1,13,500603),(1,14,500601),(1,14,500603)');
    $owner->assign(8,'egypt',$writer,'branch',$store->find(1,8,'egypt')['revision']);$owner->revoke(8,'china',$store->find(1,8,'china')['revision']);
    $owner->assign(12,'china',$writer,'branch',$store->find(1,12,'china')['revision']);$owner->assign(13,'egypt',$writer,'own',0);$owner->assign(14,'china',$writer,'own',0);
    $omar=m119FetchIdentity('synthetic-13');$shan=m119FetchIdentity('synthetic-14');$omarAccess=new OfficeExpenseStaffAccess($db,$omar,$GLOBALS['conf'],7,$store);$shanAccess=new OfficeExpenseStaffAccess($db,$shan,$GLOBALS['conf'],7,$store);
    $make=fn($user,$acl)=>new OfficeExpenseDraftService($sources,$money,$user,1,OfficeExpenseStaffAccess::OFFICES,'EGP',$acl);
    $o=$make($omar,$omarAccess);$s=$make($shan,$shanAccess);$egyptManager=$make($moaz,$egypt);$chinaManager=$make($rina,$china);
    $od=$o->submit($payload,'synthetic-omar-expense');$cp=$payload;$cp['office']='china';$cp['currency']='CNY';$cp['quote']=['rate'=>'0.1','date'=>'2026-11-02','source'=>'SYNTHETIC'];$sd=$s->submit($cp,'synthetic-shan-expense');
    aeq(array_keys($egypt->managedOffices()),['egypt']);aeq(array_keys($china->managedOffices()),['china']);
    aeq($egyptManager->getReadable($od['id'])['id'],$od['id']);aeq($chinaManager->getReadable($sd['id'])['id'],$sd['id']);
    denied(fn()=>$egyptManager->getReadable($sd['id']));denied(fn()=>$chinaManager->getReadable($od['id']));
    aeq(array_column($o->listReadable(),'id'),[$od['id']]);aeq(array_column($s->listReadable(),'id'),[$sd['id']]);
    denied(fn()=>$o->getReadable($eg['id']));denied(fn()=>$s->getReadable($cn['id']));denied(fn()=>$o->submit($cp,'synthetic-cross-branch'));denied(fn()=>$omarAccess->directory());denied(fn()=>$shanAccess->directory());
});
testAccess('partial access migration fails closed without altering original draft schema',function(){
    global $db,$conf;$savedDb=$db;$savedConf=$conf;
    try{
        m119IdentityDatabase();$backend=new M119MigrationBackend($db);$base=new OfficeExpenseNativeSchemaMigration($backend);$base->install($base->plan()['manifest_hash']);
        $stage=OfficeExpenseNativeSchemaMigration::forAccess($backend);$hash=$stage->plan()['manifest_hash'];$backend->failAt=$backend->ddlCount+2;
        $failed=false;try{$stage->install($hash);}catch(RuntimeException$e){$failed=true;}aeq($failed,true);$fresh=new M119MigrationBackend(new M119NativeSqlite($db->database_name));$stage=OfficeExpenseNativeSchemaMigration::forAccess($fresh);aeq($stage->plan()['state'],'partial');aeq((new OfficeExpenseNativeSchemaMigration($fresh))->plan()['state'],'installed');
        denied(fn()=>$stage->assertReady());denied(fn()=>$stage->install($hash));aeq($stage->reversalPlan()['destructive_sql'],[]);
    }finally{$db=$savedDb;$conf=$savedConf;}
});
echo "$pass passed, $fail failed\n";exit($fail?1:0);
