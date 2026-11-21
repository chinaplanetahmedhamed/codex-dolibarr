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

$langs->loadLangs(array("companies", "orders", "sendings", "projects", "other", "chinaplanetlogistics@chinaplanetlogistics"));

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
if (!in_array($statusFilter, array('active','intransit','arriving','arrived','critical','missingdocs'), true)) $statusFilter = '';
$hasExportDocumentTable = false;
$exportDocTableRes = $db->query("SHOW TABLES LIKE '".$db->escape(MAIN_DB_PREFIX.'cpcc_export_document')."'");
if ($exportDocTableRes && $db->num_rows($exportDocTableRes) > 0) $hasExportDocumentTable = true;
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

$pageCss = array(
    cpcc_admin_shell_asset('/custom/chinaplanetcustomercenter/css/customer_dashboard.css'),
    cpcc_admin_shell_asset('/custom/chinaplanetlogistics/css/shipment-list.css'),
);
if (!$is_external) $pageCss[] = cpcc_admin_shell_asset('/custom/chinaplanetcustomercenter/css/admin_dashboard.css');
llxHeader('', 'China Planet Logistics - Shipment List', '', '', 0, 0, array(), $pageCss);
if ($is_external) cpcc_portal_navigation($user, 'shipments');

$newbtn = '';
if ($canwrite) {
    $newbtn = '<a class="butActionNew" href="'.dol_buildpath('/chinaplanetlogistics/card.php?action=create', 1).'"><span class="fa fa-plus-circle valignmiddle btnTitle-icon"></span></a>';
}

if (!$is_external) cpcc_admin_shell_open('shipments', 'Shipment List / قائمة الشحنات', 'البحث في الحاويات وبوالص الشحن ومواعيد الوصول', 'fa-list', array('socid'=>$socid));

print '<main class="cpcc-dashboard cpcc-shipments-page" dir="rtl">';
print '<header class="cpcc-page-head"><div>';
print '<span class="cpcc-eyebrow">SHIPMENT TRACKING</span>';
print '<h1>الشحنات / Shipments</h1>';
print '<p>تابع حالة الشحنة ومواعيد الوصول والوقت الحر من مكان واحد. / Track shipment status, arrival dates, and free time in one place.</p>';
print '</div></header>';
print '<form class="cpl-shipment-filter-form" method="GET" action="'.$_SERVER["PHP_SELF"].'">';
print '<div class="cpl-filter-grid" aria-label="Shipment filters / فلاتر الشحنات">';
print '<label><span>الحاوية / Container</span><input type="text" class="flat" name="search_container" value="'.dol_escape_htmltag($search_container).'" placeholder="رقم الحاوية / Container number"></label>';
print '<label><span>البوليصة / BL</span><input type="text" class="flat" name="search_bl" value="'.dol_escape_htmltag($search_bl).'" placeholder="رقم البوليصة / BL number"></label>';
print '<label><span>الحالة / Status</span>'.Form::selectarray('status', array(
    '' => 'All statuses / كل الحالات',
    'active' => 'Active / نشطة',
    'intransit' => 'In transit / في الطريق',
    'arriving' => 'ETA within 14 days / خلال 14 يوم',
    'arrived' => 'Arrived / وصلت',
    'critical' => 'Critical / حرجة',
    'missingdocs' => 'Missing export docs / بدون مستندات تصدير',
), $statusFilter, 0, 0, 0, '', 0, 0, 0, '', '', 1).'</label>';
if (!$is_external) print '<label><span>العميل / Customer</span>'.Form::selectarray('socid',$customerOptions,$socid,'كل العملاء / All customers',0,0,'aria-label="Customer filter / فلتر العميل"',0,0,0,'','',1).'</label>';
print '<button type="submit" class="button cpl-filter-submit"><span class="fas fa-search" aria-hidden="true"></span> بحث / Search</button>';
print '</div>';
print '<div class="div-table-responsive cpl-shipment-list-wrap" tabindex="0" aria-label="Shipment results / نتائج الشحنات">';
print '<table class="tagtable liste cpl-shipment-list">';

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
if ($is_external) $sql.= " AND t.customer_visible=1";

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
elseif ($statusFilter === 'critical') $sql.= " AND t.fk_statut IN (".chinaplanetlogistics_status_sql_list('active').") AND ((t.date_eta < '".$db->escape(date('Y-m-d'))."') OR (t.freetime_end BETWEEN '".$db->escape(date('Y-m-d'))."' AND '".$db->escape(date('Y-m-d',strtotime('+7 days')))."'))";
elseif ($statusFilter === 'missingdocs' && $hasExportDocumentTable) $sql.= " AND t.fk_statut IN (".chinaplanetlogistics_status_sql_list('active').") AND NOT EXISTS (SELECT 1 FROM ".MAIN_DB_PREFIX."cpcc_export_document d WHERE d.entity=t.entity AND d.fk_shipment=t.rowid AND d.status=1)";

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

        $cardurl = dol_buildpath('/chinaplanetlogistics/card.php', 1).'?id='.(int) $obj->rowid;
        print '<tr class="oddeven">';
        print '<td><b>'.dol_escape_htmltag($obj->container_no).'</b></td>';
        print '<td>'.dol_escape_htmltag($obj->bl_no).'</td>';
        print '<td>';
        if ($is_external) {
            $trackLabel = $langs->trans('CPLTrackShipment');
            print '<div class="cpl-shipment-reference">';
            print '<a class="cpl-shipment-ref" href="'.$cardurl.'">'.dol_escape_htmltag($obj->ref).'</a>';
            print '<a class="cpl-track-action" href="'.$cardurl.'" aria-label="'.dol_escape_htmltag($trackLabel.' '.$obj->ref).'">';
            print '<span class="fas fa-route" aria-hidden="true"></span><span>'.dol_escape_htmltag($trackLabel).'</span>';
            print '</a></div>';
        } else {
            print '<a href="'.$cardurl.'">'.dol_escape_htmltag($obj->ref).'</a>';
        }
        print '</td>';
        if (!$is_external) print '<td>'.dol_escape_htmltag($obj->customer_name).'</td>';
        print '<td>'.($obj->project_ref ? dol_escape_htmltag($obj->project_ref).' - '.dol_escape_htmltag($obj->project_title) : '-').'</td>';
        print '<td>'.($obj->date_eta && $obj->date_eta != '0000-00-00' ? $obj->date_eta : '-').'</td>';
        print '<td class="center"><span style="display:inline-block;min-width:55px;padding:4px 8px;border-radius:12px;color:#fff;font-weight:bold;background:'.$eta_color.';">'.($eta_days !== null ? ($eta_days < 0 ? 'Arrived' : $eta_days.'d') : '-').'</span></td>';
        print '<td>'.($obj->freetime_end && $obj->freetime_end != '0000-00-00' ? $obj->freetime_end : '-').'</td>';
        print '<td class="center"><span style="display:inline-block;min-width:55px;padding:4px 8px;border-radius:12px;color:#fff;font-weight:bold;background:'.$free_color.';">'.($ft_days !== null ? $ft_days.'d' : '-').'</span></td>';
        print '<td>'.dol_escape_htmltag(chinaplanetlogistics_status_label($obj->fk_statut)).'</td>';
        if (!$is_external) {
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
print '</main>';

if (!$is_external) cpcc_admin_shell_close();
llxFooter();
$db->close();
?>
