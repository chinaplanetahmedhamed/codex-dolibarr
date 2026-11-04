<?php
if (!isset($_SERVER['QUERY_STRING'])) $_SERVER['QUERY_STRING'] = '';
require_once __DIR__.'/../lib/main.lib.php';
require_once __DIR__.'/../lib/admin_shell.lib.php';
require_once __DIR__.'/../lib/financial.lib.php';
require_once __DIR__.'/../lib/portal.lib.php';

if (empty($user->id) || !empty($user->socid) || (!$user->admin && !$user->hasRight('chinaplanetcustomercenter', 'write'))) {
    accessforbidden();
}

$langs->load('chinaplanetcustomercenter@chinaplanetcustomercenter');

function cpad_portfolio_currency_text($summary, $field = 'amount')
{
    $parts = array();
    ksort($summary);
    foreach ($summary as $currency => $value) {
        if (is_array($value)) {
            $amount = isset($value[$field]) ? (float) $value[$field] : 0.0;
        } else {
            $amount = (float) $value;
        }
        if (abs($amount) < 0.005) continue;
        $parts[] = $currency.' '.cpcc_portal_money($amount);
    }
    return $parts ? implode(' · ', $parts) : '—';
}

$currencyFallback = cpcc_financial_currency(!empty($conf->currency) ? $conf->currency : 'USD');
// Preserve raw input until strict calendar classification; sanitizing to an
// empty string must not silently select today's financial view.
$reportingDay = date('Y-m-d');
$reportPeriod = cpcc_financial_report_period(GETPOST('asof', 'none', 1, null, null, 1, 1), $reportingDay);
$asOf = $reportPeriod['date'] ?? '';
$showCurrentManaged = $reportPeriod['can_show_current'];
if ($showCurrentManaged && !cpcc_financial_schema_ready($db)) {
    accessforbidden('Financial tables are not installed. Re-enable the module or run the module upgrade first.');
}

$summaryTemplate = cpcc_financial_empty_summary();
$summaryFields = array_keys($summaryTemplate);

$portfolioSummary = array();
$portfolioOverdueByCurrency = array();
$totalCustomers = 0;
$customersLate = 0;
$customersNeedFunds = 0;
$totalUnpaidInvoices = 0;
$totalOverdueInvoices = 0;
$customers = array();
$customerSql = "SELECT rowid, nom FROM ".MAIN_DB_PREFIX."societe WHERE entity IN (0,".(int) $conf->entity.") AND client IN (1,2,3) ORDER BY nom";
$customerResult = $showCurrentManaged ? $db->query($customerSql) : false;
if ($customerResult) {
    while ($customer = $db->fetch_object($customerResult)) {
        $customerId = (int) $customer->rowid;
        $summary = cpcc_financial_summary($db, $customerId, $currencyFallback, 0, $asOf);
        if (empty($summary)) {
            $summary = array($currencyFallback => $summaryTemplate);
        }
        $unpaidRows = cpcc_financial_unpaid_invoices($db, $customerId, $currencyFallback, 0, $asOf);
        $overdueRows = cpcc_financial_overdue_unpaid_invoices($db, $customerId, $currencyFallback, 0, $asOf);
        $unpaidCount = count($unpaidRows);
        $overdueCount = count($overdueRows);
        $overdueByCurrency = array();
        foreach ($overdueRows as $row) {
            $c = $row['currency'];
            if (!isset($overdueByCurrency[$c])) {
                $overdueByCurrency[$c] = 0.0;
            }
            if (!isset($portfolioOverdueByCurrency[$c])) {
                $portfolioOverdueByCurrency[$c] = 0.0;
            }
            $amount = (float) $row['remaining'];
            $overdueByCurrency[$c] += $amount;
            $portfolioOverdueByCurrency[$c] += $amount;
        }

        foreach ($summary as $currency => $values) {
            if (!isset($portfolioSummary[$currency])) {
                $portfolioSummary[$currency] = $summaryTemplate;
            }
            foreach ($summaryFields as $field) {
                $portfolioSummary[$currency][$field] += isset($values[$field]) ? (float) $values[$field] : 0.0;
            }
        }

        $usd = cpcc_financial_total_usd($summary);
        $requiredForCustomer = false;
        foreach ($summary as $currency => $values) {
            if ((float) $values['required_funding'] > 0.005) {
                $requiredForCustomer = true;
                break;
            }
        }
        if ($requiredForCustomer) {
            $customersNeedFunds++;
        }

        $totalCustomers++;
        if ($overdueCount > 0) {
            $customersLate++;
            $totalOverdueInvoices += $overdueCount;
        }
        $totalUnpaidInvoices += $unpaidCount;

        $managedPosition = $showCurrentManaged ? cpcc_financial_managed_position($db, $customerId) : null;
        if ($managedPosition !== null) cpcc_financial_require_live_connection($managedPosition);
        $customers[] = array(
            'id' => $customerId,
            'managed_position' => $managedPosition,
            'name' => (string) $customer->nom,
            'as_of' => $asOf,
            'summary' => $summary,
            'unpaid_count' => $unpaidCount,
            'overdue_count' => $overdueCount,
            'overdue_by_currency' => $overdueByCurrency,
            'needs_funding' => $requiredForCustomer,
            'usd_total' => $usd['total'],
            'usd_missing' => $usd['missing'],
        );
    }
}

usort($customers, function ($left, $right) {
    // Currency units cannot determine a customer's relative funding priority.
    if ($left['needs_funding'] !== $right['needs_funding']) {
        return $left['needs_funding'] ? -1 : 1;
    }
    $nameOrder = strcasecmp($left['name'], $right['name']);
    return $nameOrder !== 0 ? $nameOrder : ($left['id'] <=> $right['id']);
}
);

$globalUsd = $showCurrentManaged ? cpcc_financial_total_usd($portfolioSummary) : array('total'=>null,'missing'=>array());

if (empty($customers)) {
    $customers = array();
}

llxHeader('', 'Financial Center', '', '', 0, 0, array(), array(cpcc_admin_shell_asset('/custom/chinaplanetcustomercenter/css/admin_dashboard.css'), cpcc_admin_shell_asset('/custom/chinaplanetcustomercenter/css/ux_fixes.css')));
cpcc_admin_shell_open('financial', 'Financial Center', $showCurrentManaged ? 'Current invoice-credit projections and funding estimates' : $langs->trans('CPCCPortfolioHistoryUnavailable'), 'fa-chart-pie', array());
?>

<div class="cpad-finance-portfolio-theme">
<div class="cpad-finance-lead">
  <p class="opacitymedium">all-customer finance roll-up and late-payment visibility</p>
  <a class="cpad-primary-action" href="<?php echo dol_buildpath('/chinaplanetcustomercenter/admin/financial.php', 1); ?>"><span class="fas fa-wallet" aria-hidden="true"></span><b>Finance Allocation Center</b><small>Funds allocation and exception adjustments</small></a>
</div>

<form method="get" class="cpad-context-form">
  <div class="cpad-context-fields">
    <label>
      <span><?php echo dol_escape_htmltag($langs->trans('CPCCPortfolioTransactionCutoff')); ?></span>
      <input type="date" name="asof" value="<?php echo dol_escape_htmltag($asOf); ?>">
    </label>
    <button class="button" type="submit">Apply</button>
  </div>
</form>
<?php if (!$showCurrentManaged) {
    $reasonKey = $reportPeriod['mode'] === 'invalid_date' ? 'CPCCPortfolioInvalidDate' : ($reportPeriod['mode'] === 'future_unavailable' ? 'CPCCPortfolioFutureUnavailable' : 'CPCCPortfolioHistoryUnavailable');
    $historyDirection = isset($langs->defaultlang) && strpos(strtolower($langs->defaultlang), 'ar') === 0 ? 'rtl' : 'ltr';
?>
<section class="cpad-panel" role="status" dir="<?php echo $historyDirection; ?>" data-history-coverage="unavailable" data-history-reason="<?php echo dol_escape_htmltag($reportPeriod['mode']); ?>">
  <h2><?php echo dol_escape_htmltag($langs->trans($reasonKey)); ?></h2>
  <p><?php echo dol_escape_htmltag($langs->trans('CPCCPortfolioHistoryCoverage')); ?></p>
  <a class="button" data-current-financial-view="1" href="<?php echo dol_escape_htmltag(dol_buildpath('/chinaplanetcustomercenter/admin/financial_portfolio.php', 1)); ?>"><?php echo dol_escape_htmltag($langs->trans('CPCCPortfolioToday')); ?></a>
</section>
<?php } else { ?>
<p class="cpad-footnote"><?php echo dol_escape_htmltag($langs->trans('CPCCPortfolioDiagnosticLimitations')); ?></p>
<p class="cpad-footnote"><?php echo cpcc_ui_funds_label('CPCCFundsEstimateBasis'); ?></p>

<section class="cpad-kpis">
  <div class="cpad-kpi"><b><?php echo $totalCustomers; ?></b><span>Total Customers</span><small>customers with customer profile</small></div>
  <div class="cpad-kpi danger"><b><?php echo $customersLate; ?></b><span>Customers With Late Invoices</span><small>due date already passed</small></div>
  <div class="cpad-kpi gold"><b><?php echo $customersNeedFunds; ?></b><span><?php echo cpcc_ui_funds_label('CPCCFundsEstimateCustomers'); ?></span><small><?php echo cpcc_ui_funds_label('CPCCFundsEstimateBasis'); ?></small></div>
  <div class="cpad-kpi"><b><?php echo $totalUnpaidInvoices; ?></b><span>Unpaid Invoices</span><small>Total count</small></div>
</section>
<section class="cpad-kpis">
  <div class="cpad-kpi"><b><?php echo $totalOverdueInvoices; ?></b><span>Overdue Open Invoices</span><small>overdue count</small></div>
  <div class="cpad-kpi"><b><?php echo cpcc_portal_money($globalUsd['total']); ?></b><span><?php echo dol_escape_htmltag($langs->trans('CPCCPortfolioWalletUsdValuation')); ?></span><small>exchange-rate valuation only</small></div>
  <div class="cpad-kpi"><b><?php echo dol_escape_htmltag(cpad_portfolio_currency_text($portfolioSummary, 'supplier_remaining')); ?></b><span>Supplier Remaining</span><small>approved supplier obligations</small></div>
  <div class="cpad-kpi"><b><?php echo dol_escape_htmltag(cpad_portfolio_currency_text($portfolioSummary, 'future_services')); ?></b><span>Future Services</span><small>approved future service commitments</small></div>
</section>

<section class="cpad-panel">
  <div class="cpad-title"><span>Finance Totals by Currency</span><small>Aggregated without mixing currencies</small></div>
  <div class="cpad-table-scroll">
    <table class="cpad-table cpad-responsive-table cpad-finance-totals-table">
      <thead>
        <tr>
          <th>Currency</th>
          <th><?php echo cpcc_ui_funds_label('CPCCFundsInvoiceCredit'); ?></th>
          <th>Obligations</th>
          <th>Unpaid Invoices</th>
          <th>Overdue Invoices</th>
          <th><?php echo cpcc_ui_funds_label('CPCCFundsEstimate'); ?></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($portfolioSummary as $currency => $totals) { ?>
        <tr>
          <td data-label="Currency"><span class="cpad-cell-value"><b><?php echo dol_escape_htmltag($currency); ?></b></span></td>
          <td data-label="<?php echo cpcc_ui_funds_label('CPCCFundsInvoiceCredit'); ?>"><span class="cpad-cell-value"><?php echo cpcc_portal_money($totals['available']); ?></span></td>
          <td data-label="Obligations"><span class="cpad-cell-value"><?php echo cpcc_portal_money($totals['obligations']); ?></span></td>
          <td data-label="Unpaid Invoices"><span class="cpad-cell-value"><?php echo cpcc_portal_money($totals['unpaid_invoices']); ?></span></td>
          <td data-label="Overdue Invoices"><span class="cpad-cell-value"><?php echo cpcc_portal_money(isset($portfolioOverdueByCurrency[$currency]) ? $portfolioOverdueByCurrency[$currency] : 0); ?></span></td>
          <td data-label="<?php echo cpcc_ui_funds_label('CPCCFundsEstimate'); ?>"><span class="cpad-cell-value"><?php echo cpcc_portal_money($totals['required_funding']); ?></span></td>
        </tr>
      <?php } ?>
      </tbody>
    </table>
  </div>
  <?php if (!empty($globalUsd['missing'])) { ?>
  <p class="cpad-footnote">Some currencies were excluded from USD valuation due to missing rate settings: <?php echo dol_escape_htmltag(implode(', ', $globalUsd['missing'])); ?></p>
  <?php } ?>
</section>

<section class="cpad-panel">
  <div class="cpad-title"><span>Customer Finance Matrix</span><small>Link customer name for Customer Statement</small></div>
  <div class="cpad-table-scroll">
    <table class="cpad-table cpad-responsive-table cpad-customer-finance-table">
      <thead>
        <tr>
          <th>Customer</th>
          <th><?php echo cpcc_ui_funds_label('CPCCFundsInvoiceCreditUsd'); ?></th>
          <th><?php echo cpcc_ui_funds_label('CPCCFundsInvoiceCredit'); ?></th>
          <th>Obligations</th>
          <th>Unpaid</th>
          <th>Overdue</th>
          <th><?php echo cpcc_ui_funds_label('CPCCFundsEstimate'); ?></th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($customers)) { ?>
        <tr class="cpad-empty-row">
          <td colspan="8">No customers found</td>
        </tr>
      <?php } else { foreach ($customers as $row) {
        $name = dol_escape_htmltag($row['name']);
        $balanceUrl = dol_buildpath('/chinaplanetcustomercenter/balance.php',1).'?socid='.(int)$row['id'];
        $financeUrl = dol_buildpath('/chinaplanetcustomercenter/admin/financial.php',1).'?socid='.(int)$row['id'];
        $lateInvoiceUrl = dol_buildpath('/compta/facture/list.php',1).'?search_status=1&search_socid='.(int)$row['id'].'&search_datelimit_end='.urlencode($row['as_of']);
      ?>
        <tr>
          <td data-label="Customer"><span class="cpad-cell-value"><a href="<?php echo $balanceUrl; ?>"><?php echo $name; ?> <small>(<?php echo (int)$row['id']; ?>)</small></a></span><?php if ($showCurrentManaged) { ?><details><summary><?php echo cpcc_ui_funds_label('CPCCFundsPortfolioLink'); ?></summary><?php echo cpcc_ui_managed_funds($row['managed_position'],array('asof'=>$asOf)); ?></details><?php } ?></td>
          <td data-label="<?php echo cpcc_ui_funds_label('CPCCFundsInvoiceCreditUsd'); ?>"><span class="cpad-cell-value"><b><?php echo cpcc_portal_money($row['usd_total']); ?></b></span></td>
          <td data-label="<?php echo cpcc_ui_funds_label('CPCCFundsInvoiceCredit'); ?>"><span class="cpad-cell-value"><?php echo cpad_portfolio_currency_text($row['summary'], 'available'); ?></span></td>
          <td data-label="Obligations"><span class="cpad-cell-value"><?php echo dol_escape_htmltag(cpad_portfolio_currency_text($row['summary'], 'obligations')); ?></span></td>
          <td data-label="Unpaid"><span class="cpad-cell-value"><?php echo cpad_portfolio_currency_text($row['summary'], 'unpaid_invoices'); ?></span></td>
          <td data-label="Overdue"><span class="cpad-cell-value">
            <a href="<?php echo $lateInvoiceUrl; ?>"><b><?php echo (int) $row['overdue_count']; ?></b></a>
            <?php if ($row['overdue_count'] > 0) { echo ' · '.cpad_portfolio_currency_text($row['overdue_by_currency'], 'amount'); } ?>
          </span></td>
          <td data-label="<?php echo cpcc_ui_funds_label('CPCCFundsEstimate'); ?>" class="<?php echo $row['needs_funding'] ? 'warn' : ''; ?>">
            <span class="cpad-cell-value"><b><?php echo dol_escape_htmltag(cpad_portfolio_currency_text($row['summary'], 'required_funding')); ?></b></span>
          </td>
          <td data-label="Action"><span class="cpad-cell-value cpad-cell-actions">
            <a href="<?php echo $financeUrl; ?>">Admin Entry</a>
            <span aria-hidden="true">·</span>
            <a href="<?php echo $balanceUrl; ?>">Statement</a>
          </span></td>
        </tr>
      <?php }} ?>
      </tbody>
    </table>
  </div>
</section>
<?php } ?>
</div>

<?php
cpcc_admin_shell_close();
llxFooter();
$db->close();
?>
