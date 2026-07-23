<?php
require $_SERVER['DOCUMENT_ROOT'].'/main.inc.php';
require_once __DIR__.'/../lib/main.lib.php';
require_once __DIR__.'/../class/ShippingSopService.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/chinaplanetcustomercenter/lib/admin_shell.lib.php';
chinaplanetsp_require_sop($user, 'sopread');
$langs->load('chinaplanetshippingprices@chinaplanetshippingprices');
$service = new ShippingSopService($db);
$canWrite = chinaplanetsp_can_sop($user, 'sopwrite');
$canManageCategories = chinaplanetsp_can_sop($user, 'sopcategory');
$filters = array(
    'q' => GETPOST('q', 'alphanohtml'), 'category' => GETPOSTINT('category'),
    'country_code' => strtoupper(GETPOST('country_code', 'alpha')), 'pol' => GETPOST('pol', 'alphanohtml'),
    'pod' => GETPOST('pod', 'alphanohtml'), 'shipping_line' => GETPOST('shipping_line', 'alphanohtml'),
    'cargo_type' => GETPOST('cargo_type', 'alphanohtml'), 'status' => GETPOST('status', 'int'),
);
$includeNonActive = $canWrite || chinaplanetsp_can_sop($user, 'soppublish');
$rows = $service->search($filters, $includeNonActive, 200);
$categories = $service->getCategories($includeNonActive);
$stats = $service->getStats();
$statusLabels = array('-1'=>'Deleted','0'=>'Draft','1'=>'Active','9'=>'Archived');
$morecss = array(
    cpcc_admin_shell_asset('/custom/chinaplanetcustomercenter/css/admin_dashboard.css'),
    cpcc_admin_shell_asset('/custom/chinaplanetshippingprices/css/shippingprices.css'),
);
llxHeader('', 'Internal SOP Library', '', '', 0, 0, array(), $morecss);
cpcc_admin_shell_open('sop', 'Internal Operations SOP / إجراءات التشغيل الداخلية', 'Find the correct procedure before handling an inquiry or order', 'fa-route');
print '<main class="chinaplanetsp-sop-page"><section class="chinaplanetsp-admin-hero"><div><span>CONTROLLED OPERATIONS LIBRARY</span><h1>Shipping SOP Center / مركز إجراءات الشحن</h1><p>Search by route, service, cargo or required country document.</p></div><div class="chinaplanetsp-sop-hero-buttons">';
if ($canWrite) print '<a class="button" href="edit.php"><span class="fas fa-plus"></span> New SOP</a>';
print '<a class="button" href="../index.php"><span class="fas fa-search-dollar"></span> Price inquiry</a></div></section>';
print '<section class="chinaplanetsp-admin-stats"><div><span>Total SOPs</span><strong>'.(int)$stats['total'].'</strong><small>excluding deleted</small></div><div><span>Active</span><strong>'.(int)$stats['active_total'].'</strong><small>published procedures</small></div><div class="warn"><span>Draft</span><strong>'.(int)$stats['draft_total'].'</strong><small>awaiting publication</small></div><div><span>Archived</span><strong>'.(int)$stats['archived_total'].'</strong><small>retained for audit</small></div></section>';
print '<section class="chinaplanetsp-panel"><div class="chinaplanetsp-panel-head"><div><span class="fas fa-filter"></span><strong>Fast SOP search / بحث سريع</strong></div>';
if ($canManageCategories) print '<a href="categories.php">Manage categories</a>';
print '</div><form method="get" class="chinaplanetsp-search"><div class="chinaplanetsp-sop-filters">';
$input = function($name,$label,$value,$placeholder='') { print '<label><span>'.dol_escape_htmltag($label).'</span><input name="'.$name.'" value="'.dol_escape_htmltag($value).'" placeholder="'.dol_escape_htmltag($placeholder).'"></label>'; };
$input('q','Title, reference or keyword',$filters['q'],'CargoX, EXW, warning...');
print '<label><span>Category</span><select name="category"><option value="">All categories</option>'; foreach($categories as $c) print '<option value="'.(int)$c['rowid'].'"'.((int)$filters['category']===(int)$c['rowid']?' selected':'').'>'.dol_escape_htmltag($c['label']).'</option>'; print '</select></label>';
$input('country_code','Country code',$filters['country_code'],'EG / SA'); $input('pol','POL',$filters['pol'],'All'); $input('pod','POD',$filters['pod'],'All'); $input('shipping_line','Shipping line',$filters['shipping_line'],'All'); $input('cargo_type','Cargo type',$filters['cargo_type'],'battery / chemical');
if ($includeNonActive) { print '<label><span>Status</span><select name="status"><option value="">All statuses</option>'; foreach(array(1=>'Active',0=>'Draft',9=>'Archived',-1=>'Deleted') as $k=>$v) print '<option value="'.$k.'"'.((string)$filters['status']===(string)$k?' selected':'').'>'.$v.'</option>'; print '</select></label>'; }
print '</div><div class="chinaplanetsp-rule-actions"><button class="button button-save"><span class="fas fa-search"></span> Search</button><a class="button" href="index.php">Reset</a></div></form></section>';
print '<section class="chinaplanetsp-sop-results"><div class="chinaplanetsp-list-head"><h2>Matching procedures <span>'.count($rows).'</span></h2></div>';
if (!$rows) print '<div class="info">No SOP matches these filters.</div>';
foreach ($rows as $row) {
    $status = $statusLabels[(string)$row['status']] ?? 'Unknown';
    $scope = array_filter(array($row['country_code'] ?: 'All countries', $row['pol'] ? 'POL '.$row['pol'] : '', $row['pod'] ? 'POD '.$row['pod'] : '', $row['shipping_line'] ?: '', $row['incoterm'] ?: '', $row['cargo_type'] ?: ''));
    print '<article class="chinaplanetsp-sop-card status-'.(int)$row['status'].'"><div class="chinaplanetsp-sop-card-main"><div class="chinaplanetsp-sop-card-meta"><span class="chinaplanetsp-badge">'.dol_escape_htmltag($row['ref']).'</span><span class="chinaplanetsp-sop-status">'.dol_escape_htmltag($status).'</span><span>Rev '.(int)$row['revision_no'].'</span></div><h3><a href="view.php?id='.(int)$row['sop_id'].'">'.dol_escape_htmltag($row['title']).'</a></h3>';
    if (!empty($row['title_ar'])) print '<div class="chinaplanetsp-sop-ar" dir="rtl">'.dol_escape_htmltag($row['title_ar']).'</div>';
    print '<p>'.dol_escape_htmltag($row['applicability'] ?: 'General operating procedure').'</p><div class="chinaplanetsp-sop-scope">'.dol_escape_htmltag(implode(' · ', $scope)).'</div></div><div class="chinaplanetsp-sop-card-side"><strong>'.dol_escape_htmltag($row['category_label'] ?: 'Uncategorized').'</strong><small>'.dol_escape_htmltag($row['responsible_department'] ?: 'Department not assigned').'</small><a class="button smallpaddingimp" href="view.php?id='.(int)$row['sop_id'].'">Open SOP</a></div></article>';
}
print '</section></main>';
cpcc_admin_shell_close(); llxFooter(); $db->close();
