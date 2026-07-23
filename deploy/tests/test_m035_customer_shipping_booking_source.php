<?php
/** Source-contract test for M035 customer rate booking. */
$root = dirname(__DIR__, 2);
$read = function ($path) use ($root) { $data=file_get_contents($root.'/'.$path); if($data===false) throw new RuntimeException('Unreadable '.$path); return $data; };
$assert = function ($condition, $message) { if(!$condition) throw new RuntimeException($message); };
try {
    $page=$read('custom/chinaplanetshippingprices/customer_booking.php');
    $booking=$read('custom/chinaplanetshippingprices/class/CustomerShippingBookingService.class.php');
    $prices=$read('custom/chinaplanetshippingprices/class/ShippingPriceService.class.php');
    $schema=$read('custom/chinaplanetshippingprices/sql/llx_chinaplanetsp_booking.sql');
    $css=$read('custom/chinaplanetshippingprices/css/customer_booking.css');
    $portal=$read('custom/chinaplanetcustomercenter/lib/portal.lib.php');
    $portalCss=$read('custom/chinaplanetcustomercenter/css/portal.css');
    $routeguard=$read('custom/chinaplanetcustomercenter/lib/routeguard.lib.php');
    $operations=$read('custom/chinaplanetoperations/class/OperationsService.class.php');
    $migration=$read('deploy/migrations/m035_customer_shipping_booking_schema.php');
    foreach(array('supplier_cost','cost_price','base_margin','source_sheet','source_row','batch_ref','rule_id','internal_notes','note_private') as$forbidden)$assert(strpos($page,$forbidden)===false,'customer page leak token '.$forbidden);
    $assert(strpos($booking,"\$posted['fk_soc']")===false&&strpos($booking,"\$posted['entity']")===false,'client authority accepted');
    $assert(strpos($booking,"'source_channel'=>'customer_rate_booking'")!==false,'bounded operations channel absent');
    $assert(strpos($booking,"addImmutableSection(\$operationId, 'customer_rate'")!==false,'immutable rate section absent');
    $assert(strpos($schema,'UNIQUE KEY uk_chinaplanetsp_booking_key (entity, fk_soc, idempotency_key)')!==false,'idempotency unique key absent');
    $assert(strpos($schema,'UNIQUE KEY uk_chinaplanetsp_booking_operation (entity, fk_operation)')!==false,'one-operation invariant absent');
    $assert(strpos($prices,'customerEligibilitySql')!==false&&strpos($prices,'valid_from')!==false&&strpos($prices,'valid_to')!==false,'validity predicate absent');
    $assert(strpos($prices,"'20GP','40GP','40HQ'")!==false,'FCL equipment allowlist absent');
    $assert(strpos($prices,"\$rule->calc_type !== 'percent'")!==false&&strpos($prices,'currency_code')!==false,'same-currency guard absent');
    $assert(strpos($operations,'A linked shipment cannot be removed or replaced.')!==false,'shipment immutability guard absent');
    $assert(strpos($routeguard,"#^/?index\\.php$#")!==false,'module index routeguard regression');
    $assert(strpos($portal,"'shippingprices' => 'shippingprices'")!==false,'shipping prices active navigation group absent');
    $assert(strpos($portal,"array('أسعار الشحن', 'Shipping Prices', 'fa-search-dollar', '/chinaplanetshippingprices/index.php')")!==false,'shipping prices customer navigation item absent');
    $assert(strpos($portalCss,'@media(max-width:1180px)')!==false,'customer navigation responsive breakpoint absent');
    foreach(array('@media(max-width:1180px)','@media(max-width:800px)','@media(max-width:520px)','@media(max-width:350px)','font-size:16px','min-height:48px') as$contract)$assert(strpos($css,$contract)!==false,'responsive contract absent '.$contract);
    $assert(strpos($css,'.cpsb-result-list{display:grid;grid-template-columns:repeat(2,minmax(0,1fr))')!==false&&strpos($css,'@media(max-width:980px){.cpsb-result-list{grid-template-columns:1fr}}')!==false,'two-column desktop rate grid or single-column fallback absent');
    $assert(strpos($css,'--navy:#102f49;--teal:#102f49;--gold:#d9a52e')!==false,'ChinaPlanet navy and gold palette absent');
    $assert(strpos($css,'.cpsb-dialog footer .cpsb-close{display:inline-flex')!==false&&strpos($css,'flex:0 1 auto')!==false,'dialog footer action alignment correction absent');
    $assert(strpos($page,'aria-live="polite"')!==false&&strpos($page,'role="alert"')!==false,'live accessibility states absent');
    $assert(strpos($page,'crypto.getRandomValues')!==false&&strpos($page,'name="token"')!==false,'idempotency or CSRF control absent');
    $assert(strpos($page,'name="gross_weight"')===false&&strpos($page,'name="cbm"')===false,'removed weight or CBM field returned to customer form');
    $assert(strpos($booking,"\$input['gross_weight']")===false&&strpos($booking,"\$input['cbm']")===false,'removed weight or CBM still required by booking service');
    $assert(strpos($page,'Notify Party (optional)')!==false&&strpos($page,'name="notify_party" maxlength="500" required')===false,'Notify Party is not optional');
    $assert(strpos($page,'id="cpsb-total" dir="ltr"')!==false,'price calculation direction isolation absent');
    $assert(strpos($page,'<h3 dir="ltr">')!==false&&strpos($page,'<strong dir="ltr">')!==false&&strpos($page,'<span dir="ltr">')!==false,'rate route or currency direction isolation absent');
    $assert(strpos($migration,"'snapshot'")!==false&&strpos($migration,"'rollback'")!==false,'schema pre-state rollback commands absent');
    $assert(strpos($migration,'M035_SCHEMA_PRESTATE_B64')!==false&&strpos($migration,'DROP TABLE IF EXISTS')!==false,'schema rollback does not restore the captured pre-state');
    $assert(strpos($migration,'ROLLBACK_TABLE_NOT_EMPTY')!==false,'schema rollback can destroy non-empty staging booking data');
    echo "M035_SOURCE=PASS projection=ALLOWLIST authority=SESSION atomic=YES responsive=320-1920 accessibility=PASS\n";
} catch(Throwable$e) { fwrite(STDERR,"M035_SOURCE=FAIL REASON=".$e->getMessage()."\n"); exit(1); }
