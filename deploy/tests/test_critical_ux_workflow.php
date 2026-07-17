<?php
$root=dirname(__DIR__,2);
$checks=array(
    'tracking has no financial/projects sections'=>strpos(file_get_contents($root.'/custom/chinaplanetlogistics/index.php'),'FINANCIAL SUMMARY')===false && strpos(file_get_contents($root.'/custom/chinaplanetlogistics/index.php'),'PROJECTS SECTION')===false,
    'tracking cards are filters'=>strpos(file_get_contents($root.'/custom/chinaplanetlogistics/index.php'),'status=intransit')!==false,
    'shipment list customer selector'=>strpos(file_get_contents($root.'/custom/chinaplanetlogistics/list.php'),"selectarray('socid'")!==false,
    'documents customer column'=>strpos(file_get_contents($root.'/custom/chinaplanetcustomercenter/documents.php'),'العميل / Customer')!==false,
    'operations export action'=>strpos(file_get_contents($root.'/custom/chinaplanetcustomercenter/admin/operations.php'),'Create Export Document')!==false,
    'operations order details stay in shell'=>strpos(file_get_contents($root.'/custom/chinaplanetcustomercenter/admin/operations.php'),'/chinaplanetcustomercenter/order.php')!==false,
    'order advanced status action is isolated'=>strpos(file_get_contents($root.'/custom/chinaplanetcustomercenter/order.php'),'target="_blank" rel="noopener"')!==false,
    'order status has bilingual safe labels'=>strpos(file_get_contents($root.'/custom/chinaplanetcustomercenter/order.php'),'Validated / معتمد')!==false && substr_count(file_get_contents($root.'/custom/chinaplanetcustomercenter/order.php'),'getLibStatut(1)')===1,
	'orders dashboard shows active completed and total'=>strpos(file_get_contents($root.'/custom/chinaplanetcustomercenter/board.php'),'$ordersActive')!==false && strpos(file_get_contents($root.'/custom/chinaplanetcustomercenter/board.php'),'$ordersTotal')!==false,
	'orders page has three lifecycle views'=>strpos(file_get_contents($root.'/custom/chinaplanetcustomercenter/orders.php'),"array('active', 'completed', 'all')")!==false,
	'orders page has date reference and status filters'=>strpos(file_get_contents($root.'/custom/chinaplanetcustomercenter/orders.php'),'name="date_from"')!==false && strpos(file_get_contents($root.'/custom/chinaplanetcustomercenter/orders.php'),'name="date_to"')!==false && strpos(file_get_contents($root.'/custom/chinaplanetcustomercenter/orders.php'),'name="search_ref"')!==false && strpos(file_get_contents($root.'/custom/chinaplanetcustomercenter/orders.php'),'name="order_status"')!==false,
	'orders remain customer scoped'=>strpos(file_get_contents($root.'/custom/chinaplanetcustomercenter/orders.php'),'cpcc_portal_customer_scope_id')!==false && strpos(file_get_contents($root.'/custom/chinaplanetcustomercenter/orders.php'),'c.fk_soc=')!==false,
    'lifecycle is visibly automatic'=>strpos(file_get_contents($root.'/custom/chinaplanetcustomercenter/admin/operations.php'),'Status is calculated automatically')!==false,
    'order confirmation is record driven'=>strpos(file_get_contents($root.'/custom/chinaplanetcustomercenter/lib/operations.lib.php'),'$orderConfirmed')!==false && strpos(file_get_contents($root.'/custom/chinaplanetcustomercenter/lib/operations.lib.php'),"'done'=>true")===false,
    'shipment milestone uses linked shipment'=>strpos(file_get_contents($root.'/custom/chinaplanetcustomercenter/lib/operations.lib.php'),"'done'=>\$shipmentCount>0")!==false,
    'export milestone requires invoice and packing list'=>strpos(file_get_contents($root.'/custom/chinaplanetcustomercenter/lib/operations.lib.php'),'$hasCommercialInvoice && $hasPackingList')!==false,
    'financial direction is explicit'=>strpos(file_get_contents($root.'/custom/chinaplanetcustomercenter/admin/financial.php'),'cpcc-direction-output')!==false,
    'shipment card mobile CSS'=>strpos(file_get_contents($root.'/custom/chinaplanetcustomercenter/css/ux_fixes.css'),'@media(max-width:700px)')!==false,
);
$failed=array();
foreach($checks as $label=>$ok){echo ($ok?'PASS':'FAIL').': '.$label.PHP_EOL;if(!$ok)$failed[]=$label;}
exit(empty($failed)?0:1);
