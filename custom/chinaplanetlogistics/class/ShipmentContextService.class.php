<?php
require_once __DIR__.'/ShipmentDocumentService.class.php';
require_once __DIR__.'/../lib/chinaplanetlogistics.lib.php';

/** Read-only source projection. Task access must also be checked by the caller. */
class ShipmentContextService
{
    private $db;
    private $access;
    private $entity;

    public function __construct($db, ShipmentDocumentService $access, $entity)
    {
        $this->db = $db;
        $this->access = $access;
        $this->entity = (int) $entity;
    }

    private function label($key, $fallback)
    {
        global $langs;
        if (is_object($langs) && method_exists($langs, 'trans')) {
            $text = $langs->trans($key);
            if ($text !== $key) return $text;
        }
        return $fallback;
    }

    private function date($value)
    {
        $date = substr((string) $value, 0, 10);
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D', $date, $m)
            || !checkdate((int) $m[2], (int) $m[3], (int) $m[1])) return null;
        return $date;
    }

    /** Calendar reference uses native display timezone; tests can pass an explicit date internally. */
    public function today()
    {
        return function_exists('dol_print_date') ? dol_print_date(dol_now(), 'dayrfc') : gmdate('Y-m-d');
    }

    /** Attention reasons are read-only projections, never shipment lifecycle updates. */
    public function attention($id, $today = null)
    {
        $out = $this->get($id); // Existing owner access check precedes the source read.
        $today = $today === null ? $this->today() : $today;
        if ($this->date($today) !== $today) throw new InvalidArgumentException('Invalid attention date');
        $out['assessedOn'] = $today;
        $out['reasons'] = array();
        if ($out['status'] === null || !chinaplanetlogistics_status_in($out['status'], 'active')) return $out;
        $fields = array_column($out['fields'], 'value', 'key');
        $add = function ($code, $key, $fallback, $date) use (&$out) {
            $out['reasons'][] = array('code'=>$code, 'label'=>$this->label($key, $fallback), 'date'=>$date);
        };
        if ($fields['date_eta'] !== null && $fields['date_eta'] < $today && $fields['date_ata'] === null) {
            $add('eta_unconfirmed', 'CPLAttentionEtaUnconfirmed', 'ETA passed; actual arrival not recorded', $fields['date_eta']);
        }
        // Same active-status and five-day free-time window as the Logistics dashboard.
        $free = $fields['freetime_end'];
        $soon = (new DateTimeImmutable($today))->modify('+5 days')->format('Y-m-d');
        if ($free !== null && $free < $today) $add('free_time_expired', 'CPLAttentionFreeTimeExpired', 'Free time expired', $free);
        elseif ($free !== null && $free <= $soon) $add('free_time_soon', 'CPLAttentionFreeTimeSoon', 'Free time ends within 5 days', $free);
        return $out;
    }

    public function get($id)
    {
        // Use the existing owner authorization before reading any operational fields.
        $source = $this->access->requireShipment((int) $id);
        if ((int) $source['entity'] !== $this->entity || (int) $source['rowid'] !== (int) $id) {
            throw new ShipmentDocumentAccessException('Shipment access denied.');
        }
        $sql = 'SELECT rowid,fk_statut,container_no,date_etd,date_eta,date_ata,freetime_end FROM '.MAIN_DB_PREFIX.'chinaplanetlogistics'
            .' WHERE entity='.$this->entity.' AND rowid='.(int) $id.' AND fk_soc='.(int) $source['fk_soc']
            .' AND COALESCE(fk_project,0)='.(int) $source['fk_project'];
        $result = $this->db->query($sql);
        if (!$result) throw new RuntimeException('Shipment context unavailable.');
        $row = $this->db->fetch_object($result);
        if (!$row) throw new ShipmentDocumentAccessException('Shipment access denied.');
        global $langs;
        if (is_object($langs) && method_exists($langs, 'load')) $langs->load('shipmentcontext@chinaplanetlogistics');
        $status = $row->fk_statut === null ? null : (int) $row->fk_statut;
        $known = $status !== null && isset(chinaplanetlogistics_status_map()[$status]);
        $out = array(
            'sourceId'=>(int) $id,
            'title'=>$this->label('CPLContextTitle', 'Shipment tracking'),
            'statusTitle'=>$this->label('CPLContextStatus', 'Shipment status'),
            'status'=>$status,
            'statusLabel'=>$known ? $this->label('CPLContextStatus'.$status, chinaplanetlogistics_status_label($status)) : $this->label('CPLContextUnknown', 'Unknown'),
            'missingLabel'=>$this->label('CPLContextMissing', 'Not recorded'),
            'fields'=>array(),
        );
        foreach (array(
            'container_no'=>array('CPLContextContainer','Container'),
            'date_etd'=>array('CPLContextETD','Estimated departure'),
            'date_eta'=>array('CPLContextETA','Estimated arrival'),
            'date_ata'=>array('CPLContextATA','Actual arrival'),
            'freetime_end'=>array('CPLContextFreeTime','Free time expiry'),
        ) as $field=>$label) {
            $value = $field === 'container_no' ? (trim((string) $row->$field) ?: null) : $this->date($row->$field);
            $out['fields'][] = array('key'=>$field, 'label'=>$this->label($label[0], $label[1]), 'value'=>$value);
        }
        return $out;
    }
}
