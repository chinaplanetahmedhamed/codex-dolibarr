<?php
require __DIR__.'/fixture.php';require __DIR__.'/../../../custom/chinaplanetoperations/class/CustomerAccountService.class.php';
$db=new AccountTestDb();$actor=new AccountTestActor();$s=new CustomerAccountService($db,new CustomerAccountPolicy($db,$actor,1),1);
$d=['date'=>'2026-10-26','direction'=>'received','amount'=>'100','currency'=>'USD','rate'=>'1','rate_date'=>'2026-10-26','rate_source'=>'Manual','description'=>'Audit scope fixture','allocations'=>[['project'=>10,'amount'=>'60'],['project'=>11,'amount'=>'40']]];
$r=$s->mutate('save',1,$d,'audit-scope-create-0001');$s->mutate('post',1,['id'=>$r['id'],'version'=>1],'audit-scope-post-00001');
$s->mutate('reallocate',1,['id'=>$r['id'],'version'=>2,'reason'=>'Consolidate in project 10','allocations'=>[['project'=>10,'amount'=>'100']]],'audit-scope-move-00001');
if(count($s->auditTrail(1))!==3)throw new Exception('Authorized full history missing');
$actor->hidden=[11];if($s->statement(1)['balance_minor']!=='10000')throw new Exception('Current allocations should remain accessible');
$denied=false;try{$s->auditTrail(1);}catch(InternalTaskAccessException $e){$denied=true;}catch(RuntimeException $e){if($e->getCode()===403)$denied=true;else throw $e;}
if(!$denied)throw new Exception('Audit disclosed inaccessible historical allocations');
echo "PASS audit scopes: authorized history visible, current cash accessible, inaccessible historical audit rejected\n";
