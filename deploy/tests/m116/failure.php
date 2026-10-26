<?php
require __DIR__.'/fixture.php';require __DIR__.'/../../../custom/chinaplanetoperations/class/CustomerAccountService.class.php';
class AccountBeginFailureDb extends AccountTestDb {public function begin(){return false;}}
$db=new AccountBeginFailureDb();$u=new AccountTestActor();$s=new CustomerAccountService($db,new CustomerAccountPolicy($db,$u,1),1);$data=['date'=>'2026-10-26','direction'=>'received','amount'=>'1','currency'=>'USD','rate'=>'1','rate_date'=>'2026-10-26','rate_source'=>'Manual','description'=>'Must not persist','allocations'=>[['amount'=>'1']]];
try{$s->mutate('save',1,$data,'begin-failure-00001');}catch(Throwable $e){}
if($db->scalar('SELECT COUNT(*) FROM llx_cpo_account_entry')!==0)throw new Exception('Failed begin allowed partial writes');echo "PASS failed transaction begin writes nothing\n";
