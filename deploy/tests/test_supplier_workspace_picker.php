<?php
/** Real supplier search SQL against synthetic in-memory rows only. */
define('MAIN_DB_PREFIX', 'picker_');
require_once dirname(__DIR__,2).'/custom/chinaplanetoperations/class/InternalTaskBoardsService.class.php';
final class PickerDatabase {
    public PDO $pdo;
    public function __construct() {$this->pdo=new PDO('sqlite::memory:');$this->pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);}
    public function query($sql){return $this->pdo->query($sql);}
    public function fetch_object($r){return $r->fetchObject();}
    public function escape($s){return substr($this->pdo->quote($s),1,-1);}
}
final class PickerActor {
    public $id=1,$entity=1,$statut=1,$socid=0,$admin=1;
    public function hasRight(...$args){return false;}
}
$conf=(object)['entity'=>1,'global'=>(object)['CPO_SUPPLIER_WORKSPACES_ENABLED'=>1]];
$db=new PickerDatabase();$actor=new PickerActor();$svc=new SupplierWorkspaceService($db,$actor,1);
$db->query('CREATE TABLE picker_societe (rowid INT,entity INT,nom TEXT,fournisseur INT)');
$db->query('CREATE TABLE picker_cpo_supplier_workspace (entity INT,fk_supplier INT)');
for($i=1;$i<=123;$i++)$db->query("INSERT INTO picker_societe VALUES ($i,1,'Supplier ".sprintf('%03d',$i)."',1)");
$db->query("INSERT INTO picker_societe VALUES (200,2,'Foreign supplier',1),(201,1,'Customer only',0),(202,1,'100%_Exact',1),(203,1,'100XXExact',1),(204,1,'中国 مورد',1)");
$db->query('INSERT INTO picker_cpo_supplier_workspace VALUES (1,1)');
$checks=0;
function check($ok,$message){global $checks;if(!$ok)throw new RuntimeException($message);$checks++;}
$first=$svc->supplierOptions('Supplier',1);$second=$svc->supplierOptions('Supplier',2);
check(count($first)===51,'First page includes one lookahead row');
check((int)$second[0]['rowid']===52,'Second page must advance beyond the first 50 options');
$ids=[];for($page=1;$page<=3;$page++)foreach(array_slice($svc->supplierOptions('Supplier',$page),0,50) as $row)$ids[]=(int)$row['rowid'];
check($ids===range(2,123),'Every not-yet-enabled supplier is reachable without duplicates');
check(count($svc->supplierOptions('Supplier',4))===0,'End of list is empty');
check($svc->supplierOptions('Foreign')===[],'Other entities excluded');
check($svc->supplierOptions('Customer only')===[],'Non-suppliers excluded');
check(count($svc->supplierOptions('100%_'))===1,'SQL wildcards treated literally');
check(count($svc->supplierOptions('中国'))===1,'Unicode search supported');
check($svc->supplierOptions('Supplier',0)===$first,'Invalid low page clamps to first page');
foreach(['external','inactive','ordinary','foreign'] as $kind){
 $a=new PickerActor();if($kind==='external')$a->socid=10;if($kind==='inactive')$a->statut=0;if($kind==='ordinary')$a->admin=0;if($kind==='foreign')$a->entity=2;
 try{(new SupplierWorkspaceService($db,$a,1))->supplierOptions('',2);throw new RuntimeException('Unauthorized picker access');}catch(InternalTaskAccessException $e){$checks++;}
}
check(count($svc->suppliers('Supplier'))===1,'Enabled supplier gallery unchanged');
echo "PASS supplier picker: $checks checks\n";
