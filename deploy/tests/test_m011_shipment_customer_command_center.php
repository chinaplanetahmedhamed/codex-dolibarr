<?php
/** Synthetic, database-free acceptance and security checks for M011. */

function m011Assert($condition, $message)
{
    if (!$condition) throw new RuntimeException('FAIL '.$message);
    print 'PASS '.$message.PHP_EOL;
}

$repo = dirname(__DIR__, 2);
require_once $repo.'/custom/chinaplanetlogistics/lib/shipment_customer.lib.php';

$statusExpected = array(0=>'inquiry',1=>'booking',8=>'loaded',9=>'export_customs',2=>'departed',3=>'arrived',4=>'import_customs',5=>'import_customs',6=>'delivered',7=>'closed');
foreach ($statusExpected as $status=>$milestone) m011Assert(cpl_status_milestone($status) === $milestone, 'status '.$status.' maps to '.$milestone);

$shipment = (object) array('fk_statut'=>3, 'date_atd'=>strtotime('2026-07-05'), 'date_ata'=>strtotime('2026-07-18'));
$events = array((object) array('event_type'=>'transshipment','milestone_code'=>'transshipment','actual_at'=>'2026-07-10 12:00:00'));
$timeline = cpl_timeline_rows($shipment, $events);
$byCode = array(); foreach ($timeline as $row) $byCode[$row['code']] = $row;
m011Assert($byCode['departed']['state'] === 'complete' && $byCode['arrived']['state'] === 'current', 'timeline uses explicit lifecycle order');
m011Assert(count($byCode['transshipment']['events']) === 1, 'timeline supports repeatable transshipment events');
$directTimeline = cpl_timeline_rows($shipment, array());
$directCodes = array(); foreach ($directTimeline as $row) $directCodes[] = $row['code'];
m011Assert(!in_array('transshipment', $directCodes, true) && array_search('arrived', $directCodes, true) === array_search('departed', $directCodes, true) + 1, 'direct sailing omits the optional transit milestone');
m011Assert($byCode['departed']['actual'] === $shipment->date_atd && $byCode['arrived']['actual'] === $shipment->date_ata, 'timeline distinguishes actual departure and arrival');
m011Assert($byCode['import_customs']['state'] === 'next' && $byCode['delivered']['state'] === 'planned', 'timeline distinguishes next and planned steps');
m011Assert(cpl_milestone_icon('loaded') === 'fa-box' && cpl_milestone_icon('arrived') === 'fa-anchor', 'timeline assigns a visual icon to each milestone');
$unconfirmed = cpl_timeline_rows((object) array('fk_statut'=>4,'date_etd'=>0,'date_atd'=>0,'date_eta'=>strtotime('2026-07-18'),'date_ata'=>0), array());
$unconfirmedByCode = array(); foreach ($unconfirmed as $row) $unconfirmedByCode[$row['code']] = $row;
m011Assert($unconfirmedByCode['arrived']['state'] === 'unconfirmed', 'status progression without ATA does not claim confirmed arrival');
$currentUnconfirmed = cpl_timeline_rows((object) array('fk_statut'=>3,'date_etd'=>0,'date_atd'=>0,'date_eta'=>strtotime('2026-07-18'),'date_ata'=>0), array());
$currentUnconfirmedByCode = array(); foreach ($currentUnconfirmed as $row) $currentUnconfirmedByCode[$row['code']] = $row;
m011Assert($currentUnconfirmedByCode['arrived']['state'] === 'unconfirmed' && !empty($currentUnconfirmedByCode['arrived']['current']), 'unconfirmed current milestone retains aria-current semantics');
m011Assert(cpl_public_source('logistics_partner', 'Secret Forwarder Ltd')['label'] === 'Logistics partner', 'customer attribution ignores arbitrary partner identity');

if (!defined('MAIN_DB_PREFIX')) define('MAIN_DB_PREFIX', 'llx_');
if (!function_exists('dol_now')) { function dol_now() { return strtotime('2026-07-19 12:00:00'); } }
if (!function_exists('dol_print_date')) { function dol_print_date($value, $format = '') { $time = is_numeric($value) ? (int)$value : strtotime((string)$value); return $time ? date('Y-m-d H:i', $time) : ''; } }
if (!function_exists('dol_escape_htmltag')) { function dol_escape_htmltag($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); } }
require_once $repo.'/custom/chinaplanetlogistics/lib/chinaplanetlogistics.lib.php';
class M011FakeDb { public function query($sql) { return false; } public function num_rows($result) { return 0; } public function escape($value) { return addslashes($value); } }
class M011FakeLangs { public function trans($key) { $args = func_get_args(); array_shift($args); return empty($args) ? $key : $key.' '.implode(',', $args); } }
class M011MapLangs { private $values; public function __construct($values) { $this->values = $values; } public function trans($key) { return isset($this->values[$key]) ? $this->values[$key] : $key; } }
$bilingual = new CplBilingualTranslate(new M011MapLangs(array('CPLTimeline'=>'Timeline')), new M011MapLangs(array('CPLTimeline'=>'المسار الزمني')));
m011Assert($bilingual->trans('CPLTimeline') === 'Timeline / المسار الزمني', 'customer command-center labels render in English and Arabic together');
$langs = new M011FakeLangs();
$legacy = (object) array(
    'id'=>91,'entity'=>1,'fk_soc'=>7,'fk_statut'=>4,'ref'=>'SAFE-SHIPMENT','date_etd'=>strtotime('2026-07-01'),'date_atd'=>0,'date_eta'=>strtotime('2026-07-18'),'date_ata'=>0,
    'freetime_days'=>14,'freetime_end'=>0,'container_no'=>'CONT-SAFE','container_type'=>'40HQ','nb_containers'=>1,'booking_no'=>'BOOK-SAFE','bl_no'=>'BL-SAFE',
    'vessel_name'=>'VESSEL-SAFE','voyage_no'=>'VOY-SAFE','port_loading'=>'NINGBO','port_destination'=>'ALEXANDRIA','notes'=>'PRIVATE-NOTE-MUST-NOT-RENDER','customer_note'=>'PUBLIC-BUT-OUTSIDE-M011',
);
ob_start(); cpl_render_shipment_command_center(new M011FakeDb(), $legacy, array('external'=>true,'modal'=>true,'canwrite'=>false,'candelete'=>false,'customer_name'=>'CUSTOMER-A','project_label'=>'PROJECT-A','listurl'=>'#')); $legacyHtml = ob_get_clean();
m011Assert(strpos($legacyHtml, 'CPLLegacySnapshotNotice') !== false && strpos($legacyHtml, 'CPLStateUnconfirmed') !== false, 'legacy customer HTML labels snapshot and unconfirmed arrival truthfully');
m011Assert(strpos($legacyHtml, 'PUBLIC-BUT-OUTSIDE-M011') !== false && strpos($legacyHtml, 'CPLSnapshotOnly') === false, 'customer note is used when no event note is published');
m011Assert(strpos($legacyHtml, 'PRIVATE-NOTE-MUST-NOT-RENDER') === false && strpos($legacyHtml, '<form') === false, 'external shipment HTML excludes private notes and mutation forms');
m011Assert(strpos($legacyHtml, 'aria-current="step"') !== false, 'timeline exposes the current milestone semantically');

$card = file_get_contents($repo.'/custom/chinaplanetlogistics/card.php');
$class = file_get_contents($repo.'/custom/chinaplanetlogistics/class/chinaplanetlogistics.class.php');
$helper = file_get_contents($repo.'/custom/chinaplanetlogistics/lib/shipment_customer.lib.php');
$operations = file_get_contents($repo.'/custom/chinaplanetoperations/class/OperationsService.class.php');
$operationEdit = file_get_contents($repo.'/custom/chinaplanetoperations/operations/edit.php');
$projectCenter = file_get_contents($repo.'/custom/cpkanban/lib/customer_project_center.lib.php');
$css = file_get_contents($repo.'/custom/chinaplanetlogistics/css/shipment-command-center.css');
$eventSql = file_get_contents($repo.'/custom/chinaplanetlogistics/sql/llx_cpl_shipment_event.sql');
$requirementSql = file_get_contents($repo.'/custom/chinaplanetlogistics/sql/llx_cpl_document_requirement.sql');
$shipmentSql = file_get_contents($repo.'/custom/chinaplanetlogistics/sql/llx_chinaplanetlogistics.sql');

m011Assert(strpos($card, "define('CSRFCHECK_WITH_TOKEN', 1)") !== false && strpos($card, "\$is_post") !== false, 'shipment mutations retain CSRF and POST checks');
m011Assert(strpos($card, "hasRight('chinaplanetlogistics', 'write')") !== false && strpos($card, "hasRight('chinaplanetcustomercenter', 'write')") === false, 'customer-center write permission cannot mutate shipments');
m011Assert(strpos($card, "(int) \$object->fk_soc !== (int) \$user->socid") !== false, 'direct customer shipment access verifies ownership');
m011Assert(strpos($card, 'cpl_render_shipment_command_center') !== false && strpos($card, 'if (!$is_external_customer)') !== false, 'customer card uses command center and keeps legacy files internal');
m011Assert(strpos($card, "\$eta_days < 0 ? 'Arrived'") === false, 'elapsed ETA is never rendered as confirmed arrival');
m011Assert(strpos($helper, 'CPLEtaDelayed') !== false && strpos($helper, 'cpl_public_datetime($event->event_at)') !== false && strpos($helper, 'cpl_public_source_label') !== false, 'ETA alert includes direction, change time and safe source');

foreach (array('entity=', 'fk_shipment=', 'fk_soc=', 'customer_visible=1') as $scope) m011Assert(strpos($helper, $scope) !== false, 'customer projections include '.$scope);
m011Assert(strpos($helper, 'SELECT o.rowid,o.title,o.waiting_on,o.next_action,o.date_deadline,o.note_public,o.tms') !== false, 'customer action projection selects public operation fields only');
m011Assert(strpos($helper, 'note_private') === false && strpos($helper, 'owner_login') === false, 'customer shipment helper excludes private operation fields and staff owner');
m011Assert(strpos($helper, "d.visibility='customer_after_approval'") !== false && strpos($helper, "d.review_status IN ('approved','final_received')") !== false && strpos($helper, 'd.is_current=1') !== false, 'operation document link requires approved current customer document');
m011Assert(strpos($helper, 'd.status=1 AND d.customer_visible=1') !== false, 'commercial document link requires published customer document');
m011Assert(strpos($helper, 'FOR UPDATE') !== false && strpos($class, 'FOR UPDATE') !== false, 'shipment history mutations lock owned rows');
m011Assert(strpos($class, 'cpl_shipment_schema_ready') !== false && strpos($class, 'audited shipment changes are blocked') !== false, 'shipment writes fail closed when audit schema is absent');
m011Assert(strpos($class, 'Shipment customer cannot be changed after audit history') !== false, 'shipment ownership freezes after audit rows exist');
m011Assert(strpos($class, '$this->freetime_start = null') !== false && strpos($class, '$this->freetime_end = null') !== false, 'clearing ATA or free-time days clears stale free-time dates');
m011Assert(strpos($class, "'event_type'=>'eta_set'") !== false && strpos($class, "'event_type'=>'atd_set'") !== false && strpos($class, "'event_type'=>'ata_set'") !== false, 'creation writes baseline plus typed initial ETA/ATD/ATA events');
m011Assert(strpos($class, 'safe deletion is blocked') !== false && substr_count($class, 'FOR UPDATE') >= 2 && strpos($class, '$this->db->commit();'.PHP_EOL.'        return 1;'.PHP_EOL.'    }'.PHP_EOL.PHP_EOL.'    // ── HELPERS') !== false, 'delete fails closed and locks the shipment in one transaction');

m011Assert(strpos($operations, 'validateShipmentLink') !== false && strpos($operations, 'fk_soc=".$socid') !== false && strpos($operations, 'COALESCE(fk_project,0)=".$projectId') !== false, 'operation shipment link validates exact entity, customer and project including zero project');
m011Assert(strpos($operations, 'FOR UPDATE') !== false && strpos($operationEdit, "'fk_shipment'=>GETPOSTINT('fk_shipment')") !== false, 'internal operation form persists a locked validated shipment link');
m011Assert(substr_count($helper, 'COALESCE(') >= 3, 'customer action and document projections revalidate exact project ownership');
m011Assert(strpos($helper, "\$row->requirement_status === 'published'") !== false, 'approved source is linked only after checklist publication');
m011Assert(strpos($helper, "action=confirm_delete&amp;token=") !== false && strpos($card, 'data-cpl-back-url') !== false && strpos($card, 'window.location.href=fallback') !== false, 'delete confirmation and modal return have explicit safe navigation tokens/routes');
m011Assert(strpos($css, '.cpl-checklist a') !== false && strpos($css, 'min-height:44px') !== false, 'checklist actions meet the 44px touch target');

m011Assert(substr_count($shipmentSql, 'date_atd date') === 1 && strpos($shipmentSql, 'ALTER TABLE llx_chinaplanetlogistics ADD COLUMN date_atd') !== false, 'ATD migration is additive and non-duplicated');
m011Assert(strpos($eventSql, 'date_value_old') !== false && strpos($eventSql, 'date_value_new') !== false && strpos($eventSql, 'actual_at') !== false, 'event schema records typed ETA and actual dates');
m011Assert(strpos($requirementSql, 'UNIQUE INDEX uk_cpl_requirement_code') !== false && strpos($requirementSql, 'customer_visible') !== false, 'document requirements are stable and publication-scoped');
m011Assert(strpos($helper, 'CREATE TABLE') === false && strpos($helper, 'ALTER TABLE') === false, 'web helper never runs DDL');

foreach (array('cpl-command-hero','cpl-timeline','cpl-checklist','cpl-internal-grid') as $component) m011Assert(strpos($card.$helper, $component) !== false && strpos($css, '.'.$component) !== false, 'command-center component is rendered and styled: '.$component);
m011Assert(strpos($css, '@media(max-width:520px)') !== false && strpos($css, 'grid-template-columns:1fr') !== false, 'command center has a 390px-safe single-column layout');
m011Assert(strpos($css, 'max-width:calc(100vw - 16px)') !== false, 'customer shipment page shell is bounded to the mobile viewport');
m011Assert(strpos($css, 'overflow-x:hidden') !== false, 'customer shipment page prevents fractional page-level horizontal overflow');
m011Assert(strpos($css, '#id-container{display:block!important;width:100%!important') !== false && strpos($css, '#id-right>.fiche{width:calc(100% - 12px)!important') !== false, 'mobile shipment page replaces the desktop table shell with a full-width layout');
m011Assert(strpos($css, '.cpl-timeline li{display:grid;grid-template-columns:42px minmax(0,1fr)') !== false && strpos($css, 'height:calc(100% - 2px)') !== false, 'mobile shipment timeline becomes a readable vertical journey');
m011Assert(strpos($helper, "!empty(\$step['current']) ? 'fa-ship'") !== false && strpos($css, '@keyframes cpl-ship-bob') !== false && strpos($css, 'prefers-reduced-motion:reduce') !== false, 'current shipment stage carries the moving ship marker accessibly');
m011Assert(strpos($projectCenter, 'customer_eta_change') !== false && strpos($projectCenter, 'customer_latest_update') !== false, 'M010 shipment summary receives ETA alert and latest update');

foreach (array('CPLTimeline','CPLEtaPassedUnconfirmed','CPLDocumentChecklist','CPLBackToProject') as $key) {
    m011Assert(strpos(file_get_contents($repo.'/custom/chinaplanetlogistics/langs/en_US/chinaplanetlogistics.lang'), $key.'=') !== false, 'English language includes '.$key);
    m011Assert(strpos(file_get_contents($repo.'/custom/chinaplanetlogistics/langs/ar_SA/chinaplanetlogistics.lang'), $key.'=') !== false, 'Arabic language includes '.$key);
}
$langEn = file_get_contents($repo.'/custom/chinaplanetlogistics/langs/en_US/chinaplanetlogistics.lang');
$langAr = file_get_contents($repo.'/custom/chinaplanetlogistics/langs/ar_SA/chinaplanetlogistics.lang');
m011Assert(strpos($langEn, 'CPLStatusArrivedPort=Arrived port') !== false && strpos($langAr, 'CPLStatusArrivedPort=وصلت إلى الميناء') !== false, 'customer status labels are translated in English and Arabic');
m011Assert(strpos($langEn, 'CPLMilestoneTransshipment=Transit') !== false && strpos($langAr, 'CPLMilestoneTransshipment=ترانزيت') !== false && strpos($langAr, 'إعادة الشحن') === false, 'optional transit milestone uses the approved customer wording');

print 'M011 shipment customer command-center tests passed'.PHP_EOL;
