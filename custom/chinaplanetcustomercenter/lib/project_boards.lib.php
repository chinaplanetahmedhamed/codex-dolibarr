<?php

/**
 * Read customer-approved card titles and status from the current project board.
 * Staff task titles, free-form list names, descriptions and attachments stay in Operations.
 */
function cpcc_customer_board_cards($db, $project)
{
    $global = $GLOBALS['conf']->global;
    if (empty($global->CPO_BOARDS_ENABLED) || empty($global->CPO_CUSTOMER_WORKSPACES_ENABLED)) return [];

    $p = MAIN_DB_PREFIX;
    $publicationTable = $p.'cpo_board_customer_card';
    $schema = $db->query("SHOW TABLES LIKE '".$db->escape($publicationTable)."'");
    $hasPublication = false;
    while ($schema && ($table = $db->fetch_object($schema))) {
        if (in_array($publicationTable, (array) $table, true)) {
            $hasPublication = true;
            break;
        }
    }
    $title = $hasPublication ? 'CASE WHEN pub.fk_task IS NULL THEN o.title ELSE pub.public_title END' : 'o.title';
    $sql = "SELECT $title AS title, t.status, t.progress, l.title AS board_list_title FROM {$p}cpo_project_board m";
    $sql .= " JOIN {$p}projet pr ON pr.entity=m.entity AND pr.rowid=m.fk_project";
    $sql .= " JOIN {$p}cpo_board b ON b.entity=m.entity AND b.rowid=m.fk_board AND b.archived=0";
    $sql .= " JOIN {$p}cpo_board_card c ON c.entity=b.entity AND c.fk_board=b.rowid AND c.archived=0";
    $sql .= " JOIN {$p}cpo_board_list l ON l.entity=b.entity AND l.fk_board=b.rowid AND l.rowid=c.fk_list AND l.archived=0";
    $sql .= " JOIN {$p}cpo_task t ON t.entity=b.entity AND t.rowid=c.fk_task AND t.date_archived IS NULL";
    $sql .= " LEFT JOIN {$p}cpo_operation o ON o.entity=t.entity AND o.rowid=t.fk_operation"
        ." AND o.fk_project=m.fk_project AND o.fk_soc=pr.fk_soc AND o.status=1 AND o.customer_visible=1";
    if ($hasPublication) {
        $sql .= " LEFT JOIN {$p}cpo_board_customer_card pub ON pub.entity=t.entity AND pub.fk_task=t.rowid";
    }
    $sql .= " WHERE pr.entity=".(int) $project->entity." AND pr.rowid=".(int) $project->rowid." AND pr.fk_soc=".(int) $project->fk_soc;
    $sql .= " AND t.status IN ('todo','in_progress','waiting','review','done')";
    $sql .= $hasPublication
        ? " AND ((pub.fk_task IS NULL AND o.rowid IS NOT NULL) OR (pub.fk_board=b.rowid"
            ." AND pub.fk_project=pr.rowid AND pub.fk_soc=pr.fk_soc AND pub.public_title<>''))"
        : " AND o.rowid IS NOT NULL";
    if (!empty($global->CPO_BOARD_DELETE_ENABLED)) {
        $sql .= " AND NOT EXISTS (SELECT 1 FROM {$p}cpo_board_deleted d WHERE d.entity=b.entity AND d.fk_board=b.rowid)";
    }
    $sql .= " ORDER BY l.position,l.rowid,c.position,c.rowid";

    $result = $db->query($sql);
    if (!$result) {
        dol_syslog('Customer Center: project board projection unavailable', DOL_LOG_ERR);
        return [];
    }

    $cards = [];
    while ($card = $db->fetch_object($result)) {
        $cards[] = $card;
    }
    return $cards;
}

function cpcc_render_customer_projects($db, $user, $entity, $projectId, $activeOnly, $boardOnly = false)
{
    $socid = cpcc_portal_customer_scope_id($db, $user);
    if ($entity <= 0 || $socid <= 0) accessforbidden();
    header('Cache-Control: private, no-store');

    $sql = 'SELECT rowid,entity,fk_soc,ref,title,fk_statut FROM '.MAIN_DB_PREFIX.'projet';
    $sql .= ' WHERE entity='.(int) $entity.' AND fk_soc='.(int) $socid;
    if ($projectId > 0) $sql .= ' AND rowid='.(int) $projectId;
    $sql .= ' ORDER BY datec DESC,rowid DESC';
    $result = $db->query($sql);
    if (!$result) dol_syslog('Customer Center: customer project lookup failed', DOL_LOG_ERR);
    $projects = [];
    while ($result && ($project = $db->fetch_object($result))) $projects[] = $project;
    if ($projectId > 0 && !$projects) accessforbidden();
    if ($boardOnly) {
        if ($projectId <= 0) accessforbidden();
        header('Content-Type: application/json; charset=UTF-8');
        ob_start();
        cpcc_render_customer_board($db, $projects[0]);
        $html = ob_get_clean();
        print json_encode(['board' => $html], JSON_INVALID_UTF8_SUBSTITUTE);
        return;
    }

    $route = '/chinaplanetcustomercenter/projects.php';
    $allUrl = cpcc_portal_scoped_url($route, true, $socid);
    $activeUrl = cpcc_portal_scoped_url($route, true, $socid, ['status' => 'active']);
    $css = [
        cpcc_portal_asset('/custom/chinaplanetcustomercenter/css/customer_dashboard.css'),
        cpcc_portal_asset('/custom/chinaplanetcustomercenter/css/projects.css'),
    ];
    $js = $projectId > 0 ? [cpcc_portal_asset('/custom/chinaplanetcustomercenter/js/project_board.js')] : [];
    llxHeader('', 'Projects / المشاريع', '', '', 0, 0, $js, $css);
    cpcc_portal_navigation($user, 'projects');
    print '<main class="cpcc-dashboard cpcc-projects-page" dir="rtl">';

    if ($projectId > 0) {
        $project = $projects[0];
        print '<a class="cpcc-project-back" href="'.dol_escape_htmltag($activeOnly ? $activeUrl : $allUrl).'">مشاريعي / My Projects</a>';
        print '<header class="cpcc-project-heading"><div><h1><bdi>'.dol_escape_htmltag($project->title).'</bdi></h1>';
        print '<p><bdi>'.dol_escape_htmltag($project->ref).'</bdi> · عرض فقط / Read only</p></div>';
        $statement = cpcc_portal_scoped_url('/chinaplanetcustomercenter/balance.php', true, $socid, ['projectid' => $projectId]);
        print '<a class="button cpcc-project-statement" href="'.dol_escape_htmltag($statement).'">كشف حساب المشروع / Project statement</a></header>';
        $boardUrl = cpcc_portal_scoped_url($route, true, $socid, ['project' => $projectId, 'view' => 'board']);
        print '<div data-project-board-url="'.dol_escape_htmltag($boardUrl).'">';
        cpcc_render_customer_board($db, $project);
        print '</div><p class="warning" role="status" data-project-board-offline hidden>تعذّر تحديث البورد / Board updates unavailable.</p>';
        cpcc_render_customer_project_documents($db, $project);
        cpcc_render_customer_project_shipments($db, $project);
    } else {
        print '<header class="cpcc-section-head"><h1>مشاريعي / My Projects</h1></header>';
        if ($result) {
            $active = array_filter($projects, function ($project) { return (int) $project->fk_statut === 1; });
            print '<nav class="cpcc-project-view-cards" aria-label="حالة المشاريع / Project status">';
            foreach ([[$activeUrl, 'النشطة / Active', count($active), $activeOnly], [$allUrl, 'الكل / All', count($projects), !$activeOnly]] as [$url, $label, $count, $selected]) {
                print '<a class="cpcc-project-view'.($selected ? ' active' : '').'"'.($selected ? ' aria-current="page"' : '').' href="'.dol_escape_htmltag($url).'"><span>'.$label.'</span><strong>'.$count.'</strong></a>';
            }
            print '</nav>';
            $visibleProjects = $activeOnly ? $active : $projects;
            if (!$visibleProjects) print '<p class="cpcc-empty">لا توجد مشاريع / No projects found.</p>';
            print '<div class="cpcc-project-tiles">';
            $labels = [0 => 'مسودة / Draft', 1 => 'نشط / Active', 2 => 'مغلق / Closed'];
            foreach ($visibleProjects as $project) {
                $params = ['project' => (int) $project->rowid];
                if ($activeOnly) $params['status'] = 'active';
                $url = cpcc_portal_scoped_url($route, true, $socid, $params);
                print '<a class="cpcc-project-tile" href="'.dol_escape_htmltag($url).'">';
                print '<strong><bdi>'.dol_escape_htmltag($project->title).'</bdi></strong>';
                print '<small><bdi>'.dol_escape_htmltag($project->ref).'</bdi> · '.($labels[(int) $project->fk_statut] ?? '—').'</small>';
                print '<span>افتح البورد / Open board</span></a>';
            }
            print '</div>';
        } else {
            print '<p class="cpcc-empty">تعذّر تحميل المشاريع / Projects could not be loaded.</p>';
        }
    }

    print '</main>';
    llxFooter();
}

function cpcc_render_customer_board($db, $project)
{
    $cards = cpcc_customer_board_cards($db, $project);
    $groups = ['todo' => [], 'in_progress' => [], 'done' => []];
    // Operations moves a card between lists independently of its task status.
    // Only the standard list labels have customer-facing equivalents; free-form
    // staff names remain private and continue using the native status fallback.
    $standardLists = ['to do' => 'todo', 'in progress' => 'in_progress', 'done' => 'done'];
    foreach ($cards as $card) {
        $key = $standardLists[strtolower(trim($card->board_list_title))]
            ?? (in_array($card->status, ['todo', 'done'], true) ? $card->status : 'in_progress');
        $groups[$key][] = $card;
    }

    print '<h2>بورد المشروع / Project Board</h2>';
    if (!$cards) {
        print '<p class="cpcc-empty">لا توجد مراحل منشورة لهذا المشروع / No published stages for this project.</p>';
    } else {
        $labels = ['todo' => 'للتنفيذ / To do', 'in_progress' => 'قيد العمل / In progress', 'done' => 'مكتمل / Done'];
        print '<div class="cpcc-project-board">';
        foreach ($groups as $key => $stageCards) {
            print '<section class="cpcc-project-board-stage" data-stage="'.$key.'"><h3><span>'.$labels[$key].'</span>';
            print '<span class="cpcc-stage-count" aria-label="عدد الكروت / Card count">'.count($stageCards).'</span></h3>';
            if (!$stageCards) print '<p class="opacitymedium">لا توجد كروت / No cards</p>';
            foreach ($stageCards as $card) {
                $progress = max(0, min(100, (int) $card->progress));
                print '<article class="cpcc-project-tile"><strong><bdi>'.dol_escape_htmltag($card->title).'</bdi></strong>';
                print '<small class="cpcc-card-progress"><span>التقدم / Progress</span><bdi>'.$progress.'%</bdi></small>';
                print '<progress class="cpcc-card-progress-bar" max="100" value="'.$progress.'" aria-label="التقدم / Progress">'.$progress.'%</progress></article>';
            }
            print '</section>';
        }
        print '</div>';
    }
}

function cpcc_render_customer_project_documents($db, $project)
{
    require_once __DIR__.'/export.lib.php';
    $documents = cpcc_export_documents($db, (int) $project->fk_soc, (int) $project->rowid);
    print '<h2>مستندات المشروع / Project Documents</h2>';
    if (!$documents) {
        print '<p class="cpcc-empty">لا توجد مستندات منشورة / No published documents.</p>';
        return;
    }
    print '<div class="cpcc-project-tiles">';
    foreach ($documents as $document) {
        $url = cpcc_portal_scoped_url('/chinaplanetcustomercenter/export_document.php', true, (int) $project->fk_soc, ['id' => (int) $document->rowid]);
        print '<a class="cpcc-project-tile" href="'.dol_escape_htmltag($url).'">';
        print '<strong><bdi>'.dol_escape_htmltag(cpcc_export_type_label($document->document_type)).'</bdi></strong>';
        print '<small><bdi>'.dol_escape_htmltag($document->ref).'</bdi></small></a>';
    }
    print '</div>';
}

function cpcc_render_customer_project_shipments($db, $project)
{
    $library = __DIR__.'/../../chinaplanetlogistics/lib/shipment_customer.lib.php';
    if (!is_readable($library)) return;
    require_once $library;

    $scope = ' FROM '.MAIN_DB_PREFIX.'chinaplanetlogistics WHERE entity='.(int) $project->entity
        .' AND fk_soc='.(int) $project->fk_soc.' AND fk_project='.(int) $project->rowid
        .' AND customer_visible=1 ORDER BY rowid DESC';
    $manualRef = cpl_schema_has_column($db, 'chinaplanetlogistics', 'manual_ref') ? ',manual_ref' : '';
    $result = $db->query('SELECT rowid,ref'.$manualRef.$scope);
    if (!$result || !($shipment = $db->fetch_object($result))) return;

    print '<h2>شحنات المشروع / Project Shipments</h2><div class="cpcc-project-tiles">';
    do {
        $url = cpcc_portal_scoped_url('/chinaplanetlogistics/card.php', true, (int) $project->fk_soc, ['id' => (int) $shipment->rowid]);
        print '<a class="cpcc-project-tile" href="'.dol_escape_htmltag($url).'"><strong><bdi>'
            .dol_escape_htmltag(cpl_shipment_display_ref((array) $shipment)).'</bdi></strong></a>';
    } while ($shipment = $db->fetch_object($result));
    print '</div>';
}
