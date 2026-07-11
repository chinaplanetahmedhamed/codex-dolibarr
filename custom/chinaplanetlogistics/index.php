<?php
/**
 * Customer Dashboard for China Planet Logistics
 * External user detection: $user->socid > 0 means external user (Dolibarr standard)
 */
require "../../main.inc.php";
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';

$langs->loadLangs(array("companies", "orders", "sendings", "projects", "other"));

// Security check
if (empty($user->id) || (!$user->hasRight('chinaplanetlogistics', 'read') && empty($user->admin))) {
    accessforbidden();
}

// ============================================================
// EXTERNAL USER DETECTION - Dolibarr standard method
// In Dolibarr: $user->socid > 0 = external/portal user
// ============================================================
$socid = (int) $user->socid;
$is_external = ($socid > 0);

// Admin override: admin can see all data
if ($user->admin) {
    $is_external = false;
    $socid = 0;
}

// Build the WHERE clause for company filtering
$where_soc = "";
if ($is_external && $socid > 0) {
    $where_soc = " AND fk_soc = " . $socid;
} elseif ($is_external && $socid <= 0) {
    accessforbidden("No company linked to this user.");
}

/**
 * Color logic for countdowns
 */
if (!function_exists('cpl_color')) {
    function cpl_color($days) {
        if ($days === null) return '#95a5a6';
        if ($days < 0) return '#1a1a1a';
        if ($days < 3) return '#c0392b';
        if ($days < 7) return '#e67e22';
        if ($days < 10) return '#f39c12';
        if ($days < 14) return '#27ae60';
        return '#2ecc71';
    }
}

llxHeader('', 'China Planet Logistics - Dashboard');

print load_fiche_titre('Logistics Dashboard / لوحة تحكم الشحن', '', 'ship');

$today = date('Y-m-d');

// --- STATISTICS ---
$c_intransit = 0;
$res = $db->query("SELECT count(*) as total FROM ".MAIN_DB_PREFIX."chinaplanetlogistics WHERE fk_statut IN (1,2)".$where_soc);
if ($res) { $obj = $db->fetch_object($res); $c_intransit = (int)$obj->total; }

$c_arr14 = 0;
$res = $db->query("SELECT count(*) as total FROM ".MAIN_DB_PREFIX."chinaplanetlogistics WHERE date_eta BETWEEN '".$db->escape($today)."' AND '".date('Y-m-d', strtotime('+14 days'))."' AND fk_statut < 3".$where_soc);
if ($res) { $obj = $db->fetch_object($res); $c_arr14 = (int)$obj->total; }

$c_arrived = 0;
$res = $db->query("SELECT count(*) as total FROM ".MAIN_DB_PREFIX."chinaplanetlogistics WHERE fk_statut = 3".$where_soc);
if ($res) { $obj = $db->fetch_object($res); $c_arrived = (int)$obj->total; }

$ft_critical = 0;
$res = $db->query("SELECT count(*) as total FROM ".MAIN_DB_PREFIX."chinaplanetlogistics WHERE freetime_end BETWEEN '".$db->escape($today)."' AND '".date('Y-m-d', strtotime('+7 days'))."' AND fk_statut < 4".$where_soc);
if ($res) { $obj = $db->fetch_object($res); $ft_critical = (int)$obj->total; }

// --- FINANCIAL SUMMARY ---
$balance = 0;
$inv_count = 0;
$inv_unpaid = 0;
if ($socid > 0) {
    $sql_bal = "SELECT SUM(total_ttc) as total, COUNT(*) as cnt FROM ".MAIN_DB_PREFIX."facture WHERE fk_statut >= 1 AND fk_soc = " . $socid;
    $res_bal = $db->query($sql_bal);
    if ($res_bal) {
        $obj_bal = $db->fetch_object($res_bal);
        $balance = (float) $obj_bal->total;
        $inv_count = (int) $obj_bal->cnt;
    }
    $sql_unpaid = "SELECT COUNT(*) as cnt FROM ".MAIN_DB_PREFIX."facture WHERE fk_statut = 1 AND fk_soc = " . $socid;
    $res_unpaid = $db->query($sql_unpaid);
    if ($res_unpaid) {
        $obj_unpaid = $db->fetch_object($res_unpaid);
        $inv_unpaid = (int) $obj_unpaid->cnt;
    }
} elseif ($user->admin) {
    $sql_bal = "SELECT SUM(total_ttc) as total, COUNT(*) as cnt FROM ".MAIN_DB_PREFIX."facture WHERE fk_statut >= 1";
    $res_bal = $db->query($sql_bal);
    if ($res_bal) {
        $obj_bal = $db->fetch_object($res_bal);
        $balance = (float) $obj_bal->total;
        $inv_count = (int) $obj_bal->cnt;
    }
}

print '<style>
.cpl-grid { display: flex; gap: 16px; flex-wrap: wrap; margin-bottom: 24px; }
.cpl-card { flex: 1; min-width: 180px; padding: 20px; border-radius: 10px; text-align: center; color: #fff; box-shadow: 0 3px 10px rgba(0,0,0,0.12); }
.cpl-card .num { font-size: 32px; font-weight: 700; line-height: 1; }
.cpl-card .lbl { font-size: 13px; margin-top: 8px; font-weight: 600; }
.cpl-section-title { font-size: 16px; font-weight: 700; margin: 20px 0 10px 0; color: #2c3e50; border-left: 4px solid #c0392b; padding-left: 10px; }
</style>';

// --- FINANCIAL SUMMARY ---
print '<div class="cpl-section-title">Financial Summary / الملخص المالي</div>';
print '<div class="cpl-grid">';
print '<div class="cpl-card" style="background:linear-gradient(135deg,#8e44ad,#9b59b6);"><div class="num">'.price($balance).'</div><div class="lbl">Total Invoiced / إجمالي الفواتير</div></div>';
print '<div class="cpl-card" style="background:linear-gradient(135deg,#e67e22,#d35400);"><div class="num">'.$inv_unpaid.'</div><div class="lbl">Unpaid Invoices / فواتير غير مدفوعة</div></div>';
print '<div class="cpl-card" style="background:linear-gradient(135deg,#27ae60,#2ecc71);"><div class="num">'.$inv_count.'</div><div class="lbl">Total Invoices / عدد الفواتير</div></div>';
print '</div>';

// --- PROJECTS SECTION ---
print '<div class="cpl-section-title">Active Projects / المشاريع القائمة</div>';
print '<div class="div-table-responsive">';
print '<table class="liste centpercent">';
print '<tr class="liste_titre"><th>Ref</th><th>Project Title</th><th>Status</th><th>Date Start</th></tr>';

// dateo is stored as DATE type (string '2026-06-28'), not timestamp
// Use it directly without jdate()
$sql_prj = "SELECT rowid, ref, title, fk_statut, dateo FROM ".MAIN_DB_PREFIX."projet WHERE fk_statut = 1";
if ($socid > 0) {
    $sql_prj .= " AND fk_soc = " . $socid;
} elseif ($is_external) {
    $sql_prj .= " AND 1=0";
}
$sql_prj .= " ORDER BY dateo DESC LIMIT 10";

$res_prj = $db->query($sql_prj);
if ($res_prj && $db->num_rows($res_prj) > 0) {
    while($prj = $db->fetch_object($res_prj)) {
        print '<tr class="oddeven">';
        print '<td><a href="'.DOL_URL_ROOT.'/projet/card.php?id='.$prj->rowid.'">'.dol_escape_htmltag($prj->ref).'</a></td>';
        print '<td>'.dol_escape_htmltag($prj->title).'</td>';
        print '<td><span style="color:#27ae60;font-weight:bold;">Active</span></td>';
        // dateo is a DATE string - display directly
        print '<td>'.dol_escape_htmltag($prj->dateo).'</td>';
        print '</tr>';
    }
} else {
    print '<tr class="oddeven"><td colspan="4" class="center opacitymedium">No active projects found.</td></tr>';
}
print '</table></div>';

// --- CONTAINER STATUS ---
print '<div class="cpl-section-title">Container Status / حالة الحاويات</div>';
print '<div class="cpl-grid">';
print '<div class="cpl-card" style="background:linear-gradient(135deg,#34495e,#2c3e50);"><div class="num">'.$c_intransit.'</div><div class="lbl">In Transit / في البحر</div></div>';
print '<div class="cpl-card" style="background:linear-gradient(135deg,#27ae60,#2ecc71);"><div class="num">'.$c_arr14.'</div><div class="lbl">Arriving &lt; 14d</div></div>';
print '<div class="cpl-card" style="background:linear-gradient(135deg,#2980b9,#3498db);"><div class="num">'.$c_arrived.'</div><div class="lbl">Arrived / وصلت</div></div>';
print '<div class="cpl-card" style="background:linear-gradient(135deg,#c0392b,#e74c3c);"><div class="num">'.$ft_critical.'</div><div class="lbl">FT Critical (&lt;7d)</div></div>';
print '</div>';

// --- RECENT SHIPMENTS ---
print '<div class="cpl-section-title">Recent Shipments / آخر الشحنات</div>';
print '<div class="div-table-responsive">';
print '<table class="liste centpercent">';
print '<tr class="liste_titre"><th>Container No</th><th>ETA</th><th class="center">Arrival</th><th class="center">Free Time</th><th>Status</th></tr>';

$sql_shp = "SELECT rowid, container_no, date_eta, freetime_end, fk_statut FROM ".MAIN_DB_PREFIX."chinaplanetlogistics WHERE fk_statut < 4";
if ($socid > 0) {
    $sql_shp .= " AND fk_soc = " . $socid;
} elseif ($is_external) {
    $sql_shp .= " AND 1=0";
}
$sql_shp .= " ORDER BY date_eta ASC LIMIT 10";

$statuslabels = array(0=>'Inquiry', 1=>'Booking', 2=>'On Vessel', 3=>'Arrived', 4=>'Closed');
$res_shp = $db->query($sql_shp);
if ($res_shp && $db->num_rows($res_shp) > 0) {
    while($shp = $db->fetch_object($res_shp)) {
        $eta_days = (!empty($shp->date_eta) && $shp->date_eta != '0000-00-00') ? (int)ceil((strtotime($shp->date_eta) - time()) / 86400) : null;
        $ft_days = (!empty($shp->freetime_end) && $shp->freetime_end != '0000-00-00') ? (int)ceil((strtotime($shp->freetime_end) - time()) / 86400) : null;
        print '<tr class="oddeven">';
        print '<td><a href="'.dol_buildpath('/chinaplanetlogistics/card.php?id='.$shp->rowid, 1).'"><b>'.dol_escape_htmltag($shp->container_no).'</b></a></td>';
        print '<td>'.($shp->date_eta && $shp->date_eta != '0000-00-00' ? $shp->date_eta : '-').'</td>';
        print '<td class="center"><span style="display:inline-block;min-width:45px;padding:2px 6px;border-radius:10px;color:#fff;font-size:11px;background:'.cpl_color($eta_days).';">'.($eta_days !== null ? ($eta_days < 0 ? 'Arrived' : $eta_days.'d') : '-').'</span></td>';
        print '<td class="center"><span style="display:inline-block;min-width:45px;padding:2px 6px;border-radius:10px;color:#fff;font-size:11px;background:'.cpl_color($ft_days).';">'.($ft_days !== null ? $ft_days.'d' : '-').'</span></td>';
        print '<td>'.(isset($statuslabels[$shp->fk_statut]) ? $statuslabels[$shp->fk_statut] : '-').'</td>';
        print '</tr>';
    }
} else {
    print '<tr class="oddeven"><td colspan="5" class="center opacitymedium">No shipments found.</td></tr>';
}
print '</table></div>';

llxFooter();
$db->close();
?>
