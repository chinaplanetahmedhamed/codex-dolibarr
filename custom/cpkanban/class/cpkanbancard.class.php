<?php
/**
 * Class for China Planet Kanban Cards (customer projects/tasks).
 * Explicit SQL CRUD matching the logistics module style.
 *
 * SECURITY: any customer-facing query MUST pass $socid and filter by fk_soc.
 * Never trust an id coming from the browser without checking ownership.
 */
class CpKanbanCard extends CommonObject
{
    public $db;
    public $table_element = 'cpkanban_card';
    public $element = 'cpkanban_card';
    public $picto = 'project';

    public $id;
    public $rowid;
    public $ref;
    public $entity;
    public $fk_soc;
    public $fk_column;
    public $title;
    public $description;
    public $priority;
    public $progress;
    public $date_deadline;
    public $fk_user_assign;
    public $fk_user_creat;
    public $cust_visible;
    public $fk_shipment;
    public $fk_project;
    public $position;
    public $note_public;
    public $note_private;
    public $status;
    public $date_creation;

    public function __construct($db)
    {
        $this->db = $db;
    }

    private function s($v) { return ($v === null || $v === '') ? "NULL" : "'".$this->db->escape($v)."'"; }
    private function i($v) { return ($v === null || $v === '') ? "NULL" : (int) $v; }
    private function d($v) {
        if (empty($v)) return "NULL";
        if (is_numeric($v)) return "'".$this->db->idate($v)."'";
        return "'".$this->db->escape($v)."'";
    }

    /**
     * Create a card
     * @param User $user
     * @param int  $notrigger
     * @return int >0 id if OK, <0 if KO
     */
    public function create($user, $notrigger = 0)
    {
        global $conf;
        $entity = isset($conf->entity) ? (int) $conf->entity : 1;

        if (empty($this->ref)) $this->ref = 'PRJ-'.dol_print_date(dol_now(), '%Y%m%d-%H%M%S');
        if (empty($this->priority)) $this->priority = 'med';
        if (!isset($this->progress)) $this->progress = 0;
        if (!isset($this->cust_visible)) $this->cust_visible = 1;
        if (!isset($this->status)) $this->status = 1;
        if (!isset($this->position)) $this->position = 0;

        $sql = "INSERT INTO ".MAIN_DB_PREFIX."cpkanban_card";
        $sql.= " (ref, entity, fk_soc, fk_column, title, description, priority, progress, date_deadline,";
        $sql.= " fk_user_assign, fk_user_creat, cust_visible, fk_shipment, fk_project, position, note_public, note_private, status, date_creation)";
        $sql.= " VALUES (";
        $sql.= $this->s($this->ref).", ".$entity.", ".$this->i($this->fk_soc).", ".$this->i($this->fk_column).", ";
        $sql.= $this->s($this->title).", ".$this->s($this->description).", ".$this->s($this->priority).", ".(int) $this->progress.", ".$this->d($this->date_deadline).", ";
        $sql.= $this->i($this->fk_user_assign).", ".(int) $user->id.", ".(int) $this->cust_visible.", ".$this->i($this->fk_shipment).", ".$this->i($this->fk_project).", ";
        $sql.= (int) $this->position.", ".$this->s($this->note_public).", ".$this->s($this->note_private).", ".(int) $this->status.", '".$this->db->idate(dol_now())."')";

        $this->db->begin();
        $resql = $this->db->query($sql);
        if (!$resql) { $this->error = $this->db->lasterror(); $this->db->rollback(); return -1; }
        $this->id = $this->db->last_insert_id(MAIN_DB_PREFIX."cpkanban_card");
        $this->rowid = $this->id;

        // log creation
        $this->addLog($user, 'create', null, $this->fk_column, 'Card created');
        $this->db->commit();
        return $this->id;
    }

    /**
     * Fetch a card.
     * @param int $id
     * @param int $socid  If >0, enforce ownership (customer view). 0 = internal/no restriction.
     * @return int 1 if OK, 0 if not found / not owned, <0 if KO
     */
    public function fetch($id, $socid = 0)
    {
        $sql = "SELECT * FROM ".MAIN_DB_PREFIX."cpkanban_card WHERE rowid = ".(int) $id;
        if ($socid > 0) {
            // SECURITY: customer can only fetch their own card, and only if visible
            $sql.= " AND fk_soc = ".(int) $socid." AND cust_visible = 1 AND status <> 0";
        }
        $resql = $this->db->query($sql);
        if (!$resql) { $this->error = $this->db->lasterror(); return -1; }
        $obj = $this->db->fetch_object($resql);
        if (!$obj) return 0;

        $this->id = $obj->rowid; $this->rowid = $obj->rowid;
        $this->ref = $obj->ref;
        $this->entity = $obj->entity;
        $this->fk_soc = $obj->fk_soc;
        $this->fk_column = $obj->fk_column;
        $this->title = $obj->title;
        $this->description = $obj->description;
        $this->priority = $obj->priority;
        $this->progress = $obj->progress;
        $this->date_deadline = $this->db->jdate($obj->date_deadline);
        $this->fk_user_assign = $obj->fk_user_assign;
        $this->fk_user_creat = $obj->fk_user_creat;
        $this->cust_visible = $obj->cust_visible;
        $this->fk_shipment = $obj->fk_shipment;
        $this->fk_project = $obj->fk_project;
        $this->position = $obj->position;
        $this->note_public = $obj->note_public;
        $this->note_private = $obj->note_private;
        $this->status = $obj->status;
        $this->date_creation = $this->db->jdate($obj->date_creation);
        return 1;
    }

    /**
     * Return all cards grouped by column id.
     * @param DoliDB $db
     * @param int    $socid  If >0, only that customer's visible cards (customer view).
     * @param array  $filters  optional: ['fk_user_assign'=>id, 'priority'=>'high', 'search'=>'text']
     * @return array  [ fk_column => array(card rows) ]
     */
    public static function getBoard($db, $socid = 0, $filters = array())
    {
        $byCol = array();
        $sql = "SELECT c.*, s.nom as customer_name, (SELECT a.rowid FROM ".MAIN_DB_PREFIX."cpkanban_asset a WHERE a.entity=c.entity AND a.fk_card=c.rowid AND a.is_cover=1 AND a.status=1";
        if ($socid > 0) $sql.= " AND a.customer_visible=1 AND a.review_status='approved'";
        $sql.= " ORDER BY a.rowid DESC LIMIT 1) as cover_asset_id";
        $sql.= " FROM ".MAIN_DB_PREFIX."cpkanban_card as c";
        $sql.= " LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON c.fk_soc = s.rowid";
        $sql.= " WHERE c.status <> 0";

        if ($socid > 0) {
            // SECURITY: strict isolation for customer portal
            $sql.= " AND c.fk_soc = ".(int) $socid." AND c.cust_visible = 1";
        } else {
            // internal filters (only applied on admin side)
            if (!empty($filters['fk_soc']))         $sql.= " AND c.fk_soc = ".(int) $filters['fk_soc'];
            if (!empty($filters['fk_user_assign'])) $sql.= " AND c.fk_user_assign = ".(int) $filters['fk_user_assign'];
            if (!empty($filters['priority']))       $sql.= " AND c.priority = '".$db->escape($filters['priority'])."'";
            if (!empty($filters['search']))         $sql.= " AND c.title LIKE '%".$db->escape($filters['search'])."%'";
        }
        $sql.= " ORDER BY c.fk_column ASC, c.position ASC, c.rowid DESC";

        $resql = $db->query($sql);
        if ($resql) {
            while ($obj = $db->fetch_object($resql)) {
                $byCol[$obj->fk_column][] = $obj;
            }
        }
        return $byCol;
    }

    /**
     * Update a card (internal/admin).
     * @param User $user
     * @param int  $notrigger
     * @return int 1 if OK, <0 if KO
     */
    public function update($user, $notrigger = 0)
    {
        $sql = "UPDATE ".MAIN_DB_PREFIX."cpkanban_card SET ";
        $sql.= "fk_soc = ".$this->i($this->fk_soc).", ";
        $sql.= "fk_column = ".$this->i($this->fk_column).", ";
        $sql.= "title = ".$this->s($this->title).", ";
        $sql.= "description = ".$this->s($this->description).", ";
        $sql.= "priority = ".$this->s($this->priority).", ";
        $sql.= "progress = ".(int) $this->progress.", ";
        $sql.= "date_deadline = ".$this->d($this->date_deadline).", ";
        $sql.= "fk_user_assign = ".$this->i($this->fk_user_assign).", ";
        $sql.= "cust_visible = ".(int) $this->cust_visible.", ";
        $sql.= "fk_shipment = ".$this->i($this->fk_shipment).", ";
        $sql.= "fk_project = ".$this->i($this->fk_project).", ";
        $sql.= "note_public = ".$this->s($this->note_public).", ";
        $sql.= "note_private = ".$this->s($this->note_private).", ";
        $sql.= "status = ".(int) $this->status." ";
        $sql.= "WHERE rowid = ".(int) $this->id;
        $resql = $this->db->query($sql);
        if (!$resql) { $this->error = $this->db->lasterror(); return -1; }
        return 1;
    }

    /**
     * Move a card to another column (used by drag & drop AJAX).
     * Applies the column's auto_progress if defined, and writes a log line.
     * @param User $user
     * @param int  $newColId
     * @return int 1 if OK, <0 if KO
     */
    public function moveToColumn($user, $newColId)
    {
        $oldCol = (int) $this->fk_column;
        $newColId = (int) $newColId;
        if ($oldCol === $newColId) return 1;

        // fetch auto_progress of the destination column
        $autoProg = null;
        $sql = "SELECT auto_progress FROM ".MAIN_DB_PREFIX."cpkanban_column WHERE rowid = ".$newColId;
        $resql = $this->db->query($sql);
        if ($resql) { $o = $this->db->fetch_object($resql); if ($o && $o->auto_progress !== null) $autoProg = (int) $o->auto_progress; }

        $set = "fk_column = ".$newColId;
        if ($autoProg !== null) $set.= ", progress = ".$autoProg;

        $sql = "UPDATE ".MAIN_DB_PREFIX."cpkanban_card SET ".$set." WHERE rowid = ".(int) $this->id;
        $this->db->begin();
        $resql = $this->db->query($sql);
        if (!$resql) { $this->error = $this->db->lasterror(); $this->db->rollback(); return -1; }

        $this->addLog($user, 'move', $oldCol, $newColId, 'Moved column');
        $this->db->commit();

        $this->fk_column = $newColId;
        if ($autoProg !== null) $this->progress = $autoProg;
        return 1;
    }

    /**
     * Soft-delete: mark status = 0 (cancelled). Keeps history.
     * @param User $user
     * @param int  $notrigger
     * @return int 1 if OK, <0 if KO
     */
    public function delete($user, $notrigger = 0)
    {
        $sql = "UPDATE ".MAIN_DB_PREFIX."cpkanban_card SET status = 0 WHERE rowid = ".(int) $this->id;
        $resql = $this->db->query($sql);
        if (!$resql) { $this->error = $this->db->lasterror(); return -1; }
        return 1;
    }

    /**
     * Append an activity log row.
     * @return int 1 if OK, <0 if KO
     */
    public function addLog($user, $type, $oldCol, $newCol, $note = '', $visibleCust = 1)
    {
        global $conf;
        $entity = isset($conf->entity) ? (int) $conf->entity : 1;
        $sql = "INSERT INTO ".MAIN_DB_PREFIX."cpkanban_log (entity, fk_card, fk_column_old, fk_column_new, action_type, note, fk_user, visible_cust, date_action)";
        $sql.= " VALUES (".$entity.", ".(int) $this->id.", ".($oldCol === null ? "NULL" : (int) $oldCol).", ".($newCol === null ? "NULL" : (int) $newCol).", ";
        $sql.= "'".$this->db->escape($type)."', ".($note === '' ? "NULL" : "'".$this->db->escape($note)."'").", ".(int) $user->id.", ".(int) $visibleCust.", '".$this->db->idate(dol_now())."')";
        $resql = $this->db->query($sql);
        if (!$resql) { $this->error = $this->db->lasterror(); return -1; }
        return 1;
    }

    /**
     * Return activity log rows for this card.
     * @param int $visibleCustOnly  if 1, only customer-visible log lines
     * @return array
     */
    public function getLogs($visibleCustOnly = 0)
    {
        $out = array();
        $sql = $visibleCustOnly
            ? "SELECT l.* FROM ".MAIN_DB_PREFIX."cpkanban_log as l"
            : "SELECT l.*, u.firstname, u.lastname, u.login, u.email FROM ".MAIN_DB_PREFIX."cpkanban_log as l LEFT JOIN ".MAIN_DB_PREFIX."user as u ON l.fk_user = u.rowid";
        $sql.= " WHERE l.fk_card = ".(int) $this->id;
        if ($visibleCustOnly) $sql.= " AND l.visible_cust = 1";
        $sql.= " ORDER BY l.date_action DESC";
        $resql = $this->db->query($sql);
        if ($resql) {
            while ($o = $this->db->fetch_object($resql)) {
                if ($visibleCustOnly) {
                    unset($o->fk_user);
                    $o->public_actor = 'فريق كوكب الصين';
                }
                $out[] = $o;
            }
        }
        return $out;
    }

    /**
     * Fetch the linked shipment summary (Phase 2F), enforcing the same customer ownership.
     * @param int $socid  customer id for the ownership double-check (0 = internal)
     * @return object|null  shipment row or null
     */
    public function getShipmentSummary($socid = 0)
    {
        if (empty($this->fk_shipment)) return null;
        $sql = "SELECT rowid, ref, container_no, bl_no, shipping_line, vessel_name, port_loading, port_destination,";
        $sql.= " date_eta, freetime_end, fk_statut FROM ".MAIN_DB_PREFIX."chinaplanetlogistics";
        $sql.= " WHERE rowid = ".(int) $this->fk_shipment;
        if ($socid > 0) $sql.= " AND fk_soc = ".(int) $socid; // double isolation
        $resql = $this->db->query($sql);
        if (!$resql) return null;
        return $this->db->fetch_object($resql);
    }
}
