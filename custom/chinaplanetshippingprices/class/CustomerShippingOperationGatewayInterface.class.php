<?php

/** Narrow operation-writing boundary required by customer shipping booking. */
interface CustomerShippingOperationGatewayInterface
{
    /**
     * Create the customer-visible operation and attach its immutable rate snapshot.
     *
     * Transaction ownership remains with CustomerShippingBookingService.
     *
     * @return int Created operation ID
     */
    public function createBookingOperation(array $operationData, array $snapshot, $userId);
}
