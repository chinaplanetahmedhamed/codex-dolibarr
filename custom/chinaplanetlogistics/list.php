<?php
/**
 * Customer Shipment List for China Planet Logistics
 * External user detection: $user->socid > 0 means external user (Dolibarr standard)
 */
require "../../main.inc.php";
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/custom/chinaplanetcustomercenter/lib/portal.lib.php';

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

// Admin override
if ($user->admin) {
    $is_external = false;
    $socid = 0;
}
$canwrite = (!$is_external && ($user->admin || $user->hasRight('chinaplanetlogistics', 'write') || $user->hasRight('chinaplanetcustomercenter', 'write')));

$action = GETPOST('action', 'alpha');
$search_container = GETPOST('search_container', 'alpha');
$search_bl = GETPOST('search_bl', 'alpha');

/**
 * Color logic for countdowns
 */
if (!function_exists('cpl_color_list')) {
    function cpl_color_list($days) {
        if ($days === null) return '#95a5a6';
        if ($days < 0) return '#1a1a1a';
        if ($days < 3) return '#c0392b';
        if ($days < 7) return '#e67e22';
        if ($days < 10) return '#f39c12';
        if ($days < 14) return '#27ae60';
        return '#2ecc71';
    }
}

llxHeader('', 'China Planet Logistics - Shipment List');
cpcc_portal_navigation($user, 'shipments');

$newbtn = '';
if ($canwrite) {
    $newbtn = '<a class="butActionNew" href="'.dol_buildpath('/chinaplanetlogistics/card.php?action=create', 1).'"><span class="fa fa-plus-circle valignmiddle btnTitle-icon"></span></a>';
}

print load_fiche_titre('Shipment List / قائمة الشحنات', $newbtn, 'ship');

$statuslabels = array(0=>'Inquiry', 1=>'Booking', 2=>'On Vessel', 3=>'Arrived', 4=>'Closed');

print '<form method="GET" action="'.$_SERVER["PHP_SELF"].'">';
print '<div class="div-table-responsive">';
print '<table class="tagtable liste">';
print '<tr class="liste_titre_filter">';
print '<td class="liste_titre"><input type="text" class="flat maxwidth100" name="search_container" value="'.dol_escape_htmltag($search_container).'" placeholder="Container No"></td>';
print '<td class="liste_titre"><input type="text" class="flat maxwidth100" name="search_bl" value="'.dol_escape_htmltag($search_bl).'" placeholder="BL No"></td>';
print '<td class="liste_titre" colspan="5"></td>';
print '<td class="liste_titre center"><input type="submit" class="button small" value="Search"></td>';
print '</tr>';

print '<tr class="liste_titre">';
print '<th>Container No</th>';
print '<th>BL No</th>';
print '<th>Ref</th>';
if (!$is_external) print '<th>Customer</th>';
print '<th>Project</th>';
print '<th>ETA</th>';
print '<th class="center">Arrival</th>';
print '<th>Free Time End</th>';
print '<th class="center">Free Time</th>';
print '<th>Status</th>';
if (!$is_external) print '<th class="center">Actions</th>';
print '</tr>';

$sql = "SELECT t.rowid, t.ref, t.container_no, t.bl_no, t.date_eta, t.freetime_end, t.fk_statut, s.nom as customer_name, p.title as project_title, p.ref as project_ref";
$sql.= " FROM ".MAIN_DB_PREFIX."chinaplanetlogistics as t";
$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON t.fk_soc = s.rowid";
$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."projet as p ON t.fk_project = p.rowid";
$sql.= " WHERE 1 = 1";

// STRICT FILTERING: external users only see their company's data
if ($is_external && $socid > 0) {
    $sql.= " AND t.fk_soc = " . $socid;
} elseif ($is_external) {
    $sql.= " AND 1=0";
}

if ($search_container) $sql.= " AND t.container_no LIKE '%".$db->escape($search_container)."%'";
if ($search_bl) $sql.= " AND t.bl_no LIKE '%".$db->escape($search_bl)."%'";

$sql.= " ORDER BY t.date_eta ASC";

$colspan_empty = $is_external ? '9' : '11';

$resql = $db->query($sql);
if ($resql) {
    $num = $db->num_rows($resql);
    if ($num == 0) {
        print '<tr class="oddeven"><td colspan="'.$colspan_empty.'" class="center opacitymedium">No shipments found.</td></tr>';
    }
    while ($obj = $db->fetch_object($resql)) {
        $eta_days = null;
        if (!empty($obj->date_eta) && $obj->date_eta != '0000-00-00') {
            $eta_days = (int)ceil((strtotime($obj->date_eta) - time()) / 86400);
        }
        $ft_days = null;
        if (!empty($obj->freetime_end) && $obj->freetime_end != '0000-00-00') {
            $ft_days = (int)ceil((strtotime($obj->freetime_end) - time()) / 86400);
        }
        $eta_color = cpl_color_list($eta_days);
        $free_color = cpl_color_list($ft_days);

        print '<tr class="oddeven">';
        print '<td><b>'.dol_escape_htmltag($obj->container_no).'</b></td>';
        print '<td>'.dol_escape_htmltag($obj->bl_no).'</td>';
        print '<td><a href="'.dol_buildpath('/chinaplanetlogistics/card.php?id='.$obj->rowid, 1).'">'.dol_escape_htmltag($obj->ref).'</a></td>';
        if (!$is_external) print '<td>'.dol_escape_htmltag($obj->customer_name).'</td>';
        print '<td>'.($obj->project_ref ? dol_escape_htmltag($obj->project_ref).' - '.dol_escape_htmltag($obj->project_title) : '-').'</td>';
        print '<td>'.($obj->date_eta && $obj->date_eta != '0000-00-00' ? $obj->date_eta : '-').'</td>';
        print '<td class="center"><span style="display:inline-block;min-width:55px;padding:4px 8px;border-radius:12px;color:#fff;font-weight:bold;background:'.$eta_color.';">'.($eta_days !== null ? ($eta_days < 0 ? 'Arrived' : $eta_days.'d') : '-').'</span></td>';
        print '<td>'.($obj->freetime_end && $obj->freetime_end != '0000-00-00' ? $obj->freetime_end : '-').'</td>';
        print '<td class="center"><span style="display:inline-block;min-width:55px;padding:4px 8px;border-radius:12px;color:#fff;font-weight:bold;background:'.$free_color.';">'.($ft_days !== null ? $ft_days.'d' : '-').'</span></td>';
        print '<td>'.(isset($statuslabels[$obj->fk_statut]) ? $statuslabels[$obj->fk_statut] : '-').'</td>';
        if (!$is_external) {
            $cardurl = dol_buildpath('/chinaplanetlogistics/card.php', 1).'?id='.(int) $obj->rowid;
            print '<td class="center nowrap">';
            print '<a class="marginrightonly" href="'.$cardurl.'" title="Open shipment"><span class="fas fa-eye"></span></a>';
            if ($canwrite) print '<a href="'.$cardurl.'&action=edit" title="Edit shipment"><span class="fas fa-pencil-alt"></span></a>';
            print '</td>';
        }
        print '</tr>';
    }
} else {
    print '<tr class="oddeven"><td colspan="'.$colspan_empty.'" class="center">'.$db->lasterror().'</td></tr>';
}
print '</table>';
print '</div>';
print '</form>';

llxFooter();
$db->close();
?>
