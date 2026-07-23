<?php
/** Source-contract test for M036 internal Shipping Orders projections. */
$root = dirname(__DIR__, 2);
$read = function ($path) use ($root) { $data=file_get_contents($root.'/'.$path); if($data===false) throw new RuntimeException('Unreadable '.$path); return $data; };
$assert = function ($condition, $message) { if (!$condition) throw new RuntimeException($message); };

try {
    $service = $read('custom/chinaplanetoperations/class/OperationsService.class.php');
    $page = $read('custom/chinaplanetoperations/operations/shipping_orders.php');
    $css = $read('custom/chinaplanetoperations/css/shipping_orders.css');
    $shippingCenter = $read('custom/chinaplanetlogistics/index.php');
    $board = $read('custom/chinaplanetcustomercenter/board.php');
    $dashboard = $read('custom/chinaplanetcustomercenter/admin_dashboard.php');
    $layout = $read('custom/chinaplanetcustomercenter/ajax/dashboard_layout.php');

    $assert(strpos($page, "cpo_require(\$user, 'read')") !== false, 'internal route permission missing');
    $assert(strpos($page, "cpo_require(\$user, 'read')") < strpos($page, 'searchShippingOrders('), 'route queries before permission check');
    $assert(strpos($page, "cpo_can(\$user, 'operation_write')") !== false, 'write link not independently permission-gated');
    $assert(strpos($page, 'shippingOrderStats($socid, false, $query)') !== false, 'register KPI counts do not follow customer/text filters');
    $assert(strpos($shippingCenter, "if (cpo_can(\$user, 'read'))") !== false, 'Shipping Center launcher not permission-gated');
    $assert(strpos($board, "\$canViewShippingOrders = !\$is_external && cpo_can(\$user, 'read')") !== false, 'Customer Center projection not internal and permission gated');
    $assert(strpos($board, 'cpcc_portal_customer_scope_id($db, $user, GETPOSTINT(\'socid\'))') !== false, 'selected customer is not validated by shared scope helper');

    $assert(strpos($service, 'SELECT o.rowid,o.ref,o.fk_soc,o.stage,o.next_action,o.date_creation,o.fk_shipment') !== false, 'dedicated allowlisted projection absent');
    $assert(strpos($service, 'SELECT o.*') === false || strpos($service, 'searchShippingOrders') > strpos($service, 'SELECT o.*'), 'Shipping Orders projection reuses broad o.* selection');
    foreach (array(
        'o.entity=',
        "o.status=1",
        "o.service_type='shipping_booking'",
        'b.entity=o.entity AND b.fk_operation=o.rowid',
        'l.rowid=o.fk_shipment AND l.entity=o.entity AND l.fk_soc=o.fk_soc',
        's.rowid=o.fk_soc AND s.entity=o.entity',
        '(b.rowid IS NULL OR b.fk_soc=o.fk_soc)',
        'COUNT(DISTINCT o.rowid)',
    ) as $needle) $assert(strpos($service, $needle) !== false, 'scoped query contract absent '.$needle);
    $assert(strpos($service, "\$stage.\"='published'") !== false, 'published confirmation stage missing');
    $assert(strpos($service, "JSON_VALID(COALESCE(") !== false && strpos($service, ",'{}')") !== false, 'legacy malformed JSON is not safely normalized before bucket extraction');
    $assert(strpos($service, "IN ('accepted','confirmed')") !== false, 'explicit booking confirmation state missing');
    $assert(strpos($service, "shipping_order_no") !== false && strpos($service, "carrier_booking_no") !== false, 'Carrier SO/booking evidence missing');
    $assert(strpos($service, "'confirmation_incomplete'") !== false, 'incomplete confirmation warning missing');
    $assert(strpos($service, "'integrity_exception'") !== false, 'ownership integrity projection missing');
    $assert(strpos($service, "'accepted_total'=>\$hasAcceptedEvidence") !== false, 'accepted price is not gated by immutable evidence');
    $assert(substr_count($page, 'Shipment ownership mismatch') >= 2, 'shipment ownership warning is not present in table and responsive card');

    foreach (array('input_json','snapshot_json','rate_fingerprint','terms_hash','idempotency_key','request_hash','forwarder','note_private','supplier_cost','cost_price','margin','source_sheet','batch_ref','rule_id') as $forbidden) {
        $assert(strpos($page, $forbidden) === false, 'internal page renders or names forbidden payload '.$forbidden);
    }
    $assert(stripos($page, 'Succeeded') === false && stripos($dashboard, 'Succeeded') === false, 'misleading Succeeded label present');
    foreach (array('Total Current','Active','Confirmed / SO Received','Closed','Cancelled') as $label) $assert(strpos($page, $label) !== false, 'missing list KPI '.$label);
    $assert(strpos($dashboard, 'data-cpad-widget="shipping_orders"') !== false, 'dashboard Shipping Orders widget absent');
    $assert(strpos($layout, "'shipping_orders'") !== false, 'dashboard server widget allowlist missing Shipping Orders');
    foreach (array('kpi_projects','kpi_alerts','attention_center','recent_orders','shipping_prices') as $widget) $assert(strpos($layout, "'".$widget."'") !== false, 'existing dashboard widget not preserved '.$widget);

    foreach (array('@media(max-width:1100px)','@media(max-width:760px)','@media(max-width:390px)','min-height:44px','.cpos-cards{display:none}','.cpos-table-wrap{display:none}','grid-template-columns:repeat(5') as $contract) {
        $assert(strpos($css, $contract) !== false, 'responsive/accessibility contract absent '.$contract);
    }
    $assert(strpos($page, '<bdi dir="ltr">') !== false && strpos($page, 'dir="auto"') !== false, 'bidi isolation absent');
    $assert(strpos($page, 'scope="col"') !== false && strpos($page, 'aria-live="polite"') !== false && strpos($page, 'role="alert"') !== false, 'table/live/error accessibility absent');

    echo "M036_SOURCE=PASS permission=OPERATIONS_READ projection=ALLOWLIST buckets=EXHAUSTIVE dashboard_layout=COMPATIBLE responsive=320-1440\n";
} catch (Throwable $e) {
    fwrite(STDERR, "M036_SOURCE=FAIL REASON=".$e->getMessage()."\n");
    exit(1);
}
