<?php
declare(strict_types=1);
// Real draft controller/service, isolated synthetic in-memory storage only.
$root=dirname(__DIR__,2);
require $root.'/custom/chinaplanetofficeexpenses/class/OfficeExpenseEmployeeController.php';
require $root.'/custom/chinaplanetofficeexpenses/class/OfficeExpenseEmployeePage.php';
require $root.'/deploy/tools/office_expenses/LocalDraftStore.php';
$passed=0;$failed=0;
function wf(bool $condition,string $name):void{global$passed,$failed;if($condition){$passed++;echo "PASS $name\n";}else{$failed++;echo "FAIL $name\n";}}
foreach(['egypt'=>'EGP','china'=>'CNY']as$office=>$currency){
    $user=new class{public $id=7;public $socid=0;public $status=1;public function hasRight($m,$r){return $m==='expensereport'&&$r==='creer';}};
    $service=new OfficeExpenseDraftService(new LocalDraftStore(new PDO('sqlite::memory:')),new OfficeExpenseMoney(),$user,1,[$office=>[$currency]],$currency);
    $c=new OfficeExpenseEmployeeController($service,'synthetic-csrf','synthetic-csrf');
    foreach(['expense_claim'=>'700.10','advance'=>'1000.00']as$kind=>$amount){
        $p=['action'=>'save','token'=>'synthetic-csrf','submission_key'=>'synthetic-workflow-'.$kind,'kind'=>$kind,'office'=>$office,'amount'=>$amount,'currency'=>$currency,'occurred_on'=>'2026-11-03','source_ref'=>'SYNTHETIC receipt','purpose'=>'SYNTHETIC '.$kind,'quote_rate'=>'','quote_date'=>'','quote_source'=>'','settles_ref'=>'','lang'=>'en'];
        $first=$c->handle('POST',[],$p);$retry=$c->handle('POST',[],$p);
        wf($first['status']===303&&$retry['redirect']===$first['redirect'],$office.' '.$kind.' duplicate POST resolves to the same draft');
        $rows=$service->listOwn();$draft=end($rows);
        wf($draft['office']===$office&&$draft['amount']===$amount&&$draft['currency']===$currency&&$draft['status']==='draft',$office.' '.$kind.' persists exact original money as a draft');
        wf($c->handle('POST',[],array_replace($p,['amount'=>'900.00']))['status']===409,$office.' '.$kind.' changed intent cannot reuse the submission key');
    }
    wf(count($service->listOwn())===2,$office.' expense and advance retries create exactly two records');
    foreach(['ar','en']as$lang){
        $state=$c->handle('GET',['view'=>'advances','lang'=>$lang],[]);
        $html=(new OfficeExpenseEmployeePage())->render($state,$lang,'synthetic-csrf');
        wf(str_contains($html,'data-review-office')&&str_contains($html,'data-review-kind')&&str_contains($html,'data-review-money'),$office.' '.$lang.' form provides original-money and office review before saving');
        wf(str_contains($html,'data-draft-submit')&&str_contains($html,'data-saving-label')&&str_contains($html,'data-draft-saving-status'),$office.' '.$lang.' form exposes pending-submission feedback');
        $invalid=$c->handle('POST',[],array_replace($p,['submission_key'=>'synthetic-invalid-'.$lang,'purpose'=>'','amount'=>'180.29','lang'=>$lang]));
        $errorHtml=(new OfficeExpenseEmployeePage())->render($invalid,$lang,'synthetic-csrf');
        $dom=new DOMDocument();@$dom->loadHTML('<?xml encoding="UTF-8">'.$errorHtml);$xp=new DOMXPath($dom);
        wf($invalid['status']===422&&$xp->evaluate('string(//output[@data-review-money])')==='180.29 '.$currency&&count($service->listOwn())===2,$office.' '.$lang.' invalid draft retains exact review money without saving');
    }
    wf($c->handle('POST',[],array_replace($p,['action'=>'post']))['status']===403&&count($service->listOwn())===2,$office.' employee route cannot post or alter saved drafts');
}
echo "$passed passed, $failed failed\n";exit($failed?1:0);
