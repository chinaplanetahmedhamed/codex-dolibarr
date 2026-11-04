<?php
declare(strict_types=1);
$module=dirname(__DIR__,2).'/custom/chinaplanetofficeexpenses';
$tools=dirname(__DIR__).'/tools/office_expenses';
if(!is_file($module.'/class/OfficeExpenseReceiptService.php')||!is_file($tools.'/LocalReceiptStore.php')){
    fwrite(STDERR,"FAIL scoped private receipt storage is missing\n");exit(1);
}
require $module.'/class/OfficeExpenseReceiptService.php';require $tools.'/LocalDraftStore.php';require $tools.'/LocalReceiptStore.php';
$passed=0;$failed=0;$owned=array();umask(0077);
function rcheck($actual,$expected): void {if($actual!==$expected)throw new RuntimeException('Expected '.var_export($expected,true).' got '.var_export($actual,true));}
function rreject(callable $fn,string $type=DomainException::class): void {try{$fn();}catch(Throwable $e){if($e instanceof $type)return;throw $e;}throw new RuntimeException('Expected '.$type);}
function rtest(string $name,callable $fn): void {global $passed,$failed;try{$fn();echo "PASS $name\n";$passed++;}catch(Throwable $e){fwrite(STDERR,"FAIL $name: ".$e->getMessage()."\n");$failed++;}}
final class M119ReceiptUser {public $id=7;public $status=1;public $socid=0;public $allowed=true;function hasRight($m,$r){return $this->allowed&&$m==='expensereport'&&$r==='creer';}}
class M119ReceiptPDO extends PDO {
    public $loseCommitResponse=false;public $failRollback=false;public $failCommit=false;
    public function commit(): bool {if($this->failCommit)return false;$ok=parent::commit();if($this->loseCommitResponse)throw new RuntimeException('SYNTHETIC lost commit response');return $ok;}
    public function rollBack(): bool {return $this->failRollback?false:parent::rollBack();}
}
function rsetup(int $count=10,int $bytes=26214400): array {
    global $owned;
    $base=sys_get_temp_dir().'/m119-receipts-'.bin2hex(random_bytes(8));mkdir($base,0700);$owned[]=$base;
    mkdir($base.'/private',0700);mkdir($base.'/web',0700);file_put_contents($base.'/private/.m119-receipts-synthetic','Synthetic receipts only');
    $pdo=new M119ReceiptPDO('sqlite:'.$base.'/private/state.sqlite');$user=new M119ReceiptUser();
    $drafts=new OfficeExpenseDraftService(new LocalDraftStore($pdo),new OfficeExpenseMoney(),$user,1,array('egypt'=>array('EGP')),'EGP');
    $draft=$drafts->submit(array('kind'=>'expense_claim','office'=>'egypt','amount'=>'700.10','currency'=>'EGP','occurred_on'=>'2026-11-01','source_ref'=>'SYNTHETIC receipt','purpose'=>'SYNTHETIC private draft'),'synthetic-source-draft');
    $store=new LocalReceiptStore($pdo,$base.'/private',$base.'/web',$count,$bytes);
    return array($pdo,$user,$drafts,new OfficeExpenseReceiptService($drafts,$store),$draft,$base,$store);
}
function rimage(string $kind='png'): string {
    $image=imagecreatetruecolor(3,2);$color=imagecolorallocate($image,40,90,140);imagefill($image,0,0,$color);ob_start();
    if($kind==='jpg')imagejpeg($image,null,90);else imagepng($image);$bytes=ob_get_clean();imagedestroy($image);return $bytes;
}
function rfiles(string $base): array {return glob($base.'/private/1/7/*/*')?:array();}
function rclean(string $path): void {if(is_link($path)||is_file($path)){unlink($path);return;}foreach(scandir($path) as $name)if($name!=='.'&&$name!=='..')rclean($path.'/'.$name);rmdir($path);}
register_shutdown_function(function()use(&$owned){foreach($owned as $base){if(!preg_match('/^m119-receipts-[a-f0-9]{16}$/D',basename($base)))continue;if(is_dir($base))rclean($base);}});

rtest('receipt preserves exact private original and sanitized preview linked to its own draft',function(){
    list($pdo,$user,$drafts,$receipts,$draft,$base)=rsetup();$bytes=rimage();
    $meta=$receipts->attach($draft['id'],'synthetic-receipt-key','إيصال.png',$bytes);
    rcheck($meta['draft_id'],$draft['id']);rcheck($meta['entity'],1);rcheck($meta['owner_id'],7);rcheck($meta['sha256'],hash('sha256',$bytes));
    $read=$receipts->read($draft['id'],$meta['id']);rcheck($read['bytes'],$bytes);rcheck($read['mime'],'application/octet-stream');rcheck($read['disposition'],'attachment');
    rcheck(isset($read['path']),false);rcheck(count(rfiles($base)),2);rcheck(count($receipts->listOwn($draft['id'])),1);
    $audit=$receipts->auditOwn($draft['id']);rcheck(array_column($audit,'event'),array('receipt_added','receipt_access_started'));
});
rtest('receipt and download survive store reopening without duplicating file or creation audit',function(){
    list($pdo,$user,$drafts,$receipts,$draft,$base)=rsetup();$bytes=rimage('jpg');$first=$receipts->attach($draft['id'],'synthetic-receipt-reopen','receipt.jpg',$bytes);
    $again=new OfficeExpenseReceiptService($drafts,new LocalReceiptStore(new PDO('sqlite:'.$base.'/private/state.sqlite'),$base.'/private',$base.'/web'));
    rcheck($again->attach($draft['id'],'synthetic-receipt-reopen','receipt.jpg',$bytes),$first);rcheck(count(rfiles($base)),2);rcheck(count($again->auditOwn($draft['id'])),1);
    rcheck($again->read($draft['id'],$first['id'])['bytes'],$bytes);
});
rtest('same receipt key with changed name or bytes conflicts and never overwrites source',function(){
    list($pdo,$user,$drafts,$receipts,$draft,$base)=rsetup();$bytes=rimage();$first=$receipts->attach($draft['id'],'synthetic-receipt-conflict','receipt.png',$bytes);
    rreject(function()use($receipts,$draft,$bytes){$receipts->attach($draft['id'],'synthetic-receipt-conflict','renamed.png',$bytes);});
    rreject(function()use($receipts,$draft,$bytes){$receipts->attach($draft['id'],'synthetic-receipt-conflict','receipt.png',$bytes.'changed');});
    rcheck($receipts->read($draft['id'],$first['id'])['bytes'],$bytes);rcheck(count(rfiles($base)),2);
});
rtest('different native owner entity or office cannot list read retry or inspect a receipt',function(){
    list($pdo,$user,$drafts,$receipts,$draft,$base,$store)=rsetup();$bytes=rimage();$meta=$receipts->attach($draft['id'],'synthetic-private-owner','receipt.png',$bytes);
    foreach(array(array(1,8,array('egypt'=>array('EGP'))),array(2,7,array('egypt'=>array('EGP'))),array(1,7,array('china'=>array('EGP')))) as $scope){
        $other=new M119ReceiptUser();$other->id=$scope[1];$service=new OfficeExpenseDraftService(new LocalDraftStore($pdo),new OfficeExpenseMoney(),$other,$scope[0],$scope[2],'EGP');$foreign=new OfficeExpenseReceiptService($service,$store);
        foreach(array(function()use($foreign,$draft){$foreign->listOwn($draft['id']);},function()use($foreign,$draft,$meta){$foreign->read($draft['id'],$meta['id']);},function()use($foreign,$draft,$bytes){$foreign->attach($draft['id'],'synthetic-private-owner','receipt.png',$bytes);},function()use($foreign,$draft){$foreign->inspectOwn($draft['id']);}) as $action)rreject($action);
    }
    rcheck(count($receipts->auditOwn($draft['id'])),1);
});
rtest('revoked disabled and external identity cannot access previously stored receipts',function(){
    list($pdo,$user,$drafts,$receipts,$draft)=rsetup();$meta=$receipts->attach($draft['id'],'synthetic-receipt-revoke','receipt.png',rimage());
    foreach(array('allowed'=>false,'status'=>0,'socid'=>99) as $field=>$value){$old=$user->$field;$user->$field=$value;
        rreject(function()use($receipts,$draft,$meta){$receipts->read($draft['id'],$meta['id']);});$user->$field=$old;}
    rcheck(count($receipts->auditOwn($draft['id'])),1);
});
rtest('receipt ID from another owned draft cannot be downloaded through this draft',function(){
    list($pdo,$user,$drafts,$receipts,$draft)=rsetup();$meta=$receipts->attach($draft['id'],'synthetic-receipt-source','receipt.png',rimage());
    $second=$draft;unset($second['id'],$second['created_at'],$second['entity'],$second['owner_id'],$second['conversion'],$second['status'],$second['accounting_route'],$second['native_record']);
    $other=$drafts->submit($second,'synthetic-second-draft');rreject(function()use($receipts,$other,$meta){$receipts->read($other['id'],$meta['id']);});
});
rtest('paths hidden names bidi double extensions unsupported formats and MIME spoof are rejected',function(){
    list($pdo,$user,$drafts,$receipts,$draft,$base)=rsetup();
    foreach(array('../receipt.png','dir\\receipt.png','.receipt.png','receipt.php.png',"receipt\r\n.png","receipt\u{202E}.png",'receipt.pdf','receipt.svg','receipt.exe','receipt.jpg') as $name){
        rreject(function()use($receipts,$draft,$name){$receipts->attach($draft['id'],'synthetic-name-validation',$name,rimage());},InvalidArgumentException::class);
    }
    rreject(function()use($receipts,$draft){$receipts->attach($draft['id'],'synthetic-invalid-image','receipt.png','not an image');},InvalidArgumentException::class);
    rcheck(count(rfiles($base)),0);
});
rtest('compressed-byte pixel and preview-byte limits apply before persistence',function(){
    list($pdo,$user,$drafts,$receipts,$draft,$base,$store)=rsetup();
    foreach(array(array('max_bytes'=>1),array('max_pixels'=>1),array('max_preview_bytes'=>1)) as $policy){
        $small=new OfficeExpenseReceiptService($drafts,$store,$policy);
        rreject(function()use($small,$draft){$small->attach($draft['id'],'synthetic-image-limits','receipt.png',rimage());},InvalidArgumentException::class);
    }
    rcheck(count(rfiles($base)),0);rcheck($receipts->listOwn($draft['id']),array());
});
rtest('preview strips appended content while original evidence and hash remain exact',function(){
    list($pdo,$user,$drafts,$receipts,$draft)=rsetup();$bytes=rimage()."<?php SYNTHETIC_NOT_EXECUTED ?>";
    $meta=$receipts->attach($draft['id'],'synthetic-original-preview','receipt.png',$bytes);
    rcheck($receipts->read($draft['id'],$meta['id'])['bytes'],$bytes);
    $preview=$receipts->read($draft['id'],$meta['id'],'preview')['bytes'];rcheck(strpos($preview,'SYNTHETIC_NOT_EXECUTED'),false);rcheck((new finfo(FILEINFO_MIME_TYPE))->buffer($preview),'image/png');
});
rtest('per-draft count and combined original plus preview quota include orphan files',function(){
    list($pdo,$user,$drafts,$receipts,$draft,$base)=rsetup(1);$bytes=rimage();$meta=$receipts->attach($draft['id'],'synthetic-quota-first','receipt.png',$bytes);
    rcheck($receipts->attach($draft['id'],'synthetic-quota-first','receipt.png',$bytes),$meta);
    rreject(function()use($receipts,$draft,$bytes){$receipts->attach($draft['id'],'synthetic-quota-second','second.png',$bytes);});
    list($pdo,$user,$drafts,$receipts,$draft,$base)=rsetup(10,strlen($bytes));
    rreject(function()use($receipts,$draft,$bytes){$receipts->attach($draft['id'],'synthetic-quota-bytes','receipt.png',$bytes);});
    list($pdo,$user,$drafts,$receipts,$draft,$base)=rsetup(1);$dir=$base.'/private/1/7/'.$draft['id'];mkdir($dir,0700,true);$orphan=str_repeat('a',32);file_put_contents($dir.'/'.$orphan.'.original','SYNTHETIC orphan');
    rreject(function()use($receipts,$draft,$bytes){$receipts->attach($draft['id'],'synthetic-orphan-quota','receipt.png',$bytes);});
    $inspection=$receipts->inspectOwn($draft['id']);rcheck($inspection['orphan_files'],1);rcheck(is_file($dir.'/'.$orphan.'.original'),true);
    rreject(function()use($receipts,$draft,$orphan){$receipts->read($draft['id'],$orphan);});
});
rtest('metadata or audit insert failure removes only attempt-owned files and rolls back',function(){
    list($pdo,$user,$drafts,$receipts,$draft,$base)=rsetup();
    $pdo->exec("CREATE TRIGGER m119_receipt_audit_fail BEFORE INSERT ON oe_receipt_audit BEGIN SELECT RAISE(ABORT,'SYNTHETIC failure'); END");
    rreject(function()use($receipts,$draft){$receipts->attach($draft['id'],'synthetic-audit-rollback','receipt.png',rimage());},RuntimeException::class);
    rcheck(count(rfiles($base)),0);rcheck((int)$pdo->query('SELECT COUNT(*) FROM oe_receipt')->fetchColumn(),0);
    $pdo->exec('DROP TRIGGER m119_receipt_audit_fail');$receipts->attach($draft['id'],'synthetic-audit-rollback','receipt.png',rimage());rcheck(count(rfiles($base)),2);
});
rtest('lost response after commit preserves committed files and identical retry recovers',function(){
    list($pdo,$user,$drafts,$receipts,$draft,$base)=rsetup();$bytes=rimage();$pdo->loseCommitResponse=true;
    rreject(function()use($receipts,$draft,$bytes){$receipts->attach($draft['id'],'synthetic-uncertain-commit','receipt.png',$bytes);},RuntimeException::class);
    rcheck((int)$pdo->query('SELECT COUNT(*) FROM oe_receipt')->fetchColumn(),1);rcheck(count(rfiles($base)),2);
    $pdo->loseCommitResponse=false;$meta=$receipts->attach($draft['id'],'synthetic-uncertain-commit','receipt.png',$bytes);
    rcheck(count($receipts->auditOwn($draft['id'])),1);rcheck($receipts->read($draft['id'],$meta['id'])['bytes'],$bytes);
});
rtest('unconfirmed rollback preserves files and blocks further writes until reconciliation',function(){
    list($pdo,$user,$drafts,$receipts,$draft,$base)=rsetup();
    $pdo->exec("CREATE TRIGGER m119_uncertain_rollback BEFORE INSERT ON oe_receipt_audit BEGIN SELECT RAISE(ABORT,'SYNTHETIC failure'); END");$pdo->failRollback=true;
    try{
        rreject(function()use($receipts,$draft){$receipts->attach($draft['id'],'synthetic-failed-rollback','receipt.png',rimage());},RuntimeException::class);
        rcheck(count(rfiles($base)),2);rcheck($pdo->inTransaction(),true);
        rreject(function()use($receipts,$draft){$receipts->attach($draft['id'],'synthetic-blocked-rollback','receipt.png',rimage());},LogicException::class);
    }finally{$pdo->failRollback=false;if($pdo->inTransaction())$pdo->rollBack();}
    rcheck($receipts->inspectOwn($draft['id'])['orphan_files'],2);
});
rtest('false commit cannot report upload success or release receipt bytes',function(){
    list($pdo,$user,$drafts,$receipts,$draft,$base)=rsetup();$meta=$receipts->attach($draft['id'],'synthetic-commit-source','receipt.png',rimage());
    $pdo->failCommit=true;
    try{
        rreject(function()use($receipts,$draft,$meta){$receipts->read($draft['id'],$meta['id']);},RuntimeException::class);
        rcheck(count($receipts->auditOwn($draft['id'])),1);
        rreject(function()use($receipts,$draft){$receipts->attach($draft['id'],'synthetic-commit-false','second.png',rimage());},RuntimeException::class);
        rcheck(count($receipts->listOwn($draft['id'])),1);rcheck(count(rfiles($base)),4);
    }finally{$pdo->failCommit=false;if($pdo->inTransaction())$pdo->rollBack();}
});
rtest('corrupt missing or symlinked receipt files are never returned or silently replaced',function(){
    foreach(array('corrupt','missing','symlink') as $mutation){
        list($pdo,$user,$drafts,$receipts,$draft,$base)=rsetup();$bytes=rimage();$meta=$receipts->attach($draft['id'],'synthetic-integrity-source','receipt.png',$bytes);
        $path=$base.'/private/1/7/'.$draft['id'].'/'.$meta['id'].'.original';
        if($mutation==='corrupt')file_put_contents($path,'changed');else{unlink($path);if($mutation==='symlink')symlink($base.'/private/state.sqlite',$path);}
        rreject(function()use($receipts,$draft,$meta){$receipts->read($draft['id'],$meta['id']);},RuntimeException::class);
        rreject(function()use($receipts,$draft,$bytes){$receipts->attach($draft['id'],'synthetic-integrity-source','receipt.png',$bytes);},RuntimeException::class);
    }
});
rtest('altered source draft invalidates its receipt association',function(){
    list($pdo,$user,$drafts,$receipts,$draft)=rsetup();$meta=$receipts->attach($draft['id'],'synthetic-draft-binding','receipt.png',rimage());
    $changed=$draft;$changed['amount']='701.10';$q=$pdo->prepare('UPDATE oe_draft SET document_json=? WHERE id=?');$q->execute(array(json_encode($changed),$draft['id']));
    rreject(function()use($receipts,$draft,$meta){$receipts->read($draft['id'],$meta['id']);},RuntimeException::class);
});
rtest('access audit failure fails closed without returning bytes or claiming delivery',function(){
    list($pdo,$user,$drafts,$receipts,$draft)=rsetup();$meta=$receipts->attach($draft['id'],'synthetic-read-audit','receipt.png',rimage());
    $pdo->exec("CREATE TRIGGER m119_access_audit_fail BEFORE INSERT ON oe_receipt_audit WHEN NEW.event='receipt_access_started' BEGIN SELECT RAISE(ABORT,'SYNTHETIC failure'); END");
    rreject(function()use($receipts,$draft,$meta){$receipts->read($draft['id'],$meta['id']);},RuntimeException::class);
    rcheck(count($receipts->auditOwn($draft['id'])),1);
});
rtest('deleted or altered access audit after insertion prevents byte release',function(){
    foreach(array('DELETE FROM oe_receipt_audit WHERE id=NEW.id',"UPDATE oe_receipt_audit SET sha256='changed' WHERE id=NEW.id") as $mutation){
        list($pdo,$user,$drafts,$receipts,$draft)=rsetup();$meta=$receipts->attach($draft['id'],'synthetic-audit-readback','receipt.png',rimage());
        $pdo->exec("CREATE TRIGGER m119_access_audit_drift AFTER INSERT ON oe_receipt_audit WHEN NEW.event='receipt_access_started' BEGIN ".$mutation.'; END');
        rreject(function()use($receipts,$draft,$meta){$receipts->read($draft['id'],$meta['id']);},RuntimeException::class);
        rcheck(count($receipts->auditOwn($draft['id'])),1);
    }
});
rtest('caller transaction is rejected without rollback or file creation',function(){
    list($pdo,$user,$drafts,$receipts,$draft,$base)=rsetup();$pdo->beginTransaction();
    rreject(function()use($receipts,$draft){$receipts->attach($draft['id'],'synthetic-receipt-nested','receipt.png',rimage());},LogicException::class);
    rcheck($pdo->inTransaction(),true);rcheck(count(rfiles($base)),0);$pdo->rollBack();
});
rtest('web-root or symlink storage and database outside owned root are rejected',function(){
    list($pdo,$user,$drafts,$receipts,$draft,$base)=rsetup();
    rreject(function()use($pdo,$base){new LocalReceiptStore($pdo,$base.'/private',$base);},InvalidArgumentException::class);
    symlink($base.'/private',$base.'/linked');rreject(function()use($pdo,$base){new LocalReceiptStore($pdo,$base.'/linked',$base.'/web');},InvalidArgumentException::class);
    rreject(function()use($base){new LocalReceiptStore(new PDO('sqlite::memory:'),$base.'/private',$base.'/web');},InvalidArgumentException::class);
});
rtest('storage root replaced by a symlink after construction is rejected before outside writes',function(){
    list($pdo,$user,$drafts,$receipts,$draft,$base)=rsetup();
    rename($base.'/private',$base.'/original-private');mkdir($base.'/other',0700);symlink($base.'/other',$base.'/private');
    rreject(function()use($receipts,$draft){$receipts->attach($draft['id'],'synthetic-replaced-root','receipt.png',rimage());},RuntimeException::class);
    rcheck(scandir($base.'/other'),array('.','..'));
});
echo "$passed passed, $failed failed; synthetic private receipt storage; no services credentials or financial posting\n";exit($failed?1:0);
