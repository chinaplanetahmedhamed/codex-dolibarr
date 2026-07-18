<?php
/** Synthetic, database-free acceptance checks for M010. */

function m010Assert($condition, $message)
{
    if (!$condition) throw new RuntimeException('FAIL '.$message);
    print 'PASS '.$message.PHP_EOL;
}

function m010Column($id, $label, $labelEn, $position, $color)
{
    return (object) array('rowid'=>$id, 'label'=>$label, 'label_en'=>$labelEn, 'position'=>$position, 'color'=>$color);
}

$repo = dirname(__DIR__, 2);
require_once $repo.'/custom/cpkanban/lib/customer_project_center.lib.php';

$columns = array(
    10 => m010Column(10, 'الطلب', 'Inquiry', 10, '#111111'),
    20 => m010Column(20, 'قيد التنفيذ', 'In progress', 20, '#C0392B'),
    30 => m010Column(30, 'الشحن', 'Shipping', 30, '#D4A017'),
    40 => m010Column(40, 'التسليم', 'Delivered', 40, '#0D1B3E'),
);
$timeline = cpkanban_customer_timeline($columns, 20);
m010Assert($timeline[0]['state'] === 'done', 'timeline marks earlier stages complete');
m010Assert($timeline[1]['state'] === 'current', 'timeline marks the owned card stage current');
m010Assert($timeline[2]['state'] === 'future' && $timeline[3]['state'] === 'future', 'timeline leaves later stages in the future');
$next = cpkanban_customer_next_timeline_step($timeline);
m010Assert($next !== null && $next['label_en'] === 'Shipping', 'next step is derived from the next customer-visible stage');

$totals = cpkanban_customer_invoice_totals(array(
    array('currency'=>'USD', 'total'=>100.0, 'paid'=>25.0, 'remaining'=>75.0),
    array('currency'=>'USD', 'total'=>40.0, 'paid'=>40.0, 'remaining'=>0.0),
    array('currency'=>'RMB', 'total'=>700.0, 'paid'=>100.0, 'remaining'=>600.0),
));
m010Assert($totals['USD']['total'] === 140.0 && $totals['USD']['paid'] === 65.0 && $totals['USD']['remaining'] === 75.0, 'invoice totals aggregate inside one currency');
m010Assert($totals['RMB']['total'] === 700.0 && count($totals) === 2, 'invoice totals never net different currencies');

$helper = file_get_contents($repo.'/custom/cpkanban/lib/customer_project_center.lib.php');
$asset = file_get_contents($repo.'/custom/cpkanban/asset.php');
$card = file_get_contents($repo.'/custom/cpkanban/card.php');
$cardClass = file_get_contents($repo.'/custom/cpkanban/class/cpkanbancard.class.php');
$projects = file_get_contents($repo.'/custom/chinaplanetcustomercenter/projects.php');
$documents = file_get_contents($repo.'/custom/chinaplanetcustomercenter/documents.php');
$orders = file_get_contents($repo.'/custom/chinaplanetcustomercenter/orders.php');
$boardJs = file_get_contents($repo.'/custom/cpkanban/js/board.js');
$financial = file_get_contents($repo.'/custom/chinaplanetcustomercenter/lib/financial.lib.php');
$export = file_get_contents($repo.'/custom/chinaplanetcustomercenter/lib/export.lib.php');
$css = file_get_contents($repo.'/custom/cpkanban/css/board.css');
$portal = file_get_contents($repo.'/custom/chinaplanetcustomercenter/lib/portal.lib.php');
$portalCss = file_get_contents($repo.'/custom/chinaplanetcustomercenter/css/portal.css');
$portalJs = file_get_contents($repo.'/custom/chinaplanetcustomercenter/js/portal.js');

foreach (array(
    'a.entity=', 'a.fk_soc=', 'a.fk_card=', 'a.customer_visible=1', "a.review_status IN ('approved','final_received')",
    'l.entity=', 'l.fk_soc=', 'l.fk_project=', 'c.entity=', 'c.fk_soc=', 'c.fk_projet=',
) as $requiredScope) {
    m010Assert(strpos($helper, $requiredScope) !== false, 'aggregation includes scope: '.$requiredScope);
}
m010Assert(strpos($asset, '(int) $user->socid') !== false && strpos($asset, 'customer_visible') !== false && strpos($asset, 'final_received') !== false, 'direct asset route requires authenticated ownership and publication approval');
m010Assert(strpos($asset, "strpos(\$path, rtrim(\$root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR) !== 0") !== false, 'direct asset route confines files to the card directory');
m010Assert(strpos($cardClass, 'AND entity = ".$entity') !== false && strpos($cardClass, 'l.entity = ".$entity') !== false, 'card and activity reads are entity scoped');
m010Assert(strpos($cardClass, 'AND entity = ".(int) $conf->entity') !== false, 'legacy single-shipment read is entity scoped');
m010Assert(strpos($financial, 'p.entity=f.entity AND p.fk_soc=f.fk_soc') !== false, 'invoice project joins require matching entity and customer');
m010Assert(strpos($export, 'p.entity=d.entity AND p.fk_soc=d.fk_soc') !== false && strpos($export, 'l.entity=d.entity AND l.fk_soc=d.fk_soc') !== false, 'commercial-document joins require matching entity and customer');

foreach (array('cpcc_financial_project_charges', 'cpcc_financial_commitment_rows', 'note_private', 'cpcc_financial_statement') as $forbiddenSource) {
    m010Assert(strpos($helper, $forbiddenSource) === false, 'customer command center excludes '.$forbiddenSource);
}
m010Assert(strpos($helper, 'cpcc_financial_customer_invoices') !== false && strpos($helper, 'cpkanban_customer_invoice_totals') !== false, 'financial view uses customer invoices and per-currency totals');
m010Assert(strpos($helper, "method=\"") === false && strpos($helper, '<form') === false, 'phase-one command center is read only');
m010Assert(strpos($card, 'cpkanban_render_customer_project_center') !== false && strpos($card, 'if ($is_external)') !== false, 'customer view uses the command-center renderer');
m010Assert(strpos($card, 'if ($displayColumnId <= 0)') !== false, 'direct customer access to deliberately hidden columns fails closed');
m010Assert(strpos($card, "if (\$action == 'update' && \$canwrite") !== false, 'internal edit behavior remains available');
m010Assert(strpos($projects, 'if (!$is_external) {'.PHP_EOL.'        foreach ($projectIds as $projectId)') !== false, 'external project listing performs no automatic write-side synchronization');
m010Assert(strpos($projects, 'cc.entity=p.entity AND cc.fk_soc=p.fk_soc') !== false && strpos($projects, 'c.entity=p.entity AND c.fk_soc=p.fk_soc') !== false, 'project-to-card joins require matching entity and customer');
m010Assert(strpos($projects, 'class="cpk-project-modal-host"') !== false && strpos($projects, 'class="cpk-card-link"') !== false && strpos($boardJs, '.cpk-project-modal-host') !== false, 'customer project list opens the command center in the accessible modal');
m010Assert(strpos($projects, 'class="cpcc-page-head"') !== false && strpos($documents, 'class="cpcc-page-head"') !== false && strpos($orders, 'class="cpcc-page-head"') !== false, 'projects documents and orders share the customer portal hero component');
m010Assert(strpos($projects, 'customer_dashboard.css') !== false && strpos($documents, 'customer_dashboard.css') !== false, 'projects and documents load the shared customer hero stylesheet');

foreach (array('cpk-project-timeline', 'cpk-project-gallery', 'cpk-shipment-grid', 'cpk-file-grid', 'cpk-finance-summary') as $section) {
    m010Assert(strpos($helper, $section) !== false && strpos($css, '.'.$section) !== false, 'responsive section is rendered and styled: '.$section);
}
m010Assert(strpos($css, '@media(max-width:760px)') !== false && strpos($css, 'grid-template-columns:1fr') !== false, 'command center has a single-column mobile layout');
m010Assert(strpos($portal, 'cpcc-portal-menu-toggle') !== false && strpos($portal, 'aria-controls="cpcc-portal-menu"') !== false, 'customer portal renders an accessible mobile menu toggle');
m010Assert(strpos($portalCss, '@media(max-width:900px)') !== false && strpos($portalCss, '.cpcc-portal-nav.is-open{display:grid}') !== false, 'customer portal menu collapses into a mobile grid');
m010Assert(strpos($portalJs, "toggle.setAttribute('aria-expanded', open ? 'true' : 'false')") !== false && strpos($portalJs, "event.key === 'Escape'") !== false, 'customer portal menu supports state announcement and keyboard close');

print 'M010 customer project command-center tests passed'.PHP_EOL;
