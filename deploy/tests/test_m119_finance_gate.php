<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);
require dirname(__DIR__,2).'/custom/chinaplanetofficeexpenses/class/OfficeExpenseFinanceGate.php';
$base=sys_get_temp_dir().'/m119-finance-gate-'.bin2hex(random_bytes(8));mkdir($base,0700);$owner=fileowner(dirname(__DIR__,2).'/custom/chinaplanetofficeexpenses/class/OfficeExpenseFinanceGate.php');$pass=0;$fail=0;
function gt(string $name,callable $f): void {global$pass,$fail;try{$f();$pass++;echo 'PASS '.$name."\n";}catch(Throwable $e){$fail++;fwrite(STDERR,'FAIL '.$name.': '.$e->getMessage()."\n");}}
function ge($a,$b): void {if($a!==$b)throw new RuntimeException('Financial gate assertion differs.');}
function gf(string $name,string $bytes,int $mode=0644): string {global$base,$owner;$p=$base.'/'.$name;file_put_contents($p,$bytes);chmod($p,$mode);if(fileowner($p)!==$owner&&function_exists('posix_geteuid')&&posix_geteuid()===0&&!chown($p,$owner))throw new RuntimeException('Owned fixture ownership setup failed.');return $p;}
try{
    gt('canonical release switch permits the separate business-configured bridge',function(){ge(OfficeExpenseFinanceGate::enabled(),true);ge(OfficeExpenseFinanceGate::read(gf('enabled.json',"{\"version\":1,\"enabled\":true}\n")),true);});
    gt('missing paused malformed duplicate widened and oversized switches fail closed',function()use($base){ge(OfficeExpenseFinanceGate::read($base.'/absent'),false);foreach(["{\"version\":1,\"enabled\":false}\n",'bad json','{"version":1,"enabled":false,"enabled":true}','{"version":1,"enabled":true,"user":1}',str_repeat('x',129)]as$i=>$bytes)ge(OfficeExpenseFinanceGate::read(gf('bad-'.$i.'.json',$bytes)),false);});
    gt('caller-writable symlink and hardlinked switches cannot enable execution',function()use($base){$bytes="{\"version\":1,\"enabled\":true}\n";ge(OfficeExpenseFinanceGate::read(gf('writable.json',$bytes,0666)),false);$target=gf('target.json',$bytes);symlink($target,$base.'/symlink.json');ge(OfficeExpenseFinanceGate::read($base.'/symlink.json'),false);link($target,$base.'/hardlink.json');ge(OfficeExpenseFinanceGate::read($target),false);ge(OfficeExpenseFinanceGate::read($base.'/hardlink.json'),false);});
    if(function_exists('posix_geteuid')&&posix_geteuid()===0){
        gt('actual www-data reads root-owned release state but cannot bypass a pause or unsafe permissions',function()use($base){
            $dir=$base.'/native';mkdir($dir,0755);mkdir($dir.'/class',0755);chmod($base,0755);copy(dirname(__DIR__,2).'/custom/chinaplanetofficeexpenses/class/OfficeExpenseFinanceGate.php',$dir.'/class/OfficeExpenseFinanceGate.php');chmod($dir.'/class/OfficeExpenseFinanceGate.php',0644);
            file_put_contents($dir.'/reader.php',"<?php require __DIR__.'/class/OfficeExpenseFinanceGate.php'; echo OfficeExpenseFinanceGate::enabled()?'1':'0';");chmod($dir.'/reader.php',0644);
            $read=static function()use($dir){$p=proc_open(['runuser','-u','www-data','--',PHP_BINARY,$dir.'/reader.php'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,null,['PATH'=>'/usr/sbin:/usr/bin:/sbin:/bin']);if(!is_resource($p))throw new RuntimeException('Service reader unavailable.');fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);ge(proc_close($p),0);ge($err,'');return $out;};
            file_put_contents($dir.'/finance-bridge.json',"{\"version\":1,\"enabled\":true}\n");chmod($dir.'/finance-bridge.json',0644);ge($read(),'1');file_put_contents($dir.'/finance-bridge.json',"{\"version\":1,\"enabled\":false}\n");ge($read(),'0');file_put_contents($dir.'/finance-bridge.json',"{\"version\":1,\"enabled\":true}\n");chmod($dir.'/finance-bridge.json',0666);ge($read(),'0');
        });
    }else echo "SKIP actual Linux www-data case requires the isolated root CI container\n";
}finally{$clean=function($p)use(&$clean){if(is_link($p)||is_file($p)){unlink($p);return;}foreach(scandir($p)as$n)if($n!=='.'&&$n!=='..')$clean($p.'/'.$n);rmdir($p);};if(str_starts_with(basename($base),'m119-finance-gate-'))$clean($base);}
echo "$pass passed, $fail failed\n";exit($fail?1:0);
