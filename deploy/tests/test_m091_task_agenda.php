<?php
if (PHP_SAPI !== 'cli' || getenv('M091_LOCAL_RUNTIME') !== '1') exit(1);
require getenv('M091_QA_CONFIG');
if (!preg_match('/^m091_qa_[a-f0-9]{10}$/D', $dolibarr_main_db_name) || $dolibarr_main_db_host !== '127.0.0.1') throw new RuntimeException('Synthetic local database required.');
$_SERVER['DOCUMENT_ROOT'] = $dolibarr_main_document_root;
$_SERVER['SERVER_NAME'] = '127.0.0.1'; $_SERVER['HTTP_HOST'] = '127.0.0.1:8101';
require $dolibarr_main_document_root.'/master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/chinaplanetoperations/class/InternalTaskDetailsService.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/chinaplanetoperations/class/InternalTaskAgendaService.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/chinaplanetoperations/class/actions_chinaplanetoperations.class.php';
function actor($id) { global $db; $u = new User($db); $u->fetch($id); $u->loadRights(); return $u; }
$n = 0;
function ok($condition, $label) { global $n; if (!$condition) throw new RuntimeException('FAIL '.$label); echo 'PASS '.(++$n).' '.$label."\n"; }
function rejects($callback, $label, $type = RuntimeException::class) { try { $callback(); } catch (Throwable $e) { ok($e instanceof $type, $label); return; } ok(false, $label); }
function flat($days) { $out = array(); foreach ($days as $events) foreach ($events as $event) $out[-$event->id] = $event; return $out; }
$user = actor(2); $admin = actor(1); $admin->rights->chinaplanetoperations=(object)array('task_archive'=>1); $other = actor(3);
$tasks = new InternalTaskService($db, $user, 1); $extra = new InternalTaskDetailsService($db, $user, 1);
$calendar = new InternalTaskAgendaService($db, $user, 1);
$_SESSION['dol_tz_string']='Asia/Shanghai';
$first = strtotime('2026-09-01'); $last = strtotime('2026-10-01');
$base = array('title'=>'M091 persisted schedule', 'description'=>'Synthetic calendar acceptance', 'priority'=>'normal', 'fk_usergroup'=>1, 'fk_user_assign'=>2, 'date_deadline'=>'2026-09-27T15:30');
$details = array('start_date'=>'2026-09-27', 'start_time'=>'09:15', 'notes'=>'PRIVATE NOTE NEVER IN AGENDA');
$db->begin();
try {
    $id = $extra->compose($base, $details, array(), '', bin2hex(random_bytes(24)));
    $read = (new InternalTaskDetailsService($db, actor(2), 1))->get($id);
    ok($read['data']['start_time'] === '09:15', 'start time survives service reload');
    $events = flat($calendar->events($first, $last)); $event = $events[$id]??null;
    ok($event && date('H:i', $event->datep) === '09:15' && date('H:i', $event->datef) === '15:30', 'task projects exact start and deadline');
    ok(strpos($event->label, 'To do') !== false && strpos(json_encode($event), 'PRIVATE NOTE') === false, 'localized status without private detail payload');
    ok(count(array_filter(flat($calendar->events($first, $last)), function($e)use($id){return $e->id===-$id;})) === 1, 'repeated calendar reads do not duplicate tasks');
    $collation=$db->fetch_object($db->query('SELECT @@collation_connection AS value'))->value;
    foreach (array('utf8_general_ci','utf8_unicode_ci') as $connectionCollation) {
        $db->query("SET collation_connection='".$connectionCollation."'");
        ok(isset(flat($calendar->events($first,$last))[$id]),'date projection with connection collation '.$connectionCollation);
    }
    $db->query("SET collation_connection='".$db->escape($collation)."'");
    $serverTimezone=date_default_timezone_get();date_default_timezone_set('UTC');
    foreach(array('Asia/Shanghai','America/Los_Angeles') as $userTimezone) {
        $_SESSION['dol_tz_string']=$userTimezone;
        $zoneEvent=flat($calendar->events($first,$last))[$id];
        ok(dol_print_date($zoneEvent->datep,'%Y-%m-%d %H:%M','tzuserrel')==='2026-09-27 09:15'
            && dol_print_date($zoneEvent->datef,'%Y-%m-%d %H:%M','tzuserrel')==='2026-09-27 15:30',
            'Agenda matches task wall-clock dates with UTC server and '.$userTimezone.' user');
    }
    date_default_timezone_set($serverTimezone);$_SESSION['dol_tz_string']='Asia/Shanghai';
    ok(!isset(flat((new InternalTaskAgendaService($db,$other,1))->events($first,$last,array('owner'=>'2')))[$id]), 'forged owner filter cannot expose another worker task');
    ok(!isset(flat($calendar->events($first,$last,array('owner'=>'3')))[$id]), 'owner filter honored');
    $broad=actor(3);$broad->rights->agenda->allactions=(object)array('read'=>1);
    ok(!isset(flat((new InternalTaskAgendaService($db,$broad,1))->events($first,$last))[$id]),'broad Agenda rights do not expand task visibility');
    $broad->rights->chinaplanetoperations->task_manage_team=1;
    ok(!isset(flat((new InternalTaskAgendaService($db,$broad,1))->events($first,$last))[$id]),'supervisor without team grant denied');
    $managerTasks=new InternalTaskService($db,$admin,1);$managerTasks->saveGrant(1,3,true);
    ok(isset(flat((new InternalTaskAgendaService($db,$broad,1))->events($first,$last))[$id]),'granted supervisor sees authorized team task');
    $managerTasks->saveGrant(1,3,false);
    ok(!isset(flat((new InternalTaskAgendaService($db,$broad,1))->events($first,$last))[$id]),'revoked team grant removes projection immediately');

    ok(!isset(flat($calendar->events($first,$last,array('status'=>'done')))[$id]), 'completed filter excludes open work');
    ok(!isset(flat($calendar->events($first,$last,array('category'=>123)))[$id]), 'native category filter does not leak unrelated task cards');
    $adminTasks = new InternalTaskService($db,$admin,1);
    $version=$tasks->getTask($id)['version']; $version=$tasks->transition($id,$version,'in_progress');
    ok(flat($calendar->events($first,$last,array('status'=>'50')))[$id]->percentage > 0, 'start work immediately updates Agenda state');
    $version=$tasks->updateProgress($id,$version,45);
    ok(flat($calendar->events($first,$last))[$id]->percentage === 45, 'progress refresh is current');
    $version=$adminTasks->updateTask($id,$version,array_merge($base,array('fk_user_assign'=>3)));
    ok(!isset(flat($calendar->events($first,$last))[$id]) && isset(flat((new InternalTaskAgendaService($db,$other,1))->events($first,$last))[$id]), 'reassignment removes old staff entry and shows new owner');
    $version=$adminTasks->updateTask($id,$version,$base);
    $saved=$extra->get($id);$extra->save($id,$saved['version'],array_merge($details,array('start_time'=>'10:30')));
    ok(date('H:i',flat($calendar->events($first,$last))[$id]->datep)==='10:30','rescheduling refreshes same projected entry');
    $version=$tasks->transition($id,$version,'review'); $version=$adminTasks->transition($id,$version,'done');
    ok(isset(flat($calendar->events($first,$last,array('status'=>'done')))[$id]) && !isset(flat($calendar->events($first,$last,array('status'=>'todo')))[$id]),'completion uses native completed/incomplete filters');
    $version=$adminTasks->archive($id,$version,'Synthetic archive');
    ok(!isset(flat($calendar->events($first,$last))[$id]),'archived work disappears');
    $version=$adminTasks->restore($id,$version,'Synthetic restore');
    ok(isset(flat($calendar->events($first,$last))[$id]),'restored completed work returns');
    $point=$extra->compose(array_merge($base,array('title'=>'M091 deadline only')),array(),array(),'',bin2hex(random_bytes(24)));
    $event=flat($calendar->events($first,$last))[$point];ok($event->datep===$event->datef,'deadline-only task is a deadline marker');
    $noDates=$extra->compose(array_merge($base,array('date_deadline'=>'')),array(),array(),'',bin2hex(random_bytes(24)));
    ok(!isset(flat($calendar->events($first,$last))[$noDates]),'unscheduled task omitted');
    $db->query("UPDATE ".MAIN_DB_PREFIX."cpo_task SET status='cancelled' WHERE rowid=".$point);
    ok(!isset(flat($calendar->events($first,$last))[$point]),'cancelled task omitted');
    $db->query("UPDATE ".MAIN_DB_PREFIX."cpo_task SET status='todo' WHERE rowid=".$point);

    $allDay=$extra->compose(array_merge($base,array('date_deadline'=>'')),array('start_date'=>'2026-09-27'),array(),'',bin2hex(random_bytes(24)));
    ok(flat($calendar->events($first,$last))[$allDay]->fulldayevent===1,'legacy date-only schedule remains all day');
    $_SESSION['dol_tz_string']='America/Los_Angeles';
    $localFirst=dol_mktime(0,0,0,9,27,2026,'tzuserrel');$localLast=dol_mktime(0,0,0,9,28,2026,'tzuserrel');
    $zoneDays=$calendar->events($localFirst,$localLast);
    ok(isset($zoneDays[dol_mktime(0,0,0,9,27,2026,'gmt')]) && dol_print_date(flat($zoneDays)[$allDay]->datep,'%Y-%m-%d','tzuserrel')==='2026-09-27','date-only task keeps its calendar date across user timezones');
    $_SESSION['dol_tz_string']='Asia/Shanghai';
    $spans=$extra->compose(array_merge($base,array('date_deadline'=>'2026-10-02T12:00')),array('start_date'=>'2026-08-30','start_time'=>'09:00'),array(),'',bin2hex(random_bytes(24)));
    $days=$calendar->events($first,$last);$count=0;foreach($days as $events)foreach($events as $e)if($e->id===-$spans)$count++;
    ok($count===30,'multi-month task clipped to visible days count='.$count);
    foreach(array(4,5,6) as $uid) ok(!(new InternalTaskAgendaService($db,actor($uid),1))->events($first,$last),'external/entity/inactive exclusion '.$uid);
    $noRights=actor(2);$noRights->rights->chinaplanetoperations=new stdClass();ok(!(new InternalTaskAgendaService($db,$noRights,1))->events($first,$last),'revoked task rights remove Agenda projection');
    $noAgenda=actor(2);$noAgenda->rights->agenda=new stdClass();ok(!(new InternalTaskAgendaService($db,$noAgenda,1))->events($first,$last),'native Agenda rights required');
    $hook=new ActionsChinaplanetoperations($db);$action='';$event=flat($calendar->events($first,$last))[$point];$event->label='Title " <script>alert(1)</script>';
    ok($hook->eventOptions(array(),$event,$action,null)===1 && strpos($hook->resprints,'<script>')===false && strpos($hook->resprints,'tasks/card.php?id='.$point)!==false,'escaped task link targets authorized card');
} finally { while ($db->transaction_opened>0) $db->rollback(); }
foreach(array('2026-02-30T10:00','2026-09-27T25:10','2026-09-27T12:60') as $bad) rejects(function()use($bad){InternalTaskSchedule::dateTime($bad);},'invalid calendar date/time rejected '.$bad);
rejects(function()use($extra){$extra->validate(array('start_time'=>'09:00'));},'time without date rejected');
rejects(function()use($extra,$base){$extra->compose($base,array('start_date'=>'2026-09-28','start_time'=>'09:00'),array(),'',bin2hex(random_bytes(24)));},'reversed schedule rejected before creation');

// Independent committed fixture proves rollback, optimistic locking and reload.
$id=$extra->compose($base,$details,array(),'',bin2hex(random_bytes(24)));
try {
    $version=$tasks->getTask($id)['version'];
    rejects(function()use($tasks,$id,$version,$base){$tasks->updateTask($id,$version,array_merge($base,array('date_deadline'=>'2026-09-27T08:00')));},'deadline edit cannot precede saved start');
    ok($tasks->getTask($id)['date_deadline']==='2026-09-27 15:30:00','invalid deadline leaves saved value unchanged');
    rejects(function()use($extra,$id,$details){$extra->save($id,1,array_merge($details,array('start_time'=>'16:00')));},'start edit cannot exceed saved deadline');
    ok($extra->get($id)['data']['start_time']==='09:15','invalid details save rolls back');
    $extra->save($id,1,array_merge($details,array('start_time'=>'10:00')));
    rejects(function()use($extra,$id,$details){$extra->save($id,1,$details);},'stale schedule edit rejected',InternalTaskConflictException::class);
    ok($extra->get($id)['data']['start_time']==='10:00','stale save preserves latest schedule');
    rejects(function()use($other,$db,$id,$details){(new InternalTaskDetailsService($db,$other,1))->save($id,2,$details);},'other worker cannot change schedule',InternalTaskAccessException::class);
} finally {
    // Remove only the fixture created by this test, never business data.
    foreach(array('cpo_task_details'=>'fk_task','cpo_activity'=>'fk_task','cpo_task'=>'rowid') as $table=>$field) $db->query('DELETE FROM '.MAIN_DB_PREFIX.$table.' WHERE entity=1 AND '.$field.'='.(int)$id);
}
echo 'M091_AGENDA=PASS ASSERTIONS='.$n."\n";
