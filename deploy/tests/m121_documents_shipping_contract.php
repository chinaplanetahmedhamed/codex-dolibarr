<?php
/** Export actual API/model responses using the shipping commit's synthetic harness.
 * No HTTP server, runtime DB, credentials or write queries. Consumed by Docs tests.
 */
ob_start();
require __DIR__.'/test_m121_shipment_api_context.php';
ob_end_clean();

$conf->entity = 2;
$user->socid = 0;
$user->admin = 0;
$user->read = true;
DolibarrApiAccess::$user = $user;
$linked = array_merge($row, array('rowid'=>17,'id'=>17,'entity'=>2,'fk_soc'=>23,'fk_project'=>31,'ref'=>'SYNTH-LOCAL','notes'=>'SYNTHETIC_INTERNAL_DO_NOT_RETURN'));
$unlinked = array_merge($linked, array('rowid'=>19,'id'=>19,'fk_project'=>null,'ref'=>'SYNTH-UNLINKED'));
$foreign = array_merge($linked, array('rowid'=>18,'id'=>18,'entity'=>3,'fk_soc'=>24,'ref'=>'SYNTH-FOREIGN'));
$db->rows = array($linked,$unlinked,$foreign);
$db->queries = array();
$api = new ChinaplanetlogisticsApi();
$output = array('linked'=>$api->getShipment(17), 'unlinked'=>$api->getShipment(19),
    'reference'=>$api->getShipment('SYNTH-LOCAL'), 'list'=>$api->listShipments('',23));
function contractDenial121($fn) {
    try { $fn(); }
    catch (Luracast\Restler\RestException $error) { return $error->status; }
    throw new RuntimeException('Expected scoped API rejection');
}
$denials = array('foreignId'=>contractDenial121(fn()=>$api->getShipment(18)),
    'foreignRef'=>contractDenial121(fn()=>$api->getShipment('SYNTH-FOREIGN')));
$before = count($db->queries);
$user->read = false;
$denials['noRead'] = contractDenial121(fn()=>$api->getShipment(17));
$user->read = true;
$user->socid = 23;
$user->admin = 1;
$denials['externalAdmin'] = contractDenial121(fn()=>$api->getShipment(17));
DolibarrApiAccess::$user = null;
$denials['anonymous'] = contractDenial121(fn()=>$api->getShipment(17));
$user->socid = 0;
$user->admin = 0;
DolibarrApiAccess::$user = $user;
$conf->entity = 0;
$denials['noEntity'] = contractDenial121(fn()=>$api->getShipment(17));
$output['denials'] = $denials;
$output['denialsBeforeQuery'] = count($db->queries) === $before;
$output['selectOnly'] = count(array_filter($db->queries,fn($sql)=>!preg_match('/^SELECT /',$sql))) === 0;
echo json_encode($output,JSON_THROW_ON_ERROR)."\n";
