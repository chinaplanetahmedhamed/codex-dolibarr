<?php
// Actual descriptor/permission policy; synthetic native parent and SQLite rights definitions.
$temp=sys_get_temp_dir().'/m121-docs-ids-'.bin2hex(random_bytes(8));
mkdir($temp.'/core/modules',0700,true);
file_put_contents($temp.'/core/modules/DolibarrModules.class.php','<?php #[AllowDynamicProperties] class DolibarrModules {public $calls=0;public function __construct($db){} protected function _init($sql,$options){$this->calls++;return 1;} protected function _remove($sql,$options){$this->calls++;return 1;}}');
register_shutdown_function(function () use ($temp) {unlink($temp.'/core/modules/DolibarrModules.class.php');rmdir($temp.'/core/modules');rmdir($temp.'/core');rmdir($temp);});
define('DOL_DOCUMENT_ROOT',$temp);define('MAIN_DB_PREFIX','qa_');
require_once __DIR__.'/../../custom/chinaplanetdocuments/core/modules/modChinaPlanetDocuments.class.php';
require_once __DIR__.'/../../custom/chinaplanetdocuments/class/DocumentsWorkspace.class.php';
$checks=0;
function ensureDocsId($ok,$message){global $checks;++$checks;if(!$ok)throw new RuntimeException($message);}
class DocsIdsDb {
 public $db;public $queries=array();public $failQuery=false;
 public function __construct(){$this->db=new SQLite3(':memory:');$this->db->exec('CREATE TABLE qa_rights_def(id INTEGER,entity INTEGER,module TEXT,module_origin TEXT,perms TEXT,subperms TEXT)');foreach(array('user_rights','usergroup_rights') as $table)$this->db->exec('CREATE TABLE qa_'.$table.'(entity INTEGER,fk_id INTEGER)');}
 public function query($sql){$this->queries[]=$sql;if($this->failQuery)return false;if(!str_starts_with($sql,'SELECT '))throw new RuntimeException('Unexpected lifecycle write');return $this->db->query($sql);}
 public function fetch_object($r){$row=$r->fetchArray(SQLITE3_ASSOC);return $row?(object)$row:false;}
}
$conf=(object)array('entity'=>1);$db=new DocsIdsDb();$module=new modChinaPlanetDocuments($db);
ensureDocsId($module->numero===500700,'Documents must use reserved nonconflicting module500700');
ensureDocsId($module->rights[0][0]===500701,'Documents read right must use500701');
ensureDocsId($module->rights[0][4]==='workspace_read','new permission key must not inherit legacy read grants');
ensureDocsId($module->rights[0][3]===0,'new read grant remains default off');
ensureDocsId(strpos($module->menu[0]['perms'],'workspace_read')!==false,'menu checks new permission key');
$office=getenv('M121_OFFICE_MODULE_FILE');
if($office){require_once $office;$other=new modChinaPlanetOfficeExpenses($db);ensureDocsId($other->numero===500600,'Office installed module ID unchanged');ensureDocsId($other->rights[1][0]===500601,'Office draft right unchanged');ensureDocsId($module->numero!==$other->numero,'module IDs distinct');$ids=array_map(fn($r)=>$r[0],$other->rights);ensureDocsId(!in_array(500701,$ids,true),'permission IDs distinct');}
$user=new class {public $id=17;public $admin=0;public $socid=0;public $statut=1;public $newGrant=false;public function hasRight($module,$key){return $module==='chinaplanetdocuments'&&($key==='read'||($key==='workspace_read'&&$this->newGrant));}};
ensureDocsId(!DocumentsWorkspace::canRead($user),'legacy500601/read cannot authorize Documents');
$user->newGrant=true;ensureDocsId(DocumentsWorkspace::canRead($user),'explicit new grant authorizes internal staff');
$user->socid=23;$user->admin=1;ensureDocsId(!DocumentsWorkspace::canRead($user),'external admin still denied');
ensureDocsId($module->init()===1&&$module->calls===1,'fresh definitions pass read guard before native lifecycle');
$db->db->exec("INSERT INTO qa_rights_def VALUES(500601,1,'chinaplanetofficeexpenses','','draft','')");
ensureDocsId($module->init()===1,'Office-owned legacy ID does not block independent new module');
$db->db->exec("INSERT INTO qa_rights_def VALUES(500601,1,'chinaplanetdocuments','','read','')");$calls=$module->calls;
ensureDocsId($module->init()===-1&&$module->calls===$calls,'ambiguous legacy Documents definitions block activation before native writes');
ensureDocsId($module->remove()===-1&&$module->calls===$calls,'legacy ambiguity blocks removal too');
$db->db->exec("DELETE FROM qa_rights_def WHERE module='chinaplanetdocuments'");
$db->db->exec("INSERT INTO qa_rights_def VALUES(500701,1,'anothermodule','','read','')");
ensureDocsId($module->init()===-1&&$module->calls===$calls,'occupied new definition blocks writes');
$db->db->exec("DELETE FROM qa_rights_def WHERE id=500701");
$db->db->exec("INSERT INTO qa_rights_def VALUES(500701,1,'chinaplanetdocuments','','workspace_read','')");
ensureDocsId($module->init()===1,'owned current definition supports normal native lifecycle');
foreach(array('user_rights','usergroup_rights') as $table){
 $db->db->exec('INSERT INTO qa_'.$table.' VALUES(2,500701)');$calls=$module->calls;
 ensureDocsId($module->init()===-1&&$module->calls===$calls,'orphan '.$table.' grant in another entity blocks new meaning');
 $db->db->exec('DELETE FROM qa_'.$table);
}
$db->failQuery=true;$calls=$module->calls;ensureDocsId($module->init()===-1&&$module->calls===$calls,'rights inventory failure blocks native writes');$db->failQuery=false;
$conf->entity=0;$calls=$module->calls;ensureDocsId($module->init()===-1&&$module->calls===$calls,'missing active entity blocks lifecycle');
ensureDocsId($db->db->querySingle('SELECT COUNT(*) FROM qa_rights_def WHERE id=500601')===1,'Office definition never changed');
echo "PASS: $checks Documents ID/collision/permission/lifecycle checks\n";
