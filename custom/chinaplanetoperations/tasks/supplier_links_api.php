<?php
if ($_SERVER['REQUEST_METHOD']==='POST') define('CSRFCHECK_WITH_TOKEN',1);
require $_SERVER['DOCUMENT_ROOT'].'/main.inc.php';
require_once __DIR__.'/../class/SupplierProjectWorkspaceService.class.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
try {
    if(GETPOST('errorcode','aZ09')==='InvalidToken')throw new InternalTaskAccessException('Reload and retry.');
    $service=new SupplierProjectWorkspaceService($db,$user,(int)$conf->entity);
    $action=GETPOST('action','aZ09');$task=GETPOSTINT('task_id');$project=GETPOSTINT('project_id');
    if($_SERVER['REQUEST_METHOD']==='GET') {
        if($action==='board')$out=$service->boardPanel(GETPOSTINT('board'));
        else {
            $out=$task?$service->forCard($task):$service->projectPanel($project);
            if($action==='options') {
                $rows=$service->options((int)$out['project_id'],GETPOST('q','alphanohtml'),GETPOSTINT('page'));
                $out=['suppliers'=>array_slice($rows,0,50),'has_more'=>count($rows)>50];
            }elseif($action!=='links')throw new InvalidArgumentException('Unknown supplier action.');
        }
    }elseif($_SERVER['REQUEST_METHOD']==='POST') {
        if($action==='draft')$out=$service->createDraft(GETPOSTINT('board'),GETPOST('request','aZ09'));
        elseif(in_array($action,['link','unlink'],true))$out=$service->change($project,$task,GETPOSTINT('supplier'),$action==='link',GETPOSTINT('version'));
        else throw new InvalidArgumentException('Unknown supplier action.');
    }else {http_response_code(405);throw new InvalidArgumentException('Use GET or POST.');}
    echo json_encode(['ok'=>true,'data'=>$out],JSON_UNESCAPED_UNICODE|JSON_HEX_TAG);
}catch(InternalTaskAccessException $e){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'Supplier or project access denied.']);}
catch(InternalTaskConflictException $e){http_response_code(409);echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);}
catch(Throwable $e){if(http_response_code()!==405)http_response_code(422);error_log('M142 supplier links: '.get_class($e));echo json_encode(['ok'=>false,'error'=>'Unable to complete supplier action. Reload and retry.']);}
$db->close();
