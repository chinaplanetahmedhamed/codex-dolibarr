<?php
/** Bridge one synthetic Docs request into the actual API/atomic writer. No HTTP. */
$payload=json_decode(file_get_contents('php://stdin'),true,512,JSON_THROW_ON_ERROR);
if (($payload['overwrite']??null)!==0 || ($payload['publish_to_customer']??null)!==0) {
    throw new RuntimeException('Docs must explicitly request a private non-overwrite upload');
}
ob_start();require __DIR__.'/test_m121_shipment_api_context.php';ob_end_clean();
function dol_sanitizeFileName($value) { return preg_match('/^[A-Za-z0-9_.-]+$/D',$value) ? $value : ''; }
$dataRoot=sys_get_temp_dir().'/m121-atomic-contract-'.bin2hex(random_bytes(8));
define('DOL_DATA_ROOT',$dataRoot);
$folder=$dataRoot.'/chinaplanetlogistics/ship-17';mkdir($folder,0700,true);
register_shutdown_function(function () use ($folder,$dataRoot) {
    foreach (scandir($folder) as $name) if ($name!=='.'&&$name!=='..') unlink($folder.'/'.$name);
    rmdir($folder);rmdir($dataRoot.'/chinaplanetlogistics');rmdir($dataRoot);
});
$original="%PDF-1.4\n% SYNTHETIC ORIGINAL\n%%EOF\n";
file_put_contents($folder.'/SHIP-17-TEST.pdf',$original);
$conf->entity=2;
$db->rows=array(array_merge($row,array('rowid'=>17,'id'=>17,'entity'=>2,'fk_soc'=>23,'fk_project'=>31,'ref'=>'SYNTH-LOCAL')));
$db->queries=array();
DolibarrApiAccess::$user=new class {public $id=1;public $socid=0;public $admin=0;public function hasRight($module,$right){return $module==='chinaplanetlogistics'&&in_array($right,array('read','write'),true);}};
$shipment=new ChinaPlanetLogistics($db);$shipment->fetch(17);
$before=cpcc_portal_gallery_file_metadata($shipment,'ship-17/SHIP-17-TEST.pdf');
if (empty($before['ok'])) throw new RuntimeException('Synthetic portal fingerprint invalid');
$receipt=null;$status=200;
try {
    $receipt=(new ChinaplanetlogisticsApi())->uploadDocument('',(string)$payload['filename'],(string)$payload['filecontent'],(string)$payload['fileencoding'],(string)$payload['ref'],0,0);
} catch (Luracast\Restler\RestException $error) { $status=$error->status; }
clearstatcache();
$after=cpcc_portal_gallery_file_metadata($shipment,'ship-17/SHIP-17-TEST.pdf');
$output=array('status'=>$status,'receipt'=>$receipt,'originalPreserved'=>file_get_contents($folder.'/SHIP-17-TEST.pdf')===$original,
    'fingerprintPreserved'=>$before===$after,'stagingNames'=>count(glob($folder.'/.cpl-upload-*')),
    'finalFileCount'=>count(glob($folder.'/SHIP-*')),'returnedBytesMatch'=>false,'mode'=>null);
if ($receipt) {
    $file=$folder.'/'.$receipt['filename'];
    $output['returnedBytesMatch']=file_get_contents($file)===base64_decode($payload['filecontent'],true);
    $output['mode']=sprintf('%04o',fileperms($file)&0777);
}
if (array_filter($db->queries,fn($sql)=>!str_starts_with($sql,'SELECT '))) throw new RuntimeException('Unexpected business-data write');
echo json_encode($output,JSON_THROW_ON_ERROR)."\n";
