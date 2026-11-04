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
