<?php
/** Actual Boards class/SELECT in SQLite; reference/auth/source adapters are synthetic.
 * Isolate the exact class in a namespace, replacing only require_once dependency
 * loading. This tests truncation before aggregation without changing production seams.
 */
namespace M121AttentionTaskCap;
define('MAIN_DB_PREFIX', 'cap_');
define('DOL_URL_ROOT', '/erp');
function isModEnabled($name) { return $name === 'chinaplanetlogistics'; }
class InternalTaskContextService {
    private $db;
    function __construct($db, $actor, $entity) { $this->db = $db; }
    function getReferences($id) {
        $this->db->references[] = $id;
        return ['context' => ['shipment' => $id === $this->db->lateId ? ['id'=>17,'label'=>'Synthetic shipment'] : null]];
    }
}
class InternalTaskService { static function boardStatuses() { return ['todo'=>0,'done'=>100]; } }
class ShipmentDocumentService { static function enabled() { return true; } function __construct(...$args) {} }
class ShipmentContextService {
    function __construct(...$args) {}
    function today() { return '2026-11-04'; }
    function attention($id, $today) { return ['status'=>2,'reasons'=>[['code'=>'eta_unconfirmed']]]; }
}
class CapActor { public $admin=0; function hasRight(...$args) { return true; } }
class CapAuth {
    function actorId() { return 7; }
    function isAdministrator() { return false; }
    function isInternalActive() { return true; }
    function readableSql($alias) { return $alias.'.readable=1'; }
}
class CapDB {
    public $pdo, $queries=[], $references=[], $lateId;
    function __construct($count) {
        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('CREATE TABLE cap_cpo_task(rowid INTEGER,entity INTEGER,fk_user_assign INTEGER,title TEXT,ref TEXT,status TEXT,priority TEXT,date_deadline TEXT,date_archived TEXT,readable INTEGER)');
        foreach (['card'=>'rowid INTEGER,entity INTEGER,fk_task INTEGER,fk_board INTEGER,fk_list INTEGER,archived INTEGER',
            ''=>'rowid INTEGER,entity INTEGER,title TEXT,archived INTEGER',
            'list'=>'rowid INTEGER,entity INTEGER,fk_board INTEGER,title TEXT,archived INTEGER',
            'member'=>'entity INTEGER,fk_board INTEGER,fk_user INTEGER,role TEXT'] as $suffix=>$columns) {
            $this->pdo->exec('CREATE TABLE cap_cpo_board'.($suffix?'_'.$suffix:'').'('.$columns.')');
        }
        $insert=$this->pdo->prepare('INSERT INTO cap_cpo_task VALUES(?,2,7,?,? ,\'todo\',\'normal\',?,NULL,1)');
        for ($i=1; $i<=$count; $i++) $insert->execute([$i,'Fictional task '.$i,'SYN-'.$i,sprintf('2026-11-04 00:%02d:%02d',intdiv($i,60),$i%60)]);
        $this->lateId=$count; // Sole overdue shipment is linked to last task in SQL order.
        $this->pdo->exec("INSERT INTO cap_cpo_task VALUES(9001,3,7,'Foreign entity','FOREIGN','todo','normal','2020-01-01',NULL,1),(9002,2,8,'Other assignee','OTHER','todo','normal','2020-01-01',NULL,1),(9003,2,7,'Not readable','HIDDEN','todo','normal','2020-01-01',NULL,0)");
    }
    function query($sql) {
        if (!str_starts_with($sql, 'SELECT ')) throw new \RuntimeException('Business write forbidden');
        $this->queries[]=$sql;
        return $this->pdo->query($sql);
    }
    function fetch_object($result) { return $result->fetchObject(); }
}
$source=file_get_contents(__DIR__.'/../../custom/chinaplanetoperations/class/InternalTaskBoardsService.class.php');
$source=preg_replace('/\brequire_once\s+__DIR__\s*\.\s*\x27[^\x27]+\x27\s*;/', '', $source);
eval('namespace '.__NAMESPACE__.';'.substr($source,5));
$assertions=0;
function check($value, $why) { global $assertions; if (!$value) throw new \RuntimeException($why); $assertions++; }
foreach ([499,500,501] as $count) {
    $db=new CapDB($count);
    $r=new \ReflectionClass(InternalTaskBoardsService::class);
    $service=$r->newInstanceWithoutConstructor();
    foreach (['db'=>$db,'actor'=>new CapActor,'auth'=>new CapAuth,'entity'=>2] as $key=>$value) $r->getProperty($key)->setValue($service,$value);
    $out=$service->myWork(false,true,true);
    check(count($out['tasks'])===min(500,$count), 'Existing 500-row work bound retained');
    check(count($db->queries)===1 && str_ends_with($db->queries[0],'LIMIT 500'), 'No extra/sentinel query');
    check(count($db->references)===min(500,$count) && !array_intersect([9001,9002,9003],$db->references), 'Actual SQL preserves entity, assignee and readable scope');
    check($out['shipmentAttention']['incomplete']===($count>=500), 'Cap must make coverage conservative at '.$count);
    check(count($out['shipmentAttention']['shipments'])===($count<=500?1:0), 'Only covered linked shipments inspected');
    if ($count===501) check(!in_array(501,$db->references,true), 'Regression: omitted task contains sole overdue shipment');
    $before=count($db->queries);
    $plain=$service->myWork(false,true,false);
    check(!array_key_exists('shipmentAttention',$plain) && count($db->queries)===$before+1, 'Unrequested attention payload unchanged');
}
echo "M121_SHIPMENT_ATTENTION_TASK_CAP=PASS ASSERTIONS=$assertions (actual SELECT/class; synthetic dependency adapters)\n";
