<?php

$root = dirname(__DIR__, 2);
$service = file_get_contents($root.'/custom/chinaplanetcustomercenter/class/PortalInvitationService.class.php');
$attemptSchema = file_get_contents($root.'/custom/chinaplanetcustomercenter/sql/llx_cpcc_portal_invitation_attempt.sql');
$migration = file_get_contents($root.'/deploy/migrations/m068_website_lead_quotation_portal_lifecycle.php');
if ($service === false || $attemptSchema === false || $migration === false) throw new RuntimeException('M068 invitation sources missing');
$assert = static function ($condition, $message) { if (!$condition) throw new RuntimeException($message); };

$assert(strpos($service, 'create_from_contact($contact, $login,') !== false && strpos($service, "'cp-'.substr(hash_hmac") !== false, 'safe internal login is missing');
$assert(strpos($service, 'LOWER(TRIM(email))=') !== false && strpos($service, 'LOWER(TRIM(login))=') !== false, 'email/login uniqueness conflict check missing');
$assert(strpos($service, 'const ISSUE_LIMIT = 3;') !== false && strpos($service, 'const ISSUE_WINDOW_SECONDS = 3600;') !== false, '3/hour invite limit missing');
$assert(strpos($service, 'const ATTEMPT_LIMIT = 5;') !== false && strpos($service, 'const ATTEMPT_WINDOW_SECONDS = 900;') !== false, '5/15-minute activation limit missing');
$assert(strpos($service, 'GET_LOCK(') !== false, 'concurrent invitation issue lock missing');
$assert(strpos($service, "hash_hmac('sha256', \$this->clientIp") !== false, 'IP is not protected by HMAC');
$assert(strpos($attemptSchema, 'idx_cpcc_invitation_attempt_scope (entity, token_hash, ip_hmac, last_attempt_at)') !== false && strpos($attemptSchema, 'UNIQUE KEY uk_cpcc_invitation_attempt') === false, 'durable rolling per-token/IP attempts missing');
$assert(strpos($service, 'COUNT(*) AS total') !== false && strpos($service, "last_attempt_at>'") !== false && strpos($service, 'attempt_count=attempt_count+1') === false, 'activation limiter is not a true sliding window');
$assert(strpos($migration, "'cpcc_portal_invitation_attempt'") !== false && strpos($migration, 'idx_cpcc_invitation_attempt_scope') !== false && strpos($migration, 'DROP INDEX `uk_cpcc_invitation_attempt`') !== false, 'rolling attempt schema transition missing from migration');
$assert(strpos($service, "throw new PortalInvitationException('portal_group_right_failed')") !== false, 'rights provisioning does not fail closed');
$assert(strpos($service, "throw new PortalInvitationException('portal_group_membership_failed')") !== false, 'membership provisioning does not fail closed');
$assert(strpos($service, "throw new PortalInvitationException('portal_landing_page_save_failed')") !== false && strpos($service, "throw new PortalInvitationException('portal_landing_page_verify_missing')") !== false, 'landing-page provisioning does not fail closed');
$assert(strpos($service, 'pass=NULL,pass_crypted=NULL,pass_temp=NULL,statut=0') !== false, 'temporary credential clearing missing');
$assert(strpos($service, 'rollbackCreatedUser($createdUserId') !== false && strpos($service, "throw new PortalInvitationException('portal_user_rollback_failed')") !== false, 'failed provisioning can leave an orphan portal user');
$assert(strpos($service, 'SET pass=NULL WHERE entity=') !== false && strpos($service, 'pass_crypted IS NOT NULL') !== false, 'plaintext password clearing after activation missing');
$assert(strpos($service, "return array('status'=>'invite_pending', 'expires_at'=>\$expires, 'user_id'=>\$userId)") !== false, 'invitation result is not non-secret');
$assert(strpos($service, "'activation_url'=>") === false && strpos($service, "'token'=>\$token") === false, 'raw invitation secret is returned');
$assert(strpos($service, 'public function requestPasswordReset') !== false && strpos($service, "count(\$rows) !== 1") !== false && strpos($service, "LOWER(TRIM(sp.email))") !== false, 'reset lookup is not exact, tenant-scoped, and ambiguity-safe');
$assert(strpos($service, 'private function hasCurrentPortalAccess') !== false && strpos($service, 'elseif (!$this->hasCurrentPortalAccess') !== false && strpos($service, '$this->issueUnlocked((int) $requestRow->request_id, $portal, $sendMail, false, $portalUserId)') !== false, 'anonymous reset can provision or restore portal access');
$assert(strpos($service, 'foreach (array(500201) as $rightId)') !== false && strpos($service, 'array(343') === false, 'insecure core temporary-password reset right was granted');

echo "M068_INVITATION_SECURITY_SOURCE=PASS CUSTOMER_LOGIN=email CORE_LOGIN=internal ISSUE_LIMIT=3/hour ATTEMPT_LIMIT=5/15m STORAGE=hash-only\n";
