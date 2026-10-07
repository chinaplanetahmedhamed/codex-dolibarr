<?php
if($_SERVER['REQUEST_METHOD']==='POST'&&empty($_POST)&&empty($_FILES)&&(int)($_SERVER['CONTENT_LENGTH']??0)>0){http_response_code(413);header('Content-Type: application/json');echo json_encode(['ok'=>false,'error'=>'Upload exceeds the server limit.']);exit;}
if($_SERVER['REQUEST_METHOD']==='POST')define('CSRFCHECK_WITH_TOKEN',1);
require_once __DIR__.'/lib/main.lib.php';
require_once __DIR__.'/class/ShipmentDocumentService.class.php';
header('Cache-Control: private, no-store');header('X-Content-Type-Options: nosniff');$sid=GETPOSTINT('shipment_id');$action=GETPOST('action','aZ09');$ajax=GETPOSTINT('json')===1||strtolower($_SERVER['HTTP_X_REQUESTED_WITH']??'')==='xmlhttprequest';
try{
    $svc=new ShipmentDocumentService($db,$user);$svc->requireShipment($sid);if(GETPOST('errorcode','aZ09')==='InvalidToken')throw new ShipmentDocumentAccessException('Reload and retry.');
    $id=GETPOSTINT('document_id');$revision=GETPOSTINT('revision');
    if($_SERVER['REQUEST_METHOD']==='GET'){
        if($action==='download'||$action==='preview'){
            $d=$svc->document($id,'read',GETPOSTINT('version_id')>0);if((int)$d['fk_shipment']!==$sid)throw new ShipmentDocumentAccessException('Document access denied.');$f=$svc->content($id,GETPOSTINT('version_id'));
            $preview=$action==='preview'&&$f['kind']==='image';if($action==='preview'&&!$preview)throw new ShipmentDocumentAccessException('Preview unavailable.');
            header('Content-Type: '.($preview?$f['mime']:'application/octet-stream'));header('Content-Length: '.$f['size']);$ascii=preg_replace('/[^A-Za-z0-9._-]/','_',$f['name']);header('Content-Disposition: '.($preview?'inline':'attachment').'; filename="'.$ascii.'"; filename*=UTF-8\'\''.rawurlencode($f['name']));if($preview)header("Content-Security-Policy: default-src 'none'; img-src 'self'; sandbox");readfile($f['path']);exit;
        }
        throw new RuntimeException('Choose a document action.');
    }
    if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);throw new RuntimeException('Use GET or POST.');}
    if($id){$d=$svc->document($id,'read',$action==='restore');if((int)$d['fk_shipment']!==$sid)throw new ShipmentDocumentAccessException('Document access denied.');}
    if($action==='upload')$out=$svc->upload($sid,$_FILES['document_file']??[],$id,$revision);
    elseif($action==='rename')$out=['revision'=>$svc->rename($id,$revision,GETPOST('name','alphanohtml'))];
    elseif($action==='archive'||$action==='restore')$out=['revision'=>$svc->archive($id,$revision,$action==='restore')];
    elseif($action==='publish')$out=$svc->publishDocument($id,$revision);
    elseif($action==='unpublish')$out=$svc->unpublishDocument($id,$revision);
    else throw new RuntimeException('Unknown action.');
    unset($out['file'],$out['storageKey']);if($ajax){header('Content-Type: application/json');echo json_encode(['ok'=>true,'data'=>$out]);exit;}
    setEventMessages('Document updated / تم تحديث المستند',null,'mesgs');header('Location: '.dol_buildpath('/chinaplanetlogistics/card.php',1).'?id='.$sid.'#shipment-files-manager');exit;
}catch(ShipmentDocumentAccessException $e){http_response_code(403);$error='Document access denied / لا توجد صلاحية';}
catch(ShipmentDocumentConflictException $e){http_response_code(409);$error='Document changed. Reload and retry / تغير المستند، أعد التحميل';}
catch(LengthException $e){http_response_code(413);$error='Upload exceeds the limit / تجاوز حجم الملف الحد المسموح';}
catch(Throwable $e){if(http_response_code()!==405)http_response_code(422);$error='Document operation failed / تعذر تحديث المستند';error_log('M099 shipment document: '.get_class($e));}
header('Content-Type: application/json; charset=utf-8');echo json_encode(['ok'=>false,'error'=>$error]);$db->close();
