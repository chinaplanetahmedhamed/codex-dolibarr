<?php
/** Source characterization for the first M053 module dependency-cycle removal. */
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
    $booking = $read('custom/chinaplanetshippingprices/class/CustomerShippingBookingService.class.php');
    $contract = $read('custom/chinaplanetshippingprices/class/CustomerShippingOperationGatewayInterface.class.php');
    $gateway = $read('custom/chinaplanetoperations/class/CustomerShippingOperationGateway.class.php');
    $page = $read('custom/chinaplanetshippingprices/customer_booking.php');
    $operationsDescriptor = $read('custom/chinaplanetoperations/core/modules/modChinaPlanetOperations.class.php');
    $publicIntake = $read('custom/chinaplanetoperations/class/PublicWebsiteRequestService.class.php');

    $assert(strpos($booking, 'OperationsService') === false, 'SHIPPING_BOOKING_STILL_NAMES_OPERATIONS_SERVICE');
    $assert(strpos($booking, 'custom/chinaplanetoperations') === false, 'SHIPPING_BOOKING_STILL_IMPORTS_OPERATIONS');
    $assert(strpos($booking, 'instanceof CustomerShippingOperationGatewayInterface') !== false, 'GATEWAY_CONTRACT_NOT_ENFORCED');
    $assert(strpos($contract, 'createBookingOperation') !== false, 'GATEWAY_METHOD_MISSING');
    $assert(strpos($gateway, 'implements CustomerShippingOperationGatewayInterface') !== false, 'OPERATIONS_ADAPTER_CONTRACT_MISSING');
    $assert(strpos($gateway, "addImmutableSection(\$operationId, 'customer_rate'") !== false, 'IMMUTABLE_RATE_WRITE_MOVED_OR_MISSING');
    $assert(strpos($page, 'new CustomerShippingOperationGateway($db)') !== false, 'COMPOSITION_ROOT_MISSING');
    $assert(strpos($operationsDescriptor, "'modChinaPlanetShippingPrices'") !== false, 'DECLARED_OPERATIONS_TO_SHIPPING_DIRECTION_MISSING');
    $assert(strpos($publicIntake, 'PublicShippingRateService') !== false, 'PUBLIC_INTAKE_RATE_DEPENDENCY_MISSING');

    echo "M053_DEPENDENCY_CYCLE=PASS shipping_service_to_operations=REMOVED composition=EXPLICIT behavior=CHARACTERIZED\n";
} catch (Throwable $e) {
    fwrite(STDERR, "M053_DEPENDENCY_CYCLE=FAIL REASON=".$e->getMessage()."\n");
    exit(1);
}
