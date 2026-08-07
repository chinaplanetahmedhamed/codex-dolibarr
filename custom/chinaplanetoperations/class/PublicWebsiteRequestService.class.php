<?php

require_once __DIR__.'/OperationsService.class.php';
if (!class_exists('PublicShippingRateService')) require_once DOL_DOCUMENT_ROOT.'/custom/chinaplanetshippingprices/class/PublicShippingRateService.class.php';

class PublicWebsiteRequestException extends RuntimeException
{
    private $publicCode;
    private $httpStatus;

    public function __construct($publicCode, $httpStatus = 422)
    {
        parent::__construct((string) $publicCode);
        $this->publicCode = (string) $publicCode;
        $this->httpStatus = (int) $httpStatus;
    }

    public function getPublicCode() { return $this->publicCode; }
    public function getHttpStatus() { return $this->httpStatus; }
}

/** Durable, entity-scoped intake boundary for the anonymous React website. */
class PublicWebsiteRequestService
{
    const SOURCE = 'chinaplanet-main-website';
    const RETENTION_DAYS = 180;

    private $db;
    private $entity;
    private $hmacKey;
    private $rates;

    public function __construct($db, $entity = null, $hmacKey = null, $rateService = null)
    {
        $this->db = $db;
        $this->entity = $entity === null ? (int) $GLOBALS['conf']->entity : (int) $entity;
        $configured = $hmacKey === null ? (string) getenv('CHINAPLANET_PUBLIC_REQUEST_HMAC_KEY') : (string) $hmacKey;
        if ($hmacKey === null && strlen($configured) < 32 && function_exists('getDolGlobalString')) {
            $configured = (string) getDolGlobalString('CHINAPLANET_PUBLIC_REQUEST_HMAC_KEY');
        }
        if (strlen($configured) < 32) throw new PublicWebsiteRequestException('intake_service_not_configured', 503);
        $this->hmacKey = $configured;
        $this->rates = $rateService;
    }

    public static function requestTypes() { return array('quote_request', 'catalog_order', 'shipping_booking'); }
    public static function statuses() { return array('new', 'qualified', 'waiting_information', 'converted', 'disqualified', 'closed'); }

    public function create(array $input, $user)
    {
        $normalized = $this->normalize($input);
        $type = $normalized['request_type'];
        $key = $normalized['idempotency_key'];
        $hash = hash_hmac('sha256', $this->canonicalJson($normalized), $this->hmacKey);

        $this->db->begin();
        $existing = $this->findByIdempotency($type, $key, true);
        if ($existing) return $this->finishExisting($existing, $hash);

        $snapshot = isset($normalized['snapshot']) ? $normalized['snapshot'] : array();
        $fkRate = null;
        if ($type === 'shipping_booking') {
            try {
                $rates = $this->rates ?: new PublicShippingRateService($this->db, $this->entity);
                $resolved = $rates->resolveToken($normalized['rate_token']);
                $snapshot = $rates->buildCommercialSnapshot($resolved, $normalized['details']['container_quantity'], $normalized['details']['terms_acknowledged_at']);
                $fkRate = (int) $resolved['priced']['rate_id'];
            } catch (PublicShippingRateException $e) {
                $this->db->rollback();
                throw new PublicWebsiteRequestException($e->getPublicCode(), $e->getHttpStatus());
            }
        }

        $contact = $normalized['contact'];
        $contactIdentity = strtolower(trim($contact['email'].'|'.$contact['phone']));
        $contactHmac = hash_hmac('sha256', $contactIdentity, $this->hmacKey);
        $ref = 'WEB-'.gmdate('Ymd-His').'-'.strtoupper(bin2hex(random_bytes(3)));
        $request = array(
            'schema_version' => 1,
            'locale' => $normalized['locale'],
            'details' => $normalized['details'],
        );
        $sql = "INSERT INTO ".MAIN_DB_PREFIX."cpo_public_request(entity,ref,integration_source,request_type,idempotency_key,request_hash,contact_hmac,contact_name,company_name,phone,email,country_code,party_json,request_json,snapshot_json,fk_rate,status,fk_user_creat,date_creation) VALUES(".
            $this->entity.",".$this->q($ref).",".$this->q(self::SOURCE).",".$this->q($type).",".$this->q($key).",".$this->q($hash).",".$this->q($contactHmac).",".$this->q($contact['name']).",".$this->qOrNull($contact['company']).",".$this->qOrNull($contact['phone']).",".$this->qOrNull($contact['email']).",".$this->qOrNull($contact['country_code']).",".$this->jsonOrNull($normalized['parties']).",".$this->json($request).",".$this->jsonOrNull($snapshot).",".($fkRate ? (int) $fkRate : 'NULL').",'new',".(int) $user->id.",".$this->q($this->now()).")";
        if (!$this->db->query($sql)) {
            $this->db->rollback();
            $existing = $this->findByIdempotency($type, $key, false);
            if ($existing) return $this->finishExisting($existing, $hash, false);
            throw new PublicWebsiteRequestException('intake_service_unavailable', 503);
        }
        $id = (int) $this->db->last_insert_id(MAIN_DB_PREFIX.'cpo_public_request');
        $this->db->commit();
        return array('id' => $id, 'ref' => $ref, 'status' => 'new', 'duplicate' => false);
    }

    public function get($id, $forUpdate = false)
    {
        $sql = "SELECT * FROM ".MAIN_DB_PREFIX."cpo_public_request WHERE entity=".$this->entity." AND rowid=".(int) $id.($forUpdate ? ' FOR UPDATE' : '');
        $res = $this->db->query($sql);
        $obj = $res ? $this->db->fetch_object($res) : null;
        if (!$obj) return null;
        $row = (array) $obj;
        foreach (array('party_json'=>'parties', 'request_json'=>'request', 'snapshot_json'=>'snapshot') as $column=>$target) {
            $row[$target] = $this->decodeStoredJson(isset($row[$column]) ? $row[$column] : null);
            unset($row[$column]);
        }
        return $row;
    }

    public function listing(array $filters = array(), $limit = 100)
    {
        $sql = "SELECT rowid,ref,request_type,contact_name,company_name,country_code,status,fk_soc,fk_operation,fk_propal,date_creation,date_modification FROM ".MAIN_DB_PREFIX."cpo_public_request WHERE entity=".$this->entity;
        if (!empty($filters['status']) && in_array($filters['status'], self::statuses(), true)) $sql .= " AND status=".$this->q($filters['status']);
        if (!empty($filters['request_type']) && in_array($filters['request_type'], self::requestTypes(), true)) $sql .= " AND request_type=".$this->q($filters['request_type']);
        $sql .= " ORDER BY date_creation DESC,rowid DESC LIMIT ".max(1, min(250, (int) $limit));
        $res = $this->db->query($sql);
        $rows = array();
        if ($res) while ($obj = $this->db->fetch_object($res)) $rows[] = (array) $obj;
        return $rows;
    }

    public function updateStatus($id, $status, $reason, $userId)
    {
        if (!in_array($status, self::statuses(), true)) throw new PublicWebsiteRequestException('invalid_status', 422);
        $sql = "UPDATE ".MAIN_DB_PREFIX."cpo_public_request SET status=".$this->q($status).",internal_reason=".$this->qOrNull($this->clean($reason, 255)).",fk_user_modif=".(int) $userId.",date_modification=".$this->q($this->now())." WHERE entity=".$this->entity." AND rowid=".(int) $id." AND fk_operation IS NULL";
        $res = $this->db->query($sql);
        if (!$res) throw new RuntimeException($this->db->lasterror());
        return $this->db->affected_rows($res) > 0;
    }

    public function promote($id, $socid, $userId)
    {
        $this->db->begin();
        $row = $this->get($id, true);
        if (!$row) { $this->db->rollback(); throw new PublicWebsiteRequestException('request_not_found', 404); }
        if (!empty($row['fk_operation'])) { $this->db->commit(); return (int) $row['fk_operation']; }
        if (!in_array($row['status'], array('new', 'qualified', 'waiting_information'), true)) { $this->db->rollback(); throw new PublicWebsiteRequestException('request_not_promotable', 409); }
        $res = $this->db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."societe WHERE entity IN (0,".$this->entity.") AND rowid=".(int) $socid." AND status=1");
        if (!$res || !$this->db->fetch_object($res)) { $this->db->rollback(); throw new PublicWebsiteRequestException('invalid_customer', 422); }

        $details = isset($row['request']['details']) && is_array($row['request']['details']) ? $row['request']['details'] : array();
        $restricted = !empty($details['restricted_cargo']) || (!empty($details['dangerous_goods']) && $details['dangerous_goods'] !== 'no');
        $serviceType = $row['request_type'] === 'shipping_booking' ? ($restricted ? 'shipping_inquiry' : 'shipping_booking') : ($row['request_type'] === 'catalog_order' ? 'order' : 'customer_intake');
        $serviceData = $this->operationServiceData($row, $details);
        $data = array(
            'service_type'=>$serviceType, 'title'=>$row['ref'].' - '.$row['contact_name'], 'fk_soc'=>(int) $socid,
            'fk_project'=>0, 'fk_commande'=>0, 'fk_shipment'=>0, 'fk_user_assign'=>0, 'priority'=>'normal',
            'waiting_on'=>'internal', 'next_action'=>'Review website request', 'date_deadline'=>'',
            'note_private'=>'Promoted from public website intake '.$row['ref'], 'note_public'=>'',
            'customer_visible'=>0, 'service_data'=>$serviceData,
        );
        try {
            $operations = new OperationsService($this->db);
            $operationId = $operations->create($data, (int) $userId, false);
            if (!empty($row['snapshot'])) $operations->addImmutableSection($operationId, 'public_rate', $row['snapshot'], (int) $userId);
            $sql = "UPDATE ".MAIN_DB_PREFIX."cpo_public_request SET fk_soc=".(int) $socid.",fk_operation=".(int) $operationId.",status='converted',fk_user_modif=".(int) $userId.",date_converted=".$this->q($this->now()).",date_modification=".$this->q($this->now())." WHERE entity=".$this->entity." AND rowid=".(int) $id." AND fk_operation IS NULL";
            $res = $this->db->query($sql);
            if (!$res || $this->db->affected_rows($res) !== 1) throw new RuntimeException('Concurrent request promotion.');
            $this->db->commit();
            return $operationId;
        } catch (Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    public function anonymizeExpired($userId)
    {
        $sql = "UPDATE ".MAIN_DB_PREFIX."cpo_public_request SET contact_name='Anonymized',company_name=NULL,phone=NULL,email=NULL,country_code=NULL,party_json=NULL,contact_hmac=".$this->q(hash_hmac('sha256', 'anonymized', $this->hmacKey)).",fk_user_modif=".(int) $userId.",date_anonymized=".$this->q($this->now()).",date_modification=".$this->q($this->now())." WHERE entity=".$this->entity." AND status IN ('converted','disqualified','closed') AND date_anonymized IS NULL AND date_creation<DATE_SUB(NOW(),INTERVAL ".self::RETENTION_DAYS." DAY)";
        $res = $this->db->query($sql);
        if (!$res) throw new RuntimeException($this->db->lasterror());
        return $this->db->affected_rows($res);
    }

    private function normalize(array $input)
    {
        $type = strtolower($this->clean($input['request_type'] ?? '', 32));
        if (!in_array($type, self::requestTypes(), true)) throw new PublicWebsiteRequestException('invalid_request_type', 422);
        $key = $this->clean($input['idempotency_key'] ?? '', 100);
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{15,99}$/', $key)) throw new PublicWebsiteRequestException('invalid_idempotency_key', 422);
        $contactInput = isset($input['contact']) && is_array($input['contact']) ? $input['contact'] : array();
        $contact = array(
            'name'=>$this->clean($contactInput['name'] ?? '', 160), 'company'=>$this->clean($contactInput['company'] ?? '', 190),
            'phone'=>$this->clean($contactInput['phone'] ?? '', 40), 'email'=>strtolower($this->clean($contactInput['email'] ?? '', 254)),
            'country_code'=>strtoupper($this->clean($contactInput['country_code'] ?? '', 2)),
        );
        if ($contact['name'] === '' || ($contact['phone'] === '' && !filter_var($contact['email'], FILTER_VALIDATE_EMAIL))) throw new PublicWebsiteRequestException('invalid_contact', 422);
        if ($contact['email'] !== '' && !filter_var($contact['email'], FILTER_VALIDATE_EMAIL)) throw new PublicWebsiteRequestException('invalid_contact', 422);
        $detailsInput = isset($input['details']) && is_array($input['details']) ? $input['details'] : array();
        $details = $this->normalizeDetails($type, $detailsInput);
        $normalized = array(
            'request_type'=>$type, 'idempotency_key'=>$key, 'locale'=>$this->clean($input['locale'] ?? 'ar', 12),
            'contact'=>$contact, 'parties'=>$this->normalizeParties($input['parties'] ?? array()), 'details'=>$details,
        );
        if ($type === 'shipping_booking') {
            $normalized['rate_token'] = $this->clean($input['rate_token'] ?? '', 4096);
            if ($normalized['rate_token'] === '') throw new PublicWebsiteRequestException('rate_required', 422);
        } elseif (isset($input['snapshot']) && is_array($input['snapshot'])) {
            $normalized['snapshot'] = $this->boundedStructure($input['snapshot']);
        }
        return $normalized;
    }

    private function normalizeDetails($type, array $input)
    {
        $allowed = array(
            'quote_request'=>array('service','message','product_summary','quantity','target_country'),
            'catalog_order'=>array('message','currency','lines','packing_plan','source_reference'),
            'shipping_booking'=>array('container_quantity','cargo_type','dangerous_goods','restricted_cargo','cargo_summary','gross_weight','hs_code','final_destination','required_services','terms_acknowledged_at'),
        );
        $out = array();
        foreach ($allowed[$type] as $field) {
            if (in_array($field, array('lines','packing_plan','required_services'), true)) $out[$field] = $this->boundedStructure($input[$field] ?? array());
            elseif (in_array($field, array('container_quantity','quantity'), true)) $out[$field] = max(1, min(1000000, (int) ($input[$field] ?? 1)));
            elseif (in_array($field, array('restricted_cargo'), true)) $out[$field] = !empty($input[$field]);
            else $out[$field] = $this->clean($input[$field] ?? '', $field === 'message' || $field === 'cargo_summary' ? 4000 : 255);
        }
        if ($type === 'shipping_booking') {
            $out['container_quantity'] = max(1, min(100, (int) $out['container_quantity']));
            if (!in_array($out['dangerous_goods'], array('no','yes','unknown'), true)) $out['dangerous_goods'] = 'unknown';
            if ($out['terms_acknowledged_at'] === '') throw new PublicWebsiteRequestException('terms_acknowledgement_required', 422);
        }
        return $out;
    }

    private function normalizeParties($input)
    {
        if (!is_array($input)) return array();
        $out = array();
        foreach (array('shipper','consignee','notify_party') as $key) if (isset($input[$key])) $out[$key] = $this->clean($input[$key], 1000);
        return $out;
    }

    private function operationServiceData(array $row, array $details)
    {
        $data = array('customer_contact'=>$row['contact_name'], 'contact_channel'=>'website', 'contact_links'=>'', 'customer_requirements'=>$details['message'] ?? ($details['cargo_summary'] ?? ''));
        foreach (array('cargo_summary','gross_weight','hs_code','final_destination','dangerous_goods','required_services') as $key) if (isset($details[$key])) $data[$key] = is_array($details[$key]) ? implode(', ', $details[$key]) : (string) $details[$key];
        if (!empty($row['snapshot']['route'])) {
            foreach (array('pol','pod','container_type') as $key) if (isset($row['snapshot']['route'][$key])) $data[$key === 'container_type' ? 'container_request' : $key] = (string) $row['snapshot']['route'][$key];
            $data['load_type'] = 'FCL';
        }
        return $data;
    }

    private function findByIdempotency($type, $key, $forUpdate)
    {
        $sql = "SELECT rowid,ref,status,request_hash FROM ".MAIN_DB_PREFIX."cpo_public_request WHERE entity=".$this->entity." AND integration_source=".$this->q(self::SOURCE)." AND request_type=".$this->q($type)." AND idempotency_key=".$this->q($key).($forUpdate ? ' FOR UPDATE' : '');
        $res = $this->db->query($sql);
        $obj = $res ? $this->db->fetch_object($res) : null;
        return $obj ? (array) $obj : null;
    }

    private function finishExisting(array $existing, $hash, $transactionOpen = true)
    {
        if ($transactionOpen) $this->db->commit();
        if (!hash_equals((string) $existing['request_hash'], (string) $hash)) throw new PublicWebsiteRequestException('idempotency_conflict', 409);
        return array('id'=>(int) $existing['rowid'], 'ref'=>$existing['ref'], 'status'=>$existing['status'], 'duplicate'=>true);
    }

    private function boundedStructure($value, $depth = 0)
    {
        if ($depth > 5) return null;
        if (is_array($value)) {
            $out = array(); $count = 0;
            foreach ($value as $key=>$item) { if (++$count > 100) break; $out[$this->clean($key, 80)] = $this->boundedStructure($item, $depth + 1); }
            return $out;
        }
        if (is_bool($value) || is_int($value) || is_float($value) || $value === null) return $value;
        return $this->clean($value, 2000);
    }

    /**
     * Decode current JSON and the historical double-encoded strings created by
     * the early public gateway without mutating the stored audit evidence.
     */
    private function decodeStoredJson($value)
    {
        if ($value === null || $value === '') return array();
        $decoded = is_array($value) ? $value : json_decode((string) $value, true);
        for ($depth = 0; $depth < 2 && is_string($decoded); $depth++) {
            $candidate = json_decode($decoded, true);
            if (json_last_error() !== JSON_ERROR_NONE) break;
            $decoded = $candidate;
        }
        return is_array($decoded) ? $decoded : array();
    }

    private function canonicalJson($value)
    {
        if (is_array($value)) {
            $isList = array_keys($value) === range(0, count($value) - 1);
            if (!$isList) ksort($value);
            foreach ($value as $key=>$item) $value[$key] = json_decode($this->canonicalJson($item), true);
        }
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
    }

    private function clean($value, $limit)
    {
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', trim((string) $value));
        return function_exists('mb_substr') ? mb_substr($value, 0, $limit) : substr($value, 0, $limit);
    }
    private function q($value) { return "'".$this->db->escape((string) $value)."'"; }
    private function qOrNull($value) { return $value === '' || $value === null ? 'NULL' : $this->q($value); }
    private function json($value) { return $this->q(json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); }
    private function jsonOrNull($value) { return empty($value) ? 'NULL' : $this->json($value); }
    private function now() { return $this->db->idate(dol_now()); }
}
