<?php
require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/paiement/class/paiement.class.php';

llxHeader('', 'Financial Reports / التقارير المالية');

$socid = $user->socid;
$is_external = ($socid > 0);

if (!$is_external && GETPOST('socid', 'int')) {
    $socid = GETPOST('socid', 'int');
}

if ($socid <= 0 && $is_external) {
    accessforbidden();
}

$soc = new Societe($db);
if ($socid > 0) {
    $soc->fetch($socid);
}

print load_fiche_titre('Financial Status / الحالة المالية: ' . ($socid > 0 ? $soc->nom : 'All Customers'));

// Calculate Totals
$sql = "SELECT SUM(total_ttc) as total_invoiced FROM ".MAIN_DB_PREFIX."facture WHERE fk_statut IN (1,2)";
if ($socid > 0) $sql .= " AND fk_soc = " . $socid;
$resql = $db->query($sql);
$obj = $db->fetch_object($resql);
$total_invoiced = $obj->total_invoiced;

$sql = "SELECT SUM(p.amount) as total_paid FROM ".MAIN_DB_PREFIX."paiement as p";
$sql .= " JOIN ".MAIN_DB_PREFIX."paiement_facture as pf ON p.rowid = pf.fk_paiement";
$sql .= " JOIN ".MAIN_DB_PREFIX."facture as f ON pf.fk_facture = f.rowid";
$sql .= " WHERE f.fk_statut IN (1,2)";
if ($socid > 0) $sql .= " AND f.fk_soc = " . $socid;
$resql = $db->query($sql);
$obj = $db->fetch_object($resql);
$total_paid = $obj->total_paid;

$balance = $total_invoiced - $total_paid;

print '<div class="div-table-responsive">';
print '<table class="border" width="100%">';
print '<tr style="font-size: 1.2em; font-weight: bold;">';
print '<td width="33%" class="center">Total Invoiced / إجمالي الفواتير<br><span style="color: #2980b9;">'.price($total_invoiced).'</span></td>';
print '<td width="33%" class="center">Total Paid / إجمالي المدفوعات<br><span style="color: #27ae60;">'.price($total_paid).'</span></td>';
print '<td width="33%" class="center">Balance Due / الرصيد المتبقي<br><span style="color: '.($balance > 0 ? '#c0392b' : '#27ae60').';">'.price($balance).'</span></td>';
print '</tr>';
print '</table>';
print '</div>';

print '<br>';

// List Invoices
print '<h3>Invoices / الفواتير</h3>';
$sql = "SELECT rowid, ref, total_ttc, datef, fk_statut FROM ".MAIN_DB_PREFIX."facture WHERE fk_statut IN (1,2)";
if ($socid > 0) $sql .= " AND fk_soc = " . $socid;
$sql .= " ORDER BY datef DESC";

$resql = $db->query($sql);
print '<table class="tagtable liste">';
print '<tr class="liste_titre"><td>Ref</td><td>Date</td><td align="right">Amount</td><td align="center">Status</td></tr>';
if ($resql) {
    while ($obj = $db->fetch_object($resql)) {
        print '<tr class="oddeven">';
        print '<td>'.$obj->ref.'</td>';
        print '<td>'.dol_print_date($db->jdate($obj->datef), 'day').'</td>';
        print '<td align="right">'.price($obj->total_ttc).'</td>';
        print '<td align="center">'.($obj->fk_statut == 2 ? 'Paid' : 'Unpaid').'</td>';
        print '</tr>';
    }
}
print '</table>';

llxFooter();
