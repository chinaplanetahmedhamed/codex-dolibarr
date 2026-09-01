<?php

require_once dirname(__DIR__, 2).'/custom/chinaplanetlogistics/lib/chinaplanetlogistics.lib.php';
require_once dirname(__DIR__, 2).'/custom/chinaplanetlogistics/lib/m061_repair.lib.php';
require_once dirname(__DIR__, 2).'/custom/chinaplanetcustomercenter/lib/operations.lib.php';
require_once dirname(__DIR__, 2).'/custom/chinaplanetcustomercenter/lib/portal.lib.php';

$failures = array();
function m061Assert($condition, $message)
{
    global $failures;
    if (!$condition) $failures[] = $message;
}

$now = strtotime('2026-08-19 12:00:00');
$atd = strtotime('2026-08-18 00:00:00');
$ata = strtotime('2026-08-19 00:00:00');

m061Assert(array_keys(chinaplanetlogistics_status_map()) === array(0,1,8,9,2,3,4,5,6,7), 'Canonical status order changed');

$departed = chinaplanetlogistics_normalize_operational_facts(9, $atd, null, '', 0, $now);
m061Assert($departed['ok'] && $departed['status'] === 2, 'ATD must advance pre-sailing status to On Vessel');

$arrived = chinaplanetlogistics_normalize_operational_facts(1, $atd, $ata, 'CONT-1', 0, $now);
m061Assert($arrived['ok'] && $arrived['status'] === 3, 'ATA must win and advance status to Arrived');
m061Assert($arrived['container_count'] === 1 && isset($arrived['changes']['container_count']), 'Container identity must normalize count to one');

$advanced = chinaplanetlogistics_normalize_operational_facts(5, $atd, $ata, 'CONT-2', 2, $now);
m061Assert($advanced['ok'] && $advanced['status'] === 5 && empty($advanced['changes']), 'Operational normalization must never regress a later status');

$future = chinaplanetlogistics_normalize_operational_facts(1, strtotime('2026-08-20'), null, '', 0, $now);
m061Assert(!$future['ok'] && in_array('actual_departure_future', $future['errors'], true), 'Future ATD must fail closed');

$reversed = chinaplanetlogistics_normalize_operational_facts(2, $ata, $atd, '', 0, $now);
m061Assert(!$reversed['ok'] && in_array('arrival_before_departure', $reversed['errors'], true), 'ATA earlier than ATD must fail closed');

$repairRows = array(
    (object) array('rowid'=>31, 'entity'=>1, 'fk_soc'=>4, 'container_no'=>'C-31', 'nb_containers'=>0, 'date_atd'=>'2026-08-18', 'date_ata'=>null, 'fk_statut'=>1, 'tms'=>'2026-08-19 08:00:00'),
    (object) array('rowid'=>32, 'entity'=>1, 'fk_soc'=>4, 'container_no'=>'', 'nb_containers'=>1, 'date_atd'=>'2026-08-19', 'date_ata'=>'2026-08-18', 'fk_statut'=>2, 'tms'=>'2026-08-19 08:00:00'),
    (object) array('rowid'=>33, 'entity'=>1, 'fk_soc'=>4, 'container_no'=>'C-33', 'nb_containers'=>2, 'date_atd'=>'2026-08-18', 'date_ata'=>'2026-08-19', 'fk_statut'=>5, 'tms'=>'2026-08-19 08:00:00'),
);
$repairPlan = cpl_m061_repair_plan_from_rows($repairRows, 1, $now);
m061Assert($repairPlan['candidate_count'] === 1 && $repairPlan['candidates'][0]['id'] === 31, 'Dry-run plan must contain only the exact unambiguous repair candidate');
m061Assert($repairPlan['ambiguous_count'] === 1 && $repairPlan['ambiguous'][0]['id'] === 32, 'Impossible date rows must be excluded and reported');
m061Assert($repairPlan['candidates'][0]['after_status'] === 2 && $repairPlan['candidates'][0]['after_container_count'] === 1, 'Repair plan must combine ATD and container-count corrections');
m061Assert(cpl_m061_repair_validate_plan($repairPlan, 1), 'Repair plan checksum and entity scope must validate');
$tamperedPlan = $repairPlan;
$tamperedPlan['candidates'][0]['after_status'] = 3;
m061Assert(!cpl_m061_repair_validate_plan($tamperedPlan, 1), 'Tampered repair plan must fail checksum validation');
m061Assert(cpcc_portal_date('2026-07-04 15:45:00') === '2026-07-04', 'Portal date must use unambiguous YYYY-MM-DD');
m061Assert(cpcc_portal_date('2026-07-04 15:45:00', true) === '2026-07-04 15:45', 'Portal datetime must use unambiguous YYYY-MM-DD HH:mm');
m061Assert(cpcc_financial_customer_invoice_status(0, 50, 0, '2026-08-01', '2026-08-19') === 'overdue', 'Past unpaid invoice must be overdue');
m061Assert(cpcc_financial_customer_invoice_status(1, 0, 50, '2026-08-01', '2026-08-19') === 'paid', 'Paid invoice must remain paid after due date');

$stages = array();
foreach (array('confirmed','funded','deposit','supplier_paid','service_invoice','shipment','documents','closed') as $index => $key) {
    $stages[] = array('key'=>$key, 'label'=>'AR '.$key, 'en'=>'EN '.$key, 'done'=>$index < 3);
}
$projection = cpcc_project_lifecycle_projection($stages);
m061Assert($projection['progress_percent'] === 38, 'Three of eight stages must round to 38 percent');
m061Assert($projection['current_stage']['key'] === 'supplier_paid' && $projection['next_stage']['key'] === 'service_invoice', 'Current and next stages must be the first two incomplete stages');
m061Assert($projection['timeline'][0]['state'] === 'completed' && $projection['timeline'][3]['state'] === 'current' && $projection['timeline'][4]['state'] === 'next', 'Lifecycle states must be deterministic');

foreach ($stages as &$stage) $stage['done'] = true;
unset($stage);
$complete = cpcc_project_lifecycle_projection($stages);
m061Assert($complete['completed'] && $complete['progress_percent'] === 100, 'All eight evidence stages must produce completion');
$stages[7]['done'] = false;
$active = cpcc_project_lifecycle_projection($stages);
m061Assert(!$active['completed'] && $active['progress_percent'] === 88, 'Missing project closure must prevent 100 percent');

$root = dirname(__DIR__, 2);
$operations = file_get_contents($root.'/custom/chinaplanetcustomercenter/lib/operations.lib.php');
$mobile = file_get_contents($root.'/custom/chinaplanetcustomercenter/lib/mobile_api_data.lib.php');
$projects = file_get_contents($root.'/custom/chinaplanetcustomercenter/projects.php');
$project = file_get_contents($root.'/custom/chinaplanetcustomercenter/project.php');
$kanbanCard = file_get_contents($root.'/custom/cpkanban/card.php');
$class = file_get_contents($root.'/custom/chinaplanetlogistics/class/chinaplanetlogistics.class.php');
$card = file_get_contents($root.'/custom/chinaplanetlogistics/card.php');
$invoice = file_get_contents($root.'/custom/chinaplanetcustomercenter/invoice.php');
$repairEngine = file_get_contents($root.'/custom/chinaplanetlogistics/lib/m061_repair.lib.php');
$repairCli = file_get_contents($root.'/deploy/tools/m061_shipment_consistency_repair.php');
m061Assert(strpos($operations, '$projectClosed&&$orderConfirmed') !== false, 'Final lifecycle stage must require Dolibarr project closure');
$mobileProjectSegment = substr($mobile, strpos($mobile, 'function cpcc_mobile_project_query_base'), strpos($mobile, 'function cpcc_mobile_shipment_query_base') - strpos($mobile, 'function cpcc_mobile_project_query_base'));
m061Assert(strpos($mobileProjectSegment, 'cpcc_mobile_project_projection') !== false && strpos($mobileProjectSegment, 'cpkanban_') === false, 'Mobile project projection must not read Kanban');
m061Assert(strpos($projects, 'cpk-card-link') === false && strpos($projects, 'cpcc_project_lifecycle_projection') !== false, 'External project list must use lifecycle rather than Kanban modal');
m061Assert(strpos($projects, 'if (!$is_external) {'.PHP_EOL.'    require_once DOL_DOCUMENT_ROOT') !== false && strpos($projects, 'if (!$is_external) {'.PHP_EOL.'        $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."cpkanban_card') !== false, 'Kanban code and joins must remain internal-only');
m061Assert(strpos($project, 'AND p.entity=".(int)$conf->entity') !== false && strpos($project, 'AND p.fk_soc=".$socid') !== false, 'Customer project detail must enforce exact entity and authenticated customer ownership');
m061Assert(strpos($kanbanCard, 'M061 keeps CP Kanban staff-only') !== false && strpos($kanbanCard, "header('Location: '.\$customerProjectUrl") !== false, 'Owned legacy Kanban links must redirect customers to the operational project page');
m061Assert(strpos($class, 'normalizeOperationalFacts()') !== false && strpos($class, "'event_type'=>'status_changed'") !== false, 'Normalization must feed the existing audited update');
m061Assert(strpos($class, 'preventStatusRegression') !== false && strpos($class, "'event_type'=>'data_correction'") !== false, 'Interactive normalization must prevent regression and audit count correction');
m061Assert(strpos($card, '$sendStatusEmail = GETPOSTINT') !== false && strpos($card, 'elseif ($sendStatusEmail)') !== false, 'Shipment email must remain explicit after normalization');
m061Assert(strpos($invoice, "'overdue'=>'متأخرة / Overdue'") !== false && strpos($invoice, 'validated invoice has no visible normal lines') !== false, 'Invoice detail must show overdue and staff-only no-lines quality warning');
m061Assert(strpos($repairEngine, "'customer_visible'=>0") !== false && strpos($repairEngine, 'ShipmentStatusEmailService') === false, 'Bulk repair audit must stay private and must not invoke email');
m061Assert(strpos($repairEngine, '$db->begin()') !== false && strpos($repairEngine, '$db->rollback()') !== false && strpos($repairEngine, 'already_applied') !== false, 'Repair apply/rollback must be transactional and idempotent');
m061Assert(strpos($repairCli, "'dry-run', 'apply', 'verify', 'rollback', 'status'") !== false && strpos($repairCli, "m061-r01-root-only") !== false, 'Repair CLI must expose bounded commands and root-only mutation context');

if ($failures) {
    fwrite(STDERR, "M061_OPERATIONAL_CONSISTENCY=FAIL\n- ".implode("\n- ", $failures)."\n");
    exit(1);
}
echo "M061_OPERATIONAL_CONSISTENCY=PASS\n";
