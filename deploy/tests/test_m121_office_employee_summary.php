<?php
declare(strict_types=1);
// Synthetic authorized-controller state only; never loads native configuration.
$root=dirname(__DIR__,2);
require_once $root.'/custom/chinaplanetofficeexpenses/class/OfficeExpenseEmployeeController.php';
require_once $root.'/custom/chinaplanetofficeexpenses/class/OfficeExpenseEmployeePage.php';
require_once $root.'/deploy/tools/office_expenses/LocalDraftStore.php';
$user=new class{public $id=7;public $socid=0;public $status=1;public function hasRight($m,$r){return $m==='expensereport'&&$r==='creer';}};
$service=new OfficeExpenseDraftService(new LocalDraftStore(new PDO('sqlite::memory:')),new OfficeExpenseMoney(),$user,1,['egypt'=>['EGP','USD'],'china'=>['CNY','USD']],'USD');
foreach([
    ['advance','egypt','USD','10.10'],['advance','china','USD','20.20'],
    ['advance_return','egypt','USD','3.30'],['advance_return','china','USD','4.40'],
    ['advance','egypt','EGP','1000.00'],['advance','china','CNY','180.29'],
]as$i=>[$kind,$office,$currency,$amount]){
    $payload=['kind'=>$kind,'office'=>$office,'currency'=>$currency,'amount'=>$amount,'purpose'=>'SYNTHETIC request '.$i,'occurred_on'=>'2026-11-03','source_ref'=>'SYNTHETIC '.$i,'quote'=>$currency==='USD'?[]:['rate'=>'1','date'=>'2026-11-03','source'=>'SYNTHETIC valuation']];
    if($kind==='advance_return')$payload['settles_ref']='SYNTHETIC previous advance';
    $service->submit($payload,'synthetic-summary-'.$i);
}
$controller=new OfficeExpenseEmployeeController($service,'synthetic-token','synthetic-token');
$passed=0;$failed=0;
function verify(bool $condition,string $name):void{global$passed,$failed;if($condition){$passed++;echo 'PASS '.$name."\n";}else{$failed++;echo 'FAIL '.$name."\n";}}
foreach(['ar','en']as$lang){
    $state=$controller->handle('GET',['view'=>'advances','lang'=>$lang],[]);
    $html=(new OfficeExpenseEmployeePage())->render($state,$lang,'synthetic-token');
    $dom=new DOMDocument();@$dom->loadHTML('<?xml encoding="UTF-8">'.$html);$xp=new DOMXPath($dom);
    $values=[];foreach($xp->query('//section[contains(@class,"summary-grid")]//bdi[@data-office]')as$n)$values[$n->getAttribute('data-office')][]=trim($n->textContent);
    verify(($values['egypt']??[])===['10.10 USD','1000.00 EGP','3.30 USD'],$lang.' Egypt advance and return totals stay separate');
    verify(($values['china']??[])===['20.20 USD','180.29 CNY','4.40 USD'],$lang.' China originals stay separate from Egypt USD');
    verify(str_contains($html,$lang==='ar'?'مسودات مرتجعات العهد':'Draft advance returns'),$lang.' returns have a visible heading');
    verify(str_contains($html,$lang==='ar'?'لا تمثل رصيد موظف أو إثبات دفع':'not a staff balance or proof of payment'),$lang.' draft totals cannot imply paid or settled funds');
    $state=$controller->handle('GET',['view'=>'advances','search'=>'request 2','lang'=>$lang],[]);
    $filtered=(new OfficeExpenseEmployeePage())->render($state,$lang,'synthetic-token');
    verify(str_contains($filtered,'3.30 USD')&&!str_contains($filtered,'10.10 USD')&&!str_contains($filtered,'20.20 USD'),$lang.' summary follows authorized filtered rows');
}
echo "$passed passed, $failed failed\n";exit($failed?1:0);
