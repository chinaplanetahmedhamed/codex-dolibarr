<?php

require_once DOL_DOCUMENT_ROOT.'/custom/chinaplanetshippingprices/class/CustomerShippingOperationGatewayInterface.class.php';
require_once __DIR__.'/OperationsService.class.php';

/** Operations-owned adapter for the customer shipping booking use case. */
class CustomerShippingOperationGateway implements CustomerShippingOperationGatewayInterface
{
    private $operations;

    public function __construct($db, $operations = null)
    {
        $this->operations = $operations ?: new OperationsService($db);
    }

    public function createBookingOperation(array $operationData, array $snapshot, $userId)
    {
        $operationId = $this->operations->create($operationData, (int) $userId, false);
        $this->operations->addImmutableSection($operationId, 'customer_rate', $snapshot, (int) $userId);
        return (int) $operationId;
    }
}
