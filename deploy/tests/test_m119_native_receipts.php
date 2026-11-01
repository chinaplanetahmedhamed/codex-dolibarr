<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||$argc!==2||!is_file($argv[1].'/user/class/user.class.php')){fwrite(STDERR,"Usage: php test_m119_native_receipts.php <native-htdocs>\n");exit(1);}
$module=dirname(__DIR__,2).'/custom/chinaplanetoperations/office_expenses';
if(!is_file($module.'/class/OfficeExpenseNativeReceiptStore.php')){fwrite(STDERR,"FAIL native receipt adapter is missing\n");exit(1);}
require __DIR__.'/fixtures/m119_native_bootstrap.php';require __DIR__.'/fixtures/m119_native_identity.php';
require $module.'/class/OfficeExpenseNativeReceiptStore.php';require $module.'/class/OfficeExpenseNativeStorageFactory.php';
require $module.'/class/OfficeExpenseEmployeeController.php';require __DIR__.'/fixtures/m119_native_migration.php';
require __DIR__.'/fixtures/m119_native_receipts.php';
$passed=0;$failed=0;
function ncheck($a,$b): void {if($a!==$b)throw new RuntimeException('Expected '.var_export($b,true).' got '.var_export($a,true));}
function nreject(callable $f,string $type=DomainException::class): void {try{$f();}catch(Throwable $e){if($e instanceof $type)return;throw $e;}throw new RuntimeException('Expected '.$type);}
function ntest(string $name,callable $f): void {global $passed,$failed;try{$f();echo "PASS $name\n";$passed++;}catch(Throwable $e){$failed++;fwrite(STDERR,"FAIL $name: ".$e->getMessage()."\n");}}

ntest('receipt stage requires ready unchanged draft artifact and has independent immutable marker',function(){
    global $db;m119IdentityDatabase();$backend=new M119MigrationBackend($db);$stage=OfficeExpenseNativeSchemaMigration::forReceipts($backend);
    nreject(function()use($stage){$stage->plan();});$drafts=new OfficeExpenseNativeSchemaMigration($backend);$hash=$drafts->plan()['manifest_hash'];$drafts->install($hash);
    ncheck($stage->plan()['state'],'empty');$stage->install($stage->plan()['manifest_hash']);ncheck($stage->plan()['state'],'installed');ncheck($drafts->plan()['manifest_hash'],$hash);ncheck($drafts->plan()['state'],'installed');
    ncheck((int)$db->db->querySingle('SELECT COUNT(*) FROM llx_cpo_oe_schema'),2);$before=$backend->ddlCount;$stage->install($stage->plan()['manifest_hash']);ncheck($backend->ddlCount,$before);
});
ntest('partial receipt migration stops independently without changing ready draft storage',function(){
    global $db;m119IdentityDatabase();$backend=new M119MigrationBackend($db);$drafts=new OfficeExpenseNativeSchemaMigration($backend);$drafts->install($drafts->plan()['manifest_hash']);$stage=OfficeExpenseNativeSchemaMigration::forReceipts($backend);$hash=$stage->plan()['manifest_hash'];$backend->failAt=$backend->ddlCount+2;
    nreject(function()use($stage,$hash){$stage->install($hash);},RuntimeException::class);
    $fresh=new M119MigrationBackend(new M119NativeSqlite($db->database_name));$retry=OfficeExpenseNativeSchemaMigration::forReceipts($fresh);
    ncheck($retry->plan()['state'],'partial');ncheck((new OfficeExpenseNativeSchemaMigration($fresh))->plan()['state'],'installed');
    nreject(function()use($retry,$hash){$retry->install($hash);});ncheck($fresh->ddlCount,0);
});
ntest('native receipt round-trips private original preview and exact scoped source with audited reads',function(){
    $c=nsetup();$draft=ndraft($c);$meta=$c['receipts']->attach($draft['id'],'synthetic-native-receipt','中国.png',nimage());
    ncheck(count($c['receipts']->listOwn($draft['id'])),1);ncheck($c['receipts']->read($draft['id'],$meta['id'])['bytes'],nimage());
    ncheck($c['receipts']->read($draft['id'],$meta['id'],'preview')['mime'],'application/octet-stream');ncheck(count($c['receipts']->auditOwn($draft['id'])),3);
    ncheck($c['drafts']->getOwn($draft['id'])['amount'],'180.29');ncheck(ncount($c,'expensereport'),0);ncheck($c['receipts']->inspectOwn($draft['id'])['files'],2);
});
ntest('slow receipt persistence crossing a clock second retains matching creation evidence',function(){
    $c=nsetup();$draft=ndraft($c);$c['backend']->beforeReceiptInsert=static function(){usleep(1100000);};
    $meta=$c['receipts']->attach($draft['id'],'synthetic-slow-native-receipt','a.png',nimage());ncheck($c['receipts']->read($draft['id'],$meta['id'])['bytes'],nimage());ncheck(ncount($c,'cpo_oe_receipt'),1);
});
ntest('same-key retry and fresh connection recover one receipt audit and two original files',function(){
    $c=nsetup();$draft=ndraft($c);$meta=$c['receipts']->attach($draft['id'],'synthetic-native-retry','a.png',nimage());
    ncheck($c['receipts']->attach($draft['id'],'synthetic-native-retry','a.png',nimage()),$meta);
    $fresh=nreopen($c);ncheck($fresh['receipts']->attach($draft['id'],'synthetic-native-retry','a.png',nimage()),$meta);
    ncheck(ncount($fresh,'cpo_oe_receipt'),1);ncheck(ncount($fresh,'cpo_oe_receipt_audit'),1);ncheck($fresh['receipts']->inspectOwn($draft['id'])['files'],2);
});
ntest('changed receipt intent conflicts without replacing original or adding audit',function(){
    $c=nsetup();$d=ndraft($c);$m=$c['receipts']->attach($d['id'],'synthetic-native-conflict','a.png',nimage());
    nreject(function()use($c,$d){$c['receipts']->attach($d['id'],'synthetic-native-conflict','b.png',nimage());},OfficeExpenseReceiptConflict::class);
    ncheck($c['receipts']->read($d['id'],$m['id'])['bytes'],nimage());ncheck(ncount($c,'cpo_oe_receipt'),1);
});
ntest('different employee entity or office and revoked rights cannot access native receipts',function(){
    $c=nsetup();$d=ndraft($c);$m=$c['receipts']->attach($d['id'],'synthetic-native-scope','a.png',nimage());
    foreach(array('employee','entity','office','revoked') as $scope){$r=nrestricted($c,$scope);foreach(array('list','read','attach','audit','inspect') as $action)nreject(function()use($r,$d,$m,$action){
        if($action==='list')$r->listOwn($d['id']);elseif($action==='read')$r->read($d['id'],$m['id']);elseif($action==='attach')$r->attach($d['id'],'synthetic-forbidden-key','b.png',nimage());elseif($action==='audit')$r->auditOwn($d['id']);else$r->inspectOwn($d['id']);
    });}
    ncheck(ncount($c,'cpo_oe_receipt'),1);ncheck(ncount($c,'cpo_oe_receipt_audit'),1);
});
ntest('receipt from another own draft and arbitrary path or variant cannot be read',function(){
    $c=nsetup();$d=ndraft($c);$m=$c['receipts']->attach($d['id'],'synthetic-native-bound','a.png',nimage());$other=ndraft($c,'synthetic-other-source');
    nreject(function()use($c,$other,$m){$c['receipts']->read($other['id'],$m['id']);});
    nreject(function()use($c,$d){$c['receipts']->read($d['id'],'../../private');});
    nreject(function()use($c,$d,$m){$c['receipts']->read($d['id'],$m['id'],'../../original');},InvalidArgumentException::class);
});
ntest('native source connection mismatch and altered trusted source snapshots are rejected',function(){
    $c=nsetup();$d=ndraft($c);$other=new M119MigrationBackend(new M119NativeSqlite($c['db']->database_name));
    nreject(function()use($c,$other){new OfficeExpenseNativeReceiptStore($other,$c['sources'],$c['root'],$c['web']);},InvalidArgumentException::class);
    $forged=$d;$forged['office']='egypt';nreject(function()use($c,$forged){$c['store']->listForDraft($forged);});ncheck(ncount($c,'cpo_oe_receipt'),0);
});
ntest('domain still rejects spoofed formats paths excessive pixels and compressed bytes before native writes',function(){
    $c=nsetup();$d=ndraft($c);foreach(array(array('../a.png',nimage()),array('a.jpg',nimage()),array('a.svg','<svg/>'),array('a.png','not an image')) as $bad)nreject(function()use($c,$d,$bad){$c['receipts']->attach($d['id'],'synthetic-bad-receipt',$bad[0],$bad[1]);},InvalidArgumentException::class);
    $small=new OfficeExpenseReceiptService($c['drafts'],$c['store'],array('max_bytes'=>4));nreject(function()use($small,$d){$small->attach($d['id'],'synthetic-too-large','a.png',nimage());},InvalidArgumentException::class);
    $pixels=new OfficeExpenseReceiptService($c['drafts'],$c['store'],array('max_pixels'=>1));nreject(function()use($pixels,$d){$pixels->attach($d['id'],'synthetic-too-many-pixels','a.png',nimage());},InvalidArgumentException::class);
    ncheck(ncount($c,'cpo_oe_receipt'),0);ncheck($c['receipts']->inspectOwn($d['id'])['files'],0);
});
ntest('private root marker web-root separation and symlinks are validated without automatic provisioning',function(){
    $c=nsetup();foreach(array($c['web'],$c['root'].'/missing') as $root)nreject(function()use($c,$root){new OfficeExpenseNativeReceiptStore($c['backend'],$c['sources'],$root,$c['web']);},InvalidArgumentException::class);
    $link=$c['base'].'/alias';symlink($c['root'],$link);nreject(function()use($c,$link){new OfficeExpenseNativeReceiptStore($c['backend'],$c['sources'],$link,$c['web']);},InvalidArgumentException::class);
    $d=ndraft($c);rename($c['root'],$c['base'].'/moved');symlink($c['base'].'/moved',$c['root']);
    nreject(function()use($c,$d){$c['receipts']->attach($d['id'],'synthetic-root-replaced','a.png',nimage());},RuntimeException::class);ncheck(ncount($c,'cpo_oe_receipt'),0);
});
ntest('count and combined-byte quotas include private orphans without deleting them',function(){
    $c=nsetup(1);$d=ndraft($c);$c['receipts']->attach($d['id'],'synthetic-first-file','a.png',nimage());
    nreject(function()use($c,$d){$c['receipts']->attach($d['id'],'synthetic-second-file','b.png',nimage());},OfficeExpenseReceiptQuota::class);
    $other=ndraft($c,'synthetic-orphan-source');$dir=nprivateDir($c,$other,true);file_put_contents($dir.'/'.str_repeat('a',32).'.original',nimage());chmod($dir.'/'.str_repeat('a',32).'.original',0600);
    nreject(function()use($c,$other){$c['receipts']->attach($other['id'],'synthetic-orphan-block','b.png',nimage());},OfficeExpenseReceiptQuota::class);ncheck($c['receipts']->inspectOwn($other['id'])['orphan_files'],1);
});
ntest('byte quota counts original plus preview and leaves no files on rejection',function(){
    $c=nsetup(10,strlen(nimage()));$d=ndraft($c);nreject(function()use($c,$d){$c['receipts']->attach($d['id'],'synthetic-combined-bytes','a.png',nimage());},OfficeExpenseReceiptQuota::class);ncheck(ncount($c,'cpo_oe_receipt'),0);ncheck($c['receipts']->inspectOwn($d['id'])['files'],0);
});
ntest('metadata source creation audit missing or altered file evidence fails closed',function(){
    foreach(array('metadata','source','audit','file') as $case){$c=nsetup();$d=ndraft($c);$m=$c['receipts']->attach($d['id'],'synthetic-drift-file','a.png',nimage());
        if($case==='metadata')$c['db']->db->exec("UPDATE llx_cpo_oe_receipt SET document_json='42'");
        elseif($case==='source')$c['db']->db->exec("UPDATE llx_cpo_oe_draft SET office='egypt'");
        elseif($case==='audit')$c['db']->db->exec('DELETE FROM llx_cpo_oe_receipt_audit');
        else file_put_contents(nprivateDir($c,$d).'/'.$m['id'].'.original','corrupt');
        nreject(function()use($c,$d,$m){$c['receipts']->read($d['id'],$m['id']);},RuntimeException::class);
    }
});
ntest('metadata or creation-audit failure rolls back and cleans only the attempt-owned files',function(){
    foreach(array('cpo_oe_receipt','cpo_oe_receipt_audit') as $table){$c=nsetup();$d=ndraft($c);$c['db']->db->exec("CREATE TRIGGER receipt_fail BEFORE INSERT ON llx_".$table." BEGIN SELECT RAISE(ABORT,'synthetic failure'); END");
        nreject(function()use($c,$d){$c['receipts']->attach($d['id'],'synthetic-failure-file','a.png',nimage());},RuntimeException::class);
        ncheck(ncount($c,'cpo_oe_receipt'),0);ncheck(ncount($c,'cpo_oe_receipt_audit'),0);ncheck($c['receipts']->inspectOwn($d['id'])['files'],0);
    }
});
ntest('false or lost commit never reports success and preserves files for fresh reconciliation',function(){
    foreach(array('false','lost') as $mode){$c=nsetup();$d=ndraft($c);$c['backend']->receiptCommitMode=$mode;
        nreject(function()use($c,$d){$c['receipts']->attach($d['id'],'synthetic-uncertain-commit','a.png',nimage());},RuntimeException::class);
        $fresh=nreopen($c);$usage=$fresh['receipts']->inspectOwn($d['id']);ncheck($usage['files'],2);
        if($mode==='lost'){$m=$fresh['receipts']->attach($d['id'],'synthetic-uncertain-commit','a.png',nimage());ncheck($usage['receipt_count'],1);ncheck(ncount($fresh,'cpo_oe_receipt'),1);}
        else{ncheck($usage['receipt_count'],0);ncheck($usage['orphan_files'],2);}
    }
});
ntest('unconfirmed rollback retains files and quarantines shared draft and receipt adapters',function(){
    $c=nsetup();$d=ndraft($c);$c['backend']->receiptCommitMode='rollback_failed';
    try{
        nreject(function()use($c,$d){$c['receipts']->attach($d['id'],'synthetic-failed-rollback','a.png',nimage());},RuntimeException::class);
        nreject(function()use($c){$c['drafts']->listOwn();},RuntimeException::class);
        nreject(function()use($c,$d){$c['receipts']->listOwn($d['id']);},RuntimeException::class);
        ncheck(count(glob(nprivateDir($c,$d).'/*.original')),1);
    }finally{$c['db']->db->exec('ROLLBACK');$c['db']->transaction_opened=0;}
});
ntest('access-audit failure or unconfirmed commit releases no receipt bytes',function(){
    foreach(array('audit','false','lost') as $case){$c=nsetup();$d=ndraft($c);$m=$c['receipts']->attach($d['id'],'synthetic-read-evidence','a.png',nimage());
        if($case==='audit')$c['db']->db->exec("CREATE TRIGGER access_fail BEFORE INSERT ON llx_cpo_oe_receipt_audit WHEN NEW.event='receipt_access_started' BEGIN SELECT RAISE(IGNORE); END");else$c['backend']->receiptCommitMode=$case;
        nreject(function()use($c,$d,$m){$c['receipts']->read($d['id'],$m['id']);},RuntimeException::class);ncheck(file_get_contents(nprivateDir($c,$d).'/'.$m['id'].'.original'),nimage());
    }
});
ntest('caller transaction is preserved and source-row lock precedes receipt insertion',function(){
    $c=nsetup();$d=ndraft($c);$c['db']->begin();nreject(function()use($c,$d){$c['receipts']->attach($d['id'],'synthetic-nested-file','a.png',nimage());},LogicException::class);ncheck($c['db']->transaction_opened,1);$c['db']->rollback();
    $c['backend']->receiptTrace=array();$c['receipts']->attach($d['id'],'synthetic-source-lock','a.png',nimage());
    ncheck(array_search('source_lock',$c['backend']->receiptTrace,true)<array_search('receipt_insert',$c['backend']->receiptTrace,true),true);
    ncheck(array_search('source_lock',$c['backend']->receiptTrace,true)<array_search('receipt_metadata_read',$c['backend']->receiptTrace,true),true);
});
ntest('unwired native storage factory binds exact native context and feeds draft receipt controller flow',function(){
    $c=nsetup();$factory=nbind($c);$drafts=$factory->draftFactory($c['backend'],$c['user'],$c['conf']);$receipts=$factory->receiptFactory($c['backend'],$c['user'],$c['conf'],$drafts);
    $uploads=new M119NativeReceiptUpload();$controller=new OfficeExpenseEmployeeController($drafts,'token','next','en',$receipts,$uploads);
    $payload=npayload();$post=array_merge($payload,array('action'=>'save','token'=>'token','submission_key'=>'synthetic-native-ui'));unset($post['quote']);$post['quote_rate']='7.25';$post['quote_date']='2026-11-01';$post['quote_source']='SYNTHETIC estimate';
    $saved=$controller->handle('POST',array(),$post);ncheck($saved['status'],303);parse_str(parse_url($saved['redirect'],PHP_URL_QUERY),$q);$id=$q['saved'];
    $denied=$controller->handle('POST',array(),array('action'=>'attach_receipt','token'=>'wrong','draft_id'=>$id,'receipt_key'=>'synthetic-native-ui-file'),array('receipt'=>array('name'=>'a.png','bytes'=>nimage())));ncheck($denied['status'],403);ncheck($uploads->reads,0);ncheck(ncount($c,'cpo_oe_receipt'),0);
    $attached=$controller->handle('POST',array(),array('action'=>'attach_receipt','token'=>'token','draft_id'=>$id,'receipt_key'=>'synthetic-native-ui-file'),array('receipt'=>array('name'=>'a.png','bytes'=>nimage())));ncheck($attached['status'],303);
    $m=$receipts->listOwn($id)[0];$read=$controller->handle('POST',array(),array('action'=>'download_receipt','token'=>'token','draft_id'=>$id,'receipt_id'=>$m['id'],'variant'=>'original'));ncheck($read['download']['bytes'],nimage());ncheck(ncount($c,'expensereport'),0);
});
ntest('factory refuses changed identity entity database service or missing schema without installing anything',function(){
    $c=nsetup();$f=nbind($c);$d=$f->draftFactory($c['backend'],$c['user'],$c['conf']);
    nreject(function()use($c,$f){$f->draftFactory($c['backend'],clone $c['user'],$c['conf']);},DomainException::class);
    $different=new M119MigrationBackend(new M119NativeSqlite($c['db']->database_name));nreject(function()use($c,$f,$different){$f->draftFactory($different,$c['user'],$c['conf']);},DomainException::class);
    $c['backend']->selectedDatabase='different_synthetic_database';nreject(function()use($c,$f){$f->draftFactory($c['backend'],$c['user'],$c['conf']);},DomainException::class);$c['backend']->selectedDatabase=$c['db']->database_name;
    $c['conf']->entity=2;nreject(function()use($c,$f){$f->draftFactory($c['backend'],$c['user'],$c['conf']);},DomainException::class);$c['conf']->entity=1;
    $c['conf']->currency='CNY';nreject(function()use($c,$f){$f->draftFactory($c['backend'],$c['user'],$c['conf']);},DomainException::class);$c['conf']->currency='EGP';
    nreject(function()use($c,$f){$f->receiptFactory($c['backend'],$c['user'],$c['conf'],$c['drafts']);},DomainException::class);
    $before=$c['backend']->ddlCount;$c['db']->db->exec('DROP TABLE llx_cpo_oe_receipt');nreject(function()use($c){nbind($c);});ncheck($c['backend']->ddlCount,$before);
});
ntest('factory rejects the same native User object reassigned to another employee',function(){
    $c=nsetup();$f=nbind($c);$id=$c['user']->id;$c['user']->id=$c['other']->id;
    try{nreject(function()use($c,$f){$f->draftFactory($c['backend'],$c['user'],$c['conf']);});}finally{$c['user']->id=$id;}
    ncheck(ncount($c,'cpo_oe_receipt'),0);
});
ntest('a previously bound employee service cannot read another employees draft or receipt after User reuse',function(){
    $c=nsetup();$otherDrafts=new OfficeExpenseDraftService($c['sources'],new OfficeExpenseMoney(),$c['other'],1,array('china'=>array('CNY')),'EGP');$otherReceipts=new OfficeExpenseReceiptService($otherDrafts,$c['store']);
    $d=$otherDrafts->submit(npayload(),'synthetic-other-employee-source');$m=$otherReceipts->attach($d['id'],'synthetic-other-employee-receipt','a.png',nimage());$old=$c['user']->id;$c['user']->id=$c['other']->id;
    try{
        nreject(function()use($c,$d){$c['drafts']->getOwn($d['id']);});
        nreject(function()use($c,$d,$m){$c['receipts']->read($d['id'],$m['id']);});
        nreject(function()use($c){$c['drafts']->listOwn();});
        nreject(function()use($c){$c['drafts']->submit(npayload(),'synthetic-reused-user-must-not-save');});
        ncheck($c['drafts']->usesNativeContext($c['user'],1),false);
    }finally{$c['user']->id=$old;}
    ncheck(ncount($c,'cpo_oe_receipt_audit'),1);ncheck(ncount($c,'cpo_oe_draft'),1);
});
echo "$passed passed, $failed failed; native receipt metadata/private files with synthetic DB only; no server or financial posting\n";exit($failed?1:0);
