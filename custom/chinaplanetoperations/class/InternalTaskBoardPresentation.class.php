<?php
require_once __DIR__.'/InternalTaskPersonalBoard.class.php';

/** Read-only presentation selection; writes always use the authenticated actor. */
class InternalTaskBoardPresentation
{
    private $service;
    private $auth;
    private $personal;
    private $actor;
    public function __construct($db, $actor, $entity = null)
    {
        $this->actor = $actor;
        $this->service = new InternalTaskService($db, $actor, $entity);
        $this->auth = $this->service->authorization();
        $this->personal = new InternalTaskPersonalBoard($db, $actor, $entity);
    }
    public function staffOptions()
    {
        if ($this->auth->isAdministrator()) return $this->service->internalUsers();
        if (!$this->auth->hasRight('task_manage_team')) return array();
        $users = array($this->auth->actorId() => array('rowid'=>$this->auth->actorId(), 'firstname'=>$this->actor->firstname ?? '', 'lastname'=>$this->actor->lastname ?? '', 'login'=>$this->actor->login ?? ''));
        foreach ($this->service->teamOptions(true) as $team) foreach ($this->service->userOptionsForTeam($team['rowid']) as $user) $users[(int)$user['rowid']] = $user;
        uasort($users, function ($a, $b) {return strcasecmp(trim($a['firstname'].' '.$a['lastname']) ?: $a['login'], trim($b['firstname'].' '.$b['lastname']) ?: $b['login']);});
        return array_values($users);
    }
    public function load(array $filters)
    {
        if (!empty($filters['assignee_id']) && !$this->auth->canViewPersonalBoard((int)$filters['assignee_id'])) throw new InternalTaskAccessException('Task access denied.');
        $view = $this->auth->normalizeView($filters['view'] ?? 'my');
        $ownerId = $view === 'my' ? $this->auth->actorId() : (in_array($view, array('all','team'), true) ? (int)($filters['assignee_id'] ?? 0) : 0);
        if ($ownerId && !$this->auth->canViewPersonalBoard($ownerId)) throw new InternalTaskAccessException('Task access denied.');
        $personal = $ownerId > 0;
        $filters['view'] = $view;
        if ($personal) {
            $filters['assignee_id'] = $ownerId;
            if ($ownerId === $this->auth->actorId()) $filters['view'] = 'my';
        }
        $tasks = $this->service->listTasks($filters);
        $state = null;
        $columns = $this->service->stageService()->listStages($view === 'archive');
        if ($personal) {
            $state = $this->personal->getForUser($ownerId);
            // Never expose placements of tasks outside the viewer's existing scope.
            $allowed = array_flip(array_map('intval', array_column($tasks, 'rowid')));
            foreach ($state['cards'] as &$ids) $ids = array_values(array_filter($ids, function ($id) use ($allowed) {return isset($allowed[(int)$id]);}));
            unset($ids);
            $tasks = $this->personal->arrange($tasks, $state);
            $columns = $state['columns'];
        }
        if ($view === 'archive' && $tasks) {
            $used = array_flip(array_map('intval', array_column($tasks, 'fk_stage')));
            $columns = array_values(array_filter($columns, function ($stage) use ($used) {return isset($used[(int)$stage['rowid']]);}));
        }
        $listMode = !$personal && in_array($view, array('all','team'), true);
        if ($listMode) $columns = array();
        return array('tasks'=>$tasks,'columns'=>$columns,'state'=>$state,'personal'=>$personal,'owner_id'=>$ownerId,'editable'=>$personal && $ownerId === $this->auth->actorId() && ($this->auth->isAdministrator() || $this->auth->hasRight('task_read_own')),'list'=>$listMode);
    }
}
