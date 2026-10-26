<?php
$p=__DIR__.'/../../../custom/chinaplanetoperations/class/CustomerAccountAmount.class.php';
if(!file_exists($p)){fwrite(STDERR,"FAIL: exact amount implementation missing\n");exit(1);}require $p;
$n=0;function eq($a,$b){global $n;if($a!==$b)throw new Exception(var_export([$a,$b],true));$n++;}
function bad($f){global $n;try{$f();}catch(InvalidArgumentException $e){$n++;return;}throw new Exception('Expected rejection');}
eq(CustomerAccountAmount::base('100.00','0.140000000000'),'1400');
eq(CustomerAccountAmount::base('0.05','0.100000000000'),'1');
eq(CustomerAccountAmount::base('14000','0.142857'),'200000');
eq(CustomerAccountAmount::base('999999999.99','100000'),'9999999999900000');
foreach(['0','-1','1e3','NaN','1.001','1000000000',''] as $s)bad(function()use($s){CustomerAccountAmount::minor($s);});
foreach(['0','-1','1e-2','0.0000000000001','100001'] as $s)bad(function()use($s){CustomerAccountAmount::rate($s);});
$a=CustomerAccountAmount::allocate('0.10','0.1',[['amount'=>'0.05'],['amount'=>'0.05']]);eq(array_column($a,'base_minor'),['1','0']);eq($a[1]['rounding_minor'],'-1');
bad(function(){CustomerAccountAmount::allocate('10','1',[['amount'=>'9']]);});
eq(CustomerAccountAmount::money('-1234'),'-12.34');echo "PASS amount $n assertions\n";
