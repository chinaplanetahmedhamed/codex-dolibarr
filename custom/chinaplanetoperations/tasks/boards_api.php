<?php
if ($_SERVER['REQUEST_METHOD']==='POST') define('CSRFCHECK_WITH_TOKEN',1);
require $_SERVER['DOCUMENT_ROOT'].'/main.inc.php';
require_once __DIR__.'/../class/InternalTaskBoardsService.class.php';
require_once __DIR__.'/../class/InternalTaskWorkspaceGalleryService.class.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
if(GETPOST('errorcode','aZ09')==='InvalidToken'){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'Session token expired. Reload and retry.']);exit;}
try{$svc=new InternalTaskBoardsService($db,$user);if($_SERVER['REQUEST_METHOD']==='GET'){$action=GETPOST('action','aZ09');if(in_array($action,['disable_customer_workspace','enable_supplier_workspace'],true)){http_response_code(405);throw new RuntimeException('Use POST.');}$ws=new InternalTaskWorkspaceService($db,$user,(int)$conf->entity);$wg=new InternalTaskWorkspaceGalleryService($db,$user,(int)$conf->entity);if($action==='deleted_boards')$out=(new InternalTaskBoardLifecycle($db,$user,(int)$conf->entity))->gallery();elseif(in_array($action,['suppliers','supplier_orders','supplier_options'],true)){
    $sw=new SupplierWorkspaceService($db,$user,(int)$conf->entity);
    $page=max(1,min(10000,GETPOSTINT('page')));
    if($action==='suppliers')$out=['suppliers'=>$sw->suppliers(GETPOST('q','alphanohtml'),$page),'can_enable'=>$sw->canEnable()];
    elseif($action==='supplier_options'){
        $rows=$sw->supplierOptions(GETPOST('q','alphanohtml'),$page);
        $out=['suppliers'=>array_slice($rows,0,50),'has_more'=>count($rows)>50,'page'=>$page];
    }
    else{
        $id=GETPOSTINT('supplier');
        $out=['supplier'=>$sw->supplier($id),'projects'=>(new SupplierProjectWorkspaceService($db,$user,(int)$conf->entity))->supplierProjects($id),'orders'=>$sw->orders($id,$page),'can_create'=>$ws->canCreate(),
            'can_account'=>!empty($conf->global->CPO_ACCOUNT_STATEMENTS_ENABLED)&&SupplierAccountService::allowed($user,new CustomerAccountPolicy($db,$user,(int)$conf->entity))];
        if($out['can_create'])$out['teams']=$svc->teams();
    }
    $out['can_create_supplier']=!empty($user->admin)||$user->hasRight('societe','creer');
}elseif($action==='customers')$out=$wg->customers(GETPOST('q','alphanohtml'));elseif($action==='customer_projects')$out=$wg->projects(GETPOSTINT('customer'));elseif($action==='customer_options')$out=['customers'=>$ws->customerOptions(GETPOST('q','alphanohtml'))];elseif($action==='search')$out=['tasks'=>$svc->search(GETPOST('q','alphanohtml'))];elseif($action==='mywork')$out=$svc->myWork(GETPOSTINT('archived')===1);elseif($action==='workspace')$out=$svc->workspace(GETPOSTINT('board'));elseif($action==='shared')$out=['tasks'=>$svc->shared()];else $out=$svc->gallery(GETPOSTINT('archived')===1,GETPOSTINT('general')===1);if($action==='customer_projects'&&!empty($out['can_create']))$out['teams']=$svc->teams();}elseif($_SERVER['REQUEST_METHOD']==='POST'){$action=GETPOST('action','aZ09');$raw=GETPOST('payload','none');if(!is_string($raw)||strlen($raw)>100000)throw new RuntimeException('Payload too large.');$d=json_decode($raw,true);if(!is_array($d))throw new RuntimeException('Invalid payload.');if($action==='create_board'&&!preg_match('/^[a-f0-9]{48}$/D',$d['request']??''))throw new RuntimeException('Board request identity required.');if($action==='enable_supplier_workspace')$out=(new SupplierWorkspaceService($db,$user,(int)$conf->entity))->enableSupplier($d['supplier']??null);elseif(in_array($action,['workspace_star','workspace_reorder','workspace_priority'],true))$out=(new InternalTaskWorkspaceGalleryService($db,$user,(int)$conf->entity))->mutate($action,$d);else $out=$action==='disable_customer_workspace'?(new InternalTaskWorkspaceService($db,$user,(int)$conf->entity))->disableCustomer($d['customer']??0):($action==='enable_customer_workspace'?(new InternalTaskWorkspaceService($db,$user,(int)$conf->entity))->enableCustomer($d['customer']??0):$svc->mutate($action,$d));}else {http_response_code(405);throw new RuntimeException('Use GET or POST.');}echo json_encode(['ok'=>true,'data'=>$out],JSON_UNESCAPED_UNICODE|JSON_HEX_TAG);
}catch(InternalTaskAccessException $e){http_response_code(403);echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);}catch(InternalTaskConflictException $e){http_response_code(409);echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);}catch(Throwable $e){if(http_response_code()!==405)http_response_code(422);echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);}$db->close();
