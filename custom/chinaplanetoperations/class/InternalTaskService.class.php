<?php

require_once __DIR__.'/InternalTaskMigration.class.php';
require_once __DIR__.'/InternalTaskAuthorizationService.class.php';

class InternalTaskConflictException extends RuntimeException
{
}

/** Authoritative Phase 1 service for cpo_task. */
class InternalTaskService
{
    /** @var DoliDB */
    private $db;

    /** @var object */
    private $actor;

    /** @var int */
    private $entity;

    /** @var InternalTaskAuthorizationService */
    private $auth;

    public function __construct($db, $actor = null, $entity = null)
    {
        global $conf;
        $this->db = $db;
        $this->actor = is_object($actor) ? $actor : (isset($GLOBALS['user']) && is_object($GLOBALS['user']) ? $GLOBALS['user'] : new stdClass());
        $this->entity = $entity === null ? (int) $conf->entity : (int) $entity;
        $this->auth = new InternalTaskAuthorizationService($db, $this->actor, $this->entity);
    }

    public static function boardStatuses()
    {
        return array('todo'=>0,'in_progress'=>0,'waiting'=>0,'review'=>0,'done'=>100);
    }

    public static function allStatuses()
    {
        return array('todo','in_progress','waiting','review','done','cancelled','deleted');
    }

    public static function priorities()
    {
        return array('urgent','high','normal','low');
    }

    public static function waitingParties()
    {
        return array('internal','customer','supplier','forwarder','other');
    }

    public function authorization()
    {
        return $this->auth;
    }

    public function ensureReady()
    {
        $migration = new InternalTaskMigration($this->db);
        if (!$migration->isReady(true)) throw new RuntimeException('Internal task board is not ready.');
        return true;
    }

    public function listTasks(array $filters = array())
    {
        $this->ensureReady();
        $view = $this->auth->normalizeView(isset($filters['view']) ? $filters['view'] : 'my');
        $where = array('t.entity='.$this->entity, "t.status<>'deleted'", $this->auth->visibilitySql('t', $view));
        if ($view !== 'archive') {
            $where[] = 't.date_archived IS NULL';
            $where[] = "t.status<>'cancelled'";
        }
        if (!empty($filters['operation_id'])) $where[] = 't.fk_operation='.(int) $filters['operation_id'];
        if (!empty($filters['status']) && in_array($filters['status'], self::allStatuses(), true)) $where[] = 't.status='.$this->q($filters['status']);
        if (!empty($filters['priority']) && in_array($filters['priority'], self::priorities(), true)) $where[] = 't.priority='.$this->q($filters['priority']);
        if (!empty($filters['team_id']) && ($this->auth->isAdministrator() || $this->auth->hasTeamGrant((int) $filters['team_id']))) $where[] = 't.fk_usergroup='.(int) $filters['team_id'];
        if (!empty($filters['assignee_id']) && $this->auth->isAdministrator()) $where[] = 't.fk_user_assign='.(int) $filters['assignee_id'];
        if (!empty($filters['due'])) {
            if ($filters['due'] === 'overdue') $where[] = "t.date_deadline<NOW() AND t.status NOT IN ('done','cancelled')";
            elseif ($filters['due'] === 'today') $where[] = 'DATE(t.date_deadline)=CURDATE()';
            elseif ($filters['due'] === 'none') $where[] = 't.date_deadline IS NULL';
        }
        if (!empty($filters['q'])) {
            $query = '%'.trim((string) $filters['q']).'%';
            $where[] = '(t.ref LIKE '.$this->q($query).' OR t.title LIKE '.$this->q($query).')';
        }
        $sql = $this->taskProjectionSql();
        $sql .= ' WHERE '.implode(' AND ', $where);
        $sql .= " ORDER BY FIELD(t.priority,'urgent','high','normal','low'),t.date_deadline IS NULL,t.date_deadline,t.position,t.rowid LIMIT 500";
        return $this->rows($sql);
    }

    public function operationTasks($operationId)
    {
        $this->ensureReady();
        if (!$this->auth->canOpenBoard()) return array();
        $sql = $this->taskProjectionSql();
        $sql .= ' WHERE t.entity='.$this->entity.' AND t.fk_operation='.(int) $operationId;
        $sql .= " AND t.date_archived IS NULL AND t.status NOT IN ('cancelled','deleted') AND ".$this->auth->readableSql('t');
        $sql .= ' ORDER BY t.position,t.date_deadline IS NULL,t.date_deadline,t.rowid';
        return $this->rows($sql);
    }

    public function getTask($taskId, $lock = false, $allowDeleted = false)
    {
        $this->ensureReady();
        $sql = $this->taskProjectionSql().' WHERE t.entity='.$this->entity.' AND t.rowid='.(int) $taskId;
        if (!$allowDeleted) $sql .= " AND t.status<>'deleted'";
        if ($lock) $sql .= ' FOR UPDATE';
        $res = $this->db->query($sql);
        $row = $res ? $this->db->fetch_object($res) : null;
        return $row ? get_object_vars($row) : null;
    }

    public function requireReadableTask($taskId)
    {
        $task = $this->getTask($taskId);
        if (!$task) throw new InternalTaskAccessException('Task access denied.');
        $this->auth->requireRead($task);
        return $task;
    }

    public function createTask(array $data, $generated = false, $manageTransaction = true)
    {
        $this->ensureReady();
        $teamId = !empty($data['fk_usergroup']) ? (int) $data['fk_usergroup'] : 0;
        $assigneeId = !empty($data['fk_user_assign']) ? (int) $data['fk_user_assign'] : 0;
        if ($generated && !$this->validTeam($teamId)) {
            $teamId = 0;
            $assigneeId = 0;
        } else {
            if (!$this->validTeam($teamId)) throw new RuntimeException('A valid primary team is required.');
            if (!$this->auth->isAdministrator() && (!$this->auth->hasRight('task_manage_team') || !$this->auth->hasTeamGrant($teamId))) {
                throw new InternalTaskAccessException('Task access denied.');
            }
            if ($assigneeId > 0 && !$this->auth->isActiveTeamMember($assigneeId, $teamId)) throw new RuntimeException('Assignee must be an active team member.');
        }
        $operationId = !empty($data['fk_operation']) ? (int) $data['fk_operation'] : 0;
        if ($operationId > 0 && !$this->validOperation($operationId)) throw new RuntimeException('Invalid operation link.');
        $title = $this->boundedText(isset($data['title']) ? $data['title'] : '', 255, true);
        $description = $this->boundedText(isset($data['description']) ? $data['description'] : '', 20000, true);
        $priority = in_array(isset($data['priority']) ? $data['priority'] : '', self::priorities(), true) ? $data['priority'] : 'normal';
        $deadline = $this->dateOrNull(isset($data['date_deadline']) ? $data['date_deadline'] : '');
        $actorId = $this->auth->actorId();
        if ($actorId <= 0) $actorId = !empty($data['fk_user_creat']) ? (int) $data['fk_user_creat'] : 0;
        if ($actorId <= 0) throw new RuntimeException('Task creator is required.');
        $requestHash = isset($data['request_hash']) ? strtolower(trim((string)$data['request_hash'])) : '';
        if ($requestHash === '' && !$generated && !empty($_POST['token'])) $requestHash = hash('sha256',$this->entity.':'.$actorId.':'.(string)$_POST['token']);
        if ($requestHash !== '' && !preg_match('/^[a-f0-9]{64}$/', $requestHash)) throw new RuntimeException('Invalid task request identity.');
        $requestMessage = $requestHash === '' ? '' : $requestHash.':'.hash('sha256',json_encode(array($teamId,$assigneeId,$operationId,$title,$description,$priority,$deadline)));
        $lockName = '';
        $transactionStarted = false;

        if ($manageTransaction && $requestHash !== '') {
            $lockName = 'm058c'.$this->entity.substr($requestHash, 0, 40);
            $res = $this->db->query('SELECT GET_LOCK('.$this->q($lockName).',5) acquired');
            $row = $res ? $this->db->fetch_object($res) : null;
            if (!$row || (int)$row->acquired !== 1) throw new RuntimeException('Unable to reserve task request.');
        }
        try {
            if ($manageTransaction) {$this->beginTransaction();$transactionStarted=true;}
            if ($requestHash !== '') {
                $res = $this->db->query('SELECT fk_task,message FROM '.MAIN_DB_PREFIX.'cpo_activity WHERE entity='.$this->entity.' AND fk_user='.$actorId." AND action_code='task_create_request' AND message LIKE ".$this->q($requestHash.':%').' AND fk_task IS NOT NULL ORDER BY rowid DESC LIMIT 1 FOR UPDATE');
                $existing = $res ? $this->db->fetch_object($res) : null;
                if ($existing) {
                    if (!hash_equals((string)$existing->message,$requestMessage)) throw new InternalTaskConflictException('Task request payload changed.');
                    if ($manageTransaction) {$this->commitTransaction();$transactionStarted=false;}
                    if ($lockName !== '') {$release=$lockName;$lockName='';$this->releaseNamedLock($release);}
                    return (int)$existing->fk_task;
                }
            }
            $ref = $this->nextRef();
            $sql = 'INSERT INTO '.MAIN_DB_PREFIX.'cpo_task(entity,ref,fk_operation,title,description,fk_user_assign,fk_usergroup,fk_user_creat,fk_user_modif,priority,status,progress,waiting_reason,waiting_on,date_followup,date_deadline,date_completed,date_archived,private_note,position,version,date_creation) VALUES(';
            $sql .= $this->entity.','.$this->q($ref).','.($operationId > 0 ? $operationId : 'NULL').','.$this->q($title).','.$this->q($description).','.($assigneeId > 0 ? $assigneeId : 'NULL').','.($teamId > 0 ? $teamId : 'NULL').','.$actorId.','.$actorId.','.$this->q($priority).",'todo',0,NULL,NULL,NULL,".$deadline.',NULL,NULL,NULL,0,1,'.$this->q($this->now()).')';
            if (!$this->db->query($sql)) throw new RuntimeException('Unable to create task.');
            $taskId = (int) $this->db->last_insert_id(MAIN_DB_PREFIX.'cpo_task');
            $this->log($operationId, $taskId, 'task_created', 'todo', $actorId);
            if ($requestHash !== '') $this->log($operationId, $taskId, 'task_create_request', $requestMessage, $actorId);
            if ($manageTransaction) {$this->commitTransaction();$transactionStarted=false;}
            if ($lockName !== '') {$release=$lockName;$lockName='';$this->releaseNamedLock($release);}
            return $taskId;
        } catch (Throwable $e) {
            if ($transactionStarted) $this->db->rollback();
            if ($lockName !== '') {$release=$lockName;$lockName='';$this->releaseNamedLock($release);}
            throw $e;
        }
    }

    public function updateTask($taskId, $expectedVersion, array $data)
    {
        $this->beginTransaction();
        try {
            $task = $this->getTask($taskId, true);
            if (!$task || !$this->auth->canManageTask($task)) throw new InternalTaskAccessException('Task access denied.');
            $teamId = !empty($data['fk_usergroup']) ? (int) $data['fk_usergroup'] : (int) $task['fk_usergroup'];
            if (!$this->validTeam($teamId)) throw new RuntimeException('A valid primary team is required.');
            if (!$this->auth->isAdministrator() && !$this->auth->hasTeamGrant($teamId)) throw new InternalTaskAccessException('Task access denied.');
            $assigneeId = !empty($data['fk_user_assign']) ? (int) $data['fk_user_assign'] : 0;
            if ($assigneeId > 0 && !$this->auth->isActiveTeamMember($assigneeId, $teamId)) throw new RuntimeException('Assignee must be an active team member.');
            $title = $this->boundedText(isset($data['title']) ? $data['title'] : $task['title'], 255, true);
            $description = $this->boundedText(isset($data['description']) ? $data['description'] : $task['description'], 20000, true);
            $priority = in_array(isset($data['priority']) ? $data['priority'] : '', self::priorities(), true) ? $data['priority'] : $task['priority'];
            $deadline = $this->dateOrNull(isset($data['date_deadline']) ? $data['date_deadline'] : $task['date_deadline']);
            $sql = 'UPDATE '.MAIN_DB_PREFIX.'cpo_task SET title='.$this->q($title).',description='.$this->q($description).',fk_usergroup='.$teamId.',fk_user_assign='.($assigneeId > 0 ? $assigneeId : 'NULL').',priority='.$this->q($priority).',date_deadline='.$deadline.',fk_user_modif='.$this->auth->actorId().',version=version+1';
            $sql .= ' WHERE entity='.$this->entity.' AND rowid='.(int) $taskId.' AND version='.(int) $expectedVersion;
            $this->cas($sql);
            $this->log((int) $task['fk_operation'], (int) $taskId, 'task_updated', 'instruction', $this->auth->actorId());
            $this->commitTransaction();
            return (int) $expectedVersion + 1;
        } catch (Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    public function transition($taskId, $expectedVersion, $target, array $data = array())
    {
        $target = strtolower(trim((string) $target));
        $this->beginTransaction();
        try {
            $task = $this->getTask($taskId, true);
            if (!$task) throw new InternalTaskAccessException('Task access denied.');
            $manager = $this->auth->canManageTask($task);
            $worker = $this->auth->canUpdateOwnTask($task);
            $from = (string) $task['status'];
            $progress = (int) $task['progress'];
            $completed = 'NULL';
            $waitingReason = 'NULL';
            $waitingOn = 'NULL';
            $followup = 'NULL';
            $action = 'task_moved';
            $activityMessage = $target;

            if ($target === 'cancelled') {
                if (!$this->auth->canArchiveTask($task) || !in_array($from, array('todo','in_progress','waiting','review'), true)) throw new InternalTaskAccessException('Task access denied.');
                $reason = $this->boundedText(isset($data['reason']) ? $data['reason'] : '', 500, true);
                $action = 'task_cancelled';
                $activityMessage = 'cancelled: '.$reason;
            } else {
                if (!$manager && !$worker) throw new InternalTaskAccessException('Task access denied.');
                $allowed = array(
                    'todo' => array('in_progress'),
                    'in_progress' => array('waiting','review'),
                    'waiting' => array('in_progress','review'),
                    'review' => $manager ? array('done','in_progress') : array(),
                );
                if (empty($allowed[$from]) || !in_array($target, $allowed[$from], true)) throw new RuntimeException('Task transition is not allowed.');
                if ($target === 'waiting') {
                    $party = isset($data['waiting_on']) ? strtolower(trim((string) $data['waiting_on'])) : '';
                    if (!in_array($party, self::waitingParties(), true)) throw new RuntimeException('Waiting party is required.');
                    $reason = $this->boundedText(isset($data['waiting_reason']) ? $data['waiting_reason'] : '', 2000, true);
                    $date = $this->dateOrNull(isset($data['date_followup']) ? $data['date_followup'] : '');
                    if ($date === 'NULL') throw new RuntimeException('Follow-up date is required.');
                    $waitingReason = $this->q($reason);
                    $waitingOn = $this->q($party);
                    $followup = $date;
                }
                if ($from === 'review' && $target === 'in_progress') {
                    $reason = $this->boundedText(isset($data['reason']) ? $data['reason'] : '', 500, true);
                    $action = 'task_returned';
                    $activityMessage = 'in_progress: '.$reason;
                }
                if ($target === 'done') {
                    $progress = 100;
                    $completed = $this->q($this->now());
                    $action = 'task_completed';
                }
            }
            $sql = 'UPDATE '.MAIN_DB_PREFIX.'cpo_task SET status='.$this->q($target).',progress='.$progress.',waiting_reason='.$waitingReason.',waiting_on='.$waitingOn.',date_followup='.$followup.',date_completed='.$completed.',fk_user_modif='.$this->auth->actorId().',version=version+1';
            $sql .= ' WHERE entity='.$this->entity.' AND rowid='.(int) $taskId.' AND version='.(int) $expectedVersion;
            $this->cas($sql);
            $this->log((int) $task['fk_operation'], (int) $taskId, $action, $activityMessage, $this->auth->actorId());
            $this->commitTransaction();
            return (int) $expectedVersion + 1;
        } catch (Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    public function updateProgress($taskId, $expectedVersion, $progress)
    {
        $progress = (int) $progress;
        if ($progress < 0 || $progress > 99) throw new RuntimeException('Progress must be between 0 and 99.');
        $this->beginTransaction();
        try {
            $task = $this->getTask($taskId, true);
            if (!$task || (!$this->auth->canUpdateOwnTask($task) && !$this->auth->canManageTask($task))) throw new InternalTaskAccessException('Task access denied.');
            if (!empty($task['date_archived']) || !in_array($task['status'], array('in_progress','waiting'), true)) throw new RuntimeException('Progress is read-only in this state.');
            $sql = 'UPDATE '.MAIN_DB_PREFIX.'cpo_task SET progress='.$progress.',fk_user_modif='.$this->auth->actorId().',version=version+1 WHERE entity='.$this->entity.' AND rowid='.(int) $taskId.' AND version='.(int) $expectedVersion;
            $this->cas($sql);
            $this->log((int) $task['fk_operation'], (int) $taskId, 'task_progress', (string) $progress, $this->auth->actorId());
            $this->commitTransaction();
            return (int) $expectedVersion + 1;
        } catch (Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    public function archive($taskId, $expectedVersion, $reason)
    {
        return $this->lifecycle($taskId, $expectedVersion, 'archive', $reason);
    }

    public function restore($taskId, $expectedVersion, $reason)
    {
        return $this->lifecycle($taskId, $expectedVersion, 'restore', $reason);
    }

    public function softDelete($taskId, $expectedVersion, $reason)
    {
        return $this->lifecycle($taskId, $expectedVersion, 'delete', $reason);
    }

    private function lifecycle($taskId, $expectedVersion, $action, $reason)
    {
        $safeReason = $this->boundedText($reason, 500, true);
        $this->beginTransaction();
        try {
            $task = $this->getTask($taskId, true, true);
            if (!$task) throw new InternalTaskAccessException('Task access denied.');
            if ($action === 'delete') {
                if (!$this->auth->canDeleteTask($task) || empty($task['date_archived']) || !in_array($task['status'], array('done','cancelled'), true)) throw new InternalTaskAccessException('Task access denied.');
                $set = "status='deleted',date_archived=COALESCE(date_archived,".$this->q($this->now()).')';
                $code = 'task_deleted';
            } elseif ($action === 'archive') {
                if (!$this->auth->canArchiveTask($task) || !empty($task['date_archived']) || !in_array($task['status'], array('done','cancelled'), true)) throw new InternalTaskAccessException('Task access denied.');
                $set = 'date_archived='.$this->q($this->now());
                $code = 'task_archived';
            } else {
                if (!$this->auth->canArchiveTask($task) || empty($task['date_archived']) || !in_array($task['status'], array('done','cancelled'), true)) throw new InternalTaskAccessException('Task access denied.');
                $set = 'date_archived=NULL';
                $code = 'task_restored';
            }
            $sql = 'UPDATE '.MAIN_DB_PREFIX.'cpo_task SET '.$set.',fk_user_modif='.$this->auth->actorId().',version=version+1 WHERE entity='.$this->entity.' AND rowid='.(int) $taskId.' AND version='.(int) $expectedVersion;
            $this->cas($sql);
            $this->log((int) $task['fk_operation'], (int) $taskId, $code, $action.': '.$safeReason, $this->auth->actorId());
            $this->commitTransaction();
            return (int) $expectedVersion + 1;
        } catch (Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    public function activities($taskId)
    {
        $task = $this->requireReadableTask($taskId);
        $sql = 'SELECT a.rowid,a.action_code,a.message,a.fk_user,a.date_action,u.login FROM '.MAIN_DB_PREFIX.'cpo_activity a';
        $sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'user u ON u.rowid=a.fk_user AND u.entity=a.entity';
        $sql .= ' WHERE a.entity='.$this->entity.' AND a.fk_task='.(int) $task['rowid']." AND a.action_code<>'task_create_request'";
        $sql .= ' ORDER BY a.date_action DESC,a.rowid DESC LIMIT 200';
        return $this->rows($sql);
    }

    public function availableTransitions(array $task)
    {
        if (!empty($task['date_archived']) || in_array($task['status'], array('done','cancelled','deleted'), true)) return array();
        $manager = $this->auth->canManageTask($task);
        $worker = $this->auth->canUpdateOwnTask($task);
        if (!$manager && !$worker) return array();
        $map = array(
            'todo' => array('in_progress'),
            'in_progress' => array('waiting','review'),
            'waiting' => array('in_progress','review'),
            'review' => $manager ? array('done','in_progress') : array(),
        );
        return isset($map[$task['status']]) ? $map[$task['status']] : array();
    }

    public function teamOptions($managedOnly = false)
    {
        if (!$this->auth->canOpenBoard()) return array();
        $sql = 'SELECT g.rowid,g.nom FROM '.MAIN_DB_PREFIX.'usergroup g WHERE g.entity='.$this->entity;
        if ($managedOnly && !$this->auth->isAdministrator()) {
            $sql .= ' AND EXISTS (SELECT 1 FROM '.MAIN_DB_PREFIX.'cpo_task_team_manager tm WHERE tm.entity=g.entity AND tm.fk_usergroup=g.rowid AND tm.fk_user='.$this->auth->actorId().' AND tm.status=1)';
        }
        $sql .= ' ORDER BY g.nom,g.rowid';
        return $this->rows($sql);
    }

    public function userOptionsForTeam($teamId)
    {
        $teamId = (int) $teamId;
        if (!$this->auth->isAdministrator() && (!$this->auth->hasRight('task_manage_team') || !$this->auth->hasTeamGrant($teamId))) return array();
        $sql = 'SELECT u.rowid,u.login,u.firstname,u.lastname FROM '.MAIN_DB_PREFIX.'user u';
        $sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'usergroup_user gu ON gu.fk_user=u.rowid AND gu.entity=u.entity';
        $sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'usergroup g ON g.rowid=gu.fk_usergroup AND g.entity=gu.entity';
        $sql .= ' WHERE g.rowid='.$teamId.' AND u.entity='.$this->entity.' AND u.statut=1 AND u.fk_soc IS NULL ORDER BY u.firstname,u.lastname,u.login';
        return $this->rows($sql);
    }

    public function grantRows()
    {
        if (!$this->auth->isAdministrator()) throw new InternalTaskAccessException('Task access denied.');
        $sql = 'SELECT tm.*,g.nom team_name,u.login,u.firstname,u.lastname FROM '.MAIN_DB_PREFIX.'cpo_task_team_manager tm';
        $sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'usergroup g ON g.rowid=tm.fk_usergroup AND g.entity=tm.entity';
        $sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'user u ON u.rowid=tm.fk_user AND u.entity=tm.entity AND u.statut=1 AND u.fk_soc IS NULL';
        $sql .= ' WHERE tm.entity='.$this->entity.' ORDER BY tm.status DESC,g.nom,u.login';
        return $this->rows($sql);
    }

    public function saveGrant($teamId, $userId, $enabled)
    {
        if (!$this->auth->isAdministrator()) throw new InternalTaskAccessException('Task access denied.');
        $teamId = (int) $teamId;
        $userId = (int) $userId;
        if (!$this->validTeam($teamId) || !$this->validInternalUser($userId)) throw new RuntimeException('Invalid supervisor grant target.');
        $this->beginTransaction();
        try {
            $currentRes = $this->db->query('SELECT status FROM '.MAIN_DB_PREFIX.'cpo_task_team_manager WHERE entity='.$this->entity.' AND fk_usergroup='.$teamId.' AND fk_user='.$userId.' FOR UPDATE');
            if (!$currentRes) throw new RuntimeException('Unable to inspect supervisor grant.');
            $current = $this->db->fetch_object($currentRes);
            if ($current && (int)$current->status === ($enabled ? 1 : 0)) {
                $this->commitTransaction();
                return true;
            }
            $sql = 'INSERT INTO '.MAIN_DB_PREFIX.'cpo_task_team_manager(entity,fk_usergroup,fk_user,status,fk_user_creat,fk_user_modif,date_creation) VALUES(';
            $sql .= $this->entity.','.$teamId.','.$userId.','.($enabled ? 1 : 0).','.$this->auth->actorId().','.$this->auth->actorId().','.$this->q($this->now()).')';
            $sql .= ' ON DUPLICATE KEY UPDATE status=VALUES(status),fk_user_modif=VALUES(fk_user_modif)';
            if (!$this->db->query($sql)) throw new RuntimeException('Unable to save supervisor grant.');
            $this->log(0, 0, $enabled ? 'task_team_grant_enabled' : 'task_team_grant_revoked', 'team_grant', $this->auth->actorId());
            $this->commitTransaction();
            return true;
        } catch (Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    public function internalUsers()
    {
        if (!$this->auth->isAdministrator()) return array();
        $sql = 'SELECT rowid,login,firstname,lastname FROM '.MAIN_DB_PREFIX.'user WHERE entity='.$this->entity.' AND statut=1 AND fk_soc IS NULL ORDER BY firstname,lastname,login';
        return $this->rows($sql);
    }

    public function openCount()
    {
        if (!$this->auth->canOpenBoard()) return 0;
        $view = in_array('my', $this->auth->availableViews(), true) ? 'my' : (in_array('team', $this->auth->availableViews(), true) ? 'team' : 'all');
        $sql = 'SELECT COUNT(*) total FROM '.MAIN_DB_PREFIX.'cpo_task t WHERE t.entity='.$this->entity;
        $sql .= " AND t.status NOT IN ('done','cancelled','deleted') AND t.date_archived IS NULL AND ".$this->auth->visibilitySql('t', $view);
        $res = $this->db->query($sql);
        $row = $res ? $this->db->fetch_object($res) : null;
        return $row ? (int) $row->total : 0;
    }

    private function taskProjectionSql()
    {
        return 'SELECT t.*,o.ref operation_ref,o.title operation_title,u.login owner_login,u.firstname owner_firstname,u.lastname owner_lastname,g.nom team_name,'
            .'(SELECT COUNT(*) FROM '.MAIN_DB_PREFIX."cpo_task_asset ac WHERE ac.entity=t.entity AND ac.fk_task=t.rowid AND ac.status='active') asset_count,"
            .'(SELECT ca.rowid FROM '.MAIN_DB_PREFIX."cpo_task_asset ca WHERE ca.entity=t.entity AND ca.fk_task=t.rowid AND ca.status='active' AND ca.is_cover=1 ORDER BY ca.version_no DESC,ca.rowid DESC LIMIT 1) cover_asset_id"
            .' FROM '.MAIN_DB_PREFIX.'cpo_task t'
            .' LEFT JOIN '.MAIN_DB_PREFIX.'cpo_operation o ON o.rowid=t.fk_operation AND o.entity=t.entity'
            .' LEFT JOIN '.MAIN_DB_PREFIX.'user u ON u.rowid=t.fk_user_assign AND u.entity=t.entity AND u.statut=1 AND u.fk_soc IS NULL'
            .' LEFT JOIN '.MAIN_DB_PREFIX.'usergroup g ON g.rowid=t.fk_usergroup AND g.entity=t.entity';
    }

    private function validTeam($teamId)
    {
        $teamId = (int) $teamId;
        if ($teamId <= 0) return false;
        $res = $this->db->query('SELECT rowid FROM '.MAIN_DB_PREFIX.'usergroup WHERE entity='.$this->entity.' AND rowid='.$teamId.' LIMIT 1');
        return $res && (bool) $this->db->fetch_object($res);
    }

    private function validInternalUser($userId)
    {
        $res = $this->db->query('SELECT rowid FROM '.MAIN_DB_PREFIX.'user WHERE rowid='.(int) $userId.' AND entity='.$this->entity.' AND statut=1 AND fk_soc IS NULL LIMIT 1');
        return $res && (bool) $this->db->fetch_object($res);
    }

    private function validOperation($operationId)
    {
        $res = $this->db->query('SELECT rowid FROM '.MAIN_DB_PREFIX.'cpo_operation WHERE entity='.$this->entity.' AND rowid='.(int) $operationId.' LIMIT 1');
        return $res && (bool) $this->db->fetch_object($res);
    }

    private function nextRef()
    {
        $year = date('Y');
        $like = 'CP-TK-'.$year.'-%';
        $res = $this->db->query('SELECT ref FROM '.MAIN_DB_PREFIX.'cpo_task WHERE entity='.$this->entity.' AND ref LIKE '.$this->q($like).' ORDER BY ref DESC LIMIT 1 FOR UPDATE');
        $row = $res ? $this->db->fetch_object($res) : null;
        $number = $row ? (int) substr((string) $row->ref, -6) + 1 : 1;
        return 'CP-TK-'.$year.'-'.str_pad((string) $number, 6, '0', STR_PAD_LEFT);
    }

    private function cas($sql)
    {
        $res = $this->db->query($sql);
        if (!$res) throw new RuntimeException('Unable to update task.');
        if ($this->db->affected_rows($res) !== 1) throw new InternalTaskConflictException('Task changed. Refresh and retry.');
    }

    private function log($operationId, $taskId, $code, $safeMessage, $actorId)
    {
        $sql = 'INSERT INTO '.MAIN_DB_PREFIX.'cpo_activity(entity,fk_operation,fk_task,action_code,message,visibility,fk_user,date_action) VALUES(';
        $sql .= $this->entity.','.($operationId > 0 ? (int) $operationId : 'NULL').','.($taskId > 0 ? (int) $taskId : 'NULL').','.$this->q($code).','.$this->q($safeMessage).",'internal',".(int) $actorId.','.$this->q($this->now()).')';
        if (!$this->db->query($sql)) throw new RuntimeException('Unable to save task activity.');
    }

    private function dateOrNull($value)
    {
        $value = trim((string) $value);
        if ($value === '') return 'NULL';
        $value = str_replace('T', ' ', $value);
        if (strlen($value) === 16) $value .= ':00';
        if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value)) throw new RuntimeException('Invalid date.');
        return $this->q($value);
    }

    private function boundedText($value, $max, $required)
    {
        $value = trim(strip_tags((string) $value));
        if ($required && $value === '') throw new RuntimeException('Required task value is missing.');
        if (function_exists('mb_strlen') && mb_strlen($value, 'UTF-8') > $max) $value = mb_substr($value, 0, $max, 'UTF-8');
        elseif (strlen($value) > $max) $value = substr($value, 0, $max);
        return $value;
    }

    private function rows($sql)
    {
        $rows = array();
        $res = $this->db->query($sql);
        if (!$res) throw new RuntimeException('Unable to read tasks.');
        while ($row = $this->db->fetch_object($res)) $rows[] = get_object_vars($row);
        return $rows;
    }

    private function q($value)
    {
        return "'".$this->db->escape((string) $value)."'";
    }

    private function now()
    {
        return date('Y-m-d H:i:s');
    }

    private function beginTransaction()
    {
        if (!$this->db->begin()) throw new RuntimeException('Unable to begin task transaction.');
    }

    private function commitTransaction()
    {
        if (!$this->db->commit()) throw new RuntimeException('Unable to commit task transaction.');
    }

    private function releaseNamedLock($name)
    {
        if (!$this->db->query('SELECT RELEASE_LOCK('.$this->q($name).')')) throw new RuntimeException('Unable to release task request lock.');
    }
}
