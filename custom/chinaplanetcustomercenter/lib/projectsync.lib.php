<?php

if (!function_exists('cpcc_project_status_label')) {
    function cpcc_project_status_label($status)
    {
        $status = (int) $status;
        if ($status === 0) return 'Draft | مسودة';
        if ($status === 1) return 'Validated | معتمد';
        if ($status === 2) return 'Closed | مغلق';
        return 'Unknown | غير معروف';
    }
}

if (!function_exists('cpcc_project_status_to_column_code')) {
    function cpcc_project_status_to_column_code($status, $mode = 'modify', $existingCode = '')
    {
        $mode = (string) $mode;
        $status = (int) $status;

        if ($mode === 'delete') return 'hold';
        if ($mode === 'close' || $status === 2) return 'done';

        if ($mode === 'validate') {
            if (in_array($existingCode, array('new', 'review', 'wait', ''), true)) return 'prog';
            return $existingCode;
        }

        if ($mode === 'create') {
            if ($status === 2) return 'done';
            if ($status === 1) return 'prog';
            return 'new';
        }

        if ($existingCode !== '') return $existingCode;
        if ($status === 1) return 'prog';
        if ($status === 2) return 'done';
        return 'new';
    }
}

if (!function_exists('cpcc_project_fetch_kanban_card_row')) {
    function cpcc_project_fetch_kanban_card_row($db, $projectId)
    {
        $projectId = (int) $projectId;
        if ($projectId <= 0) return null;

        $sql = "SELECT c.rowid, c.fk_column, c.status, c.progress, c.cust_visible, col.code as column_code";
        $sql.= " FROM ".MAIN_DB_PREFIX."cpkanban_card as c";
        $sql.= " LEFT JOIN ".MAIN_DB_PREFIX."cpkanban_column as col ON col.rowid = c.fk_column";
        $sql.= " WHERE c.fk_project = ".$projectId." ORDER BY c.rowid ASC LIMIT 1";
        $resql = $db->query($sql);
        if (!$resql) return null;
        $obj = $db->fetch_object($resql);
        if (!$obj) return null;
        return $obj;
    }
}

if (!function_exists('cpcc_project_default_column_id')) {
    function cpcc_project_default_column_id($db, $code = 'new')
    {
        $code = $db->escape($code);
        $sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."cpkanban_column";
        $sql.= " WHERE active = 1 AND code = '".$code."'";
        $sql.= " ORDER BY position ASC, rowid ASC LIMIT 1";
        $resql = $db->query($sql);
        if ($resql && ($obj = $db->fetch_object($resql))) {
            return (int) $obj->rowid;
        }

        $sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."cpkanban_column WHERE active = 1 ORDER BY position ASC, rowid ASC LIMIT 1";
        $resql = $db->query($sql);
        if ($resql && ($obj = $db->fetch_object($resql))) {
            return (int) $obj->rowid;
        }

        return 0;
    }
}

if (!function_exists('cpcc_project_sync_kanban_card')) {
    function cpcc_project_sync_kanban_card($db, $project, $user, $mode = 'modify')
    {
        global $conf;

        if (!class_exists('CpKanbanCard')) {
            require_once DOL_DOCUMENT_ROOT.'/custom/cpkanban/class/cpkanbancard.class.php';
        }
        if (!class_exists('CpKanbanColumn')) {
            require_once DOL_DOCUMENT_ROOT.'/custom/cpkanban/class/cpkanbancolumn.class.php';
        }

        $mode = (string) $mode;
        $projectId = (int) (!empty($project->id) ? $project->id : (!empty($project->rowid) ? $project->rowid : 0));
        $socid = (int) (!empty($project->socid) ? $project->socid : (!empty($project->fk_soc) ? $project->fk_soc : 0));
        $ref = !empty($project->ref) ? trim((string) $project->ref) : ('PRJ-'.$projectId);
        $title = !empty($project->title) ? trim((string) $project->title) : $ref;
        $description = !empty($project->description) ? trim((string) $project->description) : '';
        $status = (int) (!empty($project->status) ? $project->status : (!empty($project->statut) ? $project->statut : 0));
        $dateEnd = !empty($project->date_end) ? $project->date_end : (!empty($project->datee) ? $project->datee : null);

        $result = array(
            'mode' => $mode,
            'project_id' => $projectId,
            'card_id' => 0,
            'action' => 'skipped',
            'message' => '',
        );

        if ($projectId <= 0) {
            $result['message'] = 'Missing project id';
            return $result;
        }

        $existing = cpcc_project_fetch_kanban_card_row($db, $projectId);
        if ($socid <= 0 && $mode !== 'delete' && empty($existing)) {
            $result['message'] = 'Project has no linked customer';
            return $result;
        }
        $existingCode = !empty($existing->column_code) ? (string) $existing->column_code : '';
        $targetCode = ($socid <= 0) ? 'hold' : cpcc_project_status_to_column_code($status, $mode, $existingCode);
        $targetColumnId = cpcc_project_default_column_id($db, $targetCode);
        if ($targetColumnId <= 0) {
            $targetColumnId = cpcc_project_default_column_id($db, 'new');
        }

        $card = new CpKanbanCard($db);
        if ($existing && !empty($existing->rowid)) {
            $card->fetch((int) $existing->rowid, 0);
            $card->id = (int) $existing->rowid;
            $card->rowid = (int) $existing->rowid;
        }

        $card->fk_soc = $socid;
        $card->fk_project = $projectId;
        $card->title = $ref.' - '.$title;
        $card->description = $description;
        $card->priority = 'med';
        $card->date_deadline = $dateEnd;
        $card->cust_visible = ($socid > 0 ? 1 : 0);
        $card->fk_user_assign = !empty($card->fk_user_assign) ? $card->fk_user_assign : null;
        $card->note_public = 'Auto-synced from Vision System project '.$ref;
        $card->note_private = '';

        if ($mode === 'delete' || $socid <= 0) {
            $card->status = 0;
            if ($targetColumnId > 0) $card->fk_column = $targetColumnId;
            if (!empty($existing->progress)) {
                $card->progress = (int) $existing->progress;
            } else {
                $card->progress = 0;
            }
            $card->cust_visible = 0;
        } else {
            $card->status = 1;
            if ($existing && !empty($existing->rowid)) {
                $card->fk_column = (int) $existing->fk_column;
                if ($card->fk_column <= 0 && $targetColumnId > 0) {
                    $card->fk_column = $targetColumnId;
                }
                if ($mode === 'validate') {
                    $card->fk_column = $targetColumnId > 0 ? $targetColumnId : (int) $existing->fk_column;
                } elseif ($mode === 'close') {
                    $card->fk_column = $targetColumnId > 0 ? $targetColumnId : (int) $existing->fk_column;
                } elseif ($mode === 'create' && empty($existing->fk_column) && $targetColumnId > 0) {
                    $card->fk_column = $targetColumnId;
                }
                if ($mode === 'close') {
                    $card->progress = 100;
                } elseif ($mode === 'validate') {
                    $card->progress = empty($existing->progress) ? 55 : (int) $existing->progress;
                } elseif (empty($existing->progress)) {
                    $card->progress = $status === 2 ? 100 : ($status === 1 ? 55 : 5);
                } else {
                    $card->progress = (int) $existing->progress;
                }
            } else {
                $card->fk_column = $targetColumnId;
                $card->progress = $status === 2 ? 100 : ($status === 1 ? 55 : 5);
            }
        }

        if ($card->fk_column <= 0) {
            $result['message'] = 'No active Kanban column available';
            return $result;
        }

        if ($existing && !empty($existing->rowid)) {
            $res = $card->update($user);
            if ($res < 0) {
                $result['message'] = $card->error;
                return $result;
            }
            $result['card_id'] = (int) $existing->rowid;
            $result['action'] = 'updated';
            return $result;
        }

        $res = $card->create($user);
        if ($res < 0) {
            $result['message'] = $card->error;
            return $result;
        }
        $result['card_id'] = (int) $card->id;
        $result['action'] = 'created';
        return $result;
    }
}

if (!function_exists('cpcc_project_sync_existing_cards')) {
    function cpcc_project_sync_existing_cards($db, $user, $socid = 0)
    {
        require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';

        $socid = (int) $socid;
        $sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."projet WHERE entity IN (".getEntity('project').")";
        if ($socid > 0) {
            $sql .= " AND fk_soc = ".$socid;
        } else {
            $sql .= " AND fk_soc > 0";
        }
        $sql .= " ORDER BY rowid ASC";

        $summary = array(
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => 0,
            'details' => array(),
        );

        $resql = $db->query($sql);
        if (!$resql) {
            $summary['errors']++;
            $summary['details'][] = $db->lasterror();
            return $summary;
        }

        while ($obj = $db->fetch_object($resql)) {
            $project = new Project($db);
            if ($project->fetch((int) $obj->rowid) <= 0) {
                $summary['errors']++;
                $summary['details'][] = 'Unable to fetch project '.$obj->rowid;
                continue;
            }

            $sync = cpcc_project_sync_kanban_card($db, $project, $user, 'modify');
            if ($sync['action'] === 'created') {
                $summary['created']++;
            } elseif ($sync['action'] === 'updated') {
                $summary['updated']++;
            } else {
                $summary['skipped']++;
            }
            if (!empty($sync['message'])) {
                $summary['details'][] = 'Project '.$project->ref.': '.$sync['message'];
            }
        }

        return $summary;
    }
}
