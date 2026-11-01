<?php
declare(strict_types=1);
// This bootstrap reads native library/schema source only. No real config, main.inc.php or rows.
define('DOL_DOCUMENT_ROOT', realpath($argv[1]));
define('MAIN_DB_PREFIX', 'llx_');
define('DOL_URL_ROOT', '');
define('M119_SYNTHETIC_NATIVE', true);
$m119NativeDirectories=array();
$firstDirectory=sys_get_temp_dir().'/m119-native-'.bin2hex(random_bytes(8));
mkdir($firstDirectory,0700); define('DOL_DATA_ROOT',$firstDirectory);
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/db/DoliDB.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/db/sqlite3.class.php';
require_once DOL_DOCUMENT_ROOT.'/expensereport/class/expensereport.class.php';

// Upstream 24.0.1's SQLite constructor registers missing dbIF/dbMONTH helpers.
// This test-only constructor supplies a fresh SQLite connection; inherited SQL,
// transaction handling and all ExpenseReport/Line/price methods remain native.
final class M119NativeSqlite extends DoliDBSqlite3
{
    public function __construct(string $name)
    {
        $this->db=new SQLite3(DOL_DATA_ROOT.'/database_'.$name.'.sdb');
        $this->database_name=$name;$this->transaction_opened=0;
        $this->connected=true;$this->ok=true;$this->database_selected=true;
    }
    // Native driver's num_rows still expects PDO's queryString on SQLite3Result.
    // Count and rewind actual SELECT results without changing native expense logic.
    public function num_rows($result)
    {
        $count=0;
        while ($result->fetchArray(SQLITE3_ASSOC) !== false) $count++;
        $result->reset();
        return $count;
    }
}

function m119NativeDatabase(string $currency,int $entity) {
    global $conf,$langs,$hookmanager,$mysoc,$main_data_dir,$m119NativeDirectories,$action;
    $main_data_dir=DOL_DATA_ROOT;
    file_put_contents(DOL_DATA_ROOT.'/.m119-native-synthetic','Synthetic M119 tests only');
    $m119NativeDirectories[]=DOL_DATA_ROOT;
    $conf=(object)array('entity'=>$entity,'currency'=>$currency,'global'=>(object)array('MAIN_MAX_DECIMALS_TOT'=>2,'MAIN_MAX_DECIMALS_UNIT'=>8,'MAIN_MAX_DECIMALS_SHOWN'=>2,'SYSLOG_LEVEL'=>0),
        'db'=>(object)array('prefix'=>'llx_'),'loghandlers'=>array(),'modules_parts'=>array('triggers'=>array()),'expensereport'=>(object)array('enabled'=>1));
    $langs=new class { public $defaultlang='en_US';public function load($v){}public function loadLangs($v){}public function trans($key,...$args){return $key;}public function transnoentities($key,...$args){return $key;}public function transnoentitiesnoconv($key,...$args){return $key==='SeparatorDecimal'?'.':($key==='SeparatorThousand'?'None':$key);} };
    $hookmanager=new class { public function executeHooks($context,$parameters,$object,$action=''){return 0;} };
    $mysoc=(object)array('country_id'=>1,'localtax1_assuj'=>0,'localtax2_assuj'=>0);
    $action='';
    $db=new M119NativeSqlite('m119_office_'.bin2hex(random_bytes(6)));
    $db->db->enableExceptions(true);
    foreach (array('llx_expensereport.sql','llx_expensereport_det.sql') as $table) {
        $sql=file_get_contents(DOL_DOCUMENT_ROOT.'/install/mysql/tables/'.$table);
        $sql=preg_replace('/--[^\n]*/','',$sql);
        // SQLite lacks MySQL timestamp auto-update syntax; no business columns removed.
        $sql=str_ireplace('ON UPDATE CURRENT_TIMESTAMP','',$sql);
        if (!$db->query(trim($sql))) throw new RuntimeException('Synthetic native schema failed: '.$db->lasterror());
    }
    $db->query('CREATE TABLE llx_c_type_fees (id INTEGER PRIMARY KEY,code TEXT,active INTEGER)');
    $db->query("INSERT INTO llx_c_type_fees VALUES (7,'EX_OTHER',1),(8,'EX_OTHER2',1),(9,'EX_KME',1)");
    $db->query('CREATE TABLE llx_oe_native_draft (entity INTEGER NOT NULL,owner_id INTEGER NOT NULL,draft_id TEXT NOT NULL,payload_hash TEXT NOT NULL,snapshot_json TEXT NOT NULL,native_id INTEGER,status TEXT NOT NULL,UNIQUE(entity,owner_id,draft_id),UNIQUE(entity,native_id))');
    return $db;
}

function m119NativeCleanup(): void {
    // Only files in this process's randomly named synthetic temp directory are removed.
    if (!preg_match('/^m119-native-[a-f0-9]{16}$/D',basename(DOL_DATA_ROOT))) throw new LogicException('Unsafe synthetic cleanup path.');
    foreach (glob(DOL_DATA_ROOT.'/*') as $file) if (is_file($file)) unlink($file);
    @unlink(DOL_DATA_ROOT.'/.m119-native-synthetic'); @rmdir(DOL_DATA_ROOT);
}
register_shutdown_function('m119NativeCleanup');
