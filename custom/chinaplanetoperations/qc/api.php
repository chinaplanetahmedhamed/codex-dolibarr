<?php
$method=strtoupper(isset($_SERVER['REQUEST_METHOD'])?$_SERVER['REQUEST_METHOD']:'GET');
if(in_array($method,array('POST','PUT'),true)&&!empty($_SERVER['HTTP_X_CSRF_TOKEN']))$_POST['token']=(string)$_SERVER['HTTP_X_CSRF_TOKEN'];
define('CSRFCHECK_WITH_TOKEN',1);
require $_SERVER['DOCUMENT_ROOT'].'/main.inc.php';
require_once __DIR__.'/../lib/main.lib.php';
require_once __DIR__.'/../class/QcService.class.php';
require_once __DIR__.'/../class/QcEvidenceService.class.php';
require_once __DIR__.'/../class/QcPublicationService.class.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: private, no-store');header('X-Content-Type-Options: nosniff');header('Referrer-Policy: no-referrer');

function cpo_qc_api_reply($status,array $data){http_response_code($status);echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
function cpo_qc_api_origin(){
    $source=!empty($_SERVER['HTTP_ORIGIN'])?(string)$_SERVER['HTTP_ORIGIN']:(string)($_SERVER['HTTP_REFERER']??'');
    if($source===''||strpos($source,',')!==false||preg_match('/[\r\n]/',$source))cpo_qc_api_reply(403,array('ok'=>false,'error'=>'origin_required'));
    $configured=trim((string)($GLOBALS['conf']->global->CHINAPLANETOPERATIONS_PUBLIC_ORIGIN??''));
    if($configured!=='')$expected=parse_url($configured);else{$host=(string)($_SERVER['HTTP_HOST']??'');if(!preg_match('/^[A-Za-z0-9.-]+(?::\d{1,5})?$/',$host))cpo_qc_api_reply(403,array('ok'=>false,'error'=>'invalid_host'));$https=!empty($_SERVER['HTTPS'])&&strtolower((string)$_SERVER['HTTPS'])!=='off';$expected=parse_url(($https?'https':'http').'://'.$host);}
    $actual=parse_url($source);if(!$expected||!$actual||!empty($actual['user'])||!empty($actual['pass'])||empty($actual['scheme'])||empty($actual['host']))cpo_qc_api_reply(403,array('ok'=>false,'error'=>'cross_origin_denied'));
    $canonical=function($value){$scheme=strtolower((string)$value['scheme']);if(!in_array($scheme,array('http','https'),true))return'';$port=isset($value['port'])?(int)$value['port']:($scheme==='https'?443:80);if($port<1||$port>65535)return'';return$scheme.'://'.strtolower((string)$value['host']).':'.$port;};
    if($canonical($actual)===''||!hash_equals($canonical($expected),$canonical($actual)))cpo_qc_api_reply(403,array('ok'=>false,'error'=>'cross_origin_denied'));
}
function cpo_qc_api_json(){if((int)($_SERVER['CONTENT_LENGTH']??0)>1048576)cpo_qc_api_reply(413,array('ok'=>false,'error'=>'json_too_large'));$raw=file_get_contents('php://input');$data=json_decode((string)$raw,true);if(!is_array($data))cpo_qc_api_reply(422,array('ok'=>false,'error'=>'invalid_json'));return$data;}

$action=GETPOST('action','alphanohtml');$operationId=GETPOSTINT('id');$qc=new QcService($db);$evidence=new QcEvidenceService($db);$payload=array();
try{
 if($method==='GET'&&$action==='package'){cpo_require($user,'qc_execute');$payload=$qc->fieldPackage($operationId,(int)$user->id);$payload['csrf_token']=newToken();}
 elseif($method==='GET'&&$action==='review_bundle'){cpo_require($user,'qc_review');$payload=array('state'=>$qc->planState($operationId),'snapshot'=>$qc->sourceSnapshot($operationId),'checkpoints'=>$qc->checkpoints($operationId),'evidence'=>$evidence->evidence($operationId),'csrf_token'=>newToken());}
 elseif($method==='POST'&&$action==='checkpoint_result'){cpo_qc_api_origin();cpo_require($user,'qc_execute');$data=cpo_qc_api_json();$payload['checkpoint']=$qc->saveCheckpointResult($operationId,(int)($data['checkpoint_id']??0),$data,(int)($data['revision']??0),(string)($data['mutation_id']??''),(int)$user->id);$payload['csrf_token']=newToken();}
 elseif($method==='POST'&&$action==='finding'){cpo_qc_api_origin();cpo_require($user,'qc_execute');$data=cpo_qc_api_json();$payload['checkpoint']=$qc->addFinding($operationId,$data,(int)$user->id);$payload['csrf_token']=newToken();}
 elseif($method==='POST'&&$action==='submit'){cpo_qc_api_origin();cpo_require($user,'qc_execute');$data=cpo_qc_api_json();$payload['state']=$qc->submitInspection($operationId,(int)($data['package_revision']??0),(int)$user->id,!empty($data['acknowledge_internal_only']));$payload['csrf_token']=newToken();}
 elseif($method==='POST'&&$action==='upload_begin'){cpo_qc_api_origin();cpo_require($user,'qc_execute');$data=cpo_qc_api_json();$payload['upload']=$evidence->beginUpload($operationId,(int)($data['checkpoint_id']??0),$data,(int)$user->id);$payload['csrf_token']=newToken();}
 elseif($method==='PUT'&&$action==='upload_chunk'){cpo_qc_api_origin();cpo_require($user,'qc_execute');$length=(int)($_SERVER['CONTENT_LENGTH']??0);if($length<=0||$length>QcEvidenceService::MAX_CHUNK)cpo_qc_api_reply(413,array('ok'=>false,'error'=>'chunk_too_large'));$bytes=file_get_contents('php://input');$payload['chunk']=$evidence->storeChunk(GETPOSTINT('evidence_id'),(string)($_SERVER['HTTP_X_UPLOAD_TOKEN']??''),GETPOSTINT('chunk_index'),strtolower((string)($_SERVER['HTTP_X_CHUNK_SHA256']??'')),$bytes,(int)$user->id);$payload['csrf_token']=newToken();}
 elseif($method==='POST'&&$action==='upload_finalize'){cpo_qc_api_origin();cpo_require($user,'qc_execute');$data=cpo_qc_api_json();$payload['evidence']=$evidence->finalizeUpload((int)($data['evidence_id']??0),(string)($data['upload_token']??''),strtolower((string)($data['file_sha256']??'')),(int)$user->id);$payload['csrf_token']=newToken();}
 elseif($method==='POST'&&$action==='review_finding'){cpo_qc_api_origin();cpo_require($user,'qc_review');$data=cpo_qc_api_json();$qc->reviewFinding($operationId,(int)($data['checkpoint_id']??0),(string)($data['disposition']??''),(string)($data['note']??''),(int)$user->id);$payload['csrf_token']=newToken();}
 elseif($method==='POST'&&$action==='review_evidence'){cpo_qc_api_origin();cpo_require($user,'qc_review');$data=cpo_qc_api_json();$evidence->reviewEvidence($operationId,(int)($data['evidence_id']??0),(string)($data['disposition']??''),(string)($data['note']??''),(int)$user->id);$payload['csrf_token']=newToken();}
 elseif($method==='POST'&&$action==='reprocess_evidence'){cpo_qc_api_origin();cpo_require($user,'qc_review');$data=cpo_qc_api_json();$payload['evidence']=$evidence->reprocessEvidence($operationId,(int)($data['evidence_id']??0),(int)$user->id);$payload['csrf_token']=newToken();}
 elseif($method==='POST'&&$action==='mark_reviewed'){cpo_qc_api_origin();cpo_require($user,'qc_review');$payload['state']=$qc->markReviewed($operationId,(int)$user->id);$payload['csrf_token']=newToken();}
 elseif($method==='POST'&&$action==='prepare_revision'){cpo_qc_api_origin();cpo_require($user,'qc_review');$data=cpo_qc_api_json();$payload['state']=$qc->preparePublicationRevision($operationId,(string)($data['reason']??''),(int)$user->id);$payload['csrf_token']=newToken();}
 elseif($method==='POST'&&$action==='publish'){cpo_qc_api_origin();cpo_require($user,'qc_publish');$data=cpo_qc_api_json();$payload['publication']=(new QcPublicationService($db))->publish($operationId,(string)($data['overall_result']??''),(string)($data['override_reason']??''),(int)$user->id);$payload['csrf_token']=newToken();}
 else cpo_qc_api_reply(404,array('ok'=>false,'error'=>'unsupported_action'));
 cpo_qc_api_reply(200,array_merge(array('ok'=>true),$payload));
}catch(QcConflictException $e){cpo_qc_api_reply(409,array('ok'=>false,'error'=>'conflict','message'=>$e->getMessage(),'csrf_token'=>newToken()));}
catch(QcEvidenceConflictException $e){cpo_qc_api_reply(409,array('ok'=>false,'error'=>'conflict','message'=>$e->getMessage(),'csrf_token'=>newToken()));}
catch(Throwable $e){cpo_qc_api_reply(422,array('ok'=>false,'error'=>'validation','message'=>$e->getMessage(),'csrf_token'=>newToken()));}
