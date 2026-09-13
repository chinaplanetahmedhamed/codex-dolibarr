<?php

if (PHP_SAPI !== 'cli') exit(1);

function m079cAssert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "M079_COMMERCIAL_WORKFLOW=FAIL REASON=".$message."\n");
        exit(1);
    }
}

$root = dirname(__DIR__, 2);
$read = function ($path) use ($root) {
    $source = file_get_contents($root.'/'.$path);
    m079cAssert($source !== false, 'missing '.$path);
    return $source;
};

$service = $read('custom/chinaplanetoperations/class/OperationCommercialWorkflowService.class.php');
foreach (array(
    "DOL_DOCUMENT_ROOT.'/comm/propal/class/propal.class.php'",
    "DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php'",
    "array('order', 'sourcing', 'purchasing')",
    "getEntity('product')",
    "section_code='commercial_workflow'",
    "GET_LOCK(",
    "fk_soc",
    "fk_project",
    "fk_commande IS NULL",
    'createFromProposal',
    'Propal::STATUS_SIGNED',
    'Commande::STATUS_VALIDATED',
    'assertPermission',
    'ORDER_VALID_AFTER_CLOSE_PROPAL',
) as $needle) m079cAssert(strpos($service, $needle) !== false, 'service contract missing '.$needle);

$controller = $read('custom/chinaplanetoperations/operations/commercial_workflow.inc.php');
foreach (array(
    "\$_SERVER['REQUEST_METHOD'] !== 'POST'",
    "cpo_can(\$user, 'operation_write')",
    "hasRight('propal', 'creer')",
    "hasRight('commande', 'creer')",
    "GETPOST('quote_lines', 'array')",
) as $needle) m079cAssert(strpos($controller, $needle) !== false, 'permission or input boundary missing '.$needle);

$view = $read('custom/chinaplanetoperations/operations/commercial_workflow.view.php');
foreach (array(
    'name="token"',
    'commercial_save_quote',
    'commercial_validate_quote',
    'commercial_accept_quote',
    'acceptance_reference',
    'commercial_create_order',
    'commercial_validate_order',
    'No email, invoice, payment, supplier order, or shipment',
) as $needle) m079cAssert(strpos($view, $needle) !== false, 'guided UI contract missing '.$needle);

$orders = $read('custom/chinaplanetcustomercenter/orders.php');
$order = $read('custom/chinaplanetcustomercenter/order.php');
$mobile = $read('custom/chinaplanetcustomercenter/lib/mobile_api_data.lib.php');
$operations = $read('custom/chinaplanetoperations/class/OperationsService.class.php');
m079cAssert(strpos($orders, 'if ($isExternal) $scopeSql .= " AND c.fk_statut>0";') !== false, 'external list exposes draft orders');
m079cAssert(strpos($order, 'if ($is_external && (int) $order->statut <= 0)') !== false, 'external detail exposes draft orders');
m079cAssert(strpos($mobile, '" AND c.fk_statut>0"') !== false, 'mobile API exposes draft orders');
m079cAssert(strpos($operations, "getSection(\$id, 'commercial_workflow')") !== false, 'operation update does not inspect commercial state');
m079cAssert(strpos($operations, 'Customer and project are locked after the quotation workflow starts.') !== false, 'customer/project context is not locked');
m079cAssert(strpos($operations, 'The commercial workflow controls the linked customer order.') !== false, 'linked order context is not locked');

echo "M079_COMMERCIAL_WORKFLOW=PASS NATIVE_QUOTE=YES NATIVE_ORDER=YES CUSTOMER_PORTAL=READ_ONLY\n";
