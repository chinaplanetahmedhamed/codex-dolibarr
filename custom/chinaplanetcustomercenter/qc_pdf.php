<?php
define('NOREQUIREHTML', 1);
define('NOREQUIREMENU', 1);
require_once __DIR__.'/lib/main.lib.php';
require_once __DIR__.'/lib/portal.lib.php';
require_once __DIR__.'/lib/operation_reports.lib.php';

cpcc_portal_require_access($user);
$socid = cpcc_portal_customer_scope_id($db, $user, GETPOSTINT('socid'));
$publication = cpcc_qc_publication_by_uuid($db, GETPOST('report', 'alphanohtml'), $socid);
if (!$publication || empty($publication->pdf_stored_name)) { http_response_code(404); exit; }
$path = cpcc_qc_private_file($publication->pdf_stored_name, 'publications', $publication->pdf_size, $publication->pdf_sha256);
if (!$path) { http_response_code(404); exit; }
cpcc_qc_stream_file($path, 'application/pdf', 'QC-'.$publication->operation_ref.'-v'.(int)$publication->version_no.'.pdf', false);
