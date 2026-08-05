<?php
/** Static security contract for the M043 staging gateway migration. */
$root = dirname(__DIR__, 2);
$source = file_get_contents($root.'/deploy/migrations/m043_public_website_gateway.php');
if ($source === false) { fwrite(STDERR, "M043_GATEWAY_SOURCE=FAIL REASON=UNREADABLE\n"); exit(1); }
$assert = function ($condition, $message) { if (!$condition) throw new RuntimeException($message); };
try {
    $assert(strpos($source, "array('check', 'apply', 'disable', 'finalize-restore')") !== false, 'bounded command surface absent');
    $assert(strpos($source, "website_staging_gateway") !== false, 'fixed gateway login absent');
    $assert(strpos($source, "M043 staging-only website API gateway") !== false, 'gateway ownership marker absent');
    $assert(strpos($source, "array(500412, 500511)") !== false, 'least-privilege API rights absent');
    $assert(strpos($source, 'WHERE entity=".$entity." AND fk_user=') !== false, 'gateway-right cleanup is not entity and user scoped');
    $assert(strpos($source, "CHINAPLANET_PUBLIC_RATE_TOKEN_KEY") !== false, 'rate token secret constant absent');
    $assert(strpos($source, "CHINAPLANET_PUBLIC_REQUEST_HMAC_KEY") !== false, 'request HMAC secret constant absent');
    $assert(strpos($source, "#^/tmp/m043-secrets-[0-9]+\$#D") !== false, 'fixed transient secret directory absent');
    $assert(strpos($source, "dolEncrypt(\$apiKey") !== false, 'Dolibarr API-key encryption absent');
    $assert(strpos($source, "api_key=NULL") !== false && strpos($source, "statut=0") !== false, 'fail-closed gateway disable absent');
    $assert(strpos($source, "M043_SCHEMA_PREEXISTED") !== false && strpos($source, "RESTORE_TABLE_NOT_EMPTY") !== false, 'full-database restore finalizer absent');
    $assert(stripos($source, 'vision_erp') === false && stripos($source, 'vision_db') === false && stripos($source, 'production') === false, 'production identifier leaked into migration');
    $assert(stripos($source, 'new Commande(') === false && stripos($source, 'new Facture(') === false && stripos($source, 'new Propal(') === false, 'business object creation found');
    echo "M043_GATEWAY_SOURCE=PASS RIGHTS=LEAST_PRIVILEGE SECRETS=SERVER_SIDE DISABLE=FAIL_CLOSED\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'M043_GATEWAY_SOURCE=FAIL REASON='.$e->getMessage()."\n");
    exit(1);
}
