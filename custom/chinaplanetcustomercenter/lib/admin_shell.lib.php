<?php
require_once __DIR__.'/ui.lib.php';
cpcc_ui_initialize_language();
/**
 * Shared internal ChinaPlanet operations shell.
 *
 * This file deliberately contains presentation helpers only. Access control
 * remains the responsibility of each destination page.
 */

if (!function_exists('cpcc_admin_shell_url')) {
    function cpcc_admin_shell_url($path, $context = array())
    {
        $url = dol_buildpath($path, 1);
        $query = array();
        if (cpcc_ui_language_parameter() !== '') $query['lang'] = cpcc_ui_language_parameter();
        foreach (array('socid', 'projectid') as $key) {
            if (!empty($context[$key])) $query[$key] = (int) $context[$key];
        }
        return $url.(!empty($query) ? '?'.http_build_query($query) : '');
    }
}

if (!function_exists('cpcc_admin_shell_asset')) {
    function cpcc_admin_shell_asset($path)
    {
        $fullPath = DOL_DOCUMENT_ROOT.$path;
        // Version shared assets by their contents so deployments that preserve
        // file timestamps cannot leave every admin page on stale cached CSS.
        $version = is_file($fullPath) ? substr((string) hash_file('sha256', $fullPath), 0, 16) : '1';
        return $path.'?v='.rawurlencode($version);
    }
}

if (!function_exists('cpcc_admin_shell_navigation')) {
    function cpcc_admin_shell_navigation($active = '', $context = array())
    {
        global $user;
        $taskOnly = empty($user->admin) && empty($user->socid) && $user->hasRight('chinaplanetoperations', 'task_read_own') && !$user->hasRight('chinaplanetoperations', 'read');
        cpcc_ui_assets();
        cpcc_ui_language_controls();
        // A stable home destination, independent of browser history or customer context.
        $onToday = basename($_SERVER['SCRIPT_NAME'] ?? '') === 'workspace.php' && in_array(GETPOST('view', 'aZ09'), array('', 'my', 'manager'), true);
        if (!$onToday && !empty($user->id) && empty($user->socid) && (!empty($user->admin) || $taskOnly || $user->hasRight('chinaplanetoperations', 'read'))) {
            print '<a class="cpad-mobile-home" href="'.dol_escape_htmltag(cpcc_admin_shell_url('/chinaplanetoperations/workspace.php')).'"><span class="fas fa-home" aria-hidden="true"></span> '.dol_escape_htmltag(cpcc_ui_t('UIBackToToday')).'</a>';
        }
        $items = array(
            'workspace'=>array('/chinaplanetoperations/workspace.php', 'fa-compass', cpcc_ui_t('UIWorkspace4ca0a'), cpcc_ui_t('UIToday24345')),
            'dashboard'=>array('/chinaplanetcustomercenter/admin_dashboard.php', 'fa-chart-line', cpcc_ui_t('UIDashboardd87f4'), cpcc_ui_t('UIHome70f8b')),
            'website_requests'=>array('/chinaplanetoperations/operations/public_requests.php', 'fa-inbox', cpcc_ui_t('UIWebsiteRequests430ce'), cpcc_ui_t('UIRequestsf7194')),
            'portal'=>array('/chinaplanetcustomercenter/board.php', 'fa-users', cpcc_ui_t('UICustomerCenterb896b'), cpcc_ui_t('UICustomers035ea')),
            'shipping'=>array('/chinaplanetlogistics/index.php', 'fa-ship', cpcc_ui_t('UIShippingCenter2ae07'), cpcc_ui_t('UIShipping694e6')),
            'documents'=>array('/chinaplanetcustomercenter/documents.php', 'fa-folder-open', cpcc_ui_t('UIDocuments687c8')),
            'reports'=>array('/chinaplanetcustomercenter/operation_reports.php', 'fa-clipboard-check', cpcc_ui_t('UIReports88bc3')),
            'sop'=>array('/chinaplanetoperations/sop/index.php', 'fa-book-open', cpcc_ui_t('UISopLibrary0de43')),
            'operations'=>array('/chinaplanetcustomercenter/admin/operations.php', 'fa-route', cpcc_ui_t('UIOperationsa1fda'), cpcc_ui_t('UIOperationsa1fda')),
            'tasks'=>array('/chinaplanetoperations/tasks/board.php', 'fa-tasks', cpcc_ui_t('UITeamTasksdb625')),
            'financial'=>array('/chinaplanetcustomercenter/admin/financial_portfolio.php', 'fa-wallet', cpcc_ui_t('UIFinancialCenterfe19b'), cpcc_ui_t('UIFinance1b48d')),
        );

        $primaryKeys = array('workspace', 'website_requests', 'portal', 'operations', 'shipping', 'financial');
        $groups = array(
            'work'=>array('fa-briefcase', cpcc_ui_t('UIWork00040'), array('tasks', 'sop')),
            'records'=>array('fa-folder-open', cpcc_ui_t('UIMore4bab2'), array('dashboard', 'documents', 'reports')),
        );

        if ($taskOnly) {
            $primaryKeys = array('workspace', 'tasks'); $groups = array();
            $items['tasks'][2] = cpcc_ui_t('UIMyTasks');
            $items['workspace'][2] = $items['workspace'][3] = cpcc_ui_t('UIMyDay');
        }
        if (function_exists('getDolGlobalInt') && getDolGlobalInt('CPO_BOARDS_ENABLED')) {
            $items['tasks'][0] = '/chinaplanetoperations/tasks/boards.php';
            $items['tasks'][2] = cpcc_ui_t('UIBoards');
        }

        // Older shipping pages still pass their former section key. Keep those
        // calls compatible while presenting one clear Shipping Center entry.
        $activeAliases = array('tracking'=>'shipping', 'shippingprices'=>'shipping', 'shipments'=>'shipping');
        $activeKey = isset($activeAliases[$active]) ? $activeAliases[$active] : $active;
        $currentItem = isset($items[$activeKey]) ? $items[$activeKey] : array('', 'fa-bars', cpcc_ui_t('UINavigationcf03c'));
        $navId = 'cpad-nav-links-'.substr(md5((string) $activeKey.'|'.serialize($context)), 0, 8);

        print '<nav class="cpad-nav" data-cpad-mobile-nav aria-label="Customer Center navigation">';
        print '<button type="button" class="cpad-nav-toggle" aria-expanded="false" aria-controls="'.dol_escape_htmltag($navId).'">';
        print '<span class="cpad-nav-current"><span class="fas '.dol_escape_htmltag($currentItem[1]).'"></span>';
        print '<span><small>' . dol_escape_htmltag(cpcc_ui_t('UICurrentSection0cf15')) . '</small><strong>'.dol_escape_htmltag($currentItem[2]).'</strong></span></span>';
        print '<span class="cpad-nav-toggle-icon fas fa-chevron-down" aria-hidden="true"></span>';
        print '</button>';
        print '<div class="cpad-nav-links" id="'.dol_escape_htmltag($navId).'">';
        foreach ($primaryKeys as $key) {
            $item = $items[$key];
            $class = $key === $activeKey ? ' class="active"' : '';
            $ariaCurrent = $key === $activeKey ? ' aria-current="page"' : '';
            print '<a'.$class.$ariaCurrent.' href="'.dol_escape_htmltag(cpcc_admin_shell_url($item[0], $context)).'">';
            print '<span class="fas '.$item[1].'"></span> '.dol_escape_htmltag(isset($item[3]) ? $item[3] : $item[2]).'</a>';
        }
        foreach ($groups as $groupKey=>$group) {
            $groupActive = in_array($activeKey, $group[2], true);
            $groupId = $navId.'-'.$groupKey;
            print '<div class="cpad-nav-group'.($groupActive ? ' active' : '').'" data-cpad-nav-group>';
            print '<button type="button" class="cpad-nav-group-toggle" aria-expanded="false" aria-controls="'.dol_escape_htmltag($groupId).'">';
            print '<span class="fas '.dol_escape_htmltag($group[0]).'"></span> '.dol_escape_htmltag($group[1]);
            print '<span class="fas fa-chevron-down cpad-nav-group-chevron" aria-hidden="true"></span></button>';
            print '<div class="cpad-nav-dropdown" id="'.dol_escape_htmltag($groupId).'">';
            foreach ($group[2] as $key) {
                $item = $items[$key];
                $class = $key === $activeKey ? ' class="active"' : '';
                $ariaCurrent = $key === $activeKey ? ' aria-current="page"' : '';
                print '<a'.$class.$ariaCurrent.' href="'.dol_escape_htmltag(cpcc_admin_shell_url($item[0], $context)).'">';
                print '<span class="fas '.$item[1].'"></span><span><strong>'.dol_escape_htmltag($item[2]).'</strong></span></a>';
            }
            print '</div></div>';
        }
        $newShipmentUrl = cpcc_admin_shell_url('/chinaplanetlogistics/card.php', $context);
        $newShipmentUrl .= (strpos($newShipmentUrl, '?') === false ? '?' : '&').'action=create';
        if (!empty($user->admin) || $user->hasRight('chinaplanetlogistics', 'write')) {
        print '<a class="new" href="'.dol_escape_htmltag($newShipmentUrl).'">';
        print '<span class="fas fa-plus-circle"></span>' . dol_escape_htmltag(cpcc_ui_t('UINewShipmentd3340')) . '</a>';
        }
        print '</div>';
        print '</nav>';
        print '<script>(function(){var nav=document.currentScript.previousElementSibling;if(!nav||!nav.matches("[data-cpad-mobile-nav]"))return;var button=nav.querySelector(".cpad-nav-toggle");if(!button)return;var groups=Array.prototype.slice.call(nav.querySelectorAll("[data-cpad-nav-group]"));function closeGroups(except){groups.forEach(function(group){if(group===except)return;group.classList.remove("is-open");var toggle=group.querySelector(".cpad-nav-group-toggle");if(toggle)toggle.setAttribute("aria-expanded","false");});}function closeNav(focus){nav.classList.remove("is-open");button.setAttribute("aria-expanded","false");closeGroups();if(focus)button.focus();}button.addEventListener("click",function(){var open=!nav.classList.contains("is-open");nav.classList.toggle("is-open",open);button.setAttribute("aria-expanded",open?"true":"false");});groups.forEach(function(group){var toggle=group.querySelector(".cpad-nav-group-toggle");if(!toggle)return;toggle.addEventListener("click",function(event){event.stopPropagation();var open=!group.classList.contains("is-open");closeGroups(group);group.classList.toggle("is-open",open);toggle.setAttribute("aria-expanded",open?"true":"false");});});nav.addEventListener("keydown",function(event){if(event.key==="Escape"){closeGroups();closeNav(true);}});nav.querySelectorAll(".cpad-nav-links a").forEach(function(link){link.addEventListener("click",function(){closeNav(false);});});document.addEventListener("click",function(event){if(!nav.contains(event.target))closeNav(false);else if(!event.target.closest("[data-cpad-nav-group]"))closeGroups();});})();</script>';
    }
}

if (!function_exists('cpcc_admin_shell_header')) {
    function cpcc_admin_shell_header($compact = false)
    {
        $environmentLabel = function_exists('getDolGlobalString') ? trim((string) getDolGlobalString('CPCC_ENVIRONMENT_LABEL')) : '';
        if ($environmentLabel === '') $environmentLabel = 'ENVIRONMENT NOT SET';
        print '<div class="cpad-head'.($compact ? ' compact' : '').'">';
        print '<h1>' . dol_escape_htmltag(cpcc_ui_t('UIVisionTradeOperationsc4017')) . '</h1>';
        print '<p>' . dol_escape_htmltag(cpcc_ui_t('UIChinaPlanetVisionSystemCommandCenter354b1')) . '</p>';
        print '<div class="cpad-environment"><i aria-hidden="true"></i><span><strong>'.dol_escape_htmltag($environmentLabel).'</strong><small>Generated '.dol_escape_htmltag(date('Y-m-d H:i')).'</small></span></div>';
        print '</div>';
    }
}

if (!function_exists('cpcc_admin_shell_open')) {
    function cpcc_admin_shell_open($active, $title, $subtitle = '', $icon = 'fa-layer-group', $context = array())
    {
        $flat = !empty($context['flat']);
        print '<div class="cpad-shell cpad-section-shell'.($flat?' cpad-flat-page':'').'">';
        cpcc_admin_shell_navigation($active, $context);
        if (!$flat) cpcc_admin_shell_header(true);
        if (!$flat) { print '<section class="cpad-section-heading">';
        print '<div class="cpad-section-icon"><span class="fas '.dol_escape_htmltag($icon).'"></span></div>';
        print '<div><h2>'.dol_escape_htmltag($title).'</h2>';
        if ($subtitle !== '') print '<p>'.dol_escape_htmltag($subtitle).'</p>';
        print '</div></section>'; }
        print '<div class="cpad-section-content">';
    }
}

if (!function_exists('cpcc_admin_shell_close')) {
    function cpcc_admin_shell_close()
    {
        print '</div></div>';
    }
}
