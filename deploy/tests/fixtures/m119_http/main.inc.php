<?php
declare(strict_types=1);
// A synthetic session fixture, NOT native Dolibarr authentication acceptance.
if (PHP_SAPI!=='cli-server'||!in_array($_SERVER['REMOTE_ADDR']??'',array('127.0.0.1','::1'),true)) {http_response_code(404);exit;}
$state=realpath((string)getenv('M119_UI_STATE'));
if (!$state||!is_file($state.'/.m119-ui-synthetic')) {http_response_code(503);exit;}
session_save_path($state.'/sessions');session_name('M119SESSID');session_start(array('cookie_httponly'=>true,'cookie_samesite'=>'Strict'));
$config=json_decode(file_get_contents($state.'/actors.json'),true,512,JSON_THROW_ON_ERROR);
$actor=$config[$_SESSION['actor']??'']??null;
header('Cache-Control: private, no-store');
if (!$actor) {http_response_code(401);echo 'Sign in required. Synthetic fixture only.';exit;}
$user=new class($actor) {
    public $id;public $socid;public $status=1;private $allowed;
    public function __construct(array $actor){$this->id=$actor['id'];$this->socid=$actor['socid']??0;$this->allowed=$actor['allowed']??true;}
    public function hasRight($module,$right){return $this->allowed&&$module==='expensereport'&&$right==='creer';}
};
$conf=(object)array('entity'=>$actor['entity'],'currency'=>$actor['base']);
$langs=(object)array('defaultlang'=>'en_US');$db=null;
function currentToken(){return (string)($_SESSION['csrf']??'');}
function newToken(){return currentToken();}
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
