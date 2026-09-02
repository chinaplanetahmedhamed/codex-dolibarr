<?php
/** Dependency-free source and shared-fixture contract for M071. */
$root = dirname(__DIR__, 2);
$read = function ($path) use ($root) {
    $data = file_get_contents($root.'/'.$path);
    if ($data === false) throw new RuntimeException('Unreadable '.$path);
    return $data;
};
$assert = function ($condition, $message) { if (!$condition) throw new RuntimeException($message); };

try {
    $intake = $read('custom/chinaplanetoperations/class/PublicWebsiteRequestService.class.php');
    $lifecycle = $read('custom/chinaplanetoperations/class/PublicRequestLifecycleService.class.php');
    $quotation = $read('custom/chinaplanetoperations/class/PublicRequestQuotationService.class.php');
    $portal = $read('custom/chinaplanetcustomercenter/quotation_requests.php');
    $navigation = $read('custom/chinaplanetcustomercenter/lib/portal.lib.php');
    $schema = $read('custom/chinaplanetoperations/sql/llx_cpo_public_request.sql');
    $lineSchema = $read('custom/chinaplanetoperations/sql/llx_cpo_public_request_line.sql');
    $eventSchema = $read('custom/chinaplanetoperations/sql/llx_cpo_public_request_event.sql');
    $migration = $read('deploy/migrations/m071_catalog_order_v2.php');
    $fixture = json_decode($read('deploy/tests/fixtures/cpo_catalog_order_v2.json'), true);
    $invalidFixture = json_decode($read('deploy/tests/fixtures/cpo_catalog_order_v2.invalid.json'), true);

    $assert(is_array($fixture) && $fixture['request_type'] === 'catalog_order' && $fixture['schema_version'] === 2, 'frozen contract identity absent');
    $lines = $fixture['details']['lines'];
    $assert(count($lines) === 2 && $lines[0]['productId'] === $lines[1]['productId'], 'duplicate product rows fixture absent');
    $assert($lines[0]['lineId'] !== $lines[1]['lineId'], 'lineId is not request-unique');
    $assert($lines[0]['calculationReady'] === true && $lines[1]['calculationReady'] === false, 'mixed readiness fixture absent');
    $assert($lines[1]['unitsPerCarton'] === null && $lines[1]['cbmPerCarton'] === null && $lines[1]['grossWeightKg'] === null, 'unknown packing became a number');
    $assert($lines[1]['missingFields'] === $fixture['details']['packing_plan']['excludedItems'][0]['missingFields'], 'excluded row missing fields differ');
    $assert($lines[1]['price']['visibility'] === 'request_quote' && $lines[1]['price']['unitPrice'] === null, 'hidden/request price leaked');
    $assert($invalidFixture['contract'] === 'cpo.catalog-order.v2' && array_column($invalidFixture['cases'], 'name') === array('fractional_units_per_carton','incomplete_line_placement','unknown_iso_country'), 'shared invalid fixture cases absent');

    $assert(strpos($intake, "CATALOG_CONTRACT = 'cpo.catalog-order.v2'") !== false && strpos($intake, 'CATALOG_CONTRACT_VERSION = 2') !== false, 'v2 contract constant absent');
    $assert(strpos($intake, 'idempotency_conflict') !== false && strpos($intake, 'hash_equals') !== false, 'full request idempotency guard absent');
    $assert(strpos($intake, "CATALOG_TERMS_VERSION = '2026-08-29'") !== false && strpos($intake, 'time() + 300') !== false, 'consent version/freshness guard absent');
    $assert(strpos($intake, "'lineId'") !== false && strpos($intake, "'missingFields'") !== false && strpos($intake, "'calculationReady'") !== false, 'line contract fields absent');
    $assert(strpos($intake, 'validCountryCode') !== false && strpos($intake, 'c_country') !== false && strpos($intake, 'UPPER(code)') !== false && strpos($intake, 'UPPER(code_iso)') === false, 'database-backed ISO alpha-2 country validation absent');
    $assert(strpos($intake, 'normalizeCatalogV2Placements') !== false && strpos($intake, 'incomplete_line_placement') !== false, 'ERP intake does not reassert ready-line-only placements');
    $assert(strpos($intake, 'storeCatalogLines') !== false && strpos($intake, 'request_received') !== false, 'atomic line and event persistence absent');
    $assert(strpos($schema, 'schema_version') !== false && strpos($lineSchema, 'line_key') !== false && strpos($eventSchema, 'event_type') !== false, 'additive lifecycle schema absent');
    $assert(strpos($migration, "array('check', 'apply')") !== false, 'migration exposes actions beyond check/apply or lacks explicit action allowlist');
    $assert(strpos($migration, 'GRANTS=EXPLICIT') !== false && strpos($migration, 'user_rights') === false && strpos($migration, 'usergroup_rights') === false, 'M071 rights must be defined disabled and granted separately');
    foreach (array('new Societe(', 'new Contact(') as $creator) $assert(strpos($intake, $creator) === false, 'anonymous intake creates a lead/contact');
    foreach (array('mail(', 'sendMail', 'new User(', 'setPassword', 'new Commande(', 'new Expedition(') as $forbidden) {
        $assert(stripos($intake.$lifecycle.$quotation, $forbidden) === false, 'unauthorized lifecycle action found '.$forbidden);
    }

    $assert(strpos($lifecycle, "GET_LOCK('") !== false && strpos($lifecycle, "RELEASE_LOCK('") !== false && strpos($lifecycle, 'qualification_lock_release_failed') !== false, 'qualification lock is not fail-closed');
    foreach (array('qualification_transaction_begin_failed','qualification_transaction_commit_failed','qualification_transaction_rollback_failed') as $failure) $assert(strpos($lifecycle, $failure) !== false, 'qualification transaction failure is not explicit '.$failure);
    $assert(strpos($lifecycle, 'LEFT JOIN ".MAIN_DB_PREFIX."socpeople') !== false && strpos($lifecycle, 'company_email') !== false && strpos($lifecycle, 'company_phone') !== false, 'company/contact identity matching is incomplete');
    $assert(strpos($lifecycle, 'normalizedPhoneSql') !== false && strpos($lifecycle, 'LIMIT 100') === false && strpos($lifecycle, "'candidate_count'") !== false, 'identity lookup is truncated or lacks complete exact-phone matching');
    $assert(strpos($lifecycle, "'exact_match_requires_choice'") !== false && strpos($lifecycle, "'ambiguous'") !== false, 'identity ambiguity does not stop lead creation');
    $assert(strpos($lifecycle, 'new Societe($this->db)') !== false && strpos($lifecycle, 'new Contact($this->db)') !== false, 'staff-reviewed lead path absent');
    $assert(strpos($quotation, 'qualification_required') !== false && strpos($quotation, 'quotation_request_contact_mismatch') !== false, 'quotation ownership qualification guard absent');
    foreach (array('quotation_transaction_begin_failed','quotation_transaction_commit_failed','quotation_transaction_rollback_failed','quotation_lock_release_failed') as $failure) $assert(strpos($quotation, $failure) !== false, 'quotation transaction/lock failure is not explicit '.$failure);
    $assert(strpos($lifecycle, 'customer_visibility_changed') !== false, 'Customer Center publication audit event absent');
    $assert(strpos($lifecycle, 'quotation_not_approved') !== false && strpos($lifecycle, 'fk_statut IN (1,2,4)') !== false, 'draft/rejected quotation can be published');

    $assert(strpos($portal, 'WHERE r.entity=') !== false && strpos($portal, 'AND r.fk_soc=') !== false && strpos($portal, 'request_customer_visible=1') !== false, 'Customer Center entity/customer/visibility scope absent');
    $assert(strpos($portal, "GETPOST('ref', 'alphanohtml')") !== false && strpos($portal, "GETPOSTINT('id')") === false, 'Customer Center exposes internal request row id');
    $assert(strpos($portal, 'supplier_cost') === false && strpos($portal, 'base_margin') === false && strpos($portal, 'note_private') === false, 'Customer Center leaks private commercial fields');
    $assert(strpos($portal, 'p.fk_statut IN (1,2,4)') !== false && strpos($portal, 'propaldet') !== false && strpos($portal, 'multicurrency_subprice') !== false, 'approved customer-safe quotation detail projection absent');
    $assert(strpos($portal, 'pd.description') === false && strpos($portal, 'pd.label') !== false && strpos($portal, 'product p ON p.rowid=pd.fk_product') !== false, 'staff line description can leak into Customer Center');
    $assert(strpos($quotation, 'extractPublicLineDescription') !== false && strpos($quotation, "'public_description'=>") !== false && strpos($portal, 'staff_note') === false && strpos($portal, 'note_private') === false, 'staff and customer-visible line notes are not separated');
    $assert(strpos($navigation, "array('ar', 'en', 'fr')") !== false && strpos($navigation, "\$direction = \$locale === 'ar' ? 'rtl' : 'ltr'") !== false, 'Customer Center language/direction contract absent');

    echo "M071_SOURCE=PASS contract=cpo.catalog-order.v2 identity=STAFF_REVIEW portal=ENTITY_SCOPED\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'M071_SOURCE=FAIL REASON='.$e->getMessage()."\n");
    exit(1);
}
