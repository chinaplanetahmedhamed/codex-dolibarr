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

llxHeader('', 'Invoices / الفواتير');
cpcc_portal_navigation($user, 'invoices');

print '<h2>Invoices / الفواتير</h2>';
print '<p>Customer-scoped invoices and payment status.</p>';

if ($scope['is_external'] && $socid <= 0) {
    print '<div class="warning">No linked customer account was found for this portal user.</div>';
} else {
    $sql = "SELECT rowid, ref, datef, total_ttc, paye, fk_statut, date_lim_reglement"
         . " FROM ".MAIN_DB_PREFIX."facture"
         . ($socid > 0 ? " WHERE fk_soc = ".(int) $socid : "")
         . " ORDER BY datef DESC, rowid DESC";
    $resql = $db->query($sql);

    print '<table class="noborder centpercent">';
    print '<tr class="liste_titre"><th>Invoice</th><th>Date</th><th>Due date</th><th>Total</th><th>Paid</th><th>Status</th></tr>';
    if ($resql && $db->num_rows($resql) > 0) {
        while ($obj = $db->fetch_object($resql)) {
            print '<tr>';
            print '<td>'.dol_escape_htmltag($obj->ref).'</td>';
            print '<td>'.(!empty($obj->datef) ? dol_print_date($obj->datef, 'day') : '-').'</td>';
            print '<td>'.(!empty($obj->date_lim_reglement) ? dol_print_date($obj->date_lim_reglement, 'day') : '-').'</td>';
            print '<td>'.cpcc_portal_money($obj->total_ttc).'</td>';
            print '<td>'.($obj->paye ? 'Yes' : 'No').'</td>';
            print '<td>'.(int) $obj->fk_statut.'</td>';
            print '</tr>';
        }
    } else {
        print '<tr><td colspan="6" class="center opacitymedium">No invoices found.</td></tr>';
    }
    print '</table>';
}

llxFooter();
