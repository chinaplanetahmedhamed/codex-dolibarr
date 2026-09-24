<?php
/** Source-contract test for ChinaPlanetLogistics REST API and document upload. */
$root = dirname(__DIR__, 2);
$read = function ($path) use ($root) {
    $data = file_get_contents($root.'/'.$path);
    if ($data === false) throw new RuntimeException('Unreadable '.$path);
    return $data;
};
$assert = function ($condition, $message) {
    if (!$condition) throw new RuntimeException($message);
};

try {
    $entrypoint = $read('custom/chinaplanetlogistics/class/api_chinaplanetlogistics.class.php');
    $api = $read('custom/chinaplanetlogistics/api/class/api_chinaplanetlogistics.class.php');
    $descriptor = $read('custom/chinaplanetlogistics/core/modules/modChinaPlanetLogistics.class.php');

    // Entrypoint contract
    $assert(strpos($entrypoint, "api/class/api_chinaplanetlogistics.class.php") !== false, 'Entrypoint router must delegate to api/class/api_chinaplanetlogistics.class.php');

    // Descriptor contract
    $assert(strpos($descriptor, "'api'") !== false, 'modChinaPlanetLogistics must declare api module part');

    // Class & inheritance contract
    // Dolibarr 23 strips the api suffix for the module file, then dispatches
    // the full /chinaplanetlogisticsapi endpoint class. The suffix also avoids
    // PHP's case-insensitive collision with the ChinaPlanetLogistics model.
    $routeClass = 'ChinaplanetlogisticsApi';
    $assert(strcasecmp($routeClass, ucwords('chinaplanetlogisticsapi')) === 0, 'Class must match the Dolibarr 23 endpoint dispatcher');
    $assert(strpos($api, 'class '.$routeClass.' extends DolibarrApi') !== false, 'API class must be declared');
    $assert(strcasecmp($routeClass, 'ChinaPlanetLogistics') !== 0, 'API class must not collide with the shipment model');

    // Endpoints contract
    $assert(strpos($api, '@url GET shipments') !== false, 'GET shipments endpoint must be declared');
    $assert(strpos($api, '@url GET shipments/{id}') !== false, 'GET shipments/{id} endpoint must be declared');
    $assert(strpos($api, '@url POST upload') !== false, 'POST upload endpoint must be declared');
    $assert(strpos($api, '@url POST shipments/{id}/documents') !== false, 'POST shipments/{id}/documents endpoint must be declared');

    // Security & permission contract
    $assert(strpos($api, "hasRight('chinaplanetlogistics', 'read')") !== false, 'Read permission must be verified for shipments');
    $assert(strpos($api, "hasRight('chinaplanetlogistics', 'write')") !== false, 'Write permission must be verified for document upload');
    $assert(strpos($api, 'DolibarrApiAccess::$user') !== false, 'DolibarrApiAccess::$user identity must be used');

    // Upload & confinement contract
    $assert(strpos($api, 'cpcc_portal_validate_shipment_filename') !== false, 'Filename validation must be used');
    $assert(strpos($api, 'cpcc_portal_shipment_doc_root') !== false, 'Shipment document root must be used');
    $assert(strpos($api, 'cpcc_portal_path_is_within') !== false, 'Confinement check must be enforced');
    $assert(strpos($api, 'cpcc_portal_gallery_publish') !== false, 'Gallery publish integration must be present');
    $assert(strpos($api, '209715200') !== false, '200MB limit must be enforced');

    echo "TEST_CHINAPLANETLOGISTICS_API_SOURCE=PASS\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "TEST_CHINAPLANETLOGISTICS_API_SOURCE=FAIL: ".$e->getMessage()."\n");
    exit(1);
}
