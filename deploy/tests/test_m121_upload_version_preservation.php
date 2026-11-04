<?php
/** Actual uploader + portal fingerprint helper on disposable synthetic files only. */
ob_start();
require __DIR__.'/test_m121_shipment_api_context.php';
ob_end_clean();
function dol_sanitizeFileName($value) { return preg_match('/^[A-Za-z0-9_.-]+$/D',$value) ? $value : ''; }
$dataRoot = sys_get_temp_dir().'/m121-version-'.bin2hex(random_bytes(8));
define('DOL_DATA_ROOT',$dataRoot);
$folder = $dataRoot.'/chinaplanetlogistics/ship-17';
mkdir($folder,0700,true);
register_shutdown_function(function () use ($folder,$dataRoot) {
    foreach (array('SHIP-17-TEST.pdf','SHIP-17-TEST-2.pdf','SHIP-17-TEST-3.pdf') as $name) {
        if (is_file($folder.'/'.$name)) unlink($folder.'/'.$name);
    }
    rmdir($folder);rmdir($dataRoot.'/chinaplanetlogistics');rmdir($dataRoot);
});
$original = "%PDF-1.4\n% SYNTHETIC ORIGINAL\n%%EOF\n";
$revision = "%PDF-1.4\n% SYNTHETIC REVISION\n%%EOF\n";
file_put_contents($folder.'/SHIP-17-TEST.pdf',$original);
$conf->entity=2;
$db->rows=array(array_merge($row,array('rowid'=>17,'id'=>17,'entity'=>2,'fk_soc'=>23,'fk_project'=>31,'ref'=>'SYNTH-LOCAL')));
$db->queries=array();
DolibarrApiAccess::$user=new class {public $id=1;public $socid=0;public $admin=0;public function hasRight($module,$right){return $module==='chinaplanetlogistics'&&in_array($right,array('read','write'),true);}};
$api=new ChinaplanetlogisticsApi();
$shipment=new ChinaPlanetLogistics($db);$shipment->fetch(17);
$fingerprint=cpcc_portal_gallery_file_metadata($shipment,'ship-17/SHIP-17-TEST.pdf');
if (empty($fingerprint['ok'])) throw new RuntimeException('Synthetic fingerprint fixture invalid');
$first=$api->uploadDocument('17','TEST.pdf',base64_encode($revision),'base64','',0,0);
$second=$api->uploadDocument('17','TEST.pdf',base64_encode($revision),'base64','',0,0);
if ($first['filename']!=='SHIP-17-TEST-2.pdf' || $second['filename']!=='SHIP-17-TEST-3.pdf') throw new RuntimeException('Native version allocation failed');
if (!empty($first['customer_visible']) || !empty($second['customer_visible'])) throw new RuntimeException('New revision was published');
clearstatcache();
$after=cpcc_portal_gallery_file_metadata($shipment,'ship-17/SHIP-17-TEST.pdf');
if ($fingerprint!==$after || file_get_contents($folder.'/SHIP-17-TEST.pdf')!==$original) throw new RuntimeException('Original fingerprint changed');
if (file_get_contents($folder.'/'.$first['filename'])!==$revision || file_get_contents($folder.'/'.$second['filename'])!==$revision) throw new RuntimeException('Revision persistence failed');
if (array_filter($db->queries,fn($query)=>!str_starts_with($query,'SELECT '))) throw new RuntimeException('Unexpected business-data write');
// Reproduce the old overwrite risk only against the disposable original fixture.
$api->uploadDocument('17','TEST.pdf',base64_encode($revision),'base64','',0,1);
clearstatcache();
$unsafe=cpcc_portal_gallery_file_metadata($shipment,'ship-17/SHIP-17-TEST.pdf');
if ($fingerprint['file_sha256']===$unsafe['file_sha256']) throw new RuntimeException('Old overwrite risk not reproduced');
echo "PASS: native sequential revision allocation/persistence/private state preserves original portal fingerprint; unsafe overwrite risk reproduced on synthetic file\n";
