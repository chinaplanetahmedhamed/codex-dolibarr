<?php
/**
 * M121 snapshot regression: real SQLite FILE/WAL, independent reader/writer PDOs.
 * Native cash is seeded by frozen managed-position helpers. No TCP/MySQL/server,
 * native trigger/locking semantics or authenticated runtime acceptance is claimed.
 */
if (PHP_SAPI!=='cli') exit(1);
$psArguments=$argv; $argv=array(__FILE__); ob_start();
try { require __DIR__.'/test_m121_managed_position.php'; }
finally { ob_end_clean(); $argv=$psArguments; }
$psChecks=0; $psDirectories=array(); $psConnections=array();
function ps_assert($ok,$message) { $GLOBALS['psChecks']++; if (!$ok) throw new RuntimeException($message); }
register_shutdown_function(function () {
    foreach ($GLOBALS['psConnections'] as $connection) $connection->pdo=null;
    foreach ($GLOBALS['psDirectories'] as $directory) { foreach (glob($directory.'/*') as $file) unlink($file); rmdir($directory); }
});
class M121SnapshotDb extends M121PositionDb {
    public $transaction_opened=0; public $readOnly=false; public $hook=null;
    public $hookFired=false; public $hookReaderInTransaction=null; public $failBegin=false; public $failCommit='';
    public $begins=0; public $commits=0; public $rollbacks=0;
    function __construct($pdo,$readOnly) {
        $this->pdo=$pdo; $this->readOnly=$readOnly;
        $pdo->sqliteCreateFunction('CONCAT',function (...$parts) { return in_array(null,$parts,true)?null:implode('',$parts); },-1);
    }
    function begin() { $this->begins++; if ($this->failBegin) return 0; $ok=$this->pdo->beginTransaction(); if ($ok) $this->transaction_opened=1; return (int)$ok; }
    function commit() {
        $this->commits++; if ($this->failCommit==='before') return 0;
        $ok=$this->pdo->commit(); if ($ok) $this->transaction_opened=0;
        return $this->failCommit==='after'?0:(int)$ok;
    }
    function rollback() { $this->rollbacks++; $ok=$this->pdo->inTransaction()?$this->pdo->rollBack():false; $this->transaction_opened=0; return (int)$ok; }
    function query($sql) {
        if ($this->readOnly&&preg_match('/\A\s*(?:INSERT|UPDATE|DELETE|REPLACE|CREATE|DROP|ALTER|TRUNCATE)\b/i',$sql)) { $this->queries[]=$sql; throw new RuntimeException('Snapshot reader attempted business DML/DDL'); }
        if ($this->hook&&!$this->hookFired&&strpos($sql,'SELECT * FROM llx_cpcc_fund_allocation WHERE entity=1 AND fk_receipt=')===0) {
            $this->hookFired=true; $this->hookReaderInTransaction=$this->pdo->inTransaction(); ($this->hook)();
        }
        return parent::query($sql);
    }
}
function ps_fixture() {
    list($seed)=mp_fixture();
    $directory='/tmp/m121-position-snapshot-'.bin2hex(random_bytes(6)); mkdir($directory,0700); $GLOBALS['psDirectories'][]=$directory;
    $file=$directory.'/fixture.sqlite';
    $writerPdo=new PDO('sqlite:'.$file,null,null,array(PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION));
    ps_assert(strtolower($writerPdo->query('PRAGMA journal_mode=WAL')->fetchColumn())==='wal','Fresh file uses WAL');
    $writerPdo->exec('PRAGMA busy_timeout=2000');
    $tables=$seed->pdo->query("SELECT name,sql FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($tables as $table) {
        $writerPdo->exec($table['sql']);
        foreach ($seed->pdo->query('SELECT * FROM "'.$table['name'].'"')->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $columns=array_map(function ($name) { return '"'.$name.'"'; },array_keys($row));
            $q=$writerPdo->prepare('INSERT INTO "'.$table['name'].'"('.implode(',',$columns).') VALUES('.implode(',',array_fill(0,count($row),'?')).')'); $q->execute(array_values($row));
        }
    }
    $writerPdo->exec('CREATE TABLE snapshot_caller_probe(rowid INTEGER PRIMARY KEY,note TEXT)');
    $readerPdo=new PDO('sqlite:'.$file,null,null,array(PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION)); $readerPdo->exec('PRAGMA busy_timeout=2000');
    ps_assert($readerPdo!==$writerPdo&&strtolower($readerPdo->query('PRAGMA journal_mode')->fetchColumn())==='wal','Two independent actual PDO connections share one WAL file');
    $reader=new M121SnapshotDb($readerPdo,true); $writer=new M121SnapshotDb($writerPdo,false);
    $GLOBALS['psConnections'][]=$reader; $GLOBALS['psConnections'][]=$writer;
    $writeService=new CustomerFundsService($writer,1); $receipt=mp_register($writer,$writeService); $allocation=mp_reserve($writeService,$receipt);
    mp_supplier($writer,$allocation); // All existing native payment/bank evidence precedes interleave.
    return array($reader,$writer,new CustomerFundsService($reader,1),$receipt,$allocation);
}
function ps_signature($result) {
    $b=$result['currencies']['USD']??array();
    $out=array('coverage'=>$result['coverage'],'reasons'=>$result['reasons'],'bucket_coverage'=>$b['coverage']??null,'bucket_reasons'=>$b['reasons']??null);
    foreach (array('received_cash','paid_cash','reserved','held_cash','available_to_allocate','assigned') as $field) $out[$field]=isset($b[$field])?(float)$b[$field]:null;
    $out['customer_payment_ids']=$b['customer_payment_ids']??null; $out['supplier_payment_ids']=$b['supplier_payment_ids']??null; return $out;
}
function ps_expected($post,$insert) {
    return array('coverage'=>'verified_managed','reasons'=>array(),'bucket_coverage'=>'verified_managed','bucket_reasons'=>array(),
        'received_cash'=>100.0,'paid_cash'=>$post?40.0:0.0,'reserved'=>$post?($insert?20.0:0.0):40.0,'held_cash'=>$post?60.0:100.0,'available_to_allocate'=>$post&&$insert?40.0:60.0,'assigned'=>$post&&$insert?60.0:40.0,'customer_payment_ids'=>array(10),'supplier_payment_ids'=>$post?array(200):array());
}
function ps_commit_writer($writer,$allocation,$insert) {
    $row=$writer->rows('cpcc_fund_allocation')[0];
    $writer->begin();
    try {
        $q=$writer->pdo->prepare("UPDATE llx_cpcc_fund_allocation SET status='settled',disbursement_type='supplier_payment',disbursement_id=200 WHERE rowid=?"); $q->execute(array($allocation));
        if ($insert) { $row['rowid']=2; $row['amount']=20; $row['request_key']=hash('sha256','M121 committed reservation20'); $row['status']='reserved'; $row['disbursement_type']=null; $row['disbursement_id']=null; $writer->insert('cpcc_fund_allocation',$row); }
        if (!$writer->commit()) throw new RuntimeException('Fixture writer commit failed');
    } catch (Throwable $error) { $writer->rollback(); throw $error; }
}
function ps_read_only($reader,$start) {
    foreach (array_slice($reader->queries,$start) as $sql) ps_assert(!preg_match('/\A\s*(?:INSERT|UPDATE|DELETE|REPLACE|CREATE|DROP|ALTER|TRUNCATE)\b/i',$sql),'Actual reader issued no business DML/DDL');
}
function ps_unknown($result,$label) {
    ps_assert(!in_array($result['coverage'],array('verified_managed','not_managed'),true)&&$result['reasons']!==array(),$label.' fails closed with a reason');
    foreach ($result['currencies'] as $bucket) foreach (array('received_cash','paid_cash','reserved','held_cash','available_to_allocate','assigned') as $field) ps_assert(array_key_exists($field,$bucket)&&$bucket[$field]===null,$label.' cannot certify '.$field);
}

// Stronger insertion case first: old available60 versus post available40 exposes the old hybrid.
foreach (array(true,false) as $insert) {
    list($reader,$writer,$service,$receipt,$allocation)=ps_fixture();
    $reader->hook=function () use ($writer,$allocation,$insert) { ps_commit_writer($writer,$allocation,$insert); };
    $start=count($reader->queries); $result=$service->currentPosition(7); $signature=ps_signature($result);
    ps_assert($reader->hookFired&&!$writer->pdo->inTransaction(),'Independent writer committed inside the known reader SELECT boundary');
    if ($signature!==ps_expected(false,$insert)&&$signature!==ps_expected(true,$insert)) fwrite(STDERR,'M121 snapshot RED mixed result: '.json_encode($signature).PHP_EOL);
    ps_assert($signature===ps_expected(false,$insert)||$signature===ps_expected(true,$insert),'One coherent pre-or-post snapshot; no mixed reservation/cash/control state');
    ps_assert(!$reader->pdo->inTransaction()&&$reader->transaction_opened===0,'Reader releases its owned snapshot transaction');
    ps_read_only($reader,$start);
    $next=$service->currentPosition(7);
    ps_assert(ps_signature($next)===ps_expected(true,$insert),'Next independent read sees the committed writer state');
    ps_assert(count($writer->rows('cpcc_financial_entry'))===0&&count($writer->rows('paiement'))===1&&count($writer->rows('paiementfourn'))===1,'Interleave creates no fake debit or native cash');
}

// Existing caller isolation is not certified: refuse without touching ownership/work.
list($reader,$writer,$service)=ps_fixture();
$reader->begin(); $reader->pdo->exec("INSERT INTO snapshot_caller_probe VALUES(1,'caller owns this write')");
$counts=array($reader->begins,$reader->commits,$reader->rollbacks);
$start=count($reader->queries); $result=$service->currentPosition(7);
ps_unknown($result,'Caller-owned transaction');
ps_assert(in_array('snapshot_unavailable',$result['reasons'],true),'Caller transaction refusal identifies snapshot_unavailable');
ps_assert($reader->pdo->inTransaction()&&$reader->transaction_opened===1,'Reader preserves caller transaction ownership');
ps_assert(array($reader->begins,$reader->commits,$reader->rollbacks)===$counts,'Reader never begins, commits or rolls back caller transaction');
ps_assert((int)$reader->pdo->query('SELECT COUNT(*) FROM snapshot_caller_probe')->fetchColumn()===1&&(int)$writer->pdo->query('SELECT COUNT(*) FROM snapshot_caller_probe')->fetchColumn()===0,'Caller work remains present and uncommitted');
ps_read_only($reader,$start); $reader->rollback();
ps_assert((int)$writer->pdo->query('SELECT COUNT(*) FROM snapshot_caller_probe')->fetchColumn()===0,'Only explicit caller rollback removes caller work');

foreach (array('begin','query','commit_before','commit_after') as $failure) {
    list($reader,$writer,$service,$receipt,$allocation)=ps_fixture();
    // Commit writer state before the read; reader failures must preserve that durable state.
    ps_commit_writer($writer,$allocation,true); $durable=mp_state($writer);
    if ($failure==='begin') $reader->failBegin=true;
    elseif ($failure==='query') $reader->failNeedle='bank_url';
    else $reader->failCommit=$failure==='commit_before'?'before':'after';
    $start=count($reader->queries); $result=$service->currentPosition(7); ps_unknown($result,$failure);
    if ($failure==='begin') ps_assert($reader->rollbacks===0,'Failed begin cannot roll back an unowned transaction');
    ps_assert(!$reader->pdo->inTransaction()&&$reader->transaction_opened===0,$failure.' cleans only its own snapshot transaction');
    ps_assert(mp_state($writer)===$durable,$failure.' preserves committed writer state'); ps_read_only($reader,$start);
    $reader->failBegin=false; $reader->failNeedle=null; $reader->failCommit='';
    ps_assert(ps_signature($service->currentPosition(7))===ps_expected(true,true),$failure.' next read recovers committed state');
}
echo 'M121 position snapshot PASS ('.$psChecks.' checks; real SQLite FILE/WAL with two PDO connections; seeded native evidence; no MySQL/trigger/native concurrency claim)'.PHP_EOL;
