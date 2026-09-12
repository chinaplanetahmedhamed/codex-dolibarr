<?php

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "M078_LOCAL_SHIPMENT_CUSTOMER_FLOW=FAIL REASON=CLI_ONLY\n");
    exit(1);
}

function m078Assert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "M078_LOCAL_SHIPMENT_CUSTOMER_FLOW=FAIL REASON=".$message."\n");
        exit(1);
    }
}

function m078Source($relative)
{
    $source = file_get_contents(dirname(__DIR__, 2).'/'.$relative);
    m078Assert($source !== false, 'missing source '.$relative);
    return $source;
}

$edit = m078Source('custom/chinaplanetoperations/operations/edit.php');
foreach (array(
    "hasRight('societe', 'creer')",
    "GETPOSTINT('socid')",
    "DOL_URL_ROOT.'/societe/card.php?action=create&client=1&backtopage='",
    'rawurlencode($operationCreateUrl)',
    '!$id && $canCreateCustomers',
    'إضافة عميل جديد في النظام',
) as $needle) m078Assert(strpos($edit, $needle) !== false, 'native customer handoff missing '.$needle);

$index = m078Source('custom/chinaplanetoperations/operations/index.php');
foreach (array(
    "hasRight('chinaplanetlogistics','read')",
    "hasRight('chinaplanetlogistics','write')",
    '../../chinaplanetlogistics/list.php',
    '../../chinaplanetlogistics/card.php?action=create',
) as $needle) m078Assert(strpos($index, $needle) !== false, 'shipping center action missing '.$needle);

$css = m078Source('custom/chinaplanetoperations/css/operations.css');
foreach (array(
    '.cpad-section-content .cpo-table td{color:#edf5ff!important}',
    '.cpad-section-content .cpo-table td a{color:#76ddff!important',
    '.cpad-section-content .cpo-table td .cpo-badge{',
    'color:#123f7a!important',
    '.cpw-native-row{display:flex;',
    'background:#fff;color:var(--cpw-navy)!important',
) as $needle) m078Assert(strpos($css, $needle) !== false, 'dark-table contrast missing '.$needle);

$shipmentService = m078Source('custom/chinaplanetoperations/class/OperationShipmentService.class.php');
m078Assert(strpos($shipmentService, '"INSERT INTO ".MAIN_DB_PREFIX."chinaplanetlogistics') !== false, 'operation shipment is not a Shipping Center row');
m078Assert(strpos($shipmentService, 'fk_shipment') !== false, 'operation does not retain shipment link');

$statusLib = m078Source('custom/chinaplanetlogistics/lib/chinaplanetlogistics.lib.php');
m078Assert(strpos($statusLib, "8 => 'Loaded & Going to Export Customs'") !== false, 'loaded shipment status missing');

$workspace = m078Source('custom/chinaplanetoperations/workspace.php');
foreach (array(
    "hasRight('chinaplanetlogistics', 'read')",
    'COUNT(DISTINCT l.rowid) shipment_count',
    'SELECT rowid,ref,container_no,bl_no,date_eta,fk_statut,customer_visible',
    'id="customer-shipments"',
    'شحنات العميل',
    '/custom/chinaplanetlogistics/list.php?socid=',
) as $needle) m078Assert(strpos($workspace, $needle) !== false, 'customer workspace shipment link missing '.$needle);

$shipmentCard = m078Source('custom/chinaplanetlogistics/card.php');
m078Assert(substr_count($shipmentCard, "\$object->customer_visible = GETPOSTINT('customer_visible') ? 1 : 0;") === 2, 'shipment portal visibility is not explicit on create and update');
m078Assert(strpos($shipmentCard, "(\$action === 'edit' ? ' checked' : '')") !== false, 'new shipment publication is still preselected');
m078Assert(strpos($shipmentCard, 'name="customer_visible"') !== false, 'shipment portal visibility control missing');
m078Assert(substr_count($shipmentCard, "GETPOST('container_type', 'alpha') === '-1' ? ''") === 2, 'empty container type is stored as a fake value');
$shipmentObject = m078Source('custom/chinaplanetlogistics/class/chinaplanetlogistics.class.php');
m078Assert(strpos($shipmentObject, '$sql .= "customer_visible = ".(!empty($this->customer_visible) ? 1 : 0)." ";') !== false, 'shipment update cannot change portal visibility');

echo "M078_LOCAL_SHIPMENT_CUSTOMER_FLOW=PASS NATIVE_CUSTOMER=YES SHIPPING_CENTER=YES LOADED_STATUS=8\n";
