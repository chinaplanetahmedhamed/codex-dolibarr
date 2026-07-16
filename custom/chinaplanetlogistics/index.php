<?php
/**
 * Customer Dashboard for China Planet Logistics
 * External user detection: $user->socid > 0 means external user (Dolibarr standard)
 */
require_once __DIR__.'/lib/main.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/custom/chinaplanetcustomercenter/lib/admin_shell.lib.php';
require_once DOL_DOCUMENT_ROOT.'/custom/chinaplanetcustomercenter/lib/portal.lib.php';

$langs->loadLangs(array("companies", "orders", "sendings", "projects", "other"));

// Security check
if (empty($user->id)) {
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

llxHeader('', 'China Planet Logistics - Dashboard', '', '', 0, 0, array(), $is_external ? array('/custom/chinaplanetcustomercenter/css/customer_dashboard.css') : array(cpcc_admin_shell_asset('/custom/chinaplanetcustomercenter/css/admin_dashboard.css')));

if ($is_external) { cpcc_portal_navigation($user,'shipments'); print load_fiche_titre('Logistics Dashboard / لوحة تحكم الشحن', '', 'ship'); }
else cpcc_admin_shell_open('tracking', 'Shipment Tracking / متابعة الشحن', 'لوحة مواعيد الوصول والحاويات والتنبيهات التشغيلية', 'fa-ship', array('socid'=>$socid));

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

print '<style>
.cpl-grid { display: flex; gap: 16px; flex-wrap: wrap; margin-bottom: 24px; }
.cpl-card { flex: 1; min-width: 180px; padding: 20px; border-radius: 10px; text-align: center; color: #fff!important; box-shadow: 0 3px 10px rgba(0,0,0,0.12); text-decoration:none; transition:transform .18s,box-shadow .18s; }
.cpl-card:hover,.cpl-card:focus{transform:translateY(-3px);box-shadow:0 8px 20px rgba(0,0,0,.2);outline:3px solid #83e8ff;outline-offset:2px}
.cpl-card .num { font-size: 32px; font-weight: 700; line-height: 1; }
.cpl-card .lbl { font-size: 13px; margin-top: 8px; font-weight: 600; }
.cpl-section-title { font-size: 16px; font-weight: 700; margin: 20px 0 10px 0; color: #2c3e50; border-left: 4px solid #c0392b; padding-left: 10px; }
.cpl-section-head{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap}.cpl-show-all{padding:9px 14px;border-radius:8px;background:#0d5d7d;color:#fff!important;text-decoration:none;font-weight:700}.cpl-show-all:hover,.cpl-show-all:focus{background:#08749a;outline:3px solid #83e8ff}
</style>';

// --- CONTAINER STATUS ---
print '<div class="cpl-section-head"><div class="cpl-section-title">Container Status / حالة الحاويات</div><a class="cpl-show-all" href="'.dol_buildpath('/chinaplanetlogistics/list.php',1).($socid>0?'?socid='.(int)$socid:'').'">عرض كل الشحنات / Show all shipments</a></div>';
print '<div class="cpl-grid">';
$filterBase=dol_buildpath('/chinaplanetlogistics/list.php',1).'?'.($socid>0?'socid='.(int)$socid.'&':'');
print '<a class="cpl-card" href="'.$filterBase.'status=intransit" style="background:linear-gradient(135deg,#34495e,#2c3e50);"><div class="num">'.$c_intransit.'</div><div class="lbl">In Transit / في البحر</div></a>';
print '<a class="cpl-card" href="'.$filterBase.'status=arriving" style="background:linear-gradient(135deg,#177b42,#23a85b);"><div class="num">'.$c_arr14.'</div><div class="lbl">Arriving &lt; 14d / وصول خلال 14 يوم</div></a>';
print '<a class="cpl-card" href="'.$filterBase.'status=arrived" style="background:linear-gradient(135deg,#176c9d,#258bc2);"><div class="num">'.$c_arrived.'</div><div class="lbl">Arrived / وصلت</div></a>';
print '<a class="cpl-card" href="'.$filterBase.'status=critical" style="background:linear-gradient(135deg,#a92d22,#d53b2e);"><div class="num">'.$ft_critical.'</div><div class="lbl">FT Critical (&lt;7d) / وقت حر حرج</div></a>';
print '</div>';

// --- RECENT SHIPMENTS ---
print '<div class="cpl-section-title">Recent Shipments / آخر الشحنات</div>';
print '<div class="div-table-responsive">';
print '<table class="liste centpercent">';
print '<tr class="liste_titre"><th>Container No / الحاوية</th><th>Customer / العميل</th><th>ETA / الوصول</th><th class="center">Arrival / الوصول</th><th class="center">Free Time / الوقت الحر</th><th>Status / الحالة</th></tr>';

$sql_shp = "SELECT t.rowid,t.container_no,t.date_eta,t.freetime_end,t.fk_statut,s.nom customer_name FROM ".MAIN_DB_PREFIX."chinaplanetlogistics t LEFT JOIN ".MAIN_DB_PREFIX."societe s ON s.rowid=t.fk_soc WHERE t.fk_statut < 4";
if ($socid > 0) {
    $sql_shp .= " AND t.fk_soc = " . $socid;
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
        print '<td><a href="'.dol_buildpath('/chinaplanetlogistics/card.php', 1).'?id='.(int)$shp->rowid.'"><b>'.dol_escape_htmltag($shp->container_no).'</b></a></td>';
        print '<td>'.dol_escape_htmltag($shp->customer_name ?: '-').'</td>';
        print '<td>'.($shp->date_eta && $shp->date_eta != '0000-00-00' ? $shp->date_eta : '-').'</td>';
        print '<td class="center"><span style="display:inline-block;min-width:45px;padding:2px 6px;border-radius:10px;color:#fff;font-size:11px;background:'.cpl_color($eta_days).';">'.($eta_days !== null ? ($eta_days < 0 ? 'Arrived' : $eta_days.'d') : '-').'</span></td>';
        print '<td class="center"><span style="display:inline-block;min-width:45px;padding:2px 6px;border-radius:10px;color:#fff;font-size:11px;background:'.cpl_color($ft_days).';">'.($ft_days !== null ? $ft_days.'d' : '-').'</span></td>';
        print '<td>'.(isset($statuslabels[$shp->fk_statut]) ? $statuslabels[$shp->fk_statut] : '-').'</td>';
        print '</tr>';
    }
} else {
    print '<tr class="oddeven"><td colspan="6" class="center opacitymedium">No shipments found / لا توجد شحنات.</td></tr>';
}
print '</table></div>';

if (!$is_external) cpcc_admin_shell_close();
llxFooter();
$db->close();
?>
