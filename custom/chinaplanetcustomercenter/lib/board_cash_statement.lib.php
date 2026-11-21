<?php
require_once __DIR__.'/../../chinaplanetoperations/class/CustomerFinanceLink.class.php';
require_once __DIR__.'/../../chinaplanetoperations/class/CustomerAccountPolicy.class.php';

function cpcc_board_cash_totals(array $rows): array
{
    $empty = ['received'=>'0','spent'=>'0','balance'=>'0'];
    $base = $empty;
    $original = [];
    foreach ($rows as $row) {
        $key = $row['direction'];
        $currency = $row['currency'];
        $base[$key] = bcadd($base[$key], $row['base_minor'], 0);
        if (!isset($original[$currency])) $original[$currency] = $empty;
        $original[$currency][$key] = bcadd($original[$currency][$key], $row['original_minor'], 0);
    }
    $base['balance'] = bcsub($base['received'], $base['spent'], 0);
    foreach ($original as &$total) $total['balance'] = bcsub($total['received'], $total['spent'], 0);
    unset($total);
    ksort($original);
    return ['base'=>$base,'original'=>$original];
}

/** Finance filters use the same reviewed rows for display, print and download. */
function cpcc_board_cash_filter(array $rows, string $from, string $to, string $currency): array
{
    return array_values(array_filter($rows, static function (array $row) use ($from, $to, $currency) {
        return ($from === '' || $row['date'] >= $from) && ($to === '' || $row['date'] <= $to)
            && ($currency === '' || $currency === $row['currency']);
    }));
}

function cpcc_board_cash_statement($db, $conf, $user, bool $external): void
{
    global $langs;
    $langs->load('chinaplanetcustomercenter@chinaplanetcustomercenter');
    $t = static function ($key) use ($langs) { return dol_escape_htmltag($langs->trans($key)); };
    $escape = 'dol_escape_htmltag';
    $customer = $external ? (int)$user->socid : GETPOSTINT('socid');
    $projectValue = GETPOST('projectid', 'none');
    $format = GETPOST('format', 'none');
    $project = null;
    $policy = null;
    header('Cache-Control: private, no-store');
    if ($customer <= 0 || !is_string($projectValue) || !preg_match('/^(?:0|[1-9][0-9]{0,9})?$/D', $projectValue)
        || !in_array($format, ['', 'csv', 'print'], true)) cpcc_portal_access_denied($user, 'balance');
    $projectId = (int)$projectValue;

    try {
        if (!$external) {
            $policy = new CustomerAccountPolicy($db, $user, (int)$conf->entity);
            $policy->scope($customer, $projectId);
            if ($format !== '') $policy->requireRight('export');
        }
        if ($projectId > 0) {
            $project = cpcc_financial_statement_project($db, $customer, $projectId);
            if (!$project) cpcc_portal_access_denied($user, 'balance');
        }
    } catch (Throwable $error) {
        cpcc_portal_access_denied($user, 'balance');
    }

    $from = GETPOST('from', 'none');
    $to = GETPOST('to', 'none');
    foreach ([$from,$to] as $date) {
        if (!is_string($date) || ($date !== '' && (!preg_match('/^([0-9]{4})-([0-9]{2})-([0-9]{2})$/D', $date, $parts)
            || !checkdate((int)$parts[2], (int)$parts[3], (int)$parts[1])))) {
            http_response_code(400);
            print $t('CPCCBoardCashInvalidDates');
            return;
        }
    }
    if ($from !== '' && $to !== '' && $from > $to) {
        http_response_code(400);
        print $t('CPCCBoardCashInvalidDates');
        return;
    }
    $currency = GETPOST('currency', 'none');
    if (!is_string($currency) || ($currency !== '' && !preg_match('/^[A-Za-z]{3}$/D', $currency))) {
        http_response_code(400);
        print $t('CPCCBoardCashUnavailable');
        return;
    }
    $currency = strtoupper($currency);
    $finance = new CustomerFinanceLink($db, (int)$conf->entity, $conf->currency ?? 'USD');
    try {
        $all = $finance->rows($customer, $projectId, $policy);
    } catch (InternalTaskAccessException $error) {
        cpcc_portal_access_denied($user, 'balance');
    } catch (Throwable $error) {
        if ((int)$error->getCode() === 403) cpcc_portal_access_denied($user, 'balance');
        http_response_code(503);
        print $t('CPCCBoardCashUnavailable');
        return;
    }
    $projects = [];
    foreach ($all as $movement) $projects += $movement['projects'];
    asort($projects, SORT_NATURAL | SORT_FLAG_CASE);
    $rows = cpcc_board_cash_filter($all, $from, $to, $currency);
    $totals = cpcc_board_cash_totals($rows);
    $money = static function ($minor) { return CustomerAccountAmount::money($minor); };
    $rowLabel = static function ($row) use ($langs) {
        if ($row['kind'] === 'refund') return $langs->trans('CPCCBoardCashRefund');
        return $langs->trans($row['direction'] === 'received' ? 'CPCCBoardCashReceived' : 'CPCCBoardCashSpent');
    };
    $columns = ['CPCCBoardCashDate','CPCCBoardCashMovement','CPCCBoardCashAmount','CPCCBoardCashCurrency','CPCCBoardCashFx','CPCCBoardCashFxDate','CPCCBoardCashUsd'];

    if ($format === 'csv') {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="customer-cash-statement.csv"');
        $output = fopen('php://output', 'w');
        fwrite($output, "\xEF\xBB\xBF");
        fputcsv($output, array_map([$langs, 'trans'], $columns), ',', '"', '');
        foreach ($rows as $row) {
            fputcsv($output, [$row['date'],$rowLabel($row),$money($row['original_minor']),$row['currency'],$row['rate'],$row['rate_date'],$money($row['base_minor'])], ',', '"', '');
        }
        fclose($output);
        return;
    }

    $params = ['view'=>'board_cash'];
    if ($project) $params['projectid'] = $projectId;
    $filters = array_filter(['from'=>$from,'to'=>$to,'currency'=>$currency], static function ($v) { return $v !== ''; });
    $url = static function ($query) use ($external, $customer) {
        return dol_escape_htmltag(cpcc_portal_scoped_url('/chinaplanetcustomercenter/balance.php', $external, $customer, $query));
    };
    $assets = ['/custom/chinaplanetcustomercenter/css/customer_dashboard.css','/custom/chinaplanetcustomercenter/css/funding.css'];
    llxHeader('', $langs->trans('CPCCBoardCashTitle'), '', '', 0, 0, [], $assets);
    if ($external && $format !== 'print') cpcc_portal_navigation($user, 'balance');
    $dir = $langs->trans('DIRECTION') === 'rtl' ? 'rtl' : 'ltr';
    print '<main class="cpcc-dashboard" dir="'.$dir.'"><header class="cpcc-page-head"><div><h1>'.$t('CPCCBoardCashTitle').'</h1>';
    if ($project) print '<p>'.$escape($project->title).' · '.$escape($project->ref).'</p>';
    print '<p>'.$t('CPCCBoardCashCoverage').'</p></div></header>';
    if ($format !== 'print') {
        print '<div class="cpcc-balance-pills">';
        if ($project) print '<a href="'.$url(['view'=>'board_cash']).'">'.$t('CPCCAllProjectStatements').'</a>';
        if ($external || cpcc_portal_has_any_right($user, [['chinaplanetcustomercenter','read']])) {
            print '<a href="'.$url(['view'=>'invoice_credit']).'">'.$t('CPCCFundsHistory').'</a>';
        }
        print '</div>';
        print '<form method="get" action="'.$url([]).'" class="cpcc-balance-pills">';
        $language = function_exists('cpcc_ui_language_parameter') ? cpcc_ui_language_parameter() : GETPOST('lang', 'aZ09');
        if (!in_array($language, ['ar_SA','en_US'], true)) $language = '';
        foreach (['view'=>'board_cash'] + (!$external ? ['socid'=>$customer] : []) + ($language !== '' ? ['lang'=>$language] : []) as $key=>$value) {
            print '<input type="hidden" name="'.$escape($key).'" value="'.$escape((string)$value).'">';
        }
        if ($project || $projects) {
            print '<label>'.$t('CPCCBoardCashProject').'<select name="projectid"><option value="0">'.$t('CPCCBoardCashAllProjects').'</option>';
            if ($project) $projects[$projectId] = $project->ref.' · '.$project->title;
            foreach ($projects as $id=>$label) {
                print '<option value="'.(int)$id.'"'.((int)$id === $projectId ? ' selected' : '').'>'.$escape($label).'</option>';
            }
            print '</select></label>';
        }
        print '<label>'.$t('CPCCBoardCashFrom').'<input type="date" name="from" value="'.$escape($from).'"></label>';
        print '<label>'.$t('CPCCBoardCashTo').'<input type="date" name="to" value="'.$escape($to).'"></label>';
        print '<label>'.$t('CPCCBoardCashCurrency').'<select name="currency"><option value="">'.$t('CPCCStatementAllCurrencies').'</option>';
        foreach (array_unique(array_column($all, 'currency')) as $code) {
            print '<option value="'.$escape($code).'"'.($code === $currency ? ' selected' : '').'>'.$escape($code).'</option>';
        }
        print '</select></label><button type="submit" class="button">'.$t('CPCCBoardCashFilter').'</button></form>';
        print '<div class="cpcc-balance-pills"><a href="'.$url($params + $filters + ['format'=>'print']).'">'.$t('CPCCBoardCashPrint').'</a>';
        print '<a href="'.$url($params + $filters + ['format'=>'csv']).'">'.$t('CPCCBoardCashDownload').'</a></div>';
    }
    if ($rows) {
        $sections = [['USD · '.$t('CPCCBoardCashUsd'), $totals['base']]];
        foreach ($totals['original'] as $code=>$total) $sections[] = [$escape($code).' · '.$t('CPCCBoardCashOriginal'), $total];
        foreach ($sections as [$title,$total]) {
            print '<section class="cpcc-currency-block"><h2>'.$title.'</h2><dl>';
            foreach (['received'=>'CPCCBoardCashReceived','spent'=>'CPCCBoardCashSpent','balance'=>'CPCCBoardCashBalance'] as $key=>$label) {
                print '<dt>'.$t($label).'</dt><dd>'.$escape($money($total[$key])).'</dd>';
            }
            print '</dl></section>';
        }
    }
    print '<div class="cpcc-table-wrap"><table class="cpcc-table statement"><thead><tr>';
    foreach ($columns as $column) print '<th scope="col">'.$t($column).'</th>';
    print '</tr></thead><tbody>';
    if (!$rows) print '<tr><td colspan="7">'.$t('CPCCStatementNoMovements').'</td></tr>';
    foreach ($rows as $row) {
        print '<tr>';
        foreach ([$row['date'],$rowLabel($row),$money($row['original_minor']),$row['currency'],$row['rate'],$row['rate_date'],$money($row['base_minor'])] as $value) {
            print '<td>'.$escape($value).'</td>';
        }
        print '</tr>';
    }
    print '</tbody></table></div></main>';
    llxFooter();
}
