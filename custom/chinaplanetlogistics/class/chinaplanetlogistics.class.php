<?php
require_once __DIR__.'/../lib/shipment_customer.lib.php';
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
    public $date_atd;
    public $date_eta;
    public $date_ata;
    public $freetime_days;
    public $freetime_start;
    public $freetime_end;
    public $fk_statut;
    public $notes;
    public $customer_note;
    public $customer_visible = 1;
    public $date_creation;
    public $tms;
    public $audit_source_type = 'manual_team';
    public $audit_source_label = '';
    public $audit_reason_public = '';
    public $audit_customer_visible = 1;
    public $last_status_change = null;

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

        if (!cpl_shipment_schema_ready($this->db)) {
            $this->error = 'Shipment command-center schema is not installed.';
            return -1;
        }

        if (empty($this->ref) || $this->ref == 'AUTO') {
            $this->ref = 'LOG-'.date('Ymd-His');
        }
        $this->calculateFreeTimeDates();
        $entity = $this->activeEntity();

        $sql  = "INSERT INTO ".MAIN_DB_PREFIX."chinaplanetlogistics (";
        $sql .= "ref, entity, fk_soc, fk_project, fk_user_creat,";
        $sql .= "container_no, container_type, nb_containers, booking_no, bl_no,";
        $sql .= "shipping_line, vessel_name, voyage_no, port_loading, port_destination,";
        $sql .= "date_etd, date_atd, date_eta, date_ata,";
        $sql .= "freetime_days, freetime_start, freetime_end,";
        $sql .= "fk_statut, notes, customer_note, customer_visible, date_creation";
        $sql .= ") VALUES (";
        $sql .= $this->sqlStr($this->ref).", ".$entity.", ".$this->sqlInt($this->fk_soc).", ".$this->sqlInt($this->fk_project).", ".(int)$user->id.", ";
        $sql .= $this->sqlStr($this->container_no).", ".$this->sqlStr($this->container_type).", ".$this->sqlInt($this->nb_containers).", ";
        $sql .= $this->sqlStr($this->booking_no).", ".$this->sqlStr($this->bl_no).", ";
        $sql .= $this->sqlStr($this->shipping_line).", ".$this->sqlStr($this->vessel_name).", ".$this->sqlStr($this->voyage_no).", ";
        $sql .= $this->sqlStr($this->port_loading).", ".$this->sqlStr($this->port_destination).", ";
        $sql .= $this->sqlDate($this->date_etd).", ".$this->sqlDate($this->date_atd).", ".$this->sqlDate($this->date_eta).", ".$this->sqlDate($this->date_ata).", ";
        $sql .= $this->sqlInt($this->freetime_days).", ".$this->sqlDate($this->freetime_start).", ".$this->sqlDate($this->freetime_end).", ";
        $sql .= $this->sqlInt($this->fk_statut).", ".$this->sqlStr($this->notes).", ".$this->sqlStr($this->customer_note).", ".(!empty($this->customer_visible) ? 1 : 0).", ";
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
        $this->entity = $entity;
        $initialEvents = array(array('event_type'=>'baseline','milestone_code'=>cpl_status_milestone($this->fk_statut),'status_to'=>$this->fk_statut));
        if (!empty($this->date_eta)) $initialEvents[] = array('event_type'=>'eta_set','milestone_code'=>'arrived','planned_at'=>$this->date_eta,'date_value_new'=>$this->date_eta);
        if (!empty($this->date_atd)) $initialEvents[] = array('event_type'=>'atd_set','milestone_code'=>'departed','actual_at'=>$this->date_atd,'date_value_new'=>$this->date_atd);
        if (!empty($this->date_ata)) $initialEvents[] = array('event_type'=>'ata_set','milestone_code'=>'arrived','actual_at'=>$this->date_ata,'date_value_new'=>$this->date_ata);
        foreach ($initialEvents as $event) {
            $event['source_type'] = $this->audit_source_type;
            $event['source_label_public'] = $this->audit_source_label;
            $event['reason_public'] = $this->audit_reason_public;
            $event['customer_visible'] = $this->audit_customer_visible;
            if (!cpl_shipment_event_insert($this->db, $this, $user, $event)) {
                $this->error = $this->db->lasterror();
                $this->db->rollback();
                return -1;
            }
        }
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
        $this->date_atd       = isset($obj->date_atd) ? $this->db->jdate($obj->date_atd) : null;
        $this->date_eta       = $this->db->jdate($obj->date_eta);
        $this->date_ata       = $this->db->jdate($obj->date_ata);
        $this->freetime_days  = $obj->freetime_days;
        $this->freetime_start = $this->db->jdate($obj->freetime_start);
        $this->freetime_end   = $this->db->jdate($obj->freetime_end);
        $this->fk_statut      = $obj->fk_statut;
        $this->notes          = $obj->notes;
        $this->customer_note  = $obj->customer_note;
        $this->customer_visible = isset($obj->customer_visible) ? (int) $obj->customer_visible : 1;
        $this->date_creation  = $this->db->jdate($obj->date_creation);
        $this->tms            = isset($obj->tms) ? $this->db->jdate($obj->tms) : null;
        return 1;
    }

    // ── UPDATE ────────────────────────────────────────────────────────────
    public function update($user, $notrigger = 0)
    {
        $this->last_status_change = null;
        if (!cpl_shipment_schema_ready($this->db)) {
            $this->error = 'Shipment command-center schema is not installed; audited shipment changes are blocked.';
            return -1;
        }
        $this->calculateFreeTimeDates();

        $this->db->begin();
        $lockSql = "SELECT fk_soc,fk_statut,date_eta,date_atd,date_ata FROM ".MAIN_DB_PREFIX."chinaplanetlogistics WHERE rowid=".(int) $this->id." AND entity = ".$this->activeEntity()." FOR UPDATE";
        $lockRes = $this->db->query($lockSql);
        $old = $lockRes ? $this->db->fetch_object($lockRes) : null;
        if (!$old) {
            $this->error = 'Shipment was not found.';
            $this->db->rollback();
            return -1;
        }
        if ((int) $old->fk_soc !== (int) $this->fk_soc && cpl_shipment_has_audit_rows($this->db, $this->id, $this->activeEntity(), (int) $old->fk_soc)) {
            $this->error = 'Shipment customer cannot be changed after audit history or document requirements exist.';
            $this->db->rollback();
            return -1;
        }

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
        $sql .= "date_atd = ".$this->sqlDate($this->date_atd).", ";
        $sql .= "date_eta = ".$this->sqlDate($this->date_eta).", ";
        $sql .= "date_ata = ".$this->sqlDate($this->date_ata).", ";
        $sql .= "freetime_days = ".$this->sqlInt($this->freetime_days).", ";
        $sql .= "freetime_start = ".$this->sqlDate($this->freetime_start).", ";
        $sql .= "freetime_end = ".$this->sqlDate($this->freetime_end).", ";
        $sql .= "fk_statut = ".$this->sqlInt($this->fk_statut).", ";
        $sql .= "notes = ".$this->sqlStr($this->notes).", ";
        $sql .= "customer_note = ".$this->sqlStr($this->customer_note).", ";
        $sql .= "customer_visible = ".(!empty($this->customer_visible) ? 1 : 0)." ";
        $sql .= "WHERE rowid = ".(int)$this->id." AND entity = ".$this->activeEntity();

        $resql = $this->db->query($sql);
        if (!$resql) {
            $this->error = $this->db->lasterror();
            $this->db->rollback();
            return -1;
        }

        $this->entity = $this->activeEntity();
        $events = array();
        if ((int) $old->fk_statut !== (int) $this->fk_statut) {
            $this->last_status_change = array('from'=>(int) $old->fk_statut, 'to'=>(int) $this->fk_statut);
            $events[] = array('event_type'=>'status_changed','milestone_code'=>cpl_status_milestone($this->fk_statut),'status_from'=>(int)$old->fk_statut,'status_to'=>(int)$this->fk_statut);
        }
        $oldEta = $this->db->jdate($old->date_eta); $newEta = $this->date_eta;
        if ((int) $oldEta !== (int) $newEta) {
            $events[] = array('event_type'=>empty($oldEta)?'eta_set':'eta_changed','milestone_code'=>'arrived','planned_at'=>$newEta,'date_value_old'=>$oldEta,'date_value_new'=>$newEta);
        }
        foreach (array('atd'=>array('old'=>$this->db->jdate($old->date_atd),'new'=>$this->date_atd,'milestone'=>'departed'), 'ata'=>array('old'=>$this->db->jdate($old->date_ata),'new'=>$this->date_ata,'milestone'=>'arrived')) as $code=>$dates) {
            if ((int)$dates['old'] === (int)$dates['new']) continue;
            $type = empty($dates['new']) ? $code.'_cleared' : (empty($dates['old']) ? $code.'_set' : $code.'_changed');
            $events[] = array('event_type'=>$type,'milestone_code'=>$dates['milestone'],'actual_at'=>$dates['new'],'date_value_old'=>$dates['old'],'date_value_new'=>$dates['new']);
        }
        foreach ($events as $event) {
            $event['source_type'] = $this->audit_source_type;
            $event['source_label_public'] = $this->audit_source_label;
            $event['reason_public'] = $this->audit_reason_public;
            $event['customer_visible'] = $this->audit_customer_visible;
            if (!cpl_shipment_event_insert($this->db, $this, $user, $event)) {
                $this->error = $this->db->lasterror();
                $this->db->rollback();
                return -1;
            }
        }
        if (!$this->db->commit()) {
            $this->last_status_change = null;
            $this->error = 'Shipment update could not be committed.';
            return -1;
        }
        return 1;
    }

    // ── DELETE ────────────────────────────────────────────────────────────
    public function delete($user, $notrigger = 0)
    {
        if (!cpl_shipment_schema_ready($this->db)) {
            $this->error = 'Shipment command-center schema is not installed; safe deletion is blocked.';
            return -1;
        }
        $entity = $this->activeEntity();
        $this->db->begin();
        $lock = $this->db->query("SELECT rowid,fk_soc FROM ".MAIN_DB_PREFIX."chinaplanetlogistics WHERE rowid=".(int)$this->id." AND entity=".$entity." FOR UPDATE");
        $lockedShipment = $lock ? $this->db->fetch_object($lock) : null;
        if (!$lockedShipment) {
            $this->error = 'Shipment was not found.';
            $this->db->rollback();
            return -1;
        }
        if (cpl_shipment_has_audit_rows($this->db, $this->id, $entity, (int)$lockedShipment->fk_soc)) {
            $this->error = 'Shipment with audit history or document requirements cannot be deleted.';
            $this->db->rollback();
            return -1;
        }
        $sql = "DELETE FROM ".MAIN_DB_PREFIX."chinaplanetlogistics WHERE rowid = ".(int)$this->id." AND entity = ".$entity;
        $resql = $this->db->query($sql);
        if (!$resql) {
            $this->error = $this->db->lasterror();
            $this->db->rollback();
            return -1;
        }
        $this->db->commit();
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
            $this->freetime_start = null;
            $this->freetime_end = null;
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
