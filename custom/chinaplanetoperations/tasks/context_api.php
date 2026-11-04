<?php
if($_SERVER['REQUEST_METHOD']==='POST'&&empty($_POST)&&empty($_FILES)&&(int)($_SERVER['CONTENT_LENGTH']??0)>0){http_response_code(413);header('Content-Type: application/json');echo json_encode(['ok'=>false,'error'=>'Upload exceeds the server limit.']);exit;}
if($_SERVER['REQUEST_METHOD']==='POST')define('CSRFCHECK_WITH_TOKEN',1);
require $_SERVER['DOCUMENT_ROOT'].'/main.inc.php';
require_once __DIR__.'/../class/InternalTaskContextService.class.php';
header('Cache-Control: private, no-store');header('X-Content-Type-Options: nosniff');
try{
    $svc=new InternalTaskContextService($db,$user);$id=GETPOSTINT('task_id');$action=GETPOST('action','aZ09');
    if(GETPOST('errorcode','aZ09')==='InvalidToken')throw new InternalTaskAccessException('Reload and retry.');
    if($_SERVER['REQUEST_METHOD']==='GET'){
        if(in_array($action,['download','preview'],true)){
            $f=$svc->content($id,GETPOSTINT('document_id'),GETPOSTINT('version_id'));
            $preview=$action==='preview'&&$f['kind']==='image';if($action==='preview'&&!$preview)throw new InternalTaskAccessException('Preview unavailable.');
            header('Content-Type: '.($preview?$f['mime']:'application/octet-stream'));header('Content-Length: '.$f['size']);
            $ascii=preg_replace('/[^A-Za-z0-9._-]/','_',$f['name']);header('Content-Disposition: '.($preview?'inline':'attachment').'; filename="'.$ascii.'"; filename*=UTF-8\'\''.rawurlencode($f['name']));
            if($preview)header("Content-Security-Policy: default-src 'none'; img-src 'self'; sandbox");readfile($f['path']);exit;
        }elseif($action==='options')$out=['options'=>$svc->options($id,GETPOST('type','aZ09'),GETPOST('q','alphanohtml'),GETPOSTINT('customer_id'),GETPOSTINT('project_id'))];
        elseif($action==='versions')$out=['versions'=>$svc->versions($id,GETPOSTINT('document_id'))];
        else $out=$svc->get($id,GETPOSTINT('archived')===1);
    }elseif($_SERVER['REQUEST_METHOD']==='POST'){
        if($action==='save_context')$out=$svc->save($id,GETPOSTINT('version'),GETPOSTINT('customer_id'),GETPOSTINT('project_id'),GETPOSTINT('shipment_id'));
        elseif($action==='upload')$out=$svc->upload($id,$_FILES['document_file']??[],GETPOSTINT('replace_document_id'),GETPOSTINT('revision'));
        elseif($action==='rename')$out=$svc->rename($id,GETPOSTINT('document_id'),GETPOSTINT('revision'),GETPOST('name','alphanohtml'));
        elseif($action==='archive'||$action==='restore')$out=$svc->archive($id,GETPOSTINT('document_id'),GETPOSTINT('revision'),$action==='restore');
        elseif($action==='cover')$out=$svc->save($id,GETPOSTINT('version'),0,0,0,true,GETPOSTINT('document_id'));
        else throw new RuntimeException('Unknown action.');
    }else{http_response_code(405);throw new RuntimeException('Use GET or POST.');}
    header('Content-Type: application/json; charset=utf-8');echo json_encode(['ok'=>true,'data'=>$out],JSON_UNESCAPED_UNICODE|JSON_HEX_TAG);$db->close();exit;
}catch(InternalTaskAccessException $e){http_response_code(403);$error='Card or linked record access denied.';}
catch(ShipmentDocumentAccessException $e){http_response_code(403);$error='Card or linked record access denied.';}
catch(InternalTaskConflictException $e){http_response_code(409);$error=$e->getMessage();}
catch(ShipmentDocumentConflictException $e){http_response_code(409);$error=$e->getMessage();}
catch(ShipmentDocumentRecoveryException $e){error_log('M121 document reconciliation: '.json_encode($e->recoveryInfo()));http_response_code(503);$error='Document outcome needs review. Do not repeat the upload. / نتيجة المستند تحتاج مراجعة؛ لا تكرر الرفع.';}
catch(LengthException $e){http_response_code(413);$error=$e->getMessage();}
catch(Throwable $e){if(http_response_code()!==405)http_response_code(422);$error='Unable to save. Check the selected records or file and retry.';error_log('M099 task context: '.get_class($e));}
if(isset($error)){header('Content-Type: application/json; charset=utf-8');echo json_encode(['ok'=>false,'error'=>$error]);}$db->close();
