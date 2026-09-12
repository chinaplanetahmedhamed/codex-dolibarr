<?php

/**
 * Creates the real status-0 shipment shell owned by an eligible operation.
 * It deliberately does not create booking, loading, departure, arrival,
 * document publication, CargoX, DHL or notification side effects.
 */
class OperationShipmentService
{
    private $db;
    private $entity;

    public function __construct($db, $entity = 0)
    {
        global $conf;
        $this->db = $db;
        $this->entity = (int) $entity > 0 ? (int) $entity : (!empty($conf->entity) ? (int) $conf->entity : 1);
    }

    public static function eligibleServiceTypes()
    {
        return array('shipping_inquiry', 'shipping_booking');
    }

    public function createLinkedDraft($operationId, $userId)
    {
        $operationId = (int) $operationId;
        if ($operationId <= 0) throw new RuntimeException('Operation is required.');
        $lockName = 'm077s'.$this->entity.'o'.$operationId;
        $lock = $this->one('SELECT GET_LOCK('.$this->q($lockName).',5) acquired');
        if (!$lock || (int) $lock->acquired !== 1) throw new RuntimeException('Unable to reserve shipment creation.');
        $started = false;
        try {
            $this->db->begin(); $started = true;
            $operation = $this->one("SELECT rowid,ref,service_type,fk_soc,fk_project,fk_commande,fk_shipment,fk_user_assign FROM ".MAIN_DB_PREFIX."cpo_operation WHERE entity=".$this->entity." AND rowid=".$operationId." AND status=1 FOR UPDATE");
            if (!$operation) throw new RuntimeException('Operation was not found in the active entity.');
            if (!in_array((string) $operation->service_type, self::eligibleServiceTypes(), true)) throw new RuntimeException('Only shipping inquiry or booking operations can create a draft shipment.');
            if ((int) $operation->fk_soc <= 0) throw new RuntimeException('A real customer is required before shipment creation.');
            $this->validateContext($operation);
            if ((int) $operation->fk_shipment > 0) {
                $shipment = $this->ownedShipment((int) $operation->fk_shipment, (int) $operation->fk_soc, (int) $operation->fk_project);
                if (!$shipment) throw new RuntimeException('Existing shipment link violates customer or project ownership.');
                $this->db->commit(); $started = false; $this->unlock($lockName);
                return (int) $operation->fk_shipment;
            }
            $service = $this->serviceData($operationId);
            $pol = trim((string) ($service['pol'] ?? ''));
            $pod = trim((string) ($service['pod'] ?? ''));
            if ($pol === '' || $pod === '') throw new RuntimeException('Origin and destination are required before creating the linked draft shipment.');
            $ref = $this->shipmentRef((string) $operation->ref, $operationId);
            $existing = $this->one("SELECT rowid,fk_soc,fk_project FROM ".MAIN_DB_PREFIX."chinaplanetlogistics WHERE entity=".$this->entity." AND ref=".$this->q($ref)." FOR UPDATE");
            if ($existing) {
                if ((int) $existing->fk_soc !== (int) $operation->fk_soc || ((int) $operation->fk_project > 0 && (int) $existing->fk_project !== (int) $operation->fk_project)) throw new RuntimeException('Draft shipment reference already belongs to different context.');
                $shipmentId = (int) $existing->rowid;
            } else {
                $sql = "INSERT INTO ".MAIN_DB_PREFIX."chinaplanetlogistics(ref,entity,fk_soc,fk_project,fk_commande,fk_user_creat,fk_user_assign,container_type,nb_containers,port_loading,port_destination,date_etd,date_eta,fk_statut,notes,customer_note,customer_visible,date_creation) VALUES(";
                $sql .= $this->q($ref).",".$this->entity.",".(int) $operation->fk_soc.",".$this->intOrNull($operation->fk_project).",".$this->zeroOrInt($operation->fk_commande).",".(int) $userId.",".$this->intOrNull($operation->fk_user_assign).",".$this->nullable($service['container_type'] ?? '').",1,".$this->q($pol).",".$this->q($pod).",".$this->dateOrNull($service['etd'] ?? '').",".$this->dateOrNull($service['eta'] ?? '').",0,".$this->q('Draft created from operation '.$operation->ref.'; booking and milestones are not confirmed.').",NULL,0,".$this->q($this->now()).")";
                if (!$this->db->query($sql)) $this->fail('Unable to create draft shipment.');
                $shipmentId = (int) $this->db->last_insert_id(MAIN_DB_PREFIX.'chinaplanetlogistics');
                $this->insertBaselineEvent($shipmentId, (int) $operation->fk_soc, (string) $operation->ref, $userId);
            }
            $update = "UPDATE ".MAIN_DB_PREFIX."cpo_operation SET fk_shipment=".$shipmentId.",fk_user_modif=".(int) $userId." WHERE entity=".$this->entity." AND rowid=".$operationId." AND COALESCE(fk_shipment,0)=0";
            if (!$this->db->query($update)) $this->fail('Unable to link draft shipment to operation.');
            $this->insertOperationEvent($operationId, $shipmentId, $userId);
            $this->db->commit(); $started = false; $this->unlock($lockName);
            return $shipmentId;
        } catch (Throwable $e) {
            if ($started) $this->db->rollback();
            $this->unlock($lockName); throw $e;
        }
    }

    private function validateContext($operation)
    {
        $customer = $this->one("SELECT rowid FROM ".MAIN_DB_PREFIX."societe WHERE entity=".$this->entity." AND rowid=".(int) $operation->fk_soc." AND status=1 LIMIT 1");
        if (!$customer) throw new RuntimeException('Operation customer is invalid or inactive.');
        if ((int) $operation->fk_project > 0) {
            $project = $this->one("SELECT rowid FROM ".MAIN_DB_PREFIX."projet WHERE entity=".$this->entity." AND rowid=".(int) $operation->fk_project." AND fk_soc=".(int) $operation->fk_soc." LIMIT 1");
            if (!$project) throw new RuntimeException('Operation project does not belong to the customer.');
        }
        if ((int) $operation->fk_commande > 0) {
            $order = $this->one("SELECT rowid FROM ".MAIN_DB_PREFIX."commande WHERE entity=".$this->entity." AND rowid=".(int) $operation->fk_commande." AND fk_soc=".(int) $operation->fk_soc." LIMIT 1");
            if (!$order) throw new RuntimeException('Operation order does not belong to the customer.');
        }
    }

    private function ownedShipment($shipmentId, $socid, $projectId)
    {
        $sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."chinaplanetlogistics WHERE entity=".$this->entity." AND rowid=".(int) $shipmentId." AND fk_soc=".(int) $socid;
        if ($projectId > 0) $sql .= " AND fk_project=".(int) $projectId;
        return $this->one($sql.' LIMIT 1');
    }

    private function serviceData($operationId)
    {
        $row = $this->one("SELECT data_json FROM ".MAIN_DB_PREFIX."cpo_operation_data WHERE entity=".$this->entity." AND fk_operation=".(int) $operationId." AND section_code='service' LIMIT 1");
        $data = $row ? json_decode((string) $row->data_json, true) : array();
        return is_array($data) ? $data : array();
    }

    private function shipmentRef($operationRef, $operationId)
    {
        $safe = preg_replace('/[^A-Z0-9-]/', '', strtoupper($operationRef));
        return substr('SHP-'.substr($safe, 0, 17).'-'.$operationId, 0, 30);
    }

    private function insertBaselineEvent($shipmentId, $socid, $operationRef, $userId)
    {
        $now = $this->now();
        $sql = "INSERT INTO ".MAIN_DB_PREFIX."cpl_shipment_event(entity,fk_shipment,fk_soc,event_type,milestone_code,status_to,event_at,source_type,source_label_public,note_public,customer_visible,fk_user_creat,date_creation) VALUES(";
        $sql .= $this->entity.",".(int) $shipmentId.",".(int) $socid.",'baseline','inquiry',0,".$this->q($now).",'operation_draft',".$this->q('China Planet operation '.$operationRef).",".$this->q('Draft tracking record created; no booking or movement confirmed.').",0,".(int) $userId.",".$this->q($now).")";
        if (!$this->db->query($sql)) $this->fail('Unable to write shipment audit event.');
    }

    private function insertOperationEvent($operationId, $shipmentId, $userId)
    {
        $sql = "INSERT INTO ".MAIN_DB_PREFIX."cpo_activity(entity,fk_operation,fk_task,action_code,message,visibility,fk_user,date_action) VALUES(".$this->entity.",".(int) $operationId.",NULL,'shipment_draft_linked',".$this->q('Draft shipment #'.(int) $shipmentId.' linked; no booking or movement confirmed.').",'internal',".(int) $userId.",".$this->q($this->now()).")";
        if (!$this->db->query($sql)) $this->fail('Unable to write operation audit event.');
    }

    private function one($sql)
    {
        $res = $this->db->query($sql);
        if (!$res) $this->fail('Database query failed.');
        return $this->db->fetch_object($res);
    }

    private function unlock($name)
    {
        if ($name !== '') $this->db->query('SELECT RELEASE_LOCK('.$this->q($name).')');
    }

    private function intOrNull($value)
    {
        return (int) $value > 0 ? (string) (int) $value : 'NULL';
    }

    private function zeroOrInt($value)
    {
        return (int) $value > 0 ? (string) (int) $value : '0';
    }

    private function dateOrNull($value)
    {
        $value = trim((string) $value);
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $this->q($value) : 'NULL';
    }

    private function nullable($value)
    {
        return trim((string) $value) === '' ? 'NULL' : $this->q($value);
    }

    private function q($value)
    {
        return "'".$this->db->escape((string) $value)."'";
    }

    private function now()
    {
        return $this->db->idate(function_exists('dol_now') ? dol_now() : time());
    }

    private function fail($message)
    {
        $detail = method_exists($this->db, 'lasterror') ? (string) $this->db->lasterror() : '';
        throw new RuntimeException($detail !== '' ? $detail : $message);
    }
}
