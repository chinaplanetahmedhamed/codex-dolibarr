<?php
/** Source and security contract for M045 public request quotation workspace. */
$root = dirname(__DIR__, 2);
$read = function ($path) use ($root) { $data = file_get_contents($root.'/'.$path); if ($data === false) throw new RuntimeException('Unreadable '.$path); return $data; };
$assert = function ($condition, $message) { if (!$condition) throw new RuntimeException($message); };
try {
    $intake = $read('custom/chinaplanetoperations/class/PublicWebsiteRequestService.class.php');
    $quotation = $read('custom/chinaplanetoperations/class/PublicRequestQuotationService.class.php');
    $page = $read('custom/chinaplanetoperations/operations/public_requests.php');
    $schema = $read('custom/chinaplanetoperations/sql/llx_cpo_public_request.sql');
    $module = $read('custom/chinaplanetoperations/core/modules/modChinaPlanetOperations.class.php');
    $migration = $read('deploy/migrations/m045_public_request_quotation_schema.php');
    $css = $read('custom/chinaplanetoperations/css/operations.css');

    $assert(strpos($intake, 'new Propal(') === false, 'anonymous intake creates a quotation automatically');
    $assert(strpos($quotation, "require_once DOL_DOCUMENT_ROOT.'/comm/propal/class/propal.class.php'") !== false, 'standard Propal class is not used');
    $assert(strpos($quotation, 'new Propal($this->db)') !== false, 'standard draft proposal creation absent');
    $assert(strpos($quotation, 'Propal::STATUS_DRAFT') !== false, 'non-draft proposal mutation is not blocked');
    $assert(strpos($quotation, "GET_LOCK('") !== false && strpos($quotation, "RELEASE_LOCK('") !== false, 'per-request duplicate creation lock absent');
    $assert(strpos($schema, 'fk_propal bigint NULL') !== false && strpos($schema, 'UNIQUE KEY uk_cpo_public_request_propal (entity, fk_propal)') !== false, 'idempotent proposal link schema absent');
    $assert(strpos($migration, 'GRANTS=EXPLICIT') !== false, 'quotation financial right must not be granted implicitly');
    $assert(strpos($module, 'publicrequest_quote') !== false && strpos($page, "cpo_require(\$user, 'publicrequest_quote')") !== false, 'dedicated quotation permission absent');
    $assert(strpos($page, "hasRight('propal', 'creer')") !== false && strpos($page, "hasRight('product', 'read')") !== false, 'core proposal/product permissions absent');
    $assert(strpos($page, "define('CSRFCHECK_WITH_TOKEN', 1)") !== false && strpos($page, 'name="token"') !== false, 'CSRF protection absent');
    $assert(strpos($page, 'cpo-quote-table') !== false && strpos($page, 'cpo_public_request_product_image_html') !== false, 'structured product table or image absent');
    $assert(strpos($css, 'M045') !== false && strpos($css, '@media(max-width:430px)') !== false, 'responsive M045 UI contract absent');
    $assert(strpos($quotation, "getDolGlobalString('CHINAPLANET_PUBLIC_PRODUCT_IMAGE_HOSTS')") !== false && strpos($quotation, "!== 'https'") !== false, 'remote product image allowlist absent');
    $assert(strpos($quotation, 'cp_woo_variation_id') !== false && strpos($quotation, 'p.ref=') !== false, 'server-side product mapping fallbacks absent');
    $assert(strpos($quotation, "array('dolibarr','manual')") !== false && strpos($quotation, 'quotation_manual_price_invalid') !== false, 'controlled selling price choice absent');
    $assert(strpos($quotation, 'MultiCurrency::getIdAndTxFromCode') !== false && strpos($quotation, '$priceBase = $priceCurrency / $currencyInfo') !== false, 'multicurrency base conversion absent');
    $assert(strpos($quotation, 'note_public') !== false && strpos($quotation, 'note_private') !== false, 'public/private note separation absent');
    $assert(strpos($page, '/comm/propal/card.php?id=') !== false, 'standard preview/validate/send handoff absent');
    foreach (array('supplier_cost','cost_price','buying_price','pa_ht','fk_fournprice','base_margin') as $forbidden) {
        $assert(stripos($page, $forbidden) === false, 'staff intake page exposes private cost field '.$forbidden);
        $assert(stripos($quotation, $forbidden) === false, 'quotation bridge handles forbidden private cost '.$forbidden);
    }
    $assert(strpos($module, "'cp_woo_product_id'") !== false && strpos($module, "'cp_woo_variation_id'") !== false, 'Dolibarr product mapping fields absent');
    echo "M045_SOURCE=PASS propal=STANDARD idempotency=LOCKED pricing=SELL_ONLY grants=EXPLICIT\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'M045_SOURCE=FAIL REASON='.$e->getMessage()."\n");
    exit(1);
}
