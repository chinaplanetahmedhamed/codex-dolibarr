<?php
/** Read-only synthetic owner/projection and assigned-task aggregation. No native DB/session. */
require_once __DIR__.'/../../custom/chinaplanetoperations/class/InternalTaskBoardsService.class.php';
require_once __DIR__.'/../../custom/chinaplanetoperations/class/InternalTaskContextService.class.php';
define('MAIN_DB_PREFIX','synthetic_');define('DOL_URL_ROOT','');
$moduleEnabled=true;function isModEnabled($name){global $moduleEnabled;return $moduleEnabled&&$name==='chinaplanetlogistics';}
$conf=(object)['entity'=>2,'global'=>(object)['CPO_SHIPMENT_CARDS_ENABLED'=>1]];
class AttentionAccess121 extends ShipmentDocumentService {public $denied=[],$calls=0;function __construct(){}function requireShipment($id,$action='read'){$this->calls++;if(in_array($id,$this->denied,true))throw new ShipmentDocumentAccessException('Denied');return ['rowid'=>$id,'entity'=>2,'fk_soc'=>7,'fk_project'=>11];}}
class AttentionDB121 {public $rows=[],$queries=[],$fail=[];function query($sql){if(!str_starts_with($sql,'SELECT '))throw new RuntimeException('Write forbidden');$this->queries[]=$sql;preg_match('/ AND rowid=(\d+)/',$sql,$m);$id=(int)($m[1]??0);if(in_array($id,$this->fail,true))return false;return (object)['row'=>$this->rows[$id]??null];}function fetch_object($r){return $r->row?(object)$r->row:false;}}
class AttentionLang121 {private $labels=[];function load($name){$lang=in_array('--ar',$GLOBALS['argv'],true)?'ar_SA':'en_US';foreach(file(__DIR__.'/../../custom/chinaplanetlogistics/langs/'.$lang.'/shipmentcontext.lang') as $line){$p=explode('=',trim($line),2);if(count($p)===2)$this->labels[$p[0]]=$p[1];}}function trans($key){return $this->labels[$key]??$key;}}
class AttentionActor121 {public $id=7,$admin=0,$allowed=true;function hasRight(...$args){return $this->allowed&&$args===['chinaplanetlogistics','read'];}}
class AttentionAuth121 {public $active=true;function isInternalActive(){return $this->active;}}
class AttentionOwner121 extends ShipmentContextService {public $day='2026-11-04';function today(){return $this->day;}}
$langs=new AttentionLang121;$db=new AttentionDB121;$access=new AttentionAccess121;$owner=new AttentionOwner121($db,$access,2);
$today=in_array('--fixture',$argv,true)?date('Y-m-d'):'2026-11-04';$owner->day=$today;$d=new DateTimeImmutable($today);
$row=['rowid'=>17,'fk_statut'=>2,'container_no'=>'SYNTHETIC','date_etd'=>null,'date_eta'=>$d->modify('-1 day')->format('Y-m-d'),'date_ata'=>null,'freetime_end'=>$d->modify('+5 days')->format('Y-m-d')];
$db->rows[17]=$row;$db->rows[18]=array_merge($row,['rowid'=>18,'fk_statut'=>4,'date_ata'=>$today,'freetime_end'=>$d->modify('-1 day')->format('Y-m-d')]);
$reflection=new ReflectionClass(InternalTaskBoardsService::class);$svc=$reflection->newInstanceWithoutConstructor();$actor=new AttentionActor121;$auth=new AttentionAuth121;
foreach(['actor'=>$actor,'auth'=>$auth,'db'=>$db,'entity'=>2] as $key=>$value)$reflection->getProperty($key)->setValue($svc,$value);
$method=$reflection->getMethod('shipmentAttention');
$task=function($id,$sid,$status='todo'){return ['id'=>$id,'title'=>'Synthetic task '.$id,'ref'=>'SYN-TASK-'.$id,'status'=>$status,'archived'=>false,'shipment'=>['id'=>$sid,'label'=>'SYN-SHIP-'.$sid],'customer'=>['id'=>7,'label'=>'عميل تجريبي · Fictional customer'],'project'=>['id'=>11,'title'=>'مشروع تجريبي · Fictional project','ref'=>'SYN-P11']];};
$tasks=[$task(101,17),$task(102,17),$task(103,18)];
if(in_array('--fixture',$argv,true)){echo json_encode($method->invoke($svc,$tasks,$owner),JSON_UNESCAPED_UNICODE);exit;}
$n=0;function attentionCheck121($ok,$message){global $n;if(!$ok)throw new RuntimeException($message);$n++;}
$before=json_encode($tasks);$projection=$owner->attention(17,$today);attentionCheck121($projection['status']===2&&$projection['statusLabel']==='On Vessel','source lifecycle preserved');attentionCheck121(array_column($projection['reasons'],'code')===['eta_unconfirmed','free_time_soon'],'past ETA is unconfirmed and day5 free-time included');attentionCheck121($projection['fields'][3]['value']===null,'ETA never supplies ATA');
$db->rows[17]['date_ata']=$today;attentionCheck121(array_column($owner->attention(17,$today)['reasons'],'code')===['free_time_soon'],'actual arrival suppresses ETA warning');$db->rows[17]['date_ata']=null;
foreach([6,7,99,null] as $status){$db->rows[17]['fk_statut']=$status;attentionCheck121($owner->attention(17,$today)['reasons']===[],'terminal/unknown status not active');}$db->rows[17]['fk_statut']=2;
$db->rows[17]['date_eta']=$today;$db->rows[17]['freetime_end']=$d->modify('+6 days')->format('Y-m-d');attentionCheck121($owner->attention(17,$today)['reasons']===[],'today ETA not passed and day6 free-time excluded');
$db->rows[17]['date_eta']='2026-02-30';$db->rows[17]['freetime_end']='invalid';attentionCheck121($owner->attention(17,$today)['reasons']===[],'invalid dates do not alert');$db->rows[17]=$row;
$access->denied=[17];$q=count($db->queries);try{$owner->attention(17,$today);throw new RuntimeException('Denied source accepted');}catch(ShipmentDocumentAccessException $e){}attentionCheck121(count($db->queries)===$q,'access denied before projection SELECT');$access->denied=[];
$db->queries=[];$result=$method->invoke($svc,[...$tasks,$task(104,18,'done'),$task(101,17)],$owner);attentionCheck121(count($result['shipments'])===2&&count($result['shipments'][0]['tasks'])===2,'dedupe shipments and tasks; closed task omitted');attentionCheck121(count($db->queries)===2,'one source projection per unique shipment');attentionCheck121($result['shipments'][0]['url']==='/custom/chinaplanetlogistics/card.php?id=17','exact canonical shipment link');attentionCheck121(json_encode($tasks)===$before,'no task mutation');
$access->denied=[18];$result=$method->invoke($svc,$tasks,$owner);attentionCheck121(count($result['shipments'])===1&&!$result['incomplete'],'hidden shipment omitted without leaking its details');$access->denied=[];
$db->fail=[18];$result=$method->invoke($svc,$tasks,$owner);attentionCheck121($result['incomplete']&&count($result['shipments'])===1,'source failure reports partial rather than all clear');$db->fail=[];
$actor->allowed=false;attentionCheck121(!$method->invoke($svc,$tasks,$owner)['available'],'Logistics right required');$actor->allowed=true;$auth->active=false;attentionCheck121(!$method->invoke($svc,$tasks,$owner)['available'],'internal actor required');$auth->active=true;
$conf->global->CPO_SHIPMENT_CARDS_ENABLED=0;attentionCheck121(!$method->invoke($svc,$tasks,$owner)['available'],'existing disabled dependency not enabled');$conf->global->CPO_SHIPMENT_CARDS_ENABLED=1;
$moduleEnabled=false;attentionCheck121(!$method->invoke($svc,$tasks,$owner)['available'],'disabled Logistics module stays disabled');$moduleEnabled=true;
class AttentionCap121 extends ShipmentContextService {public $calls=0;function __construct(){}function today(){return '2026-11-04';}function attention($id,$today=null){$this->calls++;return ['reasons'=>[]];}}
$many=[];for($i=1;$i<=51;$i++)$many[]=$task(200+$i,$i);$cap=new AttentionCap121;$result=$method->invoke($svc,$many,$cap);attentionCheck121($cap->calls===50&&$result['incomplete'],'bounded source fanout and partial coverage');
foreach($db->queries as $sql)attentionCheck121(str_contains($sql,'entity=2')&&str_contains($sql,'fk_soc=7')&&str_contains($sql,'COALESCE(fk_project,0)=11'),'source entity/customer/project scope retained');
echo "M121_SHIPMENT_ATTENTION=PASS ASSERTIONS=$n\n";
