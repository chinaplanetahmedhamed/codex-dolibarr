<?php
require_once __DIR__.'/../../custom/chinaplanetoperations/class/InternalTaskBoardsService.class.php';
define('MAIN_DB_PREFIX','fixture_');
$conf=(object)['entity'=>2,'global'=>(object)['CPO_SHIPMENT_CARDS_ENABLED'=>0]];
class DashboardDB121 {public $queries=[];function query($sql){if(!str_starts_with($sql,'SELECT '))throw new RuntimeException('Dashboard attempted a write');$this->queries[]=$sql;return 1;}function fetch_object($r){return false;}}
class DashboardAuth121 {function actorId(){return 7;}function isInternalActive(){return true;}function isAdministrator(){return false;}function readableSql($alias){return $alias.'.fixture_native_allowed=1';}}
$db=new DashboardDB121;$r=new ReflectionClass(InternalTaskBoardsService::class);$svc=$r->newInstanceWithoutConstructor();
foreach(['db'=>$db,'actor'=>(object)['id'=>7,'statut'=>1,'socid'=>0],'entity'=>2,'auth'=>new DashboardAuth121] as $key=>$value)$r->getProperty($key)->setValue($svc,$value);
$result=$svc->myWork(false,true);
if($result!==['mode'=>'summary','archived'=>false,'tasks'=>[]])throw new RuntimeException('Forced summary unavailable with legacy feature disabled');
if(count($db->queries)!==1)throw new RuntimeException('Unexpected dashboard query path');
foreach(['fixture_cpo_task','t.entity=2','t.fk_user_assign=7','t.fixture_native_allowed=1','t.priority','t.date_deadline','LIMIT 500','CASE WHEN bm.role IS NOT NULL','t.date_archived IS NOT NULL',"t.status='cancelled'"] as $part)if(!str_contains($db->queries[0],$part))throw new RuntimeException('Missing canonical scope '.$part);
echo "PASS forced summary uses one existing canonical authorized SELECT with feature off; no personal-state write path\n";
function isModEnabled($name){return false;}
$attentionResult=$svc->myWork(false,false,true);if($attentionResult['mode']!=='summary'||$attentionResult['shipmentAttention']['available']!==false||count($db->queries)!==2)throw new RuntimeException('Attention must force safe summary and preserve disabled module');
echo "PASS attention-only request stays on canonical read path and does not enable disabled Logistics\n";
// The additional human title/ref fields use the incumbent scoped reference getter.
require_once __DIR__.'/../../custom/chinaplanetoperations/class/InternalTaskContextService.class.php';
class DashboardProjectAuth121 {public $active=true;function isInternalActive(){return $this->active;}function isDolibarrAdministrator(){return true;}}
class DashboardProjectDB121 {public $queries=[],$customerVisible=true;function query($sql){if(!str_starts_with($sql,'SELECT '))throw new RuntimeException('Context attempted write');$this->queries[]=$sql;$rows=[];if(str_contains($sql,'fixture_projet')&&str_contains($sql,'entity=2 AND rowid=11'))$rows=[['rowid'=>11,'ref'=>'P11','title'=>'Human project title','fk_soc'=>7]];elseif($this->customerVisible&&str_contains($sql,'fixture_societe')&&str_contains($sql,'s.entity=2 AND s.rowid=7'))$rows=[['rowid'=>7,'nom'=>'Fictional customer']];return (object)['rows'=>$rows];}function fetch_object($r){$row=array_shift($r->rows);return $row?(object)$row:false;}}
$cr=new ReflectionClass(InternalTaskContextService::class);$cs=$cr->newInstanceWithoutConstructor();$cdb=new DashboardProjectDB121;$ca=new DashboardProjectAuth121;
foreach(['db'=>$cdb,'actor'=>(object)['id'=>7],'entity'=>2,'auth'=>$ca] as $key=>$value)$cr->getProperty($key)->setValue($cs,$value);
$project=$cr->getMethod('project');$result=$project->invoke($cs,11,7);if($result['title']!=='Human project title'||$result['ref']!=='P11'||$result['customerId']!==7)throw new RuntimeException('Human project fields unavailable');
foreach([[11,8],[99,7]] as $args){try{$project->invoke($cs,...$args);throw new RuntimeException('Mismatched context accepted');}catch(InternalTaskAccessException $e){}}
$cdb->customerVisible=false;try{$project->invoke($cs,11,7);throw new RuntimeException('Hidden customer accepted');}catch(InternalTaskAccessException $e){}
$ca->active=false;$before=count($cdb->queries);try{$project->invoke($cs,11,7);throw new RuntimeException('Inactive actor accepted');}catch(InternalTaskAccessException $e){}if(count($cdb->queries)!==$before)throw new RuntimeException('Inactive actor queried context');
echo "PASS existing project getter presents human title/ref only after scoped customer/project checks; mismatch/missing/hidden/inactive cases denied (native rights stubbed)\n";
