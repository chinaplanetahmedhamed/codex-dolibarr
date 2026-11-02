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
wt('report charts retain six exact monthly buckets currency isolation and archive exclusion',function()use($m,$controller){
    $report=$m->report('egypt','2026-11-01','2026-11-30');
    we(array_keys($report['charts']),['EGP','USD']);we(count($report['charts']['EGP']['months']),6);
    we(array_column($report['charts']['EGP']['months'],'month'),['2026-06','2026-07','2026-08','2026-09','2026-10','2026-11']);
    $last=$report['charts']['EGP']['months'][5];we($last['deposit'],'200.00');we($last['expense'],'12.30');we($last['payroll'],'4000.00');we($last['net'],'-3812.30');
    we($report['charts']['EGP']['categories'][0]['amount'],'12.30');we($report['charts']['USD']['categories'],[]);
    we($report['charts']['USD']['months'][5]['expense'],'0.00');
    $state=$controller->handle('GET',['view'=>'reports','office'=>'egypt','lang'=>'en','month'=>'2026-11','currency'=>'USD'],[]);we($state['status'],200);we($state['report_currency'],'USD');
    $html=(new OfficeExpenseWorkspacePage())->render($state,'synthetic');we(substr_count($html,'class="chart-bar '),12);we(str_contains($html,'data-workspace-back'),true);we(str_contains($html,'No expenses in this period to chart.'),true);
    we($controller->handle('GET',['view'=>'reports','office'=>'egypt','currency'=>'CNY'],[])['status'],422);
    we($controller->handle('GET',['view'=>'reports','office'=>'egypt','month'=>'2026-13'],[])['status'],422);
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
    $report=$o->report('egypt','2026-11-01','2026-11-30');we($report['totals']['EGP']['expense'],'9.10');we($report['totals']['EGP']['payroll'],'4000.00');we($report['charts']['EGP']['months'][5]['expense'],'9.10');we($report['charts']['EGP']['categories'][0]['amount'],'9.10');
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
    try{$account=$m->cash('egypt')['accounts'][1];we($account['balance'],'19.55');we($account['available_balance'],'17.30');we($account['entries'][0]['future'],true);$state=(new OfficeExpenseWorkspaceController($m,'synthetic'))->handle('GET',['view'=>'treasury','office'=>'egypt','lang'=>'en','from'=>'2099-01-01','to'=>'2099-01-01'],[]);$default=(new OfficeExpenseWorkspaceController($m,'synthetic'))->handle('GET',['view'=>'treasury','office'=>'egypt'],[]);we($default['cash']['from'],date('Y-m-01'));we($default['cash']['accounts'][1]['available_balance'],'17.30');we(isset($default['cash']['accounts'][1]['period_in']),true);$html=(new OfficeExpenseWorkspacePage())->render($state,'synthetic');we(str_contains($html,'Future dated · not available yet'),true);we(str_contains($html,'Available as of Dolibarr date'),true);}finally{$db->query('DELETE FROM llx_bank WHERE rowid=501');}
});
wt('advance return form renders a linked branch advance rather than failing its select options',function()use($m){
    foreach(['ar','en']as$lang){$state=(new OfficeExpenseWorkspaceController($m,'synthetic'))->handle('GET',['view'=>'advances','kind'=>'advance_return','office'=>'egypt','lang'=>$lang],[]);we($state['status'],200);$html=(new OfficeExpenseWorkspacePage())->render($state,'synthetic');we(str_contains($html,'name="payload[settles_ref]"'),true);we(str_contains($html,'value="USD"'),true);}
});
wt('native cash period reporting separates opening balances from cash in and out without mixed currencies',function()use($m){
    $cash=$m->cash('egypt','2001-01-02','2001-01-02');$eg=$cash['accounts'][0];we($eg['period_opening'],'0.00');we($eg['period_initial'],'123.45');we($eg['period_in'],'0.00');we($eg['period_out'],'23.15');we($eg['period_closing'],'100.30');we(count($eg['entries']),2);
    $later=$m->cash('egypt','2001-01-03','2001-01-03')['accounts'][0];we($later['period_opening'],'100.30');we($later['period_initial'],'0.00');we($later['period_closing'],'100.30');we($later['entries'],[]);
    wb(fn()=>$m->cash('egypt','2001-02-30','2001-03-01'));wb(fn()=>$m->cash('egypt','2001-01-03','2001-01-02'));
});
wt('report export honors branch treasury scope and never exposes cash to an own-record worker',function()use($m,$db,$conf){
    $report=$m->report('egypt','2001-01-02','2001-01-02');we($report['cash']['accounts'][0]['period_initial'],'123.45');$csv=OfficeExpenseWorkspaceService::cashCsv($report);we(str_contains($csv,'CPO-EG-EGP'),true);we(str_contains($csv,'CPO-EG-USD'),true);we(str_contains($csv,'CPO-CN-CNY'),false);
    $user=m119FetchIdentity('synthetic-13');$worker=new OfficeExpenseWorkspaceService($db,$user,1,new OfficeExpenseStaffAccess($db,$user,$conf,7));we($worker->report('egypt','2001-01-02','2001-01-02')['cash'],null);
    $q=['view'=>'reports','office'=>'egypt','from'=>'2001-01-02','to'=>'2001-01-02','export'=>'cash_csv'];we((new OfficeExpenseWorkspaceController($m,'synthetic'))->handle('GET',$q,[])['status'],200);$denied=(new OfficeExpenseWorkspaceController($worker,'synthetic'))->handle('GET',$q,[]);we($denied['status'],403);we($denied['csv'],null);we($denied['report'],null);
});
wt('receipt register filters own authorized rows and retains stable reference and original currencies',function()use($m,$r,$receipt){
    $controller=new OfficeExpenseWorkspaceController($m,'receipt-token');$q=['view'=>'receipts','office'=>'egypt','lang'=>'ar','from'=>'2026-11-01','to'=>'2026-11-30','status'=>'all','search'=>$receipt['id']];$state=$controller->handle('GET',$q,[]);we($state['status'],200);we(count($state['rows']),1);we($state['rows'][0]['id'],$receipt['id']);
    $html=(new OfficeExpenseWorkspacePage())->render($state,'receipt-next');we(str_contains($html,'receipt-brand'),true);we(str_contains($html,'receipt-metrics'),true);we(str_contains($html,'receipt-filter'),true);we(str_contains($html,'REC-'.strtoupper($receipt['id'])),true);we(str_contains($html,'type="checkbox" name="deposit_to_safe"'),false);
    we($controller->handle('GET',array_replace($q,['from'=>'2026-02-30']),[])['status'],422);we($controller->handle('GET',array_replace($q,['status'=>'posted']),[])['status'],200);wb(fn()=>$r->receiptOwnerName('egypt',$receipt['id']));
});
wt('receipt print uses three A4 copies cut lines scoped receiver local QR and exact words',function()use($m,$receipt){
    foreach(['ar','en']as$lang){$state=(new OfficeExpenseWorkspaceController($m,'receipt-token'))->handle('GET',['view'=>'receipts','office'=>'egypt','lang'=>$lang,'edit'=>$receipt['id'],'print'=>'1'],[]);we($state['status'],200);$state['selected']['content']['notes']='SYNTHETIC receipt notes';$state['receipt_link']='https://example.invalid/custom/chinaplanetofficeexpenses/workspace.php?office=egypt&view=receipts&edit='.$receipt['id'].'&print=1';$html=(new OfficeExpenseWorkspacePage())->render($state,'receipt-next');we(substr_count($html,'class="receipt-slip"'),3);we(substr_count($html,'class="receipt-cut-line"'),2);we(substr_count($html,'class="receipt-qr-box"'),3);we(str_contains($html,'api.qrserver.com'),false);we(str_contains($html,'=SYNTHETIC formula'),true);$escape=$state;$escape['selected']['content']['payer']='<script>test</script>';we(str_contains((new OfficeExpenseWorkspacePage())->render($escape,'receipt-next'),'&lt;script&gt;test&lt;/script&gt;'),true);we(str_contains($html,'class="receipt-slip-status"'),true);
        $dir=getenv('M119_WORKSPACE_VISUAL_DIR');if($dir){file_put_contents($dir.'/'.$lang.'-receipt-redesign-a4.html','<!doctype html><html lang="'.$lang.'" dir="'.($lang==='ar'?'rtl':'ltr').'"><head><meta charset="utf-8"><title>SYNTHETIC receipt print QA</title><link rel="stylesheet" href="/custom/chinaplanetofficeexpenses/assets/fontawesome.css"><link rel="stylesheet" href="/custom/chinaplanetofficeexpenses/assets/fontawesome-solid.css"><link rel="stylesheet" href="/custom/chinaplanetofficeexpenses/employee.css"><link rel="stylesheet" href="/custom/chinaplanetofficeexpenses/workspace.css"><script src="/custom/chinaplanetofficeexpenses/workspace.js" defer></script></head><body>'.$html.'</body></html>');}
    }
    $long=$state;$long['selected']['content']['notes']=str_repeat('تفاصيل اختبار فقط ',100).'END-OF-LONG-NOTES';$longHtml=(new OfficeExpenseWorkspacePage())->render($long,'receipt-next');we(substr_count($longHtml,'END-OF-LONG-NOTES'),3);$dir=getenv('M119_WORKSPACE_VISUAL_DIR');if($dir)file_put_contents($dir.'/en-receipt-redesign-long.html',preg_replace('/<body>.*<\/body>/s','<body>'.$longHtml.'</body>',file_get_contents($dir.'/en-receipt-redesign-a4.html')));
    $words=new ReflectionMethod(OfficeExpenseWorkspacePage::class,'receiptWords');we($words->invoke(null,'500.00','USD',true),'فقط خمسمائة دولار أمريكي لا غير');we(str_contains($words->invoke(null,'999999999999.01','CNY',true),'01/100'),true);we($words->invoke(null,'0.05','EGP',false),'zero Egyptian pounds and 05/100 only');
});
wt('receipt QR corrects only the exact trusted staging alias and retains other deployment roots',function(){we(OfficeExpenseWorkspaceController::receiptBaseUrl('https://staging-erp.visiontrading.biz/'),'https://backup-erp.visiontrading.biz');we(OfficeExpenseWorkspaceController::receiptBaseUrl('https://staging-erp.visiontrading.biz/dolibarr/'),'https://backup-erp.visiontrading.biz/dolibarr');we(OfficeExpenseWorkspaceController::receiptBaseUrl('https://erp.visiontrading.biz/'),'https://erp.visiontrading.biz');we(OfficeExpenseWorkspaceController::receiptBaseUrl('https://staging-erp.visiontrading.biz.example.invalid'),'https://staging-erp.visiontrading.biz.example.invalid');});
wt('receipt save and print share existing CSRF idempotency and branch controls',function()use($m,$rp){
    $c=new OfficeExpenseWorkspaceController($m,'receipt-token');$post=['view'=>'receipts','office'=>'egypt','lang'=>'ar','action'=>'save','id'=>'','revision'=>'0','submission_key'=>'receipt-redesign-save','payload'=>$rp,'token'=>'receipt-token','print_after'=>'1'];$result=$c->handle('POST',[],$post);we($result['status'],303);parse_str(parse_url($result['redirect'],PHP_URL_QUERY),$q);we($q['print'],'1');we($c->handle('GET',$q,[])['print'],true);we($c->handle('POST',[],array_replace($post,['office'=>'china']))['status'],403);we($c->handle('POST',[],array_replace($post,['print_after'=>'bad']))['status'],422);
});
wt('financial revisions enforce submit approve post and immutable native-operation retries',function()use($db,$m){
    $row=$m->save('egypt','deposit',['amount'=>'0.01','currency'=>'USD','date'=>'2026-11-02','source'=>'SYNTHETIC','purpose'=>'SYNTHETIC financial state test','notes'=>''],'finance-state-create');
    $store=new OfficeExpenseWorkspaceStore($db);$calls=0;$check=static function($r){if($r['office']!=='egypt')throw new DomainException('Branch denied.');};
    $apply=static function($r){$v=$r['content'];$v['_finance']['checked']=true;return $v;};
    wb(fn()=>$store->financial(1,8,$row['id'],1,'post','state-post-before',[],$check,$apply));
    $row=$store->financial(1,8,$row['id'],1,'submit','state-submit',[],$check,$apply);we($row['status'],'submitted');
    wb(fn()=>$m->change('egypt','deposit',$row['id'],2,'archive'));
    $row=$store->financial(1,8,$row['id'],2,'approve','state-approve',[],$check,$apply);we($row['status'],'approved');
    $post=function($r)use(&$calls){$calls++;$v=$r['content'];$v['_finance']['native_ids']=['synthetic'=>1];return $v;};
    $row=$store->financial(1,8,$row['id'],3,'post','state-post',['amount'=>'0.01'],$check,$post);we($row['status'],'posted');we($calls,1);
    $retry=$store->financial(1,8,$row['id'],3,'post','state-post',['amount'=>'0.01'],$check,$post);we($retry['revision'],4);we($calls,1);
    wb(fn()=>$store->financial(1,8,$row['id'],3,'post','state-post',['amount'=>'0.02'],$check,$post));
    wb(fn()=>$store->financial(1,7,$row['id'],3,'post','state-post',['amount'=>'0.01'],$check,$post));
    wb(fn()=>$store->financial(1,8,$row['id'],4,'post','different-post',[],$check,$post));
    $row=$store->financial(1,8,$row['id'],4,'reverse','state-reverse',[],$check,$apply);we($row['status'],'reversed');
    wb(fn()=>$m->change('egypt','deposit',$row['id'],5,'archive'));we($db->transaction_opened,0);
});
wt('financial native effects and state roll back together on audit or source tampering failure',function()use($db,$m){
    $row=$m->save('egypt','deposit',['amount'=>'0.01','currency'=>'USD','date'=>'2026-11-02','source'=>'SYNTHETIC','purpose'=>'SYNTHETIC rollback test','notes'=>''],'finance-rollback-create');
    $store=new OfficeExpenseWorkspaceStore($db);$check=static function($r){};$apply=static function($r){$v=$r['content'];$v['_finance']=[];return $v;};
    $row=$store->financial(1,8,$row['id'],1,'submit','rollback-submit',[],$check,$apply);$row=$store->financial(1,8,$row['id'],2,'approve','rollback-approve',[],$check,$apply);
    $db->db->exec("CREATE TRIGGER synthetic_finance_audit_failure BEFORE INSERT ON llx_cpo_oe_workspace_audit BEGIN SELECT RAISE(ABORT,'SYNTHETIC'); END");
    $failed=false;try{$store->financial(1,8,$row['id'],3,'post','rollback-post',[],$check,function($r)use($db){$db->query("INSERT INTO llx_bank(rowid,dateo,datev,fk_account,amount,fk_type,label) VALUES(991,'2026-11-02','2026-11-02',102,'0.01','LIQ','SYNTHETIC atomic callback')");$v=$r['content'];$v['_finance']['bank_id']=991;return $v;});}catch(RuntimeException$e){$failed=true;}finally{$db->db->exec('DROP TRIGGER synthetic_finance_audit_failure');}
    we($failed,true);we($store->find(1,$row['id'])['status'],'approved');we($db->fetch_object($db->query('SELECT rowid FROM llx_bank WHERE rowid=991')),false);
    $failed=false;try{$store->financial(1,8,$row['id'],3,'post','tamper-post',[],$check,function($r){$v=$r['content'];$v['amount']='9.00';return $v;});}catch(RuntimeException$e){$failed=true;}we($failed,true);we($store->find(1,$row['id'])['status'],'approved');we($db->transaction_opened,0);
});
wt('workspace evidence reuses scoped private storage with retry actor audit and immutable submission',function()use($db,$m,$r,$a){
    require_once dirname(__DIR__,2).'/custom/chinaplanetofficeexpenses/class/OfficeExpenseWorkspaceEvidence.php';
    $root=sys_get_temp_dir().'/m119-workspace-evidence-'.bin2hex(random_bytes(8));mkdir($root,0700);file_put_contents($root.'/.cpo-office-expenses-private','cpo-office-expenses-private-v1');chmod($root.'/.cpo-office-expenses-private',0600);
    $source=$m->save('egypt','expense',['amount'=>'1.00','currency'=>'USD','date'=>'2026-11-02','category_ref'=>'','purpose'=>'SYNTHETIC evidence parent','source_ref'=>'SYNTHETIC','notes'=>''],'workspace-evidence-parent');
    $service=new OfficeExpenseWorkspaceEvidence($db,$m,1,8,$root,DOL_DOCUMENT_ROOT);$other=new OfficeExpenseWorkspaceEvidence($db,$r,1,12,$root,DOL_DOCUMENT_ROOT);
    $png=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
    try{
        $file=$service->attach('egypt','expense',$source['id'],'workspace-evidence-key','proof.png',$png);we($service->attach('egypt','expense',$source['id'],'workspace-evidence-key','proof.png',$png)['id'],$file['id']);we(count($service->list('egypt','expense',$source['id'])),1);
        we($service->download('egypt','expense',$source['id'],$file['id'])['bytes'],$png);wb(fn()=>$other->download('egypt','expense',$source['id'],$file['id']));
        wb(fn()=>$service->attach('egypt','expense',$source['id'],'workspace-evidence-key','changed.png',$png));
        $audit=$db->fetch_object($db->query("SELECT fk_actor FROM llx_cpo_oe_workspace_audit WHERE record_ref='".$file['id']."' AND action='download'"));we((int)$audit->fk_actor,8);
        $store=new OfficeExpenseWorkspaceStore($db);$store->financial(1,8,$source['id'],1,'submit','evidence-submit',[],static function($row){},static function($row){$v=$row['content'];$v['_finance']=[];return $v;});
        wb(fn()=>$service->attach('egypt','expense',$source['id'],'submitted-file','later.png',$png));we($service->download('egypt','expense',$source['id'],$file['id'])['bytes'],$png);
        $path=$root.'/workspace/1/egypt/'.$source['id'].'/'.$file['id'].'.blob';file_put_contents($path,'SYNTHETIC corrupt');$failed=false;try{$service->download('egypt','expense',$source['id'],$file['id']);}catch(RuntimeException$e){$failed=true;}we($failed,true);
    }finally{ $iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);foreach($iterator as$item){if($item->isDir())rmdir($item->getPathname());else unlink($item->getPathname());}rmdir($root); }
});
wt('financial authority is explicit branch-scoped and configuration never enables native accounting',function()use($db,$conf,$owner,$egypt,$china){
    require_once dirname(__DIR__,2).'/custom/chinaplanetofficeexpenses/class/OfficeExpenseFinanceSettings.php';
    wb(fn()=>$egypt->assertFinancial('egypt','expenses','post',['post'=>[]]));$egypt->assertFinancial('egypt','expenses','post',['post'=>[8]]);
    wb(fn()=>$china->assertFinancial('egypt','expenses','post',['post'=>[12]]));we($owner->isFinanceAdministrator(),true);we($egypt->isFinanceAdministrator(),false);
    $setup=new OfficeExpenseFinanceSettings($db,$conf,$owner);$default=$setup->load('egypt');we($default['content']['enabled'],false);we($setup->metadata()['enabled'],false);
    $input=['enabled'=>'0','base_currency'=>$conf->currency,'roles'=>['approve'=>['8'],'post'=>['8'],'reverse'=>[]],'accounts'=>array_fill_keys(OfficeExpenseFinanceSettings::ROLES,'0'),'money'=>[],'category_accounts'=>[],'journal_id'=>'0','expense_journal_id'=>'0','fx_policy'=>'','require_evidence'=>'1','expense_type_id'=>'0','offset_payment_id'=>'0','expense_tax_mode'=>'','customer_mode'=>'selected','customer_ids'=>[],'invoice_ids'=>[],'adjustment_journal_id'=>'0','opening_journal_id'=>'0'];
    $saved=$setup->save('egypt',$input,'finance-config-staged',0);we($saved['status'],'active');we($saved['content']['roles']['post'],[8]);we($saved['content']['enabled'],false);we(empty($conf->accounting->enabled),true);
    $manager=new OfficeExpenseFinanceSettings($db,$conf,$egypt);wb(fn()=>$manager->save('egypt',$input,'manager-config-denied',1));
    $bad=$input;$bad['roles']['post']=['13'];wb(fn()=>$setup->save('egypt',$bad,'own-worker-finance-denied',1));
    $bad=$input;$bad['enabled']='1';wb(fn()=>$setup->save('egypt',$bad,'missing-core-finance-denied',1));
    we($setup->load('egypt')['revision'],1);
});
wt('scoped navigation and default entry never require a legacy write grant or offer denied sections',function()use($db,$conf,$owner){
    require_once dirname(__DIR__,2).'/custom/chinaplanetofficeexpenses/class/OfficeExpenseWorkspaceRuntime.php';
    $db->query("INSERT INTO llx_user(rowid,entity,login,lastname,statut,fk_soc) VALUES(15,1,'synthetic-readonly-accountant','SYNTHETIC',1,0)");$db->query('INSERT INTO llx_user_rights(entity,fk_user,fk_id) VALUES(1,15,500603)');
    $owner->assign(15,'china',['reports'=>['read']],'own',0);$user=m119FetchIdentity('synthetic-readonly-accountant');$access=new OfficeExpenseStaffAccess($db,$user,$conf,7);$service=new OfficeExpenseWorkspaceService($db,$user,1,$access);
    we($access->navigationViews('china'),['reports']);$landing=OfficeExpenseWorkspaceRuntime::landing($access,['office'=>'china','lang'=>'en'],'ar');we($landing,'workspace.php?lang=en&office=china&view=reports');
    we(OfficeExpenseWorkspaceRuntime::landing($access,['view'=>'expenses'],'en'),null);wb(fn()=>OfficeExpenseWorkspaceRuntime::landing($access,['office'=>'egypt'],'en'));
    $state=(new OfficeExpenseWorkspaceController($service,'synthetic'))->handle('GET',['office'=>'china','view'=>'reports','lang'=>'en'],[]);we($state['status'],200);$html=(new OfficeExpenseWorkspacePage())->render($state,'synthetic');
    foreach(['treasury','receipts','employees','settings']as$view)we(str_contains($html,'&amp;view='.$view.'"'),false);
    we((new OfficeExpenseWorkspaceController($service,'synthetic'))->handle('GET',['office'=>'china','view'=>'treasury'],[])['status'],403);
});
wt('financial pages expose explicit confirmation and never render private posting metadata or editable approved sources',function()use($db,$m){
    $payload=['amount'=>'2.50','currency'=>'EGP','date'=>'2026-11-02','category_ref'=>'','purpose'=>'SYNTHETIC finance UI','source_ref'=>'SYNTHETIC','notes'=>''];$r=$m->save('egypt','expense',$payload,'financial-page-source');$store=new OfficeExpenseWorkspaceStore($db);
    $intent=OfficeExpenseFinancialPlan::intent($r,['date'=>'2026-11-02','staff_id'=>'8'],'EGP','2026-11-02');$plan=OfficeExpenseFinancialPlan::build($r,$intent);
    $apply=static function($row)use($plan){$v=$row['content'];$v['_finance']=['plan'=>$plan,'proof'=>[],'source_hash'=>'synthetic','private_canary'=>'DO-NOT-PRINT-PRIVATE-FINANCE'];return $v;};$check=static function(){};
    $r=$store->financial(1,8,$r['id'],1,'submit','page-submit-01',[],$check,$apply);$r=$store->financial(1,8,$r['id'],2,'approve','page-approve-01',[],$check,$apply);
    wb(fn()=>$m->save('egypt','expense',$payload,'approved-ui-edit',$r['id'],$r['revision']));
    foreach(['ar','en']as$lang){$state=(new OfficeExpenseWorkspaceController($m,'synthetic'))->handle('GET',['view'=>'expenses','office'=>'egypt','lang'=>$lang,'edit'=>$r['id']],[]);we($state['status'],200);
        $state['finance']=['enabled'=>true,'base'=>'EGP','revision'=>1,'administrator'=>false,'evidence_available'=>true,'evidence'=>[],'submit'=>true,'confirmation'=>'synthetic-confirmation','permissions'=>['approve'=>true,'post'=>true,'reverse'=>false],'staff'=>[],'customers'=>[]];
        $html=(new OfficeExpenseWorkspacePage())->render($state,'synthetic-next');we(str_contains($html,'name="action" value="post"'),true);we(str_contains($html,'name="payload[confirmation]" value="synthetic-confirmation"'),true);we(str_contains($html,'id="workspace-form"'),false);we(str_contains($html,'DO-NOT-PRINT-PRIVATE-FINANCE'),false);
        $state['finance']['permissions']['post']=false;$denied=(new OfficeExpenseWorkspacePage())->render($state,'synthetic');we(str_contains($denied,'name="action" value="post"'),false);
        $state['print']=true;$printed=(new OfficeExpenseWorkspacePage())->render($state,'synthetic');we(str_contains($printed,'DO-NOT-PRINT-PRIVATE-FINANCE'),false);
        $dir=getenv('M119_WORKSPACE_VISUAL_DIR');if($dir)file_put_contents($dir.'/'.$lang.'-financial-approval.html','<!doctype html><html lang="'.$lang.'" dir="'.($lang==='ar'?'rtl':'ltr').'"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>SYNTHETIC financial approval</title><link rel="stylesheet" href="/custom/chinaplanetofficeexpenses/employee.css"><link rel="stylesheet" href="/custom/chinaplanetofficeexpenses/workspace.css"></head><body>'.$html.'</body></html>');
    }
    $rejected=$store->financial(1,8,$r['id'],3,'reject','page-reject-01',['reason'=>'SYNTHETIC correction'],$check,$apply);$updated=$m->save('egypt','expense',$payload,'rejected-ui-edit',$r['id'],$rejected['revision']);we(count($updated['content']['_finance']['operations']),3);we(isset($updated['content']['_finance']['plan']),false);
    wb(fn()=>$store->financial(1,8,$r['id'],2,'reject','page-reject-01',['reason'=>'SYNTHETIC correction'],$check,$apply));
});
wt('financial staff subject binds once and own recipients read their manager-prepared record without inheriting edit authority',function()use($db,$conf,$owner,$m){
    $membership=(new OfficeExpenseNativeAccessStore($db))->find(1,13,'egypt');$grants=$membership['grants'];$grants['expenses']=['read','create','update','delete'];$owner->assign(13,'egypt',$grants,'own',$membership['revision']);
    $u=m119FetchIdentity('synthetic-13');$worker=new OfficeExpenseWorkspaceService($db,$u,1,new OfficeExpenseStaffAccess($db,$u,$conf,7));
    $p=['amount'=>'1.00','currency'=>'USD','date'=>'2026-11-02','category_ref'=>'','purpose'=>'SYNTHETIC manager prepared own recipient','source_ref'=>'SYNTHETIC','notes'=>''];$r=$m->save('egypt','expense',$p,'manager-recipient-source');$store=new OfficeExpenseWorkspaceStore($db);$check=static function(){};
    $apply=static function($old){$v=$old['content'];$v['_finance']=['intent'=>['staff_id'=>13]];return $v;};$r=$store->financial(1,8,$r['id'],1,'submit','bind-native-subject',[],$check,$apply);we($r['subject'],13);we($worker->get('egypt','expense',$r['id'])['id'],$r['id']);we(in_array($r['id'],array_column($worker->list('egypt','expense'),'id'),true),true);we($worker->canPrepareFinance($r),false);wb(fn()=>$worker->save('egypt','expense',$p,'recipient-owner-edit',$r['id'],2));
    $r=$store->financial(1,8,$r['id'],2,'reject','bind-native-reject',[],$check,$apply);$edited=$m->save('egypt','expense',$p,'manager-recipient-edit',$r['id'],3);we($edited['subject'],13);we($worker->get('egypt','expense',$r['id'])['subject'],13);$different=static function($old){$v=$old['content'];$v['_finance']=['intent'=>['staff_id'=>8]];return $v;};wb(fn()=>$store->financial(1,8,$r['id'],4,'submit','native-subject-reassign',[],$check,$different));
    $db->query("INSERT INTO llx_user(rowid,entity,login,lastname,statut,fk_soc) VALUES(16,1,'synthetic-16','Other worker',1,0)");foreach([500601,500603]as$right)$db->query("INSERT INTO llx_user_rights(entity,fk_user,fk_id) VALUES(1,16,$right)");$owner->assign(16,'egypt',['expenses'=>['read','create']],'own',0);$other=m119FetchIdentity('synthetic-16');$otherService=new OfficeExpenseWorkspaceService($db,$other,1,new OfficeExpenseStaffAccess($db,$other,$conf,7));wb(fn()=>$otherService->get('egypt','expense',$r['id']));we(in_array($r['id'],array_column($otherService->list('egypt','expense'),'id'),true),false);
});
echo "$pass passed, $fail failed\n";exit($fail?1:0);
