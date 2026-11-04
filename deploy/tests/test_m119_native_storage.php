<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli'||$argc!==2||!is_file($argv[1].'/user/class/user.class.php')) {
    fwrite(STDERR,"Usage: php test_m119_native_storage.php /path/to/dolibarr/htdocs\n");exit(1);
}
$module=dirname(__DIR__,2).'/custom/chinaplanetofficeexpenses';
if (!is_file($module.'/class/OfficeExpenseNativeDraftStore.php')) {
    fwrite(STDERR,"FAIL native operational draft storage is missing\n");exit(1);
}
require __DIR__.'/fixtures/m119_native_bootstrap.php';
require __DIR__.'/fixtures/m119_native_identity.php';
require $module.'/class/OfficeExpenseNativeDraftStore.php';
require __DIR__.'/fixtures/m119_native_storage_faults.php';
if(is_file($module.'/class/OfficeExpenseNativeConnection.php'))require_once $module.'/class/OfficeExpenseNativeConnection.php';
$passed=0;$failed=0;
function scheck($actual,$expected): void {if($actual!==$expected)throw new RuntimeException('Expected '.var_export($expected,true).' got '.var_export($actual,true));}
function sreject(callable $action,string $type=DomainException::class): void {try{$action();}catch(Throwable $e){if($e instanceof $type)return;throw $e;}throw new RuntimeException('Expected '.$type);}
function stest(string $name,callable $action): void {global $passed,$failed;try{$action();echo "PASS $name\n";$passed++;}catch(Throwable $e){fwrite(STDERR,"FAIL $name: ".$e->getMessage()."\n");$failed++;}}
function ssetup(): array {
    global $db,$conf,$module;
    m119IdentityDatabase();
    foreach(glob($module.'/schema/*.sql') as $file)foreach(explode(';',preg_replace('/CHARACTER SET ascii COLLATE ascii_bin/','',preg_replace('/--[^\n]*/','',file_get_contents($file)))) as $sql){
        if(trim($sql)!==''&&!$db->query(trim($sql).';'))throw new RuntimeException('Owned draft schema failed: '.basename($file).' '.$db->lasterror());
    }
    $store=new OfficeExpenseNativeDraftStore($db);
    $service=new OfficeExpenseDraftService($store,new OfficeExpenseMoney(),m119FetchIdentity('synthetic-7'),1,array('egypt'=>array('EGP'),'china'=>array('CNY')),'EGP');
    return array($db,$store,$service);
}
function spayload(string $amount='700.10',string $currency='EGP'): array {
    $p=array('kind'=>'expense_claim','office'=>$currency==='EGP'?'egypt':'china','amount'=>$amount,'currency'=>$currency,'occurred_on'=>'2026-11-01','source_ref'=>'SYNTHETIC إيصال 中国','purpose'=>'SYNTHETIC persisted office expense');
    if($currency!=='EGP')$p['quote']=array('rate'=>'7.25','date'=>'2026-11-01','source'=>'SYNTHETIC estimate');return $p;
}
function scount($db,string $table): int {return (int)$db->fetch_object($db->query('SELECT COUNT(*) n FROM llx_'.$table))->n;}
function sfault($db,string $mode): array {
    $fault=new M119NativeStorageFaults($db,$mode);$store=new OfficeExpenseNativeDraftStore($fault);
    $service=new OfficeExpenseDraftService($store,new OfficeExpenseMoney(),m119FetchIdentity('synthetic-7'),1,array('egypt'=>array('EGP')),'EGP');
    return array($fault,$store,$service);
}

stest('native database port persists exact immutable money source and one audited draft',function(){
    list($db,$store,$service)=ssetup();$draft=$service->submit(spayload(),'synthetic-native-storage');
    scheck($draft['amount'],'700.10');scheck($draft['entity'],1);scheck($draft['owner_id'],7);scheck($draft['native_record'],null);
    scheck($service->getOwn($draft['id']),$draft);scheck(scount($db,'cpo_oe_draft'),1);scheck(scount($db,'cpo_oe_draft_audit'),1);
    $audit=$store->auditOwn(1,7,$draft['id']);scheck($audit[0]['event'],'draft_created');scheck($audit[0]['draft_id'],$draft['id']);
});
stest('source and retry remain identical after a fresh database connection',function(){
    list($db,$store,$service)=ssetup();$draft=$service->submit(spayload(),'synthetic-reopen-native');
    $again=new OfficeExpenseNativeDraftStore(new M119NativeSqlite($db->database_name));
    scheck($again->findOwn(1,7,$draft['id']),$draft);
    $intent=$draft;unset($intent['id'],$intent['created_at']);scheck($again->save(1,7,'synthetic-reopen-native',$intent),$draft);
    scheck(scount($db,'cpo_oe_draft'),1);scheck(scount($db,'cpo_oe_draft_audit'),1);
});
stest('changed source on the same native key conflicts without overwriting audit or document',function(){
    list($db,$store,$service)=ssetup();$draft=$service->submit(spayload(),'synthetic-native-conflict');
    sreject(function()use($service){$service->submit(spayload('700.11'),'synthetic-native-conflict');});
    scheck($service->getOwn($draft['id']),$draft);scheck(scount($db,'cpo_oe_draft_audit'),1);
});
stest('native source keys retain case and numeric-leading-zero identity',function(){
    list($db,$store,$service)=ssetup();
    foreach(array('SourceKey123','sourcekey123','0123456789','1234567890') as $key)$service->submit(spayload(),$key);
    scheck(scount($db,'cpo_oe_draft'),4);
});
stest('native draft store isolates source IDs keys and audit by entity and employee',function(){
    list($db,$store,$service)=ssetup();$first=$service->submit(spayload(),'synthetic-shared-source-key');
    $intent=$first;unset($intent['id'],$intent['created_at']);$intent['owner_id']=8;
    $second=$store->save(1,8,'synthetic-shared-source-key',$intent);
    $intent['entity']=2;$third=$store->save(2,8,'synthetic-shared-source-key',$intent);
    scheck($store->findOwn(1,8,$first['id']),null);scheck($store->findOwn(2,7,$first['id']),null);
    scheck(count($store->listOwn(1,7)),1);scheck(count($store->listOwn(1,8)),1);scheck(count($store->listOwn(2,8)),1);
    scheck($store->auditOwn(1,8,$first['id']),array());scheck($second['id']===$third['id'],false);
});
stest('native store preserves foreign originals and quote strings without expense posting',function(){
    list($db,$store,$service)=ssetup();$draft=$service->submit(spayload('180.29','CNY'),'synthetic-source-foreign');
    $read=$service->getOwn($draft['id']);scheck($read['amount'],'180.29');scheck($read['conversion']['rate'],'7.25000000');scheck($read['conversion']['base_amount'],'1307.10');
    scheck(scount($db,'expensereport'),0);scheck(scount($db,'expensereport_det'),0);
});
stest('audit insert failure rolls back native operational source and permits same-key retry',function(){
    list($db,$store,$service)=ssetup();$db->query("CREATE TRIGGER m119_draft_audit_failure BEFORE INSERT ON llx_cpo_oe_draft_audit BEGIN SELECT RAISE(ABORT,'SYNTHETIC audit failure'); END");
    sreject(function()use($service){$service->submit(spayload(),'synthetic-source-rollback');},RuntimeException::class);
    scheck(scount($db,'cpo_oe_draft'),0);scheck(scount($db,'cpo_oe_draft_audit'),0);scheck($db->transaction_opened,0);
    $db->query('DROP TRIGGER m119_draft_audit_failure');$service->submit(spayload(),'synthetic-source-rollback');scheck(scount($db,'cpo_oe_draft'),1);
});
stest('native source integrity drift fails closed before listing or retry',function(){
    list($db,$store,$service)=ssetup();$draft=$service->submit(spayload(),'synthetic-source-drift');
    $changed=$draft;$changed['amount']='701.10';$json=json_encode($changed,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    $db->query("UPDATE llx_cpo_oe_draft SET document_json='".$db->escape($json)."'");
    sreject(function()use($service,$draft){$service->getOwn($draft['id']);},RuntimeException::class);
    sreject(function()use($service){$service->listOwn();},RuntimeException::class);
    sreject(function()use($service){$service->submit(spayload(),'synthetic-source-drift');},RuntimeException::class);
});
stest('native source refuses caller transaction without committing or rolling it back',function(){
    list($db,$store,$service)=ssetup();$db->begin();
    sreject(function()use($service){$service->submit(spayload(),'synthetic-source-nested');},LogicException::class);
    scheck($db->transaction_opened,1);$db->rollback();scheck(scount($db,'cpo_oe_draft'),0);
});
stest('native physical unique constraint prevents another row for the same submission',function(){
    list($db,$store,$service)=ssetup();$service->submit(spayload(),'synthetic-native-unique');
    scheck($db->query("INSERT INTO llx_cpo_oe_draft(entity,fk_user_creat,office,draft_ref,submission_key,payload_hash,document_json,status,date_creation) SELECT entity,fk_user_creat,office,'00000000000000000000000000000000',submission_key,payload_hash,document_json,status,date_creation FROM llx_cpo_oe_draft"),false);
    scheck(scount($db,'cpo_oe_draft'),1);
});
stest('scalar JSON or changed envelope metadata fails closed',function(){
    foreach(array("document_json='42'","office='china'","status='posted'","date_creation='2000-01-01 00:00:00'") as $mutation){
        list($db,$store,$service)=ssetup();$draft=$service->submit(spayload(),'synthetic-envelope-drift');
        $db->query('UPDATE llx_cpo_oe_draft SET '.$mutation);
        sreject(function()use($service,$draft){$service->getOwn($draft['id']);},RuntimeException::class);
    }
});
stest('silently skipped creation audit rolls source back and missing audit blocks reads',function(){
    list($db,$store,$service)=ssetup();$db->query('CREATE TRIGGER m119_skip_audit BEFORE INSERT ON llx_cpo_oe_draft_audit BEGIN SELECT RAISE(IGNORE); END');
    sreject(function()use($service){$service->submit(spayload(),'synthetic-skipped-audit');},RuntimeException::class);
    scheck(scount($db,'cpo_oe_draft'),0);$db->query('DROP TRIGGER m119_skip_audit');
    $draft=$service->submit(spayload(),'synthetic-skipped-audit');$db->query('DELETE FROM llx_cpo_oe_draft_audit');
    sreject(function()use($service,$draft){$service->getOwn($draft['id']);},RuntimeException::class);
});
stest('unconfigured native storage fails closed without silently installing tables',function(){
    global $db;m119IdentityDatabase();$store=new OfficeExpenseNativeDraftStore($db);
    sreject(function()use($store){$store->listOwn(1,7);},RuntimeException::class);
    scheck((int)$db->db->querySingle("SELECT COUNT(*) FROM sqlite_master WHERE name='llx_cpo_oe_draft'"),0);
});
stest('false commit with confirmed rollback reports failure and allows one clean same-key retry',function(){
    list($db)=ssetup();list($fault,$store,$service)=sfault($db,'false_commit');
    sreject(function()use($service){$service->submit(spayload(),'synthetic-false-native-commit');},RuntimeException::class);
    $fresh=new M119NativeSqlite($db->database_name);scheck(scount($fresh,'cpo_oe_draft'),0);scheck(scount($fresh,'cpo_oe_draft_audit'),0);$fresh->close();
    $fault->mode='normal';$draft=$service->submit(spayload(),'synthetic-false-native-commit');
    scheck($service->submit(spayload(),'synthetic-false-native-commit'),$draft);$fresh=new M119NativeSqlite($db->database_name);scheck(scount($fresh,'cpo_oe_draft'),1);scheck(scount($fresh,'cpo_oe_draft_audit'),1);
});
stest('failed rollback cannot expose uncommitted rows as success even when native counter resets',function(){
    list($db)=ssetup();list($fault,$store,$service)=sfault($db,'rollback_cleared_counter');
    try{
        sreject(function()use($service){$service->submit(spayload(),'synthetic-uncertain-native-rollback');},RuntimeException::class);
        $fresh=new M119NativeSqlite($db->database_name);scheck(scount($fresh,'cpo_oe_draft'),0);scheck(scount($fresh,'cpo_oe_draft_audit'),0);
        sreject(function()use($service){$service->listOwn();},RuntimeException::class);
        sreject(function()use($service){$service->submit(spayload(),'synthetic-uncertain-native-rollback');},RuntimeException::class);
        sreject(function()use($store){$store->findOwn(1,7,str_repeat('0',32));},RuntimeException::class);
        sreject(function()use($store){$store->auditOwn(1,7,str_repeat('0',32));},RuntimeException::class);
    }finally{$db->db->exec('ROLLBACK');$db->transaction_opened=0;}
});
stest('unconfirmed rollback is attempted once and never loops or permits later writes',function(){
    list($db)=ssetup();list($fault,$store,$service)=sfault($db,'rollback_stuck');
    try{
        sreject(function()use($service){$service->submit(spayload(),'synthetic-native-stuck-rollback');},RuntimeException::class);
        scheck($fault->rollbackCalls,1);$fault->mode='normal';
        sreject(function()use($service){$service->listOwn();},RuntimeException::class);
    }finally{$db->db->exec('ROLLBACK');$db->transaction_opened=0;}
});
stest('rebuilding an adapter on the same uncertain handle cannot reveal uncommitted source rows',function(){
    list($db)=ssetup();list($fault,$store,$service)=sfault($db,'rollback_cleared_counter');$second=new OfficeExpenseNativeDraftStore($fault);
    try{
        sreject(function()use($service){$service->submit(spayload(),'synthetic-native-same-handle');},RuntimeException::class);
        sreject(function()use($second){$second->listOwn(1,7);},RuntimeException::class);
        sreject(function()use($fault){new OfficeExpenseNativeDraftStore($fault);},RuntimeException::class);
        $fresh=new OfficeExpenseNativeDraftStore(new M119NativeSqlite($db->database_name));scheck($fresh->listOwn(1,7),array());
    }finally{$db->db->exec('ROLLBACK');$db->transaction_opened=0;}
});
stest('draft uncertainty is shared with the native migration connection guard',function(){
    if(!class_exists('OfficeExpenseNativeConnection'))throw new RuntimeException('Shared native connection guard is missing.');
    list($db)=ssetup();list($fault,$store,$service)=sfault($db,'rollback_cleared_counter');$guard=new OfficeExpenseNativeConnection($fault);
    try{
        sreject(function()use($service){$service->submit(spayload(),'synthetic-native-shared-guard');},RuntimeException::class);
        sreject(function()use($guard){$guard->assertUsable();},RuntimeException::class);
        sreject(function()use($fault){new OfficeExpenseNativeConnection($fault);},RuntimeException::class);
    }finally{$db->db->exec('ROLLBACK');$db->transaction_opened=0;}
});
stest('lost response after durable commit requires fresh connection and recovers exactly one source',function(){
    list($db)=ssetup();list($fault,$store,$service)=sfault($db,'lost_commit_response');
    sreject(function()use($service){$service->submit(spayload(),'synthetic-native-lost-commit');},RuntimeException::class);
    sreject(function()use($service){$service->listOwn();},RuntimeException::class);
    $freshDb=new M119NativeSqlite($db->database_name);$fresh=new OfficeExpenseNativeDraftStore($freshDb);
    $records=$fresh->listOwn(1,7);scheck(count($records),1);$draft=$records[0];scheck($draft['amount'],'700.10');
    $intent=$draft;unset($intent['id'],$intent['created_at']);scheck($fresh->save(1,7,'synthetic-native-lost-commit',$intent),$draft);
    scheck(scount($freshDb,'cpo_oe_draft'),1);scheck(scount($freshDb,'cpo_oe_draft_audit'),1);
});
stest('accepted commit that leaves native transaction open never reports saved',function(){
    list($db)=ssetup();list($fault,$store,$service)=sfault($db,'accepted_open_commit');
    try{
        sreject(function()use($service){$service->submit(spayload(),'synthetic-native-open-commit');},RuntimeException::class);
        $fresh=new M119NativeSqlite($db->database_name);scheck(scount($fresh,'cpo_oe_draft'),0);scheck(scount($fresh,'cpo_oe_draft_audit'),0);
    }finally{if($db->transaction_opened>0)$db->rollback();}
});
stest('uncertain begin quarantines the adapter without reading or writing through that connection',function(){
    list($db)=ssetup();list($fault,$store,$service)=sfault($db,'uncertain_begin');
    try{
        sreject(function()use($service){$service->submit(spayload(),'synthetic-native-uncertain-begin');},RuntimeException::class);
        sreject(function()use($service){$service->listOwn();},RuntimeException::class);
        $fresh=new M119NativeSqlite($db->database_name);scheck(scount($fresh,'cpo_oe_draft'),0);
    }finally{if($db->transaction_opened>0)$db->rollback();}
});
stest('native persisted draft and actual User rights govern private receipt reads after fresh revocation',function(){
    global $module;
    require_once $module.'/class/OfficeExpenseReceiptService.php';
    require_once dirname(__DIR__).'/tools/office_expenses/LocalReceiptStore.php';
    list($db,$store,$service)=ssetup();$draft=$service->submit(spayload(),'synthetic-native-receipt-source');
    $base=sys_get_temp_dir().'/m119-native-receipt-'.bin2hex(random_bytes(8));mkdir($base,0700);
    try{
        mkdir($base.'/private',0700);mkdir($base.'/web',0700);file_put_contents($base.'/private/.m119-receipts-synthetic','Synthetic native receipt only');
        $receiptStore=new LocalReceiptStore(new PDO('sqlite:'.$base.'/private/state.sqlite'),$base.'/private',$base.'/web');
        $receipts=new OfficeExpenseReceiptService($service,$receiptStore);
        $image=imagecreatetruecolor(2,2);ob_start();imagepng($image);$bytes=(string)ob_get_clean();imagedestroy($image);
        $meta=$receipts->attach($draft['id'],'synthetic-native-user-receipt','中国.png',$bytes);
        scheck($receipts->read($draft['id'],$meta['id'])['bytes'],$bytes);scheck(count($receipts->auditOwn($draft['id'])),2);
        $db->query('DELETE FROM llx_user_rights WHERE fk_user=7');
        $fresh=new OfficeExpenseDraftService($store,new OfficeExpenseMoney(),m119FetchIdentity('synthetic-7'),1,array('egypt'=>array('EGP')),'EGP');
        $revoked=new OfficeExpenseReceiptService($fresh,$receiptStore);
        sreject(function()use($revoked,$draft,$meta){$revoked->read($draft['id'],$meta['id']);});
        scheck(count($receiptStore->auditForDraft($draft)),2);scheck(scount($db,'expensereport'),0);
    }finally{
        $clean=function($path)use(&$clean){if(is_file($path)||is_link($path)){unlink($path);return;}foreach(scandir($path) as $name)if($name!=='.'&&$name!=='..')$clean($path.'/'.$name);rmdir($path);};
        if(preg_match('/^m119-native-receipt-[a-f0-9]{16}$/D',basename($base))&&is_dir($base))$clean($base);
    }
});
echo "$passed passed, $failed failed; native operational draft storage only; no credentials/services/financial posting\n";exit($failed?1:0);
