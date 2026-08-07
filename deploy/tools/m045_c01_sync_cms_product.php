<?php
/** Controlled, idempotent staging sync for the M045-C01 acceptance product. */

if (PHP_SAPI !== 'cli') { fwrite(STDERR, "M045_C01_SYNC=FAIL REASON=CLI_ONLY\n"); exit(1); }
if ($argc !== 2 || !in_array($argv[1], array('check', 'apply'), true)) { fwrite(STDERR, "M045_C01_SYNC=FAIL REASON=COMMAND_NOT_ALLOWED\n"); exit(1); }

define('NOLOGIN', 1);
define('NOREQUIREMENU', 1);
define('NOREQUIREHTML', 1);
define('NOCSRFCHECK', 1);
$documentRoot = rtrim(getenv('M045_DOL_DOCUMENT_ROOT') ?: '/var/www/html', '/');
$jsonPath = getenv('M045_C01_PRODUCT_JSON');
if (!is_file($documentRoot.'/master.inc.php') || !$jsonPath || !is_file($jsonPath)) { fwrite(STDERR, "M045_C01_SYNC=FAIL REASON=INPUT_INVALID\n"); exit(1); }
$_SERVER['DOCUMENT_ROOT'] = $documentRoot;
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['SERVER_PORT'] = '80';
require $documentRoot.'/master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
require_once DOL_DOCUMENT_ROOT.'/multicurrency/class/multicurrency.class.php';
global $db, $conf;

$data = json_decode(file_get_contents($jsonPath), true);
if (!is_array($data) || (int)($data['id'] ?? 0) !== 105789 || (string)($data['sku'] ?? '') !== 'WP-9688B') { fwrite(STDERR, "M045_C01_SYNC=FAIL REASON=PRODUCT_NOT_ALLOWLISTED\n"); exit(1); }
$packing = isset($data['packing']) && is_array($data['packing']) ? $data['packing'] : array();
$required = array('unitsPerCarton', 'lengthCm', 'widthCm', 'heightCm', 'cbmPerCarton', 'netWeightKg', 'grossWeightKg');
foreach ($required as $key) if (!isset($packing[$key]) || !is_numeric($packing[$key]) || (float)$packing[$key] <= 0) { fwrite(STDERR, "M045_C01_SYNC=FAIL REASON=PACKING_INCOMPLETE\n"); exit(1); }
$unitPrice = is_numeric($data['unitPrice'] ?? null) ? (float)$data['unitPrice'] : -1;
$currency = strtoupper((string)($data['currency'] ?? ''));
if ($unitPrice < 0 || !preg_match('/^[A-Z]{3}$/', $currency)) { fwrite(STDERR, "M045_C01_SYNC=FAIL REASON=PRICE_INVALID\n"); exit(1); }

$fields = array(
    'cp_woo_product_id'=>105789, 'cp_woo_variation_id'=>0,
    'b2b_units_per_carton'=>(int)$packing['unitsPerCarton'],
    'b2b_carton_length_cm'=>(float)$packing['lengthCm'], 'b2b_carton_width_cm'=>(float)$packing['widthCm'],
    'b2b_carton_height_cm'=>(float)$packing['heightCm'], 'b2b_carton_cbm'=>(float)$packing['cbmPerCarton'],
    'b2b_carton_net_weight_kg'=>(float)$packing['netWeightKg'], 'b2b_carton_gross_weight_kg'=>(float)$packing['grossWeightKg'],
);
foreach (array_keys($fields) as $field) {
    $res = $db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."extrafields WHERE entity=".(int)$conf->entity." AND elementtype='product' AND name='".$db->escape($field)."'");
    if (!$res || $db->num_rows($res) !== 1) { fwrite(STDERR, "M045_C01_SYNC=FAIL REASON=FIELD_MISSING FIELD=".$field."\n"); exit(1); }
}

$sql = "SELECT DISTINCT p.rowid FROM ".MAIN_DB_PREFIX."product p LEFT JOIN ".MAIN_DB_PREFIX."product_extrafields pe ON pe.fk_object=p.rowid WHERE p.entity=".(int)$conf->entity." AND (p.ref='WP-9688B' OR pe.cp_woo_product_id=105789) ORDER BY p.rowid";
$res = $db->query($sql);
if (!$res || $db->num_rows($res) > 1) { fwrite(STDERR, "M045_C01_SYNC=FAIL REASON=MAPPING_AMBIGUOUS\n"); exit(1); }
$row = $db->fetch_object($res);
$productId = $row ? (int)$row->rowid : 0;

$baseCurrency = strtoupper((string)getDolCurrency());
$tx = 1.0;
if ($currency !== $baseCurrency) {
    $currencyInfo = MultiCurrency::getIdAndTxFromCode($db, $currency);
    $tx = is_array($currencyInfo) ? (float)($currencyInfo['rate'] ?? $currencyInfo['tx'] ?? 0) : 0;
    if ($tx <= 0) { fwrite(STDERR, "M045_C01_SYNC=FAIL REASON=CURRENCY_RATE_MISSING\n"); exit(1); }
}
$baseUnitPrice = $unitPrice / $tx;

if ($argv[1] === 'check') {
    if ($productId < 1) { echo "M045_C01_SYNC_CHECK=ABSENT WOO_ID=105789 SKU=WP-9688B\n"; exit(2); }
    $product = new Product($db);
    if ($product->fetch($productId) <= 0) { fwrite(STDERR, "M045_C01_SYNC=FAIL REASON=PRODUCT_FETCH_FAILED\n"); exit(1); }
    $product->fetch_optionals();
    $ok = $product->ref === 'WP-9688B' && $product->status == 1 && abs((float)$product->price - $baseUnitPrice) < 0.000001;
    foreach ($fields as $field=>$value) $ok = $ok && isset($product->array_options['options_'.$field]) && abs((float)$product->array_options['options_'.$field] - (float)$value) < 0.000001;
    echo $ok ? "M045_C01_SYNC_CHECK=PASS PRODUCT_ID=".$productId." PRICE_BASIS=PIECE CMS_PRICE=".$unitPrice." ".$currency."\n" : "M045_C01_SYNC_CHECK=DRIFT PRODUCT_ID=".$productId."\n";
    exit($ok ? 0 : 2);
}

$user = new User($db);
if ($user->fetch(1) <= 0 || empty($user->admin)) { fwrite(STDERR, "M045_C01_SYNC=FAIL REASON=ADMIN_USER_UNAVAILABLE\n"); exit(1); }
$product = new Product($db);
if ($productId > 0 && $product->fetch($productId) <= 0) { fwrite(STDERR, "M045_C01_SYNC=FAIL REASON=PRODUCT_FETCH_FAILED\n"); exit(1); }
$product->ref = 'WP-9688B';
$product->label = trim((string)($data['name'] ?? 'Valve Fitting WP-9688B'));
$product->description = trim((string)($data['description'] ?? ''));
$product->type = Product::TYPE_PRODUCT;
$product->status = 1;
$product->status_buy = 0;
$product->price_base_type = 'HT';
$product->price = $baseUnitPrice;
$product->price_ttc = $baseUnitPrice;
$product->tva_tx = 0;
foreach ($fields as $field=>$value) $product->array_options['options_'.$field] = $value;

$db->begin();
try {
    if ($productId > 0) {
        if ($product->update($productId, $user) <= 0) throw new RuntimeException($product->error ?: 'PRODUCT_UPDATE_FAILED');
    } else {
        $productId = $product->create($user);
        if ($productId <= 0) throw new RuntimeException($product->error ?: 'PRODUCT_CREATE_FAILED');
    }
    if ($product->updatePrice($baseUnitPrice, 'HT', $user, 0) <= 0) throw new RuntimeException($product->error ?: 'PRICE_UPDATE_FAILED');
    $db->commit();
} catch (Throwable $e) {
    $db->rollback();
    fwrite(STDERR, "M045_C01_SYNC=FAIL REASON=".$e->getMessage()."\n");
    exit(1);
}
echo "M045_C01_SYNC_APPLY=PASS PRODUCT_ID=".$productId." WOO_ID=105789 SKU=WP-9688B PRICE_BASIS=PIECE CMS_PRICE=".$unitPrice." ".$currency." BASE_PRICE=".round($baseUnitPrice, 8)." ".$baseCurrency."\n";
