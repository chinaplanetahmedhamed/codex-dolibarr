<?php
declare(strict_types=1);
$root=dirname(__DIR__,2);
$controller=$root.'/custom/chinaplanetofficeexpenses/class/OfficeExpenseEmployeeController.php';
if (!is_file($controller)) { fwrite(STDERR,"FAIL authenticated employee draft controller is missing\n");exit(1); }
require_once $controller;
require_once $root.'/custom/chinaplanetofficeexpenses/class/OfficeExpenseEmployeePage.php';
require_once $root.'/deploy/tools/office_expenses/LocalDraftStore.php';
$pass=0;$fail=0;
function ueq($actual,$expected): void { if ($actual!==$expected) throw new RuntimeException('Expected '.var_export($expected,true).' got '.var_export($actual,true)); }
function ut(string $name,callable $f): void {global $pass,$fail;try{$f();$pass++;echo "PASS $name\n";}catch(Throwable $e){$fail++;fwrite(STDERR,"FAIL $name: ".$e->getMessage()."\n");}}
function usetup(?PDO $pdo=null): array {
    $pdo=$pdo?:new PDO('sqlite::memory:');$store=new LocalDraftStore($pdo);
    $user=new class {public $id=7;public $socid=0;public $status=1;public $admin=0;public $allowed=true;public function hasRight($m,$r){return $this->allowed&&$m==='expensereport'&&$r==='creer';}};
    $service=new OfficeExpenseDraftService($store,new OfficeExpenseMoney(),$user,1,array('egypt'=>array('EGP','USD')),'EGP');
    return array(new OfficeExpenseEmployeeController($service,'synthetic-csrf','synthetic-csrf'),$service,$store,$user,$pdo);
}
function upost(): array {return array('action'=>'save','token'=>'synthetic-csrf','submission_key'=>'synthetic-ui-key','kind'=>'expense_claim','office'=>'egypt','amount'=>'700.10','currency'=>'EGP','occurred_on'=>'2026-10-31','source_ref'=>'SYNTHETIC receipt','purpose'=>'SYNTHETIC office supplies','quote_rate'=>'','quote_date'=>'','quote_source'=>'','settles_ref'=>'','lang'=>'en');}
ut('authorized GET renders own form with explicit currency and no posting actions',function(){
    list($c,$s)=usetup();$r=$c->handle('GET',array(),array());ueq($r['status'],200);ueq($r['offices'],array('egypt'=>array('EGP','USD')));
    $html=(new OfficeExpenseEmployeePage())->render($r,'en','synthetic-csrf');
    foreach(array('name="token"','name="amount"','name="currency"','name="submission_key"','name="source_ref"')as$needle)ueq(strpos($html,$needle)!==false,true);
    ueq(strpos($html,'value="post"'),false);ueq(strpos($html,'value="reimbursement"'),false);
});
ut('native shell rendering keeps the standalone Finens preview and app fragment distinct',function(){
    list($c)=usetup();$r=$c->handle('GET',array(),array());$page=new OfficeExpenseEmployeePage();
    $fragment=$page->render($r,'ar','synthetic-csrf',false);
    ueq(strpos($fragment,'<!doctype html>'),false);ueq(strpos($fragment,'<body'),false);ueq(strpos($fragment,'</html>'),false);
    ueq(strpos($fragment,'class="office-expenses-app" lang="ar" dir="rtl"')!==false,true);
    ueq(strpos($fragment,'<script src="/custom/chinaplanetofficeexpenses/employee.js?v=')!==false,true);
    ueq(strpos($fragment,'مرحلة تجريبية للمسؤول')!==false,true);
    ueq(strpos($fragment,'fa-circle-plus')!==false,true);ueq(strpos($fragment,'fa-circle-minus'),false);
    ueq(strpos($fragment,'sync_office_menu'),false);
    $prompt=$page->render($r,'en','synthetic-csrf',false,true);
    ueq(strpos($prompt,'name="action" value="sync_office_menu"')!==false,true);
    ueq(strpos($prompt,'name="token" value="synthetic-csrf"')!==false,true);
    $standalone=$page->render($r,'en','synthetic-csrf');
    ueq(strpos($standalone,'<!doctype html>')!==false,true);ueq(strpos($standalone,'<body class="finens-app">')!==false,true);
});
ut('sidebar exposes implemented office expense views only',function(){
    list($c)=usetup();$html=(new OfficeExpenseEmployeePage())->render($c->handle('GET',array(),array()),'en','synthetic-csrf');
    foreach(array('view=overview','view=expenses','view=advances')as$view)ueq(strpos($html,$view)!==false,true);
    foreach(array('reports.php','employees.php','payroll.php','safe-balance')as$unsupported)ueq(strpos($html,$unsupported),false);
});
ut('Dolibarr menu keeps pilot administrator default and requires explicit staff activation',function(){
    global $root;
    $descriptor=file_get_contents($root.'/custom/chinaplanetofficeexpenses/core/modules/modChinaPlanetOfficeExpenses.class.php');
    $start=strpos($descriptor,"'fk_menu'=>'0', 'type'=>'top', 'titre'=>'OfficeExpenses'");
    $end=strpos($descriptor,"\n        );",$start);
    ueq(is_string($descriptor)&&$start!==false&&$end!==false,true);
    $entry=substr($descriptor,$start,$end-$start);
    foreach(array("'type'=>'top'","'mainmenu'=>'chinaplanetexpenses'","/chinaplanetofficeexpenses/index.php",'$user->admin && $user->id == 1','getDolEntity() == 1',"'user'=>0")as$needle)ueq(strpos($entry,$needle)!==false,true);
    ueq(strpos($entry,'getDolGlobalInt("CPO_OE_STAFF_ACCESS_ENABLED") === 1 && $user->hasRight("chinaplanetofficeexpenses", "read")')!==false,true);
    foreach(array('en_US'=>'Office Expenses','ar_SA'=>'مصروفات المكتب','zh_CN'=>'办公费用')as$locale=>$label){
        $language=file_get_contents($root.'/custom/chinaplanetofficeexpenses/langs/'.$locale.'/chinaplanetofficeexpenses.lang');
        ueq(strpos($language,'OfficeExpenses='.$label)!==false,true);
    }
});
ut('POST saves exact own draft then redirects and refresh creates no second record',function(){
    list($c,$s)=usetup();$r=$c->handle('POST',array(),upost());ueq($r['status'],303);ueq(count($s->listOwn()),1);ueq($s->listOwn()[0]['amount'],'700.10');
    ueq($c->handle('GET',array('saved'=>$s->listOwn()[0]['id']),array())['status'],200);ueq(count($s->listOwn()),1);
    ueq($c->handle('POST',array(),upost())['redirect'],$r['redirect']);ueq(count($s->listOwn()),1);
});
ut('Finens register filters only owned drafts without mixing currencies or widening office scope',function(){
    list($c,$s,$store,$u)=usetup();$c->handle('POST',array(),upost());
    $p=upost();$p['submission_key']='synthetic-advance-key';$p['kind']='advance';$p['amount']='1000.00';$p['purpose']='SYNTHETIC advance';
    $r=$c->handle('POST',array(),$p);ueq(strpos($r['redirect'],'view=advances')!==false,true);
    $r=$c->handle('GET',array('view'=>'expenses','search'=>'supplies','from'=>'2026-10-01','to'=>'2026-10-31'),array());
    ueq(count($r['filtered_drafts']),1);ueq($r['filtered_drafts'][0]['amount'],'700.10');ueq(count($r['drafts']),2);
    ueq(count($c->handle('GET',array('view'=>'advances'),array())['filtered_drafts']),1);
    ueq(count($c->handle('GET',array('search'=>'missing'),array())['filtered_drafts']),0);
    $other=clone $u;$other->id=8;
    $otherService=new OfficeExpenseDraftService($store,new OfficeExpenseMoney(),$other,1,array('egypt'=>array('EGP','USD')),'EGP');
    $otherController=new OfficeExpenseEmployeeController($otherService,'synthetic-csrf','synthetic-csrf');
    $r=$otherController->handle('GET',array('view'=>'overview','owner_id'=>'7'),array());ueq($r['status'],200);ueq(count($r['filtered_drafts']),0);
});
ut('malformed register filters fail without exceptions and never alter stored drafts',function(){
    foreach(array(array('search'=>array('x')),array('from'=>'2026-02-30'),array('view'=>array('expenses')),array('from'=>'2026-10-31','to'=>'2026-10-01'))as$query){
        list($c,$s)=usetup();$c->handle('POST',array(),upost());
        $r=$c->handle('GET',$query,array());ueq($r['status'],400);ueq(count($s->listOwn()),1);
        set_error_handler(static function($severity,$message){throw new RuntimeException($message);});
        try {$html=(new OfficeExpenseEmployeePage())->render($r,'en','synthetic-csrf');}
        finally {restore_error_handler();}
        ueq(strlen($r['values']['submission_key']),32);ueq(strpos($html,'name="submission_key" value=""'),false);
    }
});
ut('CSRF missing array or expired tokens reject writes',function(){
    foreach(array('',array('synthetic-csrf'),'expired')as$token){list($c,$s)=usetup();$p=upost();$p['token']=$token;ueq($c->handle('POST',array(),$p)['status'],403);ueq(count($s->listOwn()),0);}
});
ut('guests external admins and revoked native rights cannot see the form',function(){
    foreach(array('guest','external','rights')as$case){list($c,$s,$store,$u)=usetup();if($case==='guest')$u->id=0;if($case==='external'){$u->socid=99;$u->admin=1;}if($case==='rights')$u->allowed=false;ueq($c->handle('GET',array(),array())['status'],403);}
});
ut('forged context and unsupported employee actions cannot write',function(){
    foreach(array('entity','owner_id','native_record')as$key){list($c,$s)=usetup();$p=upost();$p[$key]='999';ueq($c->handle('POST',array(),$p)['status'],422);ueq(count($s->listOwn()),0);}
    foreach(array('post','approve','pay','prepare_native')as$action){list($c,$s)=usetup();$p=upost();$p['action']=$action;ueq($c->handle('POST',array(),$p)['status'],403);ueq(count($s->listOwn()),0);}
});
ut('office forgery and IDOR are denied without leaking other drafts',function(){
    list($c,$s,$store,$u)=usetup();$p=upost();$p['office']='china';ueq($c->handle('POST',array(),$p)['status'],403);
    $c->handle('POST',array(),upost());$id=$s->listOwn()[0]['id'];$other=clone $u;$other->id=8;
    $otherService=new OfficeExpenseDraftService($store,new OfficeExpenseMoney(),$other,1,array('egypt'=>array('EGP','USD')),'EGP');
    $otherController=new OfficeExpenseEmployeeController($otherService,'synthetic-csrf','synthetic-csrf');
    $r=$otherController->handle('GET',array('draft'=>$id),array());ueq($r['status'],404);ueq($r['drafts'],array());
    ueq($otherController->handle('GET',array('entity'=>1,'owner_id'=>7),array())['drafts'],array());
    $u->id=8;$stale=$c->handle('GET',array('draft'=>$id),array());ueq($stale['status'],403);ueq($stale['drafts'],array());
});
ut('validation preserves field values and stable retry key',function(){
    list($c,$s)=usetup();$p=upost();$p['amount']='1.001';$r=$c->handle('POST',array(),$p);ueq($r['status'],422);ueq($r['values']['amount'],'1.001');ueq($r['values']['submission_key'],'synthetic-ui-key');ueq(count($s->listOwn()),0);
    $p['amount']='1.00';ueq($c->handle('POST',array(),$p)['status'],303);
});
ut('conflicting retry returns conflict without replacing the saved intent',function(){
    list($c,$s)=usetup();$c->handle('POST',array(),upost());$p=upost();$p['amount']='800';ueq($c->handle('POST',array(),$p)['status'],409);ueq($s->listOwn()[0]['amount'],'700.10');
});
ut('foreign original currency requires quote provenance and retains exact original',function(){
    list($c,$s)=usetup();$p=upost();$p['amount']='10.25';$p['currency']='USD';ueq($c->handle('POST',array(),$p)['status'],422);
    $p['quote_rate']='50';$p['quote_date']='2026-10-31';$p['quote_source']='SYNTHETIC approved quote';ueq($c->handle('POST',array(),$p)['status'],303);
    $d=$s->listOwn()[0];ueq($d['amount'],'10.25');ueq($d['currency'],'USD');ueq($d['conversion']['base_amount'],'512.50');
});
ut('employee advances and returns are separate from expenses',function(){
    list($c,$s)=usetup();$p=upost();$p['kind']='advance';ueq($c->handle('POST',array(),$p)['status'],303);ueq($s->listOwn()[0]['accounting_route'],'employee_advance_review');
    $p['kind']='advance_return';$p['submission_key']='synthetic-return-key';ueq($c->handle('POST',array(),$p)['status'],422);$p['settles_ref']='SYNTHETIC advance';ueq($c->handle('POST',array(),$p)['status'],303);
    $p['kind']='transfer';ueq($c->handle('POST',array(),$p)['status'],422);
});
ut('register retains maximum originals and separate currency totals with explicit quote evidence',function(){
    list($unused,$unusedService,$unusedStore,$user)=usetup();
    $s=new OfficeExpenseDraftService(new LocalDraftStore(new PDO('sqlite::memory:')),new OfficeExpenseMoney(),$user,1,array('egypt'=>array('EGP'),'china'=>array('CNY')),'EGP');
    foreach(array(array('china','CNY','999999999999.99'),array('egypt','EGP','0.01'))as$i=>$row){
        $p=array('kind'=>'expense_claim','office'=>$row[0],'currency'=>$row[1],'amount'=>$row[2],'occurred_on'=>'2026-10-31','source_ref'=>'SYNTHETIC boundary '.$i,'purpose'=>'SYNTHETIC original amount');
        if($row[1]!=='EGP')$p['quote']=array('rate'=>'1','date'=>'2026-10-31','source'=>'SYNTHETIC review quote');
        $s->submit($p,'synthetic-boundary-'.$i);
    }
    $c=new OfficeExpenseEmployeeController($s,'synthetic-csrf','synthetic-csrf');$r=$c->handle('GET',array('view'=>'overview'),array());
    foreach(array('ar','en')as$lang){
        $html=(new OfficeExpenseEmployeePage())->render($r,$lang,'synthetic-csrf');
        $doc=new DOMDocument();@$doc->loadHTML('<?xml encoding="UTF-8">'.$html);$xpath=new DOMXPath($doc);
        $originals=array();foreach($xpath->query('//td[contains(@class,"amount-cell")]/bdi')as$node)$originals[]=trim($node->textContent);
        sort($originals);ueq($originals,array('0.01 EGP','999999999999.99 CNY'));
        $totals=array();foreach($xpath->query('//div[contains(@class,"stat-value")]/bdi')as$node)$totals[]=trim($node->textContent);
        ueq(in_array('0.01 EGP',$totals,true),true);ueq(in_array('999999999999.99 CNY',$totals,true),true);
        ueq(strpos($html,'1.00000000')!==false,true);ueq(strpos($html,'SYNTHETIC review quote')!==false,true);
    }
});
ut('Arabic English output and malicious text are safely escaped',function(){
    list($c)=usetup();$p=upost();$p['purpose']='<script>alert(1)</script>';$c->handle('POST',array(),$p);$r=$c->handle('GET',array(),array());
    foreach(array('ar'=>'rtl','en'=>'ltr')as$lang=>$dir){$html=(new OfficeExpenseEmployeePage())->render($r,$lang,'synthetic-csrf');ueq(strpos($html,'dir="'.$dir.'"')!==false,true);ueq(strpos($html,'<script>'),false);ueq(strpos($html,'&lt;script&gt;alert(1)&lt;/script&gt;')!==false,true);}
});
ut('storage failures retain retry intent and disclose no database details',function(){
    list($c,$s,$store,$u,$pdo)=usetup();$pdo->exec("CREATE TRIGGER fail_ui_audit BEFORE INSERT ON oe_audit BEGIN SELECT RAISE(ABORT,'PRIVATE DATABASE DETAILS'); END");
    $r=$c->handle('POST',array(),upost());ueq($r['status'],503);ueq($r['values']['submission_key'],'synthetic-ui-key');ueq(strpos(json_encode($r),'PRIVATE DATABASE'),false);ueq(count($s->listOwn()),0);
});
echo "$pass passed, $fail failed\n";exit($fail?1:0);
