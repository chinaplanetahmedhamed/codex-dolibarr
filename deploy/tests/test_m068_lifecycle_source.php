<?php

$root = dirname(__DIR__, 2);
$read = static function ($path) use ($root) {
    $value = file_get_contents($root.'/'.$path);
    if ($value === false) throw new RuntimeException('missing '.$path);
    return $value;
};
$assert = static function ($condition, $message) {
    if (!$condition) throw new RuntimeException($message);
};

$intake = $read('custom/chinaplanetoperations/class/PublicWebsiteRequestService.class.php');
$lifecycle = $read('custom/chinaplanetoperations/class/PublicRequestLifecycleService.class.php');
$invitation = $read('custom/chinaplanetcustomercenter/class/PortalInvitationService.class.php');
$portal = $read('custom/chinaplanetcustomercenter/quotation_requests.php');
$activation = $read('custom/chinaplanetcustomercenter/activate.php');
$navigation = $read('custom/chinaplanetcustomercenter/lib/portal.lib.php');
$reset = $read('custom/chinaplanetcustomercenter/reset_password.php');
$quoteCss = $read('custom/chinaplanetcustomercenter/css/quotation_requests.css');
$activationCss = $read('custom/chinaplanetcustomercenter/css/activation.css');
$download = $read('custom/chinaplanetoperations/class/PublicRequestPublicationService.class.php');
$quotation = $read('custom/chinaplanetoperations/class/PublicRequestQuotationService.class.php');
$staffPage = $read('custom/chinaplanetoperations/operations/public_requests.php');
$customerPortal = $read('custom/chinaplanetcustomercenter/quotation_requests.php');

$assert(strpos($intake, "const CATALOG_CONTRACT = 'cpo.catalog-order.v2'") !== false, 'v2 contract not frozen');
$assert(strpos($intake, "'schema_version' => (int) \$result['schema_version']") === false, 'API check belongs in API source');
$assert(strpos($lifecycle, "\$line['unitsPerCarton'] ?? null") !== false, 'ready v2 unitsPerCarton is not seeded');
$assert(strpos($lifecycle, "\$line['piecesPerCarton']") === false, 'non-contract piecesPerCarton key remains');
$assert(strpos($lifecycle, 'submitted_calculation_ready') !== false, 'ready flag is not seeded');
$assert(strpos($lifecycle, "LOWER(TRIM(s.email))") !== false && strpos($lifecycle, "sp.phone_mobile") !== false && strpos($lifecycle, "\$this->phone(\$obj->phone_mobile)") !== false, 'normalized Third Party/contact identity matching missing');
$stopEvent = strpos($lifecycle, "'qualification_stopped'");
$stopThrow = strpos($lifecycle, "if (\$stopRequired) throw new PublicWebsiteRequestException('qualification_existing_match_requires_choice'");
$stopCommit = $stopEvent === false ? false : strpos($lifecycle, '$this->db->commit();', $stopEvent);
$assert($stopEvent !== false && $stopCommit !== false && $stopThrow !== false && $stopCommit < $stopThrow, 'stopping state is not durably committed before 409');
$assert(strpos($lifecycle, "\$weight === null || !in_array(\$source, \$approvedSources, true)") !== false, 'verified packing/source fail-closed check missing');
$assert(strpos($invitation, "hash('sha256', \$token)") !== false, 'invitation token is not hash-only');
$assert(strpos($invitation, 'setPassword(') !== false && strpos($invitation, 'setstatus(1)') !== false, 'standard password activation missing');
$assert(strpos($portal, 'fk_soc=".$socid') !== false && strpos($portal, 'request_customer_visible=1') !== false, 'portal tenant scope missing');
$assert(strpos($portal, 'dir="rtl"') === false && strpos($activation, 'dir="rtl"') === false, 'new portal surfaces hardcode RTL');
$assert(strpos($navigation, 'dir="rtl"') === false && strpos($navigation, "strpos(\$activeLanguage, 'ar') === 0 ? 'rtl' : 'ltr'") !== false, 'shared portal navigation does not follow active language direction');
$assert(strpos($portal, "QuotationRequestsIntro") !== false && strpos($portal, 'QuotationStatusQualified') !== false, 'portal labels are not localized');
$assert(strpos($navigation, "'orders' => array") !== false && strpos($navigation, "'quotation_requests' => array") !== false, 'Orders and Quotation Requests are not separate portal journeys');
$assert(strpos($portal, "GETPOST('ref'") !== false && strpos($portal, '?id=') === false, 'portal request URL exposes a database rowid');
$assert(strpos($portal, 'MissingUnitsPerCarton') !== false && strpos($portal, "implode(', ', \$line['missingFields'])") === false, 'portal missing-field names are not localized');
$assert(strpos($lifecycle, '$requestVisible = (bool) $requestVisible || (bool) $proposalVisible;') !== false, 'proposal visibility does not imply its request shell visibility');
$assert(strpos($reset, 'requestPasswordReset') !== false && strpos($reset, 'PortalResetPasswordSubmitted') !== false && strpos($reset, 'dol_syslog') !== false, 'non-enumerating secure portal reset surface missing');
$assert(strpos($invitation, 'public function requestPasswordReset') !== false && strpos($invitation, 'LOWER(TRIM(u.email))') !== false && strpos($invitation, 'hasCurrentPortalAccess') !== false && strpos($invitation, 'false, $portalUserId') !== false, 'email-scoped non-provisioning one-time reset service missing');
$assert(strpos($quoteCss, 'min-height:44px') !== false && strpos($quoteCss, ':focus-visible') !== false && strpos($activationCss, 'font-size:16px') !== false && strpos($activationCss, ':focus-visible') !== false, 'mobile touch/focus acceptance is not encoded');
$assert(strpos($download, "strpos(\$fileReal,\$rootReal.DIRECTORY_SEPARATOR)!==0") !== false, 'download containment missing');
$assert(strpos($download, "dol_mkdir(\$dir,'','0750')<0") !== false, 'publication directory mode/recursive creation is not fail closed');
$assert(strpos($quotation, "\$line['verified_packing_source']") !== false && strpos($staffPage, "\$selectedPackingSource") !== false, 'staff packing verification source is not retained');
$assert(strpos($staffPage, "empty(\$preparedQuoteLine['packing_verified'])") !== false && strpos($staffPage, "!== 'ready'") !== false, 'draft action is not gated on completed line review');
$assert(strpos($intake, "throw new PublicWebsiteRequestException('qualification_required', 409)") !== false && strpos($intake, "throw new PublicWebsiteRequestException('qualification_link_conflict', 409)") !== false, 'legacy promotion can bypass v2 qualification ownership');
$assert(strpos($staffPage, "(int)(\$selected['schema_version'] ?? 1) === 1") !== false, 'legacy promote UI is exposed to v2 requests');
$assert(strpos($staffPage, "line_staff_note") !== false && strpos($staffPage, "line_public_description") !== false && strpos($staffPage, "line_note[") === false, 'private and customer-visible line notes are not separated');
$assert(strpos($quotation, "'public_description'=>") !== false && strpos($quotation, "\$item['public_description']") !== false, 'proposal line uses an unapproved private note');
$assert(strpos($customerPortal, 'multicurrency_total_ht') !== false && strpos($customerPortal, 'multicurrency_total_ttc') !== false && strpos($customerPortal, 'ProposalSubtotal') !== false, 'portal proposal amounts are not currency explicit');
$assert(substr_count($lifecycle, '$this->db->begin();') >= 4 && strpos($lifecycle, 'public_request_visibility_failed') !== false, 'line review or portal visibility is not audit-atomic');
$assert(strpos($quotation, '$this->db->begin();') !== false && strpos($quotation, '$this->db->rollback();') !== false && strpos($quotation, '$this->db->commit();') !== false, 'draft proposal workflow lacks an outer transaction');
$assert(strpos($activation, "Referrer-Policy: no-referrer") !== false && strpos($activation, 'dol_escape_htmltag($langs->trans($error))') !== false && strpos($activation, '$publicErrors') !== false, 'activation page leaks referrer or dynamic error text');

echo "M068_LIFECYCLE_SOURCE=PASS READY_UNITS_KEY=unitsPerCarton\n";
