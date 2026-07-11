<?php
/**
 * Class for China Planet Kanban Columns.
 * Explicit SQL CRUD, matching the style of the logistics module.
 */
class CpKanbanColumn extends CommonObject
{
    public $db;
    public $table_element = 'cpkanban_column';
    public $element = 'cpkanban_column';
    public $picto = 'generic';

    public $id;
    public $rowid;
    public $entity;
    public $code;
    public $label;
    public $label_en;
    public $color;
    public $position;
    public $cust_visible;
    public $auto_progress;
    public $active;

    public function __construct($db)
    {
        $this->db = $db;
    }

    private function s($v) { return ($v === null || $v === '') ? "NULL" : "'".$this->db->escape($v)."'"; }
    private function i($v) { return ($v === null || $v === '') ? "NULL" : (int) $v; }

    /**
     * Create a column
     * @param User $user
     * @return int >0 id if OK, <0 if KO
     */
    public function create($user)
    {
        global $conf;
        $entity = isset($conf->entity) ? (int) $conf->entity : 1;

        if (empty($this->code)) $this->code = 'col_'.dol_print_date(dol_now(), '%Y%m%d%H%M%S');
        if (!isset($this->cust_visible)) $this->cust_visible = 1;
        if (!isset($this->active)) $this->active = 1;
        if (empty($this->color)) $this->color = '#2980b9';
        if (!isset($this->position)) $this->position = 0;

        $sql = "INSERT INTO ".MAIN_DB_PREFIX."cpkanban_column";
        $sql.= " (entity, code, label, label_en, color, position, cust_visible, auto_progress, active)";
        $sql.= " VALUES (".$entity.", ".$this->s($this->code).", ".$this->s($this->label).", ".$this->s($this->label_en).", ";
        $sql.= $this->s($this->color).", ".(int) $this->position.", ".(int) $this->cust_visible.", ".$this->i($this->auto_progress).", ".(int) $this->active.")";

        $resql = $this->db->query($sql);
        if (!$resql) { $this->error = $this->db->lasterror(); return -1; }
        $this->id = $this->db->last_insert_id(MAIN_DB_PREFIX."cpkanban_column");
        return $this->id;
    }

    /**
     * Fetch one column
     * @param int $id
     * @return int 1 if OK, 0 if not found, <0 if KO
     */
    public function fetch($id)
    {
        $sql = "SELECT rowid, entity, code, label, label_en, color, position, cust_visible, auto_progress, active";
        $sql.= " FROM ".MAIN_DB_PREFIX."cpkanban_column WHERE rowid = ".(int) $id;
        $resql = $this->db->query($sql);
        if (!$resql) { $this->error = $this->db->lasterror(); return -1; }
        $obj = $this->db->fetch_object($resql);
        if (!$obj) return 0;
        $this->id = $obj->rowid; $this->rowid = $obj->rowid;
        $this->entity = $obj->entity;
        $this->code = $obj->code;
        $this->label = $obj->label;
        $this->label_en = $obj->label_en;
        $this->color = $obj->color;
        $this->position = $obj->position;
        $this->cust_visible = $obj->cust_visible;
        $this->auto_progress = $obj->auto_progress;
        $this->active = $obj->active;
        return 1;
    }

    /**
     * Return array of columns ordered by position.
     * @param DoliDB $db
     * @param bool   $only_customer_visible  if true, only columns with cust_visible=1
     * @param bool   $only_active            if true, only active columns
     * @return array of stdClass rows
     */
    public static function getAll($db, $only_customer_visible = false, $only_active = true)
    {
        global $conf;
        $entity = isset($conf->entity) ? (int) $conf->entity : 1;
        $out = array();
        $sql = "SELECT rowid, code, label, label_en, color, position, cust_visible, auto_progress, active";
        $sql.= " FROM ".MAIN_DB_PREFIX."cpkanban_column WHERE entity = ".$entity;
        if ($only_active) $sql.= " AND active = 1";
        if ($only_customer_visible) $sql.= " AND cust_visible = 1";
        $sql.= " ORDER BY position ASC, rowid ASC";
        $resql = $db->query($sql);
        if ($resql) {
            while ($obj = $db->fetch_object($resql)) { $out[$obj->rowid] = $obj; }
        }
        return $out;
    }

    /**
     * Update a column
     * @param User $user
     * @return int 1 if OK, <0 if KO
     */
    public function update($user)
    {
        $sql = "UPDATE ".MAIN_DB_PREFIX."cpkanban_column SET ";
        $sql.= "label = ".$this->s($this->label).", ";
        $sql.= "label_en = ".$this->s($this->label_en).", ";
        $sql.= "color = ".$this->s($this->color).", ";
        $sql.= "position = ".(int) $this->position.", ";
        $sql.= "cust_visible = ".(int) $this->cust_visible.", ";
        $sql.= "auto_progress = ".$this->i($this->auto_progress).", ";
        $sql.= "active = ".(int) $this->active." ";
        $sql.= "WHERE rowid = ".(int) $this->id;
        $resql = $this->db->query($sql);
        if (!$resql) { $this->error = $this->db->lasterror(); return -1; }
        return 1;
    }

    /**
     * Delete a column. Refuses if cards still reference it.
     * @param User $user
     * @return int 1 if OK, 0 if blocked (cards exist), <0 if KO
     */
    public function delete($user)
    {
        $sql = "SELECT COUNT(*) as nb FROM ".MAIN_DB_PREFIX."cpkanban_card WHERE fk_column = ".(int) $this->id;
        $resql = $this->db->query($sql);
        if ($resql) {
            $obj = $this->db->fetch_object($resql);
            if ($obj && (int) $obj->nb > 0) {
                $this->error = 'ColumnNotEmpty';
                return 0; // do not delete a column that still holds cards
            }
        }
        $sql = "DELETE FROM ".MAIN_DB_PREFIX."cpkanban_column WHERE rowid = ".(int) $this->id;
        $resql = $this->db->query($sql);
        if (!$resql) { $this->error = $this->db->lasterror(); return -1; }
        return 1;
    }
}
