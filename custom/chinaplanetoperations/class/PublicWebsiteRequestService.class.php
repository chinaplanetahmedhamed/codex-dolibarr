<?php

require_once __DIR__.'/OperationsService.class.php';
require_once __DIR__.'/CatalogOrderV2Contract.class.php';
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
    const CATALOG_CONTRACT_VERSION = 2;
    const CATALOG_CONTRACT = 'cpo.catalog-order.v2';
    const CATALOG_TERMS_VERSION = '2026-08-29';

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
        $schemaVersion = (int) $normalized['schema_version'];
        $key = $normalized['idempotency_key'];
        $hash = hash_hmac('sha256', $this->canonicalJson($normalized), $this->hmacKey);

        $this->beginTransaction();
        $existing = $this->findByIdempotency($type, $key, true);
        if ($existing) return $this->finishExisting($existing, $hash);

        $snapshot = isset($normalized['snapshot']) ? $normalized['snapshot'] : array();
        if ($type === 'catalog_order' && $schemaVersion === self::CATALOG_CONTRACT_VERSION) {
            $snapshot = array(
                'schema_version'=>self::CATALOG_CONTRACT_VERSION,
                'contract'=>self::CATALOG_CONTRACT,
                'source'=>'woocommerce',
                'details'=>$normalized['details'],
                'consent'=>$normalized['consent'],
                'received_at'=>$this->now(),
            );
        }
        $fkRate = null;
        if ($type === 'shipping_booking') {
            try {
                $rates = $this->rates ?: new PublicShippingRateService($this->db, $this->entity);
                $resolved = $rates->resolveToken($normalized['rate_token']);
                $snapshot = $rates->buildCommercialSnapshot($resolved, $normalized['details']['container_quantity'], $normalized['details']['terms_acknowledged_at']);
                $fkRate = (int) $resolved['priced']['rate_id'];
            } catch (PublicShippingRateException $e) {
                $this->rollbackTransaction();
                throw new PublicWebsiteRequestException($e->getPublicCode(), $e->getHttpStatus());
            }
        }

        $contact = $normalized['contact'];
        $contactIdentity = strtolower(trim($contact['email'].'|'.$contact['phone']));
        $contactHmac = hash_hmac('sha256', $contactIdentity, $this->hmacKey);
        $ref = 'WEB-'.gmdate('Ymd-His').'-'.strtoupper(bin2hex(random_bytes(3)));
        $request = array(
            'schema_version' => $schemaVersion,
            'locale' => $normalized['locale'],
            'details' => $normalized['details'],
        );
        if (!empty($normalized['consent'])) $request['consent'] = $normalized['consent'];
        $sql = "INSERT INTO ".MAIN_DB_PREFIX."cpo_public_request(entity,ref,integration_source,request_type,idempotency_key,request_hash,contact_hmac,contact_name,company_name,phone,email,country_code,party_json,request_json,snapshot_json,schema_version,fk_rate,status,qualification_status,fk_user_creat,date_creation) VALUES(".
            $this->entity.",".$this->q($ref).",".$this->q(self::SOURCE).",".$this->q($type).",".$this->q($key).",".$this->q($hash).",".$this->q($contactHmac).",".$this->q($contact['name']).",".$this->qOrNull($contact['company']).",".$this->qOrNull($contact['phone']).",".$this->qOrNull($contact['email']).",".$this->qOrNull($contact['country_code']).",".$this->jsonOrNull($normalized['parties']).",".$this->json($request).",".$this->jsonOrNull($snapshot).",".$schemaVersion.",".($fkRate ? (int) $fkRate : 'NULL').",'new','received',".(int) $user->id.",".$this->q($this->now()).")";
        if (!$this->db->query($sql)) {
            $this->rollbackTransaction();
            $existing = $this->findByIdempotency($type, $key, false);
            if ($existing) return $this->finishExisting($existing, $hash, false);
            throw new PublicWebsiteRequestException('intake_service_unavailable', 503);
        }
        $id = (int) $this->db->last_insert_id(MAIN_DB_PREFIX.'cpo_public_request');
        try {
            if ($type === 'catalog_order' && $schemaVersion === self::CATALOG_CONTRACT_VERSION) {
                $this->storeCatalogLines($id, $normalized['details']['lines'], (int) $user->id);
                $this->storeEvent($id, 'request_received', null, 'received', array('schema_version'=>2, 'line_count'=>count($normalized['details']['lines'])), (int) $user->id);
            }
            $this->commitTransaction();
        } catch (Throwable $e) {
            $this->rollbackTransaction($e);
            throw $e;
        }
        return array('id' => $id, 'ref' => $ref, 'status' => 'new', 'duplicate' => false, 'schema_version'=>$schemaVersion);
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
        $sql = "SELECT rowid,ref,request_type,schema_version,contact_name,company_name,country_code,status,qualification_status,match_status,request_customer_visible,proposal_customer_visible,fk_soc,fk_socpeople,fk_operation,fk_propal,date_creation,date_modification FROM ".MAIN_DB_PREFIX."cpo_public_request WHERE entity=".$this->entity;
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
        $this->beginTransaction();
        $row = $this->get($id, true);
        if (!$row) { $this->rollbackTransaction(); throw new PublicWebsiteRequestException('request_not_found', 404); }
        if (!empty($row['fk_operation'])) { $this->commitTransaction(); return (int) $row['fk_operation']; }
        if ((int)($row['schema_version'] ?? 1) === self::CATALOG_CONTRACT_VERSION) {
            if ((string)($row['qualification_status'] ?? '') !== 'qualified' || empty($row['fk_soc']) || empty($row['fk_socpeople'])) { $this->rollbackTransaction(); throw new PublicWebsiteRequestException('qualification_required', 409); }
            if ((int)$row['fk_soc'] !== (int)$socid) { $this->rollbackTransaction(); throw new PublicWebsiteRequestException('qualification_link_conflict', 409); }
        }
        if (!in_array($row['status'], array('new', 'qualified', 'waiting_information'), true)) { $this->rollbackTransaction(); throw new PublicWebsiteRequestException('request_not_promotable', 409); }
        $res = $this->db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."societe WHERE entity IN (0,".$this->entity.") AND rowid=".(int) $socid." AND status=1");
        if (!$res || !$this->db->fetch_object($res)) { $this->rollbackTransaction(); throw new PublicWebsiteRequestException('invalid_customer', 422); }

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
            $this->commitTransaction();
            return $operationId;
        } catch (Throwable $e) {
            $this->rollbackTransaction($e);
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
        $schemaVersion = isset($input['schema_version']) ? (int) $input['schema_version'] : 1;
        if (!in_array($schemaVersion, array(1, self::CATALOG_CONTRACT_VERSION), true) || ($schemaVersion === self::CATALOG_CONTRACT_VERSION && $type !== 'catalog_order')) throw new PublicWebsiteRequestException('invalid_contract_version', 422);
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
        if ($schemaVersion === self::CATALOG_CONTRACT_VERSION && (strlen($contact['name']) < 2 || strlen(preg_replace('/\D+/', '', $contact['phone'])) < 6 || !filter_var($contact['email'], FILTER_VALIDATE_EMAIL) || !$this->validCountryCode($contact['country_code']))) throw new PublicWebsiteRequestException('invalid_contact', 422);
        $detailsInput = isset($input['details']) && is_array($input['details']) ? $input['details'] : array();
        $details = $this->normalizeDetails($type, $detailsInput, $schemaVersion);
        $locale = strtolower($this->clean($input['locale'] ?? 'ar', 12));
        if ($schemaVersion === self::CATALOG_CONTRACT_VERSION) {
            $locale = substr(str_replace('_', '-', $locale), 0, 2);
            if (!in_array($locale, array('ar','en','fr'), true)) throw new PublicWebsiteRequestException('invalid_locale', 422);
        }
        $normalized = array(
            'schema_version'=>$schemaVersion, 'request_type'=>$type, 'idempotency_key'=>$key, 'locale'=>$locale,
            'contact'=>$contact, 'parties'=>$this->normalizeParties($input['parties'] ?? array()), 'details'=>$details,
        );
        if ($schemaVersion === self::CATALOG_CONTRACT_VERSION) $normalized['consent'] = $this->normalizeConsent($input['consent'] ?? null);
        if ($type === 'shipping_booking') {
            $normalized['rate_token'] = $this->clean($input['rate_token'] ?? '', 4096);
            if ($normalized['rate_token'] === '') throw new PublicWebsiteRequestException('rate_required', 422);
        } elseif ($schemaVersion === 1 && isset($input['snapshot']) && is_array($input['snapshot'])) {
            $normalized['snapshot'] = $this->boundedStructure($input['snapshot']);
        }
        return $normalized;
    }

    private function normalizeDetails($type, array $input, $schemaVersion = 1)
    {
        if ($type === 'catalog_order' && (int) $schemaVersion === self::CATALOG_CONTRACT_VERSION) return $this->normalizeCatalogV2Details($input);
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

    private function normalizeConsent($input)
    {
        if (!is_array($input) || empty($input['privacy'])) throw new PublicWebsiteRequestException('privacy_consent_required', 422);
        $version = $this->clean($input['terms_version'] ?? '', 64);
        $acceptedAt = $this->clean($input['accepted_at'] ?? '', 40);
        $acceptedTimestamp = strtotime($acceptedAt);
        if ($version !== self::CATALOG_TERMS_VERSION || $acceptedAt === '' || $acceptedTimestamp === false || $acceptedTimestamp > time() + 300 || $acceptedTimestamp < time() - (30 * 86400)) throw new PublicWebsiteRequestException('invalid_consent', 422);
        return array('privacy'=>true, 'terms_version'=>$version, 'accepted_at'=>gmdate('c', $acceptedTimestamp));
    }

    private function normalizeCatalogV2Details(array $input)
    {
        if (strtolower($this->clean($input['source_reference'] ?? '', 64)) !== 'woocommerce') throw new PublicWebsiteRequestException('invalid_catalog_source', 422);
        $currency = strtoupper($this->clean($input['currency'] ?? '', 3));
        if ($currency !== '' && !preg_match('/^[A-Z]{3}$/', $currency)) throw new PublicWebsiteRequestException('invalid_currency', 422);
        $rawLines = isset($input['lines']) && is_array($input['lines']) ? $input['lines'] : array();
        if (empty($rawLines) || count($rawLines) > 100) throw new PublicWebsiteRequestException('invalid_catalog_lines', 422);
        $lines = array(); $keys = array(); $requestCartons = 0; $calculatedCartons = 0;
        foreach ($rawLines as $position=>$raw) {
            if (!is_array($raw)) throw new PublicWebsiteRequestException('invalid_catalog_line', 422);
            $line = $this->normalizeCatalogV2Line($raw, $position);
            if (isset($keys[$line['lineId']])) throw new PublicWebsiteRequestException('duplicate_catalog_line_id', 422);
            $keys[$line['lineId']] = true;
            $requestCartons += $line['cartons'];
            if ($line['calculationReady']) $calculatedCartons += $line['cartons'];
            $lines[] = $line;
        }
        if ($requestCartons > 5000) throw new PublicWebsiteRequestException('catalog_carton_limit', 422);
        return array(
            'message'=>$this->clean($input['message'] ?? '', 4000),
            'currency'=>$currency === '' ? null : $currency,
            'lines'=>$lines,
            'packing_plan'=>$this->normalizeCatalogV2Plan($input['packing_plan'] ?? null, $lines, $requestCartons, $calculatedCartons),
            'source_reference'=>'woocommerce',
        );
    }

    private function normalizeCatalogV2Line(array $input, $position)
    {
        $lineId = $this->clean($input['lineId'] ?? '', 100);
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{7,99}$/', $lineId)) throw new PublicWebsiteRequestException('invalid_catalog_line_id', 422);
        if (strtolower($this->clean($input['source'] ?? '', 32)) !== 'woocommerce') throw new PublicWebsiteRequestException('invalid_catalog_line_source', 422);
        $productId = (int) ($input['productId'] ?? 0);
        $variationId = (int) ($input['variationId'] ?? 0);
        $cartons = (int) ($input['cartons'] ?? 0);
        if ($productId < 1 || $variationId < 0 || $cartons < 1 || $cartons > 2000) throw new PublicWebsiteRequestException('invalid_catalog_line', 422);
        $units = $this->nullablePositive($input, 'unitsPerCarton', true);
        $cbm = $this->nullablePositive($input, 'cbmPerCarton', false);
        $weight = $this->nullablePositive($input, 'grossWeightKg', false);
        $complete = isset($input['packingComplete']) && $input['packingComplete'] === true;
        $missing = array();
        if ($units === null) $missing[] = 'unitsPerCarton';
        if ($cbm === null) $missing[] = 'cbmPerCarton';
        if ($weight === null) $missing[] = 'grossWeightKg';
        if (!$complete) $missing[] = 'packingComplete';
        $postedMissing = isset($input['missingFields']) && is_array($input['missingFields']) ? array_values(array_unique(array_map('strval', $input['missingFields']))) : null;
        if ($postedMissing === null || $postedMissing !== $missing) throw new PublicWebsiteRequestException('invalid_missing_fields', 422);
        $ready = empty($missing);
        if (!isset($input['calculationReady']) || ($input['calculationReady'] === true) !== $ready) throw new PublicWebsiteRequestException('invalid_calculation_readiness', 422);
        $packingSource = strtolower($this->clean($input['packingSource'] ?? '', 32));
        if (!in_array($packingSource, array('woocommerce_product','woocommerce_variation'), true)) throw new PublicWebsiteRequestException('invalid_packing_source', 422);
        return array(
            'lineId'=>$lineId, 'position'=>(int)$position, 'source'=>'woocommerce',
            'productId'=>$productId, 'variationId'=>$variationId,
            'sku'=>$this->clean($input['sku'] ?? '', 128), 'name'=>$this->clean($input['name'] ?? '', 255),
            'optionLabel'=>$this->clean($input['optionLabel'] ?? '', 255), 'image'=>$this->safeCatalogImage($input['image'] ?? ''),
            'cartons'=>$cartons, 'unitsPerCarton'=>$units, 'cbmPerCarton'=>$cbm, 'grossWeightKg'=>$weight,
            'packingComplete'=>$complete, 'packingSource'=>$packingSource,
            'calculationReady'=>$ready, 'missingFields'=>$missing,
            'price'=>$this->normalizeCatalogV2Price($input['price'] ?? null),
        );
    }

    private function normalizeCatalogV2Price($input)
    {
        if (!is_array($input) || ($input['unit'] ?? '') !== 'piece') throw new PublicWebsiteRequestException('invalid_price_snapshot', 422);
        $visibility = strtolower($this->clean($input['visibility'] ?? '', 24));
        $mode = strtolower($this->clean($input['mode'] ?? '', 24));
        if (!in_array($visibility, array('public','hidden','login_required','request_quote'), true) || !in_array($mode, array('fixed','range','request','unavailable'), true)) throw new PublicWebsiteRequestException('invalid_price_snapshot', 422);
        $currency = strtoupper($this->clean($input['currency'] ?? '', 3));
        $unitPrice = $this->nullableNonNegative($input, 'unitPrice');
        $minPrice = $this->nullableNonNegative($input, 'minUnitPrice');
        $maxPrice = $this->nullableNonNegative($input, 'maxUnitPrice');
        if ($visibility !== 'public') {
            if ($currency !== '' || $unitPrice !== null || $minPrice !== null || $maxPrice !== null || !in_array($mode, array('request','unavailable'), true)) throw new PublicWebsiteRequestException('private_price_leak', 422);
        } elseif (!preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new PublicWebsiteRequestException('invalid_price_currency', 422);
        } elseif ($mode === 'fixed' && ($unitPrice === null || $minPrice !== null || $maxPrice !== null)) {
            throw new PublicWebsiteRequestException('invalid_price_snapshot', 422);
        } elseif ($mode === 'range' && ($unitPrice !== null || $minPrice === null || $maxPrice === null || $minPrice > $maxPrice)) {
            throw new PublicWebsiteRequestException('invalid_price_snapshot', 422);
        }
        return array('visibility'=>$visibility, 'mode'=>$mode, 'unit'=>'piece', 'currency'=>$currency === '' ? null : $currency, 'unitPrice'=>$unitPrice, 'minUnitPrice'=>$minPrice, 'maxUnitPrice'=>$maxPrice);
    }

    private function normalizeCatalogV2Plan($input, array $lines, $requestCartons, $calculatedCartons)
    {
        if (!is_array($input)) throw new PublicWebsiteRequestException('invalid_packing_plan', 422);
        $container = strtolower($this->clean($input['container'] ?? 'auto', 16));
        if (!in_array($container, array('auto','20gp','40gp','40hq'), true)) throw new PublicWebsiteRequestException('invalid_packing_plan', 422);
        $available = $calculatedCartons > 0;
        if ((int)($input['requestCartons'] ?? -1) !== (int)$requestCartons || (int)($input['calculatedCartons'] ?? -1) !== (int)$calculatedCartons || !isset($input['calculationAvailable']) || ($input['calculationAvailable'] === true) !== $available) throw new PublicWebsiteRequestException('invalid_packing_totals', 422);
        $expected = array(); $ready = array();
        foreach ($lines as $line) {
            if ($line['calculationReady']) $ready[$line['lineId']] = true;
            else $expected[$line['lineId']] = $line['missingFields'];
        }
        $excluded = array();
        foreach ((array)($input['excludedItems'] ?? array()) as $item) {
            if (!is_array($item)) throw new PublicWebsiteRequestException('invalid_excluded_item', 422);
            $lineId = $this->clean($item['lineId'] ?? '', 100);
            $missing = isset($item['missingFields']) && is_array($item['missingFields']) ? array_values(array_unique(array_map('strval', $item['missingFields']))) : array();
            if (!isset($expected[$lineId]) || ($item['reason'] ?? '') !== 'packing_data_missing' || $missing !== $expected[$lineId]) throw new PublicWebsiteRequestException('invalid_excluded_item', 422);
            $excluded[$lineId] = array('lineId'=>$lineId, 'reason'=>'packing_data_missing', 'missingFields'=>$missing);
        }
        if (array_keys($excluded) !== array_keys($expected)) throw new PublicWebsiteRequestException('invalid_excluded_items', 422);
        return array('container'=>$container, 'calculationAvailable'=>$available, 'requestCartons'=>(int)$requestCartons, 'calculatedCartons'=>(int)$calculatedCartons, 'placements'=>$available ? $this->normalizeCatalogV2Placements($input['placements'] ?? array(), $ready) : array(), 'excludedItems'=>array_values($excluded));
    }

    private function normalizeCatalogV2Placements($input, array $ready)
    {
        if (!is_array($input) || count($input) > 5000) throw new PublicWebsiteRequestException('invalid_packing_placements', 422);
        $placements = array();
        foreach ($input as $item) {
            if (!is_array($item)) throw new PublicWebsiteRequestException('invalid_packing_placement', 422);
            $lineId = $this->clean($item['lineId'] ?? ($item['line_key'] ?? ''), 100);
            if (!isset($ready[$lineId])) throw new PublicWebsiteRequestException('incomplete_line_placement', 422);
            $cartonIndex = null;
            if (array_key_exists('cartonIndex', $item) && $item['cartonIndex'] !== null) {
                if (!is_numeric($item['cartonIndex']) || (float)$item['cartonIndex'] < 0 || floor((float)$item['cartonIndex']) !== (float)$item['cartonIndex']) throw new PublicWebsiteRequestException('invalid_packing_placement', 422);
                $cartonIndex = (int)$item['cartonIndex'];
            }
            $orientation = $this->clean($item['orientation'] ?? '', 8);
            $color = strtolower($this->clean($item['color'] ?? '', 7));
            if ($color !== '' && !preg_match('/^#[0-9a-f]{6}$/', $color)) throw new PublicWebsiteRequestException('invalid_packing_placement', 422);
            $placements[] = array(
                'lineId'=>$lineId, 'line_key'=>$lineId, 'cartonIndex'=>$cartonIndex,
                'x'=>$this->placementNumber($item, 'x', false), 'y'=>$this->placementNumber($item, 'y', false), 'z'=>$this->placementNumber($item, 'z', false),
                'length'=>$this->placementNumber($item, 'length', true), 'width'=>$this->placementNumber($item, 'width', true), 'height'=>$this->placementNumber($item, 'height', true),
                'orientation'=>$orientation === '' ? null : $orientation, 'color'=>$color === '' ? null : $color,
                'visualOnly'=>isset($item['visualOnly']) && $item['visualOnly'] === true,
            );
        }
        return $placements;
    }

    private function placementNumber(array $input, $key, $positive)
    {
        if (!array_key_exists($key, $input) || !is_numeric($input[$key])) throw new PublicWebsiteRequestException('invalid_packing_placement', 422);
        $value = (float)$input[$key];
        if (!is_finite($value) || $value > 100000 || ($positive ? $value <= 0 : $value < 0)) throw new PublicWebsiteRequestException('invalid_packing_placement', 422);
        return $value;
    }

    private function nullablePositive(array $input, $key, $integer)
    {
        if (!array_key_exists($key, $input) || $input[$key] === null) return null;
        if (!is_numeric($input[$key])) throw new PublicWebsiteRequestException('invalid_packing_value', 422);
        $value = $integer ? (int)$input[$key] : (float)$input[$key];
        if ($value <= 0 || $value > 1000000 || ($integer && (float)$input[$key] !== (float)$value)) throw new PublicWebsiteRequestException('invalid_packing_value', 422);
        return $value;
    }

    private function nullableNonNegative(array $input, $key)
    {
        if (!array_key_exists($key, $input) || $input[$key] === null) return null;
        if (!is_numeric($input[$key]) || (float)$input[$key] < 0 || (float)$input[$key] > 1000000000) throw new PublicWebsiteRequestException('invalid_price_snapshot', 422);
        return (float)$input[$key];
    }

    private function safeCatalogImage($value)
    {
        $url = $this->clean($value, 1000);
        if ($url === '') return null;
        if (!filter_var($url, FILTER_VALIDATE_URL)) throw new PublicWebsiteRequestException('invalid_product_image', 422);
        $parts = parse_url($url); $scheme = strtolower((string)($parts['scheme'] ?? '')); $host = strtolower((string)($parts['host'] ?? ''));
        if ($scheme !== 'https' || $host === '' || filter_var($host, FILTER_VALIDATE_IP)) throw new PublicWebsiteRequestException('invalid_product_image', 422);
        $allowed = array('chinaplanet.org','www.chinaplanet.org','cms.chinaplanet.org');
        $configured = function_exists('getDolGlobalString') ? (string)getDolGlobalString('CHINAPLANET_PUBLIC_PRODUCT_IMAGE_HOSTS') : '';
        foreach (explode(',', $configured) as $domain) if (trim($domain) !== '') $allowed[] = strtolower(trim($domain));
        foreach (array_unique($allowed) as $domain) if ($host === $domain || substr($host, -strlen('.'.$domain)) === '.'.$domain) return $url;
        throw new PublicWebsiteRequestException('invalid_product_image', 422);
    }

    private function validCountryCode($code)
    {
        if (!CatalogOrderV2Contract::isIsoAlpha2($code)) return false;
        $res = $this->db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."c_country WHERE active=1 AND UPPER(code)='".$this->db->escape((string)$code)."'");
        if (!$res) throw new RuntimeException('country_validation_failed');
        return (bool)$this->db->fetch_object($res);
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

    private function storeCatalogLines($requestId, array $lines, $userId)
    {
        foreach ($lines as $line) {
            $missing = implode(',', $line['missingFields']);
            $sql = "INSERT INTO ".MAIN_DB_PREFIX."cpo_public_request_line(entity,fk_request,line_key,position,source_product_id,source_variation_id,requested_cartons,submitted_units_per_carton,submitted_cbm_per_carton,submitted_gross_weight_kg,submitted_calculation_ready,submitted_missing_fields,price_snapshot_json,fk_user_creat,date_creation) VALUES(".
                $this->entity.",".(int)$requestId.",".$this->q($line['lineId']).",".(int)$line['position'].",".(int)$line['productId'].",".((int)$line['variationId'] > 0 ? (int)$line['variationId'] : 'NULL').",".(int)$line['cartons'].",".$this->numberOrNull($line['unitsPerCarton']).",".$this->numberOrNull($line['cbmPerCarton']).",".$this->numberOrNull($line['grossWeightKg']).",".($line['calculationReady'] ? 1 : 0).",".$this->qOrNull($missing).",".$this->json($line['price']).",".(int)$userId.",".$this->q($this->now()).")";
            if (!$this->db->query($sql)) throw new PublicWebsiteRequestException('intake_line_store_failed', 503);
        }
    }

    private function storeEvent($requestId, $type, $from, $to, array $metadata, $userId)
    {
        $sql = "INSERT INTO ".MAIN_DB_PREFIX."cpo_public_request_event(entity,fk_request,event_type,state_from,state_to,metadata_json,customer_visible,fk_user_creat,date_creation) VALUES(".$this->entity.",".(int)$requestId.",".$this->q($type).",".$this->qOrNull($from).",".$this->qOrNull($to).",".$this->json($metadata).",0,".(int)$userId.",".$this->q($this->now()).")";
        if (!$this->db->query($sql)) throw new PublicWebsiteRequestException('intake_event_store_failed', 503);
    }

    private function findByIdempotency($type, $key, $forUpdate)
    {
        $sql = "SELECT rowid,ref,status,request_hash,schema_version FROM ".MAIN_DB_PREFIX."cpo_public_request WHERE entity=".$this->entity." AND integration_source=".$this->q(self::SOURCE)." AND request_type=".$this->q($type)." AND idempotency_key=".$this->q($key).($forUpdate ? ' FOR UPDATE' : '');
        $res = $this->db->query($sql);
        $obj = $res ? $this->db->fetch_object($res) : null;
        return $obj ? (array) $obj : null;
    }

    private function finishExisting(array $existing, $hash, $transactionOpen = true)
    {
        if (!hash_equals((string) $existing['request_hash'], (string) $hash)) {
            if ($transactionOpen) $this->rollbackTransaction();
            throw new PublicWebsiteRequestException('idempotency_conflict', 409);
        }
        if ($transactionOpen) $this->commitTransaction();
        return array('id'=>(int) $existing['rowid'], 'ref'=>$existing['ref'], 'status'=>$existing['status'], 'duplicate'=>true, 'schema_version'=>isset($existing['schema_version']) ? (int)$existing['schema_version'] : 1);
    }

    private function beginTransaction()
    {
        if (!$this->db->begin()) throw new RuntimeException('intake_transaction_begin_failed');
    }

    private function commitTransaction()
    {
        if (!$this->db->commit()) throw new RuntimeException('intake_transaction_commit_failed');
    }

    private function rollbackTransaction($previous = null)
    {
        if (!$this->db->rollback()) throw new RuntimeException('intake_transaction_rollback_failed', 0, $previous instanceof Throwable ? $previous : null);
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
    private function numberOrNull($value) { return $value === null ? 'NULL' : (string)(0 + $value); }
    private function json($value) { return $this->q(json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); }
    private function jsonOrNull($value) { return empty($value) ? 'NULL' : $this->json($value); }
    private function now() { return $this->db->idate(dol_now()); }
}
