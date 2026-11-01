<?php
declare(strict_types=1);
$root=dirname(__DIR__,2);require_once $root.'/custom/chinaplanetofficeexpenses/class/OfficeExpenseStaffPage.php';
function checkUi($ok,$name){if(!$ok)throw new RuntimeException($name);echo "PASS $name\n";}
$source=file_get_contents($root.'/custom/chinaplanetofficeexpenses/users.php');
checkUi(strpos($source,"style-src-attr 'unsafe-inline'")!==false,'native loader display:none is permitted independently of nonce-protected scripts');
checkUi(strpos($source,"script-src 'self'")!==false&&strpos($source,"script-src 'unsafe-inline'")===false,'inline script execution is not broadly enabled');
$member=['user_id'=>5,'name'=>'<Moaz>','login'=>'moaz','office'=>'egypt','record_scope'=>'branch','revision'=>1,'active'=>true,'manageable'=>true,'grants'=>['expenses'=>['read','create'],'advances'=>['read']]];
foreach(['ar','en']as$lang){
 $state=['language'=>$lang,'selected'=>['id'=>5,'name'=>'<Moaz>','login'=>'moaz'],'office'=>'egypt','offices'=>['egypt'=>['EGP'],'china'=>['CNY']],'members'=>[$member],'directory'=>[['id'=>5,'name'=>'<Moaz>','login'=>'moaz']],'error'=>null];
 $html=(new OfficeExpenseStaffPage())->render($state,'test-token');
 checkUi(strpos($html,'&lt;Moaz&gt;')!==false&&strpos($html,'<Moaz>')===false,$lang.' staff values remain escaped');
 checkUi(strpos($html,'office=egypt')!==false&&strpos($html,'staff_id=5')!==false,$lang.' branch and staff survive navigation');
 checkUi(substr_count($html,'class="permission-unavailable"')>=6,$lang.' full permission overview shows unsupported sections explicitly unavailable');
 checkUi(strpos($html,'name="permissions[expenses]"')!==false&&strpos($html,'name="permissions[advances]"')!==false,$lang.' supported existing POST contract retained');
 checkUi(strpos($html,'name="permissions[treasury]"')===false&&strpos($html,'name="permissions[reports]"')===false,$lang.' unavailable grants never submitted');
 checkUi(strpos($html,'class="staff-mobile-menu"')!==false,$lang.' navigation remains accessible on mobile without JavaScript');
 checkUi(substr_count($html,'aria-disabled="true"')>=7,$lang.' original menu options visible without false functional links');
}
