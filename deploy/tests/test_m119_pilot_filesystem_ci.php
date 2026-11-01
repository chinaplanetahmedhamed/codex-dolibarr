<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('CI')!=='true'||getenv('GITHUB_ACTIONS')!=='true'||PHP_OS_FAMILY!=='Linux'||!function_exists('posix_geteuid')||posix_geteuid()!==0){fwrite(STDERR,"FAIL requires the disposable root-owned GitHub Linux fixture\n");exit(1);}
$root='/var/lib/chinaplanetos-office-expenses';$web='/var/lib/m119-ci-web-'.bin2hex(random_bytes(8));
if(file_exists($root)||is_link($root)){fwrite(STDERR,"FAIL fixture root already exists; no changes made\n");exit(1);}
$source=dirname(__DIR__,2).'/custom/chinaplanetoperations/office_expenses/class/OfficeExpensePilotConfiguration.php';
$config=array('version'=>1,'enabled'=>true,'environment'=>'backup-erp','entity'=>1,'user_id'=>2001,'base_currency'=>'USD','offices'=>array('egypt'=>array('EGP'),'china'=>array('CNY')),'precision'=>array('EGP'=>2,'CNY'=>2,'USD'=>2),'private_root'=>$root.'/receipts');
$passed=0;$failed=0;$file=$root.'/pilot.json';
function ftest(string $name,bool $allowed): void {
    global $web,$passed,$failed;
    $code='require $argv[1]."/OfficeExpensePilotConfiguration.php";try{$c=OfficeExpensePilotConfiguration::read(OfficeExpensePilotConfiguration::PATH,$argv[1]);if($c->precision()!==array("EGP"=>2,"CNY"=>2,"USD"=>2))exit(2);echo "CONFIG_READ_OK";}catch(Throwable $e){echo get_class($e).": ".$e->getMessage();exit(1);}';
    $pipes=array();$p=proc_open(array('runuser','-u','www-data','--',PHP_BINARY,'-r',$code,$web),array(0=>array('pipe','r'),1=>array('pipe','w'),2=>array('pipe','w')),$pipes);
    if(!is_resource($p))throw new RuntimeException('Cannot start existing low-privilege service identity.');fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($p);
    if(($allowed&&$exit===0&&$out==='CONFIG_READ_OK')||(!$allowed&&$exit===1&&strpos($out,'Exception')!==false)){echo "PASS $name\n";$passed++;}
    else{echo "FAIL $name: ".$out.(strlen($err)?' (child dependency error)':'')."\n";$failed++;}
}
try {
    mkdir($root,0750);chgrp($root,33);mkdir($web,0755);copy($source,$web.'/OfficeExpensePilotConfiguration.php');chmod($web.'/OfficeExpensePilotConfiguration.php',0644);
    file_put_contents($file,json_encode($config,JSON_THROW_ON_ERROR));chgrp($file,33);chmod($file,0640);touch($file,time()-3600,time()-7200);
    ftest('actual www-data can read a root-owned private config on first access',true);
    chmod($file,0660);ftest('www-data rejects group-writable pilot configuration',false);chmod($file,0640);
    chown($file,33);ftest('www-data rejects application-owned pilot configuration',false);chown($file,0);
    chmod($file,0644);ftest('www-data rejects world-readable pilot configuration',false);chmod($file,0640);
    chmod($root,0770);ftest('www-data rejects writable configuration parent',false);chmod($root,0750);
    rename($file,$root.'/saved.json');symlink($root.'/saved.json',$file);ftest('www-data rejects symlinked private configuration',false);unlink($file);rename($root.'/saved.json',$file);
    ftest('restored private configuration remains readable without changing scope',true);
} finally {
    if(is_link($file)||is_file($file))unlink($file);if(is_file($root.'/saved.json'))unlink($root.'/saved.json');rmdir($root);
    unlink($web.'/OfficeExpensePilotConfiguration.php');rmdir($web);
}
echo "$passed passed, $failed failed; actual Linux www-data filesystem fixture only; no accounts or native config changed\n";exit($failed?1:0);
