<?php

define('CSRFCHECK_WITH_TOKEN', 1);
require $_SERVER['DOCUMENT_ROOT'].'/main.inc.php';
require_once __DIR__.'/../lib/main.lib.php';
require_once __DIR__.'/../class/PublicWebsiteRequestService.class.php';
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

cpo_require($user, 'publicrequest_read');
$langs->load('chinaplanetoperations@chinaplanetoperations');
$service = new PublicWebsiteRequestService($db);
$action = GETPOST('action', 'aZ09');
$id = GETPOSTINT('id');

if ($action === 'update_status') {
    cpo_require($user, 'publicrequest_update');
    try {
        $service->updateStatus($id, GETPOST('status', 'alphanohtml'), GETPOST('internal_reason', 'restricthtml'), (int) $user->id);
        setEventMessages($langs->trans('PublicRequestUpdated'), null, 'mesgs');
    } catch (Throwable $e) { setEventMessages($e->getMessage(), null, 'errors'); }
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

$request = $selected && isset($selected['request']) && is_array($selected['request']) ? $selected['request'] : array();
$details = isset($request['details']) && is_array($request['details']) ? $request['details'] : array();
$snapshot = $selected && isset($selected['snapshot']) && is_array($selected['snapshot']) ? $selected['snapshot'] : array();
$route = isset($snapshot['route']) && is_array($snapshot['route']) ? $snapshot['route'] : array();
$terms = isset($snapshot['terms']) && is_array($snapshot['terms']) ? $snapshot['terms'] : array();
$components = isset($snapshot['components']) && is_array($snapshot['components']) ? $snapshot['components'] : array();
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
      </article>

      <article class="cpo-request-block">
        <h3><span class="fas fa-clipboard-list" aria-hidden="true"></span><?php echo $langs->trans('RequestDetails'); ?></h3>
        <div class="cpo-request-field-grid">
          <?php echo cpo_public_request_field_html('locale', isset($request['locale']) ? strtoupper((string) $request['locale']) : '', $langs); ?>
          <?php foreach($details as $key=>$value) echo cpo_public_request_field_html($key, $value, $langs, in_array($key, array('message','cargo_summary','lines','packing_plan','required_services'), true)); ?>
          <?php if(empty($details)){ ?><div class="cpo-request-empty-state"><span class="fas fa-info-circle" aria-hidden="true"></span><?php echo $langs->trans('PublicNoReadableDetails'); ?></div><?php } ?>
        </div>
      </article>
    </div>

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

    <div class="cpo-request-workflow">
      <?php if(cpo_can($user,'publicrequest_update') && empty($selected['fk_operation'])){ ?><form method="post" class="cpo-request-action-form"><input type="hidden" name="token" value="<?php echo newToken(); ?>"><input type="hidden" name="action" value="update_status"><input type="hidden" name="id" value="<?php echo (int) $selected['rowid']; ?>"><label><span><?php echo $langs->trans('Status'); ?></span><select name="status"><?php foreach(PublicWebsiteRequestService::statuses() as $value){ ?><option value="<?php echo $value; ?>"<?php echo $selected['status']===$value?' selected':''; ?>><?php echo dol_escape_htmltag($value); ?></option><?php } ?></select></label><label class="is-grow"><span><?php echo $langs->trans('InternalReason'); ?></span><input name="internal_reason" maxlength="255" placeholder="<?php echo dol_escape_htmltag($langs->trans('InternalReason')); ?>"></label><button class="button"><span class="fas fa-save" aria-hidden="true"></span> <?php echo $langs->trans('Save'); ?></button></form><?php } ?>
      <?php if(cpo_can($user,'publicrequest_promote') && empty($selected['fk_operation'])){ ?><form method="post" class="cpo-request-action-form is-promote"><input type="hidden" name="token" value="<?php echo newToken(); ?>"><input type="hidden" name="action" value="promote"><input type="hidden" name="id" value="<?php echo (int) $selected['rowid']; ?>"><label class="is-grow"><span><?php echo $langs->trans('Customer'); ?></span><select required name="fk_soc"><option value=""><?php echo $langs->trans('SelectThirdParty'); ?></option><?php foreach($companies as $companyId=>$companyName){ ?><option value="<?php echo $companyId; ?>"><?php echo dol_escape_htmltag($companyName); ?></option><?php } ?></select></label><button class="button button-save"><span class="fas fa-arrow-right" aria-hidden="true"></span> <?php echo $langs->trans('PromoteToOperation'); ?></button></form><?php } ?>
    </div>
  </section><?php } ?>

  <?php if(!empty($user->admin)){ ?><form method="post" class="cpo-anonymize-form"><input type="hidden" name="token" value="<?php echo newToken(); ?>"><input type="hidden" name="action" value="anonymize_expired"><button class="button"><span class="fas fa-user-shield" aria-hidden="true"></span> <?php echo $langs->trans('AnonymizeExpiredRequests'); ?></button></form><?php } ?>
</div>
<?php cpcc_admin_shell_close(); llxFooter(); $db->close(); ?>
