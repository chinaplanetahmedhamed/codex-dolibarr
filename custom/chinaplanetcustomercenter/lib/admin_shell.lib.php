<?php
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
        $items = array(
            'dashboard'=>array('/chinaplanetcustomercenter/admin_dashboard.php', 'fa-chart-line', 'Dashboard / الرئيسية'),
            'website_requests'=>array('/chinaplanetoperations/operations/public_requests.php', 'fa-inbox', 'Website Requests / طلبات الموقع'),
            'portal'=>array('/chinaplanetcustomercenter/board.php', 'fa-users', 'Customer Center / مركز العملاء'),
            'shipping'=>array('/chinaplanetlogistics/index.php', 'fa-ship', 'Shipping Center / مركز الشحن'),
            'documents'=>array('/chinaplanetcustomercenter/documents.php', 'fa-folder-open', 'Documents / المستندات'),
            'reports'=>array('/chinaplanetcustomercenter/operation_reports.php', 'fa-clipboard-check', 'Reports / التقارير'),
            'sop'=>array('/chinaplanetshippingprices/sop/index.php', 'fa-book-open', 'SOP Library / مكتبة الإجراءات'),
            'operations'=>array('/chinaplanetcustomercenter/admin/operations.php', 'fa-route', 'Operations / العمليات'),
            'financial'=>array('/chinaplanetcustomercenter/admin/financial_portfolio.php', 'fa-wallet', 'Financial Center / المركز المالي'),
        );

        // Older shipping pages still pass their former section key. Keep those
        // calls compatible while presenting one clear Shipping Center entry.
        $activeAliases = array('tracking'=>'shipping', 'shippingprices'=>'shipping', 'shipments'=>'shipping');
        $activeKey = isset($activeAliases[$active]) ? $activeAliases[$active] : $active;
        $currentItem = isset($items[$activeKey]) ? $items[$activeKey] : array('', 'fa-bars', 'Navigation / القائمة');
        $navId = 'cpad-nav-links-'.substr(md5((string) $activeKey.'|'.serialize($context)), 0, 8);

        print '<nav class="cpad-nav" data-cpad-mobile-nav aria-label="Customer Center navigation">';
        print '<button type="button" class="cpad-nav-toggle" aria-expanded="false" aria-controls="'.dol_escape_htmltag($navId).'">';
        print '<span class="cpad-nav-current"><span class="fas '.dol_escape_htmltag($currentItem[1]).'"></span>';
        print '<span><small>Current section / القسم الحالي</small><strong>'.dol_escape_htmltag($currentItem[2]).'</strong></span></span>';
        print '<span class="cpad-nav-toggle-icon fas fa-chevron-down" aria-hidden="true"></span>';
        print '</button>';
        print '<div class="cpad-nav-links" id="'.dol_escape_htmltag($navId).'">';
        foreach ($items as $key=>$item) {
            $class = $key === $activeKey ? ' class="active"' : '';
            $ariaCurrent = $key === $activeKey ? ' aria-current="page"' : '';
            print '<a'.$class.$ariaCurrent.' href="'.dol_escape_htmltag(cpcc_admin_shell_url($item[0], $context)).'">';
            print '<span class="fas '.$item[1].'"></span> '.dol_escape_htmltag($item[2]).'</a>';
        }
        if (in_array($activeKey, array('dashboard', 'shipping'), true)) {
            $newShipmentUrl = cpcc_admin_shell_url('/chinaplanetlogistics/card.php', $context);
            $newShipmentUrl .= (strpos($newShipmentUrl, '?') === false ? '?' : '&').'action=create';
            print '<a class="new" href="'.dol_escape_htmltag($newShipmentUrl).'">';
            print '<span class="fas fa-plus-circle"></span> New Shipment / شحنة جديدة</a>';
        }
        print '</div>';
        print '</nav>';
        print '<script>(function(){var nav=document.currentScript.previousElementSibling;if(!nav||!nav.matches("[data-cpad-mobile-nav]"))return;var button=nav.querySelector(".cpad-nav-toggle");if(!button)return;function closeNav(focus){nav.classList.remove("is-open");button.setAttribute("aria-expanded","false");if(focus)button.focus();}button.addEventListener("click",function(){var open=!nav.classList.contains("is-open");nav.classList.toggle("is-open",open);button.setAttribute("aria-expanded",open?"true":"false");});nav.addEventListener("keydown",function(event){if(event.key==="Escape")closeNav(true);});nav.querySelectorAll(".cpad-nav-links a").forEach(function(link){link.addEventListener("click",function(){closeNav(false);});});document.addEventListener("click",function(event){if(!nav.contains(event.target))closeNav(false);});})();</script>';
    }
}

if (!function_exists('cpcc_admin_shell_header')) {
    function cpcc_admin_shell_header($compact = false)
    {
        $environmentLabel = function_exists('getDolGlobalString') ? trim((string) getDolGlobalString('CPCC_ENVIRONMENT_LABEL')) : '';
        if ($environmentLabel === '') $environmentLabel = 'ENVIRONMENT NOT SET';
        print '<header class="cpad-head'.($compact ? ' compact' : '').'">';
        print '<h1>VISION TRADE OPERATIONS</h1>';
        print '<p>China Planet · Vision System Command Center</p>';
        print '<div class="cpad-environment"><i aria-hidden="true"></i><span><strong>'.dol_escape_htmltag($environmentLabel).'</strong><small>Generated '.dol_escape_htmltag(date('Y-m-d H:i')).'</small></span></div>';
        print '</header>';
    }
}

if (!function_exists('cpcc_admin_shell_open')) {
    function cpcc_admin_shell_open($active, $title, $subtitle = '', $icon = 'fa-layer-group', $context = array())
    {
        print '<div class="cpad-shell cpad-section-shell">';
        cpcc_admin_shell_navigation($active, $context);
        cpcc_admin_shell_header(true);
        print '<section class="cpad-section-heading">';
        print '<div class="cpad-section-icon"><span class="fas '.dol_escape_htmltag($icon).'"></span></div>';
        print '<div><h2>'.dol_escape_htmltag($title).'</h2>';
        if ($subtitle !== '') print '<p>'.dol_escape_htmltag($subtitle).'</p>';
        print '</div></section>';
        print '<div class="cpad-section-content">';
    }
}

if (!function_exists('cpcc_admin_shell_close')) {
    function cpcc_admin_shell_close()
    {
        print '</div></div>';
    }
}
