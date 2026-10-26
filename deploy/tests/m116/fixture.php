<?php
define('MAIN_DB_PREFIX','llx_');define('DOL_DOCUMENT_ROOT',__DIR__.'/native');
$conf=(object)['entity'=>1,'global'=>(object)['CPO_CUSTOMER_WORKSPACES_ENABLED'=>1,'CPO_BOARDS_ENABLED'=>0]];
class AccountTestActor {
 public $id=7,$entity=1,$socid=0,$fk_soc=0,$statut=1,$admin=0,$hidden=[],$rights=['read','edit','post','reallocate','export'];
 public function hasRight(...$p){if($p[0]==='chinaplanetoperations')return $p[1]==='task_admin'||in_array(str_replace('account_','',$p[1]),$this->rights,true);if(implode('.',$p)==='projet.all.lire')return !$this->hidden;return true;}
}
class AccountTestDb {
 public $pdo,$transaction_opened=0,$failAudit=false;
 public function __construct($path=':memory:',$seed=true){$this->pdo=new PDO('sqlite:'.$path);$this->pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$this->pdo->exec('PRAGMA busy_timeout=5000');if(!$seed)return;
 $sql=file_get_contents(__DIR__.'/../../../custom/chinaplanetoperations/sql/llx_cpo_account.sql');
 $sql=preg_replace('/BIGINT AUTO_INCREMENT PRIMARY KEY/','INTEGER PRIMARY KEY AUTOINCREMENT',$sql);$sql=preg_replace('/UNIQUE KEY [a-z_]+\(([^)]+)\)/','UNIQUE($1)',$sql);$sql=preg_replace('/,\s*KEY [a-z_]+\([^)]+\)/','',$sql);$sql=str_replace(' ENGINE=InnoDB','',$sql);$this->pdo->exec($sql);
 $this->pdo->exec("CREATE TABLE llx_societe(rowid INTEGER PRIMARY KEY,entity INT,nom TEXT);CREATE TABLE llx_societe_commerciaux(fk_soc INT,fk_user INT);CREATE TABLE llx_projet(rowid INTEGER PRIMARY KEY,entity INT,fk_soc INT,ref TEXT,title TEXT);CREATE TABLE llx_cpo_board_card(entity INT,fk_task INT,fk_board INT);CREATE TABLE llx_cpo_project_board(entity INT,fk_project INT,fk_board INT);CREATE TABLE llx_cpo_project_task(entity INT,fk_task INT,fk_project INT,fk_native_task BIGINT);CREATE TABLE llx_projet_task(rowid INT,entity INT,fk_projet INT);CREATE TABLE llx_cpo_task(rowid INT,entity INT,status TEXT,ref TEXT,title TEXT);CREATE TABLE llx_user(rowid INT,entity INT,admin INT,statut INT,fk_soc INT);INSERT INTO llx_user VALUES(7,1,0,1,0);INSERT INTO llx_societe VALUES(1,1,'Synthetic Harbour'),(2,1,'Synthetic Other'),(3,2,'Other entity');INSERT INTO llx_projet VALUES(10,1,1,'DEMO-10','Packaging'),(11,1,1,'DEMO-11','Shipping'),(20,1,2,'DEMO-20','Other customer');INSERT INTO llx_cpo_task VALUES(100,1,'todo','DEMO-CARD','Sample shipment');INSERT INTO llx_cpo_project_board VALUES(1,10,10);INSERT INTO llx_cpo_board_card VALUES(1,100,10);INSERT INTO llx_cpo_project_task VALUES(1,100,10,1000);INSERT INTO llx_projet_task VALUES(1000,1,10)"); }
 public function query($s){if($this->failAudit&&strpos($s,'INSERT INTO llx_cpo_account_audit')===0)return false;try{return $this->pdo->query(str_replace(' FOR UPDATE','',$s));}catch(Throwable $e){fwrite(STDERR,"SQL: ".$e->getMessage()."\n");return false;}}
 public function fetch_object($r){return $r->fetch(PDO::FETCH_OBJ);}
 public function escape($s){return str_replace("'","''",$s);}
 public function begin(){$this->pdo->exec('BEGIN IMMEDIATE');$this->transaction_opened=1;return true;}
 public function commit(){$this->pdo->exec('COMMIT');$this->transaction_opened=0;return true;}
 public function rollback(){if($this->transaction_opened)$this->pdo->exec('ROLLBACK');$this->transaction_opened=0;return true;}
 public function last_insert_id($t){return $this->pdo->lastInsertId();}
 public function close() {}
 public function scalar($s){return (int)$this->pdo->query($s)->fetchColumn();}
}
