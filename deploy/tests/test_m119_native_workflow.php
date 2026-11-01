<?php
declare(strict_types=1);
// Credential-free native-library acceptance. No main.inc, sessions, server or real configuration.
if (PHP_SAPI !== 'cli' || $argc!==2 || !is_file($argv[1].'/user/class/user.class.php')) {
    fwrite(STDERR,"Usage: php test_m119_native_workflow.php /path/to/dolibarr/htdocs\n");exit(1);
}
require_once __DIR__.'/fixtures/m119_native_bootstrap.php';
require_once __DIR__.'/fixtures/m119_native_identity.php';
require_once dirname(__DIR__).'/tools/office_expenses/LocalDraftStore.php';
require_once dirname(__DIR__).'/tools/office_expenses/LocalNativeDraftWriter.php';
require_once dirname(__DIR__,2).'/custom/chinaplanetoperations/office_expenses/class/OfficeExpenseEmployeeController.php';
$passed=0;$failed=0;
function wcheck($actual,$expected): void {
    if ($actual!==$expected) throw new RuntimeException('Expected '.var_export($expected,true).' got '.var_export($actual,true));
}
function wreject(callable $action): void {
    try {$action();}catch(DomainException $error){return;}throw new RuntimeException('Expected scoped workflow rejection.');
}
function wtest(string $name,callable $action): void {
    global $passed,$failed;
    try {$action();echo "PASS $name\n";$passed++;}
    catch(Throwable $error){fwrite(STDERR,"FAIL $name: ".$error->getMessage()."\n");$failed++;}
}
function wservice(PDO $pdo,User $identity,array $offices=array('egypt'=>array('EGP'),'china'=>array('CNY'))): OfficeExpenseDraftService {
    global $conf;
    return new OfficeExpenseDraftService(new LocalDraftStore($pdo),new OfficeExpenseMoney(),$identity,(int)$conf->entity,$offices,$conf->currency);
}
function wsetup(string $currency='EGP',int $entity=1): array {
    global $db,$user;
    m119IdentityDatabase(false,$currency,$entity);
    $user=m119FetchIdentity($entity===1?'synthetic-7':'synthetic-11',$entity);
    $path=DOL_DATA_ROOT.'/workflow_'.bin2hex(random_bytes(6)).'.sqlite';
    $pdo=new PDO('sqlite:'.$path);
    return array($db,$pdo,wservice($pdo,$user),$path);
}
function wbridge(OfficeExpenseDraftService $service): OfficeExpenseNativeDraftBridge {
    global $db,$user,$conf;
    return new OfficeExpenseNativeDraftBridge($service,new LocalNativeDraftWriter($db,$user,$conf,new OfficeExpenseMoney()));
}
function wpayload(string $currency='EGP',string $amount='700.10'): array {
    return array('kind'=>'expense_claim','office'=>$currency==='EGP'?'egypt':'china','amount'=>$amount,'currency'=>$currency,
        'occurred_on'=>'2026-10-31','source_ref'=>'SYNTHETIC إيصال 中国','purpose'=>'SYNTHETIC office supplies');
}
function wpost(array $payload,string $key): array {
    // Fixed boundary values, not authentication/session credentials; never used to log in.
    return array_merge($payload,array('action'=>'save','token'=>'synthetic-request-boundary','submission_key'=>$key));
}
function wcontroller(OfficeExpenseDraftService $service): OfficeExpenseEmployeeController {
    return new OfficeExpenseEmployeeController($service,'synthetic-request-boundary','synthetic-form-boundary');
}
function wcount(string $table): int {
    global $db;return (int)$db->fetch_object($db->query('SELECT COUNT(*) n FROM llx_'.$table))->n;
}

wtest('native identity fixture contains no passwords reset secrets API keys or PHP sessions',function(){
    global $db,$user;
    list($db,$secret)=m119IdentityDatabase();wcheck($secret,null);
    $row=$db->fetch_object($db->query("SELECT COUNT(*) n FROM llx_user WHERE COALESCE(pass,'')<>'' OR COALESCE(pass_crypted,'')<>'' OR COALESCE(pass_temp,'')<>'' OR COALESCE(api_key,'')<>''"));
    wcheck((int)$row->n,0);wcheck(session_status(),PHP_SESSION_NONE);
    wcheck(glob(DOL_DATA_ROOT.'/sess_*'),array());
    $user=m119FetchIdentity('synthetic-7');wcheck($user->id,7);wcheck((int)$user->hasRight('expensereport','creer'),1);
});
wtest('controller retry after reopening storage returns the same native draft and one audit event',function(){
    global $user;
    list($db,$pdo,$service,$path)=wsetup();$post=wpost(wpayload(),'synthetic-durable-retry');
    $first=wcontroller($service)->handle('POST',array(),$post);wcheck($first['status'],303);
    $draft=$service->listOwn()[0];$link=wbridge($service)->createDraft($draft['id'],7,'explicit_zero_tax');
    $user=m119FetchIdentity('synthetic-7');$reopened=wservice(new PDO('sqlite:'.$path),$user);
    $retry=wcontroller($reopened)->handle('POST',array(),$post);wcheck($retry['redirect'],$first['redirect']);
    wcheck(wbridge($reopened)->createDraft($draft['id'],7,'explicit_zero_tax'),$link);
    wcheck(count($reopened->listOwn()),1);wcheck((int)$pdo->query('SELECT COUNT(*) FROM oe_audit')->fetchColumn(),1);
    wcheck(wcount('expensereport'),1);wcheck(wcount('expensereport_det'),1);wcheck(wcount('oe_native_draft'),1);
});
wtest('changed amount or currency on a retried form conflicts without additional draft or audit',function(){
    list($db,$pdo,$service)=wsetup();$post=wpost(wpayload(),'synthetic-change-retry');
    wcheck(wcontroller($service)->handle('POST',array(),$post)['status'],303);
    $post['amount']='700.11';wcheck(wcontroller($service)->handle('POST',array(),$post)['status'],409);
    $post=array_merge(wpost(wpayload('CNY','180.29'),'synthetic-change-retry'),array('quote_rate'=>'7.25','quote_date'=>'2026-10-31','quote_source'=>'SYNTHETIC quote'));
    wcheck(wcontroller($service)->handle('POST',array(),$post)['status'],409);
    wcheck(count($service->listOwn()),1);wcheck((int)$pdo->query('SELECT COUNT(*) FROM oe_audit')->fetchColumn(),1);wcheck(wcount('expensereport'),0);
});
wtest('fresh native rights revocation blocks saved-draft retry before any native creation',function(){
    global $db,$user;
    list($db,$pdo,$service,$path)=wsetup();$post=wpost(wpayload(),'synthetic-revoked-retry');
    wcheck(wcontroller($service)->handle('POST',array(),$post)['status'],303);$draft=$service->listOwn()[0];
    $db->query('DELETE FROM llx_user_rights WHERE fk_user=7');$user=m119FetchIdentity('synthetic-7');
    $fresh=wservice(new PDO('sqlite:'.$path),$user);
    wcheck(wcontroller($fresh)->handle('POST',array(),$post)['status'],403);
    wreject(function()use($fresh,$draft){wbridge($fresh)->createDraft($draft['id'],7,'explicit_zero_tax');});
    wcheck(wcount('expensereport'),0);wcheck((int)$pdo->query('SELECT COUNT(*) FROM oe_draft')->fetchColumn(),1);
});
wtest('same key for another native employee stays separate and cannot read the first draft',function(){
    global $db,$user;
    list($db,$pdo,$service)=wsetup();$first=$service->submit(wpayload(),'synthetic-owner-key');
    $db->query('INSERT INTO llx_user_rights(entity,fk_user,fk_id) VALUES(1,8,90001)');
    $user=m119FetchIdentity('synthetic-8');$other=wservice($pdo,$user);
    wreject(function()use($other,$first){$other->getOwn($first['id']);});
    $second=$other->submit(wpayload(),'synthetic-owner-key');wcheck($second['owner_id'],8);wcheck($second['id']===$first['id'],false);
    wcheck(count($other->listOwn()),1);$link=wbridge($other)->createDraft($second['id'],7,'explicit_zero_tax');
    $row=$db->fetch_object($db->query('SELECT fk_user_author FROM llx_expensereport WHERE rowid='.(int)$link['expense_report_id']));wcheck((int)$row->fk_user_author,8);
});
wtest('CNY entity and actual native employee persist exact original cents with identity conversion',function(){
    list($db,$pdo,$service)=wsetup('CNY',2);$draft=$service->submit(wpayload('CNY','180.29'),'synthetic-china-native');
    wcheck($draft['owner_id'],11);wcheck($draft['conversion']['base_amount'],'180.29');
    $link=wbridge($service)->createDraft($draft['id'],7,'explicit_zero_tax');wcheck($link['entity'],2);wcheck($link['native_currency'],'CNY');wcheck($link['amount'],'180.29');
    wcheck($link['paid'],false);wcheck($link['status'],'draft');
});
wtest('foreign original and quote remain exact in Egypt but cannot become a misleading native draft',function(){
    list($db,$pdo,$service)=wsetup();$payload=wpayload('CNY','180.29');
    $payload['quote']=array('rate'=>'7.25','date'=>'2026-10-31','source'=>'SYNTHETIC illustrative quote');
    $draft=$service->submit($payload,'synthetic-foreign-gate');
    wcheck($service->getOwn($draft['id'])['amount'],'180.29');wcheck($draft['conversion']['rate'],'7.25000000');wcheck($draft['conversion']['base_amount'],'1307.10');
    wreject(function()use($service,$draft){wbridge($service)->createDraft($draft['id'],7,'explicit_zero_tax');});
    wcheck(wcount('expensereport'),0);wcheck(wcount('oe_native_draft'),0);
});
wtest('advances returns transfers and reimbursements never enter native expense reports',function(){
    list($db,$pdo,$service)=wsetup();
    foreach(array('advance','advance_return','transfer','reimbursement') as $kind){
        $payload=wpayload();$payload['kind']=$kind;
        if(in_array($kind,array('advance_return','reimbursement'),true))$payload['settles_ref']='SYNTHETIC unverified settlement';
        if($kind==='transfer'){$payload['from_account_ref']='SYNTHETIC cash';$payload['destination']=array('amount'=>'700.10','currency'=>'EGP','account_ref'=>'SYNTHETIC bank');}
        $draft=$service->submit($payload,'synthetic-movement-'.$kind);
        wreject(function()use($service,$draft){wbridge($service)->createDraft($draft['id'],7,'explicit_zero_tax');});
    }
    wcheck(count($service->listOwn()),4);wcheck(wcount('expensereport'),0);wcheck(wcount('oe_native_draft'),0);
});
wtest('lost link evidence blocks automatic duplication of an existing native draft',function(){
    global $db;
    list($db,$pdo,$service)=wsetup();$draft=$service->submit(wpayload(),'synthetic-lost-link');
    wbridge($service)->createDraft($draft['id'],7,'explicit_zero_tax');
    $db->query('DELETE FROM llx_oe_native_draft'); // Failure simulation in this owned disposable database only.
    wreject(function()use($service,$draft){wbridge($service)->createDraft($draft['id'],7,'explicit_zero_tax');});
    wcheck(wcount('expensereport'),1);wcheck(wcount('expensereport_det'),1);wcheck(wcount('oe_native_draft'),0);
});
wtest('changed native draft lifecycle state blocks retry rather than creating another record',function(){
    global $db;
    foreach(array('fk_statut=2','paid=1') as $mutation){
        list($db,$pdo,$service)=wsetup();$draft=$service->submit(wpayload(),'synthetic-state-drift');
        wbridge($service)->createDraft($draft['id'],7,'explicit_zero_tax');
        $db->query('UPDATE llx_expensereport SET '.$mutation); // Simulated row drift; no approval/payment API is called.
        wreject(function()use($service,$draft){wbridge($service)->createDraft($draft['id'],7,'explicit_zero_tax');});
        wcheck(wcount('expensereport'),1);wcheck(wcount('expensereport_det'),1);
    }
});
echo "$passed passed, $failed failed; credential-free native-library workflow; full native HTTP/MariaDB acceptance pending\n";
exit($failed?1:0);
