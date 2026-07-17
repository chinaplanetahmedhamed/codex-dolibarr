<?php
if (!isset($_SERVER['QUERY_STRING'])) $_SERVER['QUERY_STRING'] = '';
require_once __DIR__.'/lib/main.lib.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/chinaplanetcustomercenter/lib/admin_shell.lib.php';
require_once DOL_DOCUMENT_ROOT.'/custom/chinaplanetcustomercenter/lib/portal.lib.php';

cpcc_portal_require_access($user);
$scope = cpcc_portal_user_scope($user);
$isExternal = !empty($scope['is_external']);
$socid = cpcc_portal_customer_scope_id($db, $user, GETPOSTINT('socid'));
if ($isExternal && $socid <= 0) accessforbidden();

$soc = new Societe($db);
if ($socid > 0) $soc->fetch($socid);

$sql = "SELECT COALESCE(SUM(total_ttc),0) total_invoiced FROM ".MAIN_DB_PREFIX."facture WHERE fk_statut IN (1,2)";
if ($socid > 0) $sql .= " AND fk_soc=".(int)$socid;
$resql = $db->query($sql);
$obj = $resql ? $db->fetch_object($resql) : null;
$totalInvoiced = $obj ? (float)$obj->total_invoiced : 0;

$sql = "SELECT COALESCE(SUM(pf.amount),0) total_paid FROM ".MAIN_DB_PREFIX."paiement_facture pf JOIN ".MAIN_DB_PREFIX."paiement p ON p.rowid=pf.fk_paiement JOIN ".MAIN_DB_PREFIX."facture f ON f.rowid=pf.fk_facture WHERE f.fk_statut IN (1,2)";
if ($socid > 0) $sql .= " AND f.fk_soc=".(int)$socid;
$resql = $db->query($sql);
$obj = $resql ? $db->fetch_object($resql) : null;
$totalPaid = $obj ? (float)$obj->total_paid : 0;
$balance = $totalInvoiced - $totalPaid;

$css = $isExternal ? array('/custom/chinaplanetcustomercenter/css/customer_dashboard.css') : array(cpcc_admin_shell_asset('/custom/chinaplanetcustomercenter/css/admin_dashboard.css'),cpcc_admin_shell_asset('/custom/chinaplanetcustomercenter/css/ux_fixes.css'));
llxHeader('', 'Financial Reports / التقارير المالية', '', '', 0, 0, array(), $css);
if ($isExternal) cpcc_portal_navigation($user, 'invoices');
else cpcc_admin_shell_open('financial', 'Financial Reports / التقارير المالية', 'ملخص الفواتير والمدفوعات حسب العميل', 'fa-chart-pie', array('socid'=>$socid));

print '<main class="cpcc-dashboard" dir="rtl">';
print '<header class="cpcc-page-head"><div><span class="cpcc-eyebrow">FINANCIAL REPORT</span><h1>الحالة المالية / Financial Status</h1><p>'.dol_escape_htmltag($socid > 0 ? $soc->name : 'All Customers / كل العملاء').'</p></div></header>';
print '<section class="cpcc-financial-cards">';
print '<div class="cpcc-card invoices"><span>إجمالي الفواتير / Total invoiced</span><strong>'.price($totalInvoiced).'</strong></div>';
print '<div class="cpcc-card received"><span>إجمالي المدفوع / Total paid</span><strong>'.price($totalPaid).'</strong></div>';
print '<div class="cpcc-card required"><span>الرصيد المستحق / Balance due</span><strong>'.price($balance).'</strong></div>';
print '</section>';

print '<section class="cpcc-section-head"><div><h2>الفواتير / Invoices</h2></div></section>';
$sql = "SELECT rowid,ref,total_ttc,datef,fk_statut FROM ".MAIN_DB_PREFIX."facture WHERE fk_statut IN (1,2)";
if ($socid > 0) $sql .= " AND fk_soc=".(int)$socid;
$sql .= " ORDER BY datef DESC";
$resql = $db->query($sql);
print '<div class="cpcc-table-wrap"><table class="cpcc-table"><thead><tr><th>المرجع / Ref</th><th>التاريخ / Date</th><th>القيمة / Amount</th><th>الحالة / Status</th><th>التفاصيل / Details</th></tr></thead><tbody>';
if ($resql && $db->num_rows($resql) > 0) {
    while ($obj = $db->fetch_object($resql)) {
        $url = cpcc_portal_scoped_url('/chinaplanetcustomercenter/invoice.php',$isExternal,$socid,array('id'=>(int)$obj->rowid));
        print '<tr><td>'.dol_escape_htmltag($obj->ref).'</td><td>'.dol_escape_htmltag(dol_print_date($db->jdate($obj->datef),'day')).'</td><td>'.price($obj->total_ttc).'</td><td>'.($obj->fk_statut==2?'مدفوعة / Paid':'مفتوحة / Unpaid').'</td><td><a href="'.dol_escape_htmltag($url).'">عرض / View</a></td></tr>';
    }
} else print '<tr><td colspan="5">لا توجد فواتير / No invoices.</td></tr>';
print '</tbody></table></div></main>';

if (!$isExternal) cpcc_admin_shell_close();
llxFooter();
