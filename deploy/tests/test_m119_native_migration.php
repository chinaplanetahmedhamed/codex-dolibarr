<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||$argc!==2||!is_file($argv[1].'/user/class/user.class.php')){fwrite(STDERR,"Usage: php test_m119_native_migration.php <native-htdocs>\n");exit(1);}
$module=dirname(__DIR__,2).'/custom/chinaplanetofficeexpenses';
if(!is_file($module.'/class/OfficeExpenseNativeSchemaMigration.php')){fwrite(STDERR,"FAIL native migration adapter is missing\n");exit(1);}
require __DIR__.'/fixtures/m119_native_bootstrap.php';require __DIR__.'/fixtures/m119_native_identity.php';
require $module.'/class/OfficeExpenseNativeSchemaMigration.php';require $module.'/class/OfficeExpenseNativeDraftStore.php';
require __DIR__.'/fixtures/m119_native_migration.php';
$passed=0;$failed=0;
function mcheck($a,$b): void {if($a!==$b)throw new RuntimeException('Expected '.var_export($b,true).' got '.var_export($a,true));}
function mreject(callable $f,string $type=RuntimeException::class): void {try{$f();}catch(Throwable $e){if($e instanceof $type)return;throw $e;}throw new RuntimeException('Expected '.$type);}
function mtest(string $name,callable $f): void {global $passed,$failed;try{$f();echo "PASS $name\n";$passed++;}catch(Throwable $e){$failed++;fwrite(STDERR,"FAIL $name: ".$e->getMessage()."\n");}}
function msetup(): array {global $db;m119IdentityDatabase();$backend=new M119MigrationBackend($db);return array($db,$backend,new OfficeExpenseNativeSchemaMigration($backend));}
function mcount($db,string $table): int {return (int)$db->db->querySingle('SELECT COUNT(*) FROM llx_'.$table);}

mtest('planning on a fresh owned database makes no schema or source writes',function(){
    list($db,$backend,$migration)=msetup();$plan=$migration->plan();mcheck($plan['state'],'empty');mcheck(count($plan['statements']),7);mcheck($backend->ddlCount,0);
    mcheck((int)$db->db->querySingle("SELECT COUNT(*) FROM sqlite_master WHERE name LIKE 'llx_cpo_oe_%'"),0);
});
mtest('native schema install is explicitly pinned and exact rerun preserves one marker and own sources',function(){
    list($db,$backend,$migration)=msetup();$hash=$migration->plan()['manifest_hash'];$installed=$migration->install($hash);mcheck($installed['state'],'installed');mcheck($backend->ddlCount,7);mcheck(mcount($db,'cpo_oe_schema'),1);
    $source=new OfficeExpenseDraftService(new OfficeExpenseNativeDraftStore($db),new OfficeExpenseMoney(),m119FetchIdentity('synthetic-7'),1,array('egypt'=>array('EGP'),'china'=>array('CNY')),'EGP');
    $payload=array('kind'=>'expense_claim','office'=>'china','amount'=>'180.29','currency'=>'CNY','occurred_on'=>'2026-11-01','source_ref'=>'SYNTHETIC 中国','purpose'=>'SYNTHETIC migrated native source','quote'=>array('rate'=>'7.25','date'=>'2026-11-01','source'=>'SYNTHETIC estimate'));
    $draft=$source->submit($payload,'synthetic-migrated-source');mcheck($draft['amount'],'180.29');mcheck($draft['conversion']['base_amount'],'1307.10');
    mcheck($migration->install($hash)['state'],'installed');mcheck($backend->ddlCount,7);mcheck(mcount($db,'cpo_oe_schema'),1);mcheck($source->getOwn($draft['id']),$draft);
    $other=new OfficeExpenseDraftService(new OfficeExpenseNativeDraftStore($db),new OfficeExpenseMoney(),m119FetchIdentity('synthetic-11',2),2,array('china'=>array('CNY')),'CNY');
    mreject(function()use($other,$draft){$other->getOwn($draft['id']);},DomainException::class);mcheck(mcount($db,'expensereport'),0);
    global $module;require_once $module.'/class/OfficeExpenseReceiptService.php';require_once dirname(__DIR__).'/tools/office_expenses/LocalReceiptStore.php';
    $root=DOL_DATA_ROOT.'/migrated-receipt-fixture';mkdir($root,0700);mkdir($root.'/private',0700);mkdir($root.'/web',0700);file_put_contents($root.'/private/.m119-receipts-synthetic','Owned migration test only');
    try{
        $receiptStore=new LocalReceiptStore(new PDO('sqlite:'.$root.'/private/state.sqlite'),$root.'/private',$root.'/web');$receipts=new OfficeExpenseReceiptService($source,$receiptStore);
        $image=imagecreatetruecolor(2,2);ob_start();imagepng($image);$bytes=(string)ob_get_clean();imagedestroy($image);
        $meta=$receipts->attach($draft['id'],'synthetic-migrated-receipt','中国.png',$bytes);mcheck($receipts->read($draft['id'],$meta['id'])['bytes'],$bytes);
        $denied=new OfficeExpenseReceiptService($other,$receiptStore);mreject(function()use($denied,$draft,$meta){$denied->read($draft['id'],$meta['id']);},DomainException::class);
        mcheck(count($receipts->auditOwn($draft['id'])),2);mcheck(mcount($db,'expensereport'),0);
    }finally{$clean=function($p)use(&$clean){if(is_file($p)||is_link($p)){unlink($p);return;}foreach(scandir($p) as $n)if($n!=='.'&&$n!=='..')$clean($p.'/'.$n);rmdir($p);};$clean($root);}
});
mtest('stale review hash is refused before locking or executing SQL',function(){
    list($db,$backend,$migration)=msetup();mreject(function()use($migration){$migration->install(str_repeat('0',64));},DomainException::class);mcheck($backend->lockCount,0);mcheck($backend->ddlCount,0);
});
mtest('existing unmarked or partial tables cannot be overwritten or silently adopted',function(){
    list($db,$backend,$migration)=msetup();$plan=$migration->plan();$backend->executeDDL($plan['statements'][2]);$before=$backend->ddlCount;
    mcheck($migration->plan()['state'],'unmarked');mreject(function()use($migration,$plan){$migration->install($plan['manifest_hash']);},DomainException::class);mcheck($backend->ddlCount,$before);
});
mtest('missing unique index and added columns block readiness without modifying schema',function(){
    foreach(array('DROP INDEX uk_cpo_oe_draft_source','ALTER TABLE llx_cpo_oe_draft ADD COLUMN unexpected TEXT') as $mutation){
        list($db,$backend,$migration)=msetup();$hash=$migration->plan()['manifest_hash'];$migration->install($hash);$db->db->exec($mutation);$before=$backend->ddlCount;
        mcheck($migration->plan()['state'],'drift');mreject(function()use($migration){$migration->assertReady();},DomainException::class);
        mreject(function()use($migration,$hash){$migration->install($hash);},DomainException::class);mcheck($backend->ddlCount,$before);
    }
});
mtest('marker hash or state drift blocks use even when physical tables match',function(){
    foreach(array("manifest_hash='".str_repeat('0',64)."'","status='installing'") as $mutation){
        list($db,$backend,$migration)=msetup();$migration->install($migration->plan()['manifest_hash']);$db->db->exec('UPDATE llx_cpo_oe_schema SET '.$mutation);
        mreject(function()use($migration){$migration->assertReady();},DomainException::class);
    }
});
mtest('migration lock contention refuses installation with no changes and locked recheck prevents races',function(){
    list($db,$backend,$migration)=msetup();$hash=$migration->plan()['manifest_hash'];$backend->locked=true;
    mreject(function()use($migration,$hash){$migration->install($hash);},DomainException::class);mcheck($backend->ddlCount,0);
    $backend->locked=false;$backend->onLock=function()use($backend,$migration){$backend->executeDDL($migration->plan()['statements'][0]);};
    mreject(function()use($migration,$hash){$migration->install($hash);},DomainException::class);mcheck($backend->ddlCount,1);
});
mtest('DDL interruption preserves partial evidence and never executes a destructive recovery',function(){
    list($db,$backend,$migration)=msetup();$hash=$migration->plan()['manifest_hash'];$backend->failAt=4;
    mreject(function()use($migration,$hash){$migration->install($hash);});$before=$backend->ddlCount;
    $fresh=new M119MigrationBackend(new M119NativeSqlite($db->database_name));$retry=new OfficeExpenseNativeSchemaMigration($fresh);
    mcheck($retry->plan()['state'],'partial');mcheck($fresh->marker()['status'],'installing');
    mreject(function()use($retry,$hash){$retry->install($hash);},DomainException::class);mcheck($backend->ddlCount,$before);mcheck($fresh->ddlCount,0);
});
mtest('lost ready-marker commit response is not success and fresh readback recovers installed state',function(){
    list($db,$backend,$migration)=msetup();$hash=$migration->plan()['manifest_hash'];$backend->loseReadyResponse=true;
    mreject(function()use($migration,$hash){$migration->install($hash);});
    $fresh=new M119MigrationBackend(new M119NativeSqlite($db->database_name));$retry=new OfficeExpenseNativeSchemaMigration($fresh);
    mcheck($retry->install($hash)['state'],'installed');mcheck($fresh->ddlCount,0);mcheck(mcount($db,'cpo_oe_schema'),1);
});
mtest('uncertain lock acquisition releases its owned lock and retires the handle without DDL',function(){
    list($db,$backend,$migration)=msetup();$hash=$migration->plan()['manifest_hash'];$backend->loseAcquireResponse=true;
    try{
        mreject(function()use($migration,$hash){$migration->install($hash);});mcheck($backend->ddlCount,0);
        mreject(function()use($backend){new OfficeExpenseNativeDraftStore($backend);});
        $fresh=new M119MigrationBackend(new M119NativeSqlite($db->database_name));mcheck((new OfficeExpenseNativeSchemaMigration($fresh))->install($hash)['state'],'installed');
    }finally{$backend->releaseOwnedForTest();}
});
mtest('unconfirmed release reports failure quarantines the handle and remains visible to a second connection',function(){
    list($db,$backend,$migration)=msetup();$hash=$migration->plan()['manifest_hash'];$backend->failRelease=true;
    try{
        mreject(function()use($migration,$hash){$migration->install($hash);});
        mreject(function()use($backend){new OfficeExpenseNativeDraftStore($backend);});
        $fresh=new M119MigrationBackend(new M119NativeSqlite($db->database_name));$retry=new OfficeExpenseNativeSchemaMigration($fresh);
        mcheck($retry->plan()['state'],'installed');mreject(function()use($retry,$hash){$retry->install($hash);},DomainException::class);mcheck($fresh->ddlCount,0);
    }finally{$backend->releaseOwnedForTest();}
});
mtest('readiness cannot observe caller-uncommitted marker changes',function(){
    list($db,$backend,$migration)=msetup();$hash=$migration->plan()['manifest_hash'];$migration->install($hash);$db->begin();
    try{mreject(function()use($migration){$migration->assertReady();},LogicException::class);}finally{$db->rollback();}
});
mtest('selected database mismatch refuses planning and installation before any owned DDL',function(){
    list($db,$backend,$migration)=msetup();$hash=$migration->plan()['manifest_hash'];$backend->selectedDatabase='different_synthetic_database';
    mreject(function()use($migration){$migration->plan();},DomainException::class);
    mreject(function()use($migration,$hash){$migration->install($hash);},DomainException::class);mcheck($backend->ddlCount,0);mcheck($backend->lockCount,0);
});
mtest('a caller-owned advisory lock is neither recursively acquired nor released by the adapter',function(){
    list($db,$backend,$migration)=msetup();$hash=$migration->plan()['manifest_hash'];$name='cpo_oe_'.substr(hash('sha256',$backend->database_name.'|llx_'),0,48);
    $backend->query("SELECT GET_LOCK('".$name."',0) AS acquired");
    try{
        mreject(function()use($migration,$hash){$migration->install($hash);},DomainException::class);mcheck($backend->lockCount,1);mcheck($backend->ddlCount,0);
        $fresh=new M119MigrationBackend(new M119NativeSqlite($db->database_name));$retry=new OfficeExpenseNativeSchemaMigration($fresh);
        mreject(function()use($retry,$hash){$retry->install($hash);},DomainException::class);mcheck($fresh->ddlCount,0);
    }finally{$backend->releaseOwnedForTest();}
});
mtest('reversal plan preserves schema and data without running down SQL',function(){
    list($db,$backend,$migration)=msetup();$migration->install($migration->plan()['manifest_hash']);$before=$backend->ddlCount;
    $rollback=$migration->reversalPlan();mcheck($rollback['destructive_sql'],array());mcheck($rollback['preserve_data'],true);mcheck($backend->ddlCount,$before);mcheck(mcount($db,'cpo_oe_schema'),1);
});
mtest('caller transactions and unsupported native database drivers fail before migration SQL',function(){
    list($db,$backend,$migration)=msetup();$hash=$migration->plan()['manifest_hash'];$db->begin();
    mreject(function()use($migration,$hash){$migration->install($hash);},LogicException::class);mcheck($db->transaction_opened,1);$db->rollback();mcheck($backend->ddlCount,0);
    mreject(function()use($db){new OfficeExpenseMysqliSchemaBackend($db);},InvalidArgumentException::class);
});
mtest('tampered SQL bytes fail manifest validation before any database work',function(){
    global $module;list($db,$backend)=msetup();$root=DOL_DATA_ROOT.'/schema-copy';mkdir($root,0700);
    try{
        foreach(glob($module.'/schema/*') as $file)if(is_file($file))copy($file,$root.'/'.basename($file));
        file_put_contents($root.'/001_drafts.sql',"\n-- changed artifact\n",FILE_APPEND);
        mreject(function()use($backend,$root){(new OfficeExpenseNativeSchemaMigration($backend,$root))->plan();});mcheck($backend->ddlCount,0);
    }finally{foreach(glob($root.'/*') as $file)unlink($file);rmdir($root);}
});
echo "$passed passed, $failed failed; fresh owned synthetic migration only; native MySQL/MariaDB acceptance pending\n";exit($failed?1:0);
