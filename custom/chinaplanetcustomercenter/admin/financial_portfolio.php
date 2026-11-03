<?php
if (!isset($_SERVER['QUERY_STRING'])) $_SERVER['QUERY_STRING'] = '';
require_once __DIR__.'/../lib/main.lib.php';
require_once __DIR__.'/../lib/admin_shell.lib.php';
require_once __DIR__.'/../lib/financial.lib.php';
require_once __DIR__.'/../lib/portal.lib.php';

if (empty($user->id) || !empty($user->socid) || (!$user->admin && !$user->hasRight('chinaplanetcustomercenter', 'write'))) {
    accessforbidden();
}

if (!cpcc_financial_schema_ready($db)) {
    accessforbidden('Financial tables are not installed. Re-enable the module or run the module upgrade first.');
}

function cpad_portfolio_currency_text($summary, $field = 'amount')
{
    $parts = array();
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
$asOf = GETPOST('asof', 'alphanohtml');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $asOf)) {
    $asOf = date('Y-m-d');
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
$customerResult = $db->query($customerSql);
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

        $customers[] = array(
            'id' => $customerId,
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
    // Prioritize customers with a shortfall without ranking unlike currencies.
    if ($left['needs_funding'] !== $right['needs_funding']) {
        return $left['needs_funding'] ? -1 : 1;
    }
    $nameOrder = strcasecmp($left['name'], $right['name']);
    return $nameOrder !== 0 ? $nameOrder : ($left['id'] <=> $right['id']);
}
);

$globalUsd = cpcc_financial_total_usd($portfolioSummary);

if (empty($customers)) {
    $customers = array();
}

llxHeader('', 'المركز المالي', '', '', 0, 0, array(), array(cpcc_admin_shell_asset('/custom/chinaplanetcustomercenter/css/admin_dashboard.css'), cpcc_admin_shell_asset('/custom/chinaplanetcustomercenter/css/ux_fixes.css')));
cpcc_admin_shell_open('financial', 'المركز المالي', 'Overview totals and per-customer funding status', 'fa-chart-pie', array());
?>

<div class="cpad-finance-portfolio-theme">
<div class="cpad-finance-lead">
  <p class="opacitymedium">عرض إجمالي أرقام التمويل لجميع العملاء / all-customer finance roll-up and late-payment visibility</p>
  <a class="cpad-primary-action" href="<?php echo dol_buildpath('/chinaplanetcustomercenter/admin/financial.php', 1); ?>"><span class="fas fa-wallet" aria-hidden="true"></span><b>Finance Allocation Center</b><small>مركز تخصيص الأموال والتسويات الاستثنائية</small></a>
</div>

<form method="get" class="cpad-context-form">
  <div class="cpad-context-fields">
    <label>
      <span>As of / حتى تاريخ</span>
      <input type="date" name="asof" value="<?php echo dol_escape_htmltag($asOf); ?>">
    </label>
    <button class="button" type="submit">Apply / تطبيق</button>
  </div>
</form>

<section class="cpad-kpis">
  <div class="cpad-kpi"><b><?php echo $totalCustomers; ?></b><span>Total Customers / إجمالي العملاء</span><small>customers with customer profile / عملاء نشطون</small></div>
  <div class="cpad-kpi danger"><b><?php echo $customersLate; ?></b><span>Customers With Late Invoices / عملاء متأخرون</span><small>due date already passed / فواتير متأخرة</small></div>
  <div class="cpad-kpi gold"><b><?php echo $customersNeedFunds; ?></b><span>Customers Needing Funds / عملاء يحتاجون تمويل</span><small>required funding > 0 / يحتاجون تمويل إضافي</small></div>
  <div class="cpad-kpi"><b><?php echo $totalUnpaidInvoices; ?></b><span>Unpaid Invoices / فواتير غير مدفوعة</span><small>Total count / إجمالي عدد الفواتير</small></div>
</section>
<section class="cpad-kpis">
  <div class="cpad-kpi"><b><?php echo $totalOverdueInvoices; ?></b><span>Overdue Open Invoices / فواتير متأخرة</span><small>overdue count / عدد المتأخر</small></div>
  <div class="cpad-kpi"><b><?php echo cpcc_portal_money($globalUsd['total']); ?></b><span>All Customer Cash (USD) / إجمالي رصيد العملاء (USD)</span><small>exchange-rate valuation only / عرض تحويلي</small></div>
  <div class="cpad-kpi"><b><?php echo dol_escape_htmltag(cpad_portfolio_currency_text($portfolioSummary, 'supplier_remaining')); ?></b><span>Supplier Remaining / بقايا الموردين</span><small>approved supplier obligations / التزامات الموردين</small></div>
  <div class="cpad-kpi"><b><?php echo dol_escape_htmltag(cpad_portfolio_currency_text($portfolioSummary, 'future_services')); ?></b><span>Future Services / الخدمات المتوقعة</span><small>approved future service commitments / التزامات الخدمات</small></div>
</section>

<section class="cpad-panel">
  <div class="cpad-title"><span>Finance Totals by Currency / إجمالي التمويل حسب العملة</span><small>Aggregated without mixing currencies / تمثيل بكل عملة</small></div>
  <div class="cpad-table-scroll">
    <table class="cpad-table cpad-responsive-table cpad-finance-totals-table">
      <thead>
        <tr>
          <th>Currency / العملة</th>
          <th>Available / متاح</th>
          <th>Obligations / إجمالي الالتزامات</th>
          <th>Unpaid Invoices / الفواتير غير المدفوعة</th>
          <th>Overdue Invoices / الفواتير المتأخرة</th>
          <th>Required Funding / التمويل المطلوب</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($portfolioSummary as $currency => $totals) { ?>
        <tr>
          <td data-label="Currency / العملة"><span class="cpad-cell-value"><b><?php echo dol_escape_htmltag($currency); ?></b></span></td>
          <td data-label="Available / متاح"><span class="cpad-cell-value"><?php echo cpcc_portal_money($totals['available']); ?></span></td>
          <td data-label="Obligations / إجمالي الالتزامات"><span class="cpad-cell-value"><?php echo cpcc_portal_money($totals['obligations']); ?></span></td>
          <td data-label="Unpaid Invoices / الفواتير غير المدفوعة"><span class="cpad-cell-value"><?php echo cpcc_portal_money($totals['unpaid_invoices']); ?></span></td>
          <td data-label="Overdue Invoices / الفواتير المتأخرة"><span class="cpad-cell-value"><?php echo cpcc_portal_money(isset($portfolioOverdueByCurrency[$currency]) ? $portfolioOverdueByCurrency[$currency] : 0); ?></span></td>
          <td data-label="Required Funding / التمويل المطلوب"><span class="cpad-cell-value"><?php echo cpcc_portal_money($totals['required_funding']); ?></span></td>
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
  <div class="cpad-title"><span>Customer Finance Matrix / مصفوفة تمويل العملاء</span><small>Link customer name for Customer Statement / اسم العميل يفتح كشف الحساب</small></div>
  <div class="cpad-table-scroll">
    <table class="cpad-table cpad-responsive-table cpad-customer-finance-table">
      <thead>
        <tr>
          <th>Customer / العميل</th>
          <th>Available (USD) / متاح (USD)</th>
          <th>Available by Currency / متاح حسب العملة</th>
          <th>Obligations / الالتزامات</th>
          <th>Unpaid / غير مدفوع</th>
          <th>Overdue / متأخر</th>
          <th>Required Funding / التمويل المطلوب</th>
          <th>Action / إجراء</th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($customers)) { ?>
        <tr class="cpad-empty-row">
          <td colspan="8">No customers found / لا يوجد عملاء.</td>
        </tr>
      <?php } else { foreach ($customers as $row) {
        $name = dol_escape_htmltag($row['name']);
        $balanceUrl = dol_buildpath('/chinaplanetcustomercenter/balance.php',1).'?socid='.(int)$row['id'];
        $financeUrl = dol_buildpath('/chinaplanetcustomercenter/admin/financial.php',1).'?socid='.(int)$row['id'];
        $lateInvoiceUrl = dol_buildpath('/compta/facture/list.php',1).'?search_status=1&search_socid='.(int)$row['id'].'&search_datelimit_end='.urlencode($row['as_of']);
      ?>
        <tr>
          <td data-label="Customer / العميل"><span class="cpad-cell-value"><a href="<?php echo $balanceUrl; ?>"><?php echo $name; ?> <small>(<?php echo (int)$row['id']; ?>)</small></a></span></td>
          <td data-label="Available (USD) / متاح (USD)"><span class="cpad-cell-value"><b><?php echo cpcc_portal_money($row['usd_total']); ?></b></span></td>
          <td data-label="Available by Currency / متاح حسب العملة"><span class="cpad-cell-value"><?php echo cpad_portfolio_currency_text($row['summary'], 'available'); ?></span></td>
          <td data-label="Obligations / الالتزامات"><span class="cpad-cell-value"><?php echo dol_escape_htmltag(cpad_portfolio_currency_text($row['summary'], 'obligations')); ?></span></td>
          <td data-label="Unpaid / غير مدفوع"><span class="cpad-cell-value"><?php echo cpad_portfolio_currency_text($row['summary'], 'unpaid_invoices'); ?></span></td>
          <td data-label="Overdue / متأخر"><span class="cpad-cell-value">
            <a href="<?php echo $lateInvoiceUrl; ?>"><b><?php echo (int) $row['overdue_count']; ?></b></a>
            <?php if ($row['overdue_count'] > 0) { echo ' · '.cpad_portfolio_currency_text($row['overdue_by_currency'], 'amount'); } ?>
          </span></td>
          <td data-label="Required Funding / التمويل المطلوب" class="<?php echo $row['needs_funding'] ? 'warn' : ''; ?>">
            <span class="cpad-cell-value"><b><?php echo dol_escape_htmltag(cpad_portfolio_currency_text($row['summary'], 'required_funding')); ?></b></span>
          </td>
          <td data-label="Action / إجراء"><span class="cpad-cell-value cpad-cell-actions">
            <a href="<?php echo $financeUrl; ?>">Admin Entry / تسجيل مالي</a>
            <span aria-hidden="true">·</span>
            <a href="<?php echo $balanceUrl; ?>">Statement / كشف حساب</a>
          </span></td>
        </tr>
      <?php }} ?>
      </tbody>
    </table>
  </div>
</section>
</div>

<?php
cpcc_admin_shell_close();
llxFooter();
$db->close();
?>

