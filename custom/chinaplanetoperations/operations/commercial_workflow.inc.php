<?php

require_once __DIR__.'/../class/OperationCommercialWorkflowService.class.php';

$commercialEligible = in_array($operation['service_type'], OperationCommercialWorkflowService::eligibleServiceTypes(), true);
$commercialCanRead = $commercialEligible
    && (!empty($user->admin) || ($user->hasRight('propal', 'lire') && $user->hasRight('commande', 'lire') && $user->hasRight('product', 'read')));
$commercialCanWrite = $commercialCanRead
    && cpo_can($user, 'operation_write')
    && (!empty($user->admin) || ($user->hasRight('propal', 'creer') && $user->hasRight('commande', 'creer')));
$commercialService = $commercialCanRead ? new OperationCommercialWorkflowService($db) : null;
$commercialActions = array('commercial_save_quote', 'commercial_validate_quote', 'commercial_accept_quote', 'commercial_create_order', 'commercial_validate_order');

if (in_array($action, $commercialActions, true)) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$commercialCanWrite) accessforbidden();
    try {
        if ($action === 'commercial_save_quote') {
            $commercialService->saveDraft($id, array(
                'currency'=>GETPOST('quote_currency', 'alpha'),
                'validity_days'=>GETPOSTINT('quote_validity_days'),
                'lines'=>GETPOST('quote_lines', 'array'),
            ), $user);
            setEventMessages('Quotation draft saved in the native ERP record.', null, 'mesgs');
        } elseif ($action === 'commercial_validate_quote') {
            $commercialService->validateQuotation($id, $user);
            setEventMessages('Quotation validated. No email was sent.', null, 'mesgs');
        } elseif ($action === 'commercial_accept_quote') {
            $commercialService->acceptQuotation($id, GETPOST('acceptance_reference', 'alphanohtml'), $user);
            setEventMessages('Customer acceptance recorded. No order has been created yet.', null, 'mesgs');
        } elseif ($action === 'commercial_create_order') {
            $commercialService->createOrder($id, $user);
            setEventMessages('Native customer order created as draft and linked to this operation.', null, 'mesgs');
        } elseif ($action === 'commercial_validate_order') {
            $commercialService->validateOrder($id, $user);
            setEventMessages('Customer order validated and is now available in the read-only customer portal.', null, 'mesgs');
        }
        $operation = $service->get($id);
    } catch (Throwable $e) {
        setEventMessages($e->getMessage(), null, 'errors');
    }
}

$commercialState = null;
if ($commercialCanRead) {
    try {
        $commercialState = $commercialService->state($id);
    } catch (Throwable $e) {
        setEventMessages($e->getMessage(), null, 'errors');
    }
}
$commercialProducts = $commercialCanWrite ? $commercialService->products() : array();
$commercialCurrencies = $commercialCanWrite ? $commercialService->currencies() : array();
$commercialProposal = $commercialState ? $commercialState['proposal'] : null;
$commercialOrder = $commercialState ? $commercialState['order'] : null;

if (!function_exists('cpo_commercial_status')) {
    function cpo_commercial_status($type, $status)
    {
        $labels = array(
            'proposal'=>array(-1=>'Canceled', 0=>'Draft', 1=>'Validated', 2=>'Accepted', 3=>'Refused', 4=>'Billed'),
            'order'=>array(-1=>'Canceled', 0=>'Draft', 1=>'Validated', 2=>'In progress', 3=>'Closed'),
        );
        return isset($labels[$type][(int) $status]) ? $labels[$type][(int) $status] : (string) $status;
    }
}
