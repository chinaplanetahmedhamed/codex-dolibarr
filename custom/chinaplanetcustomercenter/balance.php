<?php
if (!isset($_SERVER['QUERY_STRING'])) $_SERVER['QUERY_STRING'] = '';

$res = 0;
if (!$res && file_exists("../../main.inc.php")) $res = @include "../../main.inc.php";
if (!$res && file_exists("../../../main.inc.php")) $res = @include "../../../main.inc.php";
if (!$res) die("Include of main fails");

require_once __DIR__.'/lib/portal.lib.php';

cpcc_portal_require_access($user);
$scope = cpcc_portal_user_scope($user);
$socid = $scope['is_external'] ? (int) $scope['socid'] : GETPOSTINT('socid');

llxHeader('', 'Balance / الرصيد');
cpcc_portal_navigation($user, 'balance');

print '<h2>Balance / الرصيد</h2>';
print '<p>Customer-scoped balance summary based on invoices.</p>';

if ($scope['is_external'] && $socid <= 0) {
    print '<div class="warning">No linked customer account was found for this portal user.</div>';
} else {
    $sql = "SELECT COUNT(*) as nb, COALESCE(SUM(total_ttc),0) as total, COALESCE(SUM(CASE WHEN paye = 1 THEN total_ttc ELSE 0 END),0) as paid"
         . " FROM ".MAIN_DB_PREFIX."facture";
    if ($socid > 0) {
        $sql .= " WHERE fk_soc = ".(int) $socid." AND fk_statut > 0";
    } else {
        $sql .= " WHERE fk_statut > 0";
    }
    $resql = $db->query($sql);
    $obj = $resql ? $db->fetch_object($resql) : null;
    $nb = $obj ? (int) $obj->nb : 0;
    $total = $obj ? (float) $obj->total : 0;
    $paid = $obj ? (float) $obj->paid : 0;
    $due = max(0, $total - $paid);

    print '<div class="summary" style="margin-top:15px">';
    print '<div class="sc"><div class="n">'.cpcc_portal_money($total).'</div><div class="l">Invoiced</div></div>';
    print '<div class="sc"><div class="n">'.cpcc_portal_money($paid).'</div><div class="l">Paid</div></div>';
    print '<div class="sc"><div class="n">'.cpcc_portal_money($due).'</div><div class="l">Due</div></div>';
    print '<div class="sc"><div class="n">'.(int) $nb.'</div><div class="l">Invoices</div></div>';
    print '</div>';

    print '<p>Balance shown here is derived from invoice totals and payment status. For a full accounting ledger view, use the internal admin session.</p>';
}

llxFooter();
