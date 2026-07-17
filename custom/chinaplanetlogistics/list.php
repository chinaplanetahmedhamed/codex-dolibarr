<?php
/**
 * Customer Shipment List for China Planet Logistics
 * External user detection: $user->socid > 0 means external user (Dolibarr standard)
 */
require_once __DIR__.'/lib/main.lib.php';
require_once __DIR__.'/lib/chinaplanetlogistics.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/chinaplanetcustomercenter/lib/portal.lib.php';
require_once DOL_DOCUMENT_ROOT.'/custom/chinaplanetcustomercenter/lib/admin_shell.lib.php';

$langs->loadLangs(array("companies", "orders", "sendings", "projects", "other"));

// Security check
if (empty($user->id) || (!$user->hasRight('chinaplanetlogistics', 'read') && !$user->hasRight('chinaplanetcustomercenter', 'read') && empty($user->admin))) {
    accessforbidden();
}

// ============================================================
// EXTERNAL USER DETECTION - Dolibarr standard method
// In Dolibarr: $user->socid > 0 = external/portal user
// ============================================================
$scope = cpcc_portal_user_scope($user);
$is_external = !empty($scope['is_external']);
$socid = cpcc_portal_customer_scope_id($db, $user, GETPOSTINT('socid'));
$canwrite = (!$is_external && ($user->admin || $user->hasRight('chinaplanetlogistics', 'write') || $user->hasRight('chinaplanetcustomercenter', 'write')));

$action = GETPOST('action', 'alpha');
$search_container = GETPOST('search_container', 'alpha');
$search_bl = GETPOST('search_bl', 'alpha');
$search_any = GETPOST('search_any', 'alphanohtml');
$statusFilter = GETPOST('status', 'alpha');
if (!in_array($statusFilter, array('active','intransit','arriving','arrived','critical'), true)) $statusFilter = '';
$form = new Form($db);
$customerOptions = array();
if (!$is_external) {
    $resCustomers=$db->query("SELECT rowid,nom FROM ".MAIN_DB_PREFIX."societe WHERE entity=".(int)$conf->entity." AND status=1 AND client IN (1,3) ORDER BY nom");
    if($resCustomers) while($customer=$db->fetch_object($resCustomers)) $customerOptions[(int)$customer->rowid]=$customer->nom;
}

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

llxHeader('', 'China Planet Logistics - Shipment List', '', '', 0, 0, array(), $is_external ? array() : array(cpcc_admin_shell_asset('/custom/chinaplanetcustomercenter/css/admin_dashboard.css')));
if ($is_external) cpcc_portal_navigation($user, 'shipments');

$newbtn = '';
if ($canwrite) {
    $newbtn = '<a class="butActionNew" href="'.dol_buildpath('/chinaplanetlogistics/card.php?action=create', 1).'"><span class="fa fa-plus-circle valignmiddle btnTitle-icon"></span></a>';
}

if ($is_external) print load_fiche_titre('Shipment List / قائمة الشحنات', $newbtn, 'ship');
else cpcc_admin_shell_open('shipments', 'Shipment List / قائمة الشحنات', 'البحث في الحاويات وبوالص الشحن ومواعيد الوصول', 'fa-list', array('socid'=>$socid));

print '<form method="GET" action="'.$_SERVER["PHP_SELF"].'">';
if ($statusFilter !== '') print '<input type="hidden" name="status" value="'.dol_escape_htmltag($statusFilter).'">';
print '<div class="div-table-responsive">';
print '<table class="tagtable liste">';
print '<tr class="liste_titre_filter">';
print '<td class="liste_titre"><input type="text" class="flat maxwidth100" name="search_container" value="'.dol_escape_htmltag($search_container).'" placeholder="الحاوية / Container"></td>';
print '<td class="liste_titre"><input type="text" class="flat maxwidth100" name="search_bl" value="'.dol_escape_htmltag($search_bl).'" placeholder="البوليصة / BL"></td>';
if (!$is_external) print '<td class="liste_titre">'.Form::selectarray('socid',$customerOptions,$socid,'كل العملاء / All customers',0,0,'aria-label="Customer filter / فلتر العميل"',0,0,0,'','minwidth200',1).'</td>';
print '<td class="liste_titre" colspan="4"></td>';
print '<td class="liste_titre center"><input type="submit" class="button small" value="بحث / Search"></td>';
print '</tr>';

print '<tr class="liste_titre">';
print '<th>الحاوية / Container</th>';
print '<th>البوليصة / BL</th>';
print '<th>المرجع / Ref</th>';
if (!$is_external) print '<th>العميل / Customer</th>';
print '<th>المشروع / Project</th>';
print '<th>الوصول المتوقع / ETA</th>';
print '<th class="center">الوصول / Arrival</th>';
print '<th>نهاية الوقت الحر / Free Time End</th>';
print '<th class="center">الوقت الحر / Free Time</th>';
print '<th>الحالة / Status</th>';
if (!$is_external) print '<th class="center">الإجراءات / Actions</th>';
print '</tr>';

$sql = "SELECT t.rowid, t.ref, t.container_no, t.bl_no, t.date_eta, t.freetime_end, t.fk_statut, s.nom as customer_name, p.title as project_title, p.ref as project_ref";
$sql.= " FROM ".MAIN_DB_PREFIX."chinaplanetlogistics as t";
$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON t.fk_soc = s.rowid";
$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."projet as p ON p.rowid=t.fk_project AND p.entity=t.entity AND p.fk_soc=t.fk_soc";
$sql.= " WHERE t.entity = ".(int) $conf->entity;

// STRICT FILTERING: external users only see their company's data
if ($socid > 0) {
    $sql.= " AND t.fk_soc = " . $socid;
} elseif ($is_external) {
    $sql.= " AND 1=0";
}
if ($statusFilter === 'active') $sql.= " AND t.fk_statut IN (".chinaplanetlogistics_status_sql_list('active').")";
elseif ($statusFilter === 'intransit') $sql.= " AND t.fk_statut IN (".chinaplanetlogistics_status_sql_list('in_transit').")";
elseif ($statusFilter === 'arriving') $sql.= " AND t.date_eta BETWEEN '".$db->escape(date('Y-m-d'))."' AND '".$db->escape(date('Y-m-d',strtotime('+14 days')))."' AND t.fk_statut IN (".chinaplanetlogistics_status_sql_list('active').")";
elseif ($statusFilter === 'arrived') $sql.= " AND t.fk_statut IN (".chinaplanetlogistics_status_sql_list('arrived').")";
elseif ($statusFilter === 'critical') $sql.= " AND t.freetime_end BETWEEN '".$db->escape(date('Y-m-d'))."' AND '".$db->escape(date('Y-m-d',strtotime('+7 days')))."' AND t.fk_statut IN (".chinaplanetlogistics_status_sql_list('active').")";

if ($search_container) $sql.= " AND t.container_no LIKE '%".$db->escape($search_container)."%'";
if ($search_bl) $sql.= " AND t.bl_no LIKE '%".$db->escape($search_bl)."%'";
if ($search_any) {
    $escapedSearch = $db->escape($search_any);
    $sql .= " AND (t.ref LIKE '%".$escapedSearch."%' OR t.container_no LIKE '%".$escapedSearch."%' OR t.bl_no LIKE '%".$escapedSearch."%')";
}

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
        print '<td>'.dol_escape_htmltag(chinaplanetlogistics_status_label($obj->fk_statut)).'</td>';
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

if (!$is_external) cpcc_admin_shell_close();
llxFooter();
$db->close();
?>
