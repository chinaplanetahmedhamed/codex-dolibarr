<?php

require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';
require_once __DIR__.'/PublicWebsiteRequestService.class.php';

/** Entity-scoped, staff-controlled qualification for cpo.catalog-order.v2 requests. */
class PublicRequestLifecycleService
{
    private $db;
    private $entity;

    public function __construct($db, $entity = null)
    {
        $this->db = $db;
        $this->entity = $entity === null ? (int)$GLOBALS['conf']->entity : (int)$entity;
    }

    public function matchCandidates($requestId)
    {
        $request = $this->request($requestId, false);
        if (!$request) throw new PublicWebsiteRequestException('request_not_found', 404);
        $email = strtolower(trim((string)$request['email']));
        $phone = $this->phone($request['phone']);
        if ($email === '' && $phone === '') return array('status'=>'none', 'candidates'=>array());

        $conditions = array();
        if ($email !== '') {
            $quotedEmail = "'".$this->db->escape($email)."'";
            $conditions[] = "LOWER(TRIM(COALESCE(sp.email,'')))=".$quotedEmail;
            $conditions[] = "LOWER(TRIM(COALESCE(s.email,'')))=".$quotedEmail;
        }
        if ($phone !== '') {
            $quotedPhone = "'".$this->db->escape($phone)."'";
            foreach (array('sp.phone','sp.phone_perso','sp.phone_mobile','s.phone') as $column) $conditions[] = $this->normalizedPhoneSql($column).'='.$quotedPhone;
        }
        $sql = "SELECT DISTINCT s.rowid AS socid,s.nom,s.client,s.email AS company_email,s.phone AS company_phone,sp.rowid AS contact_id,sp.firstname,sp.lastname,sp.email,sp.phone,sp.phone_perso,sp.phone_mobile";
        $sql .= " FROM ".MAIN_DB_PREFIX."societe s LEFT JOIN ".MAIN_DB_PREFIX."socpeople sp ON sp.fk_soc=s.rowid AND sp.statut=1";
        $sql .= " WHERE s.entity=".$this->entity." AND s.status=1 AND (".implode(' OR ', $conditions).")";
        $sql .= " ORDER BY s.rowid,sp.rowid";
        $res = $this->db->query($sql);
        if (!$res) throw new RuntimeException('public_request_match_failed');
        $allCandidates = array(); $seen = array();
        while ($obj = $this->db->fetch_object($res)) {
            $emailMatch = $email !== '' && in_array($email, array(strtolower(trim((string)$obj->email)), strtolower(trim((string)$obj->company_email))), true);
            $phoneMatch = $phone !== '' && in_array($phone, array($this->phone($obj->phone), $this->phone($obj->phone_perso), $this->phone($obj->phone_mobile), $this->phone($obj->company_phone)), true);
            if (!$emailMatch && !$phoneMatch) continue;
            $candidateKey = (int)$obj->socid.':'.(int)$obj->contact_id;
            if (isset($seen[$candidateKey])) continue;
            $seen[$candidateKey] = true;
            $allCandidates[] = array('socid'=>(int)$obj->socid, 'contact_id'=>(int)$obj->contact_id, 'company'=>(string)$obj->nom, 'contact'=>trim($obj->firstname.' '.$obj->lastname), 'email'=>(string)$obj->email);
        }
        $total = count($allCandidates);
        return array('status'=>$total === 0 ? 'none' : ($total === 1 ? 'exact_single' : 'ambiguous'), 'candidates'=>array_slice($allCandidates, 0, 25), 'candidate_count'=>$total, 'truncated'=>$total > 25);
    }

    public function qualifyExisting($requestId, $socid, $contactId, $user)
    {
        return $this->withLock($requestId, function () use ($requestId, $socid, $contactId, $user) {
            $this->beginTransaction();
            try {
                $request = $this->request($requestId, true);
                if (!$request) throw new PublicWebsiteRequestException('request_not_found', 404);
                if (!empty($request['fk_soc']) || !empty($request['fk_socpeople'])) {
                    if ((int)$request['fk_soc'] === (int)$socid && (int)$request['fk_socpeople'] === (int)$contactId) { $this->commitTransaction(); return array('socid'=>(int)$socid, 'contact_id'=>(int)$contactId, 'duplicate'=>true); }
                    throw new PublicWebsiteRequestException('qualification_link_conflict', 409);
                }
                if (!$this->activeContact($contactId, $socid)) throw new PublicWebsiteRequestException('qualification_customer_contact_invalid', 422);
                $this->linkQualified($request, $socid, $contactId, 'staff_selected', $user);
                $this->commitTransaction();
                return array('socid'=>(int)$socid, 'contact_id'=>(int)$contactId, 'duplicate'=>false);
            } catch (Throwable $e) { $this->rollbackTransaction($e); throw $e; }
        });
    }

    /** Create a Prospect/Lead only after a staff action and only when no exact identity exists. */
    public function createReviewedLead($requestId, $companyName, $user)
    {
        return $this->withLock($requestId, function () use ($requestId, $companyName, $user) {
            $this->beginTransaction();
            try {
                $request = $this->request($requestId, true);
                if (!$request) throw new PublicWebsiteRequestException('request_not_found', 404);
                if (!empty($request['fk_soc']) || !empty($request['fk_socpeople'])) throw new PublicWebsiteRequestException('qualification_link_conflict', 409);
                $matches = $this->matchCandidates($requestId);
                if ($matches['status'] !== 'none') {
                    $status = $matches['status'] === 'ambiguous' ? 'ambiguous' : 'exact_match_requires_choice';
                    $stopped = $this->db->query("UPDATE ".MAIN_DB_PREFIX."cpo_public_request SET qualification_status='stopped',match_status='".$this->db->escape($status)."',fk_user_modif=".(int)$user->id.",date_modification='".$this->db->idate(dol_now())."' WHERE entity=".$this->entity." AND rowid=".(int)$requestId." AND fk_soc IS NULL");
                    if (!$stopped || $this->db->affected_rows($stopped) !== 1) throw new RuntimeException('qualification_stop_store_failed');
                    $this->event($requestId, 'qualification_stopped', 'received', 'stopped', array('match_status'=>$status, 'candidate_count'=>(int)($matches['candidate_count'] ?? count($matches['candidates'])), 'candidate_list_truncated'=>!empty($matches['truncated'])), (int)$user->id);
                    $this->commitTransaction();
                    throw new PublicWebsiteRequestException('qualification_existing_match_requires_choice', 409);
                }
                $name = $this->clean($companyName, 190) ?: ($this->clean($request['company_name'], 190) ?: $this->clean($request['contact_name'], 160).' - '.$request['ref']);
                $soc = new Societe($this->db);
                $soc->name = $name;
                $soc->client = 2;
                $soc->code_client = 'auto';
                $soc->status = 1;
                $soc->email = filter_var($request['email'], FILTER_VALIDATE_EMAIL) ? strtolower($request['email']) : '';
                $soc->phone = $this->clean($request['phone'], 40);
                $soc->country_code = $this->clean($request['country_code'], 2);
                $soc->note_private = 'Created after staff review from website request '.$request['ref'].'.';
                $socid = $soc->create($user);
                if ($socid <= 0) throw new RuntimeException($soc->error ?: 'qualification_lead_create_failed');

                list($first, $last) = $this->splitName($request['contact_name']);
                $contact = new Contact($this->db);
                $contact->socid = (int)$socid;
                $contact->firstname = $first;
                $contact->lastname = $last;
                $contact->email = $soc->email;
                $contact->phone_pro = $soc->phone;
                $contact->statut = 1;
                $contactId = $contact->create($user);
                if ($contactId <= 0) throw new RuntimeException($contact->error ?: 'qualification_contact_create_failed');
                $this->linkQualified($request, $socid, $contactId, 'reviewed_lead_created', $user);
                $this->commitTransaction();
                return array('socid'=>(int)$socid, 'contact_id'=>(int)$contactId, 'created'=>true);
            } catch (Throwable $e) {
                if (!($e instanceof PublicWebsiteRequestException && $e->getPublicCode() === 'qualification_existing_match_requires_choice')) $this->rollbackTransaction($e);
                throw $e;
            }
        });
    }

    public function setVisibility($requestId, $requestVisible, $proposalVisible, $user)
    {
        return $this->withLock($requestId, function () use ($requestId, $requestVisible, $proposalVisible, $user) {
            $this->beginTransaction();
            try {
                $request = $this->request($requestId, true);
                if (!$request || empty($request['fk_soc']) || empty($request['fk_socpeople']) || (string)$request['qualification_status'] !== 'qualified') throw new PublicWebsiteRequestException('qualification_required', 409);
                if ($proposalVisible && (empty($request['fk_propal']) || !$this->approvedProposal((int)$request['fk_propal'], (int)$request['fk_soc']))) throw new PublicWebsiteRequestException('quotation_not_approved', 409);
                $requestVisible = (bool)$requestVisible || (bool)$proposalVisible;
                $sql = "UPDATE ".MAIN_DB_PREFIX."cpo_public_request SET request_customer_visible=".($requestVisible ? 1 : 0).",proposal_customer_visible=".($proposalVisible ? 1 : 0).",date_customer_visible=".($requestVisible ? "'".$this->db->idate(dol_now())."'" : 'NULL').",fk_user_modif=".(int)$user->id.",date_modification='".$this->db->idate(dol_now())."' WHERE entity=".$this->entity." AND rowid=".(int)$requestId." AND fk_soc=".(int)$request['fk_soc']." AND fk_socpeople=".(int)$request['fk_socpeople'];
                $res = $this->db->query($sql);
                if (!$res || $this->db->affected_rows($res) !== 1) throw new RuntimeException('public_request_visibility_failed');
                $this->event($requestId, 'customer_visibility_changed', $request['status'], $request['status'], array('request_visible'=>$requestVisible, 'proposal_visible'=>(bool)$proposalVisible), (int)$user->id);
                $this->commitTransaction();
                return array('request_visible'=>$requestVisible, 'proposal_visible'=>(bool)$proposalVisible);
            } catch (Throwable $e) {
                $this->rollbackTransaction($e);
                throw $e;
            }
        });
    }

    private function linkQualified(array $request, $socid, $contactId, $match, $user)
    {
        $sql = "UPDATE ".MAIN_DB_PREFIX."cpo_public_request SET fk_soc=".(int)$socid.",fk_socpeople=".(int)$contactId.",status='qualified',qualification_status='qualified',match_status='".$this->db->escape($match)."',fk_user_modif=".(int)$user->id.",date_qualified='".$this->db->idate(dol_now())."',date_modification='".$this->db->idate(dol_now())."' WHERE entity=".$this->entity." AND rowid=".(int)$request['rowid']." AND fk_soc IS NULL AND fk_socpeople IS NULL";
        $res = $this->db->query($sql);
        if (!$res || $this->db->affected_rows($res) !== 1) throw new PublicWebsiteRequestException('qualification_link_conflict', 409);
        $this->event((int)$request['rowid'], 'qualified', $request['qualification_status'] ?? 'received', 'qualified', array('match_method'=>$match, 'socid'=>(int)$socid, 'contact_id'=>(int)$contactId), (int)$user->id);
    }

    private function event($requestId, $type, $from, $to, array $metadata, $userId)
    {
        $sql = "INSERT INTO ".MAIN_DB_PREFIX."cpo_public_request_event(entity,fk_request,event_type,state_from,state_to,metadata_json,customer_visible,fk_user_creat,date_creation) VALUES(".$this->entity.",".(int)$requestId.",'".$this->db->escape($type)."',".$this->qOrNull($from).",".$this->qOrNull($to).",".$this->qOrNull(json_encode($metadata, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)).",0,".(int)$userId.",'".$this->db->idate(dol_now())."')";
        if (!$this->db->query($sql)) throw new RuntimeException('public_request_audit_failed');
    }

    private function request($id, $lock) { $res=$this->db->query("SELECT * FROM ".MAIN_DB_PREFIX."cpo_public_request WHERE entity=".$this->entity." AND rowid=".(int)$id.($lock ? ' FOR UPDATE' : '')); $obj=$res?$this->db->fetch_object($res):null; return $obj?(array)$obj:null; }
    private function activeContact($id, $socid) { $res=$this->db->query("SELECT sp.rowid FROM ".MAIN_DB_PREFIX."socpeople sp INNER JOIN ".MAIN_DB_PREFIX."societe s ON s.rowid=sp.fk_soc WHERE s.entity=".$this->entity." AND s.status=1 AND sp.statut=1 AND sp.rowid=".(int)$id." AND sp.fk_soc=".(int)$socid); return $res && $this->db->fetch_object($res); }
    private function approvedProposal($id, $socid) { $res=$this->db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."propal WHERE entity=".$this->entity." AND rowid=".(int)$id." AND fk_soc=".(int)$socid." AND fk_statut IN (1,2,4)"); return $res && $this->db->fetch_object($res); }
    private function normalizedPhoneSql($column) { $sql="COALESCE(".$column.",'')"; foreach (array(' ','+','-','(',')','.','/') as $character) $sql="REPLACE(".$sql.",'".$this->db->escape($character)."','')"; return $sql; }
    private function phone($value) { return preg_replace('/\D+/', '', (string)$value); }
    private function clean($value, $max) { $v=trim(strip_tags((string)$value)); return function_exists('mb_substr') ? mb_substr($v,0,$max) : substr($v,0,$max); }
    private function splitName($value) { $parts=preg_split('/\s+/u',trim((string)$value),2); return count($parts)>1 ? array($parts[0],$parts[1]) : array('',($parts[0] ?? 'Website contact')); }
    private function qOrNull($value) { return $value === null || $value === '' ? 'NULL' : "'".$this->db->escape((string)$value)."'"; }
    private function beginTransaction() { if (!$this->db->begin()) throw new RuntimeException('qualification_transaction_begin_failed'); }
    private function commitTransaction() { if (!$this->db->commit()) throw new RuntimeException('qualification_transaction_commit_failed'); }
    private function rollbackTransaction($previous = null) { if (!$this->db->rollback()) throw new RuntimeException('qualification_transaction_rollback_failed', 0, $previous instanceof Throwable ? $previous : null); }
    private function withLock($id, $callback)
    {
        $name = 'cpo_qualify_'.$this->entity.'_'.(int)$id;
        $res = $this->db->query("SELECT GET_LOCK('".$this->db->escape($name)."',5) AS ok");
        $obj = $res ? $this->db->fetch_object($res) : null;
        if (!$obj || (int)$obj->ok !== 1) throw new PublicWebsiteRequestException('qualification_busy', 409);

        $result = null;
        $failure = null;
        try {
            $result = $callback();
        } catch (Throwable $e) {
            $failure = $e;
        }
        $release = $this->db->query("SELECT RELEASE_LOCK('".$this->db->escape($name)."') AS released");
        $released = $release ? $this->db->fetch_object($release) : null;
        if (!$released || (int)$released->released !== 1) {
            dol_syslog('M071 qualification advisory lock release failed', LOG_ERR);
            throw new RuntimeException('qualification_lock_release_failed', 0, $failure);
        }
        if ($failure) throw $failure;
        return $result;
    }
}
