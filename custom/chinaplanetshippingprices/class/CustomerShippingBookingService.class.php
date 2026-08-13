<?php

require_once __DIR__.'/ShippingPriceService.class.php';
require_once __DIR__.'/CustomerShippingOperationGatewayInterface.class.php';

class CustomerShippingBookingService
{
    private $db;
    private $entity;
    private $prices;
    private $operationGateway;

    public function __construct($db, $entity = null, $prices = null, $operationGateway = null)
    {
        $this->db = $db;
        $this->entity = $entity === null ? (int) $GLOBALS['conf']->entity : (int) $entity;
        $this->prices = $prices ?: new ShippingPriceService($db, $this->entity);
        if (!($operationGateway instanceof CustomerShippingOperationGatewayInterface)) {
            throw new InvalidArgumentException('A customer shipping operation gateway is required.');
        }
        $this->operationGateway = $operationGateway;
    }

    public function submit($user, array $posted)
    {
        $this->assertCustomer($user);
        $socid = (int) $user->socid;
        $rateId = (int) ($posted['rate_id'] ?? 0);
        $idempotencyKey = trim((string) ($posted['idempotency_key'] ?? ''));
        $postedFingerprint = strtolower(trim((string) ($posted['rate_fingerprint'] ?? '')));
        if ($rateId <= 0) throw new InvalidArgumentException('The selected price is missing. Please search again.');
        if (!preg_match('/^[a-zA-Z0-9_-]{16,64}$/', $idempotencyKey)) throw new InvalidArgumentException('The booking retry key is invalid. Please open the booking form again.');
        if (!preg_match('/^[a-f0-9]{64}$/', $postedFingerprint)) throw new InvalidArgumentException('The selected price fingerprint is invalid. Please search again.');

        $filters = $this->normalizeFilters($posted);
        $input = $this->normalizeBookingInput($posted);
        $requestHash = hash('sha256', json_encode(array('rate_id'=>$rateId,'fingerprint'=>$postedFingerprint,'filters'=>$filters,'booking'=>$input), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $existing = $this->findByIdempotency($socid, $idempotencyKey, false);
        if ($existing) return $this->resolveRetry($existing, $requestHash);

        $rate = $this->prices->getCustomerRateById($rateId, $filters);
        if (!$rate || !hash_equals((string) $rate['fingerprint'], $postedFingerprint)) {
            throw new RuntimeException('The price changed or expired. No booking was created; please search again.');
        }
        if (!empty($rate['needs_manual_review']) || $input['cargo_review_required']) {
            throw new RuntimeException('This cargo needs a manual shipping review and cannot use normal direct booking.');
        }

        $quantity = $input['container_quantity'];
        $snapshot = array(
            'schema'=>'m035.customer_rate.v1',
            'rate_evidence'=>hash('sha256', $this->entity.'|'.$rateId.'|'.$rate['fingerprint']),
            'route'=>array('pol'=>$rate['pol'],'pod'=>$rate['pod'],'country_code'=>$rate['country_code'],'shipping_line'=>$rate['shipping_line'],'service_type'=>$rate['service_type']),
            'equipment'=>$rate['container_type'],
            'price_basis'=>'per_container',
            'unit_price'=>(float) $rate['final_price'],
            'quantity'=>$quantity,
            'total'=>round((float) $rate['final_price'] * $quantity, 2),
            'currency'=>$rate['currency'],
            'components'=>$rate['components'],
            'included'=>$rate['included'],
            'excluded'=>$rate['excluded'],
            'valid_from'=>$rate['valid_from'],
            'valid_to'=>$rate['valid_to'],
            'public_notes'=>$rate['notes'],
            'public_warnings'=>$rate['warnings'],
            'rate_fingerprint'=>$rate['fingerprint'],
            'acceptance'=>array('accepted'=>true,'fk_user'=>(int) $user->id,'date'=>$this->now()),
        );
        $termsHash = hash('sha256', json_encode(array('included'=>$snapshot['included'],'excluded'=>$snapshot['excluded'],'notes'=>$snapshot['public_notes'],'warnings'=>$snapshot['public_warnings']), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $snapshot['terms_hash'] = $termsHash;

        $operationData = array(
            'service_type'=>'shipping_booking',
            'title'=>'Customer FCL booking: '.$rate['pol'].' to '.$rate['pod'].' · '.$rate['container_type'].' × '.$quantity,
            'fk_soc'=>$socid,
            'fk_project'=>0,
            'fk_commande'=>0,
            'fk_shipment'=>0,
            'fk_user_assign'=>0,
            'priority'=>'normal',
            'waiting_on'=>'internal',
            'next_action'=>'Validate cargo and request carrier space',
            'date_deadline'=>'',
            'note_private'=>'',
            'note_public'=>'Booking received. ChinaPlanet is reviewing cargo details and carrier space.',
            'customer_visible'=>1,
            'service_data'=>array(
                'source_channel'=>'customer_rate_booking',
                'shipper'=>$input['shipper'],
                'consignee'=>$input['consignee'],
                'notify_party'=>$input['notify_party'],
                'pol'=>$rate['pol'],
                'pod'=>$rate['pod'],
                'incoterm'=>$rate['incoterm'],
                'load_type'=>'FCL',
                'container_request'=>$rate['container_type'].' × '.$quantity,
                'cargo_ready_date'=>$input['cargo_ready_date'],
                'cargo_summary'=>$input['cargo_description'],
                'hs_code'=>$input['hs_code'],
                'dangerous_goods'=>'no',
                'customer_requirements'=>$input['customer_instruction'],
                'booking_status'=>'under_review',
            ),
        );

        try {
            $this->db->begin();
            $existing = $this->findByIdempotency($socid, $idempotencyKey, true);
            if ($existing) {
                $this->db->rollback();
                return $this->resolveRetry($existing, $requestHash);
            }
            $operationId = $this->operationGateway->createBookingOperation($operationData, $snapshot, (int) $user->id);
            $sql = "INSERT INTO ".MAIN_DB_PREFIX."chinaplanetsp_booking(entity,fk_soc,fk_rate,fk_operation,idempotency_key,request_hash,rate_fingerprint,input_json,snapshot_json,terms_hash,status,fk_user_creat,date_acceptance,date_creation) VALUES(".
                $this->entity.",".$socid.",".$rateId.",".$operationId.",'".$this->db->escape($idempotencyKey)."','".$requestHash."','".$rate['fingerprint']."','".$this->db->escape(json_encode($input, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))."','".$this->db->escape(json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))."','".$termsHash."','received',".(int) $user->id.",'".$this->db->idate(dol_now())."','".$this->db->idate(dol_now())."')";
            if (!$this->db->query($sql)) throw new RuntimeException($this->db->lasterror());
            $bookingId = (int) $this->db->last_insert_id(MAIN_DB_PREFIX.'chinaplanetsp_booking');
            $this->db->commit();
            return $this->getCustomerBooking($user, $bookingId);
        } catch (Throwable $e) {
            $this->db->rollback();
            $existing = $this->findByIdempotency($socid, $idempotencyKey, false);
            if ($existing) return $this->resolveRetry($existing, $requestHash);
            throw $e;
        }
    }

    public function listCustomerBookings($user, $limit = 20)
    {
        $this->assertCustomer($user);
        $sql = $this->customerSelect()." WHERE b.entity=".$this->entity." AND b.fk_soc=".(int) $user->socid." AND o.customer_visible=1 ORDER BY b.date_creation DESC LIMIT ".max(1, min(100, (int) $limit));
        $res = $this->db->query($sql);
        $out = array();
        if ($res) while ($row = $this->db->fetch_object($res)) $out[] = $this->projectCustomerBooking($row);
        return $out;
    }

    public function getCustomerBooking($user, $bookingId)
    {
        $this->assertCustomer($user);
        $sql = $this->customerSelect()." WHERE b.entity=".$this->entity." AND b.fk_soc=".(int) $user->socid." AND b.rowid=".(int) $bookingId." AND o.customer_visible=1";
        $res = $this->db->query($sql);
        $row = $res ? $this->db->fetch_object($res) : null;
        return $row ? $this->projectCustomerBooking($row) : null;
    }

    private function customerSelect()
    {
        return "SELECT b.rowid,b.date_creation,b.snapshot_json,o.ref,o.stage,o.progress,o.fk_shipment,o.note_public FROM ".MAIN_DB_PREFIX."chinaplanetsp_booking b INNER JOIN ".MAIN_DB_PREFIX."cpo_operation o ON o.rowid=b.fk_operation AND o.entity=b.entity AND o.fk_soc=b.fk_soc";
    }

    private function projectCustomerBooking($row)
    {
        $snapshot = json_decode((string) $row->snapshot_json, true);
        if (!is_array($snapshot)) $snapshot = array();
        $status = 'operational_review';
        if (!empty($row->fk_shipment)) $status = 'linked_shipment';
        elseif ($row->stage === 'cancelled') $status = 'cancelled';
        elseif (in_array($row->stage, array('corrective_action','waiting_information'), true)) $status = 'changed_exception';
        elseif (in_array($row->stage, array('published','completed'), true)) $status = 'carrier_confirmed';
        elseif ($row->stage === 'new') $status = 'received';
        return array(
            'id'=>(int) $row->rowid,
            'reference'=>$row->ref,
            'status'=>$status,
            'progress'=>(int) $row->progress,
            'customer_message'=>(string) $row->note_public,
            'created_at'=>$row->date_creation,
            'route'=>$snapshot['route'] ?? array(),
            'equipment'=>$snapshot['equipment'] ?? '',
            'quantity'=>(int) ($snapshot['quantity'] ?? 0),
            'unit_price'=>(float) ($snapshot['unit_price'] ?? 0),
            'total'=>(float) ($snapshot['total'] ?? 0),
            'currency'=>$snapshot['currency'] ?? '',
        );
    }

    private function normalizeFilters(array $posted)
    {
        return array(
            'pol'=>trim((string) ($posted['pol'] ?? '')),
            'country_code'=>strtoupper(trim((string) ($posted['country_code'] ?? ''))),
            'pod'=>trim((string) ($posted['pod'] ?? '')),
            'shipping_line'=>trim((string) ($posted['shipping_line'] ?? '')),
            'container_type'=>strtoupper(trim((string) ($posted['container_type'] ?? ''))),
            'service_type'=>strtolower(trim((string) ($posted['service_type'] ?? ''))),
            'incoterm'=>strtoupper(trim((string) ($posted['incoterm'] ?? 'FOB'))),
            'cargo_type'=>'normal',
            'documents'=>array(),
        );
    }

    private function normalizeBookingInput(array $posted)
    {
        $input = array(
            'container_quantity'=>(int) ($posted['container_quantity'] ?? 0),
            'cargo_ready_date'=>trim((string) ($posted['cargo_ready_date'] ?? '')),
            'cargo_description'=>$this->cleanText($posted['cargo_description'] ?? '', 2000),
            'hs_code'=>$this->cleanText($posted['hs_code'] ?? '', 64),
            'shipper'=>$this->cleanText($posted['shipper'] ?? '', 500),
            'consignee'=>$this->cleanText($posted['consignee'] ?? '', 500),
            'notify_party'=>$this->cleanText($posted['notify_party'] ?? '', 500),
            'customer_instruction'=>$this->cleanText($posted['customer_instruction'] ?? '', 2000),
            'cargo_review_required'=>false,
        );
        foreach (array('battery','liquid','powder','chemical','dangerous_goods','copy_brand') as $flag) {
            $input[$flag] = !empty($posted[$flag]);
            if ($input[$flag]) $input['cargo_review_required'] = true;
        }
        if ($input['container_quantity'] < 1 || $input['container_quantity'] > 20) throw new InvalidArgumentException('Container quantity must be between 1 and 20.');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $input['cargo_ready_date']) || $input['cargo_ready_date'] < date('Y-m-d') || $input['cargo_ready_date'] > date('Y-m-d', strtotime('+1 year'))) throw new InvalidArgumentException('Cargo-ready date is invalid.');
        if (mb_strlen($input['cargo_description']) < 3) throw new InvalidArgumentException('Cargo description is required.');
        foreach (array('shipper','consignee') as $field) if (mb_strlen($input[$field]) < 2) throw new InvalidArgumentException(ucwords(str_replace('_',' ',$field)).' is required.');
        if (empty($posted['accept_terms'])) throw new InvalidArgumentException('You must accept the displayed price scope and exclusions.');
        return $input;
    }

    private function cleanText($value, $max)
    {
        $value = trim(strip_tags((string) $value));
        return mb_substr($value, 0, $max);
    }

    private function findByIdempotency($socid, $key, $lock)
    {
        $sql = "SELECT b.rowid,b.request_hash,b.fk_operation,o.ref FROM ".MAIN_DB_PREFIX."chinaplanetsp_booking b INNER JOIN ".MAIN_DB_PREFIX."cpo_operation o ON o.rowid=b.fk_operation AND o.entity=b.entity AND o.fk_soc=b.fk_soc WHERE b.entity=".$this->entity." AND b.fk_soc=".(int) $socid." AND b.idempotency_key='".$this->db->escape($key)."'".($lock ? ' FOR UPDATE' : '');
        $res = $this->db->query($sql);
        return $res ? $this->db->fetch_object($res) : null;
    }

    private function resolveRetry($existing, $requestHash)
    {
        if (!hash_equals((string) $existing->request_hash, $requestHash)) throw new RuntimeException('This submission key was already used for different booking details.');
        $synthetic = new stdClass();
        $synthetic->rowid = $existing->rowid;
        return array('id'=>(int) $existing->rowid,'reference'=>$existing->ref,'status'=>'received','duplicate'=>true);
    }

    private function assertCustomer($user)
    {
        if (empty($user->id) || empty($user->socid) || (int) $user->socid <= 0 || !$user->hasRight('chinaplanetshippingprices', 'read') || !$user->hasRight('chinaplanetshippingprices', 'booking_submit')) {
            throw new RuntimeException('Customer booking permission is required.');
        }
    }

    private function now()
    {
        return $this->db->idate(dol_now());
    }
}
