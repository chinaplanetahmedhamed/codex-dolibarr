<?php
define('NOREQUIREHTML', 1);
define('NOREQUIREMENU', 1);
require_once __DIR__.'/lib/main.lib.php';
require_once __DIR__.'/lib/portal.lib.php';
require_once __DIR__.'/lib/operation_reports.lib.php';

cpcc_portal_require_access($user);
$socid = cpcc_portal_customer_scope_id($db, $user, GETPOSTINT('socid'));
$publication = cpcc_qc_publication_by_uuid($db, GETPOST('report', 'alphanohtml'), $socid);
$evidence = cpcc_qc_customer_evidence($db, $publication, GETPOST('evidence', 'alphanohtml'));
if (!$publication || !$evidence) { http_response_code(404); exit; }
$path = cpcc_qc_private_file($evidence->safe_stored_name, 'evidence', $evidence->safe_size, $evidence->safe_sha256);
if (!$path) { http_response_code(404); exit; }
$extension = $evidence->evidence_type === 'video' ? '.mp4' : '.jpg';
cpcc_qc_stream_file($path, (string)$evidence->safe_mime, 'QC-'.$publication->operation_ref.'-'.$evidence->client_uuid.$extension, true);
