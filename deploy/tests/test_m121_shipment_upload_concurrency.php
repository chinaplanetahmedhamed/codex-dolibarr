<?php
/** Real local filesystem + concurrent processes; actual REST upload method/model, synthetic actor/DB only. */
namespace Luracast\Restler { class RestException extends \RuntimeException {} }
namespace {
if(!function_exists('pcntl_fork')||!function_exists('stream_socket_pair')){fwrite(STDERR,"SKIP requires pcntl and local socket pairs\n");exit(2);}
$root=dirname(__DIR__,2);$temp=sys_get_temp_dir().'/m121-upload-'.bin2hex(random_bytes(8));$owner=getmypid();
mkdir($temp.'/api/class',0700,true);mkdir($temp.'/data/chinaplanetlogistics/ship-17',0700,true);
file_put_contents($temp.'/api/class/api.class.php','<?php class DolibarrApi {public $db;} class DolibarrApiAccess {public static $user;} class CommonObject {}');
symlink($root.'/custom',$temp.'/custom');
register_shutdown_function(function()use($temp,$owner){if(getmypid()!==$owner)return;foreach(scandir($temp.'/data/chinaplanetlogistics/ship-17') as $name)if($name!=='.'&&$name!=='..')unlink($temp.'/data/chinaplanetlogistics/ship-17/'.$name);if(is_file($temp.'/absent-target'))unlink($temp.'/absent-target');foreach(glob($temp.'/result-*') as $p)unlink($p);unlink($temp.'/custom');unlink($temp.'/api/class/api.class.php');rmdir($temp.'/api/class');rmdir($temp.'/api');rmdir($temp.'/data/chinaplanetlogistics/ship-17');rmdir($temp.'/data/chinaplanetlogistics');rmdir($temp.'/data');rmdir($temp);});
define('DOL_DOCUMENT_ROOT',$temp);define('DOL_DATA_ROOT',$temp.'/data');define('MAIN_DB_PREFIX','synthetic_');
function dol_mkdir($path){return is_dir($path)||mkdir($path,0700,true);}
function dol_sanitizeFileName($name){return preg_replace('/[^A-Za-z0-9._-]/','_',$name);}
require_once $root.'/custom/chinaplanetlogistics/api/class/api_chinaplanetlogistics.class.php';
class UploadDb121 {
 public function query($sql){if(!preg_match('/^SELECT \* FROM synthetic_chinaplanetlogistics WHERE entity=2 AND rowid = 17$/',$sql))throw new \RuntimeException('Unexpected SQL');return true;}
 public function fetch_object($r){$row=array_fill_keys(['fk_commande','fk_user_creat','fk_user_assign','container_no','container_type','nb_containers','booking_no','bl_no','shipping_line','vessel_name','voyage_no','port_loading','port_destination','date_etd','date_atd','date_eta','date_ata','freetime_days','freetime_start','freetime_end','fk_statut','notes','customer_note','date_creation','tms'],null);return (object)array_merge($row,['rowid'=>17,'ref'=>'SYNTHETIC','entity'=>2,'fk_soc'=>23,'fk_project'=>31]);}
 public function jdate($d){return null;}
}
class UploadActor121 {public $socid=0;public $admin=0;public function hasRight($m,$r){return $m==='chinaplanetlogistics'&&$r==='write';}}
$db=new UploadDb121();$conf=(object)['entity'=>2];\DolibarrApiAccess::$user=new UploadActor121();
$dir=DOL_DATA_ROOT.'/chinaplanetlogistics/ship-17';$seed=$dir.'/SHIP-17-report.pdf';file_put_contents($seed,'HISTORICAL IMMUTABLE BYTES');$seedHash=hash_file('sha256',$seed);
$uploadName=in_array('--fresh',$argv,true)?'fresh.pdf':'report.pdf';$workers=12;$children=[];$channels=[];$expected=[];
for($i=0;$i<$workers;$i++){
 $payload="%PDF-1.4\n% synthetic worker $i\n".str_repeat('% synthetic bytes '.$i."\n",200000);$expected[$i]=hash('sha256',$payload);
 $pair=stream_socket_pair(STREAM_PF_UNIX,STREAM_SOCK_STREAM,0);$pid=pcntl_fork();if($pid===-1)throw new \RuntimeException('fork unavailable');
 if($pid===0){fclose($pair[0]);fread($pair[1],1);fclose($pair[1]);try{$api=new \ChinaplanetlogisticsApi();$r=$api->uploadDocument('17',$uploadName,base64_encode($payload),'base64','',0,0);file_put_contents($temp.'/result-'.$i,json_encode($r));exit(0);}catch(\Throwable $e){file_put_contents($temp.'/result-'.$i,json_encode(['error'=>$e->getMessage()]));exit(1);}}
 fclose($pair[1]);$channels[]=$pair[0];$children[]=$pid;
}
foreach($channels as $c){fwrite($c,'x');fclose($c);}$failed=false;foreach($children as $pid){pcntl_waitpid($pid,$status);if(pcntl_wexitstatus($status)!==0)$failed=true;}if($failed){$errors=[];foreach(glob($temp.'/result-*') as $path)$errors[]=file_get_contents($path);throw new \RuntimeException('Worker failed: '.implode(' | ',$errors));}
$names=[];foreach($expected as $i=>$hash){$r=json_decode(file_get_contents($temp.'/result-'.$i),true);if(empty($r['success'])||!empty($r['customer_visible']))throw new \RuntimeException('FAIL private upload response');$names[]=$r['filename'];if(!hash_equals($hash,hash_file('sha256',$dir.'/'.$r['filename'])))throw new \RuntimeException('FAIL concurrent upload bytes were replaced or corrupted');}
if(count(array_unique($names))!==$workers)throw new \RuntimeException('FAIL concurrent uploads reused a filename');
if(!hash_equals($seedHash,hash_file('sha256',$seed)))throw new \RuntimeException('FAIL historical file changed');
if(count(array_diff(scandir($dir),['.','..']))!==$workers+1)throw new \RuntimeException('FAIL missing upload or orphan files');
// Boundary/failure cases exercise the same native storage helper with disposable files only.
$sequential=chinaplanetlogistics_store_new_upload($dir,17,'report.pdf','sequential bytes');
if(file_get_contents($dir.'/'.$sequential)!=='sequential bytes'||!hash_equals($seedHash,hash_file('sha256',$seed)))throw new \RuntimeException('FAIL sequential append changed history');
if((fileperms($dir.'/'.$sequential)&0777)!==0640)throw new \RuntimeException('FAIL new file permissions');
symlink($temp.'/absent-target',$dir.'/SHIP-17-link.pdf');
$linkVersion=chinaplanetlogistics_store_new_upload($dir,17,'link.pdf','safe bytes');
if($linkVersion!=='SHIP-17-link-2.pdf'||!is_link($dir.'/SHIP-17-link.pdf')||file_exists($temp.'/absent-target'))throw new \RuntimeException('FAIL dangling symlink occupied name was followed');
symlink($seed,$dir.'/SHIP-17-existing-link.pdf');
chinaplanetlogistics_store_new_upload($dir,17,'existing-link.pdf','other bytes');
if(!hash_equals($seedHash,hash_file('sha256',$seed)))throw new \RuntimeException('FAIL existing symlink target overwritten');
foreach(['../escape.pdf','..','nested/file.pdf',"bad\\name.pdf"] as $bad){try{chinaplanetlogistics_store_new_upload($dir,17,$bad,'bytes');throw new \LogicException('accepted unsafe name');}catch(\InvalidArgumentException $e){}}
try{chinaplanetlogistics_store_new_upload($temp.'/missing-directory',17,'report.pdf','bytes');throw new \LogicException('storage failure ignored');}catch(\RuntimeException $e){if($e instanceof \LogicException)throw $e;}
if(is_dir($temp.'/missing-directory'))throw new \RuntimeException('FAIL storage failure created directory');
$legacy="%PDF-1.4\n% explicit overwrite remains supported\n";
file_put_contents($dir.'/SHIP-17-explicit.pdf','disposable old bytes');
$explicit=(new \ChinaplanetlogisticsApi())->uploadDocument('17','explicit.pdf',base64_encode($legacy),'base64','',0,1);
if($explicit['filename']!=='SHIP-17-explicit.pdf'||file_get_contents($dir.'/'.$explicit['filename'])!==$legacy)throw new \RuntimeException('FAIL explicit overwrite compatibility');
echo "M121_UPLOAD_CONCURRENCY=PASS WORKERS=$workers NAME=$uploadName HISTORICAL=UNCHANGED PRIVATE=YES STORAGE_BOUNDARIES=PASS OVERWRITE_COMPATIBLE=YES\n";
}
