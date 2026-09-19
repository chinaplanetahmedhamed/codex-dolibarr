<?php
/** Synthetic local-only native persistence and capability test. */
if(PHP_SAPI!=='cli')exit(1);
$root=getenv('M085_DOL_DOCUMENT_ROOT');
if(!$root||strpos($root,'/local-runtime/m085-qa/htdocs')===false)throw new RuntimeException('M085_ISOLATED_RUNTIME_REQUIRED');
define('NOLOGIN',1);define('NOREQUIREMENU',1);define('NOREQUIREHTML',1);define('NOREQUIREAJAX',1);
$_SERVER['DOCUMENT_ROOT']=$root;require $root.'/master.inc.php';
if(!preg_match('/^m085_qa_[a-f0-9]{10}$/D',$dolibarr_main_db_name))throw new RuntimeException('M085_ISOLATED_DB_REQUIRED');
require_once $root.'/custom/chinaplanetoperations/class/PublicRequestPdfService.class.php';
function check($ok,$message){if(!$ok)throw new RuntimeException($message);}
function denied($fn,$status){try{$fn();throw new RuntimeException('NOT_DENIED');}catch(PublicWebsiteRequestException $e){check($e->getHttpStatus()===$status,'WRONG_DENIAL');}}
$actor=new User($db);$actor->fetch(1);$service=new PublicRequestPdfService($db,1);
$key='m085-runtime-'.bin2hex(random_bytes(8));$phone='+201'.random_int(1000000000,9999999999);
$input=['request_type'=>'catalog_order','schema_version'=>2,'idempotency_key'=>$key,'contact'=>['name'=>'M085 Native Test','phone'=>$phone,'email'=>'','country_code'=>'EG'],'consent'=>['privacy'=>true,'accepted_at'=>gmdate('c')],'details'=>['source_reference'=>'woocommerce','lines'=>[['lineId'=>'m085-runtime-line','productId'=>1,'cartons'=>2]]]];
$a=$service->prepare($input,$actor);$b=$service->prepare($input,$actor);check($a['request_ref']===$b['request_ref']&&$b['duplicate'],'DUPLICATE_ENQUIRY');
$sql="SELECT rowid,fk_soc,status FROM ".MAIN_DB_PREFIX."cpo_public_request WHERE entity=1 AND ref='".$db->escape($a['request_ref'])."'";$q=$db->query($sql);$row=$db->fetch_array($q);check(!empty($row['fk_soc'])&&$row['status']==='new','PROSPECT_OR_STATUS_INVALID');
$q=$db->query('SELECT client FROM '.MAIN_DB_PREFIX.'societe WHERE rowid='.(int)$row['fk_soc']);check((int)$db->fetch_object($q)->client===2,'NOT_NATIVE_PROSPECT');
$changed=$input;$changed['details']['lines'][0]['cartons']=3;denied(fn()=>$service->prepare($changed,$actor),409);
$changed['idempotency_key']=$key.'-v2';$v2=$service->prepare($changed,$actor);check($v2['request_ref']!==$a['request_ref'],'VERSION_REUSED');
$q=$db->query("SELECT COUNT(*) AS n FROM ".MAIN_DB_PREFIX."societe WHERE entity=1 AND phone='".$db->escape($phone)."'");check((int)$db->fetch_object($q)->n===1,'DUPLICATE_PROSPECT');
$cap=['reference'=>$a['request_ref'],'token'=>$a['token']];denied(fn()=>$service->execute($cap+['action'=>'download'],$actor),409);
$pdf=file_get_contents(getenv('M085_PDF_FIXTURE'));check(substr($pdf,0,5)==='%PDF-','PDF_FIXTURE_REQUIRED');
$r=$service->execute($cap+['action'=>'archive','pdf'=>base64_encode($pdf)],$actor);check($r['sha256']===hash('sha256',$pdf),'ARCHIVE_HASH');
// A second archive cannot replace the already accepted immutable document.
$r2=$service->execute($cap+['action'=>'archive','pdf'=>base64_encode($pdf.'CHANGED')],$actor);check($r2['sha256']===$r['sha256'],'IMMUTABLE_REPLACED');
$d=$service->execute($cap+['action'=>'download'],$actor);check(base64_decode($d['pdf'])===$pdf,'DOWNLOAD_BYTES');
$record=$service->staffRecord($row['rowid']);check($record['metadata']['download_requests']===1,'DOWNLOAD_EVENT');
$bad=$cap;$bad['token']=substr($bad['token'],0,-1).(substr($bad['token'],-1)==='0'?'1':'0');denied(fn()=>$service->execute($bad+['action'=>'download'],$actor),403);
$other=new PublicRequestPdfService($db,2);denied(fn()=>$other->execute($cap+['action'=>'download'],$actor),403);denied(fn()=>$other->staffRecord($row['rowid']),404);
$badRef=$cap;$badRef['reference']='../../conf';denied(fn()=>$service->execute($badRef+['action'=>'download'],$actor),422);
$db->query("UPDATE ".MAIN_DB_PREFIX."cpo_public_request SET date_anonymized=NOW() WHERE rowid=".(int)$row['rowid']);denied(fn()=>$service->execute($cap+['action'=>'download'],$actor),404);
$db->query("UPDATE ".MAIN_DB_PREFIX."cpo_public_request SET date_anonymized=NULL WHERE rowid=".(int)$row['rowid']);
echo json_encode(['ok'=>true,'reference'=>$a['request_ref'],'nativeProspect'=>true,'duplicatePrevented'=>true,'newVersion'=>true,'immutableBytes'=>true,'crossEntityDenied'=>true,'wrongTokenDenied'=>true,'anonymizedDenied'=>true])."\n";
