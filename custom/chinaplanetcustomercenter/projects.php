<?php
if (!isset($_SERVER['QUERY_STRING'])) $_SERVER['QUERY_STRING'] = '';

require_once __DIR__.'/lib/main.lib.php';
require_once __DIR__.'/lib/admin_shell.lib.php';

require_once __DIR__.'/lib/portal.lib.php';
require_once DOL_DOCUMENT_ROOT.'/custom/cpkanban/lib/projectsync.lib.php';
require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/cpkanban/class/cpkanbancard.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';

cpcc_portal_require_access($user);

$scope = cpcc_portal_user_scope($user);
$is_external = !empty($scope['is_external']);
$socid = cpcc_portal_customer_scope_id($db, $user, GETPOSTINT('socid'));
if (!$is_external && GETPOSTINT('socid') > 0 && $socid <= 0) accessforbidden();
$statusFilter = GETPOST('status', 'alpha') === 'active' ? 'active' : '';
$action = GETPOST('action', 'aZ09');
$canadmin = (!empty($user->admin) || $user->hasRight('chinaplanetcustomercenter', 'admin'));

$projectCountWhere = " WHERE entity IN (".getEntity('project').")";
if ($socid > 0) $projectCountWhere .= " AND fk_soc=".(int) $socid;
elseif ($is_external) $projectCountWhere .= " AND 1=0";
$projectsTotal = 0;
$projectsActive = 0;
$countRes = $db->query("SELECT COUNT(*) nb FROM ".MAIN_DB_PREFIX."projet".$projectCountWhere);
if ($countRes && ($countObj = $db->fetch_object($countRes))) $projectsTotal = (int) $countObj->nb;
$countRes = $db->query("SELECT COUNT(*) nb FROM ".MAIN_DB_PREFIX."projet".$projectCountWhere." AND fk_statut=1");
if ($countRes && ($countObj = $db->fetch_object($countRes))) $projectsActive = (int) $countObj->nb;
$activeProjectsUrl = cpcc_portal_scoped_url('/chinaplanetcustomercenter/projects.php', $is_external, $socid, array('status'=>'active'));
$allProjectsUrl = cpcc_portal_scoped_url('/chinaplanetcustomercenter/projects.php', $is_external, $socid);

$syncSummary = null;
if ($action === 'syncprojectcards') {
    if ($is_external || !$canadmin) accessforbidden();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        exit('Project synchronization requires POST.');
    }
    $syncToken = GETPOST('token', 'alphanohtml');
    if ($syncToken === '' || !hash_equals((string) currentToken(), (string) $syncToken)) accessforbidden();
    $syncSummary = cpcc_project_sync_existing_cards($db, $user, $socid);
    setEventMessages('Kanban sync completed. Created: '.(int)$syncSummary['created'].', updated: '.(int)$syncSummary['updated'].', errors: '.(int)$syncSummary['errors'], null, !empty($syncSummary['errors']) ? 'errors' : 'mesgs');
    header('Location: '.($statusFilter === 'active' ? $activeProjectsUrl : $allProjectsUrl), true, 303);
    exit;
}

$projectCss = array(
    cpcc_admin_shell_asset('/custom/chinaplanetcustomercenter/css/customer_dashboard.css'),
    cpcc_admin_shell_asset('/custom/chinaplanetcustomercenter/css/projects.css'),
);
if ($is_external) $projectCss[] = '/custom/cpkanban/css/board.css?v=20260718-m010';
else $projectCss[] = cpcc_admin_shell_asset('/custom/chinaplanetcustomercenter/css/admin_dashboard.css');
llxHeader('', 'Projects / المشاريع', '', '', 0, 0,
    $is_external ? array('/custom/cpkanban/js/board.js?v=20260718-m010') : array(),
    $projectCss);
if($is_external) cpcc_portal_navigation($user, 'projects');
else cpcc_admin_shell_open('portal','Projects / المشاريع','كل مشاريع العملاء والمشاريع النشطة في عرض واحد','fa-project-diagram',array('socid'=>$socid));

print '<main class="cpcc-dashboard cpcc-projects-page" dir="rtl">';
print '<header class="cpcc-page-head"><div>';
print '<span class="cpcc-eyebrow">CUSTOMER PROJECTS</span>';
print '<h1>المشاريع / Projects</h1>';
print '<p>تابع حالة مشاريعك وتواريخها وتفاصيلها ونسبة التقدم في مكان واحد. / Follow project status, dates, details, and progress in one place.</p>';
print '</div></header>';
print '<nav class="cpcc-project-view-cards" aria-label="Project views">';
print '<a class="cpcc-project-view'.($statusFilter === 'active' ? ' active' : '').'" href="'.dol_escape_htmltag($activeProjectsUrl).'"><span>المشاريع النشطة</span><strong>'.(int)$projectsActive.'</strong><small>Active projects</small></a>';
print '<a class="cpcc-project-view'.($statusFilter !== 'active' ? ' active' : '').'" href="'.dol_escape_htmltag($allProjectsUrl).'"><span>كل المشاريع</span><strong>'.(int)$projectsTotal.'</strong><small>All projects</small></a>';
print '</nav>';

if ($is_external && $socid <= 0) {
    print '<div class="warning">No linked customer account was found for this portal user.</div>';
} else {
    // Browsing projects must never create or modify Kanban records.
    if (!$is_external) {
        print '<form method="GET" action="'.$_SERVER['PHP_SELF'].'" style="margin-bottom:12px;">';
        print '<input type="text" class="flat" name="socid" value="'.dol_escape_htmltag((string)GETPOSTINT('socid')).'" placeholder="Customer ID filter">';
        if ($statusFilter === 'active') print '<input type="hidden" name="status" value="active">';
        print ' <input type="submit" class="button" value="Filter">';
        print '</form>';
        if ($canadmin) {
            print '<form method="POST" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'" style="margin-bottom:12px;">';
            print '<input type="hidden" name="token" value="'.newToken().'">';
            print '<input type="hidden" name="socid" value="'.(int)$socid.'">';
            if ($statusFilter === 'active') print '<input type="hidden" name="status" value="active">';
            print '<button class="button button-save" type="submit" name="action" value="syncprojectcards">Sync project cards / مزامنة بطاقات المشاريع</button></form>';
        }
    }

    $sql = "SELECT p.rowid, p.ref, p.title, p.description, p.datec, p.dateo, p.datee, p.fk_statut as status, p.fk_soc, s.nom as customer_name, COALESCE(t.nb_tasks,0) as nb_tasks,";
    $sql .= " c.rowid as card_id, c.title as card_title, c.status as card_status, c.progress as card_progress, c.fk_column,";
    $sql .= " col.label as column_label, col.code as column_code";
    $sql .= " FROM ".MAIN_DB_PREFIX."projet as p";
    $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON s.rowid = p.fk_soc";
    $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."cpkanban_card as c ON c.rowid = (SELECT MIN(cc.rowid) FROM ".MAIN_DB_PREFIX."cpkanban_card as cc WHERE cc.fk_project=p.rowid AND cc.entity=p.entity AND cc.fk_soc=p.fk_soc) AND c.entity=p.entity AND c.fk_soc=p.fk_soc";
    $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."cpkanban_column as col ON col.rowid=c.fk_column AND col.entity=c.entity";
    $sql .= " LEFT JOIN (SELECT entity,fk_projet,COUNT(*) as nb_tasks FROM ".MAIN_DB_PREFIX."projet_task GROUP BY entity,fk_projet) as t ON t.fk_projet=p.rowid AND t.entity=p.entity";
    $sql .= " WHERE p.entity IN (".getEntity('project').")";
    if ($socid > 0) {
        $sql .= " AND p.fk_soc = ".(int) $socid;
    } elseif ($is_external) {
        $sql .= " AND p.fk_soc = ".(int) $socid;
    }
    if ($statusFilter === 'active') $sql .= " AND p.fk_statut=1";
    $sql .= " ORDER BY p.datec DESC, p.rowid DESC";

    $resql = $db->query($sql);
    $rows = array();
    if ($resql) {
        while ($obj = $db->fetch_object($resql)) {
            $rows[] = $obj;
        }
    }

    if ($is_external) print '<div class="cpk-project-modal-host" data-canwrite="0">';
    print '<table class="noborder centpercent">';
    print '<tr class="liste_titre">';
    if ($is_external) {
        print '<th>المشروع / Project</th><th>الحالة / Status</th><th>التواريخ / Dates</th><th>التفاصيل / Details</th><th>التقدم / Progress</th>';
    } else {
        print '<th>Project</th><th>Customer</th><th>Status</th><th>Dates</th><th>Tasks</th><th>Details</th><th>Kanban sync</th><th>Progress</th>';
    }
    print '</tr>';

    if (!$resql) {
        dol_syslog('M084 projects list query failed: '.$db->lasterror(), LOG_ERR);
        print '<tr><td colspan="'.($is_external ? '5' : '8').'" class="error" role="alert">تعذر تحميل المشاريع. أعد المحاولة أو تواصل مع المسؤول. / Projects could not be loaded.</td></tr>';
    } elseif (empty($rows)) {
        print '<tr><td colspan="'.($is_external ? '5' : '8').'" class="center opacitymedium">لا توجد مشاريع / No projects found.</td></tr>';
    } else {
        foreach ($rows as $row) {
            $statusLabel = cpcc_project_status_label($row->status);
            $dateRange = array();
            if (!empty($row->dateo)) $dateRange[] = 'Start '.cpcc_portal_date($row->dateo, 'day');
            if (!empty($row->datee)) $dateRange[] = 'End '.cpcc_portal_date($row->datee, 'day');
            if (empty($dateRange) && !empty($row->datec)) $dateRange[] = 'Created '.cpcc_portal_date($row->datec, 'day');
            $taskCount = !empty($row->nb_tasks) ? (int) $row->nb_tasks : 0;
            $details = array();
            if (!empty($row->description)) $details[] = dol_trunc($row->description, 120);
            if (!$is_external && !empty($row->card_id)) {
                $details[] = 'Kanban: '.dol_escape_htmltag($row->card_title ?: 'card');
            }

            $cardLabel = '-';
            if (!empty($row->card_id)) {
                $cardLabel = 'Card #'.(int) $row->card_id;
                if (!empty($row->column_label)) {
                    $cardLabel .= ' / '.dol_escape_htmltag($row->column_label);
                }
            } elseif (!$is_external && $canadmin) {
                $cardLabel = 'Missing - will be created on sync';
            }

            $progress = !empty($row->card_progress) ? (int) $row->card_progress : ((int) $row->status === 2 ? 100 : ((int) $row->status === 1 ? 55 : 5));

            print '<tr>';
            print '<td>';
            $projectUrl = dol_buildpath('/chinaplanetcustomercenter/project.php', 1).'?id='.(int) $row->rowid.(!$is_external?'&socid='.(int)$socid:'');
            $projectLinkClass = '';
            $projectLinkData = '';
            if ($is_external && !empty($row->card_id)) {
                $projectUrl = dol_buildpath('/cpkanban/card.php', 1).'?id='.(int) $row->card_id;
                $projectLinkClass = ' class="cpk-card-link"';
                $projectLinkData = ' data-id="'.(int) $row->card_id.'"';
            }
            print '<strong><a'.$projectLinkClass.$projectLinkData.' href="'.dol_escape_htmltag($projectUrl).'">'.dol_escape_htmltag($row->ref).'</a></strong><br>';
            print dol_escape_htmltag($row->title ?: '');
            if (!$is_external && !empty($row->rowid) && $user->hasRight('projet', 'lire')) {
                print '<br><a target="_blank" rel="noopener" href="'.dol_buildpath('/projet/card.php', 1).'?id='.(int) $row->rowid.'">Vision Advanced / السجل المتقدم ↗</a>';
            }
            print '</td>';
            if (!$is_external) print '<td>'.dol_escape_htmltag($row->customer_name ?: '-').'</td>';
            print '<td>'.$statusLabel.'</td>';
            print '<td>'.(!empty($dateRange) ? implode('<br>', $dateRange) : '-').'</td>';
            if (!$is_external) print '<td>'.(int) $taskCount.'</td>';
            print '<td>'.(!empty($details) ? implode('<br>', array_map('dol_escape_htmltag', $details)) : '-').'</td>';
            if (!$is_external) print '<td>'.$cardLabel.'</td>';
            print '<td>'.(int) $progress.'%</td>';
            print '</tr>';
        }
    }
    print '</table>';
    if ($is_external) {
        print '<dialog id="cpk-card-dialog" class="cpk-card-dialog" aria-labelledby="cpk-card-dialog-title">';
        print '<div class="cpk-dialog-head"><strong id="cpk-card-dialog-title">مركز متابعة المشروع / Project Command Center</strong><button type="button" class="cpk-dialog-close" aria-label="إغلاق تفاصيل المشروع">×</button></div>';
        print '<iframe class="cpk-dialog-frame" title="تفاصيل بطاقة المشروع"></iframe></dialog></div>';
    }
}

print '</main>';
if(!$is_external) cpcc_admin_shell_close();
llxFooter();
