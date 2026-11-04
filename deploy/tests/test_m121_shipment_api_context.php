<?php
/** Run actual API/model with synthetic SELECT-only DB and a minimal native bootstrap. No HTTP or runtime DB. */
namespace Luracast\Restler {
    class RestException extends \RuntimeException { public $status; public function __construct($status,$message){$this->status=$status;parent::__construct($message);} }
}
namespace {
$root=dirname(__DIR__,2);$temp=sys_get_temp_dir().'/m121-api-'.bin2hex(random_bytes(8));
mkdir($temp.'/api/class',0700,true);
file_put_contents($temp.'/api/class/api.class.php', '<?php class DolibarrApi {public $db;} class DolibarrApiAccess {public static $user;} class CommonObject {}');
symlink($root.'/custom',$temp.'/custom');
register_shutdown_function(function()use($temp){unlink($temp.'/custom');unlink($temp.'/api/class/api.class.php');rmdir($temp.'/api/class');rmdir($temp.'/api');rmdir($temp);});
define('DOL_DOCUMENT_ROOT',$temp);define('MAIN_DB_PREFIX','synthetic_');
require_once $root.'/custom/chinaplanetlogistics/api/class/api_chinaplanetlogistics.class.php';
class Cursor121 {public $rows;public $i=0;public function __construct($rows){$this->rows=$rows;}}
class DBApi121 {
 public $queries=[];public $rows=[];
 public function escape($s){return str_replace("'","''",$s);}
 public function jdate($date){return $date?strtotime($date.' UTC'):null;}
 public function query($sql){
  $this->queries[]=$sql;if(!preg_match('/^SELECT /',$sql))throw new \RuntimeException('Unexpected write');
  if(!preg_match('/WHERE (?:s\.)?entity\s*=\s*(\d+)/',$sql,$scope))throw new \RuntimeException('Missing entity scope');
  $rows=array_values(array_filter($this->rows,function($r)use($scope,$sql){
   if((int)$r['entity']!==(int)$scope[1])return false;
   if(preg_match('/AND rowid = (\d+)/',$sql,$id)&&(int)$r['rowid']!==(int)$id[1])return false;
   if(preg_match("/AND ref = '([^']*)'/",$sql,$ref)&&$r['ref']!==$ref[1])return false;
   if(preg_match('/AND s.fk_soc = (\d+)/',$sql,$soc)&&(int)$r['fk_soc']!==(int)$soc[1])return false;
   return true;
  }));
  return new Cursor121($rows);
 }
 public function fetch_object($cursor){return isset($cursor->rows[$cursor->i])?(object)$cursor->rows[$cursor->i++]:false;}
}
class UserApi121 {public $socid=0;public $admin=0;public $read=true;public function hasRight($module,$right){return $module==='chinaplanetlogistics'&&$right==='read'&&$this->read;}}
$count=0;function assertApi121($v,$m){global $count;if(!$v)throw new \RuntimeException('FAIL '.$m);$count++;}
function deniedApi121($fn,$status,$message){try{$fn();}catch(\Luracast\Restler\RestException $e){assertApi121($e->status===$status,$message);return;}throw new \RuntimeException('FAIL expected denial: '.$message);}
$db=new DBApi121();$conf=(object)['entity'=>2];$user=new UserApi121();\DolibarrApiAccess::$user=$user;
$columns=['fk_commande','fk_user_creat','fk_user_assign','container_no','container_type','nb_containers','booking_no','bl_no','shipping_line','vessel_name','voyage_no','port_loading','port_destination','date_etd','date_atd','date_eta','date_ata','freetime_days','freetime_start','freetime_end','fk_statut','notes','customer_note','date_creation','tms','origin_port','destination_port','date_eta','date_etd','status','socname'];
$row=array_fill_keys($columns,null);$row=array_merge($row,['rowid'=>17,'id'=>17,'ref'=>'SYNTH-LOCAL','entity'=>2,'fk_soc'=>23,'fk_project'=>31]);
$db->rows=[$row,array_merge($row,['rowid'=>18,'id'=>18,'ref'=>'SYNTH-FOREIGN','entity'=>3,'fk_soc'=>24,'fk_project'=>32])];
$api=new ChinaplanetlogisticsApi();
$detail=$api->getShipment(17);
assertApi121(($detail['entity']??null)==='2'&&($detail['fk_project']??null)==='31','detail exposes exact source entity/project as decimal strings');
$list=$api->listShipments();assertApi121(count($list)===1&&($list[0]['entity']??null)==='2'&&($list[0]['fk_project']??null)==='31','list exposes same context, excludes foreign tenant');
assertApi121($api->getShipment('SYNTH-LOCAL')['entity']==='2','reference lookup uses same contract');
assertApi121(strpos($db->queries[1],'s.entity, s.fk_soc, s.fk_project')!==false,'list SELECT includes source ownership columns');
$user->read=false;$user->admin=1;assertApi121($api->getShipment(17)['entity']==='2','internal admin retains scoped read');$user->read=true;$user->admin=0;
deniedApi121(fn()=>$api->getShipment(18),404,'foreign tenant numeric id denied');
deniedApi121(fn()=>$api->getShipment('SYNTH-FOREIGN'),404,'foreign tenant reference denied');
assertApi121($api->listShipments('',24)===[],'customer filter never crosses active tenant');
$db->rows[0]['fk_project']=null;assertApi121($api->getShipment(17)['fk_project']==='0'&&$api->listShipments()[0]['fk_project']==='0','unlinked project is explicit string zero');
$user->read=false;$before=count($db->queries);deniedApi121(fn()=>$api->getShipment(17),403,'missing logistics read denied');deniedApi121(fn()=>$api->listShipments(),403,'list missing read denied');assertApi121(count($db->queries)===$before,'permission rejection precedes DB access');
$user->read=true;$user->socid=23;$user->admin=1;deniedApi121(fn()=>$api->getShipment(17),403,'external user denied even with admin bit');deniedApi121(fn()=>$api->listShipments(),403,'external list denied');$user->socid=0;$user->admin=0;
\DolibarrApiAccess::$user=null;deniedApi121(fn()=>$api->getShipment(17),403,'anonymous detail denied');deniedApi121(fn()=>$api->listShipments(),403,'anonymous list denied');\DolibarrApiAccess::$user=$user;
$conf->entity=0;$before=count($db->queries);deniedApi121(fn()=>$api->getShipment(17),500,'missing active entity cannot fall back to tenant 1');deniedApi121(fn()=>$api->listShipments(),500,'list requires active entity');assertApi121(count($db->queries)===$before,'invalid entity rejection precedes DB access');
$conf->entity=3;assertApi121($api->getShipment(18)['entity']==='3','changing active entity reads its own source');deniedApi121(fn()=>$api->getShipment(17),404,'previous tenant becomes inaccessible');
assertApi121(count(array_filter($db->queries,fn($q)=>!preg_match('/^SELECT /',$q)))===0,'read-only API tests execute SELECT only');
echo 'M121_SHIPMENT_API_CONTEXT=PASS ASSERTIONS='.$count."\n";
}
