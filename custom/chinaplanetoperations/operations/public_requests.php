<?php

define('CSRFCHECK_WITH_TOKEN', 1);
require $_SERVER['DOCUMENT_ROOT'].'/main.inc.php';
require_once __DIR__.'/../lib/main.lib.php';
require_once __DIR__.'/../class/PublicWebsiteRequestService.class.php';

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
$morecss = array(cpo_asset('/custom/chinaplanetoperations/css/operations.css'));
llxHeader('', $langs->trans('PublicWebsiteRequests'), '', '', 0, 0, array(), $morecss);
?>
<div class="fiche">
  <h1><?php echo $langs->trans('PublicWebsiteRequests'); ?></h1>
  <p class="opacitymedium"><?php echo $langs->trans('PublicWebsiteRequestsHelp'); ?></p>
  <section class="cpo-panel">
    <form method="get"><div class="cpo-filters">
      <label class="cpo-field"><span><?php echo $langs->trans('Status'); ?></span><select name="status"><option value=""><?php echo $langs->trans('All'); ?></option><?php foreach(PublicWebsiteRequestService::statuses() as $value){ ?><option value="<?php echo $value; ?>"<?php echo $status===$value?' selected':''; ?>><?php echo dol_escape_htmltag($value); ?></option><?php } ?></select></label>
      <label class="cpo-field"><span><?php echo $langs->trans('RequestType'); ?></span><select name="request_type"><option value=""><?php echo $langs->trans('All'); ?></option><?php foreach(PublicWebsiteRequestService::requestTypes() as $value){ ?><option value="<?php echo $value; ?>"<?php echo $requestType===$value?' selected':''; ?>><?php echo dol_escape_htmltag($value); ?></option><?php } ?></select></label>
      <div class="cpo-form-actions"><button class="button"><?php echo $langs->trans('Search'); ?></button></div>
    </div></form>
  </section>
  <div class="div-table-responsive"><table class="liste centpercent"><thead><tr><th><?php echo $langs->trans('Ref'); ?></th><th><?php echo $langs->trans('RequestType'); ?></th><th><?php echo $langs->trans('Contact'); ?></th><th><?php echo $langs->trans('Status'); ?></th><th><?php echo $langs->trans('Date'); ?></th></tr></thead><tbody>
  <?php foreach($rows as $row){ ?><tr><td><a href="?id=<?php echo (int) $row['rowid']; ?>"><?php echo dol_escape_htmltag($row['ref']); ?></a></td><td><?php echo dol_escape_htmltag($row['request_type']); ?></td><td><?php echo dol_escape_htmltag($row['contact_name'].($row['company_name']?' · '.$row['company_name']:'')); ?></td><td><?php echo dol_escape_htmltag($row['status']); ?></td><td><?php echo dol_escape_htmltag($row['date_creation']); ?></td></tr><?php } ?>
  </tbody></table></div>
  <?php if($selected){ ?>
  <section class="cpo-form-section">
    <h2><?php echo dol_escape_htmltag($selected['ref']); ?></h2>
    <dl><dt><?php echo $langs->trans('Contact'); ?></dt><dd><?php echo dol_escape_htmltag($selected['contact_name']); ?> · <?php echo dol_escape_htmltag($selected['company_name']); ?> · <?php echo dol_escape_htmltag($selected['phone']); ?> · <?php echo dol_escape_htmltag($selected['email']); ?></dd><dt><?php echo $langs->trans('RequestDetails'); ?></dt><dd><pre><?php echo dol_escape_htmltag(json_encode($selected['request'], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)); ?></pre></dd><?php if(!empty($selected['snapshot'])){ ?><dt><?php echo $langs->trans('CommercialSnapshot'); ?></dt><dd><pre><?php echo dol_escape_htmltag(json_encode($selected['snapshot'], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)); ?></pre></dd><?php } ?></dl>
    <?php if(cpo_can($user,'publicrequest_update') && empty($selected['fk_operation'])){ ?><form method="post"><input type="hidden" name="token" value="<?php echo newToken(); ?>"><input type="hidden" name="action" value="update_status"><input type="hidden" name="id" value="<?php echo (int) $selected['rowid']; ?>"><label><?php echo $langs->trans('Status'); ?> <select name="status"><?php foreach(PublicWebsiteRequestService::statuses() as $value){ ?><option value="<?php echo $value; ?>"<?php echo $selected['status']===$value?' selected':''; ?>><?php echo dol_escape_htmltag($value); ?></option><?php } ?></select></label> <input name="internal_reason" maxlength="255" placeholder="<?php echo dol_escape_htmltag($langs->trans('InternalReason')); ?>"> <button class="button"><?php echo $langs->trans('Save'); ?></button></form><?php } ?>
    <?php if(cpo_can($user,'publicrequest_promote') && empty($selected['fk_operation'])){ ?><form method="post"><input type="hidden" name="token" value="<?php echo newToken(); ?>"><input type="hidden" name="action" value="promote"><input type="hidden" name="id" value="<?php echo (int) $selected['rowid']; ?>"><label><?php echo $langs->trans('Customer'); ?> <select required name="fk_soc"><option value=""><?php echo $langs->trans('SelectThirdParty'); ?></option><?php foreach($companies as $companyId=>$companyName){ ?><option value="<?php echo $companyId; ?>"><?php echo dol_escape_htmltag($companyName); ?></option><?php } ?></select></label> <button class="button button-save"><?php echo $langs->trans('PromoteToOperation'); ?></button></form><?php } ?>
  </section><?php } ?>
  <?php if(!empty($user->admin)){ ?><form method="post"><input type="hidden" name="token" value="<?php echo newToken(); ?>"><input type="hidden" name="action" value="anonymize_expired"><button class="button"><?php echo $langs->trans('AnonymizeExpiredRequests'); ?></button></form><?php } ?>
</div>
<?php llxFooter(); $db->close(); ?>
