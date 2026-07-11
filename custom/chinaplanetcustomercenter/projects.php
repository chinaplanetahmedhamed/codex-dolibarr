<?php
if (!isset($_SERVER['QUERY_STRING'])) $_SERVER['QUERY_STRING'] = '';

$res = 0;
if (!$res && file_exists("../../main.inc.php")) $res = @include "../../main.inc.php";
if (!$res && file_exists("../../../main.inc.php")) $res = @include "../../../main.inc.php";
if (!$res) die("Include of main fails");

require_once __DIR__.'/lib/portal.lib.php';
require_once __DIR__.'/lib/projectsync.lib.php';
require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/cpkanban/class/cpkanbancard.class.php';

cpcc_portal_require_access($user);

$scope = cpcc_portal_user_scope($user);
$is_external = !empty($scope['is_external']);
$socid = $is_external ? (int) $scope['socid'] : GETPOSTINT('socid');
$action = GETPOST('action', 'aZ09');
$canadmin = (!empty($user->admin) || $user->hasRight('chinaplanetcustomercenter', 'admin'));

$syncSummary = null;
if (!$is_external && $canadmin && $action === 'syncprojectcards') {
    $syncSummary = cpcc_project_sync_existing_cards($db, $user, $socid);
}

llxHeader('', 'Projects / المشاريع');
cpcc_portal_navigation($user, 'projects');

print '<h2>Projects / المشاريع</h2>';
print '<p>Real Dolibarr projects linked to your company, with Kanban sync status.</p>';

if ($is_external && $socid <= 0) {
    print '<div class="warning">No linked customer account was found for this portal user.</div>';
} else {
    $projectIds = array();
    $syncScopeSql = "SELECT rowid FROM ".MAIN_DB_PREFIX."projet WHERE entity IN (".getEntity('project').")";
    if ($socid > 0) {
        $syncScopeSql .= " AND fk_soc = ".(int) $socid;
    } elseif ($is_external) {
        $syncScopeSql .= " AND fk_soc = ".(int) $socid;
    }
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
        print ' <input type="submit" class="button" value="Filter">';
        if ($canadmin) {
            print ' <button class="button button-save" type="submit" name="action" value="syncprojectcards">Sync project cards</button>';
        }
        print '</form>';
    }

    $sql = "SELECT p.rowid, p.ref, p.title, p.description, p.datec, p.dateo, p.datee, p.fk_statut as status, p.fk_soc, s.nom as customer_name,";
    $sql .= " c.rowid as card_id, c.title as card_title, c.status as card_status, c.progress as card_progress, c.fk_column,";
    $sql .= " col.label as column_label, col.code as column_code";
    $sql .= " FROM ".MAIN_DB_PREFIX."projet as p";
    $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON s.rowid = p.fk_soc";
    $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."cpkanban_card as c ON c.rowid = (SELECT MIN(cc.rowid) FROM ".MAIN_DB_PREFIX."cpkanban_card as cc WHERE cc.fk_project = p.rowid)";
    $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."cpkanban_column as col ON col.rowid = c.fk_column";
    $sql .= " LEFT JOIN (SELECT fk_projet, COUNT(*) as nb_tasks FROM ".MAIN_DB_PREFIX."projet_task GROUP BY fk_projet) as t ON t.fk_projet = p.rowid";
    $sql .= " WHERE p.entity IN (".getEntity('project').")";
    if ($socid > 0) {
        $sql .= " AND p.fk_soc = ".(int) $socid;
    } elseif ($is_external) {
        $sql .= " AND p.fk_soc = ".(int) $socid;
    }
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
    print '<th>Project</th><th>Customer</th><th>Status</th><th>Dates</th><th>Tasks</th><th>Details</th><th>Kanban sync</th><th>Progress</th>';
    print '</tr>';

    if (empty($rows)) {
        print '<tr><td colspan="8" class="center opacitymedium">No projects found.</td></tr>';
    } else {
        foreach ($rows as $row) {
            $statusLabel = cpcc_project_status_label($row->status);
            $dateRange = array();
            if (!empty($row->dateo)) $dateRange[] = 'Start '.dol_print_date($row->dateo, 'day');
            if (!empty($row->datee)) $dateRange[] = 'End '.dol_print_date($row->datee, 'day');
            if (empty($dateRange) && !empty($row->datec)) $dateRange[] = 'Created '.dol_print_date($row->datec, 'day');
            $taskCount = !empty($row->nb_tasks) ? (int) $row->nb_tasks : 0;
            $details = array();
            if (!empty($row->description)) $details[] = dol_trunc($row->description, 120);
            if (!empty($row->card_id)) {
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
            print '<strong>'.dol_escape_htmltag($row->ref).'</strong><br>';
            print dol_escape_htmltag($row->title ?: '');
            if (!$is_external && !empty($row->rowid) && $user->hasRight('projet', 'lire')) {
                print '<br><a href="'.dol_buildpath('/projet/card.php', 1).'?id='.(int) $row->rowid.'">Open in Dolibarr</a>';
            }
            print '</td>';
            print '<td>'.dol_escape_htmltag($row->customer_name ?: '-').'</td>';
            print '<td>'.$statusLabel.'</td>';
            print '<td>'.(!empty($dateRange) ? implode('<br>', $dateRange) : '-').'</td>';
            print '<td>'.(int) $taskCount.'</td>';
            print '<td>'.(!empty($details) ? implode('<br>', array_map('dol_escape_htmltag', $details)) : '-').'</td>';
            print '<td>'.$cardLabel.'</td>';
            print '<td>'.(int) $progress.'%</td>';
            print '</tr>';
        }
    }
    print '</table>';
}

llxFooter();
