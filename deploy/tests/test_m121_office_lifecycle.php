<?php
declare(strict_types=1);
// State/authority/replay acceptance only. Callbacks never call native payment APIs.
if(PHP_SAPI!=='cli'||$argc!==2)exit(2);
require __DIR__.'/fixtures/m119_native_bootstrap.php';
require __DIR__.'/fixtures/m119_native_identity.php';
$module=dirname(__DIR__,2).'/custom/chinaplanetofficeexpenses';
require $module.'/class/OfficeExpenseNativeSchemaMigration.php';
require $module.'/class/OfficeExpenseWorkspaceService.php';
require $module.'/class/OfficeExpenseFinancialPlan.php';
require __DIR__.'/fixtures/m119_native_migration.php';
[$db]=m119IdentityDatabase();$conf->chinaplanetofficeexpenses=(object)['enabled'=>1];$conf->modules['chinaplanetofficeexpenses']=1;
$backend=new M119MigrationBackend($db);
foreach([new OfficeExpenseNativeSchemaMigration($backend),OfficeExpenseNativeSchemaMigration::forAccess($backend),OfficeExpenseNativeSchemaMigration::forWorkspace($backend)]as$m)$m->install($m->plan()['manifest_hash']);
$db->query('UPDATE llx_user SET admin=1 WHERE rowid=7');
$db->query("INSERT INTO llx_user(rowid,entity,login,lastname,statut,fk_soc) VALUES(12,1,'synthetic-worker','SYNTHETIC',1,0)");
foreach([500601=>'draft',500603=>'read']as$id=>$right){
    $db->query("INSERT INTO llx_rights_def(id,entity,module,perms) VALUES($id,1,'chinaplanetofficeexpenses','$right')");
    foreach([8,12]as$actor)$db->query("INSERT INTO llx_user_rights(entity,fk_user,fk_id) VALUES(1,$actor,$id)");
}
$owner=new OfficeExpenseStaffAccess($db,m119FetchIdentity('synthetic-7'),$conf,7);
$managerUser=m119FetchIdentity('synthetic-8');$workerUser=m119FetchIdentity('synthetic-worker');
$manager=new OfficeExpenseStaffAccess($db,$managerUser,$conf,7);$worker=new OfficeExpenseStaffAccess($db,$workerUser,$conf,7);
$staff=new OfficeExpenseWorkspaceService($db,$workerUser,1,$worker);$store=new OfficeExpenseWorkspaceStore($db);
foreach(['egypt','china']as$office){
    $owner->assign(8,$office,OfficeExpenseStaffAccess::ACTIONS,'branch',0);
    $owner->assign(12,$office,['expenses'=>['read','create','update'],'advances'=>['read','create','update']],'own',0);
}
$pass=0;$fail=0;
function lc(string $name,callable $f):void{global$pass,$fail;try{$f();$pass++;echo 'PASS '.$name."\n";}catch(Throwable$e){$fail++;fwrite(STDERR,'FAIL '.$name.': '.$e->getMessage()."\n");}}
function le($a,$b):void{if($a!==$b)throw new RuntimeException(var_export([$a,$b],true));}
function ld(callable $f):void{try{$f();}catch(DomainException|InvalidArgumentException$e){return;}throw new RuntimeException('Expected domain refusal.');}
$roles=['approve'=>[8],'post'=>[8],'reverse'=>[8]];
foreach(['egypt'=>['EGP','120.50','0.08'],'china'=>['CNY','88.20','0.14']]as$office=>[$currency,$amount,$rate])foreach(['expense','advance']as$kind){
    $prefix=$office.'-'.$kind;$row=null;$payload=['amount'=>$amount,'currency'=>$currency,'date'=>'2026-11-03','purpose'=>'SYNTHETIC lifecycle','source_ref'=>'SYNTHETIC','notes'=>''];
    if($kind==='expense')$payload['category_ref']='';
    $counts=['submit'=>0,'approve'=>0,'reject'=>0,'post'=>0,'reverse'=>0];
    $run=static function(array $r,int $expected,string $action,string $key,array $input=[],bool $asWorker=false)use($office,$kind,$rate,$staff,$store,$manager,$worker,$roles,&$counts):array{
        return $store->financial(1,$asWorker?12:8,$r['id'],$expected,$action,$key,$input,
            static function($old)use($office,$kind,$action,$asWorker,$staff,$manager,$worker,$roles){
                if($old['office']!==$office||$old['kind']!==$kind)throw new DomainException('Scope mismatch.');
                if($action==='submit'){if(!$staff->canPrepareFinance($old))throw new DomainException('Staff cannot submit.');}
                else ($asWorker?$worker:$manager)->assertFinancial($office,OfficeExpenseWorkspaceDocument::SECTIONS[$kind],$action==='reject'?'approve':$action,$roles);
            },
            static function($old)use($action,$input,$rate,&$counts){
                $counts[$action]++;$v=$old['content'];$f=$v['_finance']??[];
                if($action==='submit'){
                    $intent=OfficeExpenseFinancialPlan::intent($old,['date'=>'2026-11-03','rate'=>$rate,'rate_date'=>'2026-11-03','rate_source'=>'SYNTHETIC reviewed quote','setup_revision'=>'1','staff_id'=>'12'],'USD','2026-11-03');
                    $f=['operations'=>$f['operations']??[],'intent'=>$intent,'plan'=>OfficeExpenseFinancialPlan::build($old,$intent)];
                }elseif($action==='reject')$f['rejection_reason']=$input['reason'];
                elseif($action==='reverse')$f['reversal_plan']=OfficeExpenseFinancialPlan::reverse($f['plan'],'2026-11-03','2026-11-03');
                elseif($action==='approve')$f['approved_hash']='SYNTHETIC callback marker';
                // No bank, payment, salary or bookkeeping row is written by these callbacks.
                $v['_finance']=$f;return $v;
            });
    };
    lc($prefix.' draft retry keeps one source and original currency',function()use(&$row,$staff,$office,$kind,$payload,$prefix){
        $row=$staff->save($office,$kind,$payload,$prefix.'-draft');le($row['status'],'draft');
        le($staff->save($office,$kind,$payload,$prefix.'-draft')['id'],$row['id']);le(count($staff->list($office,$kind)),1);
        ld(fn()=>$staff->save($office,$kind,array_replace($payload,['amount'=>'1.00']),$prefix.'-draft'));
        ld(fn()=>$staff->save($office,$kind,array_replace($payload,['currency'=>$office==='egypt'?'CNY':'EGP']),$prefix.'-forged'));
        le($row['content']['currency'],$payload['currency']);
    });
    lc($prefix.' staff submit binds subject and freezes submitted edits',function()use(&$row,$run,$staff,$office,$kind,$payload,$prefix){
        $row=$run($row,1,'submit',$prefix.'-submit',[],true);le($row['status'],'submitted');le($row['subject'],12);
        ld(fn()=>$staff->save($office,$kind,$payload,$prefix.'-frozen',$row['id'],2));
        ld(fn()=>$run($row,2,'approve',$prefix.'-worker-approval',[],true));
        ld(fn()=>$run($row,2,'post',$prefix.'-worker-post',[],true));
    });
    lc($prefix.' rejection revision retains operation audit and invalidates approval',function()use(&$row,$run,$staff,$office,$kind,$payload,$prefix){
        $row=$run($row,2,'reject',$prefix.'-reject',['reason'=>'SYNTHETIC correction']);le($row['status'],'rejected');
        $row=$staff->save($office,$kind,array_replace($payload,['notes'=>'SYNTHETIC corrected']),$prefix.'-revision',$row['id'],3);
        le($row['status'],'rejected');le($row['revision'],4);le(count($row['content']['_finance']['operations']),2);
        le(isset($row['content']['_finance']['approved_hash']),false);
        $row=$run($row,4,'submit',$prefix.'-resubmit',[],true);le($row['status'],'submitted');le($row['subject'],12);
        le(isset($row['content']['_finance']['rejection_reason']),false);
        ld(fn()=>$run($row,4,'submit',$prefix.'-stale-new-submit',[],true));
    });
    lc($prefix.' explicit manager approves; one callback on identical posting retry',function()use(&$row,$run,$counts,$staff,$office,$kind,$prefix){
        $row=$run($row,5,'approve',$prefix.'-approve');le($row['status'],'approved');
        $row=$run($row,6,'post',$prefix.'-post');le($row['status'],'posted');
        le($run($row,6,'post',$prefix.'-post'),$row);
        ld(fn()=>$run($row,6,'post',$prefix.'-post',['changed'=>'intent']));
        ld(fn()=>$run($row,7,'post',$prefix.'-second-post'));
        ld(fn()=>$staff->change($office,$kind,$row['id'],7,'archive'));
        le($row['office'],$office);le($row['content']['_finance']['plan']['currency'],$row['content']['currency']);
    });
    lc($prefix.' reversal inverts recorded valuation once and retains original source',function()use(&$row,$run,$prefix,$payload,&$counts){
        ld(fn()=>$run($row,7,'reverse',$prefix.'-worker-reverse',[],true));
        $original=$row['content'];$row=$run($row,7,'reverse',$prefix.'-reverse');le($row['status'],'reversed');
        le($run($row,7,'reverse',$prefix.'-reverse'),$row);le($counts['post'],1);le($counts['reverse'],1);
        le($row['content']['amount'],$payload['amount']);le($row['content']['currency'],$payload['currency']);
        le($row['content']['_finance']['plan'],$original['_finance']['plan']);
        le($row['content']['_finance']['reversal_plan']['cash_amount'],$payload['amount']);
        le($row['content']['_finance']['reversal_plan']['valuation'],$original['_finance']['plan']['valuation']);
    });
    lc($prefix.' fresh connection preserves source and immutable transition audit',function()use($db,$row,$staff,$office,$kind){
        $fresh=new OfficeExpenseWorkspaceStore(new M119NativeSqlite($db->database_name));le($fresh->find(1,$row['id']),$row);
        le(array_column($staff->history($office,$kind,$row['id']),'action'),['create','submit','reject','update','submit','approve','post','reverse']);
        le($db->transaction_opened,0);
    });
}
lc('revoked manager cannot recover an old financial acknowledgement',function()use($owner,$manager,$roles,$run,$row){
    $owner->revoke(8,'china',1);ld(fn()=>$manager->assertFinancial('china','expenses','post',$roles));
    ld(fn()=>$run($row,7,'reverse','china-advance-reverse'));
});
echo "$pass passed, $fail failed (state callbacks only; no native financial posting)\n";exit($fail?1:0);
