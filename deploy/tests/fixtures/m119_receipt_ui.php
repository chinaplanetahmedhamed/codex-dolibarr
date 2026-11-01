<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$root=dirname(__DIR__,3);$module=$root.'/custom/chinaplanetoperations/office_expenses';
if(!is_file($module.'/class/OfficeExpenseReceiptHttp.php')){fwrite(STDERR,"FAIL protected receipt interface/transport is missing\n");exit(1);}
require_once $module.'/class/OfficeExpenseReceiptHttp.php';require_once $module.'/class/OfficeExpenseEmployeeController.php';require_once $module.'/class/OfficeExpenseEmployeePage.php';
require_once $root.'/deploy/tools/office_expenses/LocalDraftStore.php';require_once $root.'/deploy/tools/office_expenses/LocalReceiptStore.php';
umask(0077);$owned=array();$passed=0;$failed=0;
function ucheck($a,$b): void {if($a!==$b)throw new RuntimeException('Expected '.var_export($b,true).' got '.var_export($a,true));}
function ureject(callable $f,string $class=InvalidArgumentException::class): void {try{$f();}catch(Throwable $e){if($e instanceof $class)return;throw $e;}throw new RuntimeException('Expected '.$class);}
function utest(string $name,callable $f): void {global $passed,$failed;try{$f();echo "PASS $name\n";$passed++;}catch(Throwable $e){fwrite(STDERR,"FAIL $name: ".$e->getMessage()."\n");$failed++;}}
class M119ReceiptUIUser {public $id=7;public $socid=0;public $status=1;public $allowed=true;function hasRight($m,$r){return $this->allowed&&$m==='expensereport'&&$r==='creer';}}
// Test-only bytes source: never loaded by index.php, native bootstrap or a request-selectable factory.
class M119SyntheticUploadSource implements OfficeExpenseReceiptUploadSource {
    public $reads=0;public $cap=5242880;
    public function effectiveLimit(int $configured): int {return min($configured,$this->cap);}
    public function read(array $file,int $maxBytes): array {$this->reads++;if(!is_string($file['synthetic_bytes']??null)||!is_string($file['name']??null))throw new InvalidArgumentException('Synthetic fixture invalid.');if(strlen($file['synthetic_bytes'])>$maxBytes)throw new OfficeExpenseUploadTooLarge('Synthetic limit.');return array('name'=>$file['name'],'bytes'=>$file['synthetic_bytes']);}
}
function usetup(int $count=10): array {
    global $owned;$base=sys_get_temp_dir().'/m119-receipt-ui-'.bin2hex(random_bytes(8));mkdir($base,0700);$owned[]=$base;mkdir($base.'/private',0700);mkdir($base.'/web',0700);file_put_contents($base.'/private/.m119-receipts-synthetic','Synthetic UI only');
    $pdo=new PDO('sqlite:'.$base.'/private/state.sqlite');$user=new M119ReceiptUIUser();$store=new LocalDraftStore($pdo);
    $drafts=new OfficeExpenseDraftService($store,new OfficeExpenseMoney(),$user,1,array('egypt'=>array('EGP','USD')),'EGP');
    $receipts=new OfficeExpenseReceiptService($drafts,new LocalReceiptStore($pdo,$base.'/private',$base.'/web',$count));$uploads=new M119SyntheticUploadSource();
    $controller=new OfficeExpenseEmployeeController($drafts,'synthetic-csrf','synthetic-csrf','en',$receipts,$uploads);
    return array($pdo,$user,$drafts,$receipts,$uploads,$controller,$base);
}
function uimage(): string {$i=imagecreatetruecolor(3,2);ob_start();imagepng($i);$b=(string)ob_get_clean();imagedestroy($i);return $b;}
function udraft($controller,string $key='synthetic-ui-source'): array {
    $post=array('action'=>'save','token'=>'synthetic-csrf','submission_key'=>$key,'kind'=>'expense_claim','office'=>'egypt','amount'=>'700.10','currency'=>'EGP','occurred_on'=>'2026-11-01','source_ref'=>'SYNTHETIC UI receipt','purpose'=>'SYNTHETIC private source');
    $saved=$controller->handle('POST',array(),$post);ucheck($saved['status'],303);parse_str(parse_url($saved['redirect'],PHP_URL_QUERY),$q);
    $state=$controller->handle('GET',$q,array());ucheck($state['status'],200);return array($q['saved'],$state);
}
function uupload(string $id,string $key='synthetic-ui-receipt'): array {return array('action'=>'attach_receipt','token'=>'synthetic-csrf','lang'=>'en','draft_id'=>$id,'receipt_key'=>$key);}
function ufiles(string $name='receipt.png'): array {return array('receipt'=>array('name'=>$name,'synthetic_bytes'=>uimage()));}
function uclean($path): void {if(is_link($path)||is_file($path)){unlink($path);return;}foreach(scandir($path)as$n)if($n!=='.'&&$n!=='..')uclean($path.'/'.$n);rmdir($path);}
register_shutdown_function(function()use(&$owned){foreach($owned as$p)if(is_dir($p)&&preg_match('/^m119-receipt-ui-[a-f0-9]{16}$/D',basename($p)))uclean($p);});
