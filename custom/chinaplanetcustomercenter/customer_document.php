<?php
define('NOREQUIREHTML', 1);
define('NOREQUIREMENU', 1);
require_once __DIR__.'/lib/main.lib.php';
require_once __DIR__.'/lib/portal.lib.php';
require_once __DIR__.'/lib/customer_documents.lib.php';
cpcc_portal_require_access($user);
$socid = cpcc_portal_customer_scope_id($db, $user, GETPOSTINT('socid'));
$file = cpcc_customer_document_core_file($db, $socid, GETPOSTINT('id'));
if (!$file) { http_response_code(404); exit; }
while (ob_get_level()) ob_end_clean();
header('Content-Type: '.($file['mime'] ?: 'application/octet-stream'));
header('Content-Length: '.(int)$file['size']);
header('Content-Disposition: attachment; filename="'.rawurlencode($file['name']).'"');
header('Cache-Control: private, no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
readfile($file['path']);
exit;
