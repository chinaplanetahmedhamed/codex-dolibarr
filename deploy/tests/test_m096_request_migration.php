<?php
if(PHP_SAPI!=='cli'||getenv('M091_LOCAL_RUNTIME')!=='1')exit(1);
require getenv('M091_QA_CONFIG');
if(!preg_match('/^m091_qa_[a-f0-9]{10}$/D',$dolibarr_main_db_name)||$dolibarr_main_db_host!=='127.0.0.1')exit(1);
define('NOLOGIN',1);define('NOREQUIREMENU',1);define('NOREQUIREHTML',1);
require $dolibarr_main_document_root.'/master.inc.php';
require dirname(__DIR__).'/migrations/m096_public_request_lifecycle.php';
class Migration96DB {
    private $db; private $table;
    function __construct($db,$table){$this->db=$db;$this->table=$table;}
    function query($sql){return $this->db->query(str_replace(MAIN_DB_PREFIX.'cpo_public_request',$this->table,$sql));}
    function __call($name,$args){return $this->db->$name(...$args);}
}
$n=0;function checkMigration96($ok,$message){global $n;if(!$ok)throw new RuntimeException('FAIL '.$message);echo 'PASS '.(++$n).' '.$message."\n";}
$table=MAIN_DB_PREFIX.'m096_schema_'.bin2hex(random_bytes(5));$proxy=new Migration96DB($db,$table);
try {
 $schema=file_get_contents(dirname(__DIR__,2).'/custom/chinaplanetoperations/sql/llx_cpo_public_request.sql');
 $schema=preg_replace('/^.*(?:lifecycle_state|lifecycle_version|lifecycle_history|idx_cpo_public_request_folder).*\R/m','',$schema);
 $schema=str_replace('llx_cpo_public_request',$table,$schema);
 checkMigration96((bool)$db->query($schema),'original schema fixture created without lifecycle fields');
 checkMigration96((bool)$db->query("INSERT INTO ".$table."(entity,ref,integration_source,request_type,idempotency_key,request_hash,contact_hmac,contact_name,request_json,status,fk_user_creat,date_creation) VALUES(1,'M096-OLD','fixture','quote_request','m096-old-request','hash','contact','Old local request','{\"details\":{\"message\":\"original\"}}','qualified',1,NOW())"),'legacy request exists before upgrade');
 checkMigration96(!m096RequestLifecycleSchema($proxy),'check detects missing migration without mutating');
 checkMigration96(m096RequestLifecycleSchema($proxy,true)&&m096RequestLifecycleSchema($proxy),'migration upgrades original schema');
 $before=(array)$db->fetch_object($db->query('SELECT * FROM '.$table.' LIMIT 1'));
 checkMigration96($before['lifecycle_state']==='active'&&(int)$before['lifecycle_version']===1&&$before['lifecycle_history']===null,'old request receives active default version and empty history');
 checkMigration96($before['status']==='qualified'&&json_decode($before['request_json'],true)['details']['message']==='original','upgrade preserves old business status and evidence');
 checkMigration96(m096RequestLifecycleSchema($proxy,true)&&(array)$db->fetch_object($db->query('SELECT * FROM '.$table.' LIMIT 1'))===$before,'repeat migration changes no request data');
 checkMigration96((bool)$db->query('SELECT rowid,ref,status,request_json,fk_operation,fk_propal FROM '.$table),'old code query remains compatible for rollback');
 $db->query('ALTER TABLE '.$table.' MODIFY lifecycle_state varchar(32) NOT NULL DEFAULT \'active\'');
 $denied=false;try{m096RequestLifecycleSchema($proxy,true);}catch(RuntimeException $e){$denied=$e->getMessage()==='M096_COLUMN_CONTRACT_INVALID';}
 checkMigration96($denied,'schema drift fails closed without replacing existing data');
 echo 'M096_MIGRATION=PASS ASSERTIONS='.$n."\n";
}finally{$db->query('DROP TABLE IF EXISTS '.$table);}
