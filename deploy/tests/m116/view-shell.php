<?php
require __DIR__.'/../../../custom/chinaplanetoperations/class/CustomerAccountView.class.php';if(!method_exists('CustomerAccountView','fragment'))throw new Exception('Native shell fragment missing');$s=['rows'=>[]];$c=['lang'=>'en','print'=>false];$f=CustomerAccountView::fragment($s,$c);if(strpos($f,'<html')!==false||strpos($f,'customer_account_presentations.js')===false||strpos($f,'account-config')===false)throw new Exception('Invalid fragment');$full=CustomerAccountView::render($s,$c);if(substr_count($full,'<html')!==1)throw new Exception('Invalid standalone print document');echo "PASS native fragment / print document boundary\n";

if(strpos(CustomerAccountView::fragment($s,["lang"=>"ar"]),'dir="rtl"')===false)throw new Exception("Arabic fragment direction missing");
