<?php
// Load the actual module descriptor with a minimal native module base.
$root=sys_get_temp_dir().'/m116-rights-'.bin2hex(random_bytes(6));mkdir($root.'/core/modules',0700,true);
file_put_contents($root.'/core/modules/DolibarrModules.class.php','<?php #[\AllowDynamicProperties] class DolibarrModules { public function __construct($db) {} }');
define('DOL_DOCUMENT_ROOT',$root);require __DIR__.'/../../../custom/chinaplanetoperations/core/modules/modChinaPlanetOperations.class.php';
$module=new modChinaPlanetOperations(null);$account=[];
foreach($module->rights as $right)if(strpos($right[4],'account_')===0)$account[$right[4]]=[$right[0],$right[3]];
$expected=['account_read'=>[500580,0],'account_edit'=>[500581,0],'account_post'=>[500582,0],'account_reallocate'=>[500583,0],'account_export'=>[500584,0]];
if($account!==$expected)throw new RuntimeException('Finance definition IDs must avoid the observed public-request/QC IDs and remain default-off.');
foreach($module->rights as $right)if($right[0]>=500525&&$right[0]<=500529)throw new RuntimeException('Do not redefine live public-request/QC permission IDs.');
unlink($root.'/core/modules/DolibarrModules.class.php');rmdir($root.'/core/modules');rmdir($root.'/core');rmdir($root);
echo "PASS finance definition IDs avoid observed live permissions; all five remain default-off\n";
