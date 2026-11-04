<?php
/**
 * Synthetic SQLite execution of generated financial SELECTs; no runtime/config/network.
 * Verifies event creation/payment cutoffs, not past invoice lifecycle or credit application.
 * Only SHOW TABLES metadata is translated to sqlite_master; financial SQL is unchanged.
 */
error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
define('MAIN_DB_PREFIX', 'llx_');
$conf = (object) array('entity'=>2);
$m121CutoffValidation = 0;
function getDolGlobalInt($key) { return $GLOBALS['m121CutoffValidation']; }
require_once dirname(__DIR__, 2).'/custom/chinaplanetcustomercenter/lib/financial.lib.php';
$m121CutoffChecks = 0;
function m121c_assert($condition, $message) {
    $GLOBALS['m121CutoffChecks']++;
    if (!$condition) throw new RuntimeException($message);
}
function m121c_near($actual, $expected, $message) {
    m121c_assert(abs((float) $actual - $expected) < 0.000001, $message.'; observed '.var_export($actual, true));
}
function m121c_throws($call, $message) {
    try { $call(); } catch (InvalidArgumentException $exception) {
        m121c_assert(true, $message); return;
    }
    m121c_assert(false, $message);
}
class M121CutoffRows {
    public $rows;
    public function __construct($rows) { $this->rows = $rows; }
}
class M121CutoffDb {
    public $pdo;
    public $queries = array();
    private $paymentId = 0;
    public function __construct() {
        $this->pdo = new PDO('sqlite::memory:', null, null, array(PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION));
        foreach (array(
            'CREATE TABLE llx_facture(rowid INTEGER PRIMARY KEY,ref TEXT,ref_client TEXT,entity INTEGER,fk_soc INTEGER,fk_projet INTEGER,datef TEXT,date_valid TEXT,date_closing TEXT,date_lim_reglement TEXT,multicurrency_code TEXT,total_ttc REAL,multicurrency_total_ttc REAL,fk_statut INTEGER,paye INTEGER,type INTEGER)',
            'CREATE TABLE llx_paiement(rowid INTEGER PRIMARY KEY,ref TEXT,num_paiement TEXT,entity INTEGER,datep TEXT,statut INTEGER)',
            'CREATE TABLE llx_paiement_facture(rowid INTEGER PRIMARY KEY,fk_paiement INTEGER,fk_facture INTEGER,amount REAL,multicurrency_amount REAL,multicurrency_code TEXT)',
            'CREATE TABLE llx_societe_remise_except(rowid INTEGER PRIMARY KEY,entity INTEGER,fk_soc INTEGER,datec TEXT,amount_ttc REAL,multicurrency_amount_ttc REAL,multicurrency_code TEXT,fk_facture INTEGER,fk_facture_line INTEGER,fk_facture_source INTEGER)',
            'CREATE TABLE llx_facturedet(rowid INTEGER PRIMARY KEY,fk_facture INTEGER)',
            'CREATE TABLE llx_projet(rowid INTEGER PRIMARY KEY,entity INTEGER,fk_soc INTEGER,ref TEXT,title TEXT)',
            'CREATE TABLE llx_cpcc_project_charge(rowid INTEGER PRIMARY KEY,entity INTEGER,fk_soc INTEGER,fk_project INTEGER,charge_date TEXT,category TEXT,label TEXT,supplier_label TEXT,amount REAL,currency_code TEXT,reference TEXT,source_type TEXT,source_id INTEGER,status INTEGER,customer_visible INTEGER)',
            'CREATE TABLE llx_cpcc_financial_entry(rowid INTEGER PRIMARY KEY,entity INTEGER,fk_soc INTEGER,fk_charge INTEGER,fk_project INTEGER,entry_date TEXT,entry_type TEXT,direction TEXT,description TEXT,amount REAL,currency_code TEXT,reference TEXT,bank_label TEXT,allocated_amount REAL,allocated_currency TEXT,exchange_rate REAL,transfer_group TEXT,proof_ecm_id INTEGER,source_type TEXT,source_id INTEGER,status INTEGER,customer_visible INTEGER)'
        ) as $sql) $this->pdo->exec($sql);
        $this->insert('llx_projet', array('rowid'=>11,'entity'=>2,'fk_soc'=>7,'ref'=>'SYN-11','title'=>'Synthetic'));
    }
    public function insert($table, $row) {
        $statement = $this->pdo->prepare('INSERT INTO '.$table.'('.implode(',', array_keys($row)).') VALUES('.implode(',', array_fill(0, count($row), '?')).')');
        $statement->execute(array_values($row));
    }
    public function invoice($values = array()) {
        $this->insert('llx_facture', array_merge(array('rowid'=>1,'ref'=>'SYN-1','ref_client'=>'SYN','entity'=>2,'fk_soc'=>7,'fk_projet'=>11,'datef'=>'2026-10-01','date_valid'=>'2026-10-01','date_closing'=>null,'date_lim_reglement'=>'2026-10-30','multicurrency_code'=>'USD','total_ttc'=>100,'multicurrency_total_ttc'=>100,'fk_statut'=>1,'paye'=>0,'type'=>0), $values));
    }
    public function payment($date, $amount, $values = array()) {
        $id = ++$this->paymentId;
        $this->insert('llx_paiement', array_merge(array('rowid'=>$id,'ref'=>'SYN-P'.$id,'num_paiement'=>'SYN','entity'=>2,'datep'=>$date,'statut'=>1), $values));
        $this->insert('llx_paiement_facture', array('rowid'=>$id,'fk_paiement'=>$id,'fk_facture'=>1,'amount'=>$amount,'multicurrency_amount'=>$amount,'multicurrency_code'=>'USD'));
    }
    public function credit($date, $values = array()) {
        // No source invoice is required: native manual credits remain supported.
        $this->insert('llx_societe_remise_except', array_merge(array('rowid'=>1,'entity'=>2,'fk_soc'=>7,'datec'=>$date,'amount_ttc'=>25,'multicurrency_amount_ttc'=>25,'multicurrency_code'=>'USD','fk_facture'=>1,'fk_facture_line'=>null,'fk_facture_source'=>null), $values));
    }
    public function charge($values = array()) {
        $this->insert('llx_cpcc_project_charge', array_merge(array('rowid'=>1,'entity'=>2,'fk_soc'=>7,'fk_project'=>11,'charge_date'=>'2026-10-01','category'=>'supplier','label'=>'Synthetic','supplier_label'=>'Internal synthetic supplier','amount'=>100,'currency_code'=>'USD','reference'=>'SYN','source_type'=>'manual','source_id'=>0,'status'=>1,'customer_visible'=>1), $values));
    }
    public function entry($values = array()) {
        $this->insert('llx_cpcc_financial_entry', array_merge(array('rowid'=>1,'entity'=>2,'fk_soc'=>7,'fk_charge'=>1,'fk_project'=>11,'entry_date'=>'2026-11-03 12:00:00','entry_type'=>'supplier_payment','direction'=>'D','description'=>'Synthetic','amount'=>25,'currency_code'=>'USD','reference'=>'SYN','bank_label'=>'','allocated_amount'=>25,'allocated_currency'=>'USD','exchange_rate'=>null,'transfer_group'=>'','proof_ecm_id'=>0,'source_type'=>'manual','source_id'=>0,'status'=>1,'customer_visible'=>1), $values));
    }
    public function escape($value) { return str_replace("'", "''", $value); }
    public function query($sql) {
        $this->queries[] = $sql;
        if (preg_match("/^SHOW TABLES LIKE '([a-zA-Z0-9_]+)'$/", $sql, $match)) {
            $statement = $this->pdo->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name=?");
            $statement->execute(array($match[1]));
        } else {
            $statement = $this->pdo->query($sql);
        }
        return new M121CutoffRows($statement->fetchAll(PDO::FETCH_OBJ));
    }
    public function num_rows($result) { return count($result->rows); }
    public function fetch_object($result) { return array_shift($result->rows); }
}
function m121c_remaining($db, $asOf = '2026-11-03', $project = 11) {
    return array_sum(array_column(cpcc_financial_unpaid_invoices($db, 7, 'USD', $project, $asOf), 'remaining'));
}
function m121c_allocated($db, $asOf = '2026-11-03', $project = 11) {
    return array_sum(array_column(cpcc_financial_native_allocations($db, 7, 'USD', $project, $asOf), 'amount'));
}
m121c_assert(function_exists('cpcc_financial_cutoff_sql'), 'Missing validated financial event cutoff helper');
$db = new M121CutoffDb();
m121c_assert(cpcc_financial_cutoff_sql($db, 'p.datep', '') === '', 'Empty date keeps current mode');
m121c_assert(cpcc_financial_cutoff_sql($db, 'p.datep', null) === '', 'Null date keeps current mode');
foreach (array('2024-02-28'=>'2024-02-29','2024-02-29'=>'2024-03-01','2026-02-28'=>'2026-03-01','2026-12-31'=>'2027-01-01','1000-01-01'=>'1000-01-02') as $date=>$next) {
    $where = cpcc_financial_cutoff_sql($db, 'p.datep', $date);
    $db->pdo->exec('CREATE TABLE IF NOT EXISTS cutoff_probe(datep TEXT)');
    $db->pdo->exec('DELETE FROM cutoff_probe');
    $db->insert('cutoff_probe', array('datep'=>$date.' 23:59:59'));
    $db->insert('cutoff_probe', array('datep'=>$next.' 00:00:00'));
    m121c_assert(count($db->query('SELECT p.datep FROM cutoff_probe p WHERE 1=1'.$where)->rows) === 1, 'Calendar cutoff rollover '.$date);
}
foreach (array('2026-02-29','2026-02-31','2026-13-01','2026-00-01','2026-11-3','2026-11-03 12:00:00',"2026-11-03\n","2026-11-03\r\n",' 2026-11-03',' ','0000-01-01','0999-12-31','9999-12-31',array(),42,false) as $invalid) {
    $where = cpcc_financial_cutoff_sql($db, 'datep', $invalid);
    m121c_assert(count($db->query('SELECT datep FROM cutoff_probe WHERE 1=1'.$where)->rows) === 0, 'Invalid input must yield valid fail-closed SQL');
}
foreach (array('p.datep OR 1=1','p.datep; DROP TABLE x','p..datep','p.datep.extra',"p.datep\n",'') as $column) {
    m121c_throws(function () use ($db, $column) { cpcc_financial_cutoff_sql($db, $column, '2026-11-03'); }, 'Unsafe internal identifier must throw');
}
m121c_throws(function () { cpcc_financial_applied_credit_sql(null, '2026-11-03'); }, 'Credit cutoff requires db argument');
$compatibility = new M121CutoffDb();
$compatibility->invoice(); $compatibility->credit('2026-11-05 12:00:00');
$unscopedCredits = $compatibility->query(cpcc_financial_applied_credit_sql())->rows;
m121c_assert(count($unscopedCredits) === 1 && (int) $unscopedCredits[0]->fk_facture === 1, 'No-argument credit SQL executes and retains invoice identity');
m121c_near($unscopedCredits[0]->amount, 25, 'No-argument credit SQL preserves current unbounded aggregate');

// All times on the named day are included; next midnight is excluded.
$db = new M121CutoffDb(); $db->invoice();
foreach (array('2026-11-03 00:00:00','2026-11-03 12:00:00','2026-11-03 23:59:59','2026-11-04 00:00:00') as $date) $db->payment($date, 10);
m121c_near(m121c_remaining($db), 70, 'Only same-day payment30 reduces unpaid invoices');
m121c_near(m121c_allocated($db), 30, 'Whole cutoff day allocations agree with unpaid evidence');
m121c_near(array_sum(array_column(cpcc_financial_native_receipts($db, 7, 'USD', '2026-11-03'), 'amount')), 30, 'Whole cutoff day native receipts');
m121c_near(m121c_remaining($db, ''), 60, 'Current mode includes later payments');
m121c_near(m121c_allocated($db, ''), 40, 'Current allocations unchanged');
$db = new M121CutoffDb(); $db->invoice(); $db->payment('2026-11-05 12:00:00', 25);
m121c_near(m121c_remaining($db), 100, 'Future partial payment cannot reduce dated unpaid');
m121c_near(m121c_allocated($db), 0, 'Future partial payment not allocated at cutoff');

foreach (array('2026-11-03 12:00:00'=>25,'2026-11-03 23:59:59'=>25,'2026-11-04 00:00:00'=>0) as $date=>$expected) {
    $db = new M121CutoffDb(); $db->invoice(); $db->credit($date);
    m121c_near(m121c_allocated($db), $expected, 'Manual credit creation cutoff '.$date);
    $aggregate = $db->query(cpcc_financial_applied_credit_sql($db, '2026-11-03', 2, 7))->rows;
    m121c_near(array_sum(array_map(function ($row) { return $row->amount; }, $aggregate)), $expected, 'Credit aggregate creation cutoff '.$date);
}
// Existing invoice-line discount path: only fd links the credit to its target.
// Net75 is the current invoice value; this does not reconstruct a past invoice net.
foreach (array('2026-11-02 12:00:00'=>25,'2026-11-03 23:59:59'=>25,'2026-11-04 00:00:00'=>0) as $date=>$expected) {
    $db = new M121CutoffDb(); $db->invoice(array('total_ttc'=>75,'multicurrency_total_ttc'=>75));
    $db->insert('llx_facturedet', array('rowid'=>11,'fk_facture'=>1));
    $db->credit($date, array('fk_facture'=>null,'fk_facture_line'=>11));
    m121c_near(m121c_allocated($db), $expected, 'Invoice-line-only credit allocation cutoff '.$date);
    $aggregate = $db->query(cpcc_financial_applied_credit_sql($db, '2026-11-03', 2, 7))->rows;
    m121c_near(array_sum(array_map(function ($row) { return $row->amount; }, $aggregate)), $expected, 'Invoice-line-only aggregate target join '.$date);
}
$db = new M121CutoffDb(); $db->invoice(); $db->credit(null);
m121c_near(m121c_allocated($db), 0, 'Unknown credit creation time excluded from explicit cutoff');
m121c_near(m121c_allocated($db, ''), 25, 'Unknown creation time retained in current mode');
foreach (array(array('entity'=>3),array('fk_soc'=>8)) as $foreign) {
    $db = new M121CutoffDb(); $db->invoice(); $db->credit('2026-11-03 12:00:00', $foreign);
    m121c_near(m121c_allocated($db), 0, 'Credit source scope isolation');
    m121c_assert(count($db->query(cpcc_financial_applied_credit_sql($db, '', 2, 7))->rows) === 0, 'Credit aggregate source scope isolation');
}
$db = new M121CutoffDb(); $db->invoice(); $db->payment('2026-11-03 12:00:00', 25, array('entity'=>3));
m121c_near(m121c_remaining($db), 100, 'Foreign payment cannot reduce active entity invoice');

// Preserve validation policy and current invoice eligibility, not historical reconstruction.
foreach (array(0=>75,1=>100) as $validation=>$remaining) {
    $m121CutoffValidation = $validation;
    $db = new M121CutoffDb(); $db->invoice(); $db->payment('2026-11-03 12:00:00', 25, array('statut'=>0));
    m121c_near(m121c_remaining($db), $remaining, 'Existing payment validation policy');
}
$m121CutoffValidation = 0;
foreach (array(array('fk_statut'=>0),array('fk_statut'=>2,'paye'=>1),array('fk_statut'=>3),array('paye'=>1),array('entity'=>3),array('fk_soc'=>8),array('fk_projet'=>12),array('datef'=>'2026-11-04')) as $ineligible) {
    $db = new M121CutoffDb(); $db->invoice($ineligible);
    m121c_near(m121c_remaining($db), 0, 'Invoice current state/entity/customer/project/date eligibility');
}
$db = new M121CutoffDb(); $db->invoice(array('datef'=>'2026-11-03'));
m121c_near(m121c_remaining($db), 100, 'Invoice DATE cutoff inclusive');
$db = new M121CutoffDb(); $db->invoice(array('fk_projet'=>12));
m121c_near(m121c_remaining($db, '2026-11-03', 0), 100, 'All-project mode retains other project');
$db = new M121CutoffDb(); $db->invoice(); $db->invoice(array('rowid'=>2,'fk_projet'=>12,'total_ttc'=>900,'multicurrency_total_ttc'=>900));
$summary = cpcc_financial_summary($db, 7, 'USD', 11, '2026-11-03');
m121c_near($summary['USD']['unpaid_invoices'], 100, 'Summary forwards project scope to invoice read');

// Future/foreign charge payments cannot reduce a past same-scope commitment.
foreach (array(array('entry_date'=>'2026-11-03 23:59:59'),array('entry_date'=>'2026-11-04 00:00:00'),array('entity'=>3),array('fk_soc'=>8)) as $entry) {
    $db = new M121CutoffDb(); $db->charge(); $db->entry($entry);
    $charges = cpcc_financial_commitment_rows($db, 7, 'USD', 11, '2026-11-03');
    m121c_assert(count($charges) === 1, 'LEFT JOIN cutoff must preserve unpaid charge');
    $expected = isset($entry['entry_date']) && $entry['entry_date'] === '2026-11-03 23:59:59' ? 75 : 100;
    m121c_near($charges[0]['remaining'], $expected, 'Charge paid amount uses same cutoff and entity/customer');
    m121c_assert($charges[0]['supplier'] === '', 'Supplier identity remains private by default');
}
$db = new M121CutoffDb(); $db->charge(); $db->entry(array('entry_date'=>'2026-11-04 00:00:00'));
m121c_near(cpcc_financial_commitment_rows($db, 7, 'USD', 11)[0]['remaining'], 75, 'Current charge payments unchanged');
$db = new M121CutoffDb(); $db->charge(array('charge_date'=>'2026-11-04')); $db->entry();
m121c_assert(cpcc_financial_commitment_rows($db, 7, 'USD', 11, '2026-11-03') === array(), 'Future charge excluded');
m121c_assert(count(cpcc_financial_custom_entries($db, 7, 'USD', 11, '2026-11-03')) === 1, 'Same-day custom entry retained');
$db = new M121CutoffDb(); $db->entry(array('entry_date'=>'2026-11-04 00:00:00'));
m121c_assert(cpcc_financial_custom_entries($db, 7, 'USD', 11, '2026-11-03') === array(), 'Future custom entry excluded');

// Invalid dates must close outer reads, not only empty an aggregate subquery.
foreach (array('2026-02-31',' ','9999-12-31',array()) as $invalid) {
    $db = new M121CutoffDb();
    $db->invoice(array('datef'=>'2026-01-01'));
    $db->charge(array('charge_date'=>'2026-01-01'));
    $db->entry(array('entry_date'=>'2026-01-02'));
    $db->credit('2026-01-01');
    $db->payment('2026-01-02 12:00:00', 10);
    m121c_near(m121c_remaining($db, $invalid), 0, 'Invalid date closes outer invoice query');
    m121c_near(m121c_allocated($db, $invalid), 0, 'Invalid date closes both native allocation queries');
    m121c_assert(cpcc_financial_native_receipts($db, 7, 'USD', $invalid) === array(), 'Invalid date closes receipts');
    m121c_assert(cpcc_financial_custom_entries($db, 7, 'USD', 11, $invalid) === array(), 'Invalid date closes custom entries');
    m121c_assert(cpcc_financial_commitment_rows($db, 7, 'USD', 11, $invalid) === array(), 'Invalid date closes commitment query');
}
// Gross/credit formula and current lifecycle remain explicitly unchanged.
$amounts = cpcc_financial_invoice_amounts(100, 0, 25);
m121c_near($amounts['total'], 125, 'Existing gross formula unchanged');
m121c_near($amounts['remaining'], 100, 'Existing remaining formula unchanged');
echo 'M121 financial cutoff regressions passed ('.$m121CutoffChecks." checks; SQLite generated SELECTs; not historical lifecycle acceptance)\n";
