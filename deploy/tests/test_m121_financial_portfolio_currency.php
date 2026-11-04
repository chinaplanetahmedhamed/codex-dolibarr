<?php
/**
 * Render the real portfolio with actual financial SELECTs against synthetic SQLite.
 * Only SHOW TABLES is translated; bootstrap/shell/formatting are isolated stubs.
 * No native runtime, network, private data, posting or historical acceptance.
 */
error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
define('MAIN_DB_PREFIX', 'llx_');
function m121p_assert($condition, $message) {
    if (!$condition) throw new RuntimeException($message);
}
function getDolGlobalInt($key) { return 0; }
function getDolGlobalString($key) { return ''; }
function GETPOST($key, $type) { global $scenario; return $key === 'asof' ? ($scenario==='historical'?'2000-01-01':'2026-11-03') : ''; }
function dol_escape_htmltag($text) { return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8'); }
function cpcc_portal_money($amount) { return number_format($amount, 2, '.', ','); }
function dol_buildpath($path, $absolute) { return $path; }
function cpcc_admin_shell_asset($path) { return $path; }
function cpcc_admin_shell_open(...$args) {}
function cpcc_admin_shell_close() {}
function llxHeader(...$args) {}
function llxFooter() {}
function accessforbidden(...$args) { throw new DomainException('Fixture access denied'); }
class CustomerFundsService {
    public static $reads=0; public function __construct($db,$entity) {}
    public function currentPosition($customer,$project=0) { self::$reads++; return array('scope'=>'managed_receipts_only','coverage'=>'unavailable','currencies'=>array()); }
}
class M121PortfolioRows {
    public $rows;
    public function __construct($rows) { $this->rows = $rows; }
}
class M121PortfolioDb {
    public $pdo;
    public function __construct() {
        $this->pdo = new PDO('sqlite::memory:', null, null, array(PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION));
        foreach (array(
            'societe'=>'rowid INTEGER PRIMARY KEY,entity INTEGER,client INTEGER,nom TEXT',
            'facture'=>'rowid INTEGER PRIMARY KEY,ref TEXT,ref_client TEXT,entity INTEGER,fk_soc INTEGER,fk_projet INTEGER,datef TEXT,date_lim_reglement TEXT,multicurrency_code TEXT,total_ttc REAL,multicurrency_total_ttc REAL,fk_statut INTEGER,paye INTEGER,type INTEGER',
            'paiement'=>'rowid INTEGER PRIMARY KEY,ref TEXT,num_paiement TEXT,entity INTEGER,datep DATETIME,statut INTEGER',
            'paiement_facture'=>'fk_paiement INTEGER,fk_facture INTEGER,amount REAL,multicurrency_amount REAL,multicurrency_code TEXT',
            'societe_remise_except'=>'rowid INTEGER PRIMARY KEY,entity INTEGER,fk_soc INTEGER,datec DATETIME,amount_ttc REAL,multicurrency_amount_ttc REAL,multicurrency_code TEXT,fk_facture INTEGER,fk_facture_line INTEGER,fk_facture_source INTEGER',
            'facturedet'=>'rowid INTEGER PRIMARY KEY,fk_facture INTEGER',
            'projet'=>'rowid INTEGER PRIMARY KEY,entity INTEGER,fk_soc INTEGER,ref TEXT,title TEXT',
            'cpcc_project_charge'=>'rowid INTEGER PRIMARY KEY,entity INTEGER,fk_soc INTEGER,fk_project INTEGER,charge_date DATETIME,category TEXT,label TEXT,supplier_label TEXT,amount REAL,currency_code TEXT,reference TEXT,source_type TEXT,source_id INTEGER,status INTEGER,customer_visible INTEGER',
            'cpcc_financial_entry'=>'rowid INTEGER PRIMARY KEY,entity INTEGER,fk_soc INTEGER,fk_charge INTEGER,fk_project INTEGER,entry_date DATETIME,entry_type TEXT,direction TEXT,description TEXT,amount REAL,currency_code TEXT,reference TEXT,bank_label TEXT,allocated_amount REAL,allocated_currency TEXT,exchange_rate REAL,transfer_group TEXT,proof_ecm_id INTEGER,source_type TEXT,source_id INTEGER,status INTEGER,customer_visible INTEGER'
        ) as $table=>$columns) $this->pdo->exec('CREATE TABLE llx_'.$table.'('.$columns.')');
    }
    public function insert($table, $row) {
        $sql = 'INSERT INTO llx_'.$table.'('.implode(',', array_keys($row)).') VALUES('.implode(',', array_fill(0, count($row), '?')).')';
        $statement = $this->pdo->prepare($sql);
        $statement->execute(array_values($row));
    }
    public function charge($customer, $amount, $currency, $category = 'supplier', $extra = array()) {
        $this->insert('cpcc_project_charge', array_merge(array('entity'=>2,'fk_soc'=>$customer,'fk_project'=>0,'charge_date'=>'2026-11-01','category'=>$category,'label'=>'Synthetic commitment','supplier_label'=>'Internal fixture supplier','amount'=>$amount,'currency_code'=>$currency,'status'=>1,'customer_visible'=>1), $extra));
    }
    public function entry($customer, $amount, $currency, $direction, $extra = array()) {
        $this->insert('cpcc_financial_entry', array_merge(array('entity'=>2,'fk_soc'=>$customer,'entry_date'=>'2026-11-03 23:59:59','entry_type'=>'adjustment','direction'=>$direction,'description'=>'Synthetic entry','amount'=>$amount,'currency_code'=>$currency,'status'=>1,'customer_visible'=>1), $extra));
    }
    public function escape($value) { return str_replace("'", "''", $value); }
    public function query($sql) {
        if (preg_match("/^SHOW TABLES LIKE '([a-zA-Z0-9_]+)'$/", $sql, $match)) {
            $statement = $this->pdo->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name=?");
            $statement->execute(array($match[1]));
        } else {
            m121p_assert(strpos($sql, 'SELECT ') === 0, 'Rendering must execute reads only');
            $statement = $this->pdo->query($sql);
        }
        return new M121PortfolioRows($statement->fetchAll(PDO::FETCH_OBJ));
    }
    public function num_rows($result) { return count($result->rows); }
    public function fetch_object($result) { return array_shift($result->rows); }
    public function close() {}
}
class M121PortfolioLangs {
    public $loaded = false;
    private $values = array();
    private $language;
    private $escapeProbe;
    public function __construct($language, $escapeProbe) {
        $this->language = $language; $this->escapeProbe = $escapeProbe;
    }
    public function load($module) {
        m121p_assert($module === 'chinaplanetcustomercenter@chinaplanetcustomercenter', 'Native module language domain');
        $file = dirname(__DIR__, 2).'/custom/chinaplanetcustomercenter/langs/'.$this->language.'/chinaplanetcustomercenter.lang';
        foreach (file($file, FILE_IGNORE_NEW_LINES) as $line) {
            if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) continue;
            list($key, $value) = explode('=', $line, 2);
            $this->values[trim($key)] = trim($value);
        }
        $this->loaded = true;
    }
    public function trans($key) {
        m121p_assert(isset($this->values[$key]), 'Language file must define '.$key);
        $value = $this->values[$key];
        // Treat translations as untrusted text at the page output boundary.
        if ($this->escapeProbe) $value .= ' <img src=x onerror=alert(1)>';
        return $value;
    }
}
$scenario = isset($argv[1]) ? $argv[1] : 'mixed';
$langs = new M121PortfolioLangs($scenario === 'arabic' ? 'ar_SA' : 'en_US', $scenario === 'escaped-label');
$conf = (object) array('entity'=>2,'currency'=>'USD');
$user = new class {
    public $id = 1;
    public $socid = 0;
    public $admin = 1;
    public function hasRight($module, $right) { return false; }
};
if ($scenario === 'external') $user->socid = 7;
if ($scenario === 'no-right') $user->admin = 0;
$db = new M121PortfolioDb();
if (!in_array($scenario, array('empty','external','no-right'), true)) {
    // Deliberately shuffled insertion and case-insensitive name ties; raw amounts
    // would rank Beta/Tie30 first, but currency units cannot determine rank.
    foreach (array(30=>'Tie',22=>'Beta',77=>'A funded',11=>'Alpha',5=>'tie',88=>'Empty',66=>'Reversed',99=>'X<script>alert(1)</script> & "quoted"') as $id=>$name) {
        $db->insert('societe', array('rowid'=>$id,'entity'=>2,'client'=>1,'nom'=>$name));
    }
    $db->charge(11, 100, 'USD'); $db->charge(11, 200, 'CNY');
    $db->charge(11, 30, 'USD', 'service'); $db->charge(11, 40, 'RMB', 'service');
    $db->charge(11, 50, 'EGP'); $db->charge(22, 1000, 'RMB');
    $db->charge(5, 1, 'USD'); $db->charge(30, 10000, 'EGP');
    $db->charge(77, 20, 'USD'); $db->entry(77, 100, 'USD', 'C');
    $db->entry(66, 25, 'USD', 'D'); $db->entry(66, 25, 'USD', 'C');
    $db->entry(66, 999, 'USD', 'D', array('status'=>0));
    // Real SQL must omit future, private, wrong-entity and inactive commitments.
    $db->charge(11, 900000, 'USD', 'supplier', array('charge_date'=>'2026-11-04'));
    $db->charge(11, 800000, 'USD', 'supplier', array('entity'=>3));
    $db->charge(11, 700000, 'USD', 'supplier', array('customer_visible'=>0));
    $db->charge(11, 600000, 'USD', 'supplier', array('status'=>0));
    $db->insert('societe', array('rowid'=>100,'entity'=>3,'client'=>1,'nom'=>'Foreign entity'));
    $db->insert('societe', array('rowid'=>101,'entity'=>2,'client'=>0,'nom'=>'Not a customer'));
}
$root = dirname(__DIR__, 2);
$temp = sys_get_temp_dir().'/m121-portfolio-'.bin2hex(random_bytes(6));
mkdir($temp.'/admin', 0700, true); mkdir($temp.'/lib', 0700, true);
foreach (array('main','admin_shell','portal') as $stub) file_put_contents($temp.'/lib/'.$stub.'.lib.php', "<?php\n");
foreach (array('financial','ui') as $library) copy($root.'/custom/chinaplanetcustomercenter/lib/'.$library.'.lib.php', $temp.'/lib/'.$library.'.lib.php');
copy($root.'/custom/chinaplanetcustomercenter/admin/financial_portfolio.php', $temp.'/admin/financial_portfolio.php');
register_shutdown_function(function () use ($temp) {
    foreach (glob($temp.'/lib/*') as $file) unlink($file);
    unlink($temp.'/admin/financial_portfolio.php'); rmdir($temp.'/lib'); rmdir($temp.'/admin'); rmdir($temp);
});
ob_start();
try { require $temp.'/admin/financial_portfolio.php'; } catch (DomainException $exception) {
    ob_end_clean();
    m121p_assert(in_array($scenario, array('external','no-right'), true), 'Unexpected access denial');
    echo 'M121 portfolio '.$scenario.' denied before rendering'.PHP_EOL;
    exit(0);
}
$html = ob_get_clean();
m121p_assert(!in_array($scenario, array('external','no-right'), true), 'Unauthorized fixture must not render');
$dom = new DOMDocument(); $old = libxml_use_internal_errors(true);
$dom->loadHTML('<?xml encoding="utf-8" ?>'.$html, LIBXML_NONET); libxml_clear_errors(); libxml_use_internal_errors($old);
$xpath = new DOMXPath($dom);
function m121p_text($node) { return trim(preg_replace('/\s+/u', ' ', $node->textContent)); }
function m121p_kpi($xpath, $label) {
    $node = $xpath->query('//div[contains(concat(" ",normalize-space(@class)," ")," cpad-kpi ")][span="'.$label.'"]/b');
    m121p_assert($node->length === 1, 'KPI exists: '.$label);
    return m121p_text($node->item(0));
}
$text = m121p_text($dom);
if ($scenario==='historical') {
    m121p_assert(CustomerFundsService::$reads===0, 'Past diagnostic must not read current managed positions');
    m121p_assert(strpos($text,'Current managed funds are shown only for today')!==false, 'Past diagnostic explains missing current amounts');
    echo 'M121 portfolio historical excludes current positions (actual page guard)'.PHP_EOL; exit(0);
}
m121p_assert($langs->loaded, 'Portfolio must load its native module language domain');
if ($scenario === 'mixed') m121p_assert(m121p_kpi($xpath, 'Supplier Remaining') === 'EGP 10,050.00 · RMB 1,200.00 · USD 121.00', 'Supplier KPI must not mix currency units');
$walletLabel = $scenario === 'arabic' ? 'رصيد ائتمان الفواتير (تقييم بالدولار الأمريكي)' : 'Invoice-credit balance (USD valuation)';
$cutoffLabel = $scenario === 'arabic' ? 'تاريخ قطع الحركات' : 'Transaction cutoff';
if ($scenario === 'escaped-label') $walletLabel .= ' <img src=x onerror=alert(1)>';
m121p_assert(m121p_kpi($xpath, $walletLabel) !== '', 'Translated valuation label identifies invoice-credit balance');
m121p_assert(strpos($text, $cutoffLabel) !== false, 'Translated cutoff control remains visible');
if ($scenario === 'arabic') {
    m121p_assert(strpos($text, 'ليس رصيدًا تاريخيًا كاملًا') !== false && strpos($text, 'ولا الرصيد القابل للتحصيل اليوم') !== false, 'Arabic diagnostic notice states both balance limitations');
} else {
    m121p_assert(stripos($text, 'neither a complete historical balance') !== false && stripos($text, 'nor today') !== false && stripos($text, 'collectible balance') !== false, 'English diagnostic notice states both balance limitations');
}
if ($scenario === 'escaped-label') {
    m121p_assert($xpath->query('//img')->length === 0 && substr_count($text, '<img src=x onerror=alert(1)>') >= 3, 'All three translations are escaped without hiding their contents');
}
if ($scenario === 'empty') {
    m121p_assert(m121p_kpi($xpath, 'Supplier Remaining') === '—', 'No currencies produce no invented money');
    m121p_assert(m121p_kpi($xpath, 'Future Services') === '—', 'Empty service commitments');
    m121p_assert($xpath->query('//tr[contains(@class,"cpad-empty-row")]/td[@colspan="8"]')->length === 1, 'Empty customer matrix is intact');
    echo 'M121 portfolio empty rendered fixture passed'.PHP_EOL;
    exit(0);
}
m121p_assert(m121p_kpi($xpath, 'Supplier Remaining') === 'EGP 10,050.00 · RMB 1,200.00 · USD 121.00', 'Supplier KPI must retain USD/RMB/EGP amounts, no cross-currency sum');
m121p_assert(m121p_kpi($xpath, 'Future Services') === 'RMB 40.00 · USD 30.00', 'Services KPI retains currency labels');
m121p_assert(m121p_kpi($xpath, $langs->trans('CPCCFundsEstimateCustomers')) === '4', 'Any-currency shortfall flags customers once');
$rows = $xpath->query('//table[contains(@class,"cpad-customer-finance-table")]/tbody/tr');
$ids = array(); $warn = array(); $cellsById = array();
foreach ($rows as $row) {
    $link = $xpath->query('./td[@data-label="Customer"]//a', $row)->item(0);
    parse_str(parse_url($link->getAttribute('href'), PHP_URL_QUERY), $query);
    $id = (int) $query['socid']; $ids[] = $id; $cellsById[$id] = $row;
    if ($xpath->query('./td[@data-label="'.$langs->trans('CPCCFundsEstimate').'" and contains(concat(" ",normalize-space(@class)," ")," warn ")]', $row)->length) $warn[] = $id;
}
m121p_assert($ids === array(11,22,5,30,77,88,66,99), 'Sort by deficit flag then case-insensitive name then ID, never unlike-currency amounts');
m121p_assert($warn === array(11,22,5,30), 'Funded/empty/reversed fixtures do not receive deficit flags');
foreach (array('Obligations',$langs->trans('CPCCFundsEstimate')) as $label) {
    $cell = $xpath->query('./td[@data-label="'.$label.'"]', $cellsById[11])->item(0);
    m121p_assert(m121p_text($cell) === 'EGP 50.00 · RMB 240.00 · USD 130.00', $label.' must remain currency-qualified');
}
m121p_assert($xpath->query('//script')->length === 0, 'Customer name must not become executable markup');
m121p_assert(strpos($text, 'X<script>alert(1)</script> & "quoted"') !== false, 'Escaped customer name retains readable text');
m121p_assert(strpos($text, 'Internal fixture supplier') === false, 'Supplier identities remain absent from the portfolio');
m121p_assert(strpos($text, 'Foreign entity') === false && strpos($text, 'Not a customer') === false, 'Actual customer query preserves current scope');
foreach ($scenario === 'mixed' ? array('empty','external','no-right','arabic','escaped-label','historical') : array() as $child) {
    $process = proc_open(array(PHP_BINARY, __FILE__, $child), array(1=>array('pipe','w'),2=>array('pipe','w')), $pipes);
    m121p_assert(is_resource($process), 'Fixture subprocess starts');
    $out = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
    m121p_assert(proc_close($process) === 0, $child.' fixture failed: '.$out.$err);
}
echo 'M121 portfolio currency/rendering regression passed: mixed USD/RMB/EGP, stable ties, funded/empty/reversal, EN/AR language keys, escaped translations/output and existing access denials; SQLite only, rendered browser untested'.PHP_EOL;
