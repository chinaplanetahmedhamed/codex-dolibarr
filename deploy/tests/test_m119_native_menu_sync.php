<?php
declare(strict_types=1);

final class MenuSyncTestResult
{
    public $rows;
    public $index=0;
    public function __construct(array $rows){$this->rows=$rows;}
}

final class Menubase
{
    public $db;
    public $error='';
    public $menu_handler;
    public $entity;
    public $module;
    public $type;
    public $mainmenu;
    public $leftmenu;
    public $fk_menu;
    public $position;
    public $url;
    public $target;
    public $title;
    public $prefix;
    public $langs;
    public $perms;
    public $enabled;
    public $user;

    public function __construct($db){$this->db=$db;}
    public function create($user=null){return $this->db->createMenu($this);}
}

final class MenuSyncTestDB
{
    public $rows=array();
    public $queries=array();
    public $insertions=0;
    public $failSelect=false;
    private $nextId=1;

    public function prefix(){return 'llx_';}
    public function escape($value){return addslashes((string)$value);}
    public function query($sql){
        $this->queries[]=$sql;
        if($this->failSelect)return false;
        $rows=array_values(array_filter($this->rows,static function($row){
            return $row->module==='chinaplanetofficeexpenses'
                && $row->url==='/custom/chinaplanetofficeexpenses/index.php';
        }));
        return new MenuSyncTestResult($rows);
    }
    public function num_rows($result){return count($result->rows);}
    public function fetch_object($result){return $result->rows[$result->index++]??false;}
    public function free($result){}
    public function createMenu($menu){
        $row=(object)array(
            'rowid'=>$this->nextId++,'menu_handler'=>$menu->menu_handler,'entity'=>$menu->entity,
            'module'=>$menu->module,'type'=>$menu->type,'mainmenu'=>$menu->mainmenu,
            'leftmenu'=>null,'fk_menu'=>$menu->fk_menu,'fk_mainmenu'=>null,'fk_leftmenu'=>null,
            'position'=>$menu->position,'url'=>$menu->url,'target'=>$menu->target,
            'titre'=>$menu->title,'prefix'=>$menu->prefix,'langs'=>$menu->langs,
            'perms'=>$menu->perms,'enabled'=>$menu->enabled,'usertype'=>$menu->user,
        );
        $this->rows[]=$row;$this->insertions++;
        return $row->rowid;
    }
}

require_once dirname(__DIR__,2).'/custom/chinaplanetofficeexpenses/class/OfficeExpenseNativeMenuSyncService.php';

function menuSyncAssert(bool $condition,string $message): void
{
    if(!$condition)throw new RuntimeException($message);
}

$user=(object)array('id'=>1,'admin'=>1,'socid'=>0);
$conf=(object)array('entity'=>1,'chinaplanetofficeexpenses'=>(object)array('enabled'=>1));
$db=new MenuSyncTestDB();
$service=new OfficeExpenseNativeMenuSyncService($db,$user,$conf);
menuSyncAssert($service->isAuthorized(),'Approved admin/entity was denied.');
menuSyncAssert(!$service->hasMenu(),'Unexpected menu row before synchronization.');
menuSyncAssert($service->synchronize(),'First synchronization did not create its row.');
menuSyncAssert($service->hasMenu(),'Created menu row failed exact validation.');
menuSyncAssert(!$service->synchronize(),'Repeated synchronization was not idempotent.');
menuSyncAssert($db->insertions===1,'Synchronization created more than one row.');
menuSyncAssert(strpos($db->queries[0],"WHERE module = 'chinaplanetofficeexpenses'")!==false,'Lookup is not module-scoped.');
menuSyncAssert(strpos($db->queries[0],"AND url = '/custom/chinaplanetofficeexpenses/index.php'")!==false,'Lookup is not route-scoped.');

$deniedUser=(object)array('id'=>2,'admin'=>1,'socid'=>0);
$denied=new OfficeExpenseNativeMenuSyncService($db,$deniedUser,$conf);
menuSyncAssert(!$denied->isAuthorized(),'A different administrator was authorized.');
$before=count($db->queries);
try{$denied->synchronize();throw new RuntimeException('Unauthorized synchronization succeeded.');}
catch(RuntimeException $error){menuSyncAssert(strpos($error->getMessage(),'restricted')!==false,'Unexpected authorization error.');}
menuSyncAssert(count($db->queries)===$before,'Unauthorized request queried or changed menu state.');

$wrong=(object)array('id'=>1,'admin'=>1,'socid'=>0);
$wrongConf=(object)array('entity'=>2,'chinaplanetofficeexpenses'=>(object)array('enabled'=>1));
menuSyncAssert(!(new OfficeExpenseNativeMenuSyncService($db,$wrong,$wrongConf))->isAuthorized(),'Wrong entity was authorized.');
$disabledConf=(object)array('entity'=>1,'chinaplanetofficeexpenses'=>(object)array('enabled'=>0));
menuSyncAssert(!(new OfficeExpenseNativeMenuSyncService($db,$user,$disabledConf))->isAuthorized(),'Disabled module was authorized.');

$conflictDb=new MenuSyncTestDB();
$conflict=(object)get_object_vars($db->rows[0]);$conflict->perms='1';
$conflictDb->rows[]=$conflict;
$conflictService=new OfficeExpenseNativeMenuSyncService($conflictDb,$user,$conf);
try{$conflictService->hasMenu();throw new RuntimeException('A conflicting menu row was accepted.');}
catch(RuntimeException $error){menuSyncAssert(strpos($error->getMessage(),'differs')!==false,'Unexpected conflicting-row error.');}

$duplicateDb=new MenuSyncTestDB();
$duplicateDb->rows=array($db->rows[0],(object)get_object_vars($db->rows[0]));
try{(new OfficeExpenseNativeMenuSyncService($duplicateDb,$user,$conf))->hasMenu();throw new RuntimeException('Duplicate menu rows were accepted.');}
catch(RuntimeException $error){menuSyncAssert(strpos($error->getMessage(),'Multiple')!==false,'Unexpected duplicate-row error.');}

$failedDb=new MenuSyncTestDB();$failedDb->failSelect=true;
try{(new OfficeExpenseNativeMenuSyncService($failedDb,$user,$conf))->hasMenu();throw new RuntimeException('Lookup failure was reported as success.');}
catch(RuntimeException $error){menuSyncAssert(strpos($error->getMessage(),'lookup failed')!==false,'Unexpected lookup error.');}

$staffDb=new MenuSyncTestDB();
$staffRow=(object)get_object_vars($db->rows[0]);
$staffRow->perms='($user->admin && $user->id == 1) || (getDolGlobalInt("CPO_OE_STAFF_ACCESS_ENABLED") === 1 && $user->hasRight("chinaplanetofficeexpenses", "read"))';
$staffDb->rows[]=$staffRow;
$staffService=new OfficeExpenseNativeMenuSyncService($staffDb,$user,$conf);
menuSyncAssert($staffService->hasMenu(),'The exact dormant staff descriptor was rejected.');
menuSyncAssert(!$staffService->synchronize()&&$staffDb->insertions===0,'Existing dormant staff menu was modified.');

echo "M119 menu sync: 18 assertions passed; synthetic DB only.\n";
