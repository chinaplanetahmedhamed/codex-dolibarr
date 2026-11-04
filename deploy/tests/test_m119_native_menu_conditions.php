<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||$argc!==2)exit(2);
require __DIR__.'/fixtures/m119_native_bootstrap.php';
m119NativeDatabase('USD',1);
require dirname(__DIR__,2).'/custom/chinaplanetofficeexpenses/core/modules/modChinaPlanetOfficeExpenses.class.php';
$conf->chinaplanetofficeexpenses=(object)['enabled'=>1];$conf->modules['chinaplanetofficeexpenses']=1;
$user=new class {public $id=1;public $admin=1;public $socid=0;public $read=false;public function hasRight($module,$right){return $this->read&&$module==='chinaplanetofficeexpenses'&&$right==='read';}};
$menu=(new modChinaPlanetOfficeExpenses(null))->menu[0];$passed=0;$failed=0;
function mt(string $name,callable $test): void {global $passed,$failed;try{$test();$passed++;echo "PASS $name\n";}catch(Throwable $e){$failed++;fwrite(STDERR,"FAIL $name: ".$e->getMessage()."\n");}}
function me($actual,$expected): void {if($actual!==$expected)throw new RuntimeException('Native menu evaluator result differs: '.var_export($actual,true));}
function condition(string $value): bool {$result=dol_eval($value,1,1,'1');if(!is_bool($result)&&!is_int($result))throw new RuntimeException('Native evaluator refused expression: '.(string)$result);return (bool)$result;}
mt('registered top-menu condition passes the real native evaluator for internal entity-1 users',function()use($menu){me(condition($menu['enabled']),true);me($menu['type'],'top');me($menu['prefix'],'fa-receipt');me($menu['url'],'/custom/chinaplanetofficeexpenses/index.php');});
mt('enabled condition survives the native menu editor input filter without losing function quotes',function()use($menu){$_POST['m119_menu_condition']=$menu['enabled'];try{me(GETPOST('m119_menu_condition','alphanohtml',2),$menu['enabled']);}finally{unset($_POST['m119_menu_condition']);}});
mt('external and other-entity users and disabled modules remain excluded',function()use($menu,$user,$conf){
    $user->socid=99;try{me(condition($menu['enabled']),false);}finally{$user->socid=0;}
    $conf->entity=2;try{me(condition($menu['enabled']),false);}finally{$conf->entity=1;}
    $conf->chinaplanetofficeexpenses->enabled=0;unset($conf->modules['chinaplanetofficeexpenses']);try{me(condition($menu['enabled']),false);}finally{$conf->chinaplanetofficeexpenses->enabled=1;$conf->modules['chinaplanetofficeexpenses']=1;}
});
mt('menu visibility retains bootstrap administrator and exact activated staff read ceiling',function()use($menu,$user,$conf){
    me(condition($menu['perms']),true);$user->id=8;$user->admin=0;
    try{$conf->global->CPO_OE_STAFF_ACCESS_ENABLED='0';$user->read=true;me(condition($menu['perms']),false);$conf->global->CPO_OE_STAFF_ACCESS_ENABLED='1';me(condition($menu['perms']),true);$user->read=false;me(condition($menu['perms']),false);}finally{$user->id=1;$user->admin=1;$user->read=false;}
});
echo "$passed passed, $failed failed\n";exit($failed?1:0);
