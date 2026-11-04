<?php
/** Actual API/private filesystem, synthetic SELECT adapter and actor; no HTTP/native DB. */
$input=json_decode(file_get_contents('php://stdin'),true,512,JSON_THROW_ON_ERROR);
$ctx=$input['context'];foreach(['entity','customerId','projectId','shipmentId']as$key)if(!is_int($ctx[$key])||$ctx[$key]<=0)throw new RuntimeException('Explicit synthetic context required.');
ob_start();require __DIR__.'/test_m121_shipment_api_context.php';ob_end_clean();
$conf->entity=$ctx['entity'];
$db->rows=[array_merge($row,['rowid'=>$ctx['shipmentId'],'id'=>$ctx['shipmentId'],'entity'=>$ctx['entity'],'fk_soc'=>$ctx['customerId'],'fk_project'=>$ctx['projectId'],'ref'=>$ctx['shipmentRef'],'fk_statut'=>2,'notes'=>'SYNTHETIC_PRIVATE_CANARY'])];$db->queries=[];
DolibarrApiAccess::$user=new class{public $id=19;public $socid=0;public $admin=0;public $statut=1;function hasRight($module,$right){return $module==='chinaplanetlogistics'&&in_array($right,['read','write'],true);}};
$api=new ChinaplanetlogisticsApi();
if($input['action']==='read'){$detail=$api->getShipment($ctx['shipmentRef']);echo json_encode($detail,JSON_THROW_ON_ERROR).PHP_EOL;exit;}
$p=$input['payload'];if(($p['overwrite']??null)!==0||($p['publish_to_customer']??null)!==0||($p['ref']??null)!==$ctx['shipmentRef'])throw new RuntimeException('Private nonoverwrite request required.');
function dol_sanitizeFileName($name){return preg_match('/^[A-Za-z0-9_.-]+$/D',$name)?$name:'';}
$directory=sys_get_temp_dir().'/m121-journey-private-'.bin2hex(random_bytes(8));define('DOL_DATA_ROOT',$directory);$folder=$directory.'/chinaplanetlogistics/ship-'.$ctx['shipmentId'];mkdir($folder,0700,true);
register_shutdown_function(function()use($folder,$directory){foreach(scandir($folder)as$name)if($name!=='.'&&$name!=='..')unlink($folder.'/'.$name);rmdir($folder);rmdir($directory.'/chinaplanetlogistics');rmdir($directory);});
$originalName='SHIP-'.$ctx['shipmentId'].'-INVOICE.pdf';$original="%PDF-1.4\n% SYNTHETIC ORIGINAL M121\n%%EOF\n";file_put_contents($folder.'/'.$originalName,$original);
$shipment=new ChinaPlanetLogistics($db);$shipment->fetch($ctx['shipmentId']);$before=cpcc_portal_gallery_file_metadata($shipment,'ship-'.$ctx['shipmentId'].'/'.$originalName);
$receipt=$api->uploadDocument('',(string)$p['filename'],(string)$p['filecontent'],(string)$p['fileencoding'],(string)$p['ref'],0,0);$stored=$folder.'/'.$receipt['filename'];$after=cpcc_portal_gallery_file_metadata($shipment,'ship-'.$ctx['shipmentId'].'/'.$originalName);
if(array_filter($db->queries,fn($sql)=>!str_starts_with($sql,'SELECT ')))throw new RuntimeException('Unexpected business-data write.');
echo json_encode(['receipt'=>$receipt,'originalPreserved'=>file_get_contents($folder.'/'.$originalName)===$original,'fingerprintPreserved'=>$before===$after,'returnedBytesMatch'=>file_get_contents($stored)===base64_decode($p['filecontent'],true),'mode'=>sprintf('%04o',fileperms($stored)&0777),'sha256'=>hash_file('sha256',$stored),'stagingNames'=>count(glob($folder.'/.cpl-upload-*')),'fileCount'=>count(glob($folder.'/SHIP-*')),'shipmentStatus'=>2,'authentication'=>'synthetic active internal actor; not native session acceptance'],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
