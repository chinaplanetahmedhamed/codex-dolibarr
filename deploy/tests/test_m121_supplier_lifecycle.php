<?php
/** Actual generated lifecycle SELECTs on synthetic SQLite; no native runtime/private data. */
error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
define('MAIN_DB_PREFIX', 'llx_');
$conf = (object) array('entity'=>2);
$user = (object) array('id'=>1);
function getDolGlobalInt($key) { return 0; }
require_once dirname(__DIR__, 2).'/custom/chinaplanetcustomercenter/lib/operations.lib.php';
class M121SupplierRows {
    public $rows;
    public function __construct($rows) { $this->rows = $rows; }
}
class M121SupplierDb {
    public $pdo;
    public $queries = array();
    public $failNative = false;
    public $failLookup = false;
    public function __construct() {
        $this->pdo = new PDO('sqlite::memory:', null, null, array(PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION));
        $schema = array(
            'commande'=>'rowid INTEGER,entity INTEGER,fk_soc INTEGER,fk_projet INTEGER,fk_statut INTEGER',
            'projet'=>'rowid INTEGER,entity INTEGER,fk_soc INTEGER,ref TEXT,title TEXT',
            'facture'=>'rowid INTEGER,ref TEXT,ref_client TEXT,entity INTEGER,fk_soc INTEGER,fk_projet INTEGER,datef TEXT,date_lim_reglement TEXT,multicurrency_code TEXT,total_ttc REAL,multicurrency_total_ttc REAL,fk_statut INTEGER,paye INTEGER,type INTEGER',
            'paiement'=>'rowid INTEGER,entity INTEGER,datep DATETIME,statut INTEGER,ref TEXT,num_paiement TEXT',
            'paiement_facture'=>'fk_paiement INTEGER,fk_facture INTEGER,amount REAL,multicurrency_amount REAL,multicurrency_code TEXT',
            'societe_remise_except'=>'rowid INTEGER,entity INTEGER,fk_soc INTEGER,fk_facture INTEGER,fk_facture_line INTEGER,fk_facture_source INTEGER,datec DATETIME,amount_ttc REAL,multicurrency_amount_ttc REAL,multicurrency_code TEXT',
            'facturedet'=>'rowid INTEGER,fk_facture INTEGER',
            'cpcc_project_charge'=>'rowid INTEGER PRIMARY KEY,entity INTEGER,fk_soc INTEGER,fk_project INTEGER,charge_date DATETIME,category TEXT,label TEXT,supplier_label TEXT,amount REAL,currency_code TEXT,reference TEXT,source_type TEXT,source_id INTEGER,status INTEGER,customer_visible INTEGER',
            'cpcc_financial_entry'=>'rowid INTEGER PRIMARY KEY,entity INTEGER,fk_soc INTEGER,fk_charge INTEGER,fk_project INTEGER,entry_date DATETIME,entry_type TEXT,direction TEXT,description TEXT,amount REAL,currency_code TEXT,reference TEXT,bank_label TEXT,allocated_amount REAL,allocated_currency TEXT,exchange_rate REAL,transfer_group TEXT,proof_ecm_id INTEGER,source_type TEXT,source_id INTEGER,status INTEGER,customer_visible INTEGER',
            'cpo_supplier_action'=>'rowid INTEGER,entity INTEGER,fk_operation INTEGER,fk_soc_customer INTEGER,fk_project INTEGER,fk_supplier INTEGER,fk_supplier_invoice INTEGER,fk_supplier_order INTEGER,action_type TEXT,status TEXT',
            'commande_fournisseur'=>'rowid INTEGER,entity INTEGER,fk_soc INTEGER,fk_projet INTEGER,fk_statut INTEGER',
            'facture_fourn'=>'rowid INTEGER,entity INTEGER,fk_soc INTEGER,fk_projet INTEGER,type INTEGER,fk_statut INTEGER,paye INTEGER,total_ttc REAL,multicurrency_code TEXT,multicurrency_total_ttc REAL',
            'paiementfourn'=>'rowid INTEGER,entity INTEGER',
            'paiementfourn_facturefourn'=>'fk_paiementfourn INTEGER,fk_facturefourn INTEGER,amount REAL,multicurrency_amount REAL',
            'chinaplanetlogistics'=>'entity INTEGER,fk_soc INTEGER,fk_project INTEGER,fk_statut INTEGER',
            'element_element'=>'rowid INTEGER,sourcetype TEXT,targettype TEXT,fk_source INTEGER,fk_target INTEGER'
        );
        foreach ($schema as $table=>$columns) $this->pdo->exec('CREATE TABLE llx_'.$table.'('.$columns.')');
    }
    public function insert($table, $row) {
        $query = $this->pdo->prepare('INSERT INTO llx_'.$table.'('.implode(',',array_keys($row)).') VALUES('.implode(',',array_fill(0,count($row),'?')).')');
        $query->execute(array_values($row));
    }
    public function escape($value) { return str_replace("'", "''", $value); }
    public function query($sql) {
        $this->queries[] = $sql;
        // Inject read failure, never canned financial result rows.
        if (($this->failNative && strpos($sql,'FROM llx_cpo_supplier_action a') !== false)
            || ($this->failLookup && strpos($sql,"SHOW TABLES LIKE 'llx_cpo_supplier_action'") === 0)) return false;
        if (preg_match("/^SHOW TABLES LIKE '([^']+)'$/",$sql,$match)) {
            $query = $this->pdo->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name=?");
            $query->execute(array($match[1]));
        } else {
            if (strpos($sql,'SELECT ') !== 0) throw new RuntimeException('Lifecycle must only read');
            try { $query = $this->pdo->query($sql); } catch (PDOException $error) { return false; }
        }
        return new M121SupplierRows($query->fetchAll(PDO::FETCH_OBJ));
    }
    public function num_rows($result) { return count($result->rows); }
    public function fetch_object($result) { return array_shift($result->rows); }
}

function m121s_fixture($paid = 100, $legacyPaid = false) {
    $db = new M121SupplierDb();
    $db->insert('cpcc_project_charge', array('rowid'=>1,'entity'=>2,'fk_soc'=>7,'fk_project'=>11,'charge_date'=>'2026-01-01','category'=>'supplier','label'=>'Synthetic commitment','supplier_label'=>'PRIVATE FIXTURE SUPPLIER','amount'=>100,'currency_code'=>'USD','status'=>1,'customer_visible'=>1));
    if ($legacyPaid) $db->insert('cpcc_financial_entry', array('rowid'=>1,'entity'=>2,'fk_soc'=>7,'fk_project'=>11,'fk_charge'=>1,'entry_date'=>'2026-01-02 12:00:00','entry_type'=>'supplier_payment','direction'=>'D','description'=>'Synthetic debit','amount'=>100,'currency_code'=>'USD','allocated_amount'=>100,'allocated_currency'=>'USD','status'=>1,'customer_visible'=>1));
    $db->insert('commande_fournisseur', array('rowid'=>77,'entity'=>2,'fk_soc'=>70,'fk_projet'=>11,'fk_statut'=>1));
    $db->insert('facture_fourn', array('rowid'=>100,'entity'=>2,'fk_soc'=>70,'fk_projet'=>11,'type'=>0,'fk_statut'=>1,'paye'=>0,'total_ttc'=>100,'multicurrency_code'=>'','multicurrency_total_ttc'=>0));
    $db->insert('cpo_supplier_action', array('rowid'=>100,'entity'=>2,'fk_operation'=>101,'fk_soc_customer'=>7,'fk_project'=>11,'fk_supplier'=>70,'fk_supplier_invoice'=>100,'fk_supplier_order'=>77,'action_type'=>'supplier_invoice','status'=>'confirmed'));
    $db->insert('element_element', array('rowid'=>1,'sourcetype'=>'order_supplier','targettype'=>'invoice_supplier','fk_source'=>77,'fk_target'=>100));
    if ($paid) $db->insert('paiementfourn_facturefourn', array('fk_paiementfourn'=>200,'fk_facturefourn'=>100,'amount'=>$paid,'multicurrency_amount'=>$paid));
    return $db;
}
$checks = 0;
function m121s_check($db, $expected, $label, $deposit = null) {
    global $checks;
    $result = cpcc_project_lifecycle($db, 7, 11, 'USD');
    $flags = array();
    foreach ($result as $item) {
        if (array_keys($item) !== array('key','label','en','done','rule') || !is_bool($item['done'])) throw new RuntimeException($label.': lifecycle output must remain boolean-only with presentation labels');
        $flags[$item['key']] = $item['done'];
    }
    if ($flags['supplier_paid'] !== $expected) throw new RuntimeException($label.': expected supplier_paid='.var_export($expected,true));
    if ($deposit !== null && $flags['deposit'] !== $deposit) throw new RuntimeException($label.': deposit state differs');
    if (strpos(json_encode($result), 'PRIVATE FIXTURE SUPPLIER') !== false || strpos(json_encode($result), 'invoice_total') !== false) throw new RuntimeException($label.': private supplier evidence leaked');
    $checks++;
}
$db = m121s_fixture(); m121s_check($db,true,'Matching native identity/order/link',true);
$db = m121s_fixture(25); m121s_check($db,false,'Partial native payment',true);
$db = m121s_fixture(0); m121s_check($db,false,'Unpaid native invoice',false);
foreach (array(
    'Invoice project drift'=>"UPDATE llx_facture_fourn SET fk_projet=22",
    'Invoice supplier drift'=>"UPDATE llx_facture_fourn SET fk_soc=71",
    'Nonstandard invoice type'=>"UPDATE llx_facture_fourn SET type=2",
    'Draft invoice after confirmed marker'=>"UPDATE llx_facture_fourn SET fk_statut=0",
    'Missing invoice'=>"DELETE FROM llx_facture_fourn",
    'Foreign entity native invoice'=>"UPDATE llx_facture_fourn SET entity=3",
    'Missing marker order identity'=>"UPDATE llx_cpo_supplier_action SET fk_supplier_order=NULL",
    'Missing native order'=>"DELETE FROM llx_commande_fournisseur",
    'Foreign entity native order'=>"UPDATE llx_commande_fournisseur SET entity=3",
    'Order supplier drift'=>"UPDATE llx_commande_fournisseur SET fk_soc=71",
    'Order project drift'=>"UPDATE llx_commande_fournisseur SET fk_projet=22",
    'Missing native invoice/order link'=>"DELETE FROM llx_element_element",
    'Wrong native source order link'=>"UPDATE llx_element_element SET fk_source=78",
    'Wrong native link type'=>"UPDATE llx_element_element SET sourcetype='commande'"
) as $label=>$sql) {
    $db=m121s_fixture(100,true); $db->pdo->exec($sql);
    m121s_check($db,false,$label.' must not fall back to fully-paid manual entries',false);
}
$db=m121s_fixture(100,true);
$db->insert('cpo_supplier_action', array('rowid'=>101,'entity'=>2,'fk_operation'=>102,'fk_soc_customer'=>7,'fk_project'=>11,'fk_supplier'=>70,'fk_supplier_invoice'=>999,'fk_supplier_order'=>77,'action_type'=>'supplier_invoice','status'=>'confirmed'));
m121s_check($db,false,'Mixed valid and invalid guided evidence',true);
$db=m121s_fixture(100,true);$db->failNative=true;m121s_check($db,false,'Failed native query must not report paid',false);
$db=m121s_fixture(100,true);$db->failLookup=true;m121s_check($db,false,'Unknown native table metadata must not report paid',false);
$db=m121s_fixture(100,true);$db->pdo->exec('DROP TABLE llx_commande_fournisseur');m121s_check($db,false,'Missing native dependency schema must not report paid or install schema',false);
foreach (array(true,false) as $legacy) {
    $db=m121s_fixture(100,$legacy);$db->pdo->exec('DELETE FROM llx_cpo_supplier_action');
    m121s_check($db,$legacy,'No guided markers preserves legacy fallback',$legacy);
    $db->pdo->exec('DROP TABLE llx_cpo_supplier_action');
    m121s_check($db,$legacy,'Absent optional guided schema preserves legacy fallback',$legacy);
}
$db=m121s_fixture(100,true);$db->pdo->exec('UPDATE llx_cpo_supplier_action SET entity=3');m121s_check($db,true,'Foreign-entity marker cannot affect legacy evidence',true);
$db=m121s_fixture(100,true);$db->pdo->exec('UPDATE llx_cpo_supplier_action SET fk_soc_customer=8');m121s_check($db,true,'Another customer marker cannot affect current customer legacy evidence',true);
$db=m121s_fixture(100,true);$db->pdo->exec("UPDATE llx_cpo_supplier_action SET status='preparing'");m121s_check($db,true,'Unconfirmed marker is not completed guided evidence',true);
$db=m121s_fixture(0);$db->pdo->exec("UPDATE llx_commande_fournisseur SET fk_statut=0");m121s_check($db,false,'Historical order status is not a mutation admission gate',false);
$db=m121s_fixture();$db->pdo->exec("UPDATE llx_commande_fournisseur SET fk_statut=0");m121s_check($db,true,'Historical linked order remains identity evidence without requiring active/latest status',true);
$db=m121s_fixture();$db->pdo->exec("UPDATE llx_facture_fourn SET multicurrency_code='CNY',total_ttc=100,multicurrency_total_ttc=700");$db->pdo->exec('UPDATE llx_paiementfourn_facturefourn SET amount=100,multicurrency_amount=350');m121s_check($db,false,'Original CNY partial amount cannot use base paid amount',true);
$db->pdo->exec('UPDATE llx_paiementfourn_facturefourn SET multicurrency_amount=700');m121s_check($db,true,'Original CNY fully paid',true);
$db->pdo->exec('DELETE FROM llx_paiementfourn_facturefourn');m121s_check($db,false,'Removed native payment reopens paid flag',false);
echo 'M121 supplier lifecycle PASS ('.$checks.' actual-query cases; synthetic SQLite only; native command/auth/history acceptance untested)'.PHP_EOL;
