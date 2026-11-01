<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli' || !in_array($argc,array(2,3),true) || !is_file($argv[1].'/user/class/user.class.php')
    || ($argc===3 && !in_array($argv[2],array('--without-auth','--with-auth'),true))) {
    fwrite(STDERR,"Usage: php test_m119_native_identity.php /path/to/dolibarr/htdocs [--without-auth|--with-auth]\n");exit(1);
}
$withoutAuth=($argv[2]??'')!=='--with-auth'; // Auth tests require an explicit, separately authorized opt-in.
ob_start(); // Allow a real PHP session round-trip without printing headers first.
require_once __DIR__.'/fixtures/m119_native_bootstrap.php';
require_once __DIR__.'/fixtures/m119_native_identity.php';
require_once dirname(__DIR__).'/tools/office_expenses/LocalDraftStore.php';
require_once dirname(__DIR__,2).'/custom/chinaplanetoperations/office_expenses/class/OfficeExpenseDraftService.php';
require_once dirname(__DIR__,2).'/custom/chinaplanetoperations/office_expenses/class/OfficeExpenseEmployeeController.php';
require_once dirname(__DIR__).'/tools/office_expenses/LocalNativeDraftWriter.php';
$passed=0;$failed=0;
function icheck($actual,$expected): void {if ($actual !== $expected) throw new RuntimeException('Expected '.var_export($expected,true).' got '.var_export($actual,true));}
function ireject(callable $action): void {try {$action();}catch(DomainException $e){return;}throw new RuntimeException('Expected identity rejection.');}
function itest(string $label,callable $action): void {
    global $passed,$failed;
    try {$action();echo "PASS $label\n";$passed++;}catch(Throwable $e){echo "FAIL $label: ".$e->getMessage()."\n";$failed++;}
}
function iservice(User $identity): OfficeExpenseDraftService {
    global $conf;
    return new OfficeExpenseDraftService(new LocalDraftStore(new PDO('sqlite::memory:')),new OfficeExpenseMoney(),$identity,(int)$conf->entity,array('egypt'=>array('EGP')),'EGP');
}
if (!$withoutAuth) itest('native password verifier accepts only the synthetic active account in its entity',function(){
    list($db,$secret)=m119IdentityDatabase(true);
    icheck(checkLoginPassEntity('synthetic-7',$secret,1,array('dolibarr')),'synthetic-7');
    icheck(checkLoginPassEntity('synthetic-7',$secret.'x',1,array('dolibarr')),'');
    icheck(checkLoginPassEntity('synthetic-9',$secret,1,array('dolibarr')),'');
    icheck(checkLoginPassEntity('synthetic-11',$secret,1,array('dolibarr')),'');
});
itest('actual User fetch and direct rights authorize the employee draft interface',function(){
    m119IdentityDatabase();$identity=m119FetchIdentity('synthetic-7');
    icheck($identity->id,7);icheck((int)$identity->hasRight('expensereport','creer'),1);
    icheck(iservice($identity)->officeOptions(),array('egypt'=>array('EGP')));
    $controller=new OfficeExpenseEmployeeController(iservice($identity),'synthetic-token','synthetic-token');
    icheck($controller->handle('GET',array(),array())['status'],200);
    ireject(function(){iservice(m119FetchIdentity('synthetic-8'))->listOwn();});
});
itest('actual native group rights and fresh-request revocation remain entity scoped',function(){
    global $db;
    m119IdentityDatabase();
    $db->query('INSERT INTO llx_usergroup_user(entity,fk_user,fk_usergroup) VALUES(1,8,70)');
    $db->query('INSERT INTO llx_usergroup_rights(entity,fk_usergroup,fk_id) VALUES(2,70,90001)');
    icheck((int)m119FetchIdentity('synthetic-8')->hasRight('expensereport','creer'),0);
    $db->query('UPDATE llx_usergroup_rights SET entity=1');
    icheck((int)m119FetchIdentity('synthetic-8')->hasRight('expensereport','creer'),1);
    $db->query('DELETE FROM llx_usergroup_rights');
    ireject(function(){iservice(m119FetchIdentity('synthetic-8'))->listOwn();});
    $db->query('DELETE FROM llx_user_rights WHERE fk_user=7');
    ireject(function(){iservice(m119FetchIdentity('synthetic-7'))->listOwn();});
});
itest('disabled expired future-valid and external native identities cannot use draft services',function(){
    global $db;
    m119IdentityDatabase();
    $disabled=m119FetchIdentity('synthetic-9');icheck((int)$disabled->status,0);
    ireject(function()use($disabled){iservice($disabled)->listOwn();});
    $external=m119FetchIdentity('synthetic-10');icheck((int)$external->socid,99);
    ireject(function()use($external){iservice($external)->listOwn();});
    $db->query("UPDATE llx_user SET dateendvalidity='2000-01-01' WHERE rowid=7");
    $expired=m119FetchIdentity('synthetic-7');icheck($expired->isNotIntoValidityDateRange(),1);
    ireject(function()use($expired){iservice($expired)->listOwn();});
    $db->query("UPDATE llx_user SET dateendvalidity=NULL,datestartvalidity='2100-01-01' WHERE rowid=7");
    $future=m119FetchIdentity('synthetic-7');icheck($future->isNotIntoValidityDateRange(),1);
    ireject(function()use($future){iservice($future)->listOwn();});
});
itest('actual native permission is ineffective when the expense module is disabled',function(){
    global $conf;
    m119IdentityDatabase();$identity=m119FetchIdentity('synthetic-7');
    $conf->expensereport->enabled=0;
    $conf->modules=array();
    ireject(function()use($identity){iservice($identity)->listOwn();});
});
itest('native currentToken and newToken remain distinct at the employee controller boundary',function(){
    m119IdentityDatabase();$identity=m119FetchIdentity('synthetic-7');$service=iservice($identity);
    // Model the values after native main.inc's roll; do not execute or replace its CSRF check.
    $_SESSION=array('token'=>'synthetic-validated-request','newtoken'=>'synthetic-next-form');
    icheck(currentToken(),'synthetic-validated-request');icheck(newToken(),'synthetic-next-form');
    $controller=new OfficeExpenseEmployeeController($service,currentToken(),newToken());
    $post=array('action'=>'save','token'=>newToken(),'submission_key'=>'synthetic-native-token','kind'=>'expense_claim',
        'office'=>'egypt','amount'=>'7.10','currency'=>'EGP','occurred_on'=>'2026-10-31',
        'source_ref'=>'SYNTHETIC token receipt','purpose'=>'SYNTHETIC request');
    icheck($controller->handle('POST',array(),$post)['status'],403);icheck(count($service->listOwn()),0);
    $post['token']=currentToken();
    icheck($controller->handle('POST',array(),$post)['status'],303);icheck(count($service->listOwn()),1);
    $_SESSION=array();
});
itest('actual native employee identity owns an unpaid synthetic draft and disabled identity cannot retry',function(){
    global $db,$user,$conf;
    m119IdentityDatabase();$user=m119FetchIdentity('synthetic-7');$service=iservice($user);
    $draft=$service->submit(array('kind'=>'expense_claim','office'=>'egypt','amount'=>'114.00','currency'=>'EGP',
        'occurred_on'=>'2026-10-31','source_ref'=>'SYNTHETIC native identity receipt','purpose'=>'SYNTHETIC supplies'),'native-identity-claim');
    $writer=new LocalNativeDraftWriter($db,$user,$conf,new OfficeExpenseMoney());
    $bridge=new OfficeExpenseNativeDraftBridge($service,$writer);
    $link=$bridge->createDraft($draft['id'],7,'explicit_zero_tax');
    icheck($link['paid'],false);icheck($link['status'],'draft');icheck($link['amount'],'114.00');
    $row=$db->fetch_object($db->query('SELECT fk_user_author,fk_user_creat,entity FROM llx_expensereport WHERE rowid='.(int)$link['expense_report_id']));
    icheck((int)$row->fk_user_author,7);icheck((int)$row->fk_user_creat,7);icheck((int)$row->entity,1);
    $user->status=0;
    ireject(function()use($writer,$draft){$writer->createOrFind($draft,7,'explicit_zero_tax');});
});
if (!$withoutAuth) itest('PHP session round-trip restores native identity and entity without serializing rights',function(){
    global $conf;
    list($db,$secret)=m119IdentityDatabase(true);
    $login=checkLoginPassEntity('synthetic-7',$secret,1,array('dolibarr'));
    session_save_path(DOL_DATA_ROOT);session_name('M119NATIVE');session_id(bin2hex(random_bytes(24)));session_start();
    $_SESSION=array('dol_login'=>$login,'dol_entity'=>1,'dol_logindate'=>time());
    $sessionId=session_id();session_write_close();unset($_SESSION);
    session_id($sessionId);session_start();
    $identity=m119FetchIdentity($_SESSION['dol_login'],(int)$_SESSION['dol_entity']);
    icheck($identity->id,7);icheck(iservice($identity)->listOwn(),array());
    icheck(array_keys($_SESSION),array('dol_login','dol_entity','dol_logindate'));
    ireject(function(){m119FetchIdentity('synthetic-11',(int)$_SESSION['dol_entity']);});
    session_destroy();
});
echo "$passed passed, $failed failed; ".($withoutAuth?'2 auth/session scenarios skipped; no credentials created':'real native auth/User methods')."; synthetic state only; full main.inc HTTP acceptance pending\n";
ob_end_flush();exit($failed?1:0);
