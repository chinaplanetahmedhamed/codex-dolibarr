<?php

require_once __DIR__.'/../class/OperationBillingWorkflowService.class.php';

$billingEligible = !empty($operation['fk_commande']);
$billingCanRead = $billingEligible && empty($user->socid) && (!empty($user->admin) || $user->hasRight('facture', 'lire'));
$billingCanOperate = $billingCanRead && cpo_can($user, 'operation_write');
$billingCanCreate = $billingCanOperate && (!empty($user->admin) || $user->hasRight('facture', 'creer'));
$billingCanValidate = $billingCanCreate && (!function_exists('getDolGlobalString') || !getDolGlobalString('MAIN_USE_ADVANCED_PERMS') || !empty($user->admin) || $user->hasRight('facture', 'invoice_advance', 'validate'));
$billingCanPay = $billingCanOperate && (!empty($user->admin) || ($user->hasRight('chinaplanetcustomercenter', 'financial_write') && $user->hasRight('facture', 'paiement') && $user->hasRight('banque', 'lire')));
$billingService = $billingCanRead ? new OperationBillingWorkflowService($db) : null;
$billingActions = array('billing_create_invoice', 'billing_validate_invoice', 'billing_post_receipt', 'billing_resume_receipt');

if (in_array($action, $billingActions, true)) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$billingCanRead) accessforbidden();
    try {
        if ($action === 'billing_create_invoice') {
            $invoiceId = $billingService->createInvoice($id, $user, GETPOST('billing_request_key', 'alphanohtml'));
            setEventMessages('Native invoice draft #'.$invoiceId.' created. No message was sent.', null, 'mesgs');
        } elseif ($action === 'billing_validate_invoice') {
            $invoiceId = $billingService->validateInvoice($id, $user);
            setEventMessages('Native invoice #'.$invoiceId.' validated and is now read-only in the customer portal.', null, 'mesgs');
        } elseif ($action === 'billing_post_receipt') {
            $result = $billingService->postReceipt($id, GETPOST('receipt_amount', 'alphanohtml'), GETPOSTINT('bank_account_id'), GETPOST('payment_code', 'alpha'), GETPOST('payment_date', 'alphanohtml'), GETPOST('payment_reference', 'alphanohtml'), $user, GETPOST('billing_request_key', 'alphanohtml'));
            setEventMessages('Receipt #'.$result['payment_id'].' posted with native bank evidence #'.$result['bank_id'].'.', null, 'mesgs');
        } elseif ($action === 'billing_resume_receipt') {
            $result = $billingService->resumeReceipt($id, GETPOSTINT('billing_marker_id'), $user);
            setEventMessages('Receipt #'.$result['payment_id'].' safely recovered and verified.', null, 'mesgs');
        }
    } catch (Throwable $e) {
        setEventMessages($e->getMessage(), null, 'errors');
    }
}

$billingState = null;
if ($billingCanRead) {
    try {
        $billingState = $billingService->state($id, $billingCanPay);
    } catch (Throwable $e) {
        setEventMessages($e->getMessage(), null, 'errors');
    }
}
$billingRequestKey = bin2hex(random_bytes(20));
