<?php

if (PHP_SAPI !== 'cli') { fwrite(STDERR,"M077_NATIVE_GUARD=FAIL REASON=CLI_ONLY\n"); exit(1); }
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
define('MAIN_DB_PREFIX','llx_');
$mode=$argv[1]??'status';
$confPath='/Users/mac/Documents/dev/dolibarr/dolibarr-Dev/dolibarr-23.0.3/htdocs/conf/conf.php';
$config=(static function($path){include $path;return array('host'=>$dolibarr_main_db_host,'port'=>(int)$dolibarr_main_db_port,'name'=>$dolibarr_main_db_name,'user'=>$dolibarr_main_db_user,'pass'=>$dolibarr_main_db_pass);})($confPath);
$host=strtolower(trim((string)$config['host']," \t\n\r\0\x0B[]"));
if(!in_array($host,array('localhost','127.0.0.1','::1'),true)) throw new RuntimeException('REMOTE_DATABASE_FORBIDDEN');
$mysqli=new mysqli($config['host'],$config['user'],$config['pass'],$config['name'],$config['port']);
$adapter=new class($mysqli) {
    private $db; public function __construct($db){$this->db=$db;}
    public function query($sql){return $this->db->query($sql);}
    public function escape($value){return $this->db->real_escape_string((string)$value);}
    public function fetch_object($result){return $result instanceof mysqli_result?$result->fetch_object():null;}
    public function num_rows($result){return $result instanceof mysqli_result?$result->num_rows:0;}
};
require_once dirname(__DIR__,2).'/custom/chinaplanetcustomercenter/class/ManagedFundsNativeGuard.class.php';
$names=array(MAIN_DB_PREFIX.'cpcc_m077_credit_insert',MAIN_DB_PREFIX.'cpcc_m077_credit_update');
$present=0;
foreach($names as $name){$res=$mysqli->query("SELECT TRIGGER_NAME FROM INFORMATION_SCHEMA.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE() AND TRIGGER_NAME='".$mysqli->real_escape_string($name)."'");$present+=$res->num_rows;}
if($mode==='status'){$verified=ManagedFundsNativeGuard::verify($adapter);echo "M077_NATIVE_GUARD=STATUS PRESENT=".$present."/2 VERIFIED=".($verified?'YES':'NO')." DATABASE=LOCAL\n";exit($verified?0:2);}
if($mode==='remove'){
    if(getenv('M077_NATIVE_GUARD_REMOVE')!=='1') throw new RuntimeException('EXPLICIT_LOCAL_REMOVE_GATE_REQUIRED');
    if(!ManagedFundsNativeGuard::remove($adapter)) throw new RuntimeException('NATIVE_GUARD_REMOVE_FAILED');
    echo "M077_NATIVE_GUARD=REMOVED TRIGGERS=2 DATABASE=LOCAL\n";exit(0);
}
if($mode==='repair'){
    if(getenv('M077_NATIVE_GUARD_REPAIR')!=='1') throw new RuntimeException('EXPLICIT_LOCAL_REPAIR_GATE_REQUIRED');
    if(!ManagedFundsNativeGuard::repair($adapter)) throw new RuntimeException('NATIVE_GUARD_REPAIR_FAILED_OR_ACTIVE_ALLOCATIONS');
    echo "M077_NATIVE_GUARD=REPAIRED TRIGGERS=2 VERIFIED=YES DATABASE=LOCAL\n";exit(0);
}
if($mode!=='apply'||getenv('M077_NATIVE_GUARD_APPLY')!=='1') throw new RuntimeException('EXPLICIT_LOCAL_APPLY_GATE_REQUIRED');
if(!ManagedFundsNativeGuard::install($adapter)) throw new RuntimeException('NATIVE_GUARD_INSTALL_FAILED');
echo "M077_NATIVE_GUARD=APPLIED TRIGGERS=2 DATABASE=LOCAL\n";
