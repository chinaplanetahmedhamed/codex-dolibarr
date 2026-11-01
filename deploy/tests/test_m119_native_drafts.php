<?php
declare(strict_types=1);
// Explicit native source path, fresh synthetic database only; never load conf.php/main.inc.php.
if (PHP_SAPI !== 'cli' || $argc !== 2 || !is_file($argv[1].'/expensereport/class/expensereport.class.php')) {
    fwrite(STDERR, "Usage: php test_m119_native_drafts.php /path/to/dolibarr/htdocs\n"); exit(1);
}
$root=dirname(__DIR__, 2);
$bridge=$root.'/custom/chinaplanetofficeexpenses/class/OfficeExpenseNativeDraftBridge.php';
if (!is_file($bridge)) { fwrite(STDERR,"FAIL native draft bridge is missing\n"); exit(1); }
require_once __DIR__.'/fixtures/m119_native_bootstrap.php';
require_once $bridge;
require_once $root.'/deploy/tools/office_expenses/LocalDraftStore.php';
require_once $root.'/deploy/tools/office_expenses/LocalNativeDraftWriter.php';

$passed=0; $failed=0;
function ncheck($actual,$expected): void { if ($actual !== $expected) throw new RuntimeException('Expected '.var_export($expected,true).' got '.var_export($actual,true)); }
function nreject(callable $f,string $type=DomainException::class): void { try { $f(); } catch (Throwable $e) { if ($e instanceof $type) return; throw $e; } throw new RuntimeException('Expected rejection: '.$type); }
function ntest(string $label,callable $f): void { global $passed,$failed; try { $f();echo "PASS $label\n";$passed++; } catch(Throwable $e) {fwrite(STDERR,"FAIL $label: ".$e->getMessage()."\n");$failed++;} }
function nsetup(string $currency='EGP',int $entity=1): array {
    global $conf,$user;
    $db=m119NativeDatabase($currency,$entity);
    $user=new class { public $id=7; public $socid=0; public $status=1; public $allowed=true; public function hasRight($m,$r){return $this->allowed&&$m==='expensereport'&&$r==='creer';} };
    $money=new OfficeExpenseMoney();
    $service=new OfficeExpenseDraftService(new LocalDraftStore(new PDO('sqlite::memory:')), $money, $user, $entity, array('egypt'=>array('EGP'),'china'=>array('CNY')), $currency);
    $writer=new LocalNativeDraftWriter($db,$user,$conf,$money);
    return array($db,$service,new OfficeExpenseNativeDraftBridge($service,$writer),$user,$writer);
}
function npayload(string $currency='EGP',string $amount='700.00',string $kind='expense_claim'): array {
    return array('kind'=>$kind,'office'=>$currency==='CNY'?'china':'egypt','amount'=>$amount,'currency'=>$currency,'occurred_on'=>'2026-10-31','source_ref'=>'SYNTHETIC native receipt','purpose'=>'SYNTHETIC native office expense');
}
function ncount($db,string $table): int { return (int) $db->fetch_object($db->query('SELECT COUNT(*) n FROM llx_'.$table))->n; }

ntest('real native ExpenseReport and ExpenseReportLine persist an unpaid EGP draft',function(){
    list($db,$s,$bridge)=nsetup(); $draft=$s->submit(npayload(),'synthetic-native-001');
    $link=$bridge->createDraft($draft['id'],7,'explicit_zero_tax');
    ncheck($link['native_currency'],'EGP'); ncheck($link['amount'],'700.00'); ncheck($link['status'],'draft'); ncheck($link['paid'],false);
    ncheck(ncount($db,'expensereport'),1); ncheck(ncount($db,'expensereport_det'),1); ncheck(ncount($db,'oe_native_draft'),1);
    ncheck($bridge->createDraft($draft['id'],7,'explicit_zero_tax'),$link);
    ncheck(ncount($db,'expensereport'),1);
});
ntest('real native CNY draft works in a distinct synthetic CNY entity',function(){
    list($db,$s,$bridge)=nsetup('CNY',2); $d=$s->submit(npayload('CNY','180.29'),'synthetic-native-cny');
    $link=$bridge->createDraft($d['id'],7,'explicit_zero_tax'); ncheck($link['entity'],2);ncheck($link['amount'],'180.29');ncheck($link['native_currency'],'CNY');
});
ntest('foreign currency and non-claim movements cannot enter native expense reports',function(){
    list($db,$s,$bridge)=nsetup();$p=npayload('CNY');$p['quote']=array('rate'=>'7','date'=>'2026-10-31','source'=>'SYNTHETIC');
    $d=$s->submit($p,'synthetic-native-fx');nreject(function()use($bridge,$d){$bridge->createDraft($d['id'],7,'explicit_zero_tax');});
    $a=$s->submit(npayload('EGP','1000','advance'),'synthetic-native-advance');nreject(function()use($bridge,$a){$bridge->createDraft($a['id'],7,'explicit_zero_tax');});
    ncheck(ncount($db,'expensereport'),0);ncheck(ncount($db,'oe_native_draft'),0);
});
ntest('native bridge reloads scoped source and rejects forged IDs rights and taxes',function(){
    list($db,$s,$bridge,$user)=nsetup(); $d=$s->submit(npayload(),'synthetic-native-guards');
    nreject(function()use($bridge){$bridge->createDraft(str_repeat('0',32),7,'explicit_zero_tax');});
    nreject(function()use($bridge,$d){$bridge->createDraft($d['id'],7,'unknown');});
    nreject(function()use($bridge,$d){$bridge->createDraft($d['id'],999,'explicit_zero_tax');});
    $user->allowed=false;nreject(function()use($bridge,$d){$bridge->createDraft($d['id'],7,'explicit_zero_tax');});
    ncheck(ncount($db,'expensereport'),0);
});
ntest('native line failure and link failure roll back complete native draft atomically',function(){
    list($db,$s,$bridge)=nsetup();$d=$s->submit(npayload(),'synthetic-native-rollback');
    $db->query("CREATE TRIGGER oe_fail_line BEFORE INSERT ON llx_expensereport_det BEGIN SELECT RAISE(ABORT,'SYNTHETIC line failure'); END");
    nreject(function()use($bridge,$d){$bridge->createDraft($d['id'],7,'explicit_zero_tax');},RuntimeException::class);
    ncheck(ncount($db,'expensereport'),0);ncheck(ncount($db,'oe_native_draft'),0);ncheck($db->transaction_opened,0);
    $db->query('DROP TRIGGER oe_fail_line');
    $db->query("CREATE TRIGGER oe_fail_link BEFORE UPDATE ON llx_oe_native_draft BEGIN SELECT RAISE(ABORT,'SYNTHETIC link failure'); END");
    nreject(function()use($bridge,$d){$bridge->createDraft($d['id'],7,'explicit_zero_tax');},RuntimeException::class);
    ncheck(ncount($db,'expensereport'),0);ncheck(ncount($db,'expensereport_det'),0);ncheck(ncount($db,'oe_native_draft'),0);
    $db->query('DROP TRIGGER oe_fail_link');$bridge->createDraft($d['id'],7,'explicit_zero_tax');ncheck(ncount($db,'expensereport'),1);
});
ntest('conflicting mappings and changed native amounts block retries',function(){
    list($db,$s,$bridge)=nsetup();$d=$s->submit(npayload(),'synthetic-native-conflict');$link=$bridge->createDraft($d['id'],7,'explicit_zero_tax');
    nreject(function()use($bridge,$d){$bridge->createDraft($d['id'],8,'explicit_zero_tax');});
    $db->query('UPDATE llx_expensereport SET total_ttc=701 WHERE rowid='.(int)$link['expense_report_id']);
    nreject(function()use($bridge,$d){$bridge->createDraft($d['id'],7,'explicit_zero_tax');});ncheck(ncount($db,'expensereport'),1);
});
ntest('nested caller transactions are refused without rolling back the caller',function(){
    list($db,$s,$bridge)=nsetup();$d=$s->submit(npayload(),'synthetic-native-nested');$db->begin();
    nreject(function()use($bridge,$d){$bridge->createDraft($d['id'],7,'explicit_zero_tax');},LogicException::class);
    ncheck($db->transaction_opened,1);$db->rollback();ncheck(ncount($db,'expensereport'),0);
});
ntest('changed native dates block retry association',function(){
    list($db,$s,$bridge)=nsetup();$d=$s->submit(npayload(),'synthetic-native-date');$link=$bridge->createDraft($d['id'],7,'explicit_zero_tax');
    $db->query("UPDATE llx_expensereport SET date_debut='2026-10-30' WHERE rowid=".(int)$link['expense_report_id']);
    nreject(function()use($bridge,$d){$bridge->createDraft($d['id'],7,'explicit_zero_tax');});ncheck(ncount($db,'expensereport'),1);
});
ntest('native owner entity module and external-user boundaries fail before writes',function(){
    global $conf;
    list($db,$s,$bridge,$u)=nsetup();$d=$s->submit(npayload(),'synthetic-native-context');
    $u->id=8;nreject(function()use($bridge,$d){$bridge->createDraft($d['id'],7,'explicit_zero_tax');});$u->id=7;
    $u->socid=99;nreject(function()use($bridge,$d){$bridge->createDraft($d['id'],7,'explicit_zero_tax');});$u->socid=0;
    $conf->entity=2;nreject(function()use($bridge,$d){$bridge->createDraft($d['id'],7,'explicit_zero_tax');});$conf->entity=1;
    $conf->expensereport->enabled=0;nreject(function()use($bridge,$d){$bridge->createDraft($d['id'],7,'explicit_zero_tax');});
    ncheck(ncount($db,'expensereport'),0);ncheck(ncount($db,'oe_native_draft'),0);
});
ntest('native maximum supported original amount round-trips without loss',function(){
    list($db,$s,$bridge)=nsetup();$d=$s->submit(npayload('EGP','999999999999.99'),'synthetic-native-boundary');
    $link=$bridge->createDraft($d['id'],7,'explicit_zero_tax');ncheck($link['amount'],'999999999999.99');
    $row=$db->fetch_object($db->query('SELECT snapshot_json FROM llx_oe_native_draft'));
    ncheck(json_decode($row->snapshot_json,true)['amount'],'999999999999.99');ncheck(ncount($db,'expensereport'),1);
});
ntest('native readback mismatch rolls back a newly created draft',function(){
    list($db,$s,$bridge)=nsetup();$d=$s->submit(npayload(),'synthetic-native-readback');
    $db->query('CREATE TRIGGER oe_drift AFTER UPDATE OF total_ttc ON llx_expensereport BEGIN UPDATE llx_expensereport SET total_ttc=701 WHERE rowid=NEW.rowid; END');
    nreject(function()use($bridge,$d){$bridge->createDraft($d['id'],7,'explicit_zero_tax');});
    ncheck(ncount($db,'expensereport'),0);ncheck(ncount($db,'expensereport_det'),0);ncheck(ncount($db,'oe_native_draft'),0);
});
ntest('native detail date VAT and local-tax drift each block a retry',function(){
    foreach (array("date='2026-10-30'",'tva_tx=20','localtax1_tx=10','localtax2_tx=10','total_localtax1=70','total_localtax2=70') as $mutation) {
        list($db,$s,$bridge)=nsetup();$d=$s->submit(npayload(),'synthetic-native-tax-drift');$link=$bridge->createDraft($d['id'],7,'explicit_zero_tax');
        $db->query('UPDATE llx_expensereport_det SET '.$mutation.' WHERE fk_expensereport='.(int)$link['expense_report_id']);
        nreject(function()use($bridge,$d){$bridge->createDraft($d['id'],7,'explicit_zero_tax');});ncheck(ncount($db,'expensereport'),1);
    }
});
ntest('silently ignored registry completion rolls back native records',function(){
    list($db,$s,$bridge)=nsetup();$d=$s->submit(npayload(),'synthetic-native-ignored');
    $db->query('CREATE TRIGGER oe_ignore_link BEFORE UPDATE ON llx_oe_native_draft BEGIN SELECT RAISE(IGNORE); END');
    nreject(function()use($bridge,$d){$bridge->createDraft($d['id'],7,'explicit_zero_tax');},RuntimeException::class);
    ncheck(ncount($db,'expensereport'),0);ncheck(ncount($db,'expensereport_det'),0);ncheck(ncount($db,'oe_native_draft'),0);
});
echo "$passed passed, $failed failed; native source ".DOL_DOCUMENT_ROOT."\n";
m119NativeCleanup();exit($failed?1:0);
