<?php
/** Synthetic read-model/rendering regression; no Dolibarr runtime or database needed. */
error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
define('MAIN_DB_PREFIX', 'llx_');
function m121_assert($condition, $message) {
    if (!$condition) throw new RuntimeException($message);
}
function getDolGlobalInt($key) { return 0; }
function getDolGlobalString($key) { return ''; }
function GETPOST($key, $type) { return $key === 'asof' ? '2026-11-03' : ''; }
function dol_escape_htmltag($text) { return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8'); }
function cpcc_portal_money($amount) { return number_format($amount, 2, '.', ','); }
function dol_buildpath($path, $absolute) { return $path; }
function cpcc_admin_shell_asset($path) { return $path; }
function cpcc_admin_shell_open(...$args) {}
function cpcc_admin_shell_close() {}
function llxHeader(...$args) {}
function llxFooter() {}
function accessforbidden(...$args) { throw new RuntimeException('Unexpected access denial'); }

class M121Result {
    public $rows;
    public function __construct($rows) { $this->rows = $rows; }
}
class M121FinanceDb {
    public $queries = array();
    public $projectMode = false;
    public function escape($text) { return addslashes($text); }
    public function num_rows($result) { return count($result->rows); }
    public function fetch_object($result) { return array_shift($result->rows); }
    public function close() {}
    public function query($sql) {
        $this->queries[] = $sql;
        if (strpos($sql, 'SHOW TABLES') === 0) return new M121Result(array((object) array('table'=>'exists')));
        if (strpos($sql, 'SELECT rowid, nom FROM llx_societe') === 0) {
            return new M121Result(array((object) array('rowid'=>7,'nom'=>'Alpha'), (object) array('rowid'=>8,'nom'=>'Beta')));
        }
        if (strpos($sql, 'FROM llx_cpcc_project_charge c') !== false && !$this->projectMode) {
            $fixtures = strpos($sql, 'c.fk_soc=7 ') !== false
                ? array(array('USD',100,'supplier'), array('RMB',200,'supplier'), array('USD',30,'service'), array('RMB',40,'service'))
                : array(array('RMB',1000,'supplier'));
            $rows = array();
            foreach ($fixtures as $index=>$fixture) {
                $rows[] = (object) array('rowid'=>$index+1,'fk_project'=>11,'charge_date'=>'2026-10-01','category'=>$fixture[2],
                    'label'=>'Synthetic','supplier_label'=>'Synthetic','amount'=>$fixture[1],'currency_code'=>$fixture[0],
                    'reference'=>'DEMO-M121','source_type'=>'manual','source_id'=>0,'project_ref'=>'DEMO', 'project_title'=>'Synthetic',
                    'paid'=>0,'billed_invoice_id'=>null,'billed_invoice_ref'=>null,'billed_invoice_status'=>null,'billed_invoice_type'=>null);
            }
            return new M121Result($rows);
        }
        if ($this->projectMode && strpos($sql, 'f.fk_statut=1 AND f.paye=0') !== false) {
            m121_assert(strpos($sql, 'f.entity=2 AND f.fk_soc=7') !== false, 'Invoice read must retain entity/customer scope');
            m121_assert(strpos($sql, "f.datef<='2026-11-03'") !== false, 'Invoice read must retain as-of filter');
            $rows = array();
            foreach (array(11=>75,12=>900) as $project=>$amount) {
                if (strpos($sql, 'AND f.fk_projet=') !== false && strpos($sql, 'AND f.fk_projet='.$project.' ORDER') === false) continue;
                $rows[] = (object) array('rowid'=>$project,'ref'=>'SYN-'.$project,'datef'=>'2026-10-01','date_lim_reglement'=>'2026-10-30',
                    'fk_projet'=>$project,'multicurrency_code'=>'USD','total_ttc'=>$amount,'multicurrency_total_ttc'=>$amount,
                    'paid_base'=>0,'paid_multi'=>0,'discount_base'=>0,'discount_multi'=>0,'project_ref'=>'DEMO','project_title'=>'Synthetic');
            }
            return new M121Result($rows);
        }
        // Fail if the read model gains an unreviewed query rather than hiding it.
        $known = array('FROM llx_cpcc_financial_entry WHERE', 'FROM llx_cpcc_project_charge c',
            'FROM llx_societe_remise_except r', 'FROM llx_paiement p', 'FROM llx_facture f');
        foreach ($known as $fragment) if (strpos($sql, $fragment) !== false) return new M121Result(array());
        throw new RuntimeException('Unexpected query: '.$sql);
    }
}

$root = dirname(__DIR__, 2);
$temp = sys_get_temp_dir().'/m121-finance-'.bin2hex(random_bytes(6));
mkdir($temp.'/admin', 0700, true);
mkdir($temp.'/lib', 0700, true);
foreach (array('main','admin_shell','portal') as $stub) file_put_contents($temp.'/lib/'.$stub.'.lib.php', "<?php\n");
copy($root.'/custom/chinaplanetcustomercenter/lib/financial.lib.php', $temp.'/lib/financial.lib.php');
copy($root.'/custom/chinaplanetcustomercenter/admin/financial_portfolio.php', $temp.'/admin/financial_portfolio.php');
register_shutdown_function(function () use ($temp) {
    foreach (glob($temp.'/lib/*') as $file) unlink($file);
    unlink($temp.'/admin/financial_portfolio.php');
    rmdir($temp.'/lib'); rmdir($temp.'/admin'); rmdir($temp);
});
$conf = (object) array('entity'=>2,'currency'=>'USD');
$user = (object) array('id'=>1,'socid'=>0,'admin'=>1);
$db = new M121FinanceDb();
ob_start();
require $temp.'/admin/financial_portfolio.php';
$html = ob_get_clean();
m121_assert(strpos($html, '<b>RMB 1,200.00 · USD 100.00</b><span>Supplier Remaining') !== false, 'Supplier KPI must keep each currency');
m121_assert(strpos($html, '<b>RMB 40.00 · USD 30.00</b><span>Future Services') !== false, 'Service KPI must keep each currency');
m121_assert(substr_count($html, 'RMB 240.00 · USD 130.00') === 2, 'Customer obligations and required funds must keep currency labels');
m121_assert(strpos($html, '370.00') === false, 'Customer currencies must never be summed into 370');
m121_assert(strpos($html, '>Alpha <') < strpos($html, '>Beta <'), 'Shortfall customers should sort by name without cross-currency amount ranking');
m121_assert(substr_count($html, 'class="warn"') === 2, 'Each customer with a per-currency shortfall must be flagged');

$db = new M121FinanceDb(); $db->projectMode = true;
$project = cpcc_financial_summary($db, 7, 'USD', 11, '2026-11-03');
m121_assert($project['USD']['unpaid_invoices'] === 75.0, 'Project summary must exclude another project invoice');
m121_assert($project['USD']['obligations'] === 75.0 && $project['USD']['required_funding'] === 75.0, 'Project totals must use the same filtered invoice set');
$all = cpcc_financial_summary($db, 7, 'USD', 0, '2026-11-03');
m121_assert($all['USD']['unpaid_invoices'] === 975.0, 'All-project summary must still include both invoices');
$empty = cpcc_financial_summary($db, 7, 'USD', 99, '2026-11-03');
m121_assert($empty === array(), 'Unknown project must not inherit customer-wide invoices');
echo "M121 financial currency/project regressions passed\n";
