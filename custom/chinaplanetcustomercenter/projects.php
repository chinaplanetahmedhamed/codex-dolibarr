<?php
if (!isset($_SERVER['QUERY_STRING'])) $_SERVER['QUERY_STRING'] = '';

require_once __DIR__.'/lib/main.lib.php';
require_once __DIR__.'/lib/admin_shell.lib.php';

require_once __DIR__.'/lib/portal.lib.php';
require_once __DIR__.'/lib/operations.lib.php';

cpcc_portal_require_access($user);

$scope = cpcc_portal_user_scope($user);
$is_external = !empty($scope['is_external']);
$socid = cpcc_portal_customer_scope_id($db, $user, GETPOSTINT('socid'));
$statusFilter = GETPOST('status', 'alpha') === 'active' ? 'active' : '';
$action = GETPOST('action', 'aZ09');
$canadmin = (!empty($user->admin) || $user->hasRight('chinaplanetcustomercenter', 'admin'));
if (!$is_external) {
    require_once DOL_DOCUMENT_ROOT.'/custom/cpkanban/lib/projectsync.lib.php';
    require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';
    require_once DOL_DOCUMENT_ROOT.'/custom/cpkanban/class/cpkanbancard.class.php';
    require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
}

$projectCountWhere = " WHERE entity".($is_external ? "=".(int) $conf->entity : " IN (".getEntity('project').")");
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
if (!$is_external && $canadmin && $action === 'syncprojectcards') {
    $syncSummary = cpcc_project_sync_existing_cards($db, $user, $socid);
}

$projectCss = array(
    cpcc_admin_shell_asset('/custom/chinaplanetcustomercenter/css/customer_dashboard.css'),
    cpcc_admin_shell_asset('/custom/chinaplanetcustomercenter/css/projects.css'),
);
if (!$is_external) $projectCss[] = cpcc_admin_shell_asset('/custom/chinaplanetcustomercenter/css/admin_dashboard.css');
llxHeader('', 'Projects / المشاريع', '', '', 0, 0,
    array(),
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
    $projectIds = array();
    $syncScopeSql = "SELECT rowid FROM ".MAIN_DB_PREFIX."projet WHERE entity".($is_external ? "=".(int) $conf->entity : " IN (".getEntity('project').")");
    if ($socid > 0) {
        $syncScopeSql .= " AND fk_soc = ".(int) $socid;
    } elseif ($is_external) {
        $syncScopeSql .= " AND fk_soc = ".(int) $socid;
    }
    if ($statusFilter === 'active') $syncScopeSql .= " AND fk_statut=1";
    $syncScopeSql .= " ORDER BY rowid ASC";
    $syncRes = $db->query($syncScopeSql);
    if ($syncRes) {
        while ($syncRow = $db->fetch_object($syncRes)) {
            $projectIds[] = (int) $syncRow->rowid;
        }
    }

    $autoSyncCreated = 0;
    $autoSyncUpdated = 0;
    $autoSyncSkipped = 0;
    $autoSyncErrors = 0;
    if (!$is_external) {
        foreach ($projectIds as $projectId) {
            $project = new Project($db);
            if ($project->fetch($projectId) <= 0) {
                $autoSyncErrors++;
                continue;
            }
            $sync = cpcc_project_sync_kanban_card($db, $project, $user, 'modify');
            if (!empty($sync['action']) && $sync['action'] === 'created') $autoSyncCreated++;
            elseif (!empty($sync['action']) && $sync['action'] === 'updated') $autoSyncUpdated++;
            else $autoSyncSkipped++;
        }
    }

    if (!empty($syncSummary) && !$is_external) {
        print '<div class="info">';
        print 'Kanban sync completed. Created: '.(int) $syncSummary['created'].', updated: '.(int) $syncSummary['updated'].', skipped: '.(int) $syncSummary['skipped'].', errors: '.(int) $syncSummary['errors'];
        if (!empty($syncSummary['details'])) {
            print '<br>'.dol_escape_htmltag(implode(' | ', array_slice($syncSummary['details'], 0, 6)));
        }
        print '</div>';
    } elseif (($autoSyncCreated || $autoSyncUpdated) && !$is_external) {
        print '<div class="info">';
        print 'Auto sync refreshed cards. Created: '.(int) $autoSyncCreated.', updated: '.(int) $autoSyncUpdated.', skipped: '.(int) $autoSyncSkipped.', errors: '.(int) $autoSyncErrors;
        print '</div>';
    }

    if (!$is_external) {
        print '<form method="GET" action="'.$_SERVER['PHP_SELF'].'" style="margin-bottom:12px;">';
        print '<input type="text" class="flat" name="socid" value="'.dol_escape_htmltag(GETPOSTINT('socid')).'" placeholder="Customer ID filter">';
        if ($statusFilter === 'active') print '<input type="hidden" name="status" value="active">';
        print ' <input type="submit" class="button" value="Filter">';
        if ($canadmin) {
            print ' <button class="button button-save" type="submit" name="action" value="syncprojectcards">Sync project cards</button>';
        }
        print '</form>';
    }

    $sql = "SELECT p.rowid, p.ref, p.title, p.description, p.datec, p.dateo, p.datee, p.fk_statut as status, p.fk_soc, s.nom as customer_name, COALESCE(t.nb_tasks,0) as nb_tasks,";
    if ($is_external) $sql .= " NULL as card_id,NULL as card_title,NULL as card_status,NULL as card_progress,NULL as fk_column,NULL as column_label,NULL as column_code";
    else $sql .= " c.rowid as card_id, c.title as card_title, c.status as card_status, c.progress as card_progress, c.fk_column,col.label as column_label,col.code as column_code";
    $sql .= " FROM ".MAIN_DB_PREFIX."projet as p";
    $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON s.rowid=p.fk_soc AND s.entity=p.entity";
    if (!$is_external) {
        $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."cpkanban_card as c ON c.rowid = (SELECT MIN(cc.rowid) FROM ".MAIN_DB_PREFIX."cpkanban_card as cc WHERE cc.fk_project=p.rowid AND cc.entity=p.entity AND cc.fk_soc=p.fk_soc) AND c.entity=p.entity AND c.fk_soc=p.fk_soc";
        $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."cpkanban_column as col ON col.rowid=c.fk_column AND col.entity=c.entity";
    }
    $sql .= " LEFT JOIN (SELECT entity,fk_projet,COUNT(*) as nb_tasks FROM ".MAIN_DB_PREFIX."projet_task GROUP BY entity,fk_projet) as t ON t.fk_projet=p.rowid AND t.entity=p.entity";
    $sql .= " WHERE p.entity".($is_external ? "=".(int) $conf->entity : " IN (".getEntity('project').")");
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

    print '<table class="noborder centpercent">';
    print '<tr class="liste_titre">';
    if ($is_external) {
        print '<th>المشروع / Project</th><th>الحالة / Status</th><th>التواريخ / Dates</th><th>التفاصيل / Details</th><th>التقدم / Progress</th>';
    } else {
        print '<th>Project</th><th>Customer</th><th>Status</th><th>Dates</th><th>Tasks</th><th>Details</th><th>Kanban sync</th><th>Progress</th>';
    }
    print '</tr>';

    if (empty($rows)) {
        print '<tr><td colspan="'.($is_external ? '5' : '8').'" class="center opacitymedium">لا توجد مشاريع / No projects found.</td></tr>';
    } else {
        foreach ($rows as $row) {
            $projection = null;
            if ($is_external) {
                $currency = cpcc_financial_currency(!empty($conf->currency) ? $conf->currency : 'USD');
                $projection = cpcc_project_lifecycle_projection(cpcc_project_lifecycle($db, (int) $row->fk_soc, (int) $row->rowid, $currency, (int) $conf->entity));
                $statusLabel = !empty($projection['completed']) ? 'مكتمل / Completed' : ((int) $row->status <= 0 ? 'مسودة / Draft' : 'قيد التنفيذ / In progress');
            } else {
                $statusLabel = cpcc_project_status_label($row->status);
            }
            $dateRange = array();
            if (!empty($row->dateo)) $dateRange[] = 'Start '.cpcc_portal_date($row->dateo);
            if (!empty($row->datee)) $dateRange[] = 'End '.cpcc_portal_date($row->datee);
            if (empty($dateRange) && !empty($row->datec)) $dateRange[] = 'Created '.cpcc_portal_date($row->datec);
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

            if ($is_external) $progress = (int) $projection['progress_percent'];
            elseif (!empty($row->card_progress)) $progress = (int) $row->card_progress;
            else $progress = (int) $row->status === 2 ? 100 : ((int) $row->status === 1 ? 55 : 5);

            print '<tr>';
            print '<td>';
            $projectUrl = dol_buildpath('/chinaplanetcustomercenter/project.php', 1).'?id='.(int) $row->rowid.(!$is_external?'&socid='.(int)$socid:'');
            print '<strong><a href="'.dol_escape_htmltag($projectUrl).'">'.dol_escape_htmltag($row->ref).'</a></strong><br>';
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
}

print '</main>';
if(!$is_external) cpcc_admin_shell_close();
llxFooter();
