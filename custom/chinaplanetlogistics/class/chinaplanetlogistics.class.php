<?php
/**
 * Class for China Planet Logistics shipment records.
 * FIXED: Stable CRUD helpers (sqlDate/sqlStr/sqlInt) + new fields (fk_project, customer_note, booking_no, voyage_no, nb_containers, freetime_start)
 */
class ChinaPlanetLogistics extends CommonObject
{
    public $db;
    public $table_element = 'chinaplanetlogistics';
    public $element       = 'chinaplanetlogistics';
    public $picto         = 'generic';

    public $id;
    public $rowid;
    public $ref;
    public $entity;
    public $fk_soc;
    public $fk_project;
    public $fk_commande;
    public $fk_user_creat;
    public $fk_user_assign;
    public $container_no;
    public $container_type;
    public $nb_containers;
    public $booking_no;
    public $bl_no;
    public $shipping_line;
    public $vessel_name;
    public $voyage_no;
    public $port_loading;
    public $port_destination;
    public $date_etd;
    public $date_eta;
    public $date_ata;
    public $freetime_days;
    public $freetime_start;
    public $freetime_end;
    public $fk_statut;
    public $notes;
    public $customer_note;
    public $date_creation;

    public function __construct($db)
    {
        $this->db = $db;
    }

    // ── SQL helpers (stable pattern) ──────────────────────────────────────
    private function sqlDate($val)
    {
        if (empty($val)) return "NULL";
        if (is_numeric($val)) return "'".$this->db->idate($val)."'";
        return "'".$this->db->escape($val)."'";
    }

    private function sqlStr($val)
    {
        if ($val === null || $val === '') return "NULL";
        return "'".$this->db->escape($val)."'";
    }

    private function sqlInt($val)
    {
        if ($val === null || $val === '') return "NULL";
        return (int) $val;
    }

    private function activeEntity()
    {
        global $conf;
        return !empty($conf->entity) ? (int) $conf->entity : 1;
    }

    // ── CREATE ────────────────────────────────────────────────────────────
    public function create($user, $notrigger = 0)
    {
        global $conf;

        if (empty($this->ref) || $this->ref == 'AUTO') {
            $this->ref = 'LOG-'.date('Ymd-His');
        }
        $this->calculateFreeTimeDates();
        $entity = $this->activeEntity();

        $sql  = "INSERT INTO ".MAIN_DB_PREFIX."chinaplanetlogistics (";
        $sql .= "ref, entity, fk_soc, fk_project, fk_user_creat,";
        $sql .= "container_no, container_type, nb_containers, booking_no, bl_no,";
        $sql .= "shipping_line, vessel_name, voyage_no, port_loading, port_destination,";
        $sql .= "date_etd, date_eta, date_ata,";
        $sql .= "freetime_days, freetime_start, freetime_end,";
        $sql .= "fk_statut, notes, customer_note, date_creation";
        $sql .= ") VALUES (";
        $sql .= $this->sqlStr($this->ref).", ".$entity.", ".$this->sqlInt($this->fk_soc).", ".$this->sqlInt($this->fk_project).", ".(int)$user->id.", ";
        $sql .= $this->sqlStr($this->container_no).", ".$this->sqlStr($this->container_type).", ".$this->sqlInt($this->nb_containers).", ";
        $sql .= $this->sqlStr($this->booking_no).", ".$this->sqlStr($this->bl_no).", ";
        $sql .= $this->sqlStr($this->shipping_line).", ".$this->sqlStr($this->vessel_name).", ".$this->sqlStr($this->voyage_no).", ";
        $sql .= $this->sqlStr($this->port_loading).", ".$this->sqlStr($this->port_destination).", ";
        $sql .= $this->sqlDate($this->date_etd).", ".$this->sqlDate($this->date_eta).", ".$this->sqlDate($this->date_ata).", ";
        $sql .= $this->sqlInt($this->freetime_days).", ".$this->sqlDate($this->freetime_start).", ".$this->sqlDate($this->freetime_end).", ";
        $sql .= $this->sqlInt($this->fk_statut).", ".$this->sqlStr($this->notes).", ".$this->sqlStr($this->customer_note).", ";
        $sql .= "'".$this->db->idate(dol_now())."'";
        $sql .= ")";

        $this->db->begin();
        $resql = $this->db->query($sql);
        if (!$resql) {
            $this->error = $this->db->lasterror();
            $this->db->rollback();
            return -1;
        }
        $this->id    = $this->db->last_insert_id(MAIN_DB_PREFIX."chinaplanetlogistics");
        $this->rowid = $this->id;
        $this->db->commit();
        return $this->id;
    }

    // ── FETCH ─────────────────────────────────────────────────────────────
    public function fetch($id, $ref = '')
    {
        $sql = "SELECT * FROM ".MAIN_DB_PREFIX."chinaplanetlogistics WHERE entity=".$this->activeEntity()." AND ";
        if ($ref) $sql .= "ref = '".$this->db->escape($ref)."'";
        else      $sql .= "rowid = ".(int)$id;

        $resql = $this->db->query($sql);
        if (!$resql) {
            $this->error = $this->db->lasterror();
            return -1;
        }
        $obj = $this->db->fetch_object($resql);
        if (!$obj) return 0;

        $this->id             = $obj->rowid;
        $this->rowid          = $obj->rowid;
        $this->ref            = $obj->ref;
        $this->entity         = $obj->entity;
        $this->fk_soc         = $obj->fk_soc;
        $this->fk_project     = $obj->fk_project;
        $this->fk_commande    = $obj->fk_commande;
        $this->fk_user_creat  = $obj->fk_user_creat;
        $this->fk_user_assign = $obj->fk_user_assign;
        $this->container_no   = $obj->container_no;
        $this->container_type = $obj->container_type;
        $this->nb_containers  = $obj->nb_containers;
        $this->booking_no     = $obj->booking_no;
        $this->bl_no          = $obj->bl_no;
        $this->shipping_line  = $obj->shipping_line;
        $this->vessel_name    = $obj->vessel_name;
        $this->voyage_no      = $obj->voyage_no;
        $this->port_loading   = $obj->port_loading;
        $this->port_destination = $obj->port_destination;
        $this->date_etd       = $this->db->jdate($obj->date_etd);
        $this->date_eta       = $this->db->jdate($obj->date_eta);
        $this->date_ata       = $this->db->jdate($obj->date_ata);
        $this->freetime_days  = $obj->freetime_days;
        $this->freetime_start = $this->db->jdate($obj->freetime_start);
        $this->freetime_end   = $this->db->jdate($obj->freetime_end);
        $this->fk_statut      = $obj->fk_statut;
        $this->notes          = $obj->notes;
        $this->customer_note  = $obj->customer_note;
        $this->date_creation  = $this->db->jdate($obj->date_creation);
        return 1;
    }

    // ── UPDATE ────────────────────────────────────────────────────────────
    public function update($user, $notrigger = 0)
    {
        $this->calculateFreeTimeDates();

        $sql  = "UPDATE ".MAIN_DB_PREFIX."chinaplanetlogistics SET ";
        $sql .= "fk_soc = ".$this->sqlInt($this->fk_soc).", ";
        $sql .= "fk_project = ".$this->sqlInt($this->fk_project).", ";
        $sql .= "container_no = ".$this->sqlStr($this->container_no).", ";
        $sql .= "container_type = ".$this->sqlStr($this->container_type).", ";
        $sql .= "nb_containers = ".$this->sqlInt($this->nb_containers).", ";
        $sql .= "booking_no = ".$this->sqlStr($this->booking_no).", ";
        $sql .= "bl_no = ".$this->sqlStr($this->bl_no).", ";
        $sql .= "shipping_line = ".$this->sqlStr($this->shipping_line).", ";
        $sql .= "vessel_name = ".$this->sqlStr($this->vessel_name).", ";
        $sql .= "voyage_no = ".$this->sqlStr($this->voyage_no).", ";
        $sql .= "port_loading = ".$this->sqlStr($this->port_loading).", ";
        $sql .= "port_destination = ".$this->sqlStr($this->port_destination).", ";
        $sql .= "date_etd = ".$this->sqlDate($this->date_etd).", ";
        $sql .= "date_eta = ".$this->sqlDate($this->date_eta).", ";
        $sql .= "date_ata = ".$this->sqlDate($this->date_ata).", ";
        $sql .= "freetime_days = ".$this->sqlInt($this->freetime_days).", ";
        $sql .= "freetime_start = ".$this->sqlDate($this->freetime_start).", ";
        $sql .= "freetime_end = ".$this->sqlDate($this->freetime_end).", ";
        $sql .= "fk_statut = ".$this->sqlInt($this->fk_statut).", ";
        $sql .= "notes = ".$this->sqlStr($this->notes).", ";
        $sql .= "customer_note = ".$this->sqlStr($this->customer_note)." ";
        $sql .= "WHERE rowid = ".(int)$this->id." AND entity = ".$this->activeEntity();

        $resql = $this->db->query($sql);
        if (!$resql) {
            $this->error = $this->db->lasterror();
            return -1;
        }
        return 1;
    }

    // ── DELETE ────────────────────────────────────────────────────────────
    public function delete($user, $notrigger = 0)
    {
        $sql = "DELETE FROM ".MAIN_DB_PREFIX."chinaplanetlogistics WHERE rowid = ".(int)$this->id." AND entity = ".$this->activeEntity();
        $resql = $this->db->query($sql);
        if (!$resql) {
            $this->error = $this->db->lasterror();
            return -1;
        }
        return 1;
    }

    // ── HELPERS ───────────────────────────────────────────────────────────
    /**
     * Calculate the free-time window from actual arrival.
     *
     * Business rule: start = ATA, end = ATA + configured calendar days.
     * ETA is intentionally not used because free time must not begin before
     * the shipment has actually arrived.
     *
     * @return bool True when both automatic dates were calculated
     */
    public function calculateFreeTimeDates()
    {
        $days = (int) $this->freetime_days;
        if (empty($this->date_ata) || $days <= 0) {
            return false;
        }

        $start = is_numeric($this->date_ata) ? (int) $this->date_ata : strtotime($this->date_ata);
        if ($start <= 0) {
            return false;
        }

        $end = strtotime('+'.$days.' days', $start);
        if ($end === false) {
            return false;
        }

        $this->freetime_start = $start;
        $this->freetime_end = $end;
        return true;
    }

    public function getDaysUntilETA()
    {
        if (empty($this->date_eta)) return null;
        $eta = is_numeric($this->date_eta) ? $this->date_eta : strtotime($this->date_eta);
        return ceil(($eta - time()) / 86400);
    }

    public function getRemainingFreeDays()
    {
        if (empty($this->freetime_end)) return null;
        $end = is_numeric($this->freetime_end) ? $this->freetime_end : strtotime($this->freetime_end);
        return ceil(($end - time()) / 86400);
    }
}
?>
