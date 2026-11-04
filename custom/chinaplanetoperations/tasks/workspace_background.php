<?php
if($_SERVER['REQUEST_METHOD']==='POST'&&empty($_POST)&&empty($_FILES)&&(int)($_SERVER['CONTENT_LENGTH']??0)>0){http_response_code(413);header('Content-Type: application/json');header('Cache-Control: no-store');echo json_encode(['ok'=>false,'error'=>'Choose an image smaller than 5 MB.']);exit;}
if($_SERVER['REQUEST_METHOD']==='POST')define('CSRFCHECK_WITH_TOKEN',1);
require $_SERVER['DOCUMENT_ROOT'].'/main.inc.php';
require_once __DIR__.'/../class/InternalTaskWorkspaceAppearance.class.php';
header('Cache-Control: private, no-store');header('X-Content-Type-Options: nosniff');
try{
    $svc=new InternalTaskWorkspaceAppearance($db,$user,(int)$conf->entity);
    if($_SERVER['REQUEST_METHOD']==='GET'){
        $image=$svc->read(GETPOSTINT('customer'));header('Content-Type: '.$image['mime']);header('Content-Disposition: inline');header("Content-Security-Policy: default-src 'none'; sandbox");echo $image['bytes'];exit;
    }
    if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit;}
    if(GETPOST('errorcode','aZ09')==='InvalidToken')throw new InternalTaskAccessException('Session expired. Reload and retry.');
    $out=$svc->save(GETPOSTINT('customer'),GETPOSTINT('version'),GETPOST('background_mode','aZ09'),GETPOST('background','none'),$_FILES['background_image']??[]);
    header('Content-Type: application/json');echo json_encode(['ok'=>true,'data'=>$out]);
}catch(Throwable $e){http_response_code($e instanceof InternalTaskAccessException?403:($e instanceof InternalTaskConflictException?409:($e instanceof LengthException?413:422)));header('Content-Type: application/json');echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);}
