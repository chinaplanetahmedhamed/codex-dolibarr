<?php

require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';
require_once __DIR__.'/PublicWebsiteRequestService.class.php';

/** Staff-reviewed qualification and mutable line-review layer for M068. */
class PublicRequestLifecycleService
{
    private $db;
    private $entity;

    public function __construct($db, $entity = null)
    {
        $this->db = $db;
        $this->entity = $entity === null ? (int) $GLOBALS['conf']->entity : (int) $entity;
    }

    /** Exact normalized matches are suggestions only. This method never links or creates. */
    public function matchCandidates(array $request)
    {
        $email = $this->email($request['email'] ?? '');
        $phone = $this->phone($request['phone'] ?? '');
        if ($email === '' && $phone === '') return array('status'=>'none', 'candidates'=>array());
        $clauses = array();
        if ($email !== '') $clauses[] = "LOWER(TRIM(sp.email))='".$this->db->escape($email)."'";
        if ($phone !== '') foreach (array('phone','phone_perso','phone_mobile') as $phoneColumn) $clauses[] = "(sp.".$phoneColumn." IS NOT NULL AND sp.".$phoneColumn."<>'')";
        $sql = "SELECT DISTINCT s.rowid AS socid,s.nom,s.client,sp.rowid AS contact_id,sp.firstname,sp.lastname,sp.email,sp.phone,sp.phone_perso,sp.phone_mobile FROM ".MAIN_DB_PREFIX."socpeople sp INNER JOIN ".MAIN_DB_PREFIX."societe s ON s.rowid=sp.fk_soc WHERE s.entity=".$this->entity." AND s.status=1 AND sp.statut=1 AND (".implode(' OR ', $clauses).") ORDER BY s.rowid,sp.rowid";
        $res = $this->db->query($sql);
        if (!$res) throw new RuntimeException('public_request_match_failed');
        $rows = array();
        while ($obj = $this->db->fetch_object($res)) {
            $emailMatch = $email !== '' && strtolower(trim((string)$obj->email)) === $email;
            $phoneMatch = $phone !== '' && in_array($phone, array($this->phone($obj->phone),$this->phone($obj->phone_perso),$this->phone($obj->phone_mobile)), true);
            if ($emailMatch || $phoneMatch) $rows[] = (array)$obj;
            if (count($rows) >= 25) break;
        }
        $companyClauses = array();
        if ($email !== '') $companyClauses[] = "LOWER(TRIM(s.email))='".$this->db->escape($email)."'";
        if ($phone !== '') $companyClauses[] = "(s.phone IS NOT NULL AND s.phone<>'')";
        if (!empty($companyClauses)) {
            $companySql = "SELECT s.rowid AS socid,s.nom,s.client,s.email,s.phone FROM ".MAIN_DB_PREFIX."societe s WHERE s.entity=".$this->entity." AND s.status=1 AND (".implode(' OR ', $companyClauses).") ORDER BY s.rowid";
            $companyRes = $this->db->query($companySql);
            if (!$companyRes) throw new RuntimeException('public_request_match_failed');
            while ($company = $this->db->fetch_object($companyRes)) {
                if (!(($email !== '' && strtolower(trim((string)$company->email)) === $email) || ($phone !== '' && $this->phone($company->phone) === $phone))) continue;
                $merged = false;
                foreach ($rows as &$candidate) {
                    if ((int)$candidate['socid'] === (int)$company->socid) { $candidate['company_identity_match'] = true; $merged = true; break; }
                }
                unset($candidate);
                if (!$merged) $rows[] = array('socid'=>(int)$company->socid,'nom'=>(string)$company->nom,'client'=>(int)$company->client,'contact_id'=>0,'firstname'=>'','lastname'=>'','email'=>(string)$company->email,'phone'=>(string)$company->phone,'company_identity_match'=>true);
                if (count($rows) >= 25) break;
            }
        }
        return array('status'=>count($rows) === 0 ? 'none' : (count($rows) === 1 ? 'exact_single' : 'ambiguous'), 'candidates'=>$rows);
    }

    public function qualifyExisting($requestId, $socid, $contactId, $user)
    {
        return $this->withLock($requestId, function () use ($requestId, $socid, $contactId, $user) {
            $this->db->begin();
            try {
                $request = $this->request($requestId, true);
                if (!$request) throw new PublicWebsiteRequestException('request_not_found', 404);
                if (!empty($request['fk_soc']) || !empty($request['fk_socpeople'])) {
                    if ((int)$request['fk_soc'] === (int)$socid && (int)$request['fk_socpeople'] === (int)$contactId) { $this->db->commit(); return array('socid'=>(int)$socid, 'contact_id'=>(int)$contactId, 'duplicate'=>true); }
                    throw new PublicWebsiteRequestException('qualification_link_conflict', 409);
                }
                $contact = $this->activeContact($contactId, $socid);
                if (!$contact) throw new PublicWebsiteRequestException('qualification_customer_contact_invalid', 422);
                $this->linkQualified($request, $socid, $contactId, 'staff_selected', $user);
                $this->db->commit();
                return array('socid'=>(int)$socid, 'contact_id'=>(int)$contactId, 'duplicate'=>false);
            } catch (Throwable $e) { $this->db->rollback(); throw $e; }
        });
    }

    /** Creates a Prospect/Lead only after staff review and only when no exact identity exists. */
    public function createReviewedLead($requestId, $companyName, $user)
    {
        return $this->withLock($requestId, function () use ($requestId, $companyName, $user) {
            $stopRequired = false;
            $this->db->begin();
            try {
                $request = $this->request($requestId, true);
                if (!$request) throw new PublicWebsiteRequestException('request_not_found', 404);
                if (!empty($request['fk_soc']) || !empty($request['fk_socpeople'])) throw new PublicWebsiteRequestException('qualification_link_conflict', 409);
                $matches = $this->matchCandidates($request);
                if ($matches['status'] !== 'none') {
                    $status = $matches['status'] === 'ambiguous' ? 'ambiguous' : 'exact_match_requires_choice';
                    $this->setStoppedMatch($requestId, $status, (int)$user->id);
                    $this->event($requestId, 'qualification_stopped', $request['qualification_status'] ?? 'received', 'stopped', '', 'Existing normalized identity requires explicit staff choice.', array('match_status'=>$status,'candidate_count'=>count($matches['candidates'])), 0, (int)$user->id);
                    $this->db->commit();
                    $stopRequired = true;
                } else {
                    $name = $this->clean($companyName, 190);
                    if ($name === '') $name = $this->clean($request['company_name'] ?? '', 190);
                    if ($name === '') $name = $this->clean($request['contact_name'] ?? '', 160).' - '.$request['ref'];
                    $soc = new Societe($this->db);
                    $soc->name = $name;
                    $soc->client = 2;
                    $soc->code_client = 'auto';
                    $soc->status = 1;
                    $soc->email = $this->email($request['email'] ?? '');
                    $soc->phone = $this->clean($request['phone'] ?? '', 40);
                    $soc->country_code = $this->clean($request['country_code'] ?? '', 2);
                    $soc->note_private = 'Created after staff review from website request '.$request['ref'].'.';
                    $socid = $soc->create($user);
                    if ($socid <= 0) throw new RuntimeException($soc->error ?: 'qualification_lead_create_failed');

                    list($first, $last) = $this->splitName($request['contact_name'] ?? '');
                    $contact = new Contact($this->db);
                    $contact->socid = (int)$socid;
                    $contact->firstname = $first;
                    $contact->lastname = $last;
                    $contact->email = $this->email($request['email'] ?? '');
                    $contact->phone_pro = $this->clean($request['phone'] ?? '', 40);
                    $contact->statut = 1;
                    $contactId = $contact->create($user);
                    if ($contactId <= 0) throw new RuntimeException($contact->error ?: 'qualification_contact_create_failed');
                    $this->linkQualified($request, $socid, $contactId, 'reviewed_lead_created', $user);
                    $this->db->commit();
                    return array('socid'=>(int)$socid, 'contact_id'=>(int)$contactId, 'created'=>true);
                }
            } catch (Throwable $e) { $this->db->rollback(); throw $e; }
            if ($stopRequired) throw new PublicWebsiteRequestException('qualification_existing_match_requires_choice', 409);
        });
    }

    public function ensureWorkingLines($requestId, $userId)
    {
        $request = $this->request($requestId, false);
        if (!$request || (int)($request['schema_version'] ?? 1) !== 2 || $request['request_type'] !== 'catalog_order') return array();
        $payload = json_decode((string)$request['request_json'], true);
        $lines = $payload['details']['lines'] ?? array();
        foreach ($lines as $position=>$line) {
            $missing = isset($line['missingFields']) && is_array($line['missingFields']) ? implode(',', $line['missingFields']) : '';
            $price = isset($line['price']) && is_array($line['price']) ? json_encode($line['price'], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) : null;
            $sql = "INSERT IGNORE INTO ".MAIN_DB_PREFIX."cpo_public_request_line(entity,fk_request,line_key,position,source_product_id,source_variation_id,requested_cartons,submitted_units_per_carton,submitted_cbm_per_carton,submitted_gross_weight_kg,submitted_calculation_ready,submitted_missing_fields,price_snapshot_json,fk_user_creat,date_creation) VALUES(".$this->entity.",".(int)$requestId.",'".$this->db->escape((string)$line['lineId'])."',".(int)$position.",".$this->nullInt($line['productId'] ?? null).",".$this->nullInt($line['variationId'] ?? null).",".(int)$line['cartons'].",".$this->nullInt($line['unitsPerCarton'] ?? null).",".$this->nullFloat($line['cbmPerCarton'] ?? null).",".$this->nullFloat($line['grossWeightKg'] ?? null).",".(!empty($line['calculationReady']) ? 1 : 0).",'".$this->db->escape($missing)."',".$this->qOrNull($price).",".(int)$userId.",'".$this->db->idate(dol_now())."')";
            if (!$this->db->query($sql)) throw new RuntimeException('public_request_line_seed_failed');
        }
        return $this->workingLines($requestId);
    }

    public function workingLines($requestId)
    {
        $rows = array();
        $res = $this->db->query("SELECT * FROM ".MAIN_DB_PREFIX."cpo_public_request_line WHERE entity=".$this->entity." AND fk_request=".(int)$requestId." ORDER BY position,rowid");
        if (!$res) throw new RuntimeException('public_request_lines_unavailable');
        while ($obj = $this->db->fetch_object($res)) $rows[] = (array)$obj;
        return $rows;
    }

    public function reviewLine($requestId, $lineId, array $input, $user)
    {
        $this->ensureWorkingLines($requestId, (int)$user->id);
        $verified = !empty($input['packing_verified']);
        $units = $this->positiveIntOrNull($input['units_per_carton'] ?? null);
        $cbm = $this->positiveFloatOrNull($input['cbm_per_carton'] ?? null);
        $weight = $this->positiveFloatOrNull($input['gross_weight_kg'] ?? null);
        $source = strtolower($this->clean($input['verification_source'] ?? '', 32));
        $approvedSources = array('woocommerce_product','woocommerce_variation','dolibarr_product','supplier_document','staff_measurement','other_approved');
        if ($verified && ($units === null || $cbm === null || $weight === null || !in_array($source, $approvedSources, true))) throw new PublicWebsiteRequestException('packing_verification_incomplete', 422);
        $product = (int)($input['fk_product'] ?? 0);
        if ($product > 0 && !$this->saleProduct($product)) throw new PublicWebsiteRequestException('quotation_product_mapping_required', 422);
        $status = $verified && $product > 0 ? 'ready' : 'pending';
        $this->db->begin();
        try {
            $sql = "UPDATE ".MAIN_DB_PREFIX."cpo_public_request_line SET fk_product=".($product > 0 ? $product : 'NULL').",verified_units_per_carton=".$this->nullInt($units).",verified_cbm_per_carton=".$this->nullFloat($cbm).",verified_gross_weight_kg=".$this->nullFloat($weight).",verified_packing_source=".$this->qOrNull($verified ? $source : null).",packing_verified=".($verified ? 1 : 0).",review_status='".$status."',staff_note=".$this->qOrNull($this->clean($input['staff_note'] ?? '', 1000)).",fk_user_modif=".(int)$user->id.",date_modification='".$this->db->idate(dol_now())."' WHERE entity=".$this->entity." AND fk_request=".(int)$requestId." AND rowid=".(int)$lineId;
            $res = $this->db->query($sql);
            if (!$res || $this->db->affected_rows($res) !== 1) throw new PublicWebsiteRequestException('public_request_line_not_found', 404);
            $this->event($requestId, 'line_reviewed', null, null, '', 'Working line '.(int)$lineId.' reviewed', array('line_id'=>(int)$lineId,'ready'=>$status === 'ready','verification_source'=>$verified ? $source : null), 0, (int)$user->id);
            $this->db->commit();
        } catch (Throwable $e) { $this->db->rollback(); throw $e; }
    }

    public function setVisibility($requestId, $requestVisible, $proposalVisible, $user)
    {
        $request = $this->request($requestId, false);
        if (!$request || empty($request['fk_soc'])) throw new PublicWebsiteRequestException('qualification_required', 409);
        if ($proposalVisible && empty($request['fk_propal'])) throw new PublicWebsiteRequestException('quotation_required', 409);
        // A proposal is reached through its website-request record. Publishing the
        // proposal therefore always publishes that customer-safe request shell too.
        $requestVisible = (bool) $requestVisible || (bool) $proposalVisible;
        $now = $requestVisible ? "'".$this->db->idate(dol_now())."'" : 'NULL';
        $this->db->begin();
        try {
            $sql = "UPDATE ".MAIN_DB_PREFIX."cpo_public_request SET request_customer_visible=".($requestVisible ? 1 : 0).",proposal_customer_visible=".($proposalVisible ? 1 : 0).",date_customer_visible=".$now.",fk_user_modif=".(int)$user->id.",date_modification='".$this->db->idate(dol_now())."' WHERE entity=".$this->entity." AND rowid=".(int)$requestId." AND fk_soc=".(int)$request['fk_soc'];
            $res = $this->db->query($sql);
            if (!$res || $this->db->affected_rows($res) !== 1) throw new RuntimeException('public_request_visibility_failed');
            $this->event($requestId, 'visibility_changed', null, null, '', '', array('request_visible'=>(bool)$requestVisible,'proposal_visible'=>(bool)$proposalVisible), 0, (int)$user->id);
            $this->db->commit();
        } catch (Throwable $e) { $this->db->rollback(); throw $e; }
    }

    public function publicEvents($requestId, $socid)
    {
        $sql = "SELECT e.event_type,e.note_public,e.date_creation FROM ".MAIN_DB_PREFIX."cpo_public_request_event e INNER JOIN ".MAIN_DB_PREFIX."cpo_public_request r ON r.rowid=e.fk_request AND r.entity=e.entity WHERE e.entity=".$this->entity." AND e.fk_request=".(int)$requestId." AND r.fk_soc=".(int)$socid." AND r.request_customer_visible=1 AND e.customer_visible=1 ORDER BY e.date_creation,e.rowid";
        $res = $this->db->query($sql); $rows = array();
        if ($res) while ($obj = $this->db->fetch_object($res)) $rows[] = (array)$obj;
        return $rows;
    }

    public function event($requestId, $type, $from, $to, $public, $private, array $metadata, $visible, $userId)
    {
        $sql = "INSERT INTO ".MAIN_DB_PREFIX."cpo_public_request_event(entity,fk_request,event_type,state_from,state_to,note_public,note_private,metadata_json,customer_visible,fk_user_creat,date_creation) VALUES(".$this->entity.",".(int)$requestId.",'".$this->db->escape($this->clean($type,64))."',".$this->qOrNull($from).",".$this->qOrNull($to).",".$this->qOrNull($this->clean($public,10000)).",".$this->qOrNull($this->clean($private,10000)).",".$this->qOrNull(json_encode($metadata, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)).",".($visible ? 1 : 0).",".(int)$userId.",'".$this->db->idate(dol_now())."')";
        if (!$this->db->query($sql)) throw new RuntimeException('public_request_audit_failed');
    }

    private function linkQualified(array $request, $socid, $contactId, $match, $user)
    {
        $sql = "UPDATE ".MAIN_DB_PREFIX."cpo_public_request SET fk_soc=".(int)$socid.",fk_socpeople=".(int)$contactId.",status='qualified',qualification_status='qualified',match_status='".$this->db->escape($match)."',fk_user_modif=".(int)$user->id.",date_qualified='".$this->db->idate(dol_now())."',date_modification='".$this->db->idate(dol_now())."' WHERE entity=".$this->entity." AND rowid=".(int)$request['rowid']." AND fk_soc IS NULL AND fk_socpeople IS NULL";
        $res = $this->db->query($sql);
        if (!$res || $this->db->affected_rows($res) !== 1) throw new PublicWebsiteRequestException('qualification_link_conflict', 409);
        $this->event((int)$request['rowid'], 'qualified', $request['qualification_status'] ?? 'received', 'qualified', '', 'Staff reviewed identity and linked the request.', array('match_method'=>$match,'socid'=>(int)$socid,'contact_id'=>(int)$contactId), 0, (int)$user->id);
        $this->ensureWorkingLines((int)$request['rowid'], (int)$user->id);
    }

    private function setStoppedMatch($requestId, $status, $userId)
    {
        $res = $this->db->query("UPDATE ".MAIN_DB_PREFIX."cpo_public_request SET qualification_status='stopped',match_status='".$this->db->escape($status)."',fk_user_modif=".(int)$userId.",date_modification='".$this->db->idate(dol_now())."' WHERE entity=".$this->entity." AND rowid=".(int)$requestId." AND fk_soc IS NULL AND fk_socpeople IS NULL");
        if (!$res || $this->db->affected_rows($res) !== 1) throw new RuntimeException('qualification_stop_persist_failed');
    }

    private function request($id, $lock)
    {
        $res = $this->db->query("SELECT * FROM ".MAIN_DB_PREFIX."cpo_public_request WHERE entity=".$this->entity." AND rowid=".(int)$id.($lock ? ' FOR UPDATE' : ''));
        $obj = $res ? $this->db->fetch_object($res) : null;
        return $obj ? (array)$obj : null;
    }
    private function activeContact($id, $socid) { $res=$this->db->query("SELECT sp.rowid FROM ".MAIN_DB_PREFIX."socpeople sp INNER JOIN ".MAIN_DB_PREFIX."societe s ON s.rowid=sp.fk_soc WHERE s.entity=".$this->entity." AND s.status=1 AND sp.statut=1 AND sp.rowid=".(int)$id." AND sp.fk_soc=".(int)$socid); return $res && $this->db->fetch_object($res); }
    private function saleProduct($id) { $res=$this->db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."product WHERE rowid=".(int)$id." AND entity IN (".getEntity('product').") AND tosell=1"); return $res && $this->db->fetch_object($res); }
    private function email($value) { $v=strtolower(trim((string)$value)); return filter_var($v,FILTER_VALIDATE_EMAIL) ? $v : ''; }
    private function phone($value) { return preg_replace('/\D+/', '', (string)$value); }
    private function clean($value,$max) { $v=trim(strip_tags((string)$value)); return function_exists('dol_trunc') ? dol_trunc($v,$max,'right','UTF-8',1) : substr($v,0,$max); }
    private function splitName($value) { $parts=preg_split('/\s+/u',trim((string)$value),2); return count($parts)>1 ? array($parts[0],$parts[1]) : array('',($parts[0] ?? 'Website contact')); }
    private function positiveIntOrNull($v) { if ($v==='' || $v===null) return null; $v=(int)$v; if ($v<1 || $v>1000000) throw new PublicWebsiteRequestException('packing_value_invalid',422); return $v; }
    private function positiveFloatOrNull($v) { if ($v==='' || $v===null) return null; if (!is_numeric($v) || (float)$v<=0 || (float)$v>1000000) throw new PublicWebsiteRequestException('packing_value_invalid',422); return (float)$v; }
    private function nullInt($v) { return $v===null || $v==='' ? 'NULL' : (string)(int)$v; }
    private function nullFloat($v) { return $v===null || $v==='' ? 'NULL' : (string)(float)$v; }
    private function qOrNull($v) { return $v===null || $v==='' ? 'NULL' : "'".$this->db->escape((string)$v)."'"; }
    private function withLock($id, $callback) { $name='cpo_qualify_'.$this->entity.'_'.(int)$id; $res=$this->db->query("SELECT GET_LOCK('".$this->db->escape($name)."',5) AS ok"); $obj=$res?$this->db->fetch_object($res):null; if (!$obj || (int)$obj->ok!==1) throw new PublicWebsiteRequestException('qualification_busy',409); try { return $callback(); } finally { $this->db->query("SELECT RELEASE_LOCK('".$this->db->escape($name)."')"); } }
}
