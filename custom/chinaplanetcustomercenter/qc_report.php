<?php
if (!isset($_SERVER['QUERY_STRING'])) $_SERVER['QUERY_STRING'] = '';
require_once __DIR__.'/lib/main.lib.php';
require_once __DIR__.'/lib/portal.lib.php';
require_once __DIR__.'/lib/operation_reports.lib.php';

cpcc_portal_require_access($user);
$scope = cpcc_portal_user_scope($user); $isExternal = !empty($scope['is_external']);
$socid = cpcc_portal_customer_scope_id($db, $user, GETPOSTINT('socid'));
$publication = cpcc_qc_publication_by_uuid($db, GETPOST('report', 'alphanohtml'), $socid);
if (!$publication) { http_response_code(404); accessforbidden(); }
$url = cpcc_portal_scoped_url('/chinaplanetcustomercenter/operation_report.php', $isExternal, $socid, array('id'=>(int)$publication->operation_id));
header('Location: '.$url, true, 302); exit;
