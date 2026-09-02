<?php

require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/CMailFile.class.php';

class PortalInvitationException extends RuntimeException {}

/** Hashed, expiring, one-time Customer Center activation invitations. */
class PortalInvitationService
{
    const TTL_SECONDS = 86400;
    const ISSUE_LIMIT = 3;
    const ISSUE_WINDOW_SECONDS = 3600;
    const ATTEMPT_LIMIT = 5;
    const ATTEMPT_WINDOW_SECONDS = 900;
    private $db;
    private $entity;
    private $hmacKey;

    public function __construct($db, $entity = null, $hmacKey = null)
    {
        $this->db = $db;
        $this->entity = $entity === null ? (int) $GLOBALS['conf']->entity : (int) $entity;
        $key = $hmacKey === null ? (string) getenv('CHINAPLANET_PORTAL_INVITATION_HMAC_KEY') : (string) $hmacKey;
        if ($hmacKey === null && strlen($key) < 32 && function_exists('getDolGlobalString')) $key = (string) getDolGlobalString('CHINAPLANET_PORTAL_INVITATION_HMAC_KEY');
        if (strlen($key) < 32) throw new PortalInvitationException('portal_invitation_not_configured');
        $this->hmacKey = $key;
    }

    /** Staff-only. The plaintext token exists only in this call and the outgoing mail. */
    public function issue($requestId, $staffUser, $sendMail = true)
    {
        $request = $this->qualifiedRequest($requestId);
        if (!$request) throw new PortalInvitationException('portal_invitation_qualification_required');
        $contact = new Contact($this->db);
        if ($contact->fetch((int) $request['fk_socpeople']) <= 0) throw new PortalInvitationException('portal_invitation_contact_invalid');
        $email = strtolower(trim((string) $contact->email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new PortalInvitationException('portal_invitation_email_invalid');
        $lock = 'cpcc_invite_'.substr(hash_hmac('sha256', $this->entity.'|'.$email, $this->hmacKey), 0, 32);
        $this->acquireLock($lock);
        try {
            return $this->issueUnlocked($requestId, $staffUser, $sendMail, true, 0);
        } finally {
            $this->releaseLock($lock);
        }
    }

    /**
     * Anonymous-safe password reset. Unknown or ambiguous addresses return false;
     * callers must always render the same public response to prevent enumeration.
     */
    public function requestPasswordReset($email, $sendMail = true)
    {
        $email = strtolower(trim((string) $email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return false;
        $escaped = $this->db->escape($email);
        $sql = "SELECT u.rowid AS user_id,u.fk_soc FROM ".MAIN_DB_PREFIX."user u INNER JOIN ".MAIN_DB_PREFIX."societe s ON s.rowid=u.fk_soc AND s.entity=u.entity AND s.status=1";
        $sql .= " WHERE u.entity=".$this->entity." AND u.admin=0 AND u.statut=1 AND LOWER(TRIM(u.email))='".$escaped."' ORDER BY u.rowid LIMIT 2";
        $res = $this->db->query($sql);
        if (!$res) throw new PortalInvitationException('portal_reset_lookup_failed');
        $rows = array();
        while ($obj = $this->db->fetch_object($res)) $rows[] = (array) $obj;
        if (count($rows) !== 1) return false;
        $requestSql = "SELECT r.rowid AS request_id FROM ".MAIN_DB_PREFIX."cpo_public_request r INNER JOIN ".MAIN_DB_PREFIX."socpeople sp ON sp.rowid=r.fk_socpeople AND sp.fk_soc=r.fk_soc AND sp.statut=1";
        $requestSql .= " WHERE r.entity=".$this->entity." AND r.fk_soc=".(int)$rows[0]['fk_soc']." AND r.qualification_status='qualified' AND LOWER(TRIM(sp.email))='".$escaped."' ORDER BY r.rowid DESC LIMIT 1";
        $requestRes = $this->db->query($requestSql);
        $requestRow = $requestRes ? $this->db->fetch_object($requestRes) : null;
        if (!$requestRes) throw new PortalInvitationException('portal_reset_lookup_failed');
        if (!$requestRow) return false;
        $portal = new User($this->db);
        $portalUserId = (int) $rows[0]['user_id'];
        if ($portal->fetch($portalUserId) <= 0 || (int) $portal->entity !== $this->entity || (int)$portal->socid !== (int)$rows[0]['fk_soc'] || strtolower(trim((string) $portal->email)) !== $email || !$this->hasCurrentPortalAccess($portalUserId, (int)$portal->socid)) return false;
        $lock = 'cpcc_invite_'.substr(hash_hmac('sha256', $this->entity.'|'.$email, $this->hmacKey), 0, 32);
        $this->acquireLock($lock);
        try {
            // Recheck access inside the serialized issuance path. Password reset
            // never provisions or restores a revoked portal membership/right.
            if (!$this->hasCurrentPortalAccess($portalUserId, (int)$portal->socid)) return false;
            $this->issueUnlocked((int) $requestRow->request_id, $portal, $sendMail, false, $portalUserId);
            return true;
        } finally {
            $this->releaseLock($lock);
        }
    }

    private function issueUnlocked($requestId, $staffUser, $sendMail, $allowProvision, $expectedUserId)
    {
        $request = $this->qualifiedRequest($requestId);
        if (!$request) throw new PortalInvitationException('portal_invitation_qualification_required');
        $contact = new Contact($this->db);
        if ($contact->fetch((int) $request['fk_socpeople']) <= 0 || (int) $contact->socid !== (int) $request['fk_soc'] || empty($contact->statut)) throw new PortalInvitationException('portal_invitation_contact_invalid');
        $email = strtolower(trim((string) $contact->email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new PortalInvitationException('portal_invitation_email_invalid');
        $emailHmac = hash_hmac('sha256', $email, $this->hmacKey);
        $this->enforceIssueLimit((int) $requestId, $emailHmac);

        $existing = $this->emailOrLoginUsers($email);
        $userId = 0;
        $createdUserId = 0;
        foreach ($existing as $candidate) {
            if ((int) $candidate['fk_soc'] !== (int) $request['fk_soc']) throw new PortalInvitationException('portal_invitation_email_conflict');
            if (strtolower(trim((string) $candidate['email'])) !== $email) throw new PortalInvitationException('portal_invitation_login_conflict');
            if ($userId && $userId !== (int) $candidate['rowid']) throw new PortalInvitationException('portal_invitation_email_ambiguous');
            $userId = (int) $candidate['rowid'];
        }
        if ((int)$expectedUserId > 0 && $userId !== (int)$expectedUserId) throw new PortalInvitationException('portal_reset_user_changed');
        if (!$userId) {
            if (!$allowProvision) throw new PortalInvitationException('portal_reset_user_changed');
            $portal = new User($this->db);
            // Dolibarr 23 authenticates an address containing @ against user.email.
            // Keep the framework's default prohibition on @ in login and use a
            // collision-resistant internal login that is never customer-facing.
            $login = 'cp-'.substr(hash_hmac('sha256', $this->entity.'|'.$email, $this->hmacKey), 0, 24);
            $loginCheck = $this->db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."user WHERE entity=".$this->entity." AND LOWER(login)='".$this->db->escape($login)."' LIMIT 1");
            if (!$loginCheck || $this->db->fetch_object($loginCheck)) throw new PortalInvitationException('portal_invitation_login_conflict');
            $userId = $portal->create_from_contact($contact, $login, bin2hex(random_bytes(32)));
            if ($userId <= 0) throw new PortalInvitationException($portal->error ?: 'portal_user_create_failed');
            $createdUserId = (int) $userId;
            // create_from_contact requires an initial password. Immediately remove every
            // credential representation and keep the account disabled until acceptance.
            $clear = "UPDATE ".MAIN_DB_PREFIX."user SET pass=NULL,pass_crypted=NULL,pass_temp=NULL,statut=0 WHERE entity=".$this->entity." AND rowid=".(int) $userId." AND fk_soc=".(int) $request['fk_soc']." AND LOWER(login)='".$this->db->escape($login)."'";
            $clearResult = $this->db->query($clear);
            if (!$clearResult || $this->db->affected_rows($clearResult) !== 1) {
                $this->rollbackCreatedUser($createdUserId, (int)$request['fk_soc'], $staffUser);
                throw new PortalInvitationException('portal_user_credentials_clear_failed');
            }
            $verify = $this->db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."user WHERE entity=".$this->entity." AND rowid=".(int) $userId." AND statut=0 AND pass IS NULL AND pass_crypted IS NULL AND pass_temp IS NULL");
            if (!$verify || !$this->db->fetch_object($verify)) {
                $this->rollbackCreatedUser($createdUserId, (int)$request['fk_soc'], $staffUser);
                throw new PortalInvitationException('portal_user_credentials_clear_failed');
            }
        }

        $token = $this->base64url(random_bytes(32));
        $hash = hash('sha256', $token);
        $expires = $this->db->idate(dol_now() + self::TTL_SECONDS);
        $now = $this->db->idate(dol_now());
        $this->db->begin();
        try {
            if ($allowProvision) $this->provisionPortalGroup($userId);
            elseif (!$this->hasCurrentPortalAccess($userId, (int)$request['fk_soc'])) throw new PortalInvitationException('portal_reset_access_revoked');
            if (!$this->db->query("UPDATE ".MAIN_DB_PREFIX."cpcc_portal_invitation SET status='revoked',revoked_at='".$now."',fk_user_revoke=".(int) $staffUser->id." WHERE entity=".$this->entity." AND fk_request=".(int) $requestId." AND status='pending'")) throw new PortalInvitationException('portal_invitation_revoke_failed');
            $sql = "INSERT INTO ".MAIN_DB_PREFIX."cpcc_portal_invitation(entity,fk_request,fk_soc,fk_socpeople,fk_user,email_hmac,token_hash,status,expires_at,fk_user_creat,date_creation) VALUES(".$this->entity.",".(int) $requestId.",".(int) $request['fk_soc'].",".(int) $request['fk_socpeople'].",".(int) $userId.",'".$emailHmac."','".$hash."','pending','".$expires."',".(int) $staffUser->id.",'".$now."')";
            if (!$this->db->query($sql)) throw new PortalInvitationException('portal_invitation_create_failed');
            if (!$this->db->query("UPDATE ".MAIN_DB_PREFIX."cpo_public_request SET portal_status='invite_pending',fk_user_modif=".(int) $staffUser->id.",date_modification='".$now."' WHERE entity=".$this->entity." AND rowid=".(int) $requestId." AND fk_soc=".(int) $request['fk_soc'])) throw new PortalInvitationException('portal_invitation_request_update_failed');
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollback();
            if ($createdUserId > 0) $this->rollbackCreatedUser($createdUserId, (int)$request['fk_soc'], $staffUser);
            throw $e;
        }
        $createdUserId = 0;
        $url = dol_buildpath('/chinaplanetcustomercenter/activate.php', 2).'?token='.rawurlencode($token);
        if ($sendMail) $this->send($email, (string) $request['ref'], $url);
        return array('status'=>'invite_pending', 'expires_at'=>$expires, 'user_id'=>$userId);
    }

    public function inspect($token)
    {
        $token = trim((string) $token);
        if (!preg_match('/^[A-Za-z0-9_-]{43}$/', $token)) return null;
        $hash = hash('sha256', $token);
        $sql = "SELECT i.*,r.ref FROM ".MAIN_DB_PREFIX."cpcc_portal_invitation i INNER JOIN ".MAIN_DB_PREFIX."cpo_public_request r ON r.rowid=i.fk_request AND r.entity=i.entity WHERE i.entity=".$this->entity." AND i.token_hash='".$hash."' LIMIT 1";
        $res = $this->db->query($sql);
        $obj = $res ? $this->db->fetch_object($res) : null;
        if (!$obj || $obj->status !== 'pending' || strtotime($obj->expires_at) < time()) return null;
        return array('ref'=>(string)$obj->ref, 'expires_at'=>(string)$obj->expires_at);
    }

    public function accept($token, $password, $confirm, $actor = null, $remoteIp = null)
    {
        $token = trim((string) $token);
        if (!preg_match('/^[A-Za-z0-9_-]{43}$/', $token)) throw new PortalInvitationException('portal_activation_invalid');
        $hash = hash('sha256', $token);
        $ipHmac = hash_hmac('sha256', $this->clientIp($remoteIp), $this->hmacKey);
        $passwordFailure = false;
        $this->db->begin();
        try {
            $res = $this->db->query("SELECT * FROM ".MAIN_DB_PREFIX."cpcc_portal_invitation WHERE entity=".$this->entity." AND token_hash='".$this->db->escape($hash)."' FOR UPDATE");
            $invitation = $res ? $this->db->fetch_object($res) : null;
            if (!$invitation || $invitation->status !== 'pending' || strtotime($invitation->expires_at) < time()) throw new PortalInvitationException('portal_activation_invalid');
            $this->consumeAttempt($hash, $ipHmac);
            if (!hash_equals((string) $password, (string) $confirm) || strlen((string) $password) < 12 || strlen((string) $password) > 128) {
                $this->recordFailure((int) $invitation->rowid, (int) $invitation->attempt_count + 1);
                $this->db->commit();
                $passwordFailure = true;
            } else {
                $portal = new User($this->db);
                if ($portal->fetch((int) $invitation->fk_user) <= 0 || (int) $portal->socid !== (int) $invitation->fk_soc) throw new PortalInvitationException('portal_activation_user_invalid');
                $requester = $actor ?: new User($this->db);
                if ($portal->setPassword($requester, (string) $password, 0, 0, 0, 0, 1) < 0) throw new PortalInvitationException($portal->error ?: 'portal_activation_password_failed');
                $clearPlaintext = $this->db->query("UPDATE ".MAIN_DB_PREFIX."user SET pass=NULL WHERE entity=".$this->entity." AND rowid=".(int)$invitation->fk_user." AND fk_soc=".(int)$invitation->fk_soc." AND pass_crypted IS NOT NULL");
                if (!$clearPlaintext) throw new PortalInvitationException('portal_activation_password_storage_failed');
                if ($portal->setstatus(1) < 0) throw new PortalInvitationException($portal->error ?: 'portal_activation_enable_failed');
                $now = $this->db->idate(dol_now());
                $accepted = $this->db->query("UPDATE ".MAIN_DB_PREFIX."cpcc_portal_invitation SET status='accepted',accepted_at='".$now."' WHERE rowid=".(int) $invitation->rowid." AND status='pending'");
                if (!$accepted || $this->db->affected_rows($accepted) !== 1) throw new PortalInvitationException('portal_activation_update_failed');
                if (!$this->db->query("UPDATE ".MAIN_DB_PREFIX."cpcc_portal_invitation SET status='revoked',revoked_at='".$now."' WHERE entity=".$this->entity." AND fk_user=".(int) $invitation->fk_user." AND rowid<>".(int) $invitation->rowid." AND status='pending'")) throw new PortalInvitationException('portal_activation_revoke_failed');
                if (!$this->db->query("UPDATE ".MAIN_DB_PREFIX."cpo_public_request SET portal_status='active',date_modification='".$now."' WHERE entity=".$this->entity." AND rowid=".(int) $invitation->fk_request." AND fk_soc=".(int) $invitation->fk_soc)) throw new PortalInvitationException('portal_activation_request_update_failed');
                $this->db->commit();
            }
        } catch (Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
        if ($passwordFailure) throw new PortalInvitationException('portal_activation_password_invalid');
        return true;
    }

    public function revokeForRequest($requestId, $staffUser)
    {
        $now = $this->db->idate(dol_now());
        $this->db->begin();
        try {
            if (!$this->db->query("UPDATE ".MAIN_DB_PREFIX."cpcc_portal_invitation SET status='revoked',revoked_at='".$now."',fk_user_revoke=".(int) $staffUser->id." WHERE entity=".$this->entity." AND fk_request=".(int) $requestId." AND status='pending'")) throw new PortalInvitationException('portal_invitation_revoke_failed');
            if (!$this->db->query("UPDATE ".MAIN_DB_PREFIX."cpo_public_request SET portal_status='revoked',date_modification='".$now."' WHERE entity=".$this->entity." AND rowid=".(int) $requestId)) throw new PortalInvitationException('portal_invitation_request_update_failed');
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    private function enforceIssueLimit($requestId, $emailHmac)
    {
        $since = $this->db->idate(dol_now() - self::ISSUE_WINDOW_SECONDS);
        $sql = "SELECT COUNT(*) AS total FROM ".MAIN_DB_PREFIX."cpcc_portal_invitation WHERE entity=".$this->entity." AND date_creation>='".$since."' AND (fk_request=".(int) $requestId." OR email_hmac='".$this->db->escape($emailHmac)."')";
        $res = $this->db->query($sql);
        $obj = $res ? $this->db->fetch_object($res) : null;
        if (!$obj) throw new PortalInvitationException('portal_invitation_rate_check_failed');
        if ((int) $obj->total >= self::ISSUE_LIMIT) throw new PortalInvitationException('portal_invitation_rate_limited');
    }

    private function consumeAttempt($tokenHash, $ipHmac)
    {
        $now = dol_now();
        $since = $this->db->idate($now - self::ATTEMPT_WINDOW_SECONDS);
        $res = $this->db->query("SELECT COUNT(*) AS total FROM ".MAIN_DB_PREFIX."cpcc_portal_invitation_attempt WHERE entity=".$this->entity." AND token_hash='".$this->db->escape($tokenHash)."' AND ip_hmac='".$this->db->escape($ipHmac)."' AND last_attempt_at>'".$since."'");
        if (!$res) throw new PortalInvitationException('portal_activation_rate_check_failed');
        $row = $this->db->fetch_object($res);
        if (!$row || (int)$row->total >= self::ATTEMPT_LIMIT) throw new PortalInvitationException($row ? 'portal_activation_rate_limited' : 'portal_activation_rate_check_failed');
        $timestamp = $this->db->idate($now);
        $sql = "INSERT INTO ".MAIN_DB_PREFIX."cpcc_portal_invitation_attempt(entity,token_hash,ip_hmac,window_start,attempt_count,last_attempt_at) VALUES(".$this->entity.",'".$this->db->escape($tokenHash)."','".$this->db->escape($ipHmac)."','".$timestamp."',1,'".$timestamp."')";
        if (!$this->db->query($sql)) throw new PortalInvitationException('portal_activation_rate_check_failed');
        $this->db->query("DELETE FROM ".MAIN_DB_PREFIX."cpcc_portal_invitation_attempt WHERE entity=".$this->entity." AND last_attempt_at<'".$this->db->idate($now - 172800)."'");
    }

    private function provisionPortalGroup($userId)
    {
        $name = 'Customers Portal';
        $res = $this->db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."usergroup WHERE nom='".$name."' AND entity IN (0,".$this->entity.") ORDER BY entity DESC LIMIT 1");
        if (!$res) throw new PortalInvitationException('portal_group_failed');
        $group = $this->db->fetch_object($res);
        if (!$group) {
            if (!$this->db->query("INSERT INTO ".MAIN_DB_PREFIX."usergroup(nom,note,entity,datec) VALUES('".$name."','M068 Customer Center invitations',".$this->entity.",'".$this->db->idate(dol_now())."')")) throw new PortalInvitationException('portal_group_failed');
            $groupId = (int) $this->db->last_insert_id(MAIN_DB_PREFIX.'usergroup');
        } else $groupId = (int) $group->rowid;

        foreach (array(500201) as $rightId) {
            if (!$this->db->query("INSERT IGNORE INTO ".MAIN_DB_PREFIX."usergroup_rights(fk_usergroup,fk_id,entity) VALUES(".$groupId.",".$rightId.",".$this->entity.")")) throw new PortalInvitationException('portal_group_right_failed');
            $verify = $this->db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."usergroup_rights WHERE fk_usergroup=".$groupId." AND fk_id=".$rightId." AND entity=".$this->entity);
            if (!$verify || !$this->db->fetch_object($verify)) throw new PortalInvitationException('portal_group_right_failed');
        }
        if (!$this->db->query("INSERT IGNORE INTO ".MAIN_DB_PREFIX."usergroup_user(fk_user,fk_usergroup,entity) VALUES(".(int) $userId.",".$groupId.",".$this->entity.")")) throw new PortalInvitationException('portal_group_membership_failed');
        $verify = $this->db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."usergroup_user WHERE fk_user=".(int) $userId." AND fk_usergroup=".$groupId." AND entity=".$this->entity);
        if (!$verify || !$this->db->fetch_object($verify)) throw new PortalInvitationException('portal_group_membership_failed');

        $res = $this->db->query("SELECT value FROM ".MAIN_DB_PREFIX."user_param WHERE fk_user=".(int) $userId." AND entity=".$this->entity." AND param='MAIN_LANDING_PAGE' LIMIT 1");
        if (!$res) { dol_syslog('M068 portal landing select failed: '.$this->db->lasterror(), LOG_ERR); throw new PortalInvitationException('portal_landing_page_query_failed'); }
        $param = $this->db->fetch_object($res);
        $landing = '/custom/chinaplanetcustomercenter/board.php';
        $where = "fk_user=".(int) $userId." AND entity=".$this->entity." AND param='MAIN_LANDING_PAGE'";
        $sql = $param ? "UPDATE ".MAIN_DB_PREFIX."user_param SET value='".$landing."' WHERE ".$where : "INSERT INTO ".MAIN_DB_PREFIX."user_param(fk_user,entity,param,value) VALUES(".(int) $userId.",".$this->entity.",'MAIN_LANDING_PAGE','".$landing."')";
        if (!$this->db->query($sql)) { dol_syslog('M068 portal landing save failed: '.$this->db->lasterror(), LOG_ERR); throw new PortalInvitationException('portal_landing_page_save_failed'); }
        $verify = $this->db->query("SELECT value FROM ".MAIN_DB_PREFIX."user_param WHERE ".$where." AND value='".$landing."'");
        if (!$verify) { dol_syslog('M068 portal landing verify failed: '.$this->db->lasterror(), LOG_ERR); throw new PortalInvitationException('portal_landing_page_verify_failed'); }
        if (!$this->db->fetch_object($verify)) throw new PortalInvitationException('portal_landing_page_verify_missing');
    }

    /** Read-only proof that staff has already provisioned this exact external user. */
    private function hasCurrentPortalAccess($userId, $socid)
    {
        $sql = "SELECT u.rowid FROM ".MAIN_DB_PREFIX."user u";
        $sql .= " INNER JOIN ".MAIN_DB_PREFIX."usergroup_user gu ON gu.fk_user=u.rowid AND gu.entity=u.entity";
        $sql .= " INNER JOIN ".MAIN_DB_PREFIX."usergroup g ON g.rowid=gu.fk_usergroup AND g.nom='Customers Portal' AND g.entity IN (0,".$this->entity.")";
        $sql .= " INNER JOIN ".MAIN_DB_PREFIX."usergroup_rights gr ON gr.fk_usergroup=g.rowid AND gr.fk_id=500201 AND gr.entity=".$this->entity;
        $sql .= " WHERE u.rowid=".(int)$userId." AND u.entity=".$this->entity." AND u.fk_soc=".(int)$socid." AND u.admin=0 AND u.statut=1 LIMIT 1";
        $res = $this->db->query($sql);
        if (!$res) throw new PortalInvitationException('portal_reset_access_check_failed');
        return (bool) $this->db->fetch_object($res);
    }

    private function rollbackCreatedUser($userId, $socid, $staffUser)
    {
        $portal = new User($this->db);
        if ($portal->fetch((int)$userId) <= 0) return;
        if ((int)$portal->entity !== $this->entity || (int)$portal->socid !== (int)$socid || $portal->delete($staffUser) <= 0) {
            dol_syslog('M068 failed to remove an incomplete portal user '.$userId, LOG_ERR);
            throw new PortalInvitationException('portal_user_rollback_failed');
        }
    }

    private function qualifiedRequest($id) { $res=$this->db->query("SELECT rowid,ref,fk_soc,fk_socpeople,qualification_status FROM ".MAIN_DB_PREFIX."cpo_public_request WHERE entity=".$this->entity." AND rowid=".(int)$id." AND qualification_status='qualified' AND fk_soc IS NOT NULL AND fk_socpeople IS NOT NULL"); $obj=$res?$this->db->fetch_object($res):null; return $obj?(array)$obj:null; }
    private function emailOrLoginUsers($email) { $rows=array(); $escaped=$this->db->escape($email); $res=$this->db->query("SELECT rowid,fk_soc,statut,login,email FROM ".MAIN_DB_PREFIX."user WHERE entity=".$this->entity." AND (LOWER(TRIM(email))='".$escaped."' OR LOWER(TRIM(login))='".$escaped."') ORDER BY rowid LIMIT 3"); if(!$res)throw new PortalInvitationException('portal_invitation_email_check_failed'); while($obj=$this->db->fetch_object($res))$rows[]=(array)$obj; return $rows; }
    private function send($email,$ref,$url) { $from=(string)getDolGlobalString('MAIN_MAIL_EMAIL_FROM'); if(!filter_var($from,FILTER_VALIDATE_EMAIL))throw new PortalInvitationException('portal_invitation_mail_not_configured'); $subject='Set your ChinaPlanet Customer Center password / إعداد كلمة مرور مركز العميل'; $body="استخدم الرابط الآمن ذا الصلاحية لإعداد كلمة مرورك.\n\nUse this expiring secure link to set your password.\n\n".$url."\n\nRequest: ".$ref."\nThis link expires in 24 hours and can be used once."; $mail=new CMailFile($subject,$email,$from,$body,array(),array(),array(),'','','',0,-1,'', '', '', '', 'standard'); if(!$mail->sendfile())throw new PortalInvitationException('portal_invitation_mail_failed'); }
    private function recordFailure($id,$count) { if(!$this->db->query("UPDATE ".MAIN_DB_PREFIX."cpcc_portal_invitation SET attempt_count=".(int)$count.",last_attempt_at='".$this->db->idate(dol_now())."' WHERE rowid=".(int)$id))throw new PortalInvitationException('portal_activation_audit_failed'); }
    private function clientIp($provided) { $value=$provided===null?($_SERVER['REMOTE_ADDR']??''):(string)$provided; return filter_var($value,FILTER_VALIDATE_IP)?$value:'unknown'; }
    private function base64url($bytes) { return rtrim(strtr(base64_encode($bytes),'+/','-_'),'='); }
    private function acquireLock($name) { $res=$this->db->query("SELECT GET_LOCK('".$this->db->escape($name)."',5) AS acquired");$obj=$res?$this->db->fetch_object($res):null;if(!$obj||(int)$obj->acquired!==1)throw new PortalInvitationException('portal_invitation_busy'); }
    private function releaseLock($name) { $this->db->query("SELECT RELEASE_LOCK('".$this->db->escape($name)."')"); }
}
