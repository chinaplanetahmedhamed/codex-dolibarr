<?php
require '../main.inc.php';
require_once __DIR__.'/lib/portal.lib.php';
require_once DOL_DOCUMENT_ROOT.'/custom/chinaplanetoperations/class/PublicRequestPublicationService.class.php';
cpcc_portal_require_access($user);$scope=cpcc_portal_user_scope($user);if(empty($scope['is_external'])||(int)$scope['socid']<=0)accessforbidden();
$service=new PublicRequestPublicationService($db);$file=$service->authorizedDownload(GETPOST('token','alphanohtml'),(int)$scope['socid']);if(!$file)cpcc_portal_access_denied($user,'quotation_requests');
header('Content-Type: '.$file['mime']);header('Content-Length: '.$file['size']);header('Content-Disposition: attachment; filename="'.str_replace(array('"',"\r","\n"),'',$file['name']).'"');header('X-Content-Type-Options: nosniff');header('Cache-Control: private, no-store');readfile($file['path']);exit;
