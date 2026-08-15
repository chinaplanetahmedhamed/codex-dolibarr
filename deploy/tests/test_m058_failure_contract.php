<?php
define('MAIN_DB_PREFIX','llx_');
$tmp=sys_get_temp_dir().'/m058-failure-'.bin2hex(random_bytes(5));
mkdir($tmp,0700,true);
$conf=(object)array('entity'=>1,'chinaplanetoperations'=>(object)array('dir_output'=>$tmp));
$GLOBALS['conf']=$conf;
require dirname(__DIR__,2).'/custom/chinaplanetoperations/class/InternalTaskAssetService.class.php';
class M058FailureDb{public $beginResult=false;public $commitResult=false;public function begin(){return $this->beginResult;}public function commit(){return $this->commitResult;}public function escape($value){return addslashes($value);}}
class M058FailureActor{public $id=1;public $entity=1;public $statut=1;public $fk_soc=null;public function hasRight($module,$right){return true;}}
function failureAssert($condition,$message){echo ($condition?'PASS ':'FAIL ').$message."\n";if(!$condition)throw new RuntimeException($message);}
function reflectionReject($object,$method,array $arguments=array()){$reflection=new ReflectionMethod($object,$method);$reflection->setAccessible(true);try{$reflection->invokeArgs($object,$arguments);return false;}catch(Throwable $e){return true;}}
try{
    $db=new M058FailureDb();$actor=new M058FailureActor();$tasks=new InternalTaskService($db,$actor,1);$assets=new InternalTaskAssetService($db,$actor,1,$tmp);
    failureAssert(reflectionReject($tasks,'beginTransaction'),'task begin failure is fatal');
    failureAssert(reflectionReject($tasks,'commitTransaction'),'task commit failure is fatal');
    failureAssert(reflectionReject($assets,'beginTransaction'),'asset begin failure is fatal');
    failureAssert(reflectionReject($assets,'commitTransaction'),'asset commit failure is fatal');
    $safe=$tmp.'/safe.txt';file_put_contents($safe,'safe');chmod($safe,0644);$secure=new ReflectionMethod($assets,'secureFile');$secure->setAccessible(true);$secure->invoke($assets,$safe);failureAssert((fileperms($safe)&0777)===0600,'asset file permissions are verified as 0600');
    $real=$tmp.'/real';mkdir($real,0700);$link=$tmp.'/linked';symlink($real,$link);failureAssert(reflectionReject($assets,'ensureDirectory',array($link,0700)),'symlinked asset directory is rejected');
}finally{
    if(is_link($tmp.'/linked'))unlink($tmp.'/linked');if(is_file($tmp.'/safe.txt'))unlink($tmp.'/safe.txt');if(is_dir($tmp.'/real'))rmdir($tmp.'/real');if(is_dir($tmp))rmdir($tmp);
}
echo "M058_FAILURE_CONTRACT=PASS\n";
