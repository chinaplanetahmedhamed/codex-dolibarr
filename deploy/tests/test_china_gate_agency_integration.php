<?php
define('NOLOGIN', 1);
define('NOREQUIREMENU', 1);
define('NOREQUIREHTML', 1);
define('NOCSRFCHECK', 1);

$_SERVER['DOCUMENT_ROOT'] = '/Users/mac/Documents/dev/dolibarr/dolibarr-Dev/dolibarr-23.0.3/htdocs';
$_SERVER['SERVER_NAME'] = '127.0.0.1';
$_SERVER['SERVER_PORT'] = '8088';
require $_SERVER['DOCUMENT_ROOT'].'/master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/custom/chinaplanetcustomercenter/lib/financial.lib.php';

function assertTrue($condition, $message)
{
    if (!$condition) throw new RuntimeException('FAIL: '.$message);
    print 'PASS '.$message.PHP_EOL;
}

function scalar($db, $sql)
{
    $res = $db->query($sql);
    if (!$res) throw new RuntimeException($db->lasterror());
    $obj = $db->fetch_object($res);
    return $obj ? (float) $obj->value : 0;
}

$customerId = (int) scalar($db, "SELECT rowid value FROM ".MAIN_DB_PREFIX."societe WHERE nom='China Gate' LIMIT 1");
assertTrue($customerId > 0, 'China Gate customer exists');
assertTrue((int) scalar($db, "SELECT COUNT(*) value FROM ".MAIN_DB_PREFIX."projet WHERE fk_soc=".$customerId." AND ref IN ('CG-AG-001','CG-AG-002','CG-AG-003')") === 3, 'three agency projects exist');
assertTrue((int) scalar($db, "SELECT COUNT(*) value FROM ".MAIN_DB_PREFIX."commande WHERE fk_soc=".$customerId." AND note_private LIKE '%CPCC-AGENCY-ORDER-AG-%'") === 3, 'three service orders exist');
assertTrue((int) scalar($db, "SELECT COUNT(*) value FROM ".MAIN_DB_PREFIX."facture WHERE fk_soc=".$customerId." AND note_private LIKE '%CPCC-AGENCY-INVOICE-AG-%' AND fk_statut>0") === 3, 'three Vision service invoices exist');
assertTrue((int) scalar($db, "SELECT COUNT(*) value FROM ".MAIN_DB_PREFIX."chinaplanetlogistics WHERE fk_soc=".$customerId." AND ref LIKE 'CG-AG-SHIP-%'") === 3, 'three linked shipments exist');
assertTrue((int) scalar($db, "SELECT COUNT(*) value FROM ".MAIN_DB_PREFIX."cpcc_export_document WHERE fk_soc=".$customerId." AND ref LIKE 'CPCC-AG-%'") === 6, 'six commercial documents exist');
assertTrue((int) scalar($db, "SELECT COUNT(*) value FROM ".MAIN_DB_PREFIX."menu WHERE url IN ('/custom/chinaplanetcustomercenter/admin/operations.php','/custom/chinaplanetcustomercenter/admin/financial.php','/custom/chinaplanetcustomercenter/admin/export_document.php')") === 3, 'operations, finance, and export admin menus are registered');

$project1 = (int) scalar($db, "SELECT rowid value FROM ".MAIN_DB_PREFIX."projet WHERE ref='CG-AG-001' LIMIT 1");
$rows = cpcc_financial_commitment_rows($db, $customerId, 'USD', $project1);
$supplier = null;
foreach ($rows as $row) if ($row['category'] === 'product') $supplier = $row;
assertTrue(is_array($supplier), 'AG-001 supplier obligation is visible');
assertTrue(abs($supplier['amount'] - 70000) < 0.01, 'AG-001 supplier order is 70,000 CNY');
assertTrue(abs($supplier['paid'] - 55000) < 0.01, 'AG-001 allocated supplier payments are 55,000 CNY');
assertTrue(abs($supplier['remaining'] - 15000) < 0.01, 'AG-001 supplier balance is 15,000 CNY');

$invoices = cpcc_financial_customer_invoices($db, $customerId, 'USD', $project1);
assertTrue(count($invoices) === 1, 'AG-001 has one service-only customer invoice');
assertTrue($invoices[0]['currency'] === 'RMB', 'AG-001 service invoice is displayed in RMB (native CNY)');
assertTrue(abs($invoices[0]['total'] - 7680) < 0.01, 'AG-001 service invoice total is 7,680 RMB (actual '.number_format($invoices[0]['total'], 2).')');
assertTrue(abs($invoices[0]['paid'] - 3000) < 0.01, 'AG-001 wallet allocation is 3,000 RMB (actual '.number_format($invoices[0]['paid'], 2).')');

$mixedUsd = (int) scalar($db, "SELECT COUNT(*) value FROM ".MAIN_DB_PREFIX."cpcc_financial_entry WHERE fk_soc=".$customerId." AND reference='CPCC-AG-001-SUP-USD' AND currency_code='USD' AND allocated_currency='CNY' AND allocated_amount=35000");
assertTrue($mixedUsd === 1, 'mixed USD payment is allocated to the CNY supplier invoice');

print 'China Gate agency integration tests passed'.PHP_EOL;
