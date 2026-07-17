<?php
/**
 * Idempotent local scenario seed for the ChinaPlanet agency workflow.
 *
 * Creates three China Gate projects that demonstrate RMB, USD, and mixed
 * supplier settlement, service-only Vision invoices, wallet allocations,
 * logistics records, and customer-facing export documents.
 */

define('NOLOGIN', 1);
define('NOREQUIREMENU', 1);
define('NOREQUIREHTML', 1);
define('NOREQUIREAJAX', 1);
define('NOCSRFCHECK', 1);

$_SERVER['DOCUMENT_ROOT'] = '/Users/mac/Documents/dev/dolibarr/dolibarr-Dev/dolibarr-23.0.3/htdocs';
$_SERVER['SERVER_NAME'] = '127.0.0.1';
$_SERVER['SERVER_PORT'] = '8088';

require $_SERVER['DOCUMENT_ROOT'].'/master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';
require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/paiement/class/paiement.class.php';
require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.commande.class.php';
require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/fourn/class/paiementfourn.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/chinaplanetlogistics/class/chinaplanetlogistics.class.php';

global $db, $user, $conf;

function ag_fail($message, $object = null)
{
    if ($object && !empty($object->error)) $message .= ': '.$object->error;
    throw new RuntimeException($message);
}

function ag_query($sql)
{
    global $db;
    $res = $db->query($sql);
    if (!$res) ag_fail($db->lasterror().' | '.$sql);
    return $res;
}

function ag_rowid($table, $where)
{
    global $db;
    $res = ag_query('SELECT rowid FROM '.MAIN_DB_PREFIX.$table.' WHERE '.$where.' ORDER BY rowid DESC LIMIT 1');
    $obj = $db->fetch_object($res);
    return $obj ? (int) $obj->rowid : 0;
}

function ag_sql_value($value)
{
    global $db;
    if ($value === null) return 'NULL';
    return "'".$db->escape((string) $value)."'";
}

function ag_install_schema()
{
    $files = array(
        DOL_DOCUMENT_ROOT.'/custom/chinaplanetcustomercenter/sql/llx_cpcc_project_charge.sql',
        DOL_DOCUMENT_ROOT.'/custom/chinaplanetcustomercenter/sql/llx_cpcc_financial_entry.sql',
        DOL_DOCUMENT_ROOT.'/custom/chinaplanetcustomercenter/sql/llx_cpcc_export_document.sql',
        DOL_DOCUMENT_ROOT.'/custom/chinaplanetcustomercenter/sql/llx_cpcc_export_document_line.sql',
    );
    foreach ($files as $file) {
        $sql = str_replace('llx_', MAIN_DB_PREFIX, file_get_contents($file));
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) ag_query($statement);
    }
}

function ag_currency_id($code)
{
    return ag_rowid('multicurrency', "code='".addslashes($code)."'");
}

function ag_apply_currency($object, $currency, $tx)
{
    $object->multicurrency_code = $currency;
    $object->multicurrency_tx = (float) $tx;
}

function ag_repair_document_currency($table, $detailTable, $detailForeignKey, $id, $currency, $tx, $unitPriceColumn = 'subprice', $taxTotalColumn = 'total_tva')
{
    global $db;
    $currencyId = ag_currency_id($currency);
    if (!$currencyId) ag_fail('Currency '.$currency.' is not configured');
    ag_query('UPDATE '.MAIN_DB_PREFIX.$table.' SET fk_multicurrency='.(int) $currencyId.",multicurrency_code='".$db->escape($currency)."',multicurrency_tx=".(float) $tx.',multicurrency_total_ht=total_ht*'.(float) $tx.',multicurrency_total_tva=total_tva*'.(float) $tx.',multicurrency_total_ttc=total_ttc*'.(float) $tx.' WHERE rowid='.(int) $id);
    ag_query('UPDATE '.MAIN_DB_PREFIX.$detailTable.' SET fk_multicurrency='.(int) $currencyId.",multicurrency_code='".$db->escape($currency)."',multicurrency_subprice=".$unitPriceColumn.'*'.(float) $tx.',multicurrency_total_ht=total_ht*'.(float) $tx.',multicurrency_total_tva='.$taxTotalColumn.'*'.(float) $tx.',multicurrency_total_ttc=total_ttc*'.(float) $tx.' WHERE '.$detailForeignKey.'='.(int) $id);
}

function ag_repair_payment_currency($invoiceId, $tx, $supplier = false)
{
    if ($supplier) {
        ag_query('UPDATE '.MAIN_DB_PREFIX.'paiementfourn_facturefourn SET multicurrency_amount=amount*'.(float) $tx.' WHERE fk_facturefourn='.(int) $invoiceId);
        ag_query('UPDATE '.MAIN_DB_PREFIX.'paiementfourn p INNER JOIN '.MAIN_DB_PREFIX.'paiementfourn_facturefourn pf ON pf.fk_paiementfourn=p.rowid SET p.multicurrency_amount=p.amount*'.(float) $tx.' WHERE pf.fk_facturefourn='.(int) $invoiceId);
    } else {
        ag_query('UPDATE '.MAIN_DB_PREFIX.'paiement_facture SET multicurrency_amount=amount*'.(float) $tx.' WHERE fk_facture='.(int) $invoiceId);
        ag_query('UPDATE '.MAIN_DB_PREFIX.'paiement p INNER JOIN '.MAIN_DB_PREFIX.'paiement_facture pf ON pf.fk_paiement=p.rowid SET p.multicurrency_amount=p.amount*'.(float) $tx.' WHERE pf.fk_facture='.(int) $invoiceId);
    }
}

function ag_force_foreign_totals($table, $detailTable, $detailForeignKey, $id, array $amounts)
{
    global $db;
    $total = array_sum($amounts);
    ag_query('UPDATE '.MAIN_DB_PREFIX.$table.' SET multicurrency_total_ht='.(float) $total.',multicurrency_total_tva=0,multicurrency_total_ttc='.(float) $total.' WHERE rowid='.(int) $id);
    $res = ag_query('SELECT rowid FROM '.MAIN_DB_PREFIX.$detailTable.' WHERE '.$detailForeignKey.'='.(int) $id.' ORDER BY rang,rowid');
    $index = 0;
    while ($obj = $db->fetch_object($res)) {
        if (!array_key_exists($index, $amounts)) break;
        $amount = (float) $amounts[$index++];
        ag_query('UPDATE '.MAIN_DB_PREFIX.$detailTable.' SET multicurrency_subprice='.$amount.',multicurrency_total_ht='.$amount.',multicurrency_total_tva=0,multicurrency_total_ttc='.$amount.' WHERE rowid='.(int) $obj->rowid);
    }
}

function ag_force_payment_allocation($invoiceId, $amount, $supplier = false)
{
    if ($amount <= 0) return;
    if ($supplier) {
        ag_query('UPDATE '.MAIN_DB_PREFIX.'paiementfourn_facturefourn SET multicurrency_amount='.(float) $amount.' WHERE fk_facturefourn='.(int) $invoiceId);
        ag_query('UPDATE '.MAIN_DB_PREFIX.'paiementfourn p INNER JOIN '.MAIN_DB_PREFIX.'paiementfourn_facturefourn pf ON pf.fk_paiementfourn=p.rowid SET p.multicurrency_amount='.(float) $amount.' WHERE pf.fk_facturefourn='.(int) $invoiceId);
    } else {
        ag_query('UPDATE '.MAIN_DB_PREFIX.'paiement_facture SET multicurrency_amount='.(float) $amount.' WHERE fk_facture='.(int) $invoiceId);
        ag_query('UPDATE '.MAIN_DB_PREFIX.'paiement p INNER JOIN '.MAIN_DB_PREFIX.'paiement_facture pf ON pf.fk_paiement=p.rowid SET p.multicurrency_amount='.(float) $amount.' WHERE pf.fk_facture='.(int) $invoiceId);
    }
}

function ag_find_admin()
{
    global $db, $user;
    $res = ag_query('SELECT rowid FROM '.MAIN_DB_PREFIX.'user WHERE admin=1 AND statut=1 ORDER BY rowid LIMIT 1');
    $obj = $db->fetch_object($res);
    if (!$obj || $user->fetch((int) $obj->rowid) <= 0) ag_fail('Active admin user not found');
    $user->getrights();
}

function ag_ensure_supplier($name)
{
    global $db, $user;
    $id = ag_rowid('societe', "nom='".$db->escape($name)."'");
    if ($id) return $id;
    $soc = new Societe($db);
    $soc->name = $name;
    $soc->nom = $name;
    $soc->client = 0;
    $soc->fournisseur = 1;
    $soc->status = 1;
    $soc->code_fournisseur = 'auto';
    $soc->address = 'Local training data';
    $soc->town = 'Guangdong';
    $soc->email = strtolower(preg_replace('/[^a-z0-9]+/i', '.', $name)).'@training.invalid';
    $soc->multicurrency_code = 'CNY';
    if ($soc->create($user) <= 0) ag_fail('Cannot create supplier '.$name, $soc);
    return (int) $soc->id;
}

function ag_ensure_project(array $s)
{
    global $db, $user;
    $id = ag_rowid('projet', "ref='".$db->escape($s['project_ref'])."'");
    if ($id) return $id;
    $p = new Project($db);
    $p->ref = $s['project_ref'];
    $p->title = $s['title'];
    $p->socid = $s['customer_id'];
    $p->date_start = strtotime($s['date']);
    $p->date_end = strtotime($s['date'].' +120 days');
    $p->description = 'ChinaPlanet agency workflow training scenario. Customer funds are held and managed; only Vision services are revenue.';
    $p->public = 0;
    $p->statut = 1;
    if ($p->create($user) <= 0) ag_fail('Cannot create project '.$s['project_ref'], $p);
    if (method_exists($p, 'setValid')) $p->setValid($user);
    return (int) $p->id;
}

function ag_ensure_customer_order(array $s)
{
    global $db, $user;
    $marker = 'CPCC-AGENCY-ORDER-'.$s['code'];
    $id = ag_rowid('commande', "fk_soc=".(int) $s['customer_id']." AND note_private LIKE '%".$db->escape($marker)."%'");
    if ($id) return $id;
    $o = new Commande($db);
    $o->socid = $s['customer_id'];
    $o->date_commande = strtotime($s['date']);
    $o->delivery_date = strtotime($s['date'].' +75 days');
    $o->source = 0;
    $o->note_public = 'ChinaPlanet management and logistics services for '.$s['title'].'. Product funds are managed separately in the customer wallet.';
    $o->note_private = $marker;
    $o->linked_objects = array('project' => $s['project_id']);
    ag_apply_currency($o, $s['service_currency'], $s['service_tx']);
    if ($o->create($user) <= 0) ag_fail('Cannot create customer order', $o);
    foreach ($s['services'] as $line) {
        $base = $line['amount'] / $s['service_tx'];
        if ($o->addline($line['label'], $base, 1, 0, 0, 0, 0, 0, 0, 0, 'HT', 0, '', '', 1, -1, 0, 0, null, 0, $line['label'], array(), null, '', 0, $line['amount']) <= 0) ag_fail('Cannot add order service line', $o);
    }
    $o->fetch($o->id);
    if ($o->valid($user) < 0) ag_fail('Cannot validate customer order', $o);
    ag_query('UPDATE '.MAIN_DB_PREFIX.'commande SET fk_projet='.(int) $s['project_id'].' WHERE rowid='.(int) $o->id);
    return (int) $o->id;
}

function ag_ensure_customer_invoice(array $s, $orderId)
{
    global $db, $user;
    $marker = 'CPCC-AGENCY-INVOICE-'.$s['code'];
    $id = ag_rowid('facture', "fk_soc=".(int) $s['customer_id']." AND note_private LIKE '%".$db->escape($marker)."%'");
    if ($id) return $id;
    $o = new Facture($db);
    $o->socid = $s['customer_id'];
    $o->type = Facture::TYPE_STANDARD;
    $o->date = strtotime($s['invoice_date']);
    $o->date_lim_reglement = strtotime($s['invoice_date'].' +15 days');
    $o->note_public = 'Vision Trading invoice for ChinaPlanet services only. Supplier product funds remain customer-managed funds.';
    $o->note_private = $marker;
    $o->linked_objects = array('commande' => $orderId, 'project' => $s['project_id']);
    ag_apply_currency($o, $s['service_currency'], $s['service_tx']);
    if ($o->create($user) <= 0) ag_fail('Cannot create customer invoice', $o);
    foreach ($s['services'] as $line) {
        $base = $line['amount'] / $s['service_tx'];
        if ($o->addline($line['label'], $base, 1, 0, 0, 0, 0, 0, '', '', 0, 0, 0, 'HT', 0, 1, -1, 0, 0, array(), null, 0, $line['amount']) <= 0) ag_fail('Cannot add invoice service line', $o);
    }
    $o->fetch($o->id);
    if ($o->validate($user) < 0) ag_fail('Cannot validate customer invoice', $o);
    ag_query('UPDATE '.MAIN_DB_PREFIX.'facture SET fk_projet='.(int) $s['project_id'].' WHERE rowid='.(int) $o->id);
    return (int) $o->id;
}

function ag_payment_mode()
{
    global $db;
    $res = ag_query("SELECT id FROM ".MAIN_DB_PREFIX."c_paiement WHERE code='VIR' AND active=1 LIMIT 1");
    $obj = $db->fetch_object($res);
    if ($obj) return (int) $obj->id;
    $res = ag_query('SELECT id FROM '.MAIN_DB_PREFIX.'c_paiement WHERE active=1 ORDER BY id LIMIT 1');
    $obj = $db->fetch_object($res);
    if (!$obj) ag_fail('No payment mode found');
    return (int) $obj->id;
}

function ag_ensure_invoice_allocation(array $s, $invoiceId, $modeId)
{
    global $db, $user;
    if ($s['invoice_allocation'] <= 0) return 0;
    $marker = 'CPCC-ALLOC-'.$s['code'];
    $id = ag_rowid('paiement', "num_paiement='".$db->escape($marker)."'");
    if ($id) return $id;
    $invoice = new Facture($db);
    $invoice->fetch($invoiceId);
    $foreign = (float) $s['invoice_allocation'];
    $base = round($foreign / $s['service_tx'], 2);
    $p = new Paiement($db);
    $p->datepaye = strtotime($s['invoice_date'].' +2 days');
    $p->amounts = array($invoiceId => $base);
    $p->multicurrency_amounts = array($invoiceId => $foreign);
    $p->multicurrency_code = array($invoiceId => $s['service_currency']);
    $p->multicurrency_tx = array($invoiceId => $s['service_tx']);
    $p->paiementid = $modeId;
    $p->num_payment = $marker;
    $p->note_private = 'Wallet allocation only; not a new bank receipt.';
    $id = $p->create($user, $foreign + 0.001 >= (float) $invoice->multicurrency_total_ttc ? 1 : 0);
    if ($id <= 0) ag_fail('Cannot allocate wallet funds to invoice', $p);
    return (int) $id;
}

function ag_ensure_supplier_order(array $s)
{
    global $db, $user;
    $marker = 'CPCC-AGENCY-SPO-'.$s['code'];
    $id = ag_rowid('commande_fournisseur', "fk_soc=".(int) $s['supplier_id']." AND note_private LIKE '%".$db->escape($marker)."%'");
    if ($id) return $id;
    $o = new CommandeFournisseur($db);
    $o->socid = $s['supplier_id'];
    $o->date_commande = strtotime($s['date']);
    $o->note_private = $marker.' Internal supplier purchase; customer portal exposes only approved financial summary.';
    $o->linked_objects = array('project' => $s['project_id']);
    ag_apply_currency($o, $s['supplier_currency'], $s['supplier_tx']);
    if ($o->create($user) <= 0) ag_fail('Cannot create supplier order', $o);
    $base = $s['supplier_total'] / $s['supplier_tx'];
    if ($o->addline($s['product_label'], $base, 1, 0, 0, 0, 0, 0, '', 0, 'HT', 0, 0, 0, 0, '', null, $s['supplier_total']) <= 0) ag_fail('Cannot add supplier order line', $o);
    $o->fetch($o->id);
    if ($o->valid($user) < 0) ag_fail('Cannot validate supplier order', $o);
    $o->fetch($o->id);
    if ($o->approve($user) < 0) ag_fail('Cannot approve supplier order', $o);
    ag_query('UPDATE '.MAIN_DB_PREFIX.'commande_fournisseur SET fk_projet='.(int) $s['project_id'].' WHERE rowid='.(int) $o->id);
    return (int) $o->id;
}

function ag_ensure_supplier_invoice(array $s, $supplierOrderId)
{
    global $db, $user;
    $ref = 'CPCC-'.$s['code'].'-SUP';
    $id = ag_rowid('facture_fourn', "ref_supplier='".$db->escape($ref)."'");
    if ($id) return $id;
    $o = new FactureFournisseur($db);
    $o->ref_supplier = $ref;
    $o->socid = $s['supplier_id'];
    $o->date = strtotime($s['invoice_date']);
    $o->date_echeance = strtotime($s['invoice_date'].' +30 days');
    $o->note_private = 'CPCC agency supplier invoice '.$s['code'].'; internal cost.';
    $o->linked_objects = array('order_supplier' => $supplierOrderId, 'project' => $s['project_id']);
    ag_apply_currency($o, $s['supplier_currency'], $s['supplier_tx']);
    if ($o->create($user) <= 0) ag_fail('Cannot create supplier invoice', $o);
    $base = $s['supplier_total'] / $s['supplier_tx'];
    if ($o->addline($s['product_label'], $base, 0, 0, 0, 1, 0, 0, 0, 0, 0, 0, 'HT', 0, -1, 0, array(), null, 0, $s['supplier_total'], $ref) <= 0) ag_fail('Cannot add supplier invoice line', $o);
    $o->fetch($o->id);
    if ($o->validate($user) < 0) ag_fail('Cannot validate supplier invoice', $o);
    ag_query('UPDATE '.MAIN_DB_PREFIX.'facture_fourn SET fk_projet='.(int) $s['project_id'].' WHERE rowid='.(int) $o->id);
    return (int) $o->id;
}

function ag_ensure_supplier_payment(array $s, $invoiceId, $modeId)
{
    global $db, $user;
    if ($s['supplier_paid_allocated'] <= 0) return 0;
    $marker = 'CPCC-SUP-'.$s['code'];
    $id = ag_rowid('paiementfourn', "num_paiement='".$db->escape($marker)."'");
    if ($id) return $id;
    $foreign = $s['supplier_paid_allocated'];
    $base = round($foreign / $s['supplier_tx'], 2);
    $p = new PaiementFourn($db);
    $p->datepaye = strtotime($s['invoice_date'].' +3 days');
    $p->amounts = array($invoiceId => $base);
    $p->multicurrency_amounts = array($invoiceId => $foreign);
    $p->multicurrency_code = array($invoiceId => $s['supplier_currency']);
    $p->multicurrency_tx = array($invoiceId => $s['supplier_tx']);
    $p->paiementid = $modeId;
    $p->num_payment = $marker;
    $p->note_private = 'Supplier allocation. Actual source currencies are detailed in the CPCC wallet ledger.';
    $id = $p->create($user, $foreign + 0.001 >= $s['supplier_total'] ? 1 : 0);
    if ($id <= 0) ag_fail('Cannot create supplier payment', $p);
    return (int) $id;
}

function ag_insert_charge(array $s, $category, $label, $amount, $sourceType, $sourceId, $supplierId = null)
{
    global $db, $user, $conf;
    $reference = 'CPCC-'.$s['code'].'-'.strtoupper($category).'-'.substr(md5($label), 0, 6);
    $id = ag_rowid('cpcc_project_charge', "reference='".$db->escape($reference)."'");
    if ($id) return $id;
    $sql = 'INSERT INTO '.MAIN_DB_PREFIX.'cpcc_project_charge(entity,fk_soc,fk_project,fk_supplier,charge_date,category,label,supplier_label,amount,currency_code,source_type,source_id,reference,customer_visible,status,fk_user_creat,date_creation) VALUES ('
        .(int) $conf->entity.','.(int) $s['customer_id'].','.(int) $s['project_id'].','.($supplierId ? (int) $supplierId : 'NULL').','
        .ag_sql_value($s['invoice_date']).','.ag_sql_value($category).','.ag_sql_value($label).','
        .($supplierId ? ag_sql_value($s['supplier_name']) : 'NULL').','.(float) $amount.','.ag_sql_value($category === 'product' ? $s['supplier_currency'] : $s['service_currency']).','
        .ag_sql_value($sourceType).','.(int) $sourceId.','.ag_sql_value($reference).',1,1,'.(int) $user->id.','.ag_sql_value(date('Y-m-d H:i:s')).')';
    ag_query($sql);
    return (int) $db->last_insert_id(MAIN_DB_PREFIX.'cpcc_project_charge');
}

function ag_insert_entry(array $s, $type, $direction, $description, $amount, $currency, $reference, $projectId, $chargeId = null, $allocatedAmount = null, $allocatedCurrency = null, $sourceType = null, $sourceId = null)
{
    global $db, $user, $conf;
    $id = ag_rowid('cpcc_financial_entry', "reference='".$db->escape($reference)."'");
    if ($id) return $id;
    $rate = ($allocatedAmount !== null && (float) $amount != 0) ? ((float) $allocatedAmount / (float) $amount) : null;
    $sql = 'INSERT INTO '.MAIN_DB_PREFIX.'cpcc_financial_entry(entity,fk_soc,fk_project,fk_charge,entry_date,entry_type,direction,description,amount,currency_code,allocated_amount,allocated_currency,exchange_rate,reference,bank_label,source_type,source_id,customer_visible,status,note_private,fk_user_creat,date_creation) VALUES ('
        .(int) $conf->entity.','.(int) $s['customer_id'].','.(int) $projectId.','.($chargeId ? (int) $chargeId : 'NULL').','.ag_sql_value(date('Y-m-d H:i:s', strtotime($s['invoice_date'].' +1 day'))).','
        .ag_sql_value($type).','.ag_sql_value($direction).','.ag_sql_value($description).','.(float) $amount.','.ag_sql_value($currency).','
        .($allocatedAmount === null ? 'NULL' : (float) $allocatedAmount).','.($allocatedCurrency === null ? 'NULL' : ag_sql_value($allocatedCurrency)).','.($rate === null ? 'NULL' : (float) $rate).','
        .ag_sql_value($reference).','.ag_sql_value($type === 'customer_receipt' ? 'HSBC / Training deposit' : 'ChinaPlanet managed payment').','
        .($sourceType === null ? 'NULL' : ag_sql_value($sourceType)).','.($sourceId === null ? 'NULL' : (int) $sourceId).',1,1,'.ag_sql_value('Local training scenario').','.(int) $user->id.','.ag_sql_value(date('Y-m-d H:i:s')).')';
    ag_query($sql);
    return (int) $db->last_insert_id(MAIN_DB_PREFIX.'cpcc_financial_entry');
}

function ag_ensure_shipment(array $s, $orderId)
{
    global $db, $user;
    $id = ag_rowid('chinaplanetlogistics', "ref='".$db->escape($s['shipment_ref'])."'");
    if ($id) return $id;
    $o = new ChinaPlanetLogistics($db);
    $o->ref = $s['shipment_ref'];
    $o->fk_soc = $s['customer_id'];
    $o->fk_project = $s['project_id'];
    $o->fk_commande = $orderId;
    $o->container_no = $s['container'];
    $o->container_type = '40HQ';
    $o->nb_containers = 1;
    $o->booking_no = 'BK-'.$s['code'];
    $o->bl_no = 'BL-'.$s['code'];
    $o->shipping_line = $s['shipping_line'];
    $o->vessel_name = $s['vessel'];
    $o->voyage_no = 'V-'.$s['code'];
    $o->port_loading = $s['loading'];
    $o->port_destination = 'Alexandria, Egypt';
    $o->date_etd = $s['etd'];
    $o->date_eta = $s['eta'];
    $o->date_ata = $s['ata'];
    $o->freetime_days = 7;
    $o->freetime_start = $s['eta'];
    $o->freetime_end = date('Y-m-d', strtotime($s['eta'].' +7 days'));
    $o->fk_statut = $s['shipment_status'];
    $o->notes = 'Local agency training scenario '.$s['code'];
    $o->customer_note = $s['customer_note'];
    if ($o->create($user) <= 0) ag_fail('Cannot create shipment', $o);
    return (int) $o->id;
}

function ag_ensure_export_document(array $s, $shipmentId, $type)
{
    global $db, $user, $conf;
    $suffix = $type === 'commercial_invoice' ? 'CI' : 'PL';
    $ref = 'CPCC-'.$s['code'].'-'.$suffix;
    $id = ag_rowid('cpcc_export_document', "ref='".$db->escape($ref)."'");
    if ($id) return $id;
    $total = $type === 'commercial_invoice' ? $s['supplier_total'] : 0;
    $sql = 'INSERT INTO '.MAIN_DB_PREFIX.'cpcc_export_document(entity,fk_soc,fk_project,fk_shipment,document_type,ref,document_date,currency_code,total_amount,shipper_name,consignee_name,port_loading,port_destination,incoterm,notes_public,customer_visible,status,fk_user_creat,date_creation) VALUES ('
        .(int) $conf->entity.','.(int) $s['customer_id'].','.(int) $s['project_id'].','.(int) $shipmentId.','.ag_sql_value($type).','.ag_sql_value($ref).','.ag_sql_value($s['invoice_date']).','.ag_sql_value($s['supplier_currency']).','.(float) $total.','
        .ag_sql_value('Vision for Import and Export Limited').','.ag_sql_value('China Gate').','.ag_sql_value($s['loading']).','.ag_sql_value('Alexandria, Egypt').','.ag_sql_value('FOB').','.ag_sql_value('Training document linked to the managed sourcing project.').',1,1,'.(int) $user->id.','.ag_sql_value(date('Y-m-d H:i:s')).')';
    ag_query($sql);
    $id = (int) $db->last_insert_id(MAIN_DB_PREFIX.'cpcc_export_document');
    $qty = $type === 'commercial_invoice' ? 1 : 120;
    $unit = $type === 'commercial_invoice' ? $s['supplier_total'] : 0;
    ag_query('INSERT INTO '.MAIN_DB_PREFIX.'cpcc_export_document_line(fk_document,position,description,hs_code,quantity,unit,unit_price,total_amount,cartons,net_weight,gross_weight,country_origin,date_creation) VALUES ('.(int) $id.',1,'.ag_sql_value($s['product_label']).','.ag_sql_value('TRAINING-HS').','.(float) $qty.','.ag_sql_value($type === 'commercial_invoice' ? 'lot' : 'pcs').','.(float) $unit.','.(float) $total.',12,1180,1260,'.ag_sql_value('China').','.ag_sql_value(date('Y-m-d H:i:s')).')');
    return $id;
}

ag_find_admin();
$conf->global->MAIN_DISABLE_PDF_AUTOUPDATE = 1;
ag_install_schema();

$chinaGateId = ag_rowid('societe', "nom='China Gate'");
if (!$chinaGateId) ag_fail('China Gate customer not found. Run the base China Gate seed first.');
$modeId = ag_payment_mode();

$scenarios = array(
    array(
        'code'=>'AG-001','project_ref'=>'CG-AG-001','title'=>'HEHUI — Agency Order (RMB + USD supplier settlement)','supplier_name'=>'HEHUI Industrial Co., Ltd.','product_label'=>'HEHUI mixed homeware order','supplier_currency'=>'CNY','supplier_tx'=>7.20,'supplier_total'=>70000,'supplier_paid_allocated'=>55000,
        'service_currency'=>'CNY','service_tx'=>7.20,'services'=>array(array('category'=>'commission','label'=>'Order management commission','amount'=>2500),array('category'=>'shipping','label'=>'Sea freight management','amount'=>5000),array('category'=>'cargox','label'=>'CargoX document service','amount'=>180)),'invoice_allocation'=>3000,
        'deposits'=>array(array('amount'=>20000,'currency'=>'CNY','ref'=>'CPCC-AG-001-DEP-CNY')),
        'supplier_payments'=>array(array('amount'=>20000,'currency'=>'CNY','allocated'=>20000,'ref'=>'CPCC-AG-001-SUP-CNY'),array('amount'=>5000,'currency'=>'USD','allocated'=>35000,'ref'=>'CPCC-AG-001-SUP-USD')),
        'date'=>'2026-06-10','invoice_date'=>'2026-06-12','shipment_ref'=>'CG-AG-SHIP-001','container'=>'MSKU-AG-001','shipping_line'=>'COSCO','vessel'=>'MV ChinaPlanet One','loading'=>'Yantian','etd'=>'2026-07-05','eta'=>'2026-08-01','ata'=>null,'shipment_status'=>2,'closed'=>false,'customer_note'=>'On vessel. HEHUI order paid from both RMB and USD customer funds.'
    ),
    array(
        'code'=>'AG-002','project_ref'=>'CG-AG-002','title'=>'Shuangxin — Agency Order (RMB partial payment)','supplier_name'=>'Shuangxin Packaging Co., Ltd.','product_label'=>'Custom packaging and accessories','supplier_currency'=>'CNY','supplier_tx'=>7.20,'supplier_total'=>30000,'supplier_paid_allocated'=>12000,
        'service_currency'=>'CNY','service_tx'=>7.20,'services'=>array(array('category'=>'commission','label'=>'Order management commission','amount'=>1500),array('category'=>'shipping','label'=>'LCL freight management','amount'=>3200),array('category'=>'cargox','label'=>'CargoX document service','amount'=>180)),'invoice_allocation'=>0,
        'deposits'=>array(array('amount'=>15000,'currency'=>'CNY','ref'=>'CPCC-AG-002-DEP-CNY')),
        'supplier_payments'=>array(array('amount'=>12000,'currency'=>'CNY','allocated'=>12000,'ref'=>'CPCC-AG-002-SUP-CNY')),
        'date'=>'2026-06-22','invoice_date'=>'2026-06-25','shipment_ref'=>'CG-AG-SHIP-002','container'=>'LCL-AG-002','shipping_line'=>'OOCL','vessel'=>'MV ChinaPlanet Two','loading'=>'Nansha','etd'=>'2026-07-20','eta'=>'2026-08-18','ata'=>null,'shipment_status'=>1,'closed'=>false,'customer_note'=>'Production in progress. Supplier deposit paid; RMB balance remains.'
    ),
    array(
        'code'=>'AG-003','project_ref'=>'CG-AG-003','title'=>'Guangzhou Components — Agency Order (USD complete)','supplier_name'=>'Guangzhou Export Components Ltd.','product_label'=>'Export components order','supplier_currency'=>'USD','supplier_tx'=>1.00,'supplier_total'=>10000,'supplier_paid_allocated'=>10000,
        'service_currency'=>'USD','service_tx'=>1.00,'services'=>array(array('category'=>'commission','label'=>'Order management commission','amount'=>1000),array('category'=>'shipping','label'=>'Sea freight and handling','amount'=>1200),array('category'=>'cargox','label'=>'Export document service','amount'=>75)),'invoice_allocation'=>2275,
        'deposits'=>array(array('amount'=>10000,'currency'=>'USD','ref'=>'CPCC-AG-003-DEP-USD')),
        'supplier_payments'=>array(array('amount'=>10000,'currency'=>'USD','allocated'=>10000,'ref'=>'CPCC-AG-003-SUP-USD')),
        'date'=>'2026-05-02','invoice_date'=>'2026-05-05','shipment_ref'=>'CG-AG-SHIP-003','container'=>'TGHU-AG-003','shipping_line'=>'CMA CGM','vessel'=>'MV ChinaPlanet Three','loading'=>'Huangpu','etd'=>'2026-06-01','eta'=>'2026-06-28','ata'=>'2026-06-29','shipment_status'=>4,'closed'=>true,'customer_note'=>'Completed project. Supplier, service invoice, shipment and documents are settled.'
    ),
);

$results = array();
foreach ($scenarios as $s) {
    $s['customer_id'] = $chinaGateId;
    $s['supplier_id'] = ag_ensure_supplier($s['supplier_name']);
    $s['project_id'] = ag_ensure_project($s);
    $orderId = ag_ensure_customer_order($s);
    $invoiceId = ag_ensure_customer_invoice($s, $orderId);
    $allocationPaymentId = ag_ensure_invoice_allocation($s, $invoiceId, $modeId);
    $supplierOrderId = ag_ensure_supplier_order($s);
    $supplierInvoiceId = ag_ensure_supplier_invoice($s, $supplierOrderId);
    $supplierPaymentId = ag_ensure_supplier_payment($s, $supplierInvoiceId, $modeId);

    ag_repair_document_currency('commande', 'commandedet', 'fk_commande', $orderId, $s['service_currency'], $s['service_tx']);
    ag_repair_document_currency('facture', 'facturedet', 'fk_facture', $invoiceId, $s['service_currency'], $s['service_tx']);
    ag_repair_payment_currency($invoiceId, $s['service_tx'], false);
    ag_repair_document_currency('commande_fournisseur', 'commande_fournisseurdet', 'fk_commande', $supplierOrderId, $s['supplier_currency'], $s['supplier_tx']);
    ag_repair_document_currency('facture_fourn', 'facture_fourn_det', 'fk_facture_fourn', $supplierInvoiceId, $s['supplier_currency'], $s['supplier_tx'], 'pu_ht', 'tva');
    ag_repair_payment_currency($supplierInvoiceId, $s['supplier_tx'], true);
    $serviceAmounts = array_map(function ($line) { return (float) $line['amount']; }, $s['services']);
    ag_force_foreign_totals('commande', 'commandedet', 'fk_commande', $orderId, $serviceAmounts);
    ag_force_foreign_totals('facture', 'facturedet', 'fk_facture', $invoiceId, $serviceAmounts);
    ag_force_payment_allocation($invoiceId, $s['invoice_allocation'], false);
    ag_force_foreign_totals('commande_fournisseur', 'commande_fournisseurdet', 'fk_commande', $supplierOrderId, array($s['supplier_total']));
    ag_force_foreign_totals('facture_fourn', 'facture_fourn_det', 'fk_facture_fourn', $supplierInvoiceId, array($s['supplier_total']));
    ag_force_payment_allocation($supplierInvoiceId, $s['supplier_paid_allocated'], true);

    $productChargeId = ag_insert_charge($s, 'product', $s['product_label'], $s['supplier_total'], 'supplier_invoice', $supplierInvoiceId, $s['supplier_id']);
    foreach ($s['services'] as $line) ag_insert_charge($s, $line['category'], $line['label'], $line['amount'], 'customer_invoice', $invoiceId);
    foreach ($s['deposits'] as $deposit) ag_insert_entry($s, 'customer_receipt', 'C', 'Customer deposit received by ChinaPlanet', $deposit['amount'], $deposit['currency'], $deposit['ref'], $s['project_id']);
    foreach ($s['supplier_payments'] as $payment) ag_insert_entry($s, 'supplier_payment', 'D', 'Payment managed by ChinaPlanet for '.$s['supplier_name'], $payment['amount'], $payment['currency'], $payment['ref'], $s['project_id'], $productChargeId, $payment['allocated'], $s['supplier_currency'], 'supplier_invoice', $supplierInvoiceId);
    if ($allocationPaymentId) ag_insert_entry($s, 'invoice_allocation', 'D', 'Customer wallet allocated to Vision service invoice', $s['invoice_allocation'], $s['service_currency'], 'CPCC-'.$s['code'].'-INV-ALLOC', $s['project_id'], null, null, null, 'payment', $allocationPaymentId);

    $shipmentId = ag_ensure_shipment($s, $orderId);
    $commercialInvoiceId = ag_ensure_export_document($s, $shipmentId, 'commercial_invoice');
    $packingListId = ag_ensure_export_document($s, $shipmentId, 'packing_list');
    if (!empty($s['closed'])) {
        ag_query('UPDATE '.MAIN_DB_PREFIX.'projet SET fk_statut=2 WHERE rowid='.(int) $s['project_id']);
        ag_query('UPDATE '.MAIN_DB_PREFIX.'commande SET fk_statut=3 WHERE rowid='.(int) $orderId);
    }

    $results[] = array(
        'scenario'=>$s['code'], 'project'=>$s['project_ref'], 'project_id'=>$s['project_id'],
        'customer_order_id'=>$orderId, 'service_invoice_id'=>$invoiceId, 'supplier_order_id'=>$supplierOrderId,
        'supplier_invoice_id'=>$supplierInvoiceId, 'supplier_payment_id'=>$supplierPaymentId,
        'shipment_id'=>$shipmentId, 'commercial_invoice_id'=>$commercialInvoiceId, 'packing_list_id'=>$packingListId,
    );
}

echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL;
