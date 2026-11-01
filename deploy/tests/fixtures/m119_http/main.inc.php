<?php
declare(strict_types=1);
// A synthetic session fixture, NOT native Dolibarr authentication acceptance.
if (PHP_SAPI!=='cli-server'||!in_array($_SERVER['REMOTE_ADDR']??'',array('127.0.0.1','::1'),true)) {http_response_code(404);exit;}
$state=realpath((string)getenv('M119_UI_STATE'));
if (!$state||!is_file($state.'/.m119-ui-synthetic')) {http_response_code(503);exit;}
define('DOL_DOCUMENT_ROOT',dirname(__DIR__,4));
define('DOL_URL_ROOT','');
session_save_path($state.'/sessions');session_name('M119SESSID');session_start(array('cookie_httponly'=>true,'cookie_samesite'=>'Strict'));
$config=json_decode(file_get_contents($state.'/actors.json'),true,512,JSON_THROW_ON_ERROR);
$actor=$config[$_SESSION['actor']??'']??null;
header('Cache-Control: private, no-store');
if (!$actor) {http_response_code(401);echo 'Sign in required. Synthetic fixture only.';exit;}
class FixtureMenuResult
{
    public $rows;
    public $index=0;
    public function __construct(array $rows){$this->rows=$rows;}
}
class FixtureMenuDB
{
    private $pdo;
    public function __construct($state){
        $this->pdo=new PDO('sqlite:'.$state.'/menus.sqlite');
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS menu (rowid INTEGER PRIMARY KEY AUTOINCREMENT, menu_handler TEXT, entity INTEGER, module TEXT, type TEXT, mainmenu TEXT, leftmenu TEXT, fk_menu INTEGER, fk_mainmenu TEXT, fk_leftmenu TEXT, position INTEGER, url TEXT, target TEXT, titre TEXT, prefix TEXT, langs TEXT, perms TEXT, enabled TEXT, usertype INTEGER)');
    }
    public function prefix(){return 'llx_';}
    public function escape($value){return str_replace("'","''",(string)$value);}
    public function query($sql){
        try{$statement=$this->pdo->query(str_replace('llx_menu','menu',$sql));return new FixtureMenuResult($statement->fetchAll(PDO::FETCH_OBJ));}
        catch(Throwable $error){return false;}
    }
    public function num_rows($result){return count($result->rows);}
    public function fetch_object($result){return $result->rows[$result->index++]??false;}
    public function free($result){}
    public function createMenu($menu){
        $statement=$this->pdo->prepare('INSERT INTO menu (menu_handler,entity,module,type,mainmenu,leftmenu,fk_menu,fk_mainmenu,fk_leftmenu,position,url,target,titre,prefix,langs,perms,enabled,usertype) VALUES (:menu_handler,:entity,:module,:type,:mainmenu,:leftmenu,:fk_menu,:fk_mainmenu,:fk_leftmenu,:position,:url,:target,:titre,:prefix,:langs,:perms,:enabled,:usertype)');
        $statement->execute(array(
            ':menu_handler'=>$menu->menu_handler,':entity'=>$menu->entity,':module'=>$menu->module,
            ':type'=>$menu->type,':mainmenu'=>$menu->mainmenu,':leftmenu'=>null,':fk_menu'=>$menu->fk_menu,
            ':fk_mainmenu'=>null,':fk_leftmenu'=>null,':position'=>$menu->position,':url'=>$menu->url,
            ':target'=>$menu->target,':titre'=>$menu->title,':prefix'=>$menu->prefix,':langs'=>$menu->langs,
            ':perms'=>$menu->perms,':enabled'=>$menu->enabled,':usertype'=>$menu->user,
        ));
        return (int)$this->pdo->lastInsertId();
    }
}
class Menubase
{
    public $db;public $error='';
    public $menu_handler;public $entity;public $module;public $type;public $mainmenu;public $leftmenu;
    public $fk_menu;public $position;public $url;public $target;public $title;public $prefix;
    public $langs;public $perms;public $enabled;public $user;
    public function __construct($db){$this->db=$db;}
    public function create($user=null){return $this->db->createMenu($this);}
}
$user=new class($actor) {
    public $id;public $socid;public $status=1;public $admin=0;private $allowed;private $ownAllowed;
    public function __construct(array $actor){$this->id=$actor['id'];$this->socid=$actor['socid']??0;$this->admin=$actor['admin']??0;$this->allowed=$actor['allowed']??true;$this->ownAllowed=$actor['own_allowed']??true;}
    public function hasRight($module,$right){return $this->allowed&&(($module==='expensereport'&&$right==='creer')||($this->ownAllowed&&$module==='chinaplanetofficeexpenses'&&$right==='draft'));}
};
$conf=(object)array('entity'=>$actor['entity'],'currency'=>$actor['base'],'chinaplanetofficeexpenses'=>(object)array('enabled'=>empty($actor['module_disabled'])),'chinaplanetoperations'=>(object)array('enabled'=>false));
$langs=new class {public $defaultlang='en_US';public function load($name){return true;}public function trans($key){return $key==='OfficeExpenses'?'Office Expenses':$key;}};
$db=new FixtureMenuDB($state);
if(!empty($actor['existing_csp']))header("Content-Security-Policy: default-src 'self'; style-src 'self'; script-src 'self'; font-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
function llxHeader($head='',$title='',$helpUrl='',$target='',$disablejs=0,$disablehead=0,$arrayofjs='',$arrayofcss=''){
    global $mainmenu;
    echo '<!doctype html><html lang="en"><head><title>'.htmlspecialchars((string)$title,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'</title>';
    foreach((array)$arrayofcss as $href)echo '<link rel="stylesheet" href="'.htmlspecialchars((string)$href,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'">';
    echo '</head><body><header id="id-top" data-mainmenu="'.htmlspecialchars((string)($mainmenu??''),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'">Native Dolibarr shell</header><div id="native-page">';
}
function llxFooter(){echo '</div></body></html>';}
function currentToken(){return (string)($_SESSION['csrf']??'');}
function newToken(){return currentToken();}
function getNonce(){return 'synthetic-nonce';}
function GETPOST($key,$type='alpha'){
    $value=$_POST[$key]??$_GET[$key]??'';
    if($type==='array')return is_array($value)?$value:array();
    return is_string($value)||is_numeric($value)?(string)$value:'';
}
// Model native main.inc.php's stale-token sanitization for the dedicated actor.
if (!empty($actor['native_sanitizes_tokens']) && ($_SERVER['REQUEST_METHOD']??'')==='POST' && ($_POST['token']??'')!==currentToken()) {
    unset($_POST);$_GET['errorcode']='InvalidToken';
}
if (empty($actor['unconfigured'])) {
    require_once dirname(__DIR__,3).'/tools/office_expenses/LocalDraftStore.php';
    $GLOBALS['cpoOfficeExpenseDraftFactory']=static function($db,$nativeUser,$nativeConf)use($state,$actor){
        $store=new LocalDraftStore(new PDO('sqlite:'.$state.'/drafts.sqlite'));
        return new OfficeExpenseDraftService($store,new OfficeExpenseMoney(),$nativeUser,(int)$nativeConf->entity,$actor['offices'],(string)$nativeConf->currency);
    };
}
