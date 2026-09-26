<?php
require_once __DIR__.'/InternalTaskAuthorizationService.class.php';
require_once __DIR__.'/InternalTaskSchedule.class.php';

/** Read-only native calendar projection. Task permissions remain authoritative. */
class InternalTaskAgendaService
{
    private $db;
    private $actor;
    private $entity;
    private $auth;

    public function __construct($db, $actor, $entity)
    {
        $this->db = $db;
        $this->actor = $actor;
        $this->entity = (int) $entity;
        $this->auth = new InternalTaskAuthorizationService($db, $actor, $entity);
    }

    public function events($first, $last, array $filters = array())
    {
        global $langs;
        if (!$this->auth->canOpenBoard()) return array();
        $allAgenda = !empty($this->actor->admin) || $this->actor->hasRight('agenda', 'allactions', 'read');
        if (!$allAgenda && !$this->actor->hasRight('agenda', 'myactions', 'read')) return array();
        $first = (int) $first; $last = (int) $last;
        if ($first <= 0 || $last <= $first || $last - $first > 93 * 86400) return array();
        // Native event categories/resources/types do not apply to task cards.
        if (!empty($filters['resourceid']) || !empty($filters['type']) || (int)($filters['category']??-1) > 0) return array();
        $types = is_array($filters['actioncode']??null) ? $filters['actioncode'] : explode(',', (string)($filters['actioncode']??''));
        foreach ($types as $type) if (!in_array((string)$type, array('', '-1', '0', 'AC_NON_AUTO', 'AC_OTH'), true)) return array();

        $where = array('t.entity='.$this->entity, "t.status NOT IN ('cancelled','deleted')", 't.date_archived IS NULL', $this->auth->readableSql('t'));
        $owner = (string)($filters['owner']??'-1');
        if (!$allAgenda) $where[] = 't.fk_user_assign='.$this->auth->actorId();
        if ($owner === '-2') $where[] = 't.fk_user_assign IS NULL';
        elseif ($owner === '-3') {
            $children = method_exists($this->actor, 'getAllChildIds') ? $this->actor->getAllChildIds(1) : array($this->auth->actorId());
            $ids = array_filter(array_map('intval', $children), function($id){return $id > 0;});
            $where[] = 't.fk_user_assign IN ('.($ids ? implode(',', $ids) : '0').')';
        } elseif ($owner !== '' && $owner !== '-1') {
            if (!preg_match('/^[1-9][0-9]*(,[1-9][0-9]*)*$/D', $owner)) return array();
            $where[] = 't.fk_user_assign IN ('.implode(',', array_map('intval', explode(',', $owner))).')';
        }
        if (!empty($filters['group'])) $where[] = 'EXISTS (SELECT 1 FROM '.MAIN_DB_PREFIX.'usergroup_user gu WHERE gu.entity=t.entity AND gu.fk_user=t.fk_user_assign AND gu.fk_usergroup='.(int)$filters['group'].')';
        if (!empty($filters['socid'])) $where[] = 'o.fk_soc='.(int)$filters['socid'];
        if (!empty($filters['project'])) $where[] = 'o.fk_project='.(int)$filters['project'];
        $status = (string)($filters['status']??'');
        if ($status === '0') $where[] = "t.status='todo'";
        elseif ($status === '50') $where[] = "t.status IN ('in_progress','waiting','review')";
        elseif (in_array($status, array('done','100'), true)) $where[] = "t.status='done'";
        elseif ($status === 'todo') $where[] = "t.status<>'done'";
        elseif ($status === 'na') return array();

        // Restrict the query to the visible range before materializing calendar days.
        // JSON_UNQUOTE inherits different collations on MySQL and MariaDB. Dates
        // contain only ASCII; binary comparison also handles legacy table collations.
        $start = "NULLIF(CAST(JSON_UNQUOTE(JSON_EXTRACT(CASE WHEN JSON_VALID(d.payload) THEN d.payload ELSE '{}' END, '$.start_date')) AS BINARY), '')";
        // Include timezone boundary days for legacy date-only values; exact clipping is below.
        $where[] = 'COALESCE('.$start.',DATE(t.date_deadline)) <= '.$this->q($this->db->idate($last + 2 * 86400));
        $where[] = 'COALESCE(t.date_deadline,CONCAT('.$start.", ' 23:59:59')) >= ".$this->q($this->db->idate($first - 2 * 86400));
        $sql = 'SELECT t.rowid,t.ref,t.title,t.status,t.progress,t.date_deadline,t.fk_user_assign,d.payload FROM '.MAIN_DB_PREFIX.'cpo_task t';
        $sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'cpo_task_details d ON d.entity=t.entity AND d.fk_task=t.rowid';
        $sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'cpo_operation o ON o.entity=t.entity AND o.rowid=t.fk_operation';
        $sql .= ' WHERE '.implode(' AND ', $where).' ORDER BY t.rowid';
        $res = $this->db->query($sql);
        if (!$res) throw new RuntimeException('Unable to load scheduled tasks.');
        require_once DOL_DOCUMENT_ROOT.'/comm/action/class/actioncomm.class.php';
        $langs->load('chinaplanetoperations@chinaplanetoperations');
        $out = array();
        while ($row = $this->db->fetch_object($res)) {
            $details = $row->payload ? json_decode($row->payload, true) : array();
            if (!is_array($details)) continue;
            try {
                $startValue = InternalTaskSchedule::start($details);
                $endValue = InternalTaskSchedule::dateTime($row->date_deadline);
            } catch (RuntimeException $e) { continue; }
            if ($startValue === '' && $endValue === '') continue;
            // Existing inconsistent schedules still show their deadline, never a reversed range.
            if ($startValue !== '' && $endValue !== '' && $endValue < $startValue) $startValue = '';
            $event = new ActionComm($this->db);
            $event->id = -(int)$row->rowid; // No native event ID can be mutated or opened by mistake.
            $event->ref = $row->ref;
            $event->type = 'cpo_task';
            // Native read-only external-event behavior prevents Agenda drag/drop writes.
            $event->type_code = 'ICALEVENT';
            $event->icalname = $langs->trans('CPTAgendaTask');
            $event->icalcolor = $row->status === 'done' ? '64748b' : ($row->status === 'todo' ? '2563eb' : 'd97706');
            $event->type_color = $event->icalcolor;
            $event->label = $langs->trans('TaskStatus_'.$row->status).' · '.$row->title;
            $event->array_options['cpo_task_status'] = $langs->trans('TaskStatus_'.$row->status);
            $event->array_options['cpo_task_title'] = $row->title;
            $event->percentage = $row->status === 'done' ? 100 : ($row->status === 'todo' ? 0 : max(1, min(99, (int)$row->progress)));
            $event->datep = $this->db->jdate($startValue !== '' ? $startValue : $endValue);
            $event->datef = $endValue !== '' ? $this->db->jdate($endValue) : $event->datep;
            $event->fulldayevent = $startValue !== '' && empty($details['start_time']) && $endValue === '' ? 1 : 0;
            if ($event->fulldayevent) {
                $y = (int)substr($startValue, 0, 4); $m = (int)substr($startValue, 5, 2); $d = (int)substr($startValue, 8, 2);
                $event->datep = dol_mktime(0, 0, 0, $m, $d, $y, 'tzuserrel');
                $event->datef = dol_mktime(23, 59, 59, $m, $d, $y, 'tzuserrel');
            }
            if ($event->datef < $first || $event->datep >= $last) continue;
            $event->date_start_in_calendar = max($first, $event->datep);
            $event->date_end_in_calendar = min($last - 1, $event->datef);
            $event->userownerid = (int)$row->fk_user_assign;
            $event->authorid = 0;
            $event->userassigned = $event->userownerid ? array($event->userownerid=>array('id'=>$event->userownerid)) : array();
            $event->transparency = $row->status === 'done' ? 0 : 1;
            $day = $this->dayKey($event->date_start_in_calendar);
            $endDay = $this->dayKey($event->date_end_in_calendar);
            for (; $day <= $endDay; $day += 86400) $out[$day][] = $event;
        }
        return $out;
    }

    private function q($value) { return "'".$this->db->escape((string)$value)."'"; }
    private function dayKey($timestamp)
    {
        return dol_mktime(0, 0, 0, (int)dol_print_date($timestamp, '%m', 'tzuserrel'), (int)dol_print_date($timestamp, '%d', 'tzuserrel'), (int)dol_print_date($timestamp, '%Y', 'tzuserrel'), 'gmt');
    }
}
