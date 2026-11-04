<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||$argc!==2)exit(2);
require __DIR__.'/fixtures/m119_native_bootstrap.php';
require dirname(__DIR__,2).'/custom/chinaplanetofficeexpenses/class/OfficeExpenseNativeTreasury.php';
$db=m119NativeDatabase('EGP',1);$passed=0;$failed=0;
function dc(string $name,callable $test):void{global$passed,$failed;try{$test();$passed++;echo "PASS $name\n";}catch(Throwable$e){$failed++;echo 'FAIL '.$name.': '.$e->getMessage()."\n";}}
function de($a,$b):void{if($a!==$b)throw new RuntimeException(var_export([$a,$b],true));}
function scalar(string $sql){global$db;return $db->fetch_object($db->query($sql))->value;}
dc('SQLite fixture models the eight-place decimal text cast on aggregate residue',function(){
    de(scalar('SELECT CAST(CAST(SUM(amount) AS DECIMAL(30,8)) AS CHAR) AS value FROM (SELECT 123.45 AS amount UNION ALL SELECT -23.15)'), '100.30000000');
});
dc('tiny positive negative zero and rounding values remain fixed-point text',function(){
    foreach(['0.00000001'=>'0.00000001','-0.00000001'=>'-0.00000001','0'=>'0.00000000','123.45'=>'123.45000000','1.234567891'=>'1.23456789']as$input=>$expected)de(scalar('SELECT CAST(CAST('.$input.' AS DECIMAL(30,8)) AS CHAR) AS value'),$expected);
});
dc('nested conditional aggregate and empty cash aggregate retain native cast scale',function(){
    de(scalar('SELECT CAST(CAST(COALESCE(SUM(CASE WHEN amount < 0 THEN -amount ELSE 0 END),0) AS DECIMAL(30,8)) AS CHAR) AS value FROM (SELECT -23.15 AS amount UNION ALL SELECT 123.45)'), '23.15000000');
    de(scalar('SELECT CAST(CAST(COALESCE(SUM(amount),0) AS DECIMAL(30,8)) AS CHAR) AS value FROM (SELECT 1 AS amount WHERE 0)'), '0.00000000');
});
dc('SQL string literal that resembles a cast is never rewritten',function(){
    $text='CAST(CAST(123.45 AS DECIMAL(30,8)) AS CHAR)';de(scalar("SELECT '".$text."' AS value"),$text);
});
dc('non-cast numeric SELECT output is not normalized by the fixture',function(){de(scalar('SELECT 123.45 AS value'),123.45);});
dc('NULL cast remains NULL rather than a fabricated zero amount',function(){de(scalar('SELECT CAST(CAST(NULL AS DECIMAL(30,8)) AS CHAR) AS value'),null);});
dc('production treasury rejects floating scientific malformed and excessive-precision wire values',function(){
    $method=new ReflectionMethod(OfficeExpenseNativeTreasury::class,'decimal');
    foreach([100.3,'100.30000000000001','1.0e-8','','NaN']as$value){$denied=false;try{$method->invoke(null,$value);}catch(RuntimeException$e){$denied=true;}de($denied,true);}
    de($method->invoke(null,'0.00000001'),'0.00000001');de($method->invoke(null,'100.30000000'),'100.30');
});
echo "$passed passed, $failed failed; test-only SQLite dialect mapping, not MariaDB acceptance\n";exit($failed?1:0);
