<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||$argc!==2)exit(2);
require __DIR__.'/fixtures/m119_native_bootstrap.php';
require __DIR__.'/fixtures/m119_native_identity.php';
require dirname(__DIR__,2).'/custom/chinaplanetofficeexpenses/class/OfficeExpenseNativeSchemaMigration.php';
require dirname(__DIR__,2).'/custom/chinaplanetofficeexpenses/class/OfficeExpenseWorkspaceService.php';
require __DIR__.'/fixtures/m119_native_migration.php';
[$db]=m119IdentityDatabase();$db->db->createFunction('JSON_UNQUOTE',fn($v)=>$v,1);
require __DIR__.'/fixtures/m119_native_cash.php';m119CashSchema($db);m119CashAccounts($db);
$conf->chinaplanetofficeexpenses=(object)['enabled'=>1];$conf->modules['chinaplanetofficeexpenses']=1;
$backend=new M119MigrationBackend($db);$base=new OfficeExpenseNativeSchemaMigration($backend);$base->install($base->plan()['manifest_hash']);
$am=OfficeExpenseNativeSchemaMigration::forAccess($backend);$am->install($am->plan()['manifest_hash']);
$ws=OfficeExpenseNativeSchemaMigration::forWorkspace($backend);
$pass=0;$fail=0;
function wt($name,$f){global$pass,$fail;try{$f();$pass++;echo "PASS $name\n";}catch(Throwable$e){$fail++;fwrite(STDERR,"FAIL $name: ".$e->getMessage()."\n");}}
function we($a,$b){if($a!==$b)throw new RuntimeException(var_export([$a,$b],true));}
function wb($f){try{$f();}catch(DomainException|InvalidArgumentException$e){return;}throw new RuntimeException('Expected refusal.');}
wt('workspace migration is explicit, separately pinned, idempotent and preserves base',function()use($ws,$base,$backend){we($ws->plan()['state'],'empty');wb(fn()=>$ws->assertReady());wb(fn()=>$ws->install(str_repeat('0',64)));$ws->install($ws->plan()['manifest_hash']);$ws->assertReady();we($base->plan()['state'],'installed');$n=$backend->ddlCount;$ws->install($ws->plan()['manifest_hash']);we($backend->ddlCount,$n);we($ws->reversalPlan()['destructive_sql'],[]);});
$db->query('UPDATE llx_user SET admin=1 WHERE rowid=7');
foreach([500601=>'draft',500603=>'read',500604=>'manageaccess']as$id=>$right){$db->query("INSERT INTO llx_rights_def(id,entity,module,perms) VALUES($id,1,'chinaplanetofficeexpenses','$right')");$db->query("INSERT INTO llx_user_rights(entity,fk_user,fk_id) VALUES(1,8,$id)");}
$db->query("INSERT INTO llx_user(rowid,entity,login,lastname,statut,fk_soc) VALUES(12,1,'synthetic-12','Rina',1,0)");
foreach([500601,500603]as$id)$db->query("INSERT INTO llx_user_rights(entity,fk_user,fk_id) VALUES(1,12,$id)");
$admin=m119FetchIdentity('synthetic-7');$moaz=m119FetchIdentity('synthetic-8');$rina=m119FetchIdentity('synthetic-12');
$owner=new OfficeExpenseStaffAccess($db,$admin,$conf,7);$egypt=new OfficeExpenseStaffAccess($db,$moaz,$conf,7);$china=new OfficeExpenseStaffAccess($db,$rina,$conf,7);
$a=new OfficeExpenseWorkspaceService($db,$admin,1,$owner);$m=new OfficeExpenseWorkspaceService($db,$moaz,1,$egypt);$r=new OfficeExpenseWorkspaceService($db,$rina,1,$china);
$grants=OfficeExpenseStaffAccess::ACTIONS;
$old=['expenses'=>['read','create'],'advances'=>['read','create']];
wt('new sections are denied to old grants and do not silently widen permissions',function()use($owner,$egypt,$old,$m){$owner->assign(8,'egypt',$old,'branch',0);we($egypt->allowed('egypt','payroll','read'),false);wb(fn()=>$m->list('egypt','payroll'));});
$owner->assign(8,'egypt',$grants,'branch',1);$owner->assign(12,'china',$grants,'own',0);
$category=null;$receipt=null;$cn=null;
wt('branch category create retry edit archive restore and immutable audit revisions',function()use($m,$db,&$category){$p=['name'=>'SYNTHETIC supplies','icon'=>'folder','active'=>'1'];$category=$m->save('egypt','category',$p,'category-request');we($m->save('egypt','category',$p,'category-request')['id'],$category['id']);$p['name']='SYNTHETIC renamed';wb(fn()=>$m->save('egypt','category',$p,'category-request'));$v=$m->save('egypt','category',$p,'category-update',$category['id'],1);we($v['revision'],2);wb(fn()=>$m->save('egypt','category',$p,'stale-request',$category['id'],1));$v=$m->change('egypt','category',$category['id'],2,'archive');we($v['status'],'archived');wb(fn()=>$m->save('egypt','category',$p,'archived-request',$category['id'],3));$v=$m->change('egypt','category',$category['id'],3,'restore');we($v['status'],'active');we(array_column($m->history('egypt','category',$category['id']),'action'),['create','update','archive','restore']);});
$rp=['amount'=>'25.10','currency'=>'EGP','date'=>'2026-11-02','payer'=>'=SYNTHETIC formula','phone'=>'','method'=>'cash','purpose'=>'SYNTHETIC receipt','notes'=>''];
wt('receipts persist exact original money; forged branch and direct-ID reads fail',function()use($a,$m,$r,$rp,&$receipt,&$cn){$receipt=$m->save('egypt','receipt',$rp,'receipt-request');$cn=$r->save('china','receipt',array_replace($rp,['currency'=>'CNY']),'china-receipt');we($receipt['content']['amount'],'25.10');we(count($m->list('egypt','receipt')),1);wb(fn()=>$r->get('egypt','receipt',$receipt['id']));wb(fn()=>$m->get('egypt','receipt',$cn['id']));wb(fn()=>$m->save('china','receipt',array_replace($rp,['currency'=>'CNY']),'forged-branch'));we(count($a->list('china','receipt')),1);});
wt('native employee subjects must be active branch members and own scope cannot target others',function()use($m,$r){$p=['user_id'=>'8','job'=>'Coordinator','phone'=>'','salary'=>'8000','currency'=>'EGP','hired_on'=>'2026-11-01','active'=>'1','notes'=>''];$m->save('egypt','employee',$p,'employee-request');wb(fn()=>$m->save('egypt','employee',array_replace($p,['user_id'=>'12']),'wrong-employee'));wb(fn()=>$r->save('china','employee',array_replace($p,['currency'=>'CNY']),'wrong-subject'));we(array_column($m->staff('egypt','employees'),'id'),[8]);});
wt('payroll employee/month uniqueness preserves gross versus loan and net without posting',function()use($m,$db){$p=['user_id'=>'8','month'=>'2026-11','base'=>'8000','bonus'=>'0','deduction'=>'0','loan_repayment'=>'4000','currency'=>'EGP','date'=>'2026-11-30','notes'=>'SYNTHETIC'];$v=$m->save('egypt','payroll',$p,'payroll-request');we($v['content']['gross'],'8000.00');we($v['content']['net'],'4000.00');we($v['status'],'draft');$failed=false;try{$m->save('egypt','payroll',$p,'duplicate-payroll');}catch(RuntimeException$e){$failed=true;}we($failed,true);we($db->transaction_opened,0);we(count($m->list('egypt','payroll')),1);});
wt('reports isolate branches currencies periods and excluded archives; CSV defuses formulas',function()use($m,$r,$receipt){$report=$m->report('egypt','2026-11-01','2026-11-30');we($report['totals']['EGP']['receipt'],'25.10');we($report['totals']['EGP']['payroll'],'4000.00');we(isset($report['totals']['CNY']),false);we(count($m->report('egypt','2026-10-01','2026-10-31')['rows']),0);wb(fn()=>$r->report('egypt','2026-11-01','2026-11-30'));$csv=OfficeExpenseWorkspaceService::csv(['rows'=>[['id'=>str_repeat('a',32),'kind'=>'receipt','status'=>'draft','content'=>['date'=>'2026-11-02','currency'=>'EGP','amount'=>'1.00','purpose'=>'=HYPERLINK("bad")']]]]);we(str_contains($csv,"'=HYPERLINK"),true);$m->change('egypt','receipt',$receipt['id'],1,'archive');we(isset($m->report('egypt','2026-11-01','2026-11-30')['totals']['EGP']['receipt']),false);});
wt('audit insert failure rolls document update back atomically',function()use($db,$m,$category){$before=$m->get('egypt','category',$category['id']);$db->db->exec("CREATE TRIGGER synthetic_workspace_failure BEFORE INSERT ON llx_cpo_oe_workspace_audit BEGIN SELECT RAISE(ABORT,'SYNTHETIC'); END");$failed=false;try{$m->change('egypt','category',$category['id'],4,'archive');}catch(RuntimeException$e){$failed=true;}finally{$db->db->exec('DROP TRIGGER synthetic_workspace_failure');}we($failed,true);we($m->get('egypt','category',$category['id']),$before);we($db->transaction_opened,0);});
wt('branch configuration never binds unrelated native money accounts while policy is pending',function()use($m){$p=['name'=>'China Planet Egypt','phone'=>'','currency'=>'EGP','account_id'=>'0'];we($m->save('egypt','settings',$p,'settings-request')['content']['account_id'],0);wb(fn()=>$m->save('egypt','settings',array_replace($p,['account_id'=>'1']),'bad-binding'));});
wt('revocation and native capability removal apply to existing document services immediately',function()use($owner,$r,$db,$cn){$db->query('DELETE FROM llx_user_rights WHERE fk_user=12 AND fk_id=500601');wb(fn()=>$r->change('china','receipt',$cn['id'],1,'archive'));$db->query('INSERT INTO llx_user_rights(entity,fk_user,fk_id) VALUES(1,12,500601)');$owner->revoke(12,'china',1);wb(fn()=>$r->get('china','receipt',$cn['id']));});

wt('expense category references and advance returns are branch-checked before saving',function()use($m,$category){
    $expense=['amount'=>'12.30','currency'=>'EGP','date'=>'2026-11-02','category_ref'=>$category['id'],'purpose'=>'SYNTHETIC printing','source_ref'=>'SYNTHETIC invoice','notes'=>"First line\nSecond line"];
    $v=$m->save('egypt','expense',$expense,'workspace-expense');we($v['content']['amount'],'12.30');we($v['content']['notes'],"First line\nSecond line");
    wb(fn()=>$m->save('egypt','expense',array_replace($expense,['category_ref'=>str_repeat('f',32)]),'bad-category'));
    unset($expense['category_ref']);$expense['purpose']='SYNTHETIC advance';$advance=$m->save('egypt','advance',$expense,'workspace-advance');
    $return=$expense+['settles_ref'=>$advance['id']];$return['amount']='2.30';we($m->save('egypt','advance_return',$return,'workspace-return')['content']['settles_ref'],$advance['id']);
    wb(fn()=>$m->save('egypt','advance_return',array_replace($return,['amount'=>'13']),'excess-return'));
    $m->save('egypt','deposit',['amount'=>'200','currency'=>'EGP','date'=>'2026-11-02','source'=>'SYNTHETIC owner funding','purpose'=>'SYNTHETIC office funding','notes'=>''],'workspace-deposit');
});
wt('own-scope users cannot read another creator or another payroll subject by direct ID',function()use($owner,$r,$a,$rp){$owner->assign(12,'china',OfficeExpenseStaffAccess::ACTIONS,'own',2);$foreign=$a->save('china','receipt',array_replace($rp,['currency'=>'CNY']),'foreign-creator-receipt');wb(fn()=>$r->get('china','receipt',$foreign['id']));we($r->allowed('china','category','create'),false);$category=$a->save('china','category',['name'=>'SYNTHETIC shared category','icon'=>'folder','active'=>'1'],'shared-branch-category');we($r->get('china','category',$category['id'])['id'],$category['id']);});
wt('separate workspace connection quarantines uncertain commits and fresh readback retains evidence',function()use($db){
    require_once __DIR__.'/fixtures/m119_native_storage_faults.php';
    foreach(['false_commit','lost_commit_response']as$mode){$fault=new M119NativeStorageFaults($db,$mode);$store=new OfficeExpenseWorkspaceStore($fault);$ref=bin2hex(random_bytes(16));$failed=false;try{$store->create(99,8,'egypt','deposit','workspace-'.$mode,$ref,'deposit:'.$ref,['amount'=>'1.00'],static function(){});}catch(RuntimeException $e){$failed=true;}we($failed,true);$fresh=new OfficeExpenseWorkspaceStore(new M119NativeSqlite($db->database_name));if($mode==='false_commit')we($fresh->find(99,$ref),null);else{we($fresh->find(99,$ref)['revision'],1);$refused=false;try{$store->find(99,$ref);}catch(RuntimeException $e){$refused=true;}we($refused,true);}}
});
require dirname(__DIR__,2).'/custom/chinaplanetofficeexpenses/class/OfficeExpenseWorkspacePage.php';
require dirname(__DIR__,2).'/custom/chinaplanetofficeexpenses/class/OfficeExpenseStaffController.php';
$controller=new OfficeExpenseWorkspaceController($m,'synthetic-workspace-token');
wt('all section controllers render AR EN escaped native forms and preserve branch routes',function()use($controller){
    set_error_handler(function($n,$message){throw new RuntimeException($message);});
    try{foreach(['ar','en']as$lang)foreach(array_keys(OfficeExpenseWorkspaceController::VIEWS)as$view){
        $state=$controller->handle('GET',['view'=>$view,'office'=>'egypt','lang'=>$lang],[]);we($state['status'],200);$state['manage_staff']=true;
        $html=(new OfficeExpenseWorkspacePage())->render($state,'synthetic-next');we(str_contains($html,'aria-disabled="true"'),false);we(str_contains($html,'type="money"'),false);we(str_contains($html,'workspace.php?lang='.$lang.'&amp;office=egypt&amp;view=categories'),true);
        $dir=getenv('M119_WORKSPACE_VISUAL_DIR');if($dir){if(!is_dir($dir))throw new RuntimeException('Missing synthetic visual directory.');file_put_contents($dir.'/'.$lang.'-workspace-'.$view.'.html','<!doctype html><html lang="'.$lang.'" dir="'.($lang==='ar'?'rtl':'ltr').'"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>M119 local test · '.$view.' · '.$lang.'</title><link rel="stylesheet" href="/custom/chinaplanetofficeexpenses/assets/fontawesome.css"><link rel="stylesheet" href="/custom/chinaplanetofficeexpenses/assets/fontawesome-solid.css"><link rel="stylesheet" href="/custom/chinaplanetofficeexpenses/employee.css"><link rel="stylesheet" href="/custom/chinaplanetofficeexpenses/workspace.css"><script src="/custom/chinaplanetofficeexpenses/workspace.js" defer></script></head><body>'.$html.'</body></html>');}
    }}finally{restore_error_handler();}
});
wt('controller rejects bad CSRF forged identity mutations wrong branches and unknown verbs',function()use($controller,$rp){$p=['view'=>'receipts','office'=>'egypt','lang'=>'ar','action'=>'save','id'=>'','revision'=>'0','submission_key'=>'controller-receipt','payload'=>$rp,'token'=>'synthetic-workspace-token'];we($controller->handle('POST',[],array_replace($p,['token'=>'bad']))['status'],403);we($controller->handle('POST',[],$p+['entity'=>'2'])['status'],422);we($controller->handle('POST',[],array_replace($p,['office'=>'china']))['status'],403);we($controller->handle('POST',[],array_replace($p,['action'=>'approve']))['status'],422);we($controller->handle('POST',[],$p,['file'=>[]])['status'],422);we($controller->handle('DELETE',[],[])['status'],405);$result=$controller->handle('POST',[],$p);we($result['status'],303);we($controller->handle('POST',[],$p)['redirect'],$result['redirect']);parse_str(parse_url($result['redirect'],PHP_URL_QUERY),$query);we($controller->handle('GET',$query+['print'=>'1'],[])['print'],true);});
wt('invalid report dates and direct ID kind substitution fail without exposing records',function()use($controller,$receipt){we($controller->handle('GET',['view'=>'reports','office'=>'egypt','from'=>'2026-02-30'],[])['status'],422);we($controller->handle('GET',['view'=>'reports','office'=>'egypt','from'=>'2026-11-30','to'=>'2026-11-01'],[])['status'],422);$result=$controller->handle('GET',['view'=>'payroll','office'=>'egypt','edit'=>$receipt['id']],[]);we($result['status'],403);we($result['selected'],null);we($result['rows'],[]);});
wt('expanded staff matrix exposes exact supported CRUD arrays without enabling financial approval',function()use($owner){$state=(new OfficeExpenseStaffController($owner,'synthetic'))->handle('GET',['office'=>'egypt','staff_id'=>'8'],[]);$state['workspace_ready']=true;$html=(new OfficeExpenseStaffPage())->render($state,'synthetic-next');foreach(['treasury','receipts','categories','reports','employees','payroll','settings']as$section)we(str_contains($html,'name="permissions['.$section.'][]"'),true);we(str_contains($html,'value="approve"'),false);we(str_contains($html,'aria-disabled="true"'),false);});
wt('four staff manager-worker CRUD and payslip matrix isolates Egypt and China',function()use($db,$conf,$owner,$m,$r){
    $db->query("INSERT INTO llx_user(rowid,entity,login,lastname,statut,fk_soc) VALUES(13,1,'synthetic-13','Omar',1,0),(14,1,'synthetic-14','Shan Shan',1,0)");
    foreach([13,14]as$id)foreach([500601,500603]as$right)$db->query("INSERT INTO llx_user_rights(entity,fk_user,fk_id) VALUES(1,$id,$right)");
    $members=new OfficeExpenseNativeAccessStore($db);$owner->assign(12,'china',OfficeExpenseStaffAccess::ACTIONS,'branch',$members->find(1,12,'china')['revision']);
    $worker=['expenses'=>['read','create'],'advances'=>['read','create'],'reports'=>['read'],'categories'=>['read'],'payroll'=>['read']];
    $owner->assign(13,'egypt',$worker,'own',0);$owner->assign(14,'china',$worker,'own',0);
    $omar=m119FetchIdentity('synthetic-13');$shan=m119FetchIdentity('synthetic-14');
    $o=new OfficeExpenseWorkspaceService($db,$omar,1,new OfficeExpenseStaffAccess($db,$omar,$conf,7));$s=new OfficeExpenseWorkspaceService($db,$shan,1,new OfficeExpenseStaffAccess($db,$shan,$conf,7));
    $expense=['amount'=>'8.10','currency'=>'EGP','date'=>'2026-11-02','category_ref'=>'','purpose'=>'SYNTHETIC worker claim','source_ref'=>'SYNTHETIC worker','notes'=>''];
    $eg=$o->save('egypt','expense',$expense,'omar-workspace-claim');$cn=$s->save('china','expense',array_replace($expense,['currency'=>'CNY']),'shan-workspace-claim');
    we($m->get('egypt','expense',$eg['id'])['id'],$eg['id']);we($r->get('china','expense',$cn['id'])['id'],$cn['id']);
    wb(fn()=>$m->get('china','expense',$cn['id']));wb(fn()=>$r->get('egypt','expense',$eg['id']));wb(fn()=>$o->get('china','expense',$cn['id']));
    wb(fn()=>$o->save('egypt','expense',$expense,'worker-edit',$eg['id'],1));wb(fn()=>$o->change('egypt','expense',$eg['id'],1,'archive'));
    we($m->save('egypt','expense',array_replace($expense,['amount'=>'9.10']),'manager-edit',$eg['id'],1)['revision'],2);we($m->change('egypt','expense',$eg['id'],2,'archive')['status'],'archived');we($m->change('egypt','expense',$eg['id'],3,'restore')['status'],'draft');
    $pay=['user_id'=>'13','month'=>'2026-11','base'=>'8000','bonus'=>'0','deduction'=>'0','loan_repayment'=>'4000','currency'=>'EGP','date'=>'2026-11-30','notes'=>'SYNTHETIC payslip'];
    $p=$m->save('egypt','payroll',$pay,'omar-workspace-pay');we($o->get('egypt','payroll',$p['id'])['content']['net'],'4000.00');wb(fn()=>$s->get('egypt','payroll',$p['id']));wb(fn()=>$o->save('egypt','payroll',$pay,'worker-pay-forged'));
    $report=$o->report('egypt','2026-11-01','2026-11-30');we($report['totals']['EGP']['expense'],'9.10');we($report['totals']['EGP']['payroll'],'4000.00');
    foreach($report['rows']as$row)we($row['kind']==='payroll'?$row['subject']:$row['owner'],13);
});
wt('native treasury reads separate original currencies and openings without cross-branch or entity leakage',function()use($m,$r,$owner,$db){
    $cash=$m->cash('egypt');we($cash['restricted'],false);we(array_column($cash['accounts'],'currency'),['EGP','USD']);we(array_column($cash['accounts'],'balance'),['100.30','17.30']);
    we($cash['accounts'][0]['entries'][0]['amount'],'-23.15');we($cash['accounts'][0]['entries'][1]['opening'],true);we($cash['accounts'][0]['has_more'],false);
    we(array_column($r->cash('china')['accounts'],'balance'),['789.01','19.45']);wb(fn()=>$m->cash('china'));wb(fn()=>$r->cash('egypt'));
    wb(fn()=>new OfficeExpenseNativeTreasury($db,m119FetchIdentity('synthetic-8'),1,new OfficeExpenseStaffAccess($db,m119FetchIdentity('synthetic-8'),$GLOBALS['conf'],7)));
});
wt('own treasury visibility never discloses a full branch balance and revoked grants take effect',function()use($db,$conf,$owner){
    $user=m119FetchIdentity('synthetic-13');$member=(new OfficeExpenseNativeAccessStore($db))->find(1,13,'egypt');
    $grants=$member['grants'];$grants['treasury']=['read'];$owner->assign(13,'egypt',$grants,'own',$member['revision']);
    $service=new OfficeExpenseWorkspaceService($db,$user,1,new OfficeExpenseStaffAccess($db,$user,$conf,7));we($service->cash('egypt'),['restricted'=>true,'accounts'=>[]]);
    $db->query('DELETE FROM llx_user_rights WHERE fk_user=13 AND fk_id=500603');try{wb(fn()=>$service->cash('egypt'));}finally{$db->query('INSERT INTO llx_user_rights(entity,fk_user,fk_id) VALUES(1,13,500603)');}
});
wt('cash mappings fail closed on duplicate missing wrong-currency closed or non-cash accounts',function()use($m,$db){
    foreach(["currency_code='CNY'","clos=1","courant=1","ref='SYNTHETIC'","entity=2"]as$mutation){
        $db->query('UPDATE llx_bank_account SET '.$mutation.' WHERE rowid=101');$failed=false;try{$m->cash('egypt');}catch(RuntimeException $e){$failed=true;}finally{$db->query("UPDATE llx_bank_account SET ref='CPO-EG-EGP',entity=1,currency_code='EGP',clos=0,courant=2 WHERE rowid=101");}we($failed,true);
    }
    $db->query("INSERT INTO llx_bank_account(rowid,entity,ref,label,courant,clos,currency_code,fk_pays) VALUES(106,1,'CPO-EG-EGP','SYNTHETIC duplicate',2,0,'EGP',1)");
    $failed=false;try{$m->cash('egypt');}catch(RuntimeException $e){$failed=true;}finally{$db->query('DELETE FROM llx_bank_account WHERE rowid=106');}we($failed,true);
});
wt('native treasury statement limit is explicit and its balance still includes older entries',function()use($m,$db){
    for($i=0;$i<101;$i++)$db->query("INSERT INTO llx_bank(rowid,dateo,datev,fk_account,amount,fk_type,label) VALUES(".(300+$i).",'2001-01-03','2001-01-03',102,0.10,'LIQ','SYNTHETIC statement')");
    try{$account=$m->cash('egypt')['accounts'][1];we($account['balance'],'27.40');we($account['has_more'],true);we(count($account['entries']),100);we($account['entries'][0]['id'],400);}finally{$db->query('DELETE FROM llx_bank WHERE rowid BETWEEN 300 AND 400');}
});
wt('settings accepts only the mapped primary account and never changes cash or currency bindings',function()use($m,$r,$db){
    we(array_column($m->accounts('egypt'),'id'),[101,102]);wb(fn()=>$r->accounts('egypt'));
    $settings=$m->list('egypt','settings')[0];$payload=$settings['content'];$payload['account_id']='101';
    $before=$m->cash('egypt');we($m->save('egypt','settings',$payload,'mapped-primary',$settings['id'],$settings['revision'])['content']['account_id'],101);
    foreach(['102','103','105','1']as$id)wb(fn()=>$m->save('egypt','settings',array_replace($payload,['account_id'=>$id]),'forged-cash'));
    we($m->cash('egypt'),$before);
});
wt('native cash cards render AR EN and operational forms offer USD without combining balances',function()use($m){
    foreach(['ar','en']as$lang){$controller=new OfficeExpenseWorkspaceController($m,'synthetic');$state=$controller->handle('GET',['view'=>'treasury','office'=>'egypt','lang'=>$lang],[]);we($state['status'],200);
        $html=(new OfficeExpenseWorkspacePage())->render($state,'synthetic');we(str_contains($html,'100.30 EGP'),true);we(str_contains($html,'17.30 USD'),true);we(str_contains($html,'value="USD"'),true);we(str_contains($html,'789.01'),false);
        $state=$controller->handle('GET',['view'=>'settings','office'=>'egypt','lang'=>$lang],[]);we($state['status'],200);$html=(new OfficeExpenseWorkspacePage())->render($state,'synthetic');we(str_contains($html,'CPO-EG-USD'),true);we(str_contains($html,'17.30 USD'),false);
    }
});
wt('future-dated cash is recorded but excluded from native current availability',function()use($m,$db){
    $db->query("INSERT INTO llx_bank(rowid,dateo,datev,fk_account,amount,fk_type,label) VALUES(501,'2099-01-01','2099-01-01',102,2.25,'SOLD','SYNTHETIC future opening')");
    try{$account=$m->cash('egypt')['accounts'][1];we($account['balance'],'19.55');we($account['available_balance'],'17.30');we($account['entries'][0]['future'],true);$state=(new OfficeExpenseWorkspaceController($m,'synthetic'))->handle('GET',['view'=>'treasury','office'=>'egypt','lang'=>'en'],[]);$html=(new OfficeExpenseWorkspacePage())->render($state,'synthetic');we(str_contains($html,'Future dated · not available yet'),true);we(str_contains($html,'Available as of Dolibarr date'),true);}finally{$db->query('DELETE FROM llx_bank WHERE rowid=501');}
});
echo "$pass passed, $fail failed\n";exit($fail?1:0);
