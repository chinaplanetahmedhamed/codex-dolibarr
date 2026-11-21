<?php

/** Publishes a separate customer title for an existing task placement. */
class ProjectBoardPublication
{
    private $db;
    private $actor;
    private $entity;

    public function __construct($db, $actor, $entity)
    {
        $this->db = $db;
        $this->actor = $actor;
        $this->entity = (int) $entity;
    }

    public static function available($db)
    {
        $table = MAIN_DB_PREFIX.'cpo_board_customer_card';
        $result = $db->query("SHOW TABLES LIKE '".$db->escape($table)."'");
        while ($result && ($row = $db->fetch_object($result))) {
            if (in_array($table, (array) $row, true)) return true;
        }
        return false;
    }

    private function query($sql)
    {
        $result = $this->db->query($sql);
        if (!$result) throw new RuntimeException('Board publication storage is unavailable.');
        return $result;
    }

    private function enabled()
    {
        require_once __DIR__.'/InternalTaskBoardsService.class.php';
        return InternalTaskBoardPolicy::enabled() && InternalTaskWorkspaceService::enabled() && self::available($this->db);
    }

    private function rows($scope, $lock)
    {
        $p = MAIN_DB_PREFIX;
        $sql = "SELECT t.rowid task_id, t.title internal_title, l.title list_title, c.fk_board, pr.rowid fk_project, pr.fk_soc, pub.fk_board saved_board, pub.fk_project saved_project,"
            ." pub.fk_soc saved_customer, pub.public_title, o.title operation_title, COALESCE(pub.version,0) version"
            ." FROM {$p}cpo_board_card c"
            ." JOIN {$p}cpo_board b ON b.entity=c.entity AND b.rowid=c.fk_board AND b.archived=0"
            ." JOIN {$p}cpo_project_board m ON m.entity=c.entity AND m.fk_board=c.fk_board"
            ." JOIN {$p}projet pr ON pr.entity=m.entity AND pr.rowid=m.fk_project AND pr.fk_soc>0"
            ." JOIN {$p}cpo_board_list l ON l.entity=c.entity AND l.rowid=c.fk_list AND l.fk_board=c.fk_board AND l.archived=0"
            ." JOIN {$p}cpo_task t ON t.entity=c.entity AND t.rowid=c.fk_task AND t.date_archived IS NULL"
            ." LEFT JOIN {$p}cpo_operation o ON o.entity=t.entity AND o.rowid=t.fk_operation"
            ." AND o.fk_project=pr.rowid AND o.fk_soc=pr.fk_soc AND o.status=1 AND o.customer_visible=1"
            ." LEFT JOIN {$p}cpo_board_customer_card pub ON pub.entity=c.entity AND pub.fk_task=c.fk_task"
            ." WHERE c.entity=".$this->entity." AND ".$scope
            ." AND c.archived=0 AND t.status IN ('todo','in_progress','waiting','review','done')"
            ." ORDER BY l.position,l.rowid,c.position,c.rowid";
        $result = $this->query($sql.($lock ? ' FOR UPDATE' : ''));
        $rows = [];
        while ($row = $this->db->fetch_object($result)) {
            if ((int) $row->version === 0) {
                $row->public_title = $row->operation_title;
            } elseif ((int) $row->saved_board !== (int) $row->fk_board || (int) $row->saved_project !== (int) $row->fk_project
                || (int) $row->saved_customer !== (int) $row->fk_soc) {
                $row->public_title = '';
            }
            $rows[(int) $row->task_id] = $row;
        }
        return $rows;
    }

    public function state($taskId, $lock = false)
    {
        if (!$this->enabled()) return null;
        $taskId = (int) $taskId;
        $row = $this->rows('c.fk_task='.$taskId, $lock)[$taskId] ?? null;
        if (!$row) return null;

        (new InternalTaskService($this->db, $this->actor, $this->entity))->requireReadableTask($taskId);
        (new InternalTaskBoardsService($this->db, $this->actor, $this->entity))->board((int) $row->fk_board, 'manager');
        return $row;
    }

    public function board($boardId, $lock = false)
    {
        if (!$this->enabled()) throw new InternalTaskAccessException('Board publication is unavailable.');
        $board = (new InternalTaskBoardsService($this->db, $this->actor, $this->entity))->board((int) $boardId, 'manager', $lock);
        if (!empty($board['archived']) || empty($board['workspace_project']['fk_soc'])) {
            throw new InternalTaskAccessException('Only a customer project board can be published.');
        }
        return ['board' => $board, 'cards' => $this->rows('c.fk_board='.(int) $boardId, $lock)];
    }

    public function saveBatch($boardId, $projectId, $customerId, array $changes)
    {
        if (!$this->db->begin()) throw new RuntimeException('Unable to start board publication.');
        try {
            $scope = $this->board($boardId, true);
            $project = $scope['board']['workspace_project'];
            if ((int) $project['rowid'] !== (int) $projectId || (int) $project['fk_soc'] !== (int) $customerId) {
                throw new InternalTaskConflictException('Board publication context changed.');
            }
            $seen = [];
            foreach ($changes as $change) {
                if (!is_array($change) || !is_int($change['task'] ?? null) || !is_int($change['version'] ?? null)
                    || !is_bool($change['published'] ?? null) || !is_string($change['title'] ?? null)
                    || isset($seen[$change['task']])) {
                    throw new InvalidArgumentException('Invalid board publication selection.');
                }
                $taskId = $change['task'];
                $seen[$taskId] = true;
                $row = $scope['cards'][$taskId] ?? null;
                if (!$row || (int) $row->version !== $change['version']
                    || (int) $row->fk_project !== (int) $projectId || (int) $row->fk_soc !== (int) $customerId) {
                    throw new InternalTaskConflictException('Card publication changed.');
                }
                $title = $this->validateTitle($change['title'], $change['published']);
                if ((string) $row->public_title !== $title) {
                    $this->record($row, $title);
                }
            }
            if (!$this->db->commit()) throw new RuntimeException('Unable to save board publication.');
        } catch (Throwable $error) {
            $this->db->rollback();
            throw $error;
        }
    }

    private function validateTitle($title, $publish)
    {
        $title = $publish ? trim($title) : '';
        if ($publish && ($title === '' || !mb_check_encoding($title, 'UTF-8') || mb_strlen($title) > 180
            || preg_match('/[<>\x00-\x1f]/u', $title))) {
            throw new InvalidArgumentException('Enter a valid customer-facing title.');
        }
        return $title;
    }

    public function save($taskId, $expectedVersion, $board, $project, $customer, $title, $publish)
    {
        $title = $this->validateTitle($title, $publish);
        if (!$this->db->begin()) throw new RuntimeException('Unable to start board publication.');
        try {
            $row = $this->state($taskId, true);
            if (!$row) throw new InternalTaskAccessException('Card is not available for publication.');
            if ((int) $row->version !== (int) $expectedVersion || (int) $row->fk_board !== (int) $board
                || (int) $row->fk_project !== (int) $project || (int) $row->fk_soc !== (int) $customer) {
                throw new InternalTaskConflictException('Card publication changed.');
            }
            $this->record($row, $title);
            if (!$this->db->commit()) throw new RuntimeException('Unable to save board publication.');
        } catch (Throwable $error) {
            $this->db->rollback();
            throw $error;
        }
    }

    private function record($row, $title)
    {
        $actor = (int) $this->actor->id;
        $time = "'".$this->db->escape(date('Y-m-d H:i:s'))."'";
        $value = "'".$this->db->escape($title)."'";
        $table = MAIN_DB_PREFIX.'cpo_board_customer_card';
        $taskId = (int) $row->task_id;
        if ((int) $row->version === 0) {
            $this->query("INSERT INTO $table (entity,fk_task,fk_board,fk_project,fk_soc,public_title,fk_user_modif,date_modified)"
                ." VALUES (".$this->entity.",".$taskId.",".(int) $row->fk_board.",".(int) $row->fk_project.",".(int) $row->fk_soc.",$value,$actor,$time)");
        } else {
            $this->query("UPDATE $table SET fk_board=".(int) $row->fk_board.",fk_project=".(int) $row->fk_project.",fk_soc=".(int) $row->fk_soc
                .",public_title=$value,version=version+1,fk_user_modif=$actor,date_modified=$time"
                ." WHERE entity=".$this->entity." AND fk_task=".$taskId." AND version=".(int) $row->version);
        }
        $action = $title !== '' ? 'customer_card_published' : 'customer_card_unpublished';
        $this->query('INSERT INTO '.MAIN_DB_PREFIX."cpo_activity (entity,fk_task,fk_user,action_code,message,date_action)"
            ." VALUES (".$this->entity.",".$taskId.",$actor,'$action','',$time)");
    }
}
