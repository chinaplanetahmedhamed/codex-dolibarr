<?php

define('CSRFCHECK_WITH_TOKEN', 1);
require $_SERVER['DOCUMENT_ROOT'].'/main.inc.php';
require_once __DIR__.'/../lib/main.lib.php';
require_once __DIR__.'/../class/PublicWebsiteRequestService.class.php';
require_once __DIR__.'/../class/PublicRequestQuotationService.class.php';
require_once __DIR__.'/../class/PublicRequestContactService.class.php';
require_once __DIR__.'/../class/PublicRequestLifecycleService.class.php';
require_once __DIR__.'/../class/PublicRequestPublicationService.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/chinaplanetcustomercenter/class/PortalInvitationService.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/chinaplanetcustomercenter/lib/admin_shell.lib.php';

function cpo_public_request_has_value($value)
{
    if (is_array($value)) return !empty($value);
    if ($value === null) return false;
    if (is_string($value)) return trim($value) !== '';
    return true;
}

function cpo_public_request_label($key, $langs)
{
    $labels = array(
        'name'=>'PublicFieldContactName', 'company'=>'PublicFieldCompany', 'phone'=>'PublicFieldPhone', 'email'=>'PublicFieldEmail',
        'country_code'=>'PublicFieldCountry', 'locale'=>'PublicFieldRequestLanguage', 'service'=>'PublicFieldService',
        'message'=>'PublicFieldMessage', 'product_summary'=>'PublicFieldProductSummary', 'quantity'=>'PublicFieldQuantity',
        'target_country'=>'PublicFieldTargetCountry', 'currency'=>'PublicFieldCurrency', 'lines'=>'PublicFieldOrderLines',
        'packing_plan'=>'PublicFieldPackingPlan', 'source_reference'=>'PublicFieldSourceReference',
        'container_quantity'=>'PublicFieldContainerQuantity', 'cargo_type'=>'PublicFieldCargoType',
        'dangerous_goods'=>'PublicFieldDangerousGoods', 'restricted_cargo'=>'PublicFieldRestrictedCargo',
        'cargo_summary'=>'PublicFieldCargoSummary', 'gross_weight'=>'PublicFieldGrossWeight', 'hs_code'=>'PublicFieldHSCode',
        'final_destination'=>'PublicFieldFinalDestination', 'required_services'=>'PublicFieldRequiredServices',
        'terms_acknowledged_at'=>'PublicFieldTermsAcknowledgedAt', 'pol'=>'PublicFieldPOL', 'pod'=>'PublicFieldPOD',
        'public_carrier_label'=>'PublicFieldCarrier', 'container_type'=>'PublicFieldContainerType',
        'service_type'=>'PublicFieldServiceType', 'unit_price'=>'PublicFieldUnitPrice', 'total'=>'PublicFieldTotal',
        'price_basis'=>'PublicFieldPriceBasis', 'valid_from'=>'PublicFieldValidFrom', 'valid_to'=>'PublicFieldValidTo',
        'acknowledged_at'=>'PublicFieldAcknowledgedAt', 'label'=>'PublicFieldLabel', 'amount'=>'PublicFieldAmount',
        'type'=>'PublicFieldType', 'included'=>'PublicFieldIncluded', 'excluded'=>'PublicFieldExcluded',
        'warnings'=>'PublicFieldWarnings', 'notes'=>'PublicFieldNotes',
    );
    if (isset($labels[$key])) return $langs->trans($labels[$key]);
    return ucwords(str_replace('_', ' ', (string) $key));
}

function cpo_public_request_value_html($value, $langs, $depth = 0)
{
    if (!cpo_public_request_has_value($value)) return '<span class="cpo-request-empty">'.$langs->trans('PublicValueNotProvided').'</span>';
    if (is_bool($value)) return '<span class="cpo-request-bool '.($value ? 'is-yes' : 'is-no').'">'.$langs->trans($value ? 'PublicValueYes' : 'PublicValueNo').'</span>';
    if (!is_array($value)) {
        $normalized = strtolower(trim((string) $value));
        if ($normalized === 'yes' || $normalized === 'no') {
            return '<span class="cpo-request-bool '.($normalized === 'yes' ? 'is-yes' : 'is-no').'">'.$langs->trans($normalized === 'yes' ? 'PublicValueYes' : 'PublicValueNo').'</span>';
        }
        return '<span class="cpo-request-text">'.nl2br(dol_escape_htmltag((string) $value)).'</span>';
    }
    if ($depth >= 3) return '<span class="cpo-request-empty">'.$langs->trans('PublicValueStructuredData').'</span>';
    $isList = array_keys($value) === range(0, count($value) - 1);
    if ($isList) {
        $html = '<ul class="cpo-request-list">';
        foreach ($value as $item) $html .= '<li>'.cpo_public_request_value_html($item, $langs, $depth + 1).'</li>';
        return $html.'</ul>';
    }
    $html = '<dl class="cpo-request-nested">';
    foreach ($value as $key=>$item) {
        if (!cpo_public_request_has_value($item)) continue;
        $html .= '<div><dt>'.dol_escape_htmltag(cpo_public_request_label($key, $langs)).'</dt><dd>'.cpo_public_request_value_html($item, $langs, $depth + 1).'</dd></div>';
    }
    return $html.'</dl>';
}

function cpo_public_request_field_html($key, $value, $langs, $wide = false)
{
    if (!cpo_public_request_has_value($value) && !is_bool($value)) return '';
    return '<div class="cpo-request-field'.($wide ? ' is-wide' : '').'"><span>'.dol_escape_htmltag(cpo_public_request_label($key, $langs)).'</span><div>'.cpo_public_request_value_html($value, $langs).'</div></div>';
}

function cpo_public_request_money($value, $currency)
{
    if (!is_numeric($value)) return dol_escape_htmltag((string) $value);
    return number_format((float) $value, 2, '.', ',').' '.dol_escape_htmltag((string) $currency);
}

function cpo_public_request_product_image_html(array $line)
{
    global $conf;
    if (!empty($line['product']) && !empty($line['product']->entity) && !empty($conf->product->multidir_output[$line['product']->entity])) {
        $html = $line['product']->show_photos('product', $conf->product->multidir_output[$line['product']->entity], 1, 1, 0, 0, 0, 72, 72, 1, 1, 0, '1', 'cpo-quote-product-image');
        if (!empty($line['product']->nbphoto)) return $html;
    }
    if (!empty($line['image'])) return '<img class="cpo-quote-product-image" src="'.dol_escape_htmltag($line['image']).'" alt="" loading="lazy" referrerpolicy="no-referrer">';
    return '<span class="cpo-quote-product-placeholder fas fa-box" aria-hidden="true"></span>';
}

cpo_require($user, 'publicrequest_read');
$langs->load('chinaplanetoperations@chinaplanetoperations');
$service = new PublicWebsiteRequestService($db);
$quotationService = new PublicRequestQuotationService($db);
$contactService = new PublicRequestContactService($db);
$lifecycleService = new PublicRequestLifecycleService($db);
$publicationService = new PublicRequestPublicationService($db);
$action = GETPOST('action', 'aZ09');
$id = GETPOSTINT('id');
$compose = GETPOST('compose', 'aZ09');

if ($action === 'save_quotation') {
    cpo_require($user, 'publicrequest_quote');
    if (!$user->hasRight('propal', 'creer') || !$user->hasRight('product', 'read')) accessforbidden();
    try {
        $products = GETPOST('line_product', 'array:int');
        $sources = GETPOST('line_price_source', 'array:aZ09');
        $manualPrices = GETPOST('line_manual_price', 'array:alphanohtml');
        $lineStaffNotes = GETPOST('line_staff_note', 'array:restricthtml');
        $linePublicDescriptions = GETPOST('line_public_description', 'array:restricthtml');
        $lineInput = array();
        $workingIds = GETPOST('line_working_id', 'array:int');
        $packingUnits = GETPOST('line_units_per_carton', 'array:int');
        $packingCbm = GETPOST('line_cbm_per_carton', 'array:alphanohtml');
        $packingWeight = GETPOST('line_gross_weight_kg', 'array:alphanohtml');
        $packingVerified = GETPOST('line_packing_verified', 'array:int');
        $packingSources = GETPOST('line_verification_source', 'array:aZ09');
        foreach ((array) $products as $index=>$productId) {
            if (!empty($workingIds[$index])) $lifecycleService->reviewLine($id, (int)$workingIds[$index], array('fk_product'=>(int)$productId,'units_per_carton'=>$packingUnits[$index] ?? null,'cbm_per_carton'=>$packingCbm[$index] ?? null,'gross_weight_kg'=>$packingWeight[$index] ?? null,'packing_verified'=>!empty($packingVerified[$index]),'verification_source'=>$packingSources[$index] ?? '','staff_note'=>$lineStaffNotes[$index] ?? ''), $user);
            $lineInput[(int) $index] = array(
                'fk_product'=>(int) $productId,
                'price_source'=>isset($sources[$index]) ? $sources[$index] : 'dolibarr',
                'manual_price'=>isset($manualPrices[$index]) ? $manualPrices[$index] : '',
                'public_description'=>isset($linePublicDescriptions[$index]) ? $linePublicDescriptions[$index] : '',
            );
        }
        $proposal = $quotationService->saveDraft($id, GETPOSTINT('quote_fk_soc'), GETPOSTINT('quote_contact_id'), GETPOST('quote_currency', 'alpha'), GETPOST('quote_note_public', 'restricthtml'), GETPOST('quote_note_private', 'restricthtml'), $lineInput, $user);
        setEventMessages($langs->trans('PublicQuotationSaved', $proposal->ref), null, 'mesgs');
        header('Location: public_requests.php?id='.(int) $id); exit;
    } catch (Throwable $e) { setEventMessages($langs->trans($e->getMessage()), null, 'errors'); }
}

if ($action === 'qualify_existing') {
    cpo_require($user, 'publicrequest_qualify');
    try { $lifecycleService->qualifyExisting($id, GETPOSTINT('fk_soc'), GETPOSTINT('fk_socpeople'), $user); setEventMessages($langs->trans('PublicQualificationSaved'),null,'mesgs'); header('Location: public_requests.php?id='.$id);exit; } catch(Throwable $e){setEventMessages($langs->trans($e->getMessage()),null,'errors');}
}
if ($action === 'create_reviewed_lead') {
    cpo_require($user, 'publicrequest_qualify');
    try { $lifecycleService->createReviewedLead($id, GETPOST('lead_name','alphanohtml'), $user); setEventMessages($langs->trans('PublicLeadCreated'),null,'mesgs'); header('Location: public_requests.php?id='.$id);exit; } catch(Throwable $e){setEventMessages($langs->trans($e->getMessage()),null,'errors');}
}
if ($action === 'set_customer_visibility') {
    cpo_require($user, 'publicrequest_publish');
    try { $lifecycleService->setVisibility($id, GETPOSTINT('request_visible')===1, GETPOSTINT('proposal_visible')===1, $user); setEventMessages($langs->trans('PublicVisibilitySaved'),null,'mesgs'); header('Location: public_requests.php?id='.$id);exit; } catch(Throwable $e){setEventMessages($langs->trans($e->getMessage()),null,'errors');}
}
if ($action === 'publish_reply') {
    cpo_require($user, 'publicrequest_publish');
    try { $publicationService->publishReply($id, GETPOST('public_reply','restricthtml'), $user); setEventMessages($langs->trans('PublicReplyPublished'),null,'mesgs'); header('Location: public_requests.php?id='.$id);exit; } catch(Throwable $e){setEventMessages($langs->trans($e->getMessage()),null,'errors');}
}
if ($action === 'publish_file') {
    cpo_require($user, 'publicrequest_publish');
    try { $publicationService->publishUpload($id, $_FILES['public_file'] ?? array(), $user); setEventMessages($langs->trans('PublicFilePublished'),null,'mesgs'); header('Location: public_requests.php?id='.$id);exit; } catch(Throwable $e){setEventMessages($langs->trans($e->getMessage()),null,'errors');}
}
if ($action === 'invite_portal') {
    cpo_require($user, 'publicrequest_invite');
    try { $invites=new PortalInvitationService($db);$invites->issue($id,$user,true);setEventMessages($langs->trans('PublicPortalInvitationSent'),null,'mesgs');header('Location: public_requests.php?id='.$id);exit; } catch(Throwable $e){setEventMessages($langs->trans($e->getMessage()),null,'errors');}
}
if ($action === 'revoke_portal_invitation') {
    cpo_require($user, 'publicrequest_invite');
    try { $invites=new PortalInvitationService($db);$invites->revokeForRequest($id,$user);setEventMessages($langs->trans('PublicPortalInvitationRevoked'),null,'mesgs');header('Location: public_requests.php?id='.$id);exit; } catch(Throwable $e){setEventMessages($langs->trans($e->getMessage()),null,'errors');}
}

if ($action === 'update_status') {
    cpo_require($user, 'publicrequest_update');
    try {
        $service->updateStatus($id, GETPOST('status', 'alphanohtml'), GETPOST('internal_reason', 'restricthtml'), (int) $user->id);
        setEventMessages($langs->trans('PublicRequestUpdated'), null, 'mesgs');
    } catch (Throwable $e) { setEventMessages($e->getMessage(), null, 'errors'); }
}
if ($action === 'send_customer_email') {
    cpo_require($user, 'publicrequest_update');
    cpo_require($user, 'email_write');
    $compose = 'email';
    try {
        $requestToContact = $service->get($id);
        if (!$requestToContact) throw new PublicRequestContactException('public_request_not_found');
        $sendResult = $contactService->sendEmail($requestToContact, GETPOST('email_subject', 'alphanohtml'), GETPOST('email_body', 'restricthtml'), $user);
        setEventMessages($langs->trans('PublicCustomerEmailSent', $requestToContact['email']), null, 'mesgs');
        if (empty($sendResult['history_recorded'])) setEventMessages($langs->trans('PublicCustomerEmailHistoryWarning'), null, 'warnings');
        header('Location: public_requests.php?id='.(int) $id); exit;
    } catch (Throwable $e) { setEventMessages($langs->trans($e->getMessage()), null, 'errors'); }
}
if ($action === 'promote') {
    cpo_require($user, 'publicrequest_promote');
    try {
        $operationId = $service->promote($id, GETPOSTINT('fk_soc'), (int) $user->id);
        header('Location: view.php?id='.$operationId); exit;
    } catch (Throwable $e) { setEventMessages($e->getMessage(), null, 'errors'); }
}
if ($action === 'anonymize_expired' && !empty($user->admin)) {
    try { setEventMessages($langs->trans('PublicRequestsAnonymized', $service->anonymizeExpired((int) $user->id)), null, 'mesgs'); }
    catch (Throwable $e) { setEventMessages($e->getMessage(), null, 'errors'); }
}

$status = GETPOST('status', 'alphanohtml');
$requestType = GETPOST('request_type', 'alphanohtml');
$selected = $id ? $service->get($id) : null;
$rows = $service->listing(array('status'=>$status, 'request_type'=>$requestType));
$companies = array();
$res = $db->query("SELECT rowid,nom FROM ".MAIN_DB_PREFIX."societe WHERE entity IN (0,".(int) $conf->entity.") AND status=1 ORDER BY nom LIMIT 500");
if ($res) while ($company = $db->fetch_object($res)) $companies[(int) $company->rowid] = $company->nom;
$matchReview = $selected && (int)($selected['schema_version'] ?? 1) === 2 && empty($selected['fk_soc']) ? $lifecycleService->matchCandidates($selected) : null;
$matchStatusLabels = array('none'=>'PublicMatchNone','exact_single'=>'PublicMatchExactSingle','ambiguous'=>'PublicMatchAmbiguous');
$packingSourceLabels = array(
    ''=>'PublicSourceChoose',
    'woocommerce_product'=>'PublicSourceWooProduct',
    'woocommerce_variation'=>'PublicSourceWooVariation',
    'dolibarr_product'=>'PublicSourceDolibarrProduct',
    'supplier_document'=>'PublicSourceApprovedSupplierDocument',
    'staff_measurement'=>'PublicSourceStaffMeasurement',
    'other_approved'=>'PublicSourceOtherApproved',
);

$request = $selected && isset($selected['request']) && is_array($selected['request']) ? $selected['request'] : array();
$details = isset($request['details']) && is_array($request['details']) ? $request['details'] : array();
$snapshot = $selected && isset($selected['snapshot']) && is_array($selected['snapshot']) ? $selected['snapshot'] : array();
$route = isset($snapshot['route']) && is_array($snapshot['route']) ? $snapshot['route'] : array();
$terms = isset($snapshot['terms']) && is_array($snapshot['terms']) ? $snapshot['terms'] : array();
$components = isset($snapshot['components']) && is_array($snapshot['components']) ? $snapshot['components'] : array();
$catalogLines = $selected && $selected['request_type'] === 'catalog_order' ? $quotationService->catalogLines($selected) : array();
$quoteProducts = $catalogLines ? $quotationService->products() : array();
$quoteContacts = $catalogLines ? $quotationService->contacts() : array();
$quoteCurrencies = $catalogLines ? $quotationService->currencies() : array();
$quoteProposal = null;
if ($selected && !empty($selected['fk_propal'])) {
    $quoteProposal = new Propal($db);
    if ($quoteProposal->fetch((int) $selected['fk_propal']) <= 0 || (int) $quoteProposal->entity !== (int) $conf->entity) $quoteProposal = null;
}
$quoteSocId = $quoteProposal ? (int) $quoteProposal->socid : (int) ($selected['fk_soc'] ?? 0);
$quoteCurrency = $quoteProposal ? strtoupper((string) $quoteProposal->multicurrency_code) : strtoupper((string) ($details['currency'] ?? getDolCurrency()));
if (!isset($quoteCurrencies[$quoteCurrency])) $quoteCurrency = strtoupper((string) getDolCurrency());
$quoteSelectedProducts = array();
foreach ((array) GETPOST('line_product', 'array:int') as $index=>$productId) $quoteSelectedProducts[(int) $index] = (int) $productId;
$preparedQuoteLines = $catalogLines ? $quotationService->prepareLines($selected, $quoteSocId, $quoteSelectedProducts, $quoteCurrency) : array();
$hasPackingGaps = false;
foreach ($preparedQuoteLines as $preparedQuoteLine) {
    if ((int) $preparedQuoteLine['units_per_carton'] < 1 || empty($preparedQuoteLine['packing_verified']) || (string)($preparedQuoteLine['review_status'] ?? '') !== 'ready') { $hasPackingGaps = true; break; }
}
$canQuote = $catalogLines && cpo_can($user, 'publicrequest_quote') && $user->hasRight('propal', 'creer') && $user->hasRight('product', 'read');
$canContactByEmail = $selected && cpo_can($user, 'publicrequest_update') && cpo_can($user, 'email_write') && filter_var((string) $selected['email'], FILTER_VALIDATE_EMAIL);
$whatsappUrl = $selected ? PublicRequestContactService::whatsappUrl($selected) : '';
$canContactByWhatsApp = $whatsappUrl !== '' && cpo_can($user, 'publicrequest_update');
$emailSubject = $selected ? PublicRequestContactService::defaultSubject($selected) : '';
$emailBody = $selected ? PublicRequestContactService::defaultBody($selected) : '';
if ($action === 'send_customer_email') {
    $emailSubject = GETPOST('email_subject', 'alphanohtml');
    $emailBody = GETPOST('email_body', 'restricthtml');
}
$morecss = array(
    cpo_asset('/custom/chinaplanetoperations/css/operations.css'),
    cpcc_admin_shell_asset('/custom/chinaplanetcustomercenter/css/admin_dashboard.css'),
);
llxHeader('', $langs->trans('PublicWebsiteRequests'), '', '', 0, 0, array(), $morecss);
cpcc_admin_shell_open('website_requests', $langs->trans('PublicWebsiteRequests'), $langs->trans('PublicWebsiteRequestsHelp'), 'fa-inbox');
?>
<div class="cpo-public-requests">
  <section class="cpo-panel cpo-request-filter-panel">
    <form method="get"><div class="cpo-filters">
      <label class="cpo-field"><span><?php echo $langs->trans('Status'); ?></span><select name="status"><option value=""><?php echo $langs->trans('All'); ?></option><?php foreach(PublicWebsiteRequestService::statuses() as $value){ ?><option value="<?php echo $value; ?>"<?php echo $status===$value?' selected':''; ?>><?php echo dol_escape_htmltag($value); ?></option><?php } ?></select></label>
      <label class="cpo-field"><span><?php echo $langs->trans('RequestType'); ?></span><select name="request_type"><option value=""><?php echo $langs->trans('All'); ?></option><?php foreach(PublicWebsiteRequestService::requestTypes() as $value){ ?><option value="<?php echo $value; ?>"<?php echo $requestType===$value?' selected':''; ?>><?php echo dol_escape_htmltag($value); ?></option><?php } ?></select></label>
      <div class="cpo-form-actions"><button class="button"><span class="fas fa-search" aria-hidden="true"></span> <?php echo $langs->trans('Search'); ?></button></div>
    </div></form>
  </section>

  <div class="div-table-responsive cpo-request-table-wrap"><table class="liste centpercent"><thead><tr><th><?php echo $langs->trans('Ref'); ?></th><th><?php echo $langs->trans('RequestType'); ?></th><th><?php echo $langs->trans('Contact'); ?></th><th><?php echo $langs->trans('Status'); ?></th><th><?php echo $langs->trans('Date'); ?></th></tr></thead><tbody>
  <?php foreach($rows as $row){ ?><tr<?php echo $id === (int) $row['rowid'] ? ' class="cpo-selected-row"' : ''; ?>><td><a href="?id=<?php echo (int) $row['rowid']; ?>"><?php echo dol_escape_htmltag($row['ref']); ?></a></td><td><?php echo dol_escape_htmltag($row['request_type']); ?></td><td><?php echo dol_escape_htmltag($row['contact_name'].($row['company_name']?' · '.$row['company_name']:'')); ?></td><td><span class="cpo-request-status"><?php echo dol_escape_htmltag($row['status']); ?></span></td><td><?php echo dol_escape_htmltag($row['date_creation']); ?></td></tr><?php } ?>
  </tbody></table></div>

  <?php if($selected){ ?>
  <section class="cpo-form-section cpo-request-detail">
    <p><a class="button" href="request_pdf.php?id=<?php echo (int)$selected['rowid']; ?>"><?php echo $langs->trans('PublicCataloguePdf'); ?></a></p>
    <header class="cpo-request-detail-head">
      <div>
        <span class="cpo-request-eyebrow"><?php echo $langs->trans('PublicRequestOverview'); ?></span>
        <h2><?php echo dol_escape_htmltag($selected['ref']); ?></h2>
        <div class="cpo-request-pills"><span class="cpo-request-pill is-type"><?php echo dol_escape_htmltag($selected['request_type']); ?></span><span class="cpo-request-pill is-status"><?php echo dol_escape_htmltag($selected['status']); ?></span></div>
      </div>
      <div class="cpo-request-date"><span class="fas fa-calendar-alt" aria-hidden="true"></span><small><?php echo $langs->trans('Date'); ?></small><strong><?php echo dol_escape_htmltag($selected['date_creation']); ?></strong></div>
    </header>

    <div class="cpo-request-section-grid">
      <article class="cpo-request-block">
        <h3><span class="fas fa-address-card" aria-hidden="true"></span><?php echo $langs->trans('PublicContactInformation'); ?></h3>
        <div class="cpo-request-field-grid">
          <?php echo cpo_public_request_field_html('name', $selected['contact_name'], $langs); ?>
          <?php echo cpo_public_request_field_html('company', $selected['company_name'], $langs); ?>
          <?php echo cpo_public_request_field_html('phone', $selected['phone'], $langs); ?>
          <?php echo cpo_public_request_field_html('email', $selected['email'], $langs); ?>
          <?php echo cpo_public_request_field_html('country_code', $selected['country_code'], $langs); ?>
        </div>
        <div class="cpo-contact-actions">
          <?php if($canContactByEmail){ ?><a class="button button-save" href="?id=<?php echo (int) $selected['rowid']; ?>&amp;compose=email#cpo-customer-email"><span class="fas fa-envelope" aria-hidden="true"></span> <?php echo $langs->trans('PublicReplyByEmail'); ?></a><?php } else { ?><span class="cpo-contact-unavailable"><span class="fas fa-envelope" aria-hidden="true"></span> <?php echo $langs->trans('PublicEmailUnavailable'); ?></span><?php } ?>
          <?php if($canContactByWhatsApp){ ?><a class="button cpo-whatsapp-button" href="<?php echo dol_escape_htmltag($whatsappUrl); ?>" target="_blank" rel="noopener noreferrer"><span class="fab fa-whatsapp" aria-hidden="true"></span> <?php echo $langs->trans('PublicOpenWhatsApp'); ?><span class="fas fa-external-link-alt cpo-external-icon" aria-hidden="true"></span></a><?php } else { ?><span class="cpo-contact-unavailable"><span class="fab fa-whatsapp" aria-hidden="true"></span> <?php echo $langs->trans('PublicWhatsAppUnavailable'); ?></span><?php } ?>
        </div>
      </article>

      <article class="cpo-request-block">
        <h3><span class="fas fa-clipboard-list" aria-hidden="true"></span><?php echo $langs->trans('RequestDetails'); ?></h3>
        <div class="cpo-request-field-grid">
          <?php echo cpo_public_request_field_html('locale', isset($request['locale']) ? strtoupper((string) $request['locale']) : '', $langs); ?>
          <?php foreach($details as $key=>$value) { if(in_array($key, array('lines','packing_plan'), true)) continue; echo cpo_public_request_field_html($key, $value, $langs, in_array($key, array('message','cargo_summary','required_services'), true)); } ?>
          <?php if(empty($details)){ ?><div class="cpo-request-empty-state"><span class="fas fa-info-circle" aria-hidden="true"></span><?php echo $langs->trans('PublicNoReadableDetails'); ?></div><?php } ?>
        </div>
      </article>
    </div>

    <?php if($compose === 'email' && $canContactByEmail){ ?>
    <article class="cpo-request-block cpo-customer-email" id="cpo-customer-email">
      <div class="cpo-customer-email-head"><span class="fas fa-reply" aria-hidden="true"></span><div><h3><?php echo $langs->trans('PublicReplyToCustomer'); ?></h3><p><?php echo $langs->trans('PublicReplyToCustomerHelp', $selected['email']); ?></p></div></div>
      <form method="post">
        <input type="hidden" name="token" value="<?php echo newToken(); ?>">
        <input type="hidden" name="action" value="send_customer_email">
        <input type="hidden" name="id" value="<?php echo (int) $selected['rowid']; ?>">
        <div class="cpo-customer-email-meta"><span><small><?php echo $langs->trans('From'); ?></small><strong><?php echo dol_escape_htmltag((string) getDolGlobalString('MAIN_MAIL_EMAIL_FROM')); ?></strong></span><span><small><?php echo $langs->trans('MailTo'); ?></small><strong><?php echo dol_escape_htmltag($selected['email']); ?></strong></span></div>
        <label><span><?php echo $langs->trans('MailTopic'); ?> *</span><input name="email_subject" maxlength="255" required value="<?php echo dol_escape_htmltag($emailSubject); ?>"></label>
        <label><span><?php echo $langs->trans('Message'); ?> *</span><textarea name="email_body" rows="9" maxlength="20000" required><?php echo dol_escape_htmltag($emailBody); ?></textarea></label>
        <div class="cpo-customer-email-actions"><p><span class="fas fa-shield-alt" aria-hidden="true"></span><?php echo $langs->trans(!empty($selected['fk_propal']) ? 'PublicEmailTrackedToQuotation' : 'PublicEmailTrackedToRequest'); ?></p><a class="button" href="?id=<?php echo (int) $selected['rowid']; ?>"><?php echo $langs->trans('Cancel'); ?></a><button class="button button-save" type="submit"><span class="fas fa-paper-plane" aria-hidden="true"></span> <?php echo $langs->trans('PublicSendCustomerEmail'); ?></button></div>
      </form>
    </article>
    <?php } ?>

    <?php if(!empty($preparedQuoteLines)){
      $postedSources = (array) GETPOST('line_price_source', 'array:aZ09');
      $postedManualPrices = (array) GETPOST('line_manual_price', 'array:alphanohtml');
      $postedStaffNotes = (array) GETPOST('line_staff_note', 'array:restricthtml');
      $postedPublicDescriptions = (array) GETPOST('line_public_description', 'array:restricthtml');
      $quoteIsDraft = !$quoteProposal || (int) $quoteProposal->status === Propal::STATUS_DRAFT;
      $quotePublicValue = $quoteProposal ? (string) $quoteProposal->note_public : '';
      $quotePrivateValue = $quoteProposal ? (string) $quoteProposal->note_private : '';
      $privatePrefix = 'Website request: '.$selected['ref'];
      if(strpos($quotePrivateValue, $privatePrefix) === 0) $quotePrivateValue = ltrim(substr($quotePrivateValue, strlen($privatePrefix)));
      $quoteContactId = 0;
      if($quoteProposal){ $linkedContacts = $quoteProposal->getIdContact('external', 'CUSTOMER'); if(!empty($linkedContacts[0])) $quoteContactId = (int) $linkedContacts[0]; }
    ?>
    <article class="cpo-request-block cpo-quote-workspace">
      <div class="cpo-quote-heading">
        <div><span class="fas fa-file-signature" aria-hidden="true"></span><div><h3><?php echo $langs->trans('PublicQuotationWorkspace'); ?></h3><p><?php echo $langs->trans('PublicQuotationWorkspaceHelp'); ?></p></div></div>
        <?php if($quoteProposal){ ?><a class="button button-save" href="<?php echo DOL_URL_ROOT; ?>/comm/propal/card.php?id=<?php echo (int) $quoteProposal->id; ?>"><span class="fas fa-external-link-alt" aria-hidden="true"></span> <?php echo $langs->trans('PublicOpenQuotation', $quoteProposal->ref); ?></a><?php } ?>
      </div>

      <?php if($quoteProposal){ ?><div class="cpo-quote-status"><span class="fas fa-link" aria-hidden="true"></span><strong><?php echo dol_escape_htmltag($quoteProposal->ref); ?></strong><span><?php echo $quoteProposal->getLibStatut(1); ?></span><span><?php echo price($quoteProposal->multicurrency_total_ttc, 0, $langs, 1, -1, -1, $quoteProposal->multicurrency_code); ?></span></div><?php } ?>

      <form method="post" class="cpo-quote-form">
        <input type="hidden" name="token" value="<?php echo newToken(); ?>">
        <input type="hidden" name="action" value="save_quotation">
        <input type="hidden" name="id" value="<?php echo (int) $selected['rowid']; ?>">
        <div class="cpo-quote-controls">
          <label class="cpo-field"><span><?php echo $langs->trans('Customer'); ?> *</span>
            <?php if($quoteProposal || ((int)($selected['schema_version'] ?? 1) === 2 && !empty($selected['fk_soc']))){ ?><input type="hidden" name="quote_fk_soc" value="<?php echo (int) $quoteSocId; ?>"><select disabled><option><?php echo dol_escape_htmltag(isset($companies[$quoteSocId]) ? $companies[$quoteSocId] : '#'.$quoteSocId); ?></option></select><?php } else { ?>
            <select name="quote_fk_soc" id="cpo-quote-customer" required<?php echo !$canQuote || !$quoteIsDraft ? ' disabled' : ''; ?>><option value=""><?php echo $langs->trans('SelectThirdParty'); ?></option><?php foreach($companies as $companyId=>$companyName){ ?><option value="<?php echo $companyId; ?>"<?php echo $quoteSocId===$companyId?' selected':''; ?>><?php echo dol_escape_htmltag($companyName); ?></option><?php } ?></select><?php } ?>
          </label>
          <label class="cpo-field"><span><?php echo $langs->trans('PublicQuotationContact'); ?></span><?php if((int)($selected['schema_version'] ?? 1) === 2 && !empty($selected['fk_socpeople'])){ ?><input type="hidden" name="quote_contact_id" value="<?php echo (int)$selected['fk_socpeople']; ?>"><select disabled><option><?php echo '#'.(int)$selected['fk_socpeople']; ?></option></select><?php }else{ ?><select name="quote_contact_id" id="cpo-quote-contact"<?php echo !$canQuote || !$quoteIsDraft ? ' disabled' : ''; ?>><option value="0"><?php echo $langs->trans('PublicNoSpecificContact'); ?></option><?php foreach($quoteContacts as $contact){ if($quoteProposal && (int)$contact['fk_soc']!==$quoteSocId) continue; ?><option value="<?php echo (int) $contact['id']; ?>" data-soc="<?php echo (int) $contact['fk_soc']; ?>"<?php echo $quoteContactId===(int)$contact['id']?' selected':''; ?>><?php echo dol_escape_htmltag($contact['name'].($contact['email']?' · '.$contact['email']:'')); ?></option><?php } ?></select><?php } ?></label>
          <label class="cpo-field"><span><?php echo $langs->trans('Currency'); ?> *</span><?php if($quoteProposal){ ?><input type="hidden" name="quote_currency" value="<?php echo dol_escape_htmltag($quoteCurrency); ?>"><select disabled><option><?php echo dol_escape_htmltag($quoteCurrency); ?></option></select><?php } else { ?><select name="quote_currency" required<?php echo !$canQuote || !$quoteIsDraft ? ' disabled' : ''; ?>><?php foreach($quoteCurrencies as $code){ ?><option value="<?php echo dol_escape_htmltag($code); ?>"<?php echo $quoteCurrency===$code?' selected':''; ?>><?php echo dol_escape_htmltag($code); ?></option><?php } ?></select><?php } ?></label>
        </div>

        <div class="div-table-responsive cpo-quote-table-wrap"><table class="liste centpercent cpo-quote-table">
          <thead><tr><th><?php echo $langs->trans('Image'); ?></th><th><?php echo $langs->trans('Product'); ?></th><th><?php echo $langs->trans('PublicPacking'); ?></th><th><?php echo $langs->trans('PublicDolibarrProduct'); ?></th><?php if($canQuote){ ?><th><?php echo $langs->trans('PublicPriceSource'); ?></th><th><?php echo $langs->trans('PublicUnitSellingPrice'); ?></th><th><?php echo $langs->trans('Notes'); ?></th><?php } ?></tr></thead>
          <tbody><?php foreach($preparedQuoteLines as $line){
            $lineIndex = (int) $line['index'];
            $mappedId = !empty($line['product']) ? (int) $line['product']->id : 0;
            $priceSource = isset($postedSources[$lineIndex]) ? $postedSources[$lineIndex] : ($quoteProposal ? 'manual' : 'dolibarr');
            $manualValue = isset($postedManualPrices[$lineIndex]) ? $postedManualPrices[$lineIndex] : ($line['saved_price'] !== null ? $line['saved_price'] : ($line['default_price'] !== null ? $line['default_price'] : ''));
            $staffNote = isset($postedStaffNotes[$lineIndex]) ? $postedStaffNotes[$lineIndex] : (string)($line['staff_note'] ?? '');
            $publicDescription = isset($postedPublicDescriptions[$lineIndex]) ? $postedPublicDescriptions[$lineIndex] : (string)($line['saved_public_description'] ?? '');
          ?><tr>
            <td class="cpo-quote-image-cell"><?php echo cpo_public_request_product_image_html($line); ?></td>
            <td data-title="<?php echo dol_escape_htmltag($langs->trans('Product')); ?>"><strong><?php echo dol_escape_htmltag($line['name'] ?: $langs->trans('PublicLegacyProduct', $line['product_id'])); ?></strong><?php if($line['option']){ ?><small><?php echo dol_escape_htmltag($line['option']); ?></small><?php } ?><span dir="ltr"><?php echo dol_escape_htmltag($line['sku'] ?: 'Woo #'.$line['product_id'].($line['variation_id']?' / '.$line['variation_id']:'')); ?></span></td>
            <td data-title="<?php echo dol_escape_htmltag($langs->trans('PublicPacking')); ?>"><strong><?php echo (int) $line['cartons']; ?> <?php echo $langs->trans('PublicCartons'); ?></strong><?php if(!empty($line['working_line_id']) && $canQuote && $quoteIsDraft){ ?><input type="hidden" name="line_working_id[<?php echo $lineIndex; ?>]" value="<?php echo (int)$line['working_line_id']; ?>"><label><?php echo $langs->trans('PublicUnitsPerCarton'); ?> <input type="number" min="1" name="line_units_per_carton[<?php echo $lineIndex; ?>]" value="<?php echo $line['units_per_carton']?:''; ?>"></label><label><?php echo $langs->trans('PublicCbmPerCarton'); ?> <input type="number" min="0.000001" step="0.000001" name="line_cbm_per_carton[<?php echo $lineIndex; ?>]" value="<?php echo $line['cbm_per_carton']?:''; ?>"></label><label><?php echo $langs->trans('PublicKgPerCarton'); ?> <input type="number" min="0.000001" step="0.001" name="line_gross_weight_kg[<?php echo $lineIndex; ?>]" value="<?php echo $line['gross_weight']?:''; ?>"></label><label><?php echo $langs->trans('PublicVerificationSource'); ?><select name="line_verification_source[<?php echo $lineIndex; ?>]"><?php $selectedPackingSource=(string)($line['verified_packing_source']??'');foreach($packingSourceLabels as $sourceValue=>$sourceKey){ ?><option value="<?php echo dol_escape_htmltag($sourceValue); ?>"<?php echo $selectedPackingSource===$sourceValue?' selected':''; ?>><?php echo dol_escape_htmltag($langs->trans($sourceKey)); ?></option><?php } ?></select></label><label><input type="checkbox" name="line_packing_verified[<?php echo $lineIndex; ?>]" value="1"<?php echo !empty($line['packing_verified'])?' checked':''; ?>> <?php echo $langs->trans('PublicPackingVerified'); ?></label><?php }elseif($line['total_units']){ ?><small><?php echo (int)$line['cartons'].' × '.(int)$line['units_per_carton'].' '.$langs->trans('PublicPiecesPerCarton').' = '.(int)$line['total_units'].' '.$langs->trans('PublicPieces'); ?></small><?php }else{ ?><small class="error"><?php echo $langs->trans('PublicPackingRequired'); ?></small><?php } ?></td>
            <td data-title="<?php echo dol_escape_htmltag($langs->trans('PublicDolibarrProduct')); ?>"><?php if($canQuote && $quoteIsDraft){ ?><select required name="line_product[<?php echo $lineIndex; ?>]"><option value=""><?php echo $langs->trans('PublicSelectProductMapping'); ?></option><?php foreach($quoteProducts as $productOption){ ?><option value="<?php echo (int) $productOption['id']; ?>"<?php echo $mappedId===(int)$productOption['id']?' selected':''; ?>><?php echo dol_escape_htmltag($productOption['ref'].' · '.$productOption['label']); ?></option><?php } ?></select><?php } elseif($line['product']){ ?><a href="<?php echo DOL_URL_ROOT; ?>/product/card.php?id=<?php echo (int)$line['product']->id; ?>"><?php echo dol_escape_htmltag($line['product']->ref.' · '.$line['product']->label); ?></a><?php } else { ?><span class="cpo-quote-unmapped"><?php echo $langs->trans('PublicProductNotMapped'); ?></span><?php } ?></td>
            <?php if($canQuote){ ?><td data-title="<?php echo dol_escape_htmltag($langs->trans('PublicPriceSource')); ?>"><select name="line_price_source[<?php echo $lineIndex; ?>]"<?php echo !$quoteIsDraft?' disabled':''; ?>><option value="dolibarr"<?php echo $priceSource==='dolibarr'?' selected':''; ?>><?php echo $langs->trans('PublicDolibarrSellingPrice'); ?><?php echo $line['default_price']!==null?' · '.price($line['default_price']).' '.$quoteCurrency:''; ?></option><option value="manual"<?php echo $priceSource==='manual'?' selected':''; ?>><?php echo $langs->trans('PublicManualSellingPrice'); ?></option></select></td>
            <td data-title="<?php echo dol_escape_htmltag($langs->trans('PublicUnitSellingPrice')); ?>"><input name="line_manual_price[<?php echo $lineIndex; ?>]" type="number" min="0" max="1000000000" step="0.000001" value="<?php echo dol_escape_htmltag($manualValue); ?>"<?php echo !$quoteIsDraft?' disabled':''; ?>><small><?php echo dol_escape_htmltag($quoteCurrency).' / '.$langs->trans('PublicPiece'); ?></small></td>
            <td data-title="<?php echo dol_escape_htmltag($langs->trans('PublicQuotationLineNotes')); ?>"><label><?php echo $langs->trans('PublicQuotationLinePrivateNote'); ?><textarea name="line_staff_note[<?php echo $lineIndex; ?>]" rows="2" maxlength="1000"<?php echo !$quoteIsDraft?' disabled':''; ?>><?php echo dol_escape_htmltag($staffNote); ?></textarea></label><small><?php echo $langs->trans('PublicQuotationLinePrivateNoteHelp'); ?></small><label><?php echo $langs->trans('PublicQuotationLinePublicDescription'); ?><textarea name="line_public_description[<?php echo $lineIndex; ?>]" rows="2" maxlength="1000"<?php echo !$quoteIsDraft?' disabled':''; ?>><?php echo dol_escape_htmltag($publicDescription); ?></textarea></label><small><?php echo $langs->trans('PublicQuotationLinePublicDescriptionHelp'); ?></small></td><?php } ?>
          </tr><?php } ?></tbody>
        </table></div>

        <?php if($canQuote){ ?><div class="cpo-quote-notes"><label><span><?php echo $langs->trans('PublicQuotationPublicNote'); ?></span><textarea name="quote_note_public" rows="3" maxlength="10000"<?php echo !$quoteIsDraft?' disabled':''; ?>><?php echo dol_escape_htmltag(GETPOST('quote_note_public','restricthtml') ?: $quotePublicValue); ?></textarea><small><?php echo $langs->trans('PublicQuotationPublicNoteHelp'); ?></small></label><label><span><?php echo $langs->trans('PublicQuotationPrivateNote'); ?></span><textarea name="quote_note_private" rows="3" maxlength="10000"<?php echo !$quoteIsDraft?' disabled':''; ?>><?php echo dol_escape_htmltag(GETPOST('quote_note_private','restricthtml') ?: $quotePrivateValue); ?></textarea><small><?php echo $langs->trans('PublicQuotationPrivateNoteHelp'); ?></small></label></div><?php } ?>
        <?php if($canQuote && $quoteIsDraft){ ?><div class="cpo-quote-actions"><p><span class="fas fa-shield-alt" aria-hidden="true"></span><?php echo $langs->trans($hasPackingGaps?'PublicPackingRequired':'PublicQuotationStandardWorkflowHelp'); ?></p><button class="button button-save"<?php echo $hasPackingGaps?' disabled':''; ?>><span class="fas fa-save" aria-hidden="true"></span> <?php echo $langs->trans($quoteProposal?'PublicUpdateDraftQuotation':'PublicCreateDraftQuotation'); ?></button></div><?php } elseif($quoteProposal && !$quoteIsDraft){ ?><div class="cpo-space-notice"><span class="fas fa-lock" aria-hidden="true"></span><div><strong><?php echo $langs->trans('PublicQuotationLocked'); ?></strong><p><?php echo $langs->trans('PublicQuotationLockedHelp'); ?></p></div></div><?php } ?>
      </form>
    </article>
    <?php } ?>

    <?php if(!empty($snapshot)){ ?>
    <article class="cpo-request-block cpo-commercial-card">
      <div class="cpo-commercial-head">
        <div><span class="fas fa-file-invoice-dollar" aria-hidden="true"></span><h3><?php echo $langs->trans('CommercialSnapshot'); ?></h3></div>
        <span class="cpo-request-lock"><span class="fas fa-lock" aria-hidden="true"></span><?php echo $langs->trans('PublicImmutableEvidence'); ?></span>
      </div>

      <?php if(!empty($route)){ ?>
      <div class="cpo-route-card">
        <div class="cpo-route-port"><small><?php echo $langs->trans('PublicFieldPOL'); ?></small><strong><?php echo dol_escape_htmltag(isset($route['pol']) ? $route['pol'] : '—'); ?></strong></div>
        <span class="cpo-route-arrow fas fa-long-arrow-alt-right" aria-hidden="true"></span>
        <div class="cpo-route-port"><small><?php echo $langs->trans('PublicFieldPOD'); ?></small><strong><?php echo dol_escape_htmltag(isset($route['pod']) ? $route['pod'] : '—'); ?></strong><span><?php echo dol_escape_htmltag(isset($route['country_code']) ? $route['country_code'] : ''); ?></span></div>
        <div class="cpo-route-facts">
          <?php echo cpo_public_request_field_html('public_carrier_label', isset($route['public_carrier_label']) ? $route['public_carrier_label'] : '', $langs); ?>
          <?php echo cpo_public_request_field_html('container_type', isset($route['container_type']) ? $route['container_type'] : '', $langs); ?>
          <?php echo cpo_public_request_field_html('service_type', isset($route['service_type']) ? $route['service_type'] : '', $langs); ?>
        </div>
      </div>
      <?php } ?>

      <div class="cpo-price-grid">
        <div><small><?php echo $langs->trans('PublicFieldUnitPrice'); ?></small><strong><?php echo cpo_public_request_money(isset($snapshot['unit_price']) ? $snapshot['unit_price'] : '', isset($snapshot['currency']) ? $snapshot['currency'] : ''); ?></strong><span><?php echo $langs->trans('PublicPerContainer'); ?></span></div>
        <div><small><?php echo $langs->trans('PublicFieldContainerQuantity'); ?></small><strong><?php echo dol_escape_htmltag(isset($snapshot['container_quantity']) ? $snapshot['container_quantity'] : '—'); ?></strong><span><?php echo dol_escape_htmltag(isset($route['container_type']) ? $route['container_type'] : ''); ?></span></div>
        <div class="is-total"><small><?php echo $langs->trans('PublicFieldTotal'); ?></small><strong><?php echo cpo_public_request_money(isset($snapshot['total']) ? $snapshot['total'] : '', isset($snapshot['currency']) ? $snapshot['currency'] : ''); ?></strong><span><?php echo $langs->trans('PublicAcceptedCustomerPrice'); ?></span></div>
      </div>

      <div class="cpo-request-field-grid cpo-validity-grid">
        <?php echo cpo_public_request_field_html('valid_from', isset($snapshot['valid_from']) ? $snapshot['valid_from'] : '', $langs); ?>
        <?php echo cpo_public_request_field_html('valid_to', isset($snapshot['valid_to']) ? $snapshot['valid_to'] : '', $langs); ?>
        <?php echo cpo_public_request_field_html('acknowledged_at', isset($snapshot['acknowledged_at']) ? $snapshot['acknowledged_at'] : '', $langs); ?>
      </div>

      <?php if(!empty($components)){ ?><section class="cpo-commercial-subsection"><h4><?php echo $langs->trans('PublicPriceComponents'); ?></h4><div class="cpo-price-components"><?php foreach($components as $component){ if(!is_array($component)) continue; ?><div><span><?php echo dol_escape_htmltag(isset($component['label']) ? $component['label'] : cpo_public_request_label('type', $langs)); ?></span><strong><?php echo cpo_public_request_money(isset($component['amount']) ? $component['amount'] : '', isset($component['currency']) ? $component['currency'] : (isset($snapshot['currency']) ? $snapshot['currency'] : '')); ?></strong></div><?php } ?></div></section><?php } ?>

      <div class="cpo-terms-grid">
        <section class="is-included"><h4><span class="fas fa-check-circle" aria-hidden="true"></span><?php echo $langs->trans('PublicFieldIncluded'); ?></h4><?php echo cpo_public_request_value_html(isset($terms['included']) ? $terms['included'] : array(), $langs); ?></section>
        <section class="is-excluded"><h4><span class="fas fa-minus-circle" aria-hidden="true"></span><?php echo $langs->trans('PublicFieldExcluded'); ?></h4><?php echo cpo_public_request_value_html(isset($terms['excluded']) ? $terms['excluded'] : array(), $langs); ?></section>
        <?php if(!empty($terms['warnings'])){ ?><section class="is-warning"><h4><span class="fas fa-exclamation-triangle" aria-hidden="true"></span><?php echo $langs->trans('PublicFieldWarnings'); ?></h4><?php echo cpo_public_request_value_html($terms['warnings'], $langs); ?></section><?php } ?>
        <?php if(!empty($terms['notes'])){ ?><section class="is-notes"><h4><span class="fas fa-sticky-note" aria-hidden="true"></span><?php echo $langs->trans('PublicFieldNotes'); ?></h4><?php echo cpo_public_request_value_html($terms['notes'], $langs); ?></section><?php } ?>
      </div>

      <?php if(array_key_exists('space_confirmation', $snapshot) && empty($snapshot['space_confirmation'])){ ?><div class="cpo-space-notice"><span class="fas fa-info-circle" aria-hidden="true"></span><div><strong><?php echo $langs->trans('PublicCarrierSpaceNotConfirmed'); ?></strong><p><?php echo $langs->trans('PublicCarrierSpaceNotConfirmedHelp'); ?></p></div></div><?php } ?>
    </article>
    <?php } ?>

    <?php if((int)($selected['schema_version'] ?? 1) === 2){ ?>
    <article class="cpo-request-block cpo-lifecycle-workspace">
      <h3><span class="fas fa-user-check" aria-hidden="true"></span><?php echo $langs->trans('PublicLeadQualification'); ?></h3>
      <?php if(empty($selected['fk_soc']) && cpo_can($user,'publicrequest_qualify')){ ?>
        <p><?php echo $langs->trans('PublicMatchStatus'); ?>: <strong><?php echo dol_escape_htmltag($langs->trans($matchStatusLabels[$matchReview['status'] ?? 'none'] ?? 'PublicMatchNone')); ?></strong></p>
        <?php if(!empty($matchReview['candidates'])){ ?><ul><?php foreach($matchReview['candidates'] as $candidate){ ?><li><?php echo dol_escape_htmltag(($candidate['nom'] ?? '').' · '.trim(($candidate['firstname'] ?? '').' '.($candidate['lastname'] ?? '')).' · '.($candidate['email'] ?? '').(!empty($candidate['company_identity_match'])?' · '.$langs->trans('PublicThirdPartyIdentityMatch'):'')); ?></li><?php } ?></ul><?php } ?>
        <form method="post" class="cpo-request-action-form"><input type="hidden" name="token" value="<?php echo newToken(); ?>"><input type="hidden" name="action" value="qualify_existing"><input type="hidden" name="id" value="<?php echo (int)$selected['rowid']; ?>"><label><span><?php echo $langs->trans('Customer'); ?></span><select id="cpo-qualify-customer" name="fk_soc" required><option value=""><?php echo $langs->trans('SelectThirdParty'); ?></option><?php foreach($companies as $companyId=>$companyName){ ?><option value="<?php echo $companyId; ?>"><?php echo dol_escape_htmltag($companyName); ?></option><?php } ?></select></label><label><span><?php echo $langs->trans('PublicQuotationContact'); ?></span><select id="cpo-qualify-contact" name="fk_socpeople" required><option value=""><?php echo $langs->trans('PublicChooseCustomerFirst'); ?></option><?php foreach($quoteContacts as $contact){ ?><option value="<?php echo (int)$contact['id']; ?>" data-soc="<?php echo (int)$contact['fk_soc']; ?>"><?php echo dol_escape_htmltag($contact['name'].' · '.$contact['email'].' · '.($companies[(int)$contact['fk_soc']] ?? $langs->trans('Customer'))); ?></option><?php } ?></select></label><button class="button button-save"><?php echo $langs->trans('PublicQualifyExisting'); ?></button></form>
        <?php if(($matchReview['status'] ?? '') === 'none'){ ?><form method="post" class="cpo-request-action-form"><input type="hidden" name="token" value="<?php echo newToken(); ?>"><input type="hidden" name="action" value="create_reviewed_lead"><input type="hidden" name="id" value="<?php echo (int)$selected['rowid']; ?>"><label class="is-grow"><span><?php echo $langs->trans('ThirdPartyName'); ?></span><input name="lead_name" maxlength="190" value="<?php echo dol_escape_htmltag($selected['company_name'] ?: $selected['contact_name']); ?>" required></label><button class="button"><?php echo $langs->trans('PublicCreateReviewedLead'); ?></button></form><?php }else{ ?><div class="warning"><?php echo $langs->trans('PublicMatchRequiresChoice'); ?></div><?php } ?>
      <?php }else{ ?><p><strong><?php echo $langs->trans('PublicQualified'); ?></strong> · <?php echo dol_escape_htmltag($companies[(int)$selected['fk_soc']] ?? ('#'.(int)$selected['fk_soc'])); ?></p><?php } ?>
    </article>

    <?php if(!empty($selected['fk_soc'])){ ?><article class="cpo-request-block cpo-customer-publication">
      <h3><?php echo $langs->trans('PublicCustomerCenterVisibility'); ?></h3>
      <?php if(cpo_can($user,'publicrequest_publish')){ ?><form method="post" class="cpo-request-action-form"><input type="hidden" name="token" value="<?php echo newToken(); ?>"><input type="hidden" name="action" value="set_customer_visibility"><input type="hidden" name="id" value="<?php echo (int)$selected['rowid']; ?>"><label><input type="checkbox" name="request_visible" value="1"<?php echo !empty($selected['request_customer_visible'])?' checked':''; ?>> <?php echo $langs->trans('PublicShowRequestInPortal'); ?></label><label><input type="checkbox" name="proposal_visible" value="1"<?php echo !empty($selected['proposal_customer_visible'])?' checked':''; ?><?php echo empty($selected['fk_propal'])?' disabled':''; ?>> <?php echo $langs->trans('PublicShowProposalInPortal'); ?></label><button class="button"><?php echo $langs->trans('Save'); ?></button></form>
      <form method="post" class="cpo-request-action-form"><input type="hidden" name="token" value="<?php echo newToken(); ?>"><input type="hidden" name="action" value="publish_reply"><input type="hidden" name="id" value="<?php echo (int)$selected['rowid']; ?>"><label class="is-grow"><span><?php echo $langs->trans('PublicApprovedReply'); ?></span><textarea name="public_reply" maxlength="10000" required></textarea></label><button class="button"><?php echo $langs->trans('Publish'); ?></button></form>
      <form method="post" enctype="multipart/form-data" class="cpo-request-action-form"><input type="hidden" name="token" value="<?php echo newToken(); ?>"><input type="hidden" name="action" value="publish_file"><input type="hidden" name="id" value="<?php echo (int)$selected['rowid']; ?>"><input type="hidden" name="MAX_FILE_SIZE" value="10485760"><label><span><?php echo $langs->trans('PublicApprovedFile'); ?></span><input type="file" name="public_file" accept="application/pdf,image/jpeg,image/png,image/webp" required></label><button class="button"><?php echo $langs->trans('Publish'); ?></button></form><?php } ?>
      <?php if(cpo_can($user,'publicrequest_invite')){ ?><form method="post" class="cpo-request-action-form"><input type="hidden" name="token" value="<?php echo newToken(); ?>"><input type="hidden" name="action" value="invite_portal"><input type="hidden" name="id" value="<?php echo (int)$selected['rowid']; ?>"><button class="button button-save"><?php echo $langs->trans('PublicInvitePortal'); ?></button></form><form method="post" class="cpo-request-action-form"><input type="hidden" name="token" value="<?php echo newToken(); ?>"><input type="hidden" name="action" value="revoke_portal_invitation"><input type="hidden" name="id" value="<?php echo (int)$selected['rowid']; ?>"><button class="button"><?php echo $langs->trans('PublicRevokeInvitation'); ?></button></form><?php } ?>
    </article><?php } ?>
    <?php } ?>

    <div class="cpo-request-workflow">
      <?php if(cpo_can($user,'publicrequest_update') && empty($selected['fk_operation'])){ ?><form method="post" class="cpo-request-action-form"><input type="hidden" name="token" value="<?php echo newToken(); ?>"><input type="hidden" name="action" value="update_status"><input type="hidden" name="id" value="<?php echo (int) $selected['rowid']; ?>"><label><span><?php echo $langs->trans('Status'); ?></span><select name="status"><?php foreach(PublicWebsiteRequestService::statuses() as $value){ ?><option value="<?php echo $value; ?>"<?php echo $selected['status']===$value?' selected':''; ?>><?php echo dol_escape_htmltag($value); ?></option><?php } ?></select></label><label class="is-grow"><span><?php echo $langs->trans('InternalReason'); ?></span><input name="internal_reason" maxlength="255" placeholder="<?php echo dol_escape_htmltag($langs->trans('InternalReason')); ?>"></label><button class="button"><span class="fas fa-save" aria-hidden="true"></span> <?php echo $langs->trans('Save'); ?></button></form><?php } ?>
      <?php if(cpo_can($user,'publicrequest_promote') && empty($selected['fk_operation']) && (int)($selected['schema_version'] ?? 1) === 1){ ?><form method="post" class="cpo-request-action-form is-promote"><input type="hidden" name="token" value="<?php echo newToken(); ?>"><input type="hidden" name="action" value="promote"><input type="hidden" name="id" value="<?php echo (int) $selected['rowid']; ?>"><label class="is-grow"><span><?php echo $langs->trans('Customer'); ?></span><select required name="fk_soc"><option value=""><?php echo $langs->trans('SelectThirdParty'); ?></option><?php foreach($companies as $companyId=>$companyName){ ?><option value="<?php echo $companyId; ?>"><?php echo dol_escape_htmltag($companyName); ?></option><?php } ?></select></label><button class="button button-save"><span class="fas fa-arrow-right" aria-hidden="true"></span> <?php echo $langs->trans('PromoteToOperation'); ?></button></form><?php } ?>
    </div>
  </section><?php } ?>

  <?php if(!empty($user->admin)){ ?><form method="post" class="cpo-anonymize-form"><input type="hidden" name="token" value="<?php echo newToken(); ?>"><input type="hidden" name="action" value="anonymize_expired"><button class="button"><span class="fas fa-user-shield" aria-hidden="true"></span> <?php echo $langs->trans('AnonymizeExpiredRequests'); ?></button></form><?php } ?>
</div>
<script>
(function () {
  function bindContactFilter(customerId, contactId, emptyValue) {
    var customer = document.getElementById(customerId);
    var contact = document.getElementById(contactId);
    if (!customer || !contact) return;
    function filterContacts() {
      var soc = customer.value;
      Array.prototype.forEach.call(contact.options, function (option) {
        if (!option.dataset.soc) return;
        var allowed = soc !== '' && option.dataset.soc === soc;
        option.hidden = !allowed;
        option.disabled = !allowed;
        if (!allowed && option.selected) contact.value = emptyValue;
      });
    }
    customer.addEventListener('change', filterContacts);
    filterContacts();
  }
  bindContactFilter('cpo-quote-customer', 'cpo-quote-contact', '0');
  bindContactFilter('cpo-qualify-customer', 'cpo-qualify-contact', '');
}());
</script>
<?php cpcc_admin_shell_close(); llxFooter(); $db->close(); ?>
