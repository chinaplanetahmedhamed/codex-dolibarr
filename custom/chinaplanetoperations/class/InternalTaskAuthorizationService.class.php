<?php

class InternalTaskAccessException extends RuntimeException
{
}

/** Central M058 task authorization boundary. */
class InternalTaskAuthorizationService
{
    /** @var DoliDB */
    private $db;

    /** @var object */
    private $actor;

    /** @var int */
    private $entity;

    public function __construct($db, $actor, $entity)
    {
        $this->db = $db;
        $this->actor = is_object($actor) ? $actor : new stdClass();
        $this->entity = (int) $entity;
    }

    public function actorId()
    {
        return !empty($this->actor->id) ? (int) $this->actor->id : 0;
    }

    public function isInternalActive()
    {
        if ($this->actorId() <= 0) return false;
        if (isset($this->actor->entity) && (int) $this->actor->entity > 0 && (int) $this->actor->entity !== $this->entity) return false;
        if (!empty($this->actor->socid) || !empty($this->actor->fk_soc)) return false;
        if (isset($this->actor->statut) && (int) $this->actor->statut !== 1) return false;
        $sql = 'SELECT rowid, entity, admin FROM '.MAIN_DB_PREFIX.'user WHERE rowid='.$this->actorId();
        $sql .= ' AND statut=1 AND COALESCE(fk_soc,0)=0 LIMIT 1';
        $res = $this->db->query($sql);
        $row = $res ? $this->db->fetch_object($res) : null;
        if (!$row) return false;
        $rowEntity = (int) $row->entity;
        if ($rowEntity === $this->entity) return true;
        return $rowEntity === 0 && (int) $row->admin === 1 && !empty($this->actor->admin);
    }

    public function hasRight($right)
    {
        if (!$this->isInternalActive()) return false;
        if (!method_exists($this->actor, 'hasRight')) return false;
        return (bool) $this->actor->hasRight('chinaplanetoperations', (string) $right);
    }

    public function isAdministrator()
    {
        return $this->isInternalActive() && (!empty($this->actor->admin) || $this->hasRight('task_admin'));
    }

    public function canOpenBoard()
    {
        return $this->isAdministrator() || $this->hasRight('task_read_own') || $this->hasRight('task_manage_team');
    }

    public function availableViews()
    {
        $views = array();
        if ($this->isAdministrator()) $views[] = 'all';
        if ($this->hasRight('task_manage_team') || $this->isAdministrator()) $views[] = 'team';
        if ($this->hasRight('task_read_own') || $this->isAdministrator()) $views[] = 'my';
        if ($this->hasRight('task_manage_team') || $this->isAdministrator()) {
            $views[] = 'unassigned';
            $views[] = 'archive';
        }
        if ($this->isAdministrator()) {
            $views[] = 'unclassified';
            $views[] = 'grants';
        }
        return array_values(array_unique($views));
    }

    public function normalizeView($view)
    {
        $view = strtolower(trim((string) $view));
        $available = $this->availableViews();
        if (in_array($view, $available, true)) return $view;
        if ($this->isAdministrator() && in_array('all', $available, true)) return 'all';
        if ($this->hasRight('task_manage_team') && in_array('team', $available, true)) return 'team';
        if (in_array('my', $available, true)) return 'my';
        throw new InternalTaskAccessException('Task access denied.');
    }

    public function canReadTask(array $task)
    {
        if (!$this->taskEntityValid($task) || (string) $task['status'] === 'deleted') return false;
        if ($this->isAdministrator()) return true;
        if ($this->isOwnTask($task) && $this->hasRight('task_read_own')) return true;
        return $this->hasRight('task_manage_team') && $this->hasTeamGrant((int) $task['fk_usergroup']);
    }

    public function canUpdateOwnTask(array $task)
    {
        return $this->canReadTask($task)
            && $this->isOwnTask($task)
            && $this->hasRight('task_read_own')
            && $this->hasRight('task_update_own')
            && empty($task['date_archived'])
            && !in_array((string) $task['status'], array('done','cancelled','deleted'), true);
    }

    public function canManageTask(array $task)
    {
        if (!$this->taskEntityValid($task) || (string) $task['status'] === 'deleted') return false;
        if ($this->isAdministrator()) return true;
        return $this->hasRight('task_manage_team') && $this->hasTeamGrant((int) $task['fk_usergroup']);
    }

    public function canUseTaskFiles(array $task, $write = false, $history = false)
    {
        if (!$this->taskEntityValid($task) || (string) $task['status'] === 'deleted' || !$this->hasRight('task_upload_own')) return false;
        $scoped = false;
        if ($this->isAdministrator()) $scoped = true;
        elseif ($this->isOwnTask($task) && $this->hasRight('task_read_own')) $scoped = true;
        elseif ($this->hasRight('task_manage_team') && $this->hasTeamGrant((int) $task['fk_usergroup'])) $scoped = true;
        if (!$scoped) return false;
        if ($history && !$this->canManageTask($task)) return false;
        if (!$write) return true;
        if (!empty($task['date_archived'])) return false;
        return in_array((string) $task['status'], array('todo','in_progress','waiting'), true);
    }

    public function canArchiveTask(array $task)
    {
        if (!$this->taskEntityValid($task) || !$this->hasRight('task_archive')) return false;
        if ($this->isAdministrator()) return true;
        return $this->hasRight('task_manage_team') && $this->hasTeamGrant((int) $task['fk_usergroup']);
    }

    public function canDeleteTask(array $task)
    {
        return $this->taskEntityValid($task) && $this->isAdministrator() && $this->hasRight('task_delete');
    }

    public function hasTeamGrant($teamId)
    {
        $teamId = (int) $teamId;
        if ($teamId <= 0 || !$this->isInternalActive()) return false;
        $sql = 'SELECT tm.rowid FROM '.MAIN_DB_PREFIX.'cpo_task_team_manager tm';
        $sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'usergroup g ON g.rowid=tm.fk_usergroup AND g.entity=tm.entity';
        $sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'user u ON u.rowid=tm.fk_user AND u.entity=tm.entity AND u.statut=1 AND COALESCE(u.fk_soc,0)=0';
        $sql .= ' WHERE tm.entity='.$this->entity.' AND tm.fk_user='.(int) $this->actorId().' AND tm.fk_usergroup='.$teamId.' AND tm.status=1';
        $res = $this->db->query($sql);
        return $res && (bool) $this->db->fetch_object($res);
    }

    public function isActiveTeamMember($userId, $teamId)
    {
        $userId = (int) $userId;
        $teamId = (int) $teamId;
        if ($userId <= 0 || $teamId <= 0) return false;
        $sql = 'SELECT u.rowid FROM '.MAIN_DB_PREFIX.'user u';
        $sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'usergroup_user gu ON gu.fk_user=u.rowid AND gu.entity=u.entity';
        $sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'usergroup g ON g.rowid=gu.fk_usergroup AND g.entity=gu.entity';
        $sql .= ' WHERE u.rowid='.$userId.' AND u.entity='.$this->entity.' AND u.statut=1 AND COALESCE(u.fk_soc,0)=0';
        $sql .= ' AND g.rowid='.$teamId.' AND g.entity='.$this->entity.' LIMIT 1';
        $res = $this->db->query($sql);
        return $res && (bool) $this->db->fetch_object($res);
    }

    /** Return a SQL predicate containing only server-owned integers. */
    public function visibilitySql($alias, $view)
    {
        if (!$this->canOpenBoard()) throw new InternalTaskAccessException('Task access denied.');
        $alias = preg_replace('/[^a-z0-9_]/i', '', (string) $alias);
        $view = $this->normalizeView($view);
        $own = $this->ownSql($alias);
        $team = $this->teamGrantSql($alias);
        $admin = $this->isAdministrator();

        if ($view === 'my') return $own;
        if ($view === 'team') return $admin ? $alias.'.fk_usergroup IS NOT NULL' : $team;
        if ($view === 'unassigned') return $alias.'.fk_user_assign IS NULL AND '.($admin ? $alias.'.fk_usergroup IS NOT NULL' : $team);
        if ($view === 'all') return $admin ? '1=1' : '1=0';
        if ($view === 'unclassified') return $admin ? $alias.'.fk_usergroup IS NULL' : '1=0';
        if ($view === 'archive') {
            $scope = $admin ? '1=1' : '(('.$own.') OR ('.$team.'))';
            return $scope.' AND ('.$alias.'.date_archived IS NOT NULL OR '.$alias.".status='cancelled')";
        }
        return '1=0';
    }

    public function readableSql($alias)
    {
        if (!$this->canOpenBoard()) return '1=0';
        $alias = preg_replace('/[^a-z0-9_]/i', '', (string) $alias);
        if ($this->isAdministrator()) return '1=1';
        $parts = array();
        if ($this->hasRight('task_read_own')) $parts[] = '('.$this->ownSql($alias).')';
        if ($this->hasRight('task_manage_team')) $parts[] = '('.$this->teamGrantSql($alias).')';
        return $parts ? '('.implode(' OR ', $parts).')' : '1=0';
    }

    public function requireBoard()
    {
        if (!$this->canOpenBoard()) throw new InternalTaskAccessException('Task access denied.');
    }

    public function requireRead(array $task)
    {
        if (!$this->canReadTask($task)) throw new InternalTaskAccessException('Task access denied.');
    }

    private function taskEntityValid(array $task)
    {
        return $this->isInternalActive() && isset($task['entity']) && (int) $task['entity'] === $this->entity;
    }

    private function isOwnTask(array $task)
    {
        return !empty($task['fk_user_assign'])
            && (int) $task['fk_user_assign'] === $this->actorId()
            && !empty($task['fk_usergroup'])
            && $this->isActiveTeamMember($this->actorId(), (int) $task['fk_usergroup']);
    }

    private function ownSql($alias)
    {
        $userId = $this->actorId();
        return $alias.'.fk_user_assign='.$userId
            .' AND '.$alias.'.fk_usergroup IS NOT NULL'
            .' AND EXISTS (SELECT 1 FROM '.MAIN_DB_PREFIX.'user cpo_ou'
            .' INNER JOIN '.MAIN_DB_PREFIX.'usergroup_user cpo_ogu ON cpo_ogu.fk_user=cpo_ou.rowid AND cpo_ogu.entity=cpo_ou.entity'
            .' INNER JOIN '.MAIN_DB_PREFIX.'usergroup cpo_og ON cpo_og.rowid=cpo_ogu.fk_usergroup AND cpo_og.entity=cpo_ogu.entity'
            .' WHERE cpo_ou.rowid='.$userId.' AND cpo_ou.entity='.$this->entity.' AND cpo_ou.statut=1 AND COALESCE(cpo_ou.fk_soc,0)=0'
            .' AND cpo_ogu.fk_usergroup='.$alias.'.fk_usergroup)';
    }

    private function teamGrantSql($alias)
    {
        $userId = $this->actorId();
        return $alias.'.fk_usergroup IS NOT NULL AND EXISTS (SELECT 1 FROM '.MAIN_DB_PREFIX.'cpo_task_team_manager cpo_tm'
            .' INNER JOIN '.MAIN_DB_PREFIX.'usergroup cpo_tg ON cpo_tg.rowid=cpo_tm.fk_usergroup AND cpo_tg.entity=cpo_tm.entity'
            .' INNER JOIN '.MAIN_DB_PREFIX.'user cpo_tu ON cpo_tu.rowid=cpo_tm.fk_user AND cpo_tu.entity=cpo_tm.entity AND cpo_tu.statut=1 AND COALESCE(cpo_tu.fk_soc,0)=0'
            .' WHERE cpo_tm.entity='.$this->entity.' AND cpo_tm.fk_user='.$userId
            .' AND cpo_tm.fk_usergroup='.$alias.'.fk_usergroup AND cpo_tm.status=1)';
    }
}
