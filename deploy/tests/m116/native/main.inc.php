<?php
/** Test bootstrap, synthetic actor and SQLite only. No native session or permission changes. */
require dirname(__DIR__).'/fixture.php';
$file=getenv('M116_FIXTURE_DB');if(!$file||!(strpos($file,sys_get_temp_dir())===0||(dirname($file)==='/tmp'&&strpos(basename($file),'m116-')===0)))throw new RuntimeException('Temporary fixture database required.');
$db=new AccountTestDb($file,!is_file($file)||filesize($file)===0);$user=new AccountTestActor();$conf->global->CPO_ACCOUNT_STATEMENTS_ENABLED=1;$langs=(object)['defaultlang'=>($_GET['lang']??'en')==='ar'?'ar_SA':'en_US'];
function GETPOST($name,$filter=''){return $_POST[$name]??$_GET[$name]??'';}
function GETPOSTINT($name){return (int)GETPOST($name);}
function newToken(){return 'synthetic-only-csrf-token';}
if($_SERVER['REQUEST_METHOD']==='POST'&&GETPOST('token')!==newToken())$_GET['errorcode']='InvalidToken';
