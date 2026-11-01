<?php
declare(strict_types=1);
// Disposable GitHub-runner database only. Never reads native configuration or existing accounts.
if(PHP_SAPI!=='cli'||$argc!==2||getenv('CI')!=='true'||getenv('GITHUB_ACTIONS')!=='true'
    ||getenv('M119_CI_DB_HOST')!=='127.0.0.1'||getenv('M119_CI_DB_PORT')!=='13306'
    ||getenv('M119_CI_DB_USER')!=='root'||getenv('M119_CI_DB_PASSWORD')!=='m119-synthetic-ci-only'
    ||!is_file($argv[1].'/version.inc.php')){fwrite(STDERR,"FAIL requires the isolated M119 GitHub Actions database fixture\n");exit(1);}
define('DOL_DOCUMENT_ROOT',realpath($argv[1]));define('MAIN_DB_PREFIX','llx_');define('DOL_URL_ROOT','');
require DOL_DOCUMENT_ROOT.'/version.inc.php';
if(DOL_VERSION!=='24.0.1'||!extension_loaded('bcmath')||!extension_loaded('gd')||!extension_loaded('mysqli')){fwrite(STDERR,"FAIL native version/extensions differ\n");exit(1);}
$conf=(object)array('entity'=>1,'currency'=>'USD','global'=>(object)array('SYSLOG_LEVEL'=>0,'MAIN_MAX_DECIMALS_TOT'=>2,'MAIN_MAX_DECIMALS_UNIT'=>8),
    'db'=>(object)array('character_set'=>'utf8mb4','dolibarr_main_db_collation'=>'utf8mb4_unicode_ci'),'expensereport'=>(object)array('enabled'=>1),'loghandlers'=>array(),'modules_parts'=>array());
$langs=new class {public function trans($key,...$args){return $key;}};
require DOL_DOCUMENT_ROOT.'/core/lib/functions.lib.php';require DOL_DOCUMENT_ROOT.'/core/db/DoliDB.class.php';require DOL_DOCUMENT_ROOT.'/core/db/mysqli.class.php';require DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
$module=dirname(__DIR__,2).'/custom/chinaplanetoperations/office_expenses';
require $module.'/class/OfficeExpenseNativeStorageFactory.php';require $module.'/class/OfficeExpensePilotConfiguration.php';
$name='m119_ci_'.bin2hex(random_bytes(8));$nonce=bin2hex(random_bytes(16));$base=sys_get_temp_dir().'/'.$name;$created=false;$passed=0;$failed=0;
$password=(string)getenv('M119_CI_DB_PASSWORD');
mysqli_report(MYSQLI_REPORT_OFF);
function qcheck($actual,$expected): void {if($actual!==$expected)throw new RuntimeException('Synthetic integration assertion differs.');}
function qreject(callable $action,string $type=Throwable::class): void {try{$action();}catch(Throwable $e){if($e instanceof $type)return;throw $e;}throw new RuntimeException('Expected integration denial.');}
function qtest(string $name,callable $action): void {global $passed; $action();echo "PASS $name\n";$passed++;}
function qdb(): DoliDBMysqli {global $name,$password;$d=new DoliDBMysqli('mysqli','127.0.0.1','root',$password,$name,13306);if(!$d->ok||!$d->database_selected)throw new RuntimeException('Synthetic native connection unavailable.');return $d;}
function quser(int $id=2001): User {global $db;$u=new User($db);$u->id=$id;$u->admin=1;$u->status=1;$u->socid=0;$u->rights->expensereport=(object)array('creer'=>1);return $u;}
function qservice($d,int $owner=2001,array $offices=array('egypt'=>array('EGP'),'china'=>array('CNY'))): OfficeExpenseDraftService {return new OfficeExpenseDraftService(new OfficeExpenseNativeDraftStore($d),new OfficeExpenseMoney(),quser($owner),1,$offices,'USD');}
function qpayload(): array {return array('kind'=>'expense_claim','office'=>'china','amount'=>'180.29','currency'=>'CNY','occurred_on'=>'2026-11-01','source_ref'=>'SYNTHETIC MariaDB CI','purpose'=>'SYNTHETIC original amount','quote'=>array('rate'=>'0.14000000','date'=>'2026-11-01','source'=>'SYNTHETIC fixed quote'));}
function qimage(): string {$im=imagecreatetruecolor(3,2);ob_start();imagepng($im);$b=ob_get_clean();imagedestroy($im);return $b;}
function qreceipts($d,OfficeExpenseDraftService $drafts): OfficeExpenseReceiptService {global $base;return new OfficeExpenseReceiptService($drafts,new OfficeExpenseNativeReceiptStore($d,new OfficeExpenseNativeDraftStore($d),$base.'/private',DOL_DOCUMENT_ROOT));}
function qclean(string $path): void {if(is_link($path)||is_file($path)){unlink($path);return;}foreach(scandir($path)as$n)if($n!=='.'&&$n!=='..')qclean($path.'/'.$n);rmdir($path);}
final class M119CiLostCommit extends DoliDBMysqli {public $loseNext=false;public function commit($log=''){$result=parent::commit($log);if($result&&$this->loseNext){$this->loseNext=false;return 0;}return $result;}}
$admin=null;$db=null;$schema=null;
try {
    $admin=new mysqli('127.0.0.1','root',$password,'',13306);if($admin->connect_errno)throw new RuntimeException('CI service connection unavailable.');
    qtest('actual CI server reports the required MariaDB10.6.27 engine',function()use($admin){$r=$admin->query('SELECT VERSION() AS version');$v=$r?$r->fetch_assoc()['version']:'';qcheck(strpos($v,'10.6.27-MariaDB')===0,true);});
    if(!$admin->query('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'))throw new RuntimeException('Fresh disposable database creation failed.');$created=true;
    if(!$admin->query('CREATE TABLE `'.$name.'`.m119_ci_owner(nonce CHAR(32) NOT NULL)')||!$admin->query("INSERT INTO `".$name."`.m119_ci_owner VALUES('".$nonce."')"))throw new RuntimeException('Disposable ownership marker failed.');
    mkdir($base,0700);mkdir($base.'/private',0700);file_put_contents($base.'/private/.cpo-office-expenses-private','cpo-office-expenses-private-v1');chmod($base.'/private/.cpo-office-expenses-private',0600);
    $db=qdb();$user=quser();$schema=new OfficeExpenseMysqliSchemaBackend($db);$draftMigration=new OfficeExpenseNativeSchemaMigration($schema);
    qtest('real MariaDB fresh migration planning leaves its schema empty',function()use($draftMigration){qcheck($draftMigration->plan()['state'],'empty');});
    qtest('pinned base migration installs real InnoDB columns collations indexes and markers',function()use($draftMigration){$hash=$draftMigration->plan()['manifest_hash'];qcheck($draftMigration->install($hash)['state'],'installed');qcheck($draftMigration->install($hash)['manifest_hash'],$hash);});
    $receiptMigration=OfficeExpenseNativeSchemaMigration::forReceipts($schema);
    qtest('separate receipt migration verifies its base dependency and exact rerun',function()use($receiptMigration){qcheck($receiptMigration->plan()['state'],'empty');$hash=$receiptMigration->plan()['manifest_hash'];qcheck($receiptMigration->install($hash)['state'],'installed');qcheck($receiptMigration->install($hash)['state'],'installed');});
    $drafts=qservice($db);$draft=null;
    qtest('native mysqli preserves exact original quote and scoped immutable source',function()use($drafts,&$draft){$draft=$drafts->submit(qpayload(),'synthetic-ci-original');qcheck($draft['amount'],'180.29');qcheck($draft['conversion']['base_amount'],'25.24');qcheck($draft['conversion']['rate'],'0.14000000');qcheck($draft['owner_id'],2001);});
    qtest('administrator without the required native expense right is still denied',function()use($db){$u=quser();$u->rights->expensereport->creer=0;$s=new OfficeExpenseDraftService(new OfficeExpenseNativeDraftStore($db),new OfficeExpenseMoney(),$u,1,array('china'=>array('CNY')),'USD');qreject(function()use($s){$s->submit(qpayload(),'synthetic-ci-no-native-right');},DomainException::class);});
    qtest('real unique constraints recover identical retry and reject changed intent',function()use($drafts,$draft){qcheck($drafts->submit(qpayload(),'synthetic-ci-original'),$draft);$p=qpayload();$p['amount']='180.30';qreject(function()use($drafts,$p){$drafts->submit($p,'synthetic-ci-original');});});
    qtest('native case-sensitive submission keys remain distinct',function()use($drafts){$a=$drafts->submit(qpayload(),'SYNTHETIC-case-key');$b=$drafts->submit(qpayload(),'synthetic-case-key');qcheck($a['id']===$b['id'],false);});
    qtest('fresh physical connection recovers exact persisted source',function()use($draft){$fresh=qdb();try{qcheck(qservice($fresh)->getOwn($draft['id']),$draft);}finally{$fresh->close();}});
    qtest('another employee and office scope cannot recover the source',function()use($db,$draft){qreject(function()use($db,$draft){qservice($db,2002)->getOwn($draft['id']);},DomainException::class);qreject(function()use($db,$draft){qservice($db,2001,array('egypt'=>array('EGP')))->getOwn($draft['id']);},DomainException::class);});
    $receipts=qreceipts($db,$drafts);$meta=null;$png=qimage();
    qtest('native receipt preserves exact private original and normalized preview with audits',function()use($receipts,$draft,$png,&$meta){$meta=$receipts->attach($draft['id'],'synthetic-ci-receipt','中国.png',$png);qcheck($receipts->read($draft['id'],$meta['id'])['bytes'],$png);qcheck($receipts->read($draft['id'],$meta['id'],'preview')['mime'],'application/octet-stream');qcheck(count($receipts->auditOwn($draft['id'])),3);});
    qtest('receipt retry recovers one metadata row and immutable original',function()use($db,$draft,$meta,$png){$fresh=qdb();try{$r=qreceipts($fresh,qservice($fresh));qcheck($r->attach($draft['id'],'synthetic-ci-receipt','中国.png',$png),$meta);qcheck(count($r->listOwn($draft['id'])),1);}finally{$fresh->close();}});
    qtest('real advisory lock contention refuses a competing migration',function()use($db,$name){$key='cpo_oe_'.substr(hash('sha256',$name.'|'.MAIN_DB_PREFIX),0,48);$q="'".$db->escape($key)."'";$db->query('SELECT GET_LOCK('.$q.',0)');$fresh=qdb();try{qreject(function()use($fresh){$m=new OfficeExpenseNativeSchemaMigration(new OfficeExpenseMysqliSchemaBackend($fresh));$m->install($m->plan()['manifest_hash']);},DomainException::class);}finally{$db->query('SELECT RELEASE_LOCK('.$q.')');$fresh->close();}});
    qtest('real source row lock blocks competing receipt before any file creation',function()use($db,$drafts,$png){$source=$drafts->submit(qpayload(),'synthetic-ci-lock-source');$fresh=qdb();if(!$fresh->query('SET SESSION innodb_lock_wait_timeout=1'))throw new RuntimeException('Synthetic session timeout setup failed.');$db->begin();try{if(!$db->query("UPDATE llx_cpo_oe_draft SET rowid=rowid WHERE draft_ref='".$db->escape($source['id'])."'"))throw new RuntimeException('Synthetic source lock failed.');$r=qreceipts($fresh,qservice($fresh));qreject(function()use($r,$source,$png){$r->attach($source['id'],'synthetic-ci-locked-receipt','lock.png',$png);});qcheck($r->inspectOwn($source['id'])['files'],0);}finally{$db->rollback();$fresh->close();}});
    qtest('caller transaction is preserved without source or receipt writes',function()use($db,$drafts){$db->begin();try{qreject(function()use($drafts){$drafts->submit(qpayload(),'synthetic-ci-caller-transaction');},LogicException::class);qcheck($db->transaction_opened,1);}finally{$db->rollback();}});
    qtest('lost commit acknowledgement quarantines the handle and fresh retry recovers once',function()use($name,$password){$lost=new M119CiLostCommit('mysqli','127.0.0.1','root',$password,$name,13306);$s=qservice($lost);$lost->loseNext=true;qreject(function()use($s){$s->submit(qpayload(),'synthetic-ci-lost-commit');},RuntimeException::class);qreject(function()use($s){$s->listOwn();},RuntimeException::class);$lost->close();$fresh=qdb();try{$s=qservice($fresh);$one=$s->submit(qpayload(),'synthetic-ci-lost-commit');qcheck($s->submit(qpayload(),'synthetic-ci-lost-commit'),$one);}finally{$fresh->close();}});
    qtest('no native accounting expense payment bank or ledger table exists in the fixture',function()use($db){$r=$db->query("SELECT TABLE_NAME AS n FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()");$names=array();while($o=$db->fetch_object($r))$names[]=$o->n;sort($names);qcheck($names,array('llx_cpo_oe_draft','llx_cpo_oe_draft_audit','llx_cpo_oe_receipt','llx_cpo_oe_receipt_audit','llx_cpo_oe_schema','m119_ci_owner'));});
} catch(Throwable $error) {
    $failed++;fwrite(STDERR,'FAIL real MariaDB integration: '.get_class($error)." (dependency details withheld)\n");
    if($schema){foreach(array('llx_cpo_oe_schema','llx_cpo_oe_draft','llx_cpo_oe_draft_audit')as$t){try{echo 'OWNED_SCHEMA_METADATA '.json_encode(array('table'=>$t,'metadata'=>$schema->describe($t)),JSON_THROW_ON_ERROR)."\n";}catch(Throwable $ignored){}}}
} finally {
    if($db)$db->close();
    if($created){$r=$admin->query('SELECT nonce FROM `'.$name.'`.m119_ci_owner');$row=$r?$r->fetch_assoc():null;if(($row['nonce']??null)===$nonce){$admin->query('DROP DATABASE `'.$name.'`');$r=$admin->query("SELECT COUNT(*) AS n FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='".$name."'");if(!$r||(int)$r->fetch_assoc()['n']!==0){$failed++;fwrite(STDERR,"FAIL disposable database cleanup\n");}else echo "PASS disposable synthetic database removed and absence verified\n";}else{$failed++;fwrite(STDERR,"FAIL disposable ownership marker; cleanup stopped\n");}}
    if(is_dir($base)&&preg_match('/^m119_ci_[a-f0-9]{16}$/D',basename($base)))qclean($base);
    if($admin)$admin->close();
}
echo "$passed passed, $failed failed; real MariaDB CI only; no existing database/accounts/configuration used\n";exit($failed?1:0);
