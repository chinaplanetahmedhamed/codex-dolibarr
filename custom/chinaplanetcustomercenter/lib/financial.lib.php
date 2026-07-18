<?php

function cpcc_financial_schema_ready($db)
{
    $resql = $db->query("SHOW TABLES LIKE '".$db->escape(MAIN_DB_PREFIX."cpcc_financial_entry")."'");
    return $resql && $db->num_rows($resql) > 0;
}

function cpcc_financial_currency($code, $fallback = 'USD')
{
    $code = strtoupper(trim((string) $code));
    if ($code === 'LE') $code = 'EGP';
    if ($code === 'RNB' || $code === 'CNY') $code = 'RMB';
    return preg_match('/^[A-Z]{3}$/', $code) ? $code : $fallback;
}

/**
 * Return how many units of a currency equal one USD for balance valuation.
 *
 * The configured rate is deliberately separate from transaction FX rates: it
 * is a current reporting/valuation rate and never changes the original ledger.
 */
function cpcc_financial_usd_units_per_currency($currency, $overrides = array())
{
    $currency = cpcc_financial_currency($currency, '');
    if ($currency === 'USD') return 1.0;
    if ($currency === '') return 0.0;

    if (isset($overrides[$currency])) {
        return (float) $overrides[$currency] > 0 ? (float) $overrides[$currency] : 0.0;
    }

    $constant = 'CHINAPLANETCUSTOMERCENTER_USD_UNITS_'.$currency;
    $rate = function_exists('getDolGlobalString') ? (float) getDolGlobalString($constant) : 0.0;
    return $rate > 0 ? $rate : 0.0;
}

/**
 * Value all available currency balances in USD without altering the ledger.
 * Rates use the convention "1 USD = N units of currency".
 */
function cpcc_financial_total_usd($summary, $overrides = array())
{
    $result = array('total'=>0.0, 'converted'=>array(), 'rates'=>array(), 'missing'=>array());
    foreach ($summary as $currency => $totals) {
        $currency = cpcc_financial_currency($currency, '');
        if ($currency === '') continue;
        $rate = cpcc_financial_usd_units_per_currency($currency, $overrides);
        if ($rate <= 0) {
            $result['missing'][] = $currency;
            continue;
        }
        $available = isset($totals['available']) ? (float) $totals['available'] : 0.0;
        $usdValue = $available / $rate;
        $result['converted'][$currency] = $usdValue;
        $result['rates'][$currency] = $rate;
        $result['total'] += $usdValue;
    }
    sort($result['missing']);
    return $result;
}

function cpcc_financial_type_label($type)
{
    $labels = array(
        'customer_receipt' => 'Customer payment / دفعة العميل',
        'opening_balance' => 'Opening balance / رصيد افتتاحي',
        'supplier_payment' => 'Supplier payment / دفعة مورد',
        'shipping' => 'Shipping / شحن',
        'commission' => 'Commission / عمولة',
        'cargox' => 'CargoX',
        'dhl' => 'DHL',
        'inspection' => 'Inspection / فحص',
        'service' => 'Service charge / خدمة',
        'invoice_allocation' => 'Invoice paid from customer funds / سداد فاتورة من رصيد العميل',
        'other' => 'Other expense / مصروف آخر',
        'fx_out' => 'Currency conversion out / تحويل صادر',
        'fx_in' => 'Currency conversion in / تحويل وارد',
        'adjustment' => 'Adjustment / تسوية',
        'native_payment' => 'Vision System customer payment / دفعة مسجلة',
    );
    return isset($labels[$type]) ? $labels[$type] : $type;
}

function cpcc_financial_empty_summary()
{
    return array(
        'received' => 0.0,
        'spent' => 0.0,
        'available' => 0.0,
        'obligations' => 0.0,
        'supplier_remaining' => 0.0,
        'future_services' => 0.0,
        'unpaid_invoices' => 0.0,
        'required_funding' => 0.0,
    );
}

/**
 * Amount the customer must add in one currency.
 *
 * Available may be negative. In that case the existing wallet deficit is also
 * part of the required funding. Values from different currencies must never be
 * passed to this function as one combined total.
 */
function cpcc_financial_required_funding($supplierRemaining, $futureServices, $unpaidInvoices, $available)
{
    return max(0.0, (float) $supplierRemaining + (float) $futureServices + (float) $unpaidInvoices - (float) $available);
}

function cpcc_financial_is_supplier_category($category)
{
    return in_array(strtolower(trim((string) $category)), array('product', 'supplier', 'supplier_order'), true);
}

/**
 * Return customer-visible project commitments with their allocation status.
 *
 * A non-product charge linked to a validated customer invoice is deliberately
 * excluded from future-services totals later, because that invoice is counted
 * in the native unpaid-invoice balance instead.
 */
function cpcc_financial_commitment_rows($db, $socid, $currencyFallback, $projectid = 0)
{
    $rows = array();
    if (!cpcc_financial_schema_ready($db) || (int) $socid <= 0) return $rows;

    $sql = "SELECT c.rowid,c.fk_project,c.charge_date,c.category,c.label,c.supplier_label,c.amount,c.currency_code,c.reference,c.source_type,c.source_id,";
    $sql .= " p.ref project_ref,p.title project_title,";
    $sql .= " COALESCE(SUM(CASE WHEN e.status=1 AND e.direction='D' THEN COALESCE(e.allocated_amount,0) ELSE 0 END),0) paid,";
    $sql .= " fi.rowid billed_invoice_id,fi.ref billed_invoice_ref,fi.fk_statut billed_invoice_status,fi.type billed_invoice_type";
    $sql .= " FROM ".MAIN_DB_PREFIX."cpcc_project_charge c";
    $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."cpcc_financial_entry e ON e.fk_charge=c.rowid";
    $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."projet p ON p.rowid=c.fk_project AND p.entity=c.entity AND p.fk_soc=c.fk_soc";
    $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."facture fi ON c.source_type IN ('customer_invoice','invoice') AND fi.rowid=c.source_id AND fi.fk_soc=c.fk_soc";
    $sql .= " WHERE c.entity=".(int) $GLOBALS['conf']->entity." AND c.fk_soc=".(int) $socid." AND c.status=1 AND c.customer_visible=1";
    if ((int) $projectid > 0) $sql .= " AND c.fk_project=".(int) $projectid;
    $sql .= " GROUP BY c.rowid,c.fk_project,c.charge_date,c.category,c.label,c.supplier_label,c.amount,c.currency_code,c.reference,c.source_type,c.source_id,p.ref,p.title,fi.rowid,fi.ref,fi.fk_statut,fi.type";
    $sql .= " ORDER BY c.charge_date,c.rowid";

    $resql = $db->query($sql);
    if (!$resql) return $rows;
    while ($obj = $db->fetch_object($resql)) {
        $amount = (float) $obj->amount;
        $paid = min($amount, max(0.0, (float) $obj->paid));
        $invoiceType = $obj->billed_invoice_type !== null ? (int) $obj->billed_invoice_type : -1;
        $isBilled = !empty($obj->billed_invoice_id) && (int) $obj->billed_invoice_status > 0 && in_array($invoiceType, array(0, 1, 5), true);
        $rows[] = array(
            'id' => (int) $obj->rowid,
            'project_id' => (int) $obj->fk_project,
            'project_ref' => (string) $obj->project_ref,
            'project_title' => (string) $obj->project_title,
            'date' => (string) $obj->charge_date,
            'category' => (string) $obj->category,
            'label' => (string) $obj->label,
            'supplier' => (string) $obj->supplier_label,
            'amount' => $amount,
            'paid' => $paid,
            'remaining' => max(0.0, $amount - $paid),
            'currency' => cpcc_financial_currency($obj->currency_code, $currencyFallback),
            'reference' => (string) $obj->reference,
            'source_type' => (string) $obj->source_type,
            'source_id' => (int) $obj->source_id,
            'is_supplier' => cpcc_financial_is_supplier_category($obj->category),
            'is_billed' => $isBilled,
            'invoice_id' => (int) $obj->billed_invoice_id,
            'invoice_ref' => (string) $obj->billed_invoice_ref,
        );
    }
    return $rows;
}

/** Return open native Dolibarr customer invoices in their original currency. */
function cpcc_financial_unpaid_invoices($db, $socid, $currencyFallback, $projectid = 0)
{
    $rows = array();
    $socid = (int) $socid;
    if ($socid <= 0) return $rows;

    $sql = "SELECT f.rowid,f.ref,f.datef,f.date_lim_reglement,f.fk_projet,f.multicurrency_code,f.total_ttc,f.multicurrency_total_ttc,";
    $sql .= " COALESCE(pay.amount,0) paid_base,COALESCE(pay.multicurrency_amount,0) paid_multi,";
    $sql .= " COALESCE(discount.amount,0) discount_base,COALESCE(discount.multicurrency_amount,0) discount_multi,";
    $sql .= " p.ref project_ref,p.title project_title";
    $sql .= " FROM ".MAIN_DB_PREFIX."facture f";
    $sql .= " LEFT JOIN (SELECT pf.fk_facture,SUM(pf.amount) amount,SUM(pf.multicurrency_amount) multicurrency_amount FROM ".MAIN_DB_PREFIX."paiement_facture pf GROUP BY pf.fk_facture) pay ON pay.fk_facture=f.rowid";
    $sql .= " LEFT JOIN (SELECT r.fk_facture,SUM(r.amount_ttc) amount,SUM(r.multicurrency_amount_ttc) multicurrency_amount FROM ".MAIN_DB_PREFIX."societe_remise_except r WHERE r.fk_facture IS NOT NULL GROUP BY r.fk_facture) discount ON discount.fk_facture=f.rowid";
    $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."projet p ON p.rowid=f.fk_projet AND p.entity=f.entity AND p.fk_soc=f.fk_soc";
    $sql .= " WHERE f.entity=".(int) $GLOBALS['conf']->entity." AND f.fk_soc=".$socid." AND f.fk_statut=1 AND f.paye=0 AND f.type IN (0,1,5)";
    if ((int) $projectid > 0) $sql .= " AND f.fk_projet=".(int) $projectid;
    $sql .= " ORDER BY f.datef,f.rowid";

    $resql = $db->query($sql);
    if (!$resql) return $rows;
    while ($obj = $db->fetch_object($resql)) {
        $hasMulticurrency = trim((string) $obj->multicurrency_code) !== '';
        $currency = cpcc_financial_currency($hasMulticurrency ? $obj->multicurrency_code : $currencyFallback, $currencyFallback);
        $total = $hasMulticurrency ? (float) $obj->multicurrency_total_ttc : (float) $obj->total_ttc;
        $paid = $hasMulticurrency ? (float) $obj->paid_multi : (float) $obj->paid_base;
        $discount = $hasMulticurrency ? (float) $obj->discount_multi : (float) $obj->discount_base;
        $remaining = max(0.0, $total - $paid - $discount);
        if ($remaining <= 0.005) continue;
        $rows[] = array(
            'id' => (int) $obj->rowid,
            'ref' => (string) $obj->ref,
            'date' => (string) $obj->datef,
            'due_date' => (string) $obj->date_lim_reglement,
            'project_id' => (int) $obj->fk_projet,
            'project_ref' => (string) $obj->project_ref,
            'project_title' => (string) $obj->project_title,
            'total' => $total,
            'paid' => max(0.0, $paid + $discount),
            'remaining' => $remaining,
            'currency' => $currency,
        );
    }
    return $rows;
}

/** Return all validated customer invoices with paid and remaining values. */
function cpcc_financial_customer_invoices($db, $socid, $currencyFallback, $projectid = 0)
{
    $rows = array();
    $socid = (int) $socid;
    if ($socid <= 0) return $rows;
    $sql = "SELECT f.rowid,f.ref,f.ref_client,f.datef,f.date_lim_reglement,f.fk_projet,f.fk_statut,f.paye,f.multicurrency_code,f.total_ttc,f.multicurrency_total_ttc,";
    $sql .= " COALESCE(pay.amount,0) paid_base,COALESCE(pay.multicurrency_amount,0) paid_multi,COALESCE(discount.amount,0) discount_base,COALESCE(discount.multicurrency_amount,0) discount_multi,p.ref project_ref,p.title project_title";
    $sql .= " FROM ".MAIN_DB_PREFIX."facture f";
    $sql .= " LEFT JOIN (SELECT pf.fk_facture,SUM(pf.amount) amount,SUM(pf.multicurrency_amount) multicurrency_amount FROM ".MAIN_DB_PREFIX."paiement_facture pf GROUP BY pf.fk_facture) pay ON pay.fk_facture=f.rowid";
    $sql .= " LEFT JOIN (SELECT r.fk_facture,SUM(r.amount_ttc) amount,SUM(r.multicurrency_amount_ttc) multicurrency_amount FROM ".MAIN_DB_PREFIX."societe_remise_except r WHERE r.fk_facture IS NOT NULL GROUP BY r.fk_facture) discount ON discount.fk_facture=f.rowid";
    $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."projet p ON p.rowid=f.fk_projet AND p.entity=f.entity AND p.fk_soc=f.fk_soc";
    $sql .= " WHERE f.entity=".(int)$GLOBALS['conf']->entity." AND f.fk_soc=".$socid." AND f.fk_statut>0 AND f.type IN (0,1,5)";
    if ((int)$projectid>0) $sql .= " AND f.fk_projet=".(int)$projectid;
    $sql .= " ORDER BY f.datef DESC,f.rowid DESC";
    $resql=$db->query($sql);if(!$resql)return $rows;
    while($obj=$db->fetch_object($resql)){
        $multi=trim((string)$obj->multicurrency_code)!=='';$currency=cpcc_financial_currency($multi?$obj->multicurrency_code:$currencyFallback,$currencyFallback);
        $total=$multi?(float)$obj->multicurrency_total_ttc:(float)$obj->total_ttc;$paid=$multi?(float)$obj->paid_multi:(float)$obj->paid_base;$discount=$multi?(float)$obj->discount_multi:(float)$obj->discount_base;$paid=max(0.0,$paid+$discount);$remaining=max(0.0,$total-$paid);
        $rows[]=array('id'=>(int)$obj->rowid,'ref'=>(string)$obj->ref,'ref_client'=>(string)$obj->ref_client,'date'=>(string)$obj->datef,'due_date'=>(string)$obj->date_lim_reglement,'project_id'=>(int)$obj->fk_projet,'project_ref'=>(string)$obj->project_ref,'project_title'=>(string)$obj->project_title,'total'=>$total,'paid'=>$paid,'remaining'=>$remaining,'currency'=>$currency,'status'=>$obj->paye?'paid':($remaining<$total&&$paid>0?'partial':'open'));
    }
    return $rows;
}

function cpcc_financial_native_receipts($db, $socid, $currencyFallback)
{
    $rows = array();
    $socid = (int) $socid;
    if ($socid <= 0) return $rows;

    $sql = "SELECT p.rowid, p.datep, p.ref, p.num_paiement, f.fk_projet,";
    $sql .= " COALESCE(NULLIF(pf.multicurrency_code,''), NULLIF(f.multicurrency_code,''), '".$db->escape($currencyFallback)."') currency_code,";
    $sql .= " SUM(CASE WHEN COALESCE(NULLIF(pf.multicurrency_code,''), NULLIF(f.multicurrency_code,'')) IS NOT NULL THEN COALESCE(NULLIF(pf.multicurrency_amount,0),pf.amount) ELSE pf.amount END) amount";
    $sql .= " FROM ".MAIN_DB_PREFIX."paiement p";
    $sql .= " INNER JOIN ".MAIN_DB_PREFIX."paiement_facture pf ON pf.fk_paiement=p.rowid";
    $sql .= " INNER JOIN ".MAIN_DB_PREFIX."facture f ON f.rowid=pf.fk_facture";
    $sql .= " WHERE f.fk_soc=".$socid." AND f.fk_statut>0 AND p.statut=1";
    if (cpcc_financial_schema_ready($db)) {
        $sql .= " AND NOT EXISTS (SELECT 1 FROM ".MAIN_DB_PREFIX."cpcc_financial_entry ce WHERE ce.entity=p.entity AND ce.fk_soc=f.fk_soc AND ce.source_type='payment' AND ce.source_id=p.rowid AND ce.status=1)";
    }
    $sql .= " GROUP BY p.rowid,p.datep,p.ref,p.num_paiement,f.fk_projet,currency_code ORDER BY p.datep,p.rowid";
    $resql = $db->query($sql);
    if (!$resql) return $rows;
    while ($obj = $db->fetch_object($resql)) {
        $rows[] = array(
            'id' => 'payment-'.(int) $obj->rowid,
            'date' => (string) $obj->datep,
            'type' => 'native_payment',
            'direction' => 'C',
            'description' => cpcc_financial_type_label('native_payment'),
            'amount' => (float) $obj->amount,
            'currency' => cpcc_financial_currency($obj->currency_code, $currencyFallback),
            'reference' => !empty($obj->num_paiement) ? $obj->num_paiement : $obj->ref,
            'bank' => '',
            'project_id' => (int) $obj->fk_projet,
            'charge_id' => 0,
            'allocated_amount' => null,
            'allocated_currency' => '',
            'exchange_rate' => null,
            'transfer_group' => '',
            'proof_ecm_id' => 0,
        );
    }
    return $rows;
}

function cpcc_financial_custom_entries($db, $socid, $currencyFallback, $projectid = 0)
{
    $rows = array();
    if (!cpcc_financial_schema_ready($db)) return $rows;
    $sql = "SELECT rowid,entry_date,entry_type,direction,description,amount,currency_code,reference,bank_label,fk_project,fk_charge,allocated_amount,allocated_currency,exchange_rate,transfer_group,proof_ecm_id";
    $sql .= " FROM ".MAIN_DB_PREFIX."cpcc_financial_entry WHERE entity=".(int) $GLOBALS['conf']->entity." AND fk_soc=".(int) $socid." AND status=1 AND customer_visible=1";
    if ($projectid > 0) $sql .= " AND fk_project=".(int) $projectid;
    $sql .= " ORDER BY entry_date,rowid";
    $resql = $db->query($sql);
    if (!$resql) return $rows;
    while ($obj = $db->fetch_object($resql)) {
        $rows[] = array(
            'id' => 'entry-'.(int) $obj->rowid,
            'date' => (string) $obj->entry_date,
            'type' => (string) $obj->entry_type,
            'direction' => (string) $obj->direction,
            'description' => (string) $obj->description,
            'amount' => (float) $obj->amount,
            'currency' => cpcc_financial_currency($obj->currency_code, $currencyFallback),
            'reference' => (string) $obj->reference,
            'bank' => (string) $obj->bank_label,
            'project_id' => (int) $obj->fk_project,
            'charge_id' => (int) $obj->fk_charge,
            'allocated_amount' => $obj->allocated_amount !== null ? (float) $obj->allocated_amount : null,
            'allocated_currency' => cpcc_financial_currency($obj->allocated_currency, ''),
            'exchange_rate' => $obj->exchange_rate !== null ? (float) $obj->exchange_rate : null,
            'transfer_group' => (string) $obj->transfer_group,
            'proof_ecm_id' => (int) $obj->proof_ecm_id,
        );
    }
    return $rows;
}

function cpcc_financial_statement($db, $socid, $currencyFallback, $projectid = 0)
{
    $rows = cpcc_financial_custom_entries($db, $socid, $currencyFallback, $projectid);
    if ($projectid <= 0) $rows = array_merge($rows, cpcc_financial_native_receipts($db, $socid, $currencyFallback));
    usort($rows, function ($a, $b) {
        $cmp = strcmp($a['date'], $b['date']);
        return $cmp !== 0 ? $cmp : strcmp($a['id'], $b['id']);
    });
    $balances = array();
    foreach ($rows as &$row) {
        $currency = $row['currency'];
        if (!isset($balances[$currency])) $balances[$currency] = 0.0;
        $balances[$currency] += $row['direction'] === 'C' ? $row['amount'] : -$row['amount'];
        if (abs($balances[$currency]) < 0.005) $balances[$currency] = 0.0;
        $row['balance'] = $balances[$currency];
    }
    unset($row);
    return $rows;
}

function cpcc_financial_summary($db, $socid, $currencyFallback)
{
    $summary = cpcc_financial_summarize_rows(cpcc_financial_statement($db, $socid, $currencyFallback));
    foreach (cpcc_financial_commitment_rows($db, $socid, $currencyFallback) as $commitment) {
        $currency = $commitment['currency'];
        if (!isset($summary[$currency])) $summary[$currency] = cpcc_financial_empty_summary();
        $remaining = (float) $commitment['remaining'];
        if (!empty($commitment['is_supplier'])) {
            $summary[$currency]['supplier_remaining'] += $remaining;
        } elseif (empty($commitment['is_billed'])) {
            $summary[$currency]['future_services'] += $remaining;
        }
    }
    foreach (cpcc_financial_unpaid_invoices($db, $socid, $currencyFallback) as $invoice) {
        $currency = $invoice['currency'];
        if (!isset($summary[$currency])) $summary[$currency] = cpcc_financial_empty_summary();
        $summary[$currency]['unpaid_invoices'] += (float) $invoice['remaining'];
    }
    foreach ($summary as $currency => &$totals) {
        $totals = array_merge(cpcc_financial_empty_summary(), $totals);
        $totals['obligations'] = $totals['supplier_remaining'] + $totals['future_services'] + $totals['unpaid_invoices'];
        $totals['required_funding'] = cpcc_financial_required_funding($totals['supplier_remaining'], $totals['future_services'], $totals['unpaid_invoices'], $totals['available']);
    }
    unset($totals);
    ksort($summary);
    return $summary;
}

function cpcc_financial_summarize_rows($rows)
{
    $summary = array();
    foreach ($rows as $row) {
        $currency = $row['currency'];
        if (!isset($summary[$currency])) $summary[$currency] = cpcc_financial_empty_summary();
        if ($row['direction'] === 'C') {
            if (!in_array($row['type'], array('fx_in'), true)) $summary[$currency]['received'] += $row['amount'];
            $summary[$currency]['available'] += $row['amount'];
        } else {
            if (!in_array($row['type'], array('fx_out'), true)) $summary[$currency]['spent'] += $row['amount'];
            $summary[$currency]['available'] -= $row['amount'];
        }
    }
    ksort($summary);
    return $summary;
}

function cpcc_financial_project_charges($db, $socid, $projectid, $currencyFallback)
{
    return cpcc_financial_commitment_rows($db, $socid, $currencyFallback, $projectid);
}
